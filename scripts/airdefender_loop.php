<?php
/*
 * AirDefender detection loop.
 *
 * Started by scripts/postStart.sh when fppd starts, stopped by preStop.sh.
 * Every second it decides whether safety mode should be on and makes the
 * outputs match (see airdefender_controller.php). The ADS-B feed is fetched
 * every fetchInterval seconds.
 *
 * Safety mode is on when:
 *   - a test is running (dashboard Test Mode), or
 *   - an aircraft is inside the distance and altitude thresholds, or
 *   - one was, less than clearDelaySec ago (so a plane on the edge of the
 *     radius does not flick the lights on and off every fetch), or
 *   - safety mode was on and the feed has since stopped answering: with no
 *     data there is no way to know the plane has gone, so it stays safe.
 *
 * Settings are re-read every cycle, so changes on the settings page take
 * effect without restarting fppd.
 */

if (PHP_SAPI !== 'cli') {
    exit(1);
}

require_once(__DIR__ . "/airdefender_controller.php");
require_once(__DIR__ . "/airdefender_test.php");

const AIRDEFENDER_PLUGIN = "AirDefender";
const AIRDEFENDER_DEFAULT_FEED = "https://api.airplanes.live/v2/point/{lat}/{lon}/{radius}";
const AIRDEFENDER_DEFAULT_CLEAR_DELAY = 30;
const AIRDEFENDER_FEET_PER_METER = 3.28084;

/* ---------- files ---------- */

function airdefender_state_file(): string
{
    return dirname(__DIR__) . "/state.json";
}

function airdefender_read_state(): array
{
    $raw = @file_get_contents(airdefender_state_file());
    $state = $raw !== false ? json_decode($raw, true) : null;
    return is_array($state) ? $state : [];
}

function airdefender_write_state(array $state): void
{
    $path = airdefender_state_file();
    $tmp = $path . ".tmp";
    // Written to a temp file and renamed, so the dashboard never reads half a file.
    if (@file_put_contents($tmp, json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) !== false) {
        @rename($tmp, $path);
    }
}

function airdefender_log(string $message): void
{
    $logFile = "/home/fpp/media/logs/airdefender.log";
    $timestamp = date("Y-m-d H:i:s");
    @file_put_contents($logFile, "[{$timestamp}] {$message}\n", FILE_APPEND | LOCK_EX);
}

/* Log a problem once, not every second while it lasts. */
function airdefender_log_once(string $key, ?string $message): void
{
    static $last = [];
    if ($message === null) {
        unset($last[$key]);
        return;
    }
    if (($last[$key] ?? null) !== $message) {
        $last[$key] = $message;
        airdefender_log($message);
    }
}

/* ---------- settings ---------- */

function airdefender_load_settings(): array
{
    $settings = [];
    $lines = @file("/home/fpp/media/config/plugin." . AIRDEFENDER_PLUGIN, FILE_IGNORE_NEW_LINES);
    foreach (is_array($lines) ? $lines : [] as $line) {
        $parts = explode('=', $line, 2);
        if (count($parts) === 2) {
            $settings[trim($parts[0])] = trim(trim($parts[1]), "\"' ");
        }
    }
    return $settings;
}

function airdefender_setting(array $settings, string $key, $default)
{
    $value = $settings[$key] ?? '';
    return $value === '' ? $default : $value;
}

function airdefender_truthy($value): bool
{
    return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'on', 'y'], true);
}

function airdefender_fpp_location(): array
{
    $lat = null;
    $lon = null;
    $lines = @file('/home/fpp/media/settings', FILE_IGNORE_NEW_LINES);
    foreach (is_array($lines) ? $lines : [] as $line) {
        $parts = explode('=', $line, 2);
        if (count($parts) !== 2) {
            continue;
        }
        $k = trim($parts[0]);
        $v = trim(trim($parts[1]), "\"' ");
        if ($k === 'Latitude' && is_numeric($v)) {
            $lat = (float) $v;
        } elseif ($k === 'Longitude' && is_numeric($v)) {
            $lon = (float) $v;
        }
    }
    return [$lat, $lon];
}

