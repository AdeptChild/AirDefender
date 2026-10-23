<?php
/*
 * AirDefender web ADS-B check script.
 *
 * This script reads aircraft data from a JSON feed, checks distance and altitude,
 * and prints whether the current aircraft conditions would trigger safety mode.
 *
 * Default rules:
 *   - distance <= 5 NM
 *   - altitude <= 3000 ft
 *
 * Example usage:
 *   php scripts/airdefender_check.php \
 *     --url "https://example.com/aircraft.json" \
 *     --lat 42.123456 \
 *     --lon -84.123456 \
 *     --distance 5 \
 *     --altitude 3000
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script must be run from the command line.\n");
    exit(1);
}

function airdefender_parse_args(array $argv): array
{
    $args = [];
    $i = 1;

    while ($i < count($argv)) {
        $arg = $argv[$i];

        if (strpos($arg, '--') === 0) {
            $name = substr($arg, 2);
            $value = true;

            if ($i + 1 < count($argv) && strpos($argv[$i + 1], '--') !== 0) {
                $value = $argv[$i + 1];
                $i++;
            }

            $args[$name] = $value;
        }

        $i++;
    }

    return $args;
}

function airdefender_distance_nm(float $lat1, float $lon1, float $lat2, float $lon2): float
{
    $earthRadiusNm = 3440.065; // nautical miles
    $lat1Rad = deg2rad($lat1);
    $lon1Rad = deg2rad($lon1);
    $lat2Rad = deg2rad($lat2);
    $lon2Rad = deg2rad($lon2);

    $dLat = $lat2Rad - $lat1Rad;
    $dLon = $lon2Rad - $lon1Rad;

    $a = sin($dLat / 2) * sin($dLat / 2)
        + cos($lat1Rad) * cos($lat2Rad)
        * sin($dLon / 2) * sin($dLon / 2);

    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

    return $earthRadiusNm * $c;
}

function airdefender_extract_aircraft_from_response($data): array
{
    if (!is_array($data)) {
        return [];
    }

    $aircraft = [];

    if (isset($data['aircraft']) && is_array($data['aircraft'])) {
        $aircraft = $data['aircraft'];
    } elseif (isset($data['ac']) && is_array($data['ac'])) {
        $aircraft = $data['ac'];
    } elseif (isset($data['states']) && is_array($data['states'])) {
        foreach ($data['states'] as $row) {
            if (!is_array($row)) {
                continue;
            }

            $icao = $row[0] ?? null;
            $lat = $row[6] ?? null;
            $lon = $row[5] ?? null;
            $altBaro = $row[7] ?? null;
            $altGeom = $row[13] ?? null;
            $callsign = $row[1] ?? null;

            if ($lat === null || $lon === null) {
                continue;
            }

            $aircraft[] = [
                'hex' => $icao,
                'flight' => $callsign,
                'lat' => (float) $lat,
                'lon' => (float) $lon,
                'alt_baro' => $altBaro !== null ? (float) $altBaro : null,
                'alt_geom' => $altGeom !== null ? (float) $altGeom : null,
            ];
        }
    }

    return $aircraft;
}

$args = airdefender_parse_args($argv);

$url = $args['url'] ?? getenv('AIRDEFENDER_URL') ?: '';
$showLat = isset($args['lat']) ? (float) $args['lat'] : 0.0;
$showLon = isset($args['lon']) ? (float) $args['lon'] : 0.0;
$distanceThresholdNm = isset($args['distance']) ? (float) $args['distance'] : 5.0;
$altitudeThresholdFt = isset($args['altitude']) ? (float) $args['altitude'] : 3000.0;

if (empty($url)) {
    fwrite(STDERR, "Missing ADS-B URL. Provide --url or set AIRDEFENDER_URL.\n");
    exit(2);
}

$body = @file_get_contents($url);
if ($body === false || $body === '') {
    fwrite(STDERR, "Unable to fetch ADS-B data from: {$url}\n");
    exit(3);
}

$json = json_decode($body, true);
if (!is_array($json)) {
    fwrite(STDERR, "ADS-B response is not valid JSON.\n");
    exit(4);
}

$aircraft = airdefender_extract_aircraft_from_response($json);
if (empty($aircraft)) {
    echo "No aircraft data found in ADS-B feed.\n";
    exit(0);
}

$triggered = false;
$triggerAircraft = null;

foreach ($aircraft as $plane) {
    if (!isset($plane['lat'], $plane['lon'])) {
        continue;
    }

    $altitude = $plane['alt_geom'] ?? $plane['alt_baro'] ?? null;
    if ($altitude === null) {
        continue;
    }

    $distance = airdefender_distance_nm($showLat, $showLon, (float) $plane['lat'], (float) $plane['lon']);

    if ($distance <= $distanceThresholdNm && $altitude <= $altitudeThresholdFt) {
        $triggered = true;
        $triggerAircraft = $plane;
        break;
    }
}

if ($triggered && $triggerAircraft) {
    $flight = $triggerAircraft['flight'] ?? $triggerAircraft['hex'] ?? 'Unknown';
    $lat = $triggerAircraft['lat'];
    $lon = $triggerAircraft['lon'];
    $alt = $triggerAircraft['alt_geom'] ?? $triggerAircraft['alt_baro'];
    $distance = airdefender_distance_nm($showLat, $showLon, (float) $lat, (float) $lon);

    echo "TRIGGER: {$flight} is within {$distance} NM and {$alt} ft altitude.\n";
    exit(0);
}

echo "CLEAR: no aircraft are within {$distanceThresholdNm} NM and below {$altitudeThresholdFt} ft.\n";
exit(0);
