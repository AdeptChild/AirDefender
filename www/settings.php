<?php
require_once("/opt/fpp/www/common.php");
require_once(dirname(__DIR__) . "/scripts/airdefender_paths.php");
require_once("/opt/fpp/www/api/lib/limonade.php");

if (!function_exists("GetSetting")) {
    require_once("/opt/fpp/www/api/controllers/settings.php");
}

if (!function_exists("GetSetting")) {
    echo "<div class='alert alert-danger'>";
    echo "<h2 class='h5'>Air Defender requires the FPP settings API</h2>";
    echo "<p style='margin:0;'>This page is not running inside a valid FPP plugin environment.</p>";
    echo "</div>";
    exit;
}

$pluginName = "AirDefender";

require_once(dirname(__DIR__) . "/scripts/airdefender_models.php");
require_once(dirname(__DIR__) . "/scripts/airdefender_feeds.php");
require_once(dirname(__DIR__) . "/scripts/airdefender_adsb.php");
require_once(dirname(__DIR__) . "/scripts/airdefender_secret.php");
require_once(dirname(__DIR__) . "/scripts/airdefender_playlists.php");
require_once(dirname(__DIR__) . "/scripts/airdefender_version.php");
$airdefenderModels = airdefender_fpp_models();
$airdefenderState = json_decode((string) @file_get_contents(dirname(__DIR__) . "/state.json"), true);
$airdefenderLastKnown = is_array($airdefenderState['lastKnownChannels'] ?? null) ? $airdefenderState['lastKnownChannels'] : array();

// Snapshot FPP's own global $settings array before this page defines its own $settings below.
$AirDefenderFppSettings = (isset($GLOBALS['settings']) && is_array($GLOBALS['settings'])) ? $GLOBALS['settings'] : array();

function AirDefender_NormalizeValue($value, $default) {
    if ($value === null || $value === false) {
        return $default;
    }

    if (is_array($value)) {
        return $default;
    }

    if (is_string($value)) {
        $trimmed = trim($value);
        if ($trimmed === "" || $trimmed === "[]" || strtolower($trimmed) === "null") {
            return $default;
        }
    }

    return $value;
}

