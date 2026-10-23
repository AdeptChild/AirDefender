<?php

require_once(__DIR__ . "/airdefender_controller.php");

if (PHP_SAPI !== 'cli') {
    exit(1);
}

function airdefender_get_setting($pluginName, $key, $default = null) {
    if (function_exists('GetSetting')) {
        $value = GetSetting($pluginName, $key);
        if ($value !== null && $value !== false && $value !== '') {
            return $value;
        }
    }

    $envKey = strtoupper("AIRDEFENDER_" . str_replace('-', '_', $key));
    if (getenv($envKey) !== false) {
        return getenv($envKey);
    }

    return $default;
}

function airdefender_get_fpp_lat_lon() {
    $lat = null;
    $lon = null;

    if (function_exists('GetSetting')) {
        $candidates = [
            ['System', 'Latitude'],
            ['System', 'longitude'],
            ['Settings', 'Latitude'],
            ['Settings', 'longitude'],
            ['Location', 'Latitude'],
            ['Location', 'Longitude'],
            ['fpp', 'latitude'],
            ['fpp', 'longitude'],
            ['Global', 'latitude'],
            ['Global', 'longitude'],
            ['fpp', 'lat'],
            ['fpp', 'lon'],
        ];

        foreach ($candidates as [$section, $field]) {
            $value = GetSetting($section, $field);
            if ($value !== null && $value !== false && $value !== '') {
                if (strtolower($field) === 'latitude' || strtolower($field) === 'lat') {
                    $lat = (float) $value;
                } elseif (strtolower($field) === 'longitude' || strtolower($field) === 'lon') {
                    $lon = (float) $value;
                }
            }
        }
    }

    return [$lat, $lon];
}

