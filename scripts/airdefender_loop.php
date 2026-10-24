<?php

require_once(__DIR__ . "/airdefender_controller.php");

if (PHP_SAPI !== 'cli') {
    exit(1);
}

function airdefender_state_file(): string
{
    return "/home/fpp/media/plugins/AirDefender/state.json";
}

function airdefender_read_state(): array
{
    $path = airdefender_state_file();
    if (!file_exists($path)) {
        return [
            'safetyMode' => false,
            'lastEvent' => 'No events yet.',
            'lastAircraft' => null,
            'lastDistanceNm' => null,
            'lastAltitudeFt' => null,
            'lastUpdated' => null,
            'controllerType' => 'falcon',
            'blackoutStartChannel' => 0,
            'blackoutEndChannel' => 0,
        ];
    }

    $raw = @file_get_contents($path);
    if ($raw === false || trim($raw) === '') {
        return [
            'safetyMode' => false,
            'lastEvent' => 'No events yet.',
            'lastAircraft' => null,
            'lastDistanceNm' => null,
            'lastAltitudeFt' => null,
            'lastUpdated' => null,
            'controllerType' => 'falcon',
            'blackoutStartChannel' => 0,
            'blackoutEndChannel' => 0,
        ];
    }

    $state = json_decode($raw, true);
    if (!is_array($state)) {
        return [
            'safetyMode' => false,
            'lastEvent' => 'No events yet.',
            'lastAircraft' => null,
            'lastDistanceNm' => null,
            'lastAltitudeFt' => null,
            'lastUpdated' => null,
            'controllerType' => 'falcon',
            'blackoutStartChannel' => 0,
            'blackoutEndChannel' => 0,
        ];
    }

    return $state;
}

function airdefender_write_state(array $state): void
{
    $path = airdefender_state_file();
    @mkdir(dirname($path), 0777, true);
    @file_put_contents($path, json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);
}

function airdefender_config_path(string $pluginName): string
{
    return "/home/fpp/media/config/plugin." . $pluginName;
}

function airdefender_load_plugin_settings(string $pluginName): array
{
    $path = airdefender_config_path($pluginName);
    $settings = [];

    if (!is_readable($path)) {
        return $settings;
    }

    $lines = @file($path, FILE_IGNORE_NEW_LINES);
    if (!is_array($lines)) {
        return $settings;
    }

    foreach ($lines as $line) {
        $parts = explode('=', $line, 2);
        if (count($parts) !== 2) {
            continue;
        }

        $k = trim($parts[0]);
        $v = trim($parts[1]);

        // Strip optional surrounding quotes
        $v = trim($v, "\"' ");

        $settings[$k] = $v;
    }

    return $settings;
}