function AirDefender_ConfigPath($pluginName) {
    return airdefender_config_file();
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

function AirDefender_SaveAll($pluginName, $values) {
    $path = AirDefender_ConfigPath($pluginName);
    $data = array_merge(AirDefender_LoadAll($pluginName), $values);
    $out = '';
    foreach ($data as $k => $v) {
        $out .= $k . ' = "' . str_replace(array('"', "\n", "\r"), '', (string) $v) . '"' . "\n";
    }
    return @file_put_contents($path, $out, LOCK_EX) !== false;
}

function AirDefender_StoredValue($pluginName, $key, $default) {
    static $stored = null;
    if ($stored === null) {
        $stored = AirDefender_LoadAll($pluginName);
    }
    $value = isset($stored[$key]) ? $stored[$key] : null;
    return AirDefender_NormalizeValue($value, $default);
}

function AirDefender_ParseCoordinate($value, $min, $max) {
    if ($value === null || $value === false || is_array($value)) {
        return null;
    }

    $text = trim((string) $value);
    $text = trim($text, "\"' ");
    if ($text === "" || strtolower($text) === "null" || !is_numeric($text)) {
        return null;
    }

    $number = (float) $text;
    if ($number < $min || $number > $max) {
        return null;
    }

    return $number;
}

function AirDefender_ReadFPPSettingsFile($name) {
    $paths = array(airdefender_fpp_settings_file());
    foreach ($paths as $path) {
        if (!is_readable($path)) {
            continue;
        }

        $lines = @file($path, FILE_IGNORE_NEW_LINES);
        if (!is_array($lines)) {
            continue;
        }

        foreach ($lines as $line) {
            $parts = explode('=', $line, 2);
            if (count($parts) === 2 && trim($parts[0]) === $name) {
                return trim($parts[1]);
            }
        }
    }
    return null;
}

function AirDefender_GetFPPSettingValue($fppSettings, $name) {
    // 1) FPP's global $settings array
    if (is_array($fppSettings) && isset($fppSettings[$name]) && $fppSettings[$name] !== '') {
        return $fppSettings[$name];
    }

    // 2) FPP helper
    if (function_exists('ReadSettingFromFile')) {
        $value = @ReadSettingFromFile($name);
        if ($value !== null && $value !== false && $value !== '') {
            return $value;
        }
    }

    // 3) Direct read from FPP settings file
    return AirDefender_ReadFPPSettingsFile($name);
}

function AirDefender_GetFPPDetectedLocation($fppSettings) {
    $lat = AirDefender_ParseCoordinate(AirDefender_GetFPPSettingValue($fppSettings, 'Latitude'), -90, 90);
    $lon = AirDefender_ParseCoordinate(AirDefender_GetFPPSettingValue($fppSettings, 'Longitude'), -180, 180);
    return array($lat, $lon);
}

function AirDefender_PostBool($key) {
    if (!isset($_POST[$key])) {
        return false;
    }

    $value = $_POST[$key];
    if (is_bool($value)) {
        return $value;
    }

    return in_array(strtolower((string) $value), array('1', 'true', 'yes', 'on', 'y'), true);
}

function AirDefender_IsTruthy($value) {
    if (is_bool($value)) {
        return $value;
    }

    if (is_int($value) || is_float($value)) {
        return $value != 0;
    }

    if (is_string($value)) {
        return in_array(strtolower(trim($value)), array('1', 'true', 'yes', 'on', 'y'), true);
    }

    return !empty($value);
}

$settings = array(
    'nodeRole' => array(
        'label' => 'Operation Mode',
        'type' => 'select',
        'default' => 'master',
        'options' => array(
            'master' => 'Master',
            'remote' => 'Remote',
        ),
    ),
    'masterAddress' => array(
        'label' => 'Master FPP Address',
        'type' => 'string',
        'default' => '',
    ),
    // The master's FPP UI password, for a Remote whose master has one. Never
    // shown back on the page; a blank field keeps the saved one. Kept in
    // plugindata (0600), not in the config file: see airdefender_secret.php.
    'masterPassword' => array(
        'label' => "Master's UI Password",
        'type' => 'string',
        'default' => '',
    ),
    // Off until switched on, like the detection loop: nothing is sent anywhere
    // until the show's owner turns Air Defender on.
    'enabled' => array(
        'label' => 'Enable Air Defender',
        'type' => 'bool',
        'default' => false,
    ),
    'useFPPLocation' => array(
        'label' => 'Use FPP Location',
        'type' => 'bool',
        'default' => true,
    ),
    'latitude' => array(
        'label' => 'Latitude',
        'type' => 'string',
        'default' => '',
    ),
    'longitude' => array(
        'label' => 'Longitude',
        'type' => 'string',
        'default' => '',
    ),
    'showElevationFt' => array(
        'label' => 'Show elevation (ft above sea level)',
        'type' => 'int',
        'default' => 0,
    ),
    'distanceNM' => array(
        'label' => 'Distance Threshold (NM)',
        'type' => 'int',
        'default' => 5,
    ),
    'altitudeThresholdFt' => array(
        'label' => 'Altitude Threshold (ft)',
        'type' => 'int',
        'default' => 3000,
    ),
    'fetchInterval' => array(
        'label' => 'ADS-B Fetch Interval (seconds)',
        'type' => 'int',
        'default' => 10,
    ),
    'checkWhen' => array(
        'label' => 'When to Check',
        'type' => 'select',
        'default' => 'scheduled',
        // "playing" is Only selected playlists (the key is kept so saved settings
        // still work). "always" was removed in 1.2.0 and reads as "playing".
        'options' => array(
            'scheduled' => 'All scheduled shows (default)',
            'playing' => 'Only selected playlists',
        ),
    ),
    // "Only for these playlists": URL-encoded names joined with commas; empty
    // means every playlist (see airdefender_playlists.php).
    'checkPlaylists' => array(
        'label' => 'Only For These Playlists',
        'type' => 'string',
        'default' => '',
    ),
    'clearDelaySec' => array(
        'label' => 'Hold After Aircraft Leaves (seconds)',
        'type' => 'int',
        'default' => 30,
    ),
    'feedFallback' => array(
        'label' => 'Use the Other Service as a Backup',
        'type' => 'bool',
        // Off until switched on: FPP's guidelines allow only the one service the
        // plugin exists for (the chosen one) to be sent anything by default.
        'default' => false,
    ),
    'feedDownSafe' => array(
        'label' => 'Close Heads If Feed Is Down',
        'type' => 'bool',
        'default' => false,
    ),
    'adsbSourceUrl' => array(
        'label' => 'ADS-B JSON URL',
        'type' => 'select',
        'default' => AIRDEFENDER_DEFAULT_FEED,
        'options' => airdefender_feed_options(),
    ),
    // Your own receiver's address (IP or hostname, optional port, or the full URL
    // of its aircraft.json), used when the service is "Your receiver on your network".
    'receiverAddress' => array(
        'label' => 'Receiver Address',
        'type' => 'string',
        'default' => '',
    ),
    'controllerType' => array(
        'label' => 'Controller Type',
        'type' => 'select',
        'default' => 'falcon',
        'options' => array('falcon' => 'Falcon', 'kulp' => 'Kulp', 'generic' => 'Generic'),
    ),
    'blackoutStartChannel' => array(
        'label' => 'Blackout Start Channel',
        'type' => 'int',
        'default' => 0,
    ),
    'blackoutEndChannel' => array(
        'label' => 'Blackout End Channel',
        'type' => 'int',
        'default' => 0,
    ),
);

for ($headIndex = 1; $headIndex <= 8; $headIndex++) {
    $settings['head' . $headIndex . 'Enabled'] = array(
        'label' => 'MH' . $headIndex . ' Enabled',
        'type' => 'bool',
        'default' => false,
    );
    $settings['head' . $headIndex . 'Name'] = array(
        'label' => 'MH' . $headIndex . ' Name',
        'type' => 'string',
        'default' => '',
    );
    $settings['head' . $headIndex . 'Start'] = array(
        'label' => 'MH' . $headIndex . ' Start Channel',
        'type' => 'int',
        'default' => 0,
    );
    $settings['head' . $headIndex . 'Model'] = array(
        'label' => 'MH' . $headIndex . ' Model',
        'type' => 'string',
        'default' => '',
    );
    $settings['head' . $headIndex . 'Offset'] = array(
        'label' => 'MH' . $headIndex . ' Shutter Channel in Fixture',
        'type' => 'int',
        'default' => 1,
    );
    // The FPP channel the head resolves to (kept as its last known channel).
    // Settings from before Start existed hold a manual absolute channel here.
    $settings['head' . $headIndex . 'Channel'] = array(
        'label' => 'MH' . $headIndex . ' Shutter Channel',
        'type' => 'int',
        'default' => 0,
    );
    $settings['head' . $headIndex . 'ClosedValue'] = array(
        'label' => 'MH' . $headIndex . ' Closed Value (0-255)',
        'type' => 'int',
        'default' => 0,
    );
}

$headKeys = array();
for ($headIndex = 1; $headIndex <= 8; $headIndex++) {
    foreach (array('Enabled', 'Name', 'Model', 'Start', 'Offset', 'Channel', 'ClosedValue') as $headField) {
        $headKeys[] = 'head' . $headIndex . $headField;
    }
}

// The legacy Controller group (controllerType, blackoutStartChannel, blackoutEndChannel) is hidden from the UI
// but its keys stay in $settings and in the saved config for runtime compatibility.
$settingGroups = array(
    'Plugin Status' => array('enabled'),
    'Operation Mode' => array('nodeRole', 'masterAddress'),
    'Moving Heads' => $headKeys,
    'Location' => array('useFPPLocation', 'latitude', 'longitude', 'showElevationFt'),
    'Safety Thresholds' => array('checkWhen', 'checkPlaylists', 'distanceNM', 'altitudeThresholdFt', 'fetchInterval', 'clearDelaySec'),
    'ADS-B Feed' => array('adsbSourceUrl', 'receiverAddress', 'feedDownSafe'),
);

$statusMessage = '';
// Reading it also moves a password saved by an older version out of the
// config file, before a save below writes that file again.
airdefender_master_password();
$passwordSaved = true;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $toSave = array();

    foreach ($settings as $key => $config) {
        if (!isset($_POST[$key])) {
            continue;
        }
        if ($key === 'checkPlaylists') {
            // Ticked boxes, plus an empty hidden one so "none ticked" is still sent.
            $toSave[$key] = airdefender_encode_playlists(is_array($_POST[$key]) ? $_POST[$key] : array());
            continue;
        }
        if ($key === 'masterPassword') {
            // Blank keeps the saved password; the Remove box clears it.
            if (!empty($_POST['masterPasswordClear'])) {
                $passwordSaved = airdefender_set_master_password('');
            } elseif ((string) $_POST[$key] !== '') {
                $passwordSaved = airdefender_set_master_password((string) $_POST[$key]);
            }
            continue;
        }

        $postedValue = $_POST[$key];

        if ($config['type'] === 'bool') {
            $toSave[$key] = AirDefender_PostBool($key) ? '1' : '0';
        } elseif ($config['type'] === 'int') {
            $intValue = (int) $postedValue;
            if (preg_match('/^head[1-8]ClosedValue$/', $key)) {
                $intValue = max(0, min(255, $intValue));
            } elseif (preg_match('/^head[1-8]Channel$/', $key)) {
                $intValue = max(0, $intValue);
            } elseif (preg_match('/^head[1-8]Offset$/', $key)) {
                $intValue = max(1, $intValue);
            } elseif (preg_match('/^head[1-8]Start$/', $key)) {
                $intValue = max(0, $intValue);
            } elseif ($key === 'clearDelaySec') {
                $intValue = max(0, min(600, $intValue));
            } elseif ($key === 'fetchInterval') {
                $intValue = max(5, $intValue);
            } elseif ($key === 'showElevationFt') {
                $intValue = airdefender_clamp_elevation($intValue);
            }
            $toSave[$key] = (string) $intValue;
        } elseif ($config['type'] === 'select') {
            $toSave[$key] = (string) $postedValue;
            if ($key === 'nodeRole' && !array_key_exists($toSave[$key], $config['options'])) {
                $toSave[$key] = 'master';
            }
            if ($key === 'checkWhen' && !array_key_exists($toSave[$key], $config['options'])) {
                $toSave[$key] = 'scheduled';
            }
        } else {
            $toSave[$key] = trim((string) $postedValue);
        }
    }

    // Store the channel each head resolves to now: for a model head it is the
    // last known channel in case the model later disappears.
    for ($headIndex = 1; $headIndex <= 8; $headIndex++) {
        // A manual head whose start was cleared has no channel any more.
        if (($toSave['head' . $headIndex . 'Model'] ?? '') === '' && ($toSave['head' . $headIndex . 'Start'] ?? null) === '0') {
            $toSave['head' . $headIndex . 'Channel'] = '0';
            continue;
        }
        $head = airdefender_resolve_head(array_merge(AirDefender_LoadAll($pluginName), $toSave), $headIndex, $airdefenderModels);
        if (in_array($head['status'], array('ok', 'manual'), true)) {
            $toSave['head' . $headIndex . 'Channel'] = (string) $head['channel'];
        }
    }

    if (AirDefender_SaveAll($pluginName, $toSave)) {
        $statusMessage = $passwordSaved ? "Settings saved."
            : "ERROR: settings saved, but the master's password could not be saved in " . airdefender_plugindata_dir() . " (check file permissions).";
    } else {
        $statusMessage = "ERROR: could not write " . AirDefender_ConfigPath($pluginName) . " (check file permissions).";
    }
}