/* Show location in use, or [null, null] if there is none. */
function airdefender_location(array $settings): array
{
    if (airdefender_truthy(airdefender_setting($settings, 'useFPPLocation', '1'))) {
        return airdefender_fpp_location();
    }
    $lat = airdefender_setting($settings, 'latitude', '');
    $lon = airdefender_setting($settings, 'longitude', '');
    if (is_numeric($lat) && is_numeric($lon)) {
        return [(float) $lat, (float) $lon];
    }
    return [null, null];
}

/* ---------- ADS-B ---------- */

function airdefender_distance_nm(float $lat1, float $lon1, float $lat2, float $lon2): float
{
    $earthRadiusNm = 3440.065;
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;
    return $earthRadiusNm * 2 * atan2(sqrt($a), sqrt(1 - $a));
}

/*
 * Aircraft list from any of the supported feed formats, with altitude
 * normalised to feet in 'altFt' (null when unknown).
 */
function airdefender_parse_aircraft($data): array
{
    if (!is_array($data)) {
        return [];
    }

    $planes = [];

    // readsb / tar1090 / dump1090 ("aircraft") and airplanes.live / adsb.lol / adsb.fi ("ac"): feet.
    $list = $data['aircraft'] ?? $data['ac'] ?? null;
    if (is_array($list)) {
        foreach ($list as $p) {
            if (!is_array($p) || !isset($p['lat'], $p['lon'])) {
                continue;
            }
            // alt_baro is the string "ground" for a plane on the ground.
            $alt = null;
            if (isset($p['alt_geom']) && is_numeric($p['alt_geom'])) {
                $alt = (float) $p['alt_geom'];
            } elseif (isset($p['alt_baro']) && is_numeric($p['alt_baro'])) {
                $alt = (float) $p['alt_baro'];
            } elseif (isset($p['altitude']) && is_numeric($p['altitude'])) {
                $alt = (float) $p['altitude'];   // older dump1090
            }
            $planes[] = [
                'id' => trim((string) ($p['flight'] ?? '')) ?: (string) ($p['hex'] ?? 'Unknown'),
                'lat' => (float) $p['lat'],
                'lon' => (float) $p['lon'],
                'altFt' => $alt,
                'onGround' => ($p['alt_baro'] ?? null) === 'ground',
            ];
        }
        return $planes;
    }

    // OpenSky ("states"): altitudes are in METERS.
    if (isset($data['states']) && is_array($data['states'])) {
        foreach ($data['states'] as $row) {
            if (!is_array($row) || !isset($row[5], $row[6])) {
                continue;
            }
            $meters = $row[13] ?? $row[7] ?? null;
            $planes[] = [
                'id' => trim((string) ($row[1] ?? '')) ?: (string) ($row[0] ?? 'Unknown'),
                'lat' => (float) $row[6],
                'lon' => (float) $row[5],
                'altFt' => is_numeric($meters) ? (float) $meters * AIRDEFENDER_FEET_PER_METER : null,
                'onGround' => !empty($row[8]),
            ];
        }
    }

    return $planes;
}

/* Closest aircraft inside the thresholds, with its distance, or null. */
function airdefender_find_trigger(float $lat, float $lon, float $maxNm, float $maxFt, array $planes): ?array
{
    $best = null;
    foreach ($planes as $p) {
        if ($p['onGround'] || $p['altFt'] === null || $p['altFt'] > $maxFt) {
            continue;
        }
        $d = airdefender_distance_nm($lat, $lon, $p['lat'], $p['lon']);
        if ($d <= $maxNm && ($best === null || $d < $best['distanceNm'])) {
            $best = $p + ['distanceNm' => round($d, 2)];
        }
    }
    return $best;
}

