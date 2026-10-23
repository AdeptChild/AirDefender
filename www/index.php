<?php

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

<style>
.card {
    background: #222;
    padding: 20px;
    border-radius: 8px;
    margin-bottom: 20px;
}
.label { font-weight: bold; color: #9cf; }
.value { color: #fff; }
h2 { color: #6cf; }
</style>

<div class="card">
    <h2>Air Defender Status</h2>

    <p><span class="label">Plugin Enabled:</span>
        <span class="value"><?php echo $enabled ? "Yes" : "No"; ?></span>
    </p>

    <p><span class="label">Safety Mode:</span>
        <span class="value"><?php echo $safetyMode ? "Active" : "Inactive"; ?></span>
    </p>

    <p><span class="label">Override Location:</span>
        <span class="value"><?php echo $overrideLocation ? "Yes" : "No"; ?></span>
    </p>

    <?php if ($overrideLocation): ?>
        <p><span class="label">Latitude:</span>
            <span class="value"><?php echo $latitude; ?></span>
        </p>
        <p><span class="label">Longitude:</span>
            <span class="value"><?php echo $longitude; ?></span>
        </p>
    <?php endif; ?>

    <p><span class="label">Distance Threshold:</span>
        <span class="value"><?php echo $distanceNM; ?> NM</span>
    </p>

    <p><span class="label">Fetch Interval:</span>
        <span class="value"><?php echo $fetchInterval; ?> seconds</span>
    </p>

    <p><span class="label">DMX Outputs:</span>
        <span class="value"><?php echo $dmxOutputs ? $dmxOutputs : "None"; ?></span>
    </p>
</div>

<div class="card">
    <h2>Last Event</h2>
    <p><?php echo $lastLog; ?></p>
</div>