foreach ($settings as $key => $config) {
    $value = AirDefender_StoredValue($pluginName, $key, $config['default']);
    $settings[$key]['value'] = $value;
}
$hasMasterPassword = airdefender_master_password() !== '';

// A retired feed (airplanes.live) shows as its replacement, which is what runs.
list($settings['adsbSourceUrl']['value'], $airdefenderFeedNote) = airdefender_effective_feed((string) $settings['adsbSourceUrl']['value']);

// "Always" was removed: with nothing ticked, Only selected playlists checks whenever FPP plays.
if ($settings['checkWhen']['value'] === 'always') {
    $settings['checkWhen']['value'] = 'playing';
}
// "Standalone" was folded into Master: a master with no remotes is the same thing.
if (!array_key_exists((string) $settings['nodeRole']['value'], $settings['nodeRole']['options'])) {
    $settings['nodeRole']['value'] = 'master';
}

$useFppLocationEnabled = AirDefender_IsTruthy($settings['useFPPLocation']['value']);
list($fppLat, $fppLon) = AirDefender_GetFPPDetectedLocation($AirDefenderFppSettings);

if ($useFppLocationEnabled) {
    if ($fppLat !== null) {
        $settings['latitude']['value'] = (string) $fppLat;
    }
    if ($fppLon !== null) {
        $settings['longitude']['value'] = (string) $fppLon;
    }
}

// Values for the page.
$v = function ($key) use ($settings) { return $settings[$key]['value']; };
$isRemote = $v('nodeRole') === 'remote';
$feedOptionsList = airdefender_feed_options();
// Heads shown: enabled, or with anything set. The rest wait behind "Add head".
$headsShown = array();
for ($n = 1; $n <= 8; $n++) {
    $headsShown[$n] = AirDefender_IsTruthy($v("head{$n}Enabled")) || trim((string) $v("head{$n}Name")) !== ''
        || trim((string) $v("head{$n}Model")) !== '' || (int) $v("head{$n}Start") > 0 || (int) $v("head{$n}Channel") > 0;
}
if (!in_array(true, $headsShown, true)) {
    $headsShown[1] = true;   // always show one row to start from
}
$airdefenderSaved = $_SERVER['REQUEST_METHOD'] === 'POST' && strpos($statusMessage, 'ERROR') !== 0;
?>
<style>
    /* Colours follow FPP's light/dark theme (data-bs-theme on the page):
       light uses FPP's and Bootstrap's own colour variables; dark keeps the
       radar-scope green of the plugin's icon. Nothing below sets a colour
       directly, only these variables. */
    .ads {
        --card: var(--fpp-bg-card, var(--bs-body-bg)); --card2: var(--bs-body-bg); --line: var(--bs-border-color);
        --text: var(--bs-body-color); --soft: var(--bs-secondary-color); --muted: var(--bs-secondary-color);
        --accent: var(--bs-success-text-emphasis); --accent-bg: var(--bs-success); --on-accent: var(--bs-white);
        --input-line: var(--bs-border-color); --input-text: var(--bs-body-color); --disabled-bg: var(--bs-secondary-bg);
        --seg: var(--bs-tertiary-bg); --track: var(--bs-secondary-bg); --knob: var(--bs-white); --knob-off: var(--bs-secondary);
        --ok: var(--bs-success); --good-text: var(--bs-success-text-emphasis); --warn: var(--fpp-header-attention, var(--bs-orange));
        --caution-text: var(--bs-warning-text-emphasis); --err: var(--bs-danger-text-emphasis);
        --msg-ok-bg: var(--bs-success-bg-subtle); --msg-ok: var(--bs-success-text-emphasis);
        --msg-err-bg: var(--bs-danger-bg-subtle); --msg-err: var(--bs-danger-text-emphasis);
        --bar-bg: var(--bs-warning-bg-subtle); --bar-text: var(--bs-warning-text-emphasis);
        color: var(--text); max-width: 70rem; padding-bottom: 5rem;
    }
    [data-bs-theme="dark"] .ads {
        --card: #0f1f18; --card2: #0b1712; --line: #1f3d2d; --text: #e3f2e8; --soft: #c6dccd; --muted: #8fb09c;
        --accent: #7dffb4; --accent-bg: #7dffb4; --on-accent: #0b1712;
        --input-line: #2a5a40; --input-text: #fff; --disabled-bg: #0b1712;
        --seg: #0b1712; --track: #2a5a40; --knob: #fff; --knob-off: #c6dccd;
        --ok: #2fbf71; --good-text: #7dffb4; --warn: #ff9800; --caution-text: #ffb74d; --err: #ff7b72;
        --msg-ok-bg: #1f3a24; --msg-ok: #cfeed6; --msg-err-bg: #4a1f1f; --msg-err: #ffd7d3;
        --bar-bg: #3a2a10; --bar-text: #ffcc80;
    }
    .ads * { box-sizing:border-box; }
    .ads-top { display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap; margin-bottom:12px; }
    .ads-top h2 { color:var(--accent); margin:0; }
    .ads-ver { font-size:13px; color:var(--muted); font-weight:normal; }
    .ads-btn { border:1px solid var(--line); background:var(--card); color:var(--text); border-radius:6px; padding:7px 12px;
           font-size:14px; cursor:pointer; text-decoration:none; display:inline-block; line-height:1.2; min-height:2.25rem; }
    .ads-btn.primary { background:var(--accent-bg); color:var(--on-accent); border-color:var(--accent-bg); font-weight:bold; }
    .ads-msg { padding:10px 14px; border-radius:8px; margin-bottom:12px; font-size:14px; }
    .ads-msg.ok { background:var(--msg-ok-bg); color:var(--msg-ok); } .ads-msg.err { background:var(--msg-err-bg); color:var(--msg-err); }
    .ads-sec { background:var(--card); border:1px solid var(--line); border-radius:12px; padding:14px 18px; margin-bottom:12px; }
    .ads-sec h3 { margin:0 0 12px 0; font-size:16px; color:var(--accent); display:flex; align-items:baseline; gap:10px; }
    .ads-sec h3 .n { color:var(--muted); font-size:13px; font-weight:normal; }
    .ads-fields { display:grid; grid-template-columns:repeat(auto-fit, minmax(min(100%, 27.5rem), 1fr)); gap:0 28px; align-items:start; }
    .ads-fields .ads-field:last-child { margin-bottom:12px; }
    .ads-field { display:grid; grid-template-columns:9.5rem minmax(0,1fr); gap:6px 12px; align-items:center; margin-bottom:12px; }
    .ads-field:last-child { margin-bottom:0; }
    .ads-field > label { color:var(--soft); font-size:14px; }
    .ads-help { grid-column:2; font-size:12px; color:var(--muted); line-height:1.45; }
    .ads input[type=text], .ads input[type=number], .ads input[type=password], .ads select { padding:7px 9px; border-radius:6px;
           border:1px solid var(--input-line); background:var(--card2); color:var(--input-text); font-size:14px; height:auto; line-height:1.3;
           max-width:100%; }
    .ads select { padding-right:28px; }
    .ads input:disabled { background:var(--disabled-bg); color:var(--muted); cursor:not-allowed; }
    .ads-unit { display:inline-flex; align-items:center; gap:6px; flex-wrap:wrap; max-width:100%; }
    .ads-unit input { width:6rem; }
    .ads-unit input.wide { width:7.5rem; }
    .ads-unit span { color:var(--muted); font-size:13px; white-space:nowrap; }
    .ads-w-md { width:100%; max-width:17.5rem; }
    .ads-w-sm { width:100%; max-width:16rem; }
    .ads-field > div, .ads-field > select { min-width:0; }
    .ads-seg { display:inline-flex; justify-self:start; background:var(--seg); border-radius:8px; padding:3px; gap:3px; flex-wrap:wrap; }
    .ads-seg label { position:relative; }
    .ads-seg input { position:absolute; opacity:0; pointer-events:none; }
    .ads-seg span { display:block; padding:6px 12px; border-radius:6px; color:var(--soft); font-size:13px; font-weight:bold; cursor:pointer; min-height:2rem; }
    .ads-seg input:checked + span { background:var(--accent-bg); color:var(--on-accent); }
    .ads-seg input:focus-visible + span { outline:2px solid var(--accent); }
    .ads-sw { position:relative; display:inline-flex; align-items:center; gap:8px; cursor:pointer; font-size:14px; min-height:2rem; }
    .ads-sw input { position:absolute; opacity:0; pointer-events:none; }
    .ads-sw .track { width:2.5rem; height:1.375rem; border-radius:11px; background:var(--track); position:relative; transition:background .15s; flex:none; }
    .ads-sw .track::after { content:""; position:absolute; top:3px; left:3px; width:1rem; height:1rem; border-radius:50%; background:var(--knob-off); transition:left .15s; }
    .ads-sw input:checked + .track { background:var(--ok); }
    .ads-sw input:checked + .track::after { left:calc(100% - 1rem - 3px); background:var(--knob); }
    .ads-sw input:focus-visible + .track { outline:2px solid var(--accent); }
    .ads-col { min-width:0; }
    .ads-pl-field { display:flex; flex-direction:column; gap:6px; margin-bottom:12px; }
    .ads-pl-head { color:var(--soft); font-size:14px; }
    .ads-pl { display:grid; grid-template-columns:repeat(auto-fill, minmax(min(100%, 12rem), 1fr)); gap:2px 12px; align-content:start;
           min-height:10rem; max-height:22rem; overflow:auto; padding:6px 10px; border:1px solid var(--input-line); border-radius:6px;
           background:var(--card2); transition:opacity .15s; }
    .ads-pl label { display:flex; align-items:center; gap:8px; font-size:14px; min-height:2rem; cursor:pointer; overflow-wrap:anywhere; }
    .ads-pl .form-check-input { margin:0; flex:none; }
    .ads-pl-empty { color:var(--muted); font-size:13px; }
    .ads-pl-help { font-size:12px; color:var(--muted); line-height:1.45; }
    .ads-pl-field.off .ads-pl { opacity:.4; pointer-events:none; }
    .ads-pl-field.off .ads-pl-head { color:var(--muted); }
    .ads-small { margin-left:10px; font-size:13px; color:var(--soft); }
    .ads-soft { font-size:14px; color:var(--soft); }
    .ads-caution { color:var(--caution-text); }
    .ads-good { color:var(--good-text); }
    .ads a.ads-link { color:var(--good-text); }
    .ads-heads { overflow-x:auto; }
    .ads-heads table { width:100%; border-collapse:collapse; min-width:48rem; }
    .ads-heads th { text-align:left; font-size:12px; color:var(--muted); font-weight:bold; padding:0 6px 6px 0; }
    .ads-heads td { padding:5px 6px 5px 0; border-top:1px solid var(--line); vertical-align:middle; }
    .ads-heads td input[type=text] { width:100%; min-width:7rem; }
    .ads-heads td input[type=number] { width:5.5rem; }
    .ads-heads td.closed input { width:4rem; }
    .ads-heads td select { width:100%; min-width:9rem; }
    .ads-heads tr.off td:not(.keep) { opacity:.45; }
    .ads-res { font-size:13px; color:var(--good-text); white-space:nowrap; }
    .ads-res.warn { color:var(--caution-text); white-space:normal; } .ads-res.err { color:var(--err); white-space:normal; }
    .ads-x { background:none; border:none; color:var(--muted); font-size:18px; cursor:pointer; padding:2px 6px; min-width:2rem; min-height:2rem; }
    .ads-x:hover { color:var(--err); }
    .ads-add { margin-top:10px; }
    .ads-note { font-size:12px; color:var(--muted); line-height:1.5; margin-top:10px; }
    .ads-test { display:flex; align-items:center; gap:10px; flex-wrap:wrap; margin-top:4px; }
    .ads-test-out { font-size:13px; } .ads-test-out.ok { color:var(--good-text); } .ads-test-out.err { color:var(--err); }
    .ads-remote-only, .ads.remote .ads-master-only { display:none; }
    .ads.remote .ads-remote-only { display:block; }
    .ads-bar { position:sticky; bottom:0; z-index:5; display:none; justify-content:space-between; align-items:center; gap:12px;
           flex-wrap:wrap; margin-top:12px; padding:10px 16px; border-radius:12px; background:var(--bar-bg); border:1px solid var(--warn); }
    .ads-bar.show { display:flex; }
    .ads-bar span { color:var(--bar-text); font-size:14px; }
    .ads-bar-actions { display:flex; gap:8px; }
    @media (max-width:37.5rem) {
        .ads-field { grid-template-columns:1fr; }
        .ads-help { grid-column:1; }
    }