/* Fetch and decode the feed. Returns [aircraft list, null] or [null, error]. */
function airdefender_fetch_feed(string $url): array
{
    $context = stream_context_create([
        'http' => [
            'timeout' => 8,
            'header' => "User-Agent: FPP-AirDefender\r\nAccept: application/json\r\n",
            'ignore_errors' => true,
        ],
    ]);

    $body = @file_get_contents($url, false, $context);
    $status = 0;
    foreach ($http_response_header ?? [] as $line) {
        if (preg_match('#^HTTP/\S+\s+(\d{3})#', $line, $m)) {
            $status = (int) $m[1];
        }
    }

    if ($body === false || $body === '') {
        return [null, "No answer from the ADS-B feed"];
    }
    if ($status !== 0 && $status !== 200) {
        return [null, "ADS-B feed answered HTTP {$status}"];
    }
    $json = json_decode($body, true);
    if (!is_array($json)) {
        return [null, "ADS-B feed did not return valid JSON"];
    }
    return [airdefender_parse_aircraft($json), null];
}

/* ---------- main ---------- */

// One copy only: a second start (fppd restarted twice quickly) just exits.
$lock = @fopen(sys_get_temp_dir() . "/airdefender_loop.lock", "c");
if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
    exit(0);
}

$state = airdefender_read_state();
$outputs = new AirDefenderOutputs(is_array($state['appliedChannels'] ?? null) ? $state['appliedChannels'] : []);

$safe = false;
$lastTriggerAt = 0;          // last time an aircraft was inside the thresholds
$lastTrigger = null;         // that aircraft
$nextFetchAt = 0;
$feedError = null;
$lastFetchOkAt = null;
$aircraftCount = null;
$lastFeedKey = '';
$feedWasDown = false;

airdefender_log("Detection loop started (pid " . getmypid() . ").");

