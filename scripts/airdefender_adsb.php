<?php
/*
 * Asking an ADS-B service for aircraft and reading its answer. Shared by the
 * detection loop and the settings page's Test connection button, so the test
 * does exactly what the loop does.
 */

const AIRDEFENDER_FEET_PER_METER = 3.28084;

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

    // readsb / tar1090 / dump1090 ("aircraft") and adsb.lol / adsb.fi ("ac" or "aircraft"): feet.
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
    if ($status === 401 || $status === 403) {
        return [null, "ADS-B feed refused the request (HTTP {$status}); it may need an account now. Pick another service in Settings"];
    }
    if ($status === 429) {
        return [null, "ADS-B feed says too many requests (HTTP 429); raise the fetch interval in Settings"];
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
