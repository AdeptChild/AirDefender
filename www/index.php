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

function AirDefender_ConfigPath($pluginName) {
    global $mediaDirectory;
    $dir = !empty($mediaDirectory) ? $mediaDirectory : '/home/fpp/media';
    return $dir . '/config/plugin.' . $pluginName;
}

function AirDefender_LoadAll($pluginName) {
    $path = AirDefender_ConfigPath($pluginName);
    $data = array();

    if (!is_readable($path)) {
        return $data;
    }

    $lines = @file($path, FILE_IGNORE_NEW_LINES);
    if (!is_array($lines)) {
        return $data;
    }

    foreach ($lines as $line) {
        $parts = explode('=', $line, 2);
        if (count($parts) === 2) {
            $data[trim($parts[0])] = trim(trim($parts[1]), "\"");
        }
    }

    return $data;
}

function AirDefender_StoredValue($pluginName, $key, $default) {
    static $stored = null;
    if ($stored === null) {
        $stored = AirDefender_LoadAll($pluginName);
    }

    $value = isset($stored[$key]) ? $stored[$key] : null;
    return $value === null || $value === false || $value === "" ? $default : $value;
}

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

$enabled = AirDefender_StoredValue("AirDefender", "enabled", false);
$safetyMode = AirDefender_StoredValue("AirDefender", "safetyMode", false);
$useFPPLocation = AirDefender_StoredValue("AirDefender", "useFPPLocation", true);
$latitude = AirDefender_StoredValue("AirDefender", "latitude", "");
$longitude = AirDefender_StoredValue("AirDefender", "longitude", "");
$distanceNM = AirDefender_StoredValue("AirDefender", "distanceNM", 5);
$altitudeThresholdFt = AirDefender_StoredValue("AirDefender", "altitudeThresholdFt", 3000);
$fetchInterval = AirDefender_StoredValue("AirDefender", "fetchInterval", 10);
$adsbSourceUrl = AirDefender_StoredValue("AirDefender", "adsbSourceUrl", "https://api.airplanes.live/v2/point/{lat}/{lon}/{radius}");
$controllerType = AirDefender_StoredValue("AirDefender", "controllerType", "falcon");
$blackoutStartChannel = AirDefender_StoredValue("AirDefender", "blackoutStartChannel", 0);
$blackoutEndChannel = AirDefender_StoredValue("AirDefender", "blackoutEndChannel", 0);

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
<div style="background:#1e1e1e;padding:20px;border-radius:10px;margin-bottom:20px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;">
    <div>
        <h2 style="color:#6cf;margin:0;">Air Defender</h2>
        <p style="color:#ccc;margin-top:8px;">ADS-B Safety Monitoring Dashboard</p>
    </div>
    <div style="display:flex;gap:10px;flex-wrap:wrap;">
        <a href="plugin.php?plugin=AirDefender&page=www/settings.php" style="display:inline-block;padding:9px 14px;border-radius:8px;background:#6cf;color:#111;text-decoration:none;font-weight:bold;">
            Settings
        </a>
        <a href="plugin.php?plugin=AirDefender&page=www/index.php" style="display:inline-block;padding:9px 14px;border-radius:8px;background:#444;color:#fff;text-decoration:none;font-weight:bold;">
            Refresh
        </a>
    </div>
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
        <h3 style="color:#6cf;margin:0 0 10px 0;font-size:18px;">Blackout Range</h3>
        <p style="color:#eee;font-size:16px;margin:0;">
            <?php
                $start = (int) $blackoutStartChannel;
                $end = (int) $blackoutEndChannel;
                if ($start > 0 || $end > 0) {
                    echo htmlspecialchars((string) $start . " - " . $end);
                } else {
                    echo "Not configured";
                }
            ?>
        </p>
    </div>
</div>

<?php if (!empty($latitude) || !empty($longitude)): ?>
<div style="background:#2a2a2a;padding:15px;border-radius:8px;margin-top:20px;">
    <h3 style="color:#6cf;margin:0 0 10px 0;">Location</h3>
    <p style="color:#eee;margin:0;">Latitude: <?php echo htmlspecialchars((string) $latitude ?: "Not set"); ?></p>
    <p style="color:#eee;margin:0;">Longitude: <?php echo htmlspecialchars((string) $longitude ?: "Not set"); ?></p>
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
        @ <?php echo isset($lastAircraft['distanceNm']) ? $lastAircraft['distanceNm'] : ($state['lastDistanceNm'] ?? 'n/a'); ?> NM,
        <?php echo isset($lastAircraft['altitudeFt']) ? $lastAircraft['altitudeFt'] : ($state['lastAltitudeFt'] ?? 'n/a'); ?> ft
    </p>
</div>
<?php endif; ?>

<div style="background:#2a2a2a;padding:15px;border-radius:8px;margin-top:20px;">
    <h3 style="color:#6cf;margin:0 0 10px 0;">Last Event</h3>
    <p style="color:#eee;margin:0;">
        <?php echo htmlspecialchars($lastLog); ?>
    </p>
</div>