</style>

<form method="POST" action="plugin.php?plugin=AirDefender&page=www/settings.php" class="ads<?php echo $isRemote ? ' remote' : ''; ?>" id="ads" novalidate>
    <div class="ads-top">
        <h2>Air Defender settings <span class="ads-ver">v<?php echo htmlspecialchars(airdefender_version()); ?></span></h2>
        <a class="ads-btn" href="plugin.php?plugin=AirDefender&page=www/index.php">Dashboard</a>
    </div>

    <?php if ($statusMessage !== ''): ?>
        <div class="ads-msg <?php echo $airdefenderSaved ? 'ok' : 'err'; ?>" id="adsMsg"><?php echo htmlspecialchars($statusMessage); ?></div>
    <?php endif; ?>

    <!-- 1. Air Defender -->
    <div class="ads-sec">
        <h3>Air Defender <span class="n">1 of 5</span></h3>
        <div class="ads-field">
            <label for="enabledSw">Enabled</label>
            <div>
                <input type="hidden" name="enabled" value="0">
                <label class="ads-sw"><input type="checkbox" id="enabledSw" name="enabled" value="1" <?php echo AirDefender_IsTruthy($v('enabled')) ? 'checked' : ''; ?>><span class="track"></span><span id="enabledText"></span></label>
            </div>
        </div>
        <div class="ads-field">
            <label>Role</label>
            <div class="ads-seg" role="radiogroup" aria-label="Role">
                <?php foreach ($settings['nodeRole']['options'] as $val => $lab): ?>
                    <label><input type="radio" name="nodeRole" value="<?php echo $val; ?>" <?php echo $v('nodeRole') === $val ? 'checked' : ''; ?>><span><?php echo $lab; ?></span></label>
                <?php endforeach; ?>
            </div>
            <div class="ads-help">
                <b>Master</b>: checks for aircraft. Use it on a single FPP, and on the one that runs the show (also covers heads it sends to a Falcon over DDP/E1.31).
                <b>Remote</b>: for an FPP in remote (multisync) mode; follows the master and closes its own heads, and closes them if it loses the master.
            </div>
        </div>
        <div class="ads-field ads-remote-only" style="display:none;" id="masterRow">
            <label for="masterAddress">Master FPP address</label>
            <input type="text" name="masterAddress" id="masterAddress" value="<?php echo htmlspecialchars((string) $v('masterAddress')); ?>" placeholder="Blank finds it automatically" class="ads-w-md">
            <div class="ads-help">IP or hostname of the master. Leave blank to find it from FPP's MultiSync list; fill it in if there is more than one player FPP.</div>
        </div>
        <div class="ads-field ads-remote-only" style="display:none;" id="masterPwRow">
            <label for="masterPassword">Master's UI password</label>
            <div>
                <input type="password" name="masterPassword" id="masterPassword" value="" autocomplete="new-password" class="ads-w-md"
                    placeholder="<?php echo $hasMasterPassword ? 'Saved: type to change' : 'Only if the master has one'; ?>">
                <?php if ($hasMasterPassword): ?>
                    <label class="ads-small"><input type="checkbox" name="masterPasswordClear" value="1"> Remove saved password</label>
                <?php endif; ?>
            </div>
            <div class="ads-help">Only needed if the master FPP has a UI password (Settings &rarr; UI on the master). The Remote logs in as admin with it to read the master's status, on your local network.</div>
        </div>
    </div>

    <!-- 2. Moving heads -->
    <div class="ads-sec">
        <h3>Moving heads <span class="n">2 of 5</span></h3>
        <div class="ads-heads">
            <table>
                <thead><tr>
                    <th>On</th><th>Name</th><th>Model</th>
                    <th title="The head's first FPP channel, from xLights">Start ch.</th>
                    <th title="The shutter's channel in the fixture's DMX chart">Shutter ch.</th>
                    <th title="The value that closes the shutter">Closed</th><th></th><th></th>
                </tr></thead>
                <tbody>
                <?php for ($n = 1; $n <= 8; $n++):
                    $model = (string) $v("head{$n}Model");
                    $missing = $model !== '' && is_array($airdefenderModels) && !isset($airdefenderModels[$model]);
                    $start = (int) $v("head{$n}Start");
                    $offset = max(1, (int) $v("head{$n}Offset"));
                    if ($model === '' && $start < 1 && (int) $v("head{$n}Channel") > 0) {   // saved before Start existed
                        $start = (int) $v("head{$n}Channel");
                        $offset = 1;
                    }
                    $channel = (string) $v("head{$n}Channel");
                    if ($model !== '' && !empty($airdefenderLastKnown[$n])) {
                        $channel = (string) (int) $airdefenderLastKnown[$n];
                    }
                ?>
                    <tr data-head="<?php echo $n; ?>" <?php echo $headsShown[$n] ? '' : 'hidden'; ?> <?php echo ($model === '' && $start < 1 && trim((string) $v("head{$n}Name")) === '' && !AirDefender_IsTruthy($v("head{$n}Enabled"))) ? 'data-blank="1"' : ''; ?>>
                        <td class="keep">
                            <input type="hidden" name="head<?php echo $n; ?>Enabled" value="0">
                            <label class="ads-sw" title="Protect this head"><input type="checkbox" name="head<?php echo $n; ?>Enabled" id="head<?php echo $n; ?>Enabled" value="1" <?php echo AirDefender_IsTruthy($v("head{$n}Enabled")) ? 'checked' : ''; ?>><span class="track"></span></label>
                        </td>
                        <td><input type="text" name="head<?php echo $n; ?>Name" id="head<?php echo $n; ?>Name" value="<?php echo htmlspecialchars((string) $v("head{$n}Name")); ?>" maxlength="40" placeholder="MH<?php echo $n; ?>"></td>
                        <td>
                            <select name="head<?php echo $n; ?>Model" id="head<?php echo $n; ?>Model" data-previous="<?php echo htmlspecialchars($model); ?>">
                                <option value="" <?php echo $model === '' ? 'selected' : ''; ?>>Manual channel</option>
                                <?php if ($missing): ?><option value="<?php echo htmlspecialchars($model); ?>" selected><?php echo htmlspecialchars($model); ?> (not found)</option><?php endif; ?>
                                <?php foreach ((array) $airdefenderModels as $mName => $mInfo): ?>
                                    <option value="<?php echo htmlspecialchars($mName); ?>" <?php echo $model === (string) $mName ? 'selected' : ''; ?>><?php echo htmlspecialchars($mName); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td><input type="number" name="head<?php echo $n; ?>Start" id="head<?php echo $n; ?>Start" value="<?php echo $start > 0 ? $start : ''; ?>" data-manual="<?php echo $start > 0 ? $start : ''; ?>" min="1" step="1" placeholder="82448"></td>
                        <td><input type="number" name="head<?php echo $n; ?>Offset" id="head<?php echo $n; ?>Offset" value="<?php echo $offset; ?>" min="1" step="1"></td>
                        <td class="closed"><input type="number" name="head<?php echo $n; ?>ClosedValue" id="head<?php echo $n; ?>ClosedValue" value="<?php echo (int) $v("head{$n}ClosedValue"); ?>" min="0" max="255" step="1"></td>
                        <td><span class="ads-res" id="head<?php echo $n; ?>Result"></span>
                            <input type="hidden" name="head<?php echo $n; ?>Channel" id="head<?php echo $n; ?>Channel" value="<?php echo htmlspecialchars($channel); ?>"></td>
                        <td class="keep"><button type="button" class="ads-x" data-remove="<?php echo $n; ?>" title="Remove this head" aria-label="Remove head <?php echo $n; ?>">&times;</button></td>
                    </tr>
                <?php endfor; ?>
                </tbody>
            </table>
        </div>
        <button type="button" class="ads-btn ads-add" id="addHead">+ Add head</button>
        <div class="ads-note">
            Pick the head's <b>model</b>, or choose <b>Manual channel</b> and enter its <b>start channel</b> from xLights. <b>Shutter ch.</b> is the shutter's
            channel in the fixture's DMX chart (for example 7), and <b>Closed</b> the value that closes it. Air Defender works out the FPP channel.
            <?php if ($airdefenderModels === null): ?>
                <br><span class="ads-caution">Could not read FPP's model list right now; Manual channel still works.</span>
            <?php elseif (!$airdefenderModels): ?>
                <br><span class="ads-caution">No models in FPP yet: upload from xLights with FPP Connect's <b>Models</b> column set to <b>All</b>, or add them on FPP's Pixel Overlay Models page.</span>
            <?php else: ?>
                Head not in the list? In FPP Connect, set the <b>Models</b> column to <b>All</b> (Local leaves out heads on other controllers) and upload again.
            <?php endif; ?>
        </div>
    </div>

    <div class="ads-sec ads-remote-only" style="display:none;">
        <h3>On a Remote</h3>
        <div class="ads-soft">When to check, location, safety limits and flight data are set on the <b>master</b>; this Remote follows it.</div>
    </div>

    <div class="ads-master-only">
        <!-- 3. When and where -->
        <div class="ads-sec">
            <h3>When and where <span class="n">3 of 5</span></h3>
            <div class="ads-fields">
            <div class="ads-col">
            <div class="ads-field">
                <label>When to check</label>
                <div class="ads-seg" role="radiogroup" aria-label="When to check">
                    <?php foreach (array('scheduled' => 'All scheduled shows', 'playing' => 'Only selected playlists') as $val => $lab): ?>
                        <label><input type="radio" name="checkWhen" value="<?php echo $val; ?>" <?php echo $v('checkWhen') === $val ? 'checked' : ''; ?>><span><?php echo $lab; ?></span></label>
                    <?php endforeach; ?>
                </div>
                <div class="ads-help" id="checkWhenHelp"></div>
            </div>
            <div class="ads-field">
                <label for="useFppSw">Use FPP's location</label>
                <div>
                    <input type="hidden" name="useFPPLocation" value="0">
                    <label class="ads-sw"><input type="checkbox" id="useFppSw" name="useFPPLocation" value="1" <?php echo $useFppLocationEnabled ? 'checked' : ''; ?>><span class="track"></span></label>
                </div>
            </div>
            <div class="ads-field">
                <label for="latitude">Latitude, longitude</label>
                <div class="ads-unit">
                    <input type="text" name="latitude" id="latitude" value="<?php echo htmlspecialchars((string) $v('latitude')); ?>" data-fpp="<?php echo $fppLat !== null ? $fppLat : ''; ?>" class="wide" inputmode="decimal" aria-label="Latitude">
                    <input type="text" name="longitude" id="longitude" value="<?php echo htmlspecialchars((string) $v('longitude')); ?>" data-fpp="<?php echo $fppLon !== null ? $fppLon : ''; ?>" class="wide" inputmode="decimal" aria-label="Longitude">
                </div>
                <div class="ads-help" id="locHelp"></div>
            </div>
            <div class="ads-field">
                <label for="showElevationFt">Show elevation</label>
                <div class="ads-unit">
                    <input type="number" name="showElevationFt" id="showElevationFt" value="<?php echo htmlspecialchars((string) $v('showElevationFt')); ?>" min="<?php echo AIRDEFENDER_ELEVATION_MIN_FT; ?>" max="<?php echo AIRDEFENDER_ELEVATION_MAX_FT; ?>" step="1" ><span>ft above sea level</span>
                    <button type="button" class="ads-btn" id="elevLookup">Look it up</button>
                </div>
                <div class="ads-help" id="elevHelp"></div>
            </div>
            </div>
            <div class="ads-col ads-pl-field" id="playlistField">
                <div class="ads-pl-head">Playlists to check during</div>
                <input type="hidden" name="checkPlaylists[]" value="">
                <?php
                $savedPlaylists = airdefender_decode_playlists((string) $v('checkPlaylists'));
                $allPlaylists = airdefender_playlist_names();
                // Keep ticked ones that FPP no longer has, so they are not dropped unseen.
                $listed = array_merge($allPlaylists, array_values(array_diff($savedPlaylists, $allPlaylists)));
                ?>
                <?php if ($listed === array()): ?>
                    <div class="ads-pl ads-pl-empty">No playlists in FPP yet.</div>
                <?php else: ?>
                    <div class="ads-pl" role="group" aria-label="Playlists Air Defender checks during">
                        <?php foreach ($listed as $pl): ?>
                            <label><input class="form-check-input" type="checkbox" name="checkPlaylists[]" value="<?php echo htmlspecialchars($pl); ?>" <?php echo in_array($pl, $savedPlaylists, true) ? 'checked' : ''; ?>>
                                <?php echo htmlspecialchars($pl); ?><?php if (!in_array($pl, $allPlaylists, true)): ?> <span class="ads-caution">(not in FPP)</span><?php endif; ?></label>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <div class="ads-pl-help" id="playlistHelp"></div>
            </div>
            </div>
        </div>

        <!-- 4. Safety limits -->
        <div class="ads-sec">
            <h3>Safety limits <span class="n">4 of 5</span></h3>
            <div class="ads-fields">
            <div class="ads-field">
                <label for="distanceNM">Distance</label>
                <div class="ads-unit"><input type="number" name="distanceNM" id="distanceNM" value="<?php echo htmlspecialchars((string) $v('distanceNM')); ?>" min="1" step="1"><span>NM</span> <span id="kmHint"></span></div>
            </div>
            <div class="ads-field">
                <label for="altitudeThresholdFt">Altitude</label>
                <div class="ads-unit"><input type="number" name="altitudeThresholdFt" id="altitudeThresholdFt" value="<?php echo htmlspecialchars((string) $v('altitudeThresholdFt')); ?>" min="0" step="100"><span>ft</span> <span id="mHint"></span></div>
                <div class="ads-help" id="altHelp">Heads close when an aircraft is inside the distance <i>and</i> below the altitude.</div>
            </div>
            <div class="ads-field">
                <label for="clearDelaySec">Hold after it leaves</label>
                <div class="ads-unit"><input type="number" name="clearDelaySec" id="clearDelaySec" value="<?php echo htmlspecialchars((string) $v('clearDelaySec')); ?>" min="0" max="600" step="5"><span>s</span></div>
                <div class="ads-help">Keeps heads closed a little longer, so one on the edge of the area doesn't flash them on and off.</div>
            </div>
            <div class="ads-field">
                <label for="fetchInterval">Check every</label>
                <div class="ads-unit"><input type="number" name="fetchInterval" id="fetchInterval" value="<?php echo htmlspecialchars((string) $v('fetchInterval')); ?>" min="5" step="1"><span>s</span></div>
                <div class="ads-help" id="rateHint"></div>
            </div>
            </div>
        </div>

        <!-- 5. Flight data -->
        <div class="ads-sec">
            <h3>Flight data <span class="n">5 of 5</span></h3>
            <div class="ads-fields">
            <div class="ads-field">
                <label for="adsbSourceUrl">Service</label>
                <select name="adsbSourceUrl" id="adsbSourceUrl" class="ads-w-sm">
                    <?php foreach ($feedOptionsList as $val => $lab): ?>
                        <option value="<?php echo htmlspecialchars($val); ?>" <?php echo $v('adsbSourceUrl') === $val ? 'selected' : ''; ?>><?php echo htmlspecialchars($lab); ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="ads-help">
                    <?php if (!empty($airdefenderFeedNote)): ?><span class="ads-caution"><?php echo htmlspecialchars($airdefenderFeedNote); ?></span><br><?php endif; ?>
                    Free community services for personal, non-commercial use: data from
                    <a href="https://adsb.fi" target="_blank" rel="noopener" class="ads-link">adsb.fi</a> and
                    <a href="https://adsb.lol" target="_blank" rel="noopener" class="ads-link">adsb.lol</a> (ODbL). Your own receiver sends nothing to the internet.
                </div>
            </div>
            <div class="ads-field" id="receiverField">
                <label for="receiverAddress">Receiver address</label>
                <input type="text" name="receiverAddress" id="receiverAddress" class="ads-w-md" value="<?php echo htmlspecialchars((string) $v('receiverAddress')); ?>"
                    placeholder="e.g. 192.168.1.50" autocomplete="off" spellcheck="false">
                <div class="ads-help">The IP address or name of the Pi running your receiver (ADSB.im, PiAware, or readsb/dump1090 with
                    tar1090), on your home network. Add a port if it uses one (192.168.1.50:8080). Test connection finds where it
                    publishes its aircraft list.</div>
            </div>
            <div class="ads-field">
                <label>Test</label>
                <div class="ads-test">
                    <button type="button" class="ads-btn" id="feedTest">Test connection</button>
                    <span class="ads-test-out" id="feedTestOut"></span>
                </div>
                <div class="ads-help">Asks the service once now, with the location and limits on this page.</div>
            </div>
            <div class="ads-field" id="fallbackField">
                <label for="feedFallbackSw">Use the other as a backup</label>
                <div>
                    <input type="hidden" name="feedFallback" value="0">
                    <label class="ads-sw"><input type="checkbox" id="feedFallbackSw" name="feedFallback" value="1" <?php echo AirDefender_IsTruthy($v('feedFallback')) ? 'checked' : ''; ?>><span class="track"></span></label>
                </div>
                <div class="ads-help">If adsb.fi or adsb.lol stops answering or limits requests, ask the other one in the same check; try the chosen one again every minute and go back to it when it answers. Off by default, because turning it on means the other service is sent your show's location too. Never used with your own receiver.</div>
            </div>
            <div class="ads-field">
                <label for="feedDownSw">Close heads if feed is down</label>
                <div>
                    <input type="hidden" name="feedDownSafe" value="0">
                    <label class="ads-sw"><input type="checkbox" id="feedDownSw" name="feedDownSafe" value="1" <?php echo AirDefender_IsTruthy($v('feedDownSafe')) ? 'checked' : ''; ?>><span class="track"></span></label>
                </div>
                <div class="ads-help">Off: if the service stops answering, heads stay as they are unless an aircraft was near. On: they close until it answers. Stricter, but an internet outage keeps them closed.</div>
            </div>
            </div>
        </div>
    </div>

    <div class="ads-bar" id="saveBar">
        <span>Unsaved changes</span>
        <span class="ads-bar-actions">
            <button type="button" class="ads-btn" id="discard">Discard</button>
            <button type="submit" class="ads-btn primary">Save settings</button>
        </span>
    </div>
