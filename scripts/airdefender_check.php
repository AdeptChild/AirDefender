<?php
/*
 * Air Defender command-line check: asks the flight-data service once, the
 * same way the detection loop does, and prints what it sees. Handy over SSH
 * when the dashboard says something is wrong.
 *
 * With no options it uses your saved settings (service, location, limits):
 *   php /home/fpp/media/plugins/AirDefender/scripts/airdefender_check.php
 *
 * Any of them can be overridden:
 *   --url "https://api.adsb.lol/v2/point/{lat}/{lon}/{radius}"
 *   --lat 34.685 --lon -82.953 --distance 5 --altitude 3000
 *
 * Exit code: 0 clear, 1 an aircraft is inside the limits, 2 could not check.
 */

if (PHP_SAPI !== 'cli') {
    exit(2);
}

require_once(__DIR__ . "/airdefender_adsb.php");
require_once(__DIR__ . "/airdefender_feeds.php");

$opts = getopt('', ['url:', 'lat:', 'lon:', 'distance:', 'altitude:', 'help']);
if (isset($opts['help'])) {
    echo "Usage: php airdefender_check.php [--url TEMPLATE] [--lat N] [--lon N] [--distance NM] [--altitude FT]\n";
    exit(0);
}

$settings = [];
foreach ((array) @file("/home/fpp/media/config/plugin.AirDefender", FILE_IGNORE_NEW_LINES) as $line) {
    $parts = explode('=', (string) $line, 2);
    if (count($parts) === 2) {
        $settings[trim($parts[0])] = trim(trim($parts[1]), "\"' ");
    }
}
$fpp = [];
foreach ((array) @file("/home/fpp/media/settings", FILE_IGNORE_NEW_LINES) as $line) {
    $parts = explode('=', (string) $line, 2);
    if (count($parts) === 2) {
        $fpp[trim($parts[0])] = trim(trim($parts[1]), "\"' ");
    }
}

$useFpp = !in_array(strtolower($settings['useFPPLocation'] ?? '1'), ['0', 'false', 'no', 'off'], true);
$lat = $opts['lat'] ?? ($useFpp ? ($fpp['Latitude'] ?? '') : ($settings['latitude'] ?? ''));
$lon = $opts['lon'] ?? ($useFpp ? ($fpp['Longitude'] ?? '') : ($settings['longitude'] ?? ''));
$maxNm = (float) ($opts['distance'] ?? ($settings['distanceNM'] ?? 5));
$maxFt = (float) ($opts['altitude'] ?? ($settings['altitudeThresholdFt'] ?? 3000));
[$template] = airdefender_effective_feed($opts['url'] ?? ($settings['adsbSourceUrl'] ?? ''));

if (!is_numeric($lat) || !is_numeric($lon)) {
    fwrite(STDERR, "No show location: set it in FPP or Air Defender settings, or pass --lat and --lon.\n");
    exit(2);
}
$lat = (float) $lat;
$lon = (float) $lon;

$url = str_replace(['{lat}', '{lon}', '{radius}'], [round($lat, 5), round($lon, 5), max(1, (int) ceil($maxNm))], $template);
echo "Asking {$url}\n";
echo "Limits: {$maxNm} NM, {$maxFt} ft\n\n";

[$planes, $error] = airdefender_fetch_feed($url);
if ($planes === null) {
    fwrite(STDERR, "Could not check: {$error}\n");
    exit(2);
}

$rows = [];
foreach ($planes as $p) {
    $d = airdefender_distance_nm($lat, $lon, $p['lat'], $p['lon']);
    $inside = !$p['onGround'] && $d <= $maxNm && $p['altFt'] !== null && $p['altFt'] <= $maxFt;
    $rows[] = [$d, $p, $inside];
}
usort($rows, function ($a, $b) { return $a[0] <=> $b[0]; });

$hits = 0;
foreach ($rows as [$d, $p, $inside]) {
    $alt = $p['onGround'] ? 'ground' : ($p['altFt'] === null ? '?' : number_format($p['altFt']) . ' ft');
    printf("  %-9s %6.2f NM  %10s  %s\n", $p['id'], $d, $alt, $inside ? 'INSIDE LIMITS' : '');
    $hits += $inside ? 1 : 0;
}
if (!$rows) {
    echo "  No aircraft in range.\n";
}

echo "\n" . ($hits ? "TRIGGER: {$hits} aircraft inside the limits; heads would close.\n" : "CLEAR: no aircraft inside the limits.\n");
exit($hits ? 1 : 0);