while (true) {
    $now = time();
    $settings = airdefender_load_settings();

    $enabled = airdefender_truthy(airdefender_setting($settings, 'enabled', '0'));
    $role = strtolower((string) airdefender_setting($settings, 'nodeRole', 'standalone'));
    $maxNm = max(0.1, (float) airdefender_setting($settings, 'distanceNM', 5));
    $maxFt = max(0, (float) airdefender_setting($settings, 'altitudeThresholdFt', 3000));
    $fetchInterval = max(5, (int) airdefender_setting($settings, 'fetchInterval', 10));
    $clearDelay = max(0, (int) airdefender_setting($settings, 'clearDelaySec', AIRDEFENDER_DEFAULT_CLEAR_DELAY));
    $feedTemplate = (string) airdefender_setting($settings, 'adsbSourceUrl', AIRDEFENDER_DEFAULT_FEED);
    $heads = AirDefenderOutputs::headsFromSettings($settings);
    [$lat, $lon] = airdefender_location($settings);

    $test = airdefender_test_status();
    $detecting = $enabled && $role !== 'remote' && $lat !== null && $lon !== null;

    // Start over with a fresh fetch whenever what we ask the feed changes.
    $feedKey = $feedTemplate . "|" . $lat . "|" . $lon . "|" . $maxNm;
    if ($feedKey !== $lastFeedKey) {
        $lastFeedKey = $feedKey;
        $nextFetchAt = 0;
    }

    /* --- feed --- */
    if ($detecting && $now >= $nextFetchAt) {
        $nextFetchAt = $now + $fetchInterval;
        $url = str_replace(
            ['{lat}', '{lon}', '{radius}'],
            [round($lat, 5), round($lon, 5), max(1, (int) ceil($maxNm))],
            $feedTemplate
        );
        [$planes, $feedError] = airdefender_fetch_feed($url);

        if ($feedError === null) {
            if ($feedWasDown) {
                airdefender_log("ADS-B feed answering again.");
                $feedWasDown = false;
            }
            $lastFetchOkAt = $now;
            $aircraftCount = count($planes);
            airdefender_log_once('feed', null);
            $hit = airdefender_find_trigger($lat, $lon, $maxNm, $maxFt, $planes);
            if ($hit !== null) {
                $lastTriggerAt = $now;
                $lastTrigger = $hit;
            }
        } else {
            $feedWasDown = true;
            airdefender_log_once('feed', "{$feedError} ({$url}).");
        }
    }

    /* --- decide --- */
    $wasSafe = $safe;
    $planeNow = $lastTriggerAt > 0 && $lastTriggerAt >= ($lastFetchOkAt ?? 0) && $feedError === null
        && $now - $lastTriggerAt <= $fetchInterval + 2;

    if ($test['active']) {
        $safe = true;
        $reason = 'test';
        $event = "Test running: safety on, {$test['remaining']} s left";
    } elseif (!$enabled) {
        $safe = false;
        $reason = 'disabled';
        $event = 'Plugin disabled';
    } elseif ($role === 'remote') {
        $safe = false;
        $reason = 'remote';
        $event = 'Remote mode: waiting for commands from the master (not built yet)';
    } elseif ($lat === null || $lon === null) {
        $safe = false;
        $reason = 'no-location';
        $event = 'No show location: set it in FPP settings or turn off Use FPP Location and enter one';
    } elseif ($planeNow) {
        $safe = true;
        $reason = 'aircraft';
        $event = "Aircraft {$lastTrigger['id']} at {$lastTrigger['distanceNm']} NM, "
            . round($lastTrigger['altFt']) . " ft";
    } elseif ($feedError !== null && $wasSafe) {
        $safe = true;
        $reason = 'feed-hold';
        $event = "Feed down while an aircraft was near: staying safe until it answers ({$feedError})";
    } elseif ($lastTriggerAt > 0 && $now - $lastTriggerAt < $clearDelay) {
        $safe = true;
        $reason = 'hold';
        $left = $clearDelay - ($now - $lastTriggerAt);
        $event = "Aircraft {$lastTrigger['id']} has left: holding safe for {$left} s more";
    } else {
        $safe = false;
        $reason = $feedError !== null ? 'feed-error' : 'clear';
        $event = $feedError !== null
            ? "ADS-B feed problem: {$feedError}"
            : "Clear: no aircraft within {$maxNm} NM below {$maxFt} ft";
    }

    /* --- act --- */
    $outputsOk = $outputs->apply($safe, $heads);
    airdefender_log_once('outputs', $outputsOk ? null : "Could not set the shutter overlays: {$outputs->lastError}");

    if ($safe && !$wasSafe) {
        $what = $reason === 'test' ? "test started" : $event;
        airdefender_log("SAFETY ON ({$what}). Closing " . count($heads) . " shutter channel(s): "
            . ($heads ? implode(', ', array_keys($heads)) : 'none configured'));
    } elseif (!$safe && $wasSafe) {
        airdefender_log("SAFETY OFF. Shutters released back to the sequence.");
    }
    if ($safe && !$heads) {
        airdefender_log_once('heads', "Safety mode is on but no moving heads are enabled in settings, so nothing is being closed.");
    } else {
        airdefender_log_once('heads', null);
    }

    /* --- report --- */
    airdefender_write_state([
        'safetyMode' => $safe,
        'reason' => $reason,
        'testActive' => $test['active'],
        'lastEvent' => $event,
        'lastAircraft' => $lastTrigger ? [
            'flight' => $lastTrigger['id'],
            'distanceNm' => $lastTrigger['distanceNm'],
            'altitudeFt' => round($lastTrigger['altFt']),
            'seenAt' => date('c', $lastTriggerAt),
        ] : null,
        'aircraftCount' => $aircraftCount,
        'lastFetchOk' => $lastFetchOkAt ? date('c', $lastFetchOkAt) : null,
        'feedError' => $feedError,
        'location' => $lat !== null ? ['lat' => $lat, 'lon' => $lon] : null,
        'headsConfigured' => count($heads),
        'appliedChannels' => $outputs->applied(),
        'outputError' => $outputsOk ? null : $outputs->lastError,
        'lastUpdated' => date('c', $now),
        'pid' => getmypid(),
    ]);

    sleep(1);
}
