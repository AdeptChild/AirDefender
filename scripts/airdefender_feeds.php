<?php
/*
 * The flight-data services Air Defender can ask, and what to do with
 * addresses saved by older versions.
 *
 * airplanes.live used to be the default, but its API is no longer open: it
 * answers HTTP 403 without a key, and its free tier (500 requests a day) is
 * far below one request every few seconds. adsb.fi moved its query to v3.
 */

const AIRDEFENDER_DEFAULT_FEED = "https://api.adsb.lol/v2/point/{lat}/{lon}/{radius}";

/* URL template => label, as offered in Settings. */
function airdefender_feed_options(): array
{
    return [
        'https://api.adsb.lol/v2/point/{lat}/{lon}/{radius}' => 'adsb.lol (default)',
        'https://opendata.adsb.fi/api/v3/lat/{lat}/lon/{lon}/dist/{radius}' => 'adsb.fi',
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
            "airplanes.live no longer allows open access, so Air Defender is using adsb.lol instead. Pick a service in Settings to save it."];
    }
    if (stripos($saved, 'opendata.adsb.fi/api/v2/') !== false) {
        return ['https://opendata.adsb.fi/api/v3/lat/{lat}/lon/{lon}/dist/{radius}', null];
    }
    return [$saved, null];
}
