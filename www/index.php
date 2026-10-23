<?php
require_once("/opt/fpp/www/common.php");
require_once("/opt/fpp/www/api/common.php");

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

$enabled          = GetSetting($pluginName, "enabled");
$safetyMode       = GetSetting($pluginName, "safetyMode");
$overrideLocation = GetSetting($pluginName, "overrideLocation");
$latitude         = GetSetting($pluginName, "latitude");
$longitude        = GetSetting($pluginName, "longitude");
$distanceNM       = GetSetting($pluginName, "distanceNM");
$fetchInterval    = GetSetting($pluginName, "fetchInterval");
$dmxOutputs       = GetSetting($pluginName, "dmxOutputs");

$logFile = "/home/fpp/media/logs/airdefender.log";
$lastLog = "No log entries yet.";

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
        <p style="color:#eee;font-size:16px;margin:0;"><?php echo !empty($enabled) ? "Yes" : "No"; ?></p>
    </div>

    <div style="flex:1;background:#2a2a2a;padding:15px;border-radius:8px;margin:5px;">
        <h3 style="color:<?php echo !empty($safetyMode) ? '#ff9800' : '#888'; ?>;margin:0 0 10px 0;font-size:18px;">
            Safety Mode
        </h3>
        <p style="color:#eee;font-size:16px;margin:0;"><?php echo !empty($safetyMode) ? "Active" : "Inactive"; ?></p>
    </div>

    <div style="flex:1;background:#2a2a2a;padding:15px;border-radius:8px;margin:5px;">
        <h3 style="color:#6cf;margin:0 0 10px 0;font-size:18px;">Distance Threshold</h3>
        <p style="color:#eee;font-size:16px;margin:0;"><?php echo isset($distanceNM) ? $distanceNM : 0; ?> NM</p>
    </div>

    <div style="flex:1;background:#2a2a2a;padding:15px;border-radius:8px;margin:5px;">
        <h3 style="color:#6cf;margin:0 0 10px 0;font-size:18px;">Fetch Interval</h3>
        <p style="color:#eee;font-size:16px;margin:0;"><?php echo isset($fetchInterval) ? $fetchInterval : 0; ?> sec</p>
    </div>

</div>

<?php if (!empty($overrideLocation)): ?>
<div style="background:#2a2a2a;padding:15px;border-radius:8px;margin-top:20px;">
    <h3 style="color:#6cf;margin:0 0 10px 0;">Override Location</h3>
    <p style="color:#eee;margin:0;">Latitude: <?php echo $latitude; ?></p>
    <p style="color:#eee;margin:0;">Longitude: <?php echo $longitude; ?></p>
</div>
<?php endif; ?>

<div style="background:#2a2a2a;padding:15px;border-radius:8px;margin-top:20px;">
    <h3 style="color:#6cf;margin:0 0 10px 0;">Last Event</h3>
    <p style="color:#eee;margin:0;"><?php echo htmlspecialchars($lastLog); ?></p>
</div>