function airdefender_get_setting(array $settings, $key, $default = null) {
    if (array_key_exists($key, $settings)) {
        $value = $settings[$key];
        if ($value !== null && $value !== '') {
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

    $lines = @file('/home/fpp/media/settings', FILE_IGNORE_NEW_LINES);
    if (is_array($lines)) {
        foreach ($lines as $line) {
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
        if (!is_numeric($altitude)) {
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
$pluginSettings = airdefender_load_plugin_settings($pluginName);

$enabled = filter_var(airdefender_get_setting($pluginSettings, 'enabled', '0'), FILTER_VALIDATE_BOOLEAN);
$useFppLocation = filter_var(airdefender_get_setting($pluginSettings, 'useFPPLocation', '1'), FILTER_VALIDATE_BOOLEAN);

// No overrideLocation setting anymore; manual location is used when useFPPLocation is off.
$manualLat = airdefender_get_setting($pluginSettings, 'latitude', '');
$manualLon = airdefender_get_setting($pluginSettings, 'longitude', '');

$distanceNm = (float) airdefender_get_setting($pluginSettings, 'distanceNM', 5);
$altitudeFt = (float) airdefender_get_setting($pluginSettings, 'altitudeThresholdFt', 3000);
$fetchInterval = max(1, (int) airdefender_get_setting($pluginSettings, 'fetchInterval', 10));
$adsbUrl = (string) airdefender_get_setting($pluginSettings, 'adsbSourceUrl', '');
$controllerType = (string) airdefender_get_setting($pluginSettings, 'controllerType', 'falcon');

$blackoutStartChannel = (int) airdefender_get_setting($pluginSettings, 'blackoutStartChannel', 0);
$blackoutEndChannel = (int) airdefender_get_setting($pluginSettings, 'blackoutEndChannel', 0);

if (!$enabled) {
    $state = airdefender_read_state();
    $state['safetyMode'] = false;
    $state['lastEvent'] = 'Plugin disabled';
    $state['lastUpdated'] = date('c');
    $state['controllerType'] = $controllerType;
    $state['blackoutStartChannel'] = $blackoutStartChannel;
    $state['blackoutEndChannel'] = $blackoutEndChannel;
    airdefender_write_state($state);
    exit(0);
}

if (empty($adsbUrl)) {
    $state = airdefender_read_state();
    $state['safetyMode'] = false;
    $state['lastEvent'] = 'ADS-B URL is empty; waiting for configuration.';
    $state['lastUpdated'] = date('c');
    $state['controllerType'] = $controllerType;
    $state['blackoutStartChannel'] = $blackoutStartChannel;
    $state['blackoutEndChannel'] = $blackoutEndChannel;
    airdefender_write_state($state);
    airdefender_log("ADS-B URL is empty; plugin waiting for configuration.");
    exit(0);
}

$showLat = null;
$showLon = null;

if (!empty($useFppLocation)) {
    [$fppLat, $fppLon] = airdefender_get_fpp_lat_lon();
    if ($fppLat !== null && $fppLon !== null) {
        $showLat = (float) $fppLat;
        $showLon = (float) $fppLon;
    }
} elseif ($manualLat !== '' && $manualLon !== '') {
    $showLat = (float) $manualLat;
    $showLon = (float) $manualLon;
}

if ($showLat === null || $showLon === null) {
    $state = airdefender_read_state();
    $state['safetyMode'] = false;
    $state['lastEvent'] = 'No valid location available. Check FPP or manual override.';
    $state['lastUpdated'] = date('c');
    $state['controllerType'] = $controllerType;
    $state['blackoutStartChannel'] = $blackoutStartChannel;
    $state['blackoutEndChannel'] = $blackoutEndChannel;
    airdefender_write_state($state);
    airdefender_log("No valid show location available. Check FPP location or manual latitude/longitude.");
    exit(0);
}
$adsbUrl = str_replace(
    ['{lat}', '{lon}', '{radius}'],
    [round($showLat, 5), round($showLon, 5), max(1, (int) ceil($distanceNm))],
    $adsbUrl
);

$controller = airdefender_make_controller($controllerType, $blackoutStartChannel, $blackoutEndChannel);

while (true) {
    $state = airdefender_read_state();
    $wasActive = !empty($state['safetyMode']);
    $state['controllerType'] = $controllerType;
    $state['blackoutStartChannel'] = $blackoutStartChannel;
    $state['blackoutEndChannel'] = $blackoutEndChannel;

    $payload = @file_get_contents($adsbUrl);
    if ($payload === false || $payload === '') {
        $state['safetyMode'] = false;
        $state['lastEvent'] = "Unable to retrieve ADS-B feed: {$adsbUrl}";
        $state['lastUpdated'] = date('c');
        airdefender_write_state($state);
        airdefender_log("Unable to retrieve ADS-B feed: {$adsbUrl}");
        sleep(max(1, $fetchInterval));
        continue;
    }

    $json = json_decode($payload, true);
    if (!is_array($json)) {
        $state['safetyMode'] = false;
        $state['lastEvent'] = 'ADS-B payload is not valid JSON.';
        $state['lastUpdated'] = date('c');
        airdefender_write_state($state);
        airdefender_log("ADS-B payload is not valid JSON.");
        sleep(max(1, $fetchInterval));
        continue;
    }

    $aircraft = airdefender_parse_aircraft($json);
    $trigger = airdefender_find_triggering_aircraft($showLat, $showLon, $distanceNm, $altitudeFt, $aircraft);
    $newMode = $trigger !== null;

    if ($trigger !== null) {
        $flight = $trigger['flight'] ?? $trigger['hex'] ?? 'Unknown';
        $distance = airdefender_distance_nm($showLat, $showLon, (float) $trigger['lat'], (float) $trigger['lon']);
        $altitude = $trigger['alt_geom'] ?? $trigger['alt_baro'];

        $state['safetyMode'] = true;
        $state['lastAircraft'] = [
            'flight' => $flight,
            'lat' => (float) $trigger['lat'],
            'lon' => (float) $trigger['lon'],
            'distanceNm' => round($distance, 2),
            'altitudeFt' => (float) $altitude,
        ];
        $state['lastDistanceNm'] = round($distance, 2);
        $state['lastAltitudeFt'] = (float) $altitude;
        $state['lastEvent'] = "Triggered safety mode for {$flight}: {$distance} NM away, {$altitude} ft";
        $state['lastUpdated'] = date('c');

        if (!$wasActive) {
            airdefender_log("Triggered safety mode for {$flight}: {$distance} NM away, {$altitude} ft");
        }

        $controller->activateSafetyMode();
    } else {
        $state['safetyMode'] = false;
        $state['lastAircraft'] = null;
        $state['lastDistanceNm'] = null;
        $state['lastAltitudeFt'] = null;
        $state['lastEvent'] = "Safety mode clear: no aircraft within {$distanceNm} NM and below {$altitudeFt} ft";
        $state['lastUpdated'] = date('c');

        $controller->clearSafetyMode();
    }

    airdefender_write_state($state);
    sleep(max(1, $fetchInterval));
}

?>