</form>

<script>
(function () {
    var form = document.getElementById('ads');
    var $ = function (id) { return document.getElementById(id); };
    var models = <?php echo json_encode(is_array($airdefenderModels) ? $airdefenderModels : new stdClass()); ?>;
    var modelsKnown = <?php echo $airdefenderModels === null ? 'false' : 'true'; ?>;
    var MAX_CH = 8388608;
    var API = 'plugin.php?plugin=AirDefender&page=www/test_api.php&nopage=1';

    function role() { var r = form.querySelector('input[name=nodeRole]:checked'); return r ? r.value : 'master'; }

    /* ---------- section 1 ---------- */
    function updateTop() {
        var remote = role() === 'remote';
        form.classList.toggle('remote', remote);
        form.querySelectorAll('.ads-remote-only').forEach(function (el) { el.style.display = remote ? '' : 'none'; });
        $('masterRow').style.display = remote ? 'grid' : 'none';
        $('masterPwRow').style.display = remote ? 'grid' : 'none';
        $('enabledText').textContent = $('enabledSw').checked ? 'On' : 'Off';
    }

    /* ---------- heads ---------- */
    function headRow(n) {
        var sel = $('head' + n + 'Model'), start = $('head' + n + 'Start'), off = $('head' + n + 'Offset');
        var chan = $('head' + n + 'Channel'), res = $('head' + n + 'Result'), on = $('head' + n + 'Enabled').checked;
        var name = sel.value, manual = name === '', known = modelsKnown && Object.prototype.hasOwnProperty.call(models, name);
        if (manual) {
            if (start.disabled) { start.value = start.getAttribute('data-manual') || ''; }
            start.disabled = false;
        } else {
            if (!start.disabled) { start.setAttribute('data-manual', start.value); }
            start.value = known ? models[name].start : '';
            start.disabled = true;
        }
        var o = parseInt(off.value, 10), text = '', cls = '';
        if (manual) {
            var st = parseInt(start.value, 10);
            if (!(st >= 1)) { text = 'Enter the start channel'; cls = 'warn'; }
            else if (!(o >= 1)) { text = 'Enter the shutter channel'; cls = 'warn'; }
            else if (st + o - 1 > MAX_CH) { text = "Past FPP's last channel"; cls = 'err'; }
            else { text = '→ FPP ch ' + (st + o - 1); }
        } else if (!modelsKnown) { text = 'Model list unavailable'; cls = 'warn'; }
        else if (!known) {
            var last = parseInt(chan.value, 10);
            text = 'Model not found: pick it again' + (last > 0 ? ' (still protecting ch ' + last + ')' : ''); cls = 'err';
        } else if (!(o >= 1)) { text = 'Enter the shutter channel'; cls = 'warn'; }
        else if (o > models[name].count) { text = 'Past the end of this model (' + models[name].count + ' ch)'; cls = 'err'; }
        else { text = '→ FPP ch ' + (models[name].start + o - 1); }
        res.textContent = on ? text : 'Off';
        res.className = 'ads-res' + (on && cls ? ' ' + cls : '');
        sel.closest('tr').classList.toggle('off', !on);
    }
    function allHeads() { for (var n = 1; n <= 8; n++) { headRow(n); } updateAddButton(); }
    function updateAddButton() {
        $('addHead').style.display = form.querySelectorAll('tr[data-head][hidden]').length ? '' : 'none';
    }
    $('addHead').addEventListener('click', function () {
        var row = form.querySelector('tr[data-head][hidden]');
        if (!row) { return; }
        var n = row.getAttribute('data-head');
        row.hidden = false;
        $('head' + n + 'Enabled').checked = true;
        $('head' + n + 'Name').focus();
        allHeads(); dirty();
    });
    form.querySelectorAll('[data-remove]').forEach(function (b) {
        b.addEventListener('click', function () {
            var n = b.getAttribute('data-remove');
            $('head' + n + 'Enabled').checked = false;
            $('head' + n + 'Name').value = '';
            $('head' + n + 'Model').value = '';
            $('head' + n + 'Model').setAttribute('data-previous', '');
            $('head' + n + 'Start').disabled = false;
            $('head' + n + 'Start').value = '';
            $('head' + n + 'Start').setAttribute('data-manual', '');
            $('head' + n + 'Offset').value = '1';
            $('head' + n + 'ClosedValue').value = '0';
            $('head' + n + 'Channel').value = '0';
            b.closest('tr').hidden = true;
            if (!form.querySelector('tr[data-head]:not([hidden])')) {   // keep one row to start from
                b.closest('tr').hidden = false;
            }
            allHeads(); dirty();
        });
    });
    // An empty row switches itself on when you start filling it in, so a head
    // entered on a fresh setup is not saved as Off by mistake.
    function wakeRow(n) {
        var row = $('head' + n + 'Model').closest('tr');
        if (row.getAttribute('data-blank') === '1') {
            row.removeAttribute('data-blank');
            $('head' + n + 'Enabled').checked = true;
        }
    }
    for (var h = 1; h <= 8; h++) {
        (function (n) {
            ['Name', 'Start', 'Offset'].forEach(function (f) {
                $('head' + n + f).addEventListener('input', function () { wakeRow(n); });
            });
            $('head' + n + 'Enabled').addEventListener('change', function () {
                this.closest('tr').removeAttribute('data-blank');   // a deliberate choice stands
            });
            $('head' + n + 'Model').addEventListener('change', function () { wakeRow(n); });
            $('head' + n + 'Model').addEventListener('change', function () {
                var sel = this, nameIn = $('head' + n + 'Name'), prev = sel.getAttribute('data-previous') || '';
                if (sel.value !== '' && (nameIn.value.trim() === '' || nameIn.value.trim() === prev)) { nameIn.value = sel.value; }
                sel.setAttribute('data-previous', sel.value);
            });
        })(h);
    }

    /* ---------- section 3 ---------- */
    function updateWhen() {
        var w = form.querySelector('input[name=checkWhen]:checked');
        w = w ? w.value : 'scheduled';
        $('checkWhenHelp').textContent = w === 'playing'
            ? 'Checks only while one of the selected playlists plays, scheduled or started by hand, and from 2 minutes before a scheduled one.'
            : 'Checks during every scheduled show, starting 2 minutes before each one. To test a sequence at another time, use Check now on the dashboard.';
        var field = $('playlistField'), list = field.querySelector('.ads-pl');
        field.classList.toggle('off', w !== 'playing');
        if (list) { list.setAttribute('aria-disabled', w !== 'playing' ? 'true' : 'false'); }
        var ticked = form.querySelectorAll('input[name="checkPlaylists[]"][type=checkbox]:checked').length;
        var help = $('playlistHelp');
        help.className = 'ads-pl-help';
        if (w !== 'playing') {
            help.textContent = 'Used with Only selected playlists. Your selections are kept while this is greyed out.';
        } else if (!ticked) {
            help.className = 'ads-pl-help ads-caution';
            help.textContent = 'Nothing ticked yet, so it checks while any playlist plays. Tick the ones with your moving heads.';
        } else {
            help.textContent = "Using Remote Falcon? Tick its playlist too, so viewer requests are covered. A sequence started on its own isn't in a playlist: use Check now.";
        }
        updateRate();
    }
    function updateLocation() {
        var useFpp = $('useFppSw').checked;
        ['latitude', 'longitude'].forEach(function (id) {
            var el = $(id);
            if (useFpp) {
                if (!el.disabled) { el.setAttribute('data-own', el.value); }
                el.value = el.getAttribute('data-fpp') || '';
            } else if (el.disabled) {
                el.value = el.getAttribute('data-own') || el.value;
            }
            el.disabled = useFpp;
        });
        var lat = parseFloat($('latitude').value), lon = parseFloat($('longitude').value);
        var help = $('locHelp');
        help.textContent = '';
        if (isNaN(lat) || isNaN(lon)) {
            help.textContent = useFpp ? "FPP has no location set: set it in FPP's settings, or turn this off and enter one." : 'Enter the show’s latitude and longitude.';
            help.className = 'ads-help ads-caution';
        } else {
            help.className = 'ads-help';
            help.appendChild(document.createTextNode(useFpp
                ? "From FPP's settings, which can be miles off. Make sure the pin is on your show: " : ''));
            var a = document.createElement('a');
            a.href = 'https://www.openstreetmap.org/?mlat=' + lat + '&mlon=' + lon + '#map=15/' + lat + '/' + lon;
            a.target = '_blank'; a.rel = 'noopener'; a.className = 'ads-link'; a.textContent = 'Check it on a map';
            help.appendChild(a);
        }
    }

    function elevation() {
        var e = parseInt($('showElevationFt').value, 10);
        return isNaN(e) ? 0 : e;
    }
    function updateElevation() {
        var e = elevation(), ft = parseFloat($('altitudeThresholdFt').value);
        var help = $('elevHelp');
        if (elevMsg) {
            help.textContent = elevMsg.text; help.className = 'ads-help' + (elevMsg.cls ? ' ' + elevMsg.cls : '');
        } else {
            help.className = 'ads-help';
            help.textContent = e === 0
                ? 'Aircraft report their height above sea level. Enter your ground’s elevation, or press Look it up, so the altitude limit counts from your show.'
                : 'The altitude limit counts from here.';
        }
        $('altHelp').innerHTML = '';
        $('altHelp').appendChild(document.createTextNode(e !== 0 && ft >= 0
            ? 'Heads close when an aircraft is inside the distance and less than ' + Math.round(ft).toLocaleString()
              + ' ft above your show (below ' + Math.round(ft + e).toLocaleString() + ' ft above sea level).'
            : 'Heads close when an aircraft is inside the distance and below the altitude.'));
    }
    var elevMsg = null;
    ['showElevationFt', 'latitude', 'longitude', 'useFppSw'].forEach(function (id) {
        $(id).addEventListener(id === 'useFppSw' ? 'change' : 'input', function () { elevMsg = null; });
    });
    $('elevLookup').addEventListener('click', function () {
        var btn = this;
        btn.disabled = true;
        elevMsg = { text: 'Looking it up…', cls: '' };
        updateElevation();
        var body = new URLSearchParams({ action: 'elevation', lat: $('latitude').value, lon: $('longitude').value });
        fetch(API, { method: 'POST', credentials: 'same-origin', cache: 'no-store',
                     headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: body.toString() })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (d.ok) {
                    $('showElevationFt').value = d.feet;
                    elevMsg = { text: 'Found ' + d.feet.toLocaleString() + ' ft at ' + $('latitude').value + ', ' + $('longitude').value
                        + '. Save to use it. Elevation data: Open-Meteo, Copernicus DEM.', cls: 'ads-good' };
                } else {
                    elevMsg = { text: d.error || 'No answer', cls: 'ads-caution' };
                }
            })
            .catch(function (e) { elevMsg = { text: 'Could not look it up: ' + e.message, cls: 'ads-caution' }; })
            .then(function () { btn.disabled = false; refreshAll(); dirty(); });
    });

    /* ---------- section 4 ---------- */
    function updateRate() {
        var nm = parseFloat($('distanceNM').value), ft = parseFloat($('altitudeThresholdFt').value);
        $('kmHint').textContent = nm > 0 ? '≈ ' + (nm * 1.852).toFixed(1) + ' km' : '';
        $('mHint').textContent = ft >= 0 ? '≈ ' + Math.round(ft * 0.3048).toLocaleString() + ' m' : '';
        var sec = Math.max(5, parseInt($('fetchInterval').value, 10) || 10);
        var perHour = Math.round(3600 / sec);
        $('rateHint').textContent = '≈ ' + (perHour * 5).toLocaleString() + ' requests in a 5-hour show night. A smaller distance may want a quicker check.';
    }

    /* ---------- section 5 ---------- */
    $('feedTest').addEventListener('click', function () {
        var btn = this, out = $('feedTestOut');
        btn.disabled = true; out.className = 'ads-test-out'; out.textContent = 'Asking…';
        var body = new URLSearchParams({
            action: 'feed_test', url: $('adsbSourceUrl').value, receiver: $('receiverAddress').value,
            lat: $('latitude').value, lon: $('longitude').value,
            nm: $('distanceNM').value, ft: $('altitudeThresholdFt').value, elev: elevation()
        });
        fetch(API, { method: 'POST', credentials: 'same-origin', cache: 'no-store',
                     headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: body.toString() })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (d.ok) {
                    out.className = 'ads-test-out ok';
                    out.textContent = 'OK · ' + d.aircraft + ' aircraft nearby'
                        + (d.nearest ? ' · nearest ' + d.nearest.id + ' at ' + d.nearest.nm + ' NM' + (d.nearest.ft !== null ? ', ' + d.nearest.ft.toLocaleString() + ' ft' + (elevation() ? ' above the show' : '') : '') : '')
                        + (d.inside ? ' · ' + d.inside + ' inside your limits' : '') + ' (' + d.ms + ' ms)'
                        + (d.foundAt ? ' · reading ' + d.foundAt : '');
                } else {
                    out.className = 'ads-test-out err';
                    out.textContent = d.error || 'No answer';
                }
            })
            .catch(function (e) { out.className = 'ads-test-out err'; out.textContent = 'Could not run the test: ' + e.message; })
            .then(function () { btn.disabled = false; });
    });

    /* Your receiver's address only matters for that choice; a receiver has no online backup. */
    function updateFeed() {
        var f = $('adsbSourceUrl').value;
        $('receiverField').style.display = f === 'receiver' ? '' : 'none';
        $('fallbackField').style.display = (f === 'receiver' || f.indexOf('http://localhost') === 0) ? 'none' : '';
    }

    /* ---------- unsaved changes ---------- */
    function snapshot() {
        // Disabled inputs are left out of a submit, so compare what would actually be saved.
        var fd = new FormData(form), parts = [];
        fd.forEach(function (val, key) { parts.push(key + '=' + val); });
        return parts.join('&');
    }
    var initial;
    var submitting = false;
    function dirty() {
        $('saveBar').classList.toggle('show', snapshot() !== initial);
    }
    form.addEventListener('input', function () { refreshAll(); dirty(); });
    form.addEventListener('change', function () { refreshAll(); dirty(); });
    form.addEventListener('submit', function () { submitting = true; });
    $('discard').addEventListener('click', function () { window.location.href = window.location.href.split('#')[0]; });
    window.addEventListener('beforeunload', function (e) {
        if (!submitting && snapshot() !== initial) { e.preventDefault(); e.returnValue = ''; }
    });

    function refreshAll() { updateTop(); allHeads(); updateWhen(); updateLocation(); updateElevation(); updateRate(); updateFeed(); }
    refreshAll();
    initial = snapshot();
    var msg = $('adsMsg');
    if (msg && msg.classList.contains('ok')) { setTimeout(function () { msg.style.display = 'none'; }, 4000); }
})();
</script>
