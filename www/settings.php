<?php
require_once("/opt/fpp/www/common.php");

if (!function_exists("GetSetting") || !function_exists("SetSetting") || !function_exists("SaveSettings")) {
    require_once("/opt/fpp/www/api/controllers/settings.php");
}

if (!function_exists("GetSetting") || !function_exists("SetSetting") || !function_exists("SaveSettings")) {
    echo "<div style='padding:20px;background:#3a1f1f;color:#fff;border-radius:8px;'>";
    echo "<h2 style='margin:0 0 10px 0;'>AirDefender requires the FPP settings API</h2>";
    echo "<p style='margin:0;'>This page is not running inside a valid FPP plugin environment.</p>";
    echo "</div>";
    exit;
}

$pluginName = "AirDefender";

// Handle form submission
if (isset($_POST['saveSettings'])) {
    $enabled          = isset($_POST["enabled"]) ? (int)$_POST["enabled"] : 0;
    $safetyMode       = isset($_POST["safetyMode"]) ? (int)$_POST["safetyMode"] : 0;
    $overrideLocation = isset($_POST["overrideLocation"]) ? (int)$_POST["overrideLocation"] : 0;
    $latitude         = isset($_POST["latitude"]) ? trim($_POST["latitude"]) : "";
    $longitude        = isset($_POST["longitude"]) ? trim($_POST["longitude"]) : "";
    $distanceNM       = isset($_POST["distanceNM"]) ? (int)$_POST["distanceNM"] : 0;
    $fetchInterval    = isset($_POST["fetchInterval"]) ? (int)$_POST["fetchInterval"] : 0;
    $dmxOutputs       = isset($_POST["dmxOutputs"]) ? trim($_POST["dmxOutputs"]) : "";

    SetSetting($pluginName, "enabled", $enabled);
    SetSetting($pluginName, "safetyMode", $safetyMode);
    SetSetting($pluginName, "overrideLocation", $overrideLocation);
    SetSetting($pluginName, "latitude", $latitude);
    SetSetting($pluginName, "longitude", $longitude);
    SetSetting($pluginName, "distanceNM", $distanceNM);
    SetSetting($pluginName, "fetchInterval", $fetchInterval);
    SetSetting($pluginName, "dmxOutputs", $dmxOutputs);

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
    <p style="color:#ccc;margin-top:8px;">Configure ADS-B safety automation</p>
</div>

<form method="post">

    <div style="background:#2a2a2a;padding:15px;border-radius:8px;margin-bottom:15px;">
        <label style="color:#eee;font-size:16px;">Enable Plugin</label><br>
        <select name="enabled" style="padding:5px;margin-top:5px;">
            <option value="1" <?php echo (!empty($enabled) ? "selected" : ""); ?>>Enabled</option>
            <option value="0" <?php echo (empty($enabled) ? "selected" : ""); ?>>Disabled</option>
        </select>
    </div>

    <div style="background:#2a2a2a;padding:15px;border-radius:8px;margin-bottom:15px;">
        <label style="color:#eee;font-size:16px;">Safety Mode</label><br>
        <select name="safetyMode" style="padding:5px;margin-top:5px;">
            <option value="1" <?php echo (!empty($safetyMode) ? "selected" : ""); ?>>Active</option>
            <option value="0" <?php echo (empty($safetyMode) ? "selected" : ""); ?>>Inactive</option>
        </select>
    </div>

    <div style="background:#2a2a2a;padding:15px;border-radius:8px;margin-bottom:15px;">
        <label style="color:#eee;font-size:16px;">Override Location</label><br>
        <select name="overrideLocation" style="padding:5px;margin-top:5px;">
            <option value="1" <?php echo (!empty($overrideLocation) ? "selected" : ""); ?>>Enabled</option>
            <option value="0" <?php echo (empty($overrideLocation) ? "selected" : ""); ?>>Disabled</option>
        </select>
    </div>

    <div style="background:#2a2a2a;padding:15px;border-radius:8px;margin-bottom:15px;">
        <label style="color:#eee;font-size:16px;">Latitude</label><br>
        <input type="text" name="latitude" value="<?php echo htmlspecialchars($latitude ?? ''); ?>" style="width:100%;padding:5px;margin-top:5px;">
    </div>

    <div style="background:#2a2a2a;padding:15px;border-radius:8px;margin-bottom:15px;">
        <label style="color:#eee;font-size:16px;">Longitude</label><br>
        <input type="text" name="longitude" value="<?php echo htmlspecialchars($longitude ?? ''); ?>" style="width:100%;padding:5px;margin-top:5px;">
    </div>

    <div style="background:#2a2a2a;padding:15px;border-radius:8px;margin-bottom:15px;">
        <label style="color:#eee;font-size:16px;">Distance Threshold (NM)</label><br>
        <input type="number" name="distanceNM" value="<?php echo htmlspecialchars((string)($distanceNM ?? 0)); ?>" style="width:100%;padding:5px;margin-top:5px;">
    </div>

    <div style="background:#2a2a2a;padding:15px;border-radius:8px;margin-bottom:15px;">
        <label style="color:#eee;font-size:16px;">ADS-B Fetch Interval (seconds)</label><br>
        <input type="number" name="fetchInterval" value="<?php echo htmlspecialchars((string)($fetchInterval ?? 0)); ?>" style="width:100%;padding:5px;margin-top:5px;">
    </div>

    <div style="background:#2a2a2a;padding:15px;border-radius:8px;margin-bottom:15px;">
        <label style="color:#eee;font-size:16px;">DMX Outputs (comma-separated)</label><br>
        <input type="text" name="dmxOutputs" value="<?php echo htmlspecialchars($dmxOutputs ?? ''); ?>" style="width:100%;padding:5px;margin-top:5px;">
    </div>

    <button type="submit" name="saveSettings" style="padding:10px 20px;background:#6cf;color:#000;border:none;border-radius:5px;font-size:16px;">
        Save Settings
    </button>

</form>