function airdefender_distance_nm(float $lat1, float $lon1, float $lat2, float $lon2): float
{
    $earthRadiusNm = 3440.065;
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

function airdefender_parse_aircraft($data): array
{
    if (!is_array($data)) {
        return [];
    }

    if (isset($data['aircraft']) && is_array($data['aircraft'])) {
        return $data['aircraft'];
    }

    if (isset($data['ac']) && is_array($data['ac'])) {
        return $data['ac'];
    }

    if (isset($data['states']) && is_array($data['states'])) {
        $results = [];
        foreach ($data['states'] as $row) {
            if (!is_array($row)) {
                continue;
            }

            $lat = $row[6] ?? null;
            $lon = $row[5] ?? null;
            if ($lat === null || $lon === null) {
                continue;
            }

            $results[] = [
                'hex' => $row[0] ?? null,
                'flight' => $row[1] ?? null,
                'lat' => (float) $lat,
                'lon' => (float) $lon,
                'alt_geom' => isset($row[13]) ? (float) $row[13] : null,
                'alt_baro' => isset($row[7]) ? (float) $row[7] : null,
            ];
        }
        return $results;
    }

    return [];
}

function airdefender_find_triggering_aircraft($showLat, $showLon, $distanceNm, $altitudeFt, $aircraft): ?array
{
    foreach ($aircraft as $plane) {
        if (!isset($plane['lat'], $plane['lon'])) {
            continue;
        }

        $altitude = $plane['alt_geom'] ?? $plane['alt_baro'] ?? null;
        if ($altitude === null) {
            continue;
        }

        $distance = airdefender_distance_nm($showLat, $showLon, (float) $plane['lat'], (float) $plane['lon']);
        if ($distance <= $distanceNm && $altitude <= $altitudeFt) {
            return $plane;
        }
    }

    return null;
}

function airdefender_log($message) {
    $logFile = "/home/fpp/media/logs/airdefender.log";
    $timestamp = date("Y-m-d H:i:s");
    @file_put_contents($logFile, "[{$timestamp}] {$message}\n", FILE_APPEND | LOCK_EX);
}

$pluginName = "AirDefender";

if (function_exists('GetSetting')) {
    $enabled = airdefender_get_setting($pluginName, 'enabled', false);
    $useFppLocation = airdefender_get_setting($pluginName, 'useFPPLocation', true);
    $manualOverride = airdefender_get_setting($pluginName, 'overrideLocation', false);
    $manualLat = airdefender_get_setting($pluginName, 'latitude', '');
    $manualLon = airdefender_get_setting($pluginName, 'longitude', '');
    $distanceNm = (float) airdefender_get_setting($pluginName, 'distanceNM', 5);
    $altitudeFt = (float) airdefender_get_setting($pluginName, 'altitudeThresholdFt', 3000);
    $fetchInterval = (int) airdefender_get_setting($pluginName, 'fetchInterval', 10);
    $adsbUrl = airdefender_get_setting($pluginName, 'adsbSourceUrl', '');
    $controllerType = airdefender_get_setting($pluginName, 'controllerType', 'falcon');
    $serialPort = airdefender_get_setting($pluginName, 'serialPort', '');
    $dmxOutputs = airdefender_get_setting($pluginName, 'dmxOutputs', '');
} else {
    $enabled = filter_var(getenv('AIRDEFENDER_ENABLED') ?: false, FILTER_VALIDATE_BOOLEAN);
    $useFppLocation = filter_var(getenv('AIRDEFENDER_USE_FPP_LOCATION') ?: true, FILTER_VALIDATE_BOOLEAN);
    $manualOverride = filter_var(getenv('AIRDEFENDER_OVERRIDE_LOCATION') ?: false, FILTER_VALIDATE_BOOLEAN);
    $manualLat = getenv('AIRDEFENDER_LATITUDE') ?: '';
    $manualLon = getenv('AIRDEFENDER_LONGITUDE') ?: '';
    $distanceNm = (float) (getenv('AIRDEFENDER_DISTANCE_NM') ?: 5);
    $altitudeFt = (float) (getenv('AIRDEFENDER_ALTITUDE_THRESHOLD_FT') ?: 3000);
    $fetchInterval = (int) (getenv('AIRDEFENDER_FETCH_INTERVAL') ?: 10);
    $adsbUrl = getenv('AIRDEFENDER_ADSB_URL') ?: '';
    $controllerType = getenv('AIRDEFENDER_CONTROLLER_TYPE') ?: 'falcon';
    $serialPort = getenv('AIRDEFENDER_SERIAL_PORT') ?: '';
    $dmxOutputs = getenv('AIRDEFENDER_DMX_OUTPUTS') ?: '';
}

if (!$enabled) {
    exit(0);
}

if (empty($adsbUrl)) {
    airdefender_log("ADS-B URL is empty; plugin waiting for configuration.");
    exit(0);
}

$showLat = null;
$showLon = null;

if (!empty($manualOverride) && $manualLat !== '' && $manualLon !== '') {
    $showLat = (float) $manualLat;
    $showLon = (float) $manualLon;
} elseif (!empty($useFppLocation)) {
    [$fppLat, $fppLon] = airdefender_get_fpp_lat_lon();
    if ($fppLat !== null && $fppLon !== null) {
        $showLat = (float) $fppLat;
        $showLon = (float) $fppLon;
    }
}

if ($showLat === null || $showLon === null) {
    airdefender_log("No valid show location available. Check FPP location or manual latitude/longitude.");
    exit(0);
}

$controller = airdefender_make_controller($controllerType, $serialPort, $dmxOutputs);

while (true) {
    $payload = @file_get_contents($adsbUrl);
    if ($payload === false || $payload === '') {
        airdefender_log("Unable to retrieve ADS-B feed: {$adsbUrl}");
        sleep(max(1, $fetchInterval));
        continue;
    }

    $json = json_decode($payload, true);
    if (!is_array($json)) {
        airdefender_log("ADS-B payload is not valid JSON.");
        sleep(max(1, $fetchInterval));
        continue;
    }

    $aircraft = airdefender_parse_aircraft($json);
    $trigger = airdefender_find_triggering_aircraft($showLat, $showLon, $distanceNm, $altitudeFt, $aircraft);

    if ($trigger !== null) {
        $flight = $trigger['flight'] ?? $trigger['hex'] ?? 'Unknown';
        $distance = airdefender_distance_nm($showLat, $showLon, (float) $trigger['lat'], (float) $trigger['lon']);
        $altitude = $trigger['alt_geom'] ?? $trigger['alt_baro'];

        airdefender_log("Triggered safety mode for {$flight}: {$distance} NM away, {$altitude} ft");
        $controller->activateSafetyMode();
    } else {
        airdefender_log("Safety mode clear: no aircraft within {$distanceNm} NM and below {$altitudeFt} ft");
        $controller->clearSafetyMode();
    }

    sleep(max(1, $fetchInterval));
}

?>
