<?php
require_once("/opt/fpp/www/common.php");

$pluginName = "AirDefender";

// Handle form submission
if (isset($_POST['saveSettings'])) {
    SetSetting($pluginName, "enabled", $_POST["enabled"]);
    SetSetting($pluginName, "safetyMode", $_POST["safetyMode"]);
    SetSetting($pluginName, "overrideLocation", $_POST["overrideLocation"]);
    SetSetting($pluginName, "latitude", $_POST["latitude"]);
    SetSetting($pluginName, "longitude", $_POST["longitude"]);
    SetSetting($pluginName, "distanceNM", $_POST["distanceNM"]);
    SetSetting($pluginName, "fetchInterval", $_POST["fetchInterval"]);
    SetSetting($pluginName, "dmxOutputs", $_POST["dmxOutputs"]);

    SaveSettings();
    echo "<div style='padding:10px;background:#2e7d32;color:white;border-radius:5px;margin-bottom:15px;'>Settings Saved</div>";
}

// Load settings
$enabled          = GetSetting($pluginName, "enabled");
$safetyMode       = GetSetting($pluginName, "safetyMode");
$overrideLocation = GetSetting($pluginName, "overrideLocation");
$latitude         = GetSetting($pluginName, "latitude");
$longitude        = GetSetting($pluginName, "longitude");
$distanceNM       = GetSetting($pluginName, "distanceNM");
$fetchInterval    = GetSetting($pluginName, "fetchInterval");
$dmxOutputs       = GetSetting($pluginName, "dmxOutputs");
?>

<div style="background:#1e1e1e;padding:20px;border-radius:10px;margin-bottom:20px;">
    <h2 style="color:#6cf;margin:0;">Air Defender Settings</h2>
    <p style="color:#ccc;margin-top:8px;">Configure ADS‑B safety automation</p>
</div>

<form method="post">

    <div style="background:#2a2a2a;padding:15px;border-radius:8px;margin-bottom:15px;">
        <label style="color:#eee;font-size:16px;">Enable Plugin</label><br>
        <select name="enabled" style="padding:5px;margin-top:5px;">
            <option value="1" <?php echo ($enabled ? "selected" : ""); ?>>Enabled</option>
            <option value="0" <?php echo (!$enabled ? "selected" : ""); ?>>Disabled</option>
        </select>
    </div>

    <div style="background:#2a2a2a;padding:15px;border-radius:8px;margin-bottom:15px;">
        <label style="color:#eee;font-size:16px;">Safety Mode</label><br>
        <select name="safetyMode" style="padding:5px;margin-top:5px;">
            <option value="1" <?php echo ($safetyMode ? "selected" : ""); ?>>Active</option>
            <option value="0" <?php echo (!$safetyMode ? "selected" : ""); ?>>Inactive</option>
        </select>
    </div>

    <div style="background:#2a2a2a;padding:15px;border-radius:8px;margin-bottom:15px;">
        <label style="color:#eee;font-size:16px;">Override Location</label><br>
        <select name="overrideLocation" style="padding:5px;margin-top:5px;">
            <option value="1" <?php echo ($overrideLocation ? "selected" : ""); ?>>Enabled</option>
            <option value="0" <?php echo (!$overrideLocation ? "selected" : ""); ?>>Disabled</option>
        </select>
    </div>

    <div style="background:#2a2a2a;padding:15px;border-radius:8px;margin-bottom:15px;">
        <label style="color:#eee;font-size:16px;">Latitude</label><br>
        <input type="text" name="latitude" value="<?php echo $latitude; ?>" style="width:100%;padding:5px;margin-top:5px;">
    </div>

    <div style="background:#2a2a2a;padding:15px;border-radius:8px;margin-bottom:15px;">
        <label style="color:#eee;font-size:16px;">Longitude</label><br>
        <input type="text" name="longitude" value="<?php echo $longitude; ?>" style="width:100%;padding:5px;margin-top:5px;">
    </div>

    <div style="background:#2a2a2a;padding:15px;border-radius:8px;margin-bottom:15px;">
        <label style="color:#eee;font-size:16px;">Distance Threshold (NM)</label><br>
        <input type="number" name="distanceNM" value="<?php echo $distanceNM; ?>" style="width:100%;padding:5px;margin-top:5px;">
    </div>

    <div style="background:#2a2a2a;padding:15px;border-radius:8px;margin-bottom:15px;">
        <label style="color:#eee;font-size:16px;">ADS‑B Fetch Interval (seconds)</label><br>
        <input type="number" name="fetchInterval" value="<?php echo $fetchInterval; ?>" style="width:100%;padding:5px;margin-top:5px;">
    </div>

    <div style="background:#2a2a2a;padding:15px;border-radius:8px;margin-bottom:15px;">
        <label style="color:#eee;font-size:16px;">DMX Outputs (comma-separated)</label><br>
        <input type="text" name="dmxOutputs" value="<?php echo $dmxOutputs; ?>" style="width:100%;padding:5px;margin-top:5px;">
    </div>

    <button type="submit" name="saveSettings" style="padding:10px 20px;background:#6cf;color:#000;border:none;border-radius:5px;font-size:16px;">
        Save Settings
    </button>

</form>
