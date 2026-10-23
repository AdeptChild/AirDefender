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

<div style="background:#222;padding:20px;border-radius:8px;margin-bottom:20px;">
    <h2 style="color:#6cf;">Air Defender Status</h2>

    <p><strong>Plugin Enabled:</strong> <?php echo $enabled ? "Yes" : "No"; ?></p>
    <p><strong>Safety Mode:</strong> <?php echo $safetyMode ? "Active" : "Inactive"; ?></p>
    <p><strong>Override Location:</strong> <?php echo $overrideLocation ? "Yes" : "No"; ?></p>

    <?php if ($overrideLocation): ?>
        <p><strong>Latitude:</strong> <?php echo $latitude; ?></p>
        <p><strong>Longitude:</strong> <?php echo $longitude; ?></p>
    <?php endif; ?>

    <p><strong>Distance Threshold:</strong> <?php echo $distanceNM; ?> NM</p>
    <p><strong>Fetch Interval:</strong> <?php echo $fetchInterval; ?> seconds</p>
    <p><strong>DMX Outputs:</strong> <?php echo $dmxOutputs ? $dmxOutputs : "None"; ?></p>
</div>

<div style="background:#222;padding:20px;border-radius:8px;">
    <h2 style="color:#6cf;">Last Event</h2>
    <p><?php echo $lastLog; ?></p>
</div>

