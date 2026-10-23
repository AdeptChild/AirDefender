<?php
require_once("/opt/fpp/www/common.php");
require_once("/opt/fpp/www/api/lib/limonade.php");

if (!function_exists("GetSetting")) {
    require_once("/opt/fpp/www/api/controllers/settings.php");
}

if (!function_exists("GetSetting")) {
    echo "<div style='padding:20px;background:#3a1f1f;color:#fff;border-radius:8px;'>";
    echo "<h2 style='margin:0 0 10px 0;'>AirDefender requires the FPP settings API</h2>";
    echo "<p style='margin:0;'>This page is not running inside a valid FPP plugin environment.</p>";
    echo "</div>";
    exit;
}

$pluginName = "AirDefender";

function AirDefender_StateFile() {
    return "/home/fpp/media/plugins/AirDefender/state.json";
}

function AirDefender_ReadState() {
    $path = AirDefender_StateFile();
    if (!file_exists($path)) {
        return [
            'safetyMode' => false,
            'lastEvent' => 'No events yet.',
            'lastAircraft' => null,
            'lastDistanceNm' => null,
            'lastAltitudeFt' => null,
            'lastUpdated' => null,
        ];
    }

    $content = @file_get_contents($path);
    if ($content === false || trim($content) === '') {
        return [
            'safetyMode' => false,
            'lastEvent' => 'No events yet.',
            'lastAircraft' => null,
            'lastDistanceNm' => null,
            'lastAltitudeFt' => null,
            'lastUpdated' => null,
        ];
    }

    $data = json_decode($content, true);
    if (!is_array($data)) {
        return [
            'safetyMode' => false,
            'lastEvent' => 'No events yet.',
            'lastAircraft' => null,
            'lastDistanceNm' => null,
            'lastAltitudeFt' => null,
            'lastUpdated' => null,
        ];
    }

    return $data;
}

$enabled = GetSetting($pluginName, "enabled");
$safetyMode = GetSetting($pluginName, "safetyMode");
$useFPPLocation = GetSetting($pluginName, "useFPPLocation");
$overrideLocation = GetSetting($pluginName, "overrideLocation");
$latitude = GetSetting($pluginName, "latitude");
$longitude = GetSetting($pluginName, "longitude");
$distanceNM = GetSetting($pluginName, "distanceNM");
$altitudeThresholdFt = GetSetting($pluginName, "altitudeThresholdFt");
$fetchInterval = GetSetting($pluginName, "fetchInterval");
$adsbSourceUrl = GetSetting($pluginName, "adsbSourceUrl");
$controllerType = GetSetting($pluginName, "controllerType");
$serialPort = GetSetting($pluginName, "serialPort");

$state = AirDefender_ReadState();
$lastLog = isset($state['lastEvent']) ? $state['lastEvent'] : 'No log entries yet.';
$lastAircraft = $state['lastAircraft'] ?? null;

$logFile = "/home/fpp/media/logs/airdefender.log";
if (file_exists($logFile)) {
    $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines && count($lines) > 0) {
        $lastLog = end($lines);
    }
}
?>
<div style="background:#1e1e1e;padding:20px;border-radius:10px;margin-bottom:20px;">
    <h2 style="color:#6cf;margin:0;">Air Defender</h2>
    <p style="color:#ccc;margin-top:8px;">ADS-B Safety Monitoring Dashboard</p>
</div>

