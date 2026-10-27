<?php
/*
 * An ADS-B receiver of your own on your home network (a Pi running ADSB.im,
 * PiAware, or readsb/dump1090 with tar1090): Air Defender reads the
 * aircraft.json it publishes. Nothing is sent to the internet and the show's
 * location is not sent anywhere: the receiver lists every aircraft it hears
 * and Air Defender works out which are near the show.
 *
 * The address is saved as receiverAddress: an IP address or hostname, with a
 * port if needed (192.168.1.50, adsb.local, 192.168.1.50:8080), or the full
 * URL of its aircraft.json. Only addresses on the home network are accepted.
 */

require_once(__DIR__ . "/airdefender_adsb.php");
require_once(__DIR__ . "/airdefender_feeds.php");   // AIRDEFENDER_FEED_RECEIVER

// Receiver data older than this means its decoder has probably stopped while
// its web server goes on serving the last file.
const AIRDEFENDER_RECEIVER_MAX_AGE_SEC = 120;

// Where the common receiver software publishes aircraft.json.
const AIRDEFENDER_RECEIVER_PATHS = [
    '/tar1090/data/aircraft.json',      // tar1090 (readsb, ADSB.im, most builds)
    '/skyaware/data/aircraft.json',     // PiAware / FlightAware SkyAware
    '/dump1090-fa/data/aircraft.json',  // older dump1090-fa
    '/dump1090/data/aircraft.json',
    '/data/aircraft.json',              // tar1090 on its own port (ADSB.im / ultrafeeder :8080)
];

/* Whether a feed setting is a receiver of your own (on this FPP or the network). */
function airdefender_is_receiver_feed(string $template): bool
{
    return $template === AIRDEFENDER_FEED_RECEIVER || stripos($template, 'http://localhost') === 0;
}

/* Whether an IP address is on a home network (private, loopback or link-local). */
function airdefender_is_local_ip(string $ip): bool
{
    return filter_var($ip, FILTER_VALIDATE_IP) !== false
        && filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
}

/* null when the host is on the home network, otherwise why not. */
function airdefender_receiver_host_error(string $host): ?string
{
    $host = trim($host, '[]');
    if ($host === '') {
        return "Enter the receiver's address in Settings";
    }
    if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
        return airdefender_is_local_ip($host) ? null
            : "{$host} is not on your home network; Air Defender only reads a receiver on your own network";
    }
    if (!preg_match('/^[A-Za-z0-9]([A-Za-z0-9.-]*[A-Za-z0-9])?$/', $host)) {
        return "\"{$host}\" is not a valid receiver address";
    }
    $ips = @gethostbynamel($host);
    if (!$ips) {
        return "Can't find the receiver {$host} on the network (try its IP address)";
    }
    foreach ($ips as $ip) {
        if (!airdefender_is_local_ip($ip)) {
            return "{$host} is not on your home network; Air Defender only reads a receiver on your own network";
        }
    }
    return null;
}

/*
 * The URLs to try for an address: [urls, error]. A full URL ending in .json is
 * used as it is; otherwise the usual paths, on the port given or on 80 and 8080.
 */
function airdefender_receiver_candidates(string $address): array
{
    $address = trim($address);
    if ($address === '') {
        return [[], "Enter the receiver's address in Settings"];
    }
    $url = preg_match('#^https?://#i', $address) ? $address : 'http://' . $address;
    $parts = parse_url($url);
    if (!$parts || empty($parts['host'])) {
        return [[], "\"{$address}\" is not a valid receiver address"];
    }
    $err = airdefender_receiver_host_error($parts['host']);
    if ($err !== null) {
        return [[], $err];
    }
    $scheme = strtolower($parts['scheme'] ?? 'http');
    $host = $parts['host'];
    $hostPart = strpos($host, ':') !== false ? "[{$host}]" : $host;   // IPv6
    $path = rtrim($parts['path'] ?? '', '/');
    if (preg_match('/\.json$/i', $path)) {
        return [[$url], null];
    }
    $bases = isset($parts['port']) ? ["{$scheme}://{$hostPart}:{$parts['port']}"] : ["{$scheme}://{$hostPart}", "{$scheme}://{$hostPart}:8080"];
    $urls = [];
    foreach ($bases as $base) {
        if ($path !== '') {
            $urls[] = $base . $path . '/data/aircraft.json';
        }
        foreach (AIRDEFENDER_RECEIVER_PATHS as $p) {
            $urls[] = $base . $p;
        }
    }
    return [array_values(array_unique($urls)), null];
}

/* Whether something answers on a URL's host and port, quickly. */
function airdefender_port_open(string $url, float $timeout = 2.0): bool
{
    $p = parse_url($url);
    $port = (int) ($p['port'] ?? (strtolower($p['scheme'] ?? 'http') === 'https' ? 443 : 80));
    $fh = @fsockopen(trim((string) $p['host'], '[]'), $port, $errno, $errstr, $timeout);
    if ($fh) {
        fclose($fh);
        return true;
    }
    return false;
}

/*
 * Aircraft from the receiver at $address: [planes, error]. $found is the URL
 * that answered last time (tried first) and is updated when it changes.
 */
function airdefender_fetch_receiver(string $address, ?string &$found = null): array
{
    [$urls, $err] = airdefender_receiver_candidates($address);
    if ($err !== null) {
        $found = null;
        return [null, $err];
    }
    if ($found !== null && in_array($found, $urls, true)) {
        [$planes, $err] = airdefender_fetch_feed($found, AIRDEFENDER_RECEIVER_MAX_AGE_SEC, 4);
        if ($planes !== null || strpos((string) $err, 'old') !== false) {
            return [$planes, $err];   // stale data is the receiver's problem, not the path's
        }
    }

    // Look for it: skip ports that do not answer, so a receiver that is off
    // costs a couple of seconds, not one timeout per path.
    $open = [];
    $lastErr = null;
    foreach ($urls as $url) {
        $p = parse_url($url);
        $key = $p['host'] . ':' . ($p['port'] ?? 80);
        if (!isset($open[$key])) {
            $open[$key] = airdefender_port_open($url);
        }
        if (!$open[$key]) {
            continue;
        }
        [$planes, $err] = airdefender_fetch_feed($url, AIRDEFENDER_RECEIVER_MAX_AGE_SEC, 4);
        if ($planes !== null) {
            $found = $url;
            return [$planes, null];
        }
        if (strpos((string) $err, 'old') !== false) {
            $found = $url;
            return [null, $err];
        }
        $lastErr = $err;
    }
    $found = null;
    $host = parse_url($urls[0], PHP_URL_HOST);
    if (!in_array(true, $open, true)) {
        return [null, "Can't reach the receiver at {$host}: check it is on and the address is right"];
    }
    return [null, "The receiver at {$host} answered, but no aircraft data was found at the usual places (tar1090, SkyAware, port 8080); enter the full address of its aircraft.json"];
}
