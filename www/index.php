<?php
require_once("/opt/fpp/www/common.php");

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
    <p style="color:#ccc;margin-top:8px;">ADS‑B Safety Monitoring Dashboard</p>
</div>

<div style="display:flex;flex-wrap:wrap;">

    <div style="flex:1;background:#2a2a2a;padding:15px;border-radius:8px;margin:5px;">
        <h3 style="color:<?php echo $enabled ? '#4caf50' : '#f44336'; ?>;margin:0 0 10px 0;font-size:18px;">
            Plugin Enabled
        </h3>
        <p style="color:#eee;font-size:16px;margin:0;"><?php echo $enabled ? "Yes" : "No"; ?></p>
    </div>

    <div style="flex:1;background:#2a2a2a;padding:15px;border-radius:8px;margin:5px;">
        <h3 style="color:<?php echo $safetyMode ? '#ff9800' : '#888'; ?>;margin:0 0 10px 0;font-size:18px;">
            Safety Mode
        </h3>
        <p style="color:#eee;font-size:16px;margin:0;"><?php echo $safetyMode ? "Active" : "Inactive"; ?></p>
    </div>

    <div style="flex:1;background:#2a2a2a;padding:15px;border-radius:8px;margin:5px;">
        <h3 style="color:#6cf;margin:0 0 10px 0;font-size:18px;">Distance Threshold</h3>
        <p style="color:#eee;font-size:16px;margin:0;"><?php echo $distanceNM; ?> NM</p>
    </div>

    <div style="flex:1;background:#2a2a2a;padding:15px;border-radius:8px;margin:5px;">
        <h3 style="color:#6cf;margin:0 0 10px 0;font-size:18px;">Fetch Interval</h3>
        <p style="color:#eee;font-size:16px;margin:0;"><?php echo $fetchInterval; ?> sec</p>
    </div>

</div>

<?php if ($overrideLocation): ?>
<div style="background:#2a2a2a;padding:15px;border-radius:8px;margin-top:20px;">
    <h3 style="color:#6cf;margin:0 0 10px 0;">Override Location</h3>
    <p style="color:#eee;margin:0;">Latitude: <?php echo $latitude; ?></p>
    <p style="color:#eee;margin:0;">Longitude: <?php echo $longitude; ?></p>
</div>
<?php endif; ?>

<div style="background:#2a2a2a;padding:15px;border-radius:8px;margin-top:20px;">
    <h3 style="color:#6cf;margin:0 0 10px 0;">Last Event</h3>
    <p style="color:#eee;margin:0;"><?php echo $lastLog; ?></p>
</div>