<div style="display:flex;flex-wrap:wrap;">
    <div style="flex:1;background:#2a2a2a;padding:15px;border-radius:8px;margin:5px;">
        <h3 style="color:<?php echo !empty($enabled) ? '#4caf50' : '#f44336'; ?>;margin:0 0 10px 0;font-size:18px;">
            Plugin Enabled
        </h3>
        <p style="color:#eee;font-size:16px;margin:0;">
            <?php echo !empty($enabled) ? "Yes" : "No"; ?>
        </p>
    </div>

    <div style="flex:1;background:#2a2a2a;padding:15px;border-radius:8px;margin:5px;">
        <h3 style="color:<?php echo !empty($state['safetyMode']) ? '#ff9800' : '#888'; ?>;margin:0 0 10px 0;font-size:18px;">
            Safety Mode
        </h3>
        <p style="color:#eee;font-size:16px;margin:0;">
            <?php echo !empty($state['safetyMode']) ? "Active" : "Inactive"; ?>
        </p>
    </div>

    <div style="flex:1;background:#2a2a2a;padding:15px;border-radius:8px;margin:5px;">
        <h3 style="color:#6cf;margin:0 0 10px 0;font-size:18px;">Distance Threshold</h3>
        <p style="color:#eee;font-size:16px;margin:0;">
            <?php echo isset($distanceNM) ? $distanceNM : 0; ?> NM
        </p>
    </div>

    <div style="flex:1;background:#2a2a2a;padding:15px;border-radius:8px;margin:5px;">
        <h3 style="color:#6cf;margin:0 0 10px 0;font-size:18px;">Altitude Threshold</h3>
        <p style="color:#eee;font-size:16px;margin:0;">
            <?php echo isset($altitudeThresholdFt) ? $altitudeThresholdFt : 3000; ?> ft
        </p>
    </div>
</div>

<div style="display:flex;flex-wrap:wrap;margin-top:20px;">
    <div style="flex:1;background:#2a2a2a;padding:15px;border-radius:8px;margin:5px;">
        <h3 style="color:#6cf;margin:0 0 10px 0;font-size:18px;">Location Source</h3>
        <p style="color:#eee;font-size:16px;margin:0;">
            <?php echo !empty($useFPPLocation) ? "FPP" : "Manual"; ?>
        </p>
    </div>

    <div style="flex:1;background:#2a2a2a;padding:15px;border-radius:8px;margin:5px;">
        <h3 style="color:#6cf;margin:0 0 10px 0;font-size:18px;">Controller</h3>
        <p style="color:#eee;font-size:16px;margin:0;">
            <?php echo htmlspecialchars((string) $controllerType ?: "falcon"); ?>
        </p>
    </div>

    <div style="flex:1;background:#2a2a2a;padding:15px;border-radius:8px;margin:5px;">
        <h3 style="color:#6cf;margin:0 0 10px 0;font-size:18px;">Serial Port</h3>
        <p style="color:#eee;font-size:16px;margin:0;">
            <?php echo htmlspecialchars((string) $serialPort ?: "Not Selected"); ?>
        </p>
    </div>
</div>

<?php if (!empty($overrideLocation)): ?>
<div style="background:#2a2a2a;padding:15px;border-radius:8px;margin-top:20px;">
    <h3 style="color:#6cf;margin:0 0 10px 0;">Manual Override Location</h3>
    <p style="color:#eee;margin:0;">Latitude: <?php echo htmlspecialchars((string) $latitude); ?></p>
    <p style="color:#eee;margin:0;">Longitude: <?php echo htmlspecialchars((string) $longitude); ?></p>
</div>
<?php endif; ?>

<?php if (!empty($adsbSourceUrl)): ?>
<div style="background:#2a2a2a;padding:15px;border-radius:8px;margin-top:20px;">
    <h3 style="color:#6cf;margin:0 0 10px 0;">ADS-B Feed URL</h3>
    <p style="color:#eee;margin:0;word-break:break-all;">
        <?php echo htmlspecialchars((string) $adsbSourceUrl); ?>
    </p>
</div>
<?php endif; ?>

<?php if (!empty($lastAircraft)): ?>
<div style="background:#2a2a2a;padding:15px;border-radius:8px;margin-top:20px;">
    <h3 style="color:#6cf;margin:0 0 10px 0;">Last Affected Aircraft</h3>
    <p style="color:#eee;margin:0;">
        <?php echo htmlspecialchars($lastAircraft['flight'] ?? $lastAircraft['hex'] ?? 'Unknown'); ?>
        @ <?php echo isset($lastAircraft['distanceNm']) ? $lastAircraft['distanceNm'] : 'n/a'; ?> NM,
        <?php echo isset($lastAircraft['altitudeFt']) ? $lastAircraft['altitudeFt'] : 'n/a'; ?> ft
    </p>
</div>
<?php endif; ?>

<div style="background:#2a2a2a;padding:15px;border-radius:8px;margin-top:20px;">
    <h3 style="color:#6cf;margin:0 0 10px 0;">Last Event</h3>
    <p style="color:#eee;margin:0;">
        <?php echo htmlspecialchars($lastLog); ?>
    </p>
</div>
