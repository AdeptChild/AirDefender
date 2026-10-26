<?php
/*
 * The flight-data services Air Defender can ask, and what to do with
 * addresses saved by older versions.
 *
 * airplanes.live used to be the default, but its API is no longer open: it
 * answers HTTP 403 without a key, and its free tier (500 requests a day) is
 * far below one request every few seconds. adsb.fi moved its query to v3.
 *
 * adsb.fi is the default because it publishes its limit (1 request a second,
 * far above what Air Defender needs); adsb.lol publishes none, and has been
 * reported to limit some users to about 500 a day. Each is the other's backup.
 */

const AIRDEFENDER_FEED_ADSBFI = "https://opendata.adsb.fi/api/v3/lat/{lat}/lon/{lon}/dist/{radius}";
const AIRDEFENDER_FEED_ADSBLOL = "https://api.adsb.lol/v2/point/{lat}/{lon}/{radius}";
const AIRDEFENDER_DEFAULT_FEED = AIRDEFENDER_FEED_ADSBFI;

/* URL template => label, as offered in Settings. */
function airdefender_feed_options(): array
{
    return [
        AIRDEFENDER_FEED_ADSBFI => 'adsb.fi (default)',
        AIRDEFENDER_FEED_ADSBLOL => 'adsb.lol',
        'http://localhost/tar1090/data/aircraft.json' => 'Local receiver (tar1090)',
        'http://localhost:8080/data/aircraft.json' => 'Local receiver (dump1090-fa)',
    ];
}

/*
 * The feed to use for a saved setting: [url, note]. Retired services are
 * swapped for their replacement, with a note saying so; otherwise note is null.
 */
function airdefender_effective_feed(?string $saved): array
{
    $saved = trim((string) $saved);
    if ($saved === '') {
        return [AIRDEFENDER_DEFAULT_FEED, null];
    }
    if (stripos($saved, 'api.airplanes.live') !== false) {
        return [AIRDEFENDER_DEFAULT_FEED,
            "airplanes.live no longer allows open access, so Air Defender is using adsb.fi instead. Pick a service in Settings to save it."];
    }
    if (stripos($saved, 'opendata.adsb.fi/api/v2/') !== false) {
        return ['https://opendata.adsb.fi/api/v3/lat/{lat}/lon/{lon}/dist/{radius}', null];
    }
    return [$saved, null];
}

/*
 * The online service to fall back on when $template does not answer, or null:
 * adsb.fi and adsb.lol back each other up. A local receiver (or any other
 * address) has no backup, so a show that chose to keep its location on the
 * FPP never has it sent to an online service.
 */
function airdefender_feed_backup(string $template): ?string
{
    if ($template === AIRDEFENDER_FEED_ADSBFI) {
        return AIRDEFENDER_FEED_ADSBLOL;
    }
    if ($template === AIRDEFENDER_FEED_ADSBLOL) {
        return AIRDEFENDER_FEED_ADSBFI;
    }
    return null;
}

/* A short name for a feed address: the service's name, or its host. */
function airdefender_feed_label(string $template): string
{
    $options = airdefender_feed_options();
    if (isset($options[$template])) {
        return preg_replace('/ \(default\)$/', '', $options[$template]);
    }
    return parse_url($template, PHP_URL_HOST) ?: 'custom feed';
}
