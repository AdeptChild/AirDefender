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

require_once(dirname(__DIR__) . "/scripts/airdefender_models.php");
require_once(dirname(__DIR__) . "/scripts/airdefender_feeds.php");
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
    $paths = array('/home/fpp/media/settings', '/media/settings');
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
    // shown back on the page; a blank field keeps the saved one.
    'masterPassword' => array(
        'label' => "Master's UI Password",
        'type' => 'string',
        'default' => '',
    ),
    'enabled' => array(
        'label' => 'Enable Air Defender',
        'type' => 'bool',
        'default' => true,
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
        'options' => array(
            'scheduled' => 'During scheduled shows (default)',
            'playing' => 'Whenever FPP is playing',
            'always' => 'Always',
        ),
    ),
    'clearDelaySec' => array(
        'label' => 'Hold After Aircraft Leaves (seconds)',
        'type' => 'int',
        'default' => 30,
    ),
    'feedFallback' => array(
        'label' => 'Use the Other Service as a Backup',
        'type' => 'bool',
        'default' => true,
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
    'Location' => array('useFPPLocation', 'latitude', 'longitude'),
    'Safety Thresholds' => array('checkWhen', 'distanceNM', 'altitudeThresholdFt', 'fetchInterval', 'clearDelaySec'),
    'ADS-B Feed' => array('adsbSourceUrl', 'feedDownSafe'),
);

$statusMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $toSave = array();

    foreach ($settings as $key => $config) {
        if (!isset($_POST[$key])) {
            continue;
        }
        if ($key === 'masterPassword') {
            // Blank keeps the saved password; the Remove box clears it.
            if (!empty($_POST['masterPasswordClear'])) {
                $toSave[$key] = '';
            } elseif ((string) $_POST[$key] !== '') {
                // Stored encoded: the settings file cannot hold every character a password may use.
                $toSave[$key] = 'b64:' . base64_encode((string) $_POST[$key]);
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
        $statusMessage = "Settings saved.";
    } else {
        $statusMessage = "ERROR: could not write " . AirDefender_ConfigPath($pluginName) . " (check file permissions).";
    }
}

foreach ($settings as $key => $config) {
    $value = AirDefender_StoredValue($pluginName, $key, $config['default']);
    $settings[$key]['value'] = $value;
}

// A retired feed (airplanes.live) shows as its replacement, which is what runs.
list($settings['adsbSourceUrl']['value'], $airdefenderFeedNote) = airdefender_effective_feed((string) $settings['adsbSourceUrl']['value']);

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
    .ads { --card:#262626; --card2:#1d1d1d; --line:#3a3a3a; --text:#ececec; --muted:#9a9a9a; --accent:#6cf;
           --warn:#ff9800; --ok:#4caf50; --bad:#f44336; color:var(--text); max-width:1100px; padding-bottom:80px; }
    .ads * { box-sizing:border-box; }
    .ads-top { display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap; margin-bottom:12px; }
    .ads-top h2 { color:var(--accent); margin:0; }
    .ads-btn { border:1px solid var(--line); background:var(--card); color:var(--text); border-radius:6px; padding:7px 12px;
           font-size:14px; cursor:pointer; text-decoration:none; display:inline-block; line-height:1.2; }
    .ads-btn.primary { background:var(--accent); color:#111; border-color:var(--accent); font-weight:bold; }
    .ads-msg { padding:10px 14px; border-radius:8px; margin-bottom:12px; font-size:14px; }
    .ads-msg.ok { background:#1f3a24; color:#cfeed6; } .ads-msg.err { background:#4a1f1f; color:#ffd7d3; }
    .ads-sec { background:var(--card); border:1px solid var(--line); border-radius:12px; padding:14px 18px; margin-bottom:12px; }
    .ads-sec h3 { margin:0 0 12px 0; font-size:16px; color:var(--accent); display:flex; align-items:baseline; gap:10px; }
    .ads-sec h3 .n { color:var(--muted); font-size:13px; font-weight:normal; }
    .ads-fields { display:grid; grid-template-columns:repeat(auto-fit, minmax(440px, 1fr)); gap:0 28px; align-items:start; }
    .ads-fields .ads-field:last-child { margin-bottom:12px; }
    .ads-field { display:grid; grid-template-columns:150px minmax(0,1fr); gap:6px 12px; align-items:center; margin-bottom:12px; }
    .ads-field:last-child { margin-bottom:0; }
    .ads-field > label { color:#cfcfcf; font-size:14px; }
    .ads-help { grid-column:2; font-size:12px; color:var(--muted); line-height:1.45; }
    .ads input[type=text], .ads input[type=number], .ads select { padding:7px 9px; border-radius:6px; border:1px solid #444;
           background:#111; color:#fff; font-size:14px; height:auto; line-height:1.3; }
    .ads select { padding-right:28px; }
    .ads input:disabled { background:#1a1a1a; color:#888; cursor:not-allowed; }
    .ads-unit { display:inline-flex; align-items:center; gap:6px; }
    .ads-unit input { width:95px; }
    .ads-unit span { color:var(--muted); font-size:13px; white-space:nowrap; }
    .ads-field > div, .ads-field > select { min-width:0; }
    .ads-seg { display:inline-flex; justify-self:start; background:#111; border-radius:8px; padding:3px; gap:3px; flex-wrap:wrap; }
    .ads-seg label { position:relative; }
    .ads-seg input { position:absolute; opacity:0; pointer-events:none; }
    .ads-seg span { display:block; padding:6px 12px; border-radius:6px; color:#bbb; font-size:13px; font-weight:bold; cursor:pointer; }
    .ads-seg input:checked + span { background:var(--accent); color:#111; }
    .ads-seg input:focus-visible + span { outline:2px solid var(--accent); }
    .ads-sw { position:relative; display:inline-flex; align-items:center; gap:8px; cursor:pointer; font-size:14px; }
    .ads-sw input { position:absolute; opacity:0; pointer-events:none; }
    .ads-sw .track { width:40px; height:22px; border-radius:11px; background:#444; position:relative; transition:background .15s; flex:none; }
    .ads-sw .track::after { content:""; position:absolute; top:3px; left:3px; width:16px; height:16px; border-radius:50%; background:#ddd; transition:left .15s; }
    .ads-sw input:checked + .track { background:var(--ok); }
    .ads-sw input:checked + .track::after { left:21px; background:#fff; }
    .ads-sw input:focus-visible + .track { outline:2px solid var(--accent); }
    .ads-heads { overflow-x:auto; }
    .ads-heads table { width:100%; border-collapse:collapse; min-width:780px; }
    .ads-heads th { text-align:left; font-size:12px; color:var(--muted); font-weight:bold; padding:0 6px 6px 0; }
    .ads-heads td { padding:5px 6px 5px 0; border-top:1px solid var(--line); vertical-align:middle; }
    .ads-heads td input[type=text] { width:100%; min-width:110px; }
    .ads-heads td input[type=number] { width:86px; }
    .ads-heads td.closed input { width:62px; }
    .ads-heads td select { width:100%; min-width:140px; }
    .ads-heads tr.off td:not(.keep) { opacity:.45; }
    .ads-res { font-size:13px; color:#8fd18f; white-space:nowrap; }
    .ads-res.warn { color:#ffb74d; white-space:normal; } .ads-res.err { color:#ff7b72; white-space:normal; }
    .ads-x { background:none; border:none; color:var(--muted); font-size:18px; cursor:pointer; padding:2px 6px; }
    .ads-x:hover { color:#ff7b72; }
    .ads-add { margin-top:10px; }
    .ads-note { font-size:12px; color:var(--muted); line-height:1.5; margin-top:10px; }
    .ads-test { display:flex; align-items:center; gap:10px; flex-wrap:wrap; margin-top:4px; }
    .ads-test-out { font-size:13px; } .ads-test-out.ok { color:#8fd18f; } .ads-test-out.err { color:#ff7b72; }
    .ads-remote-only, .ads.remote .ads-master-only { display:none; }
    .ads.remote .ads-remote-only { display:block; }
    .ads-bar { position:sticky; bottom:0; z-index:5; display:none; justify-content:space-between; align-items:center; gap:12px;
           flex-wrap:wrap; margin-top:12px; padding:10px 16px; border-radius:12px; background:#3a2a10; border:1px solid var(--warn); }
    .ads-bar.show { display:flex; }
    .ads-bar span { color:#ffcc80; font-size:14px; }
    @media (max-width:520px) { .ads-fields { grid-template-columns:1fr; } }
    @media (max-width:600px) {
        .ads-field { grid-template-columns:1fr; }
        .ads-help { grid-column:1; }
    }
</style>

<form method="POST" action="plugin.php?plugin=AirDefender&page=www/settings.php" class="ads<?php echo $isRemote ? ' remote' : ''; ?>" id="ads" novalidate>
    <div class="ads-top">
        <h2>Air Defender settings <span style="font-size:13px;color:#888;font-weight:normal;">v<?php echo htmlspecialchars(airdefender_version()); ?></span></h2>
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
            <input type="text" name="masterAddress" id="masterAddress" value="<?php echo htmlspecialchars((string) $v('masterAddress')); ?>" placeholder="Blank finds it automatically" style="max-width:280px;">
            <div class="ads-help">IP or hostname of the master. Leave blank to find it from FPP's MultiSync list; fill it in if there is more than one player FPP.</div>
        </div>
        <div class="ads-field ads-remote-only" style="display:none;" id="masterPwRow">
            <label for="masterPassword">Master's UI password</label>
            <div>
                <input type="password" name="masterPassword" id="masterPassword" value="" autocomplete="new-password" style="max-width:280px;"
                    placeholder="<?php echo (string) $v('masterPassword') !== '' ? 'Saved: type to change' : 'Only if the master has one'; ?>">
                <?php if ((string) $v('masterPassword') !== ''): ?>
                    <label style="margin-left:10px;font-size:13px;color:#cfcfcf;"><input type="checkbox" name="masterPasswordClear" value="1"> Remove saved password</label>
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
                    <tr data-head="<?php echo $n; ?>" <?php echo $headsShown[$n] ? '' : 'hidden'; ?>>
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
                <br><span style="color:#ffb74d;">Could not read FPP's model list right now; Manual channel still works.</span>
            <?php elseif (!$airdefenderModels): ?>
                <br><span style="color:#ffb74d;">No models in FPP yet: upload from xLights with FPP Connect's <b>Models</b> column set to <b>All</b>, or add them on FPP's Pixel Overlay Models page.</span>
            <?php else: ?>
                Head not in the list? In FPP Connect, set the <b>Models</b> column to <b>All</b> (Local leaves out heads on other controllers) and upload again.
            <?php endif; ?>
        </div>
    </div>

    <div class="ads-sec ads-remote-only" style="display:none;">
        <h3>On a Remote</h3>
        <div style="font-size:14px;color:#cfcfcf;">When to check, location, safety limits and flight data are set on the <b>master</b>; this Remote follows it.</div>
    </div>

    <div class="ads-master-only">
        <!-- 3. When and where -->
        <div class="ads-sec">
            <h3>When and where <span class="n">3 of 5</span></h3>
            <div class="ads-fields">
            <div class="ads-field">
                <label>When to check</label>
                <div class="ads-seg" role="radiogroup" aria-label="When to check">
                    <?php foreach (array('scheduled' => 'Scheduled shows', 'playing' => 'When playing', 'always' => 'Always') as $val => $lab): ?>
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
                    <input type="text" name="latitude" id="latitude" value="<?php echo htmlspecialchars((string) $v('latitude')); ?>" data-fpp="<?php echo $fppLat !== null ? $fppLat : ''; ?>" style="width:120px;" inputmode="decimal" aria-label="Latitude">
                    <input type="text" name="longitude" id="longitude" value="<?php echo htmlspecialchars((string) $v('longitude')); ?>" data-fpp="<?php echo $fppLon !== null ? $fppLon : ''; ?>" style="width:120px;" inputmode="decimal" aria-label="Longitude">
                </div>
                <div class="ads-help" id="locHelp"></div>
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
                <div class="ads-help">Heads close when an aircraft is inside the distance <i>and</i> below the altitude.</div>
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
                <select name="adsbSourceUrl" id="adsbSourceUrl" style="max-width:260px;">
                    <?php foreach ($feedOptionsList as $val => $lab): ?>
                        <option value="<?php echo htmlspecialchars($val); ?>" <?php echo $v('adsbSourceUrl') === $val ? 'selected' : ''; ?>><?php echo htmlspecialchars($lab); ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="ads-help">
                    <?php if (!empty($airdefenderFeedNote)): ?><span style="color:#ffb74d;"><?php echo htmlspecialchars($airdefenderFeedNote); ?></span><br><?php endif; ?>
                    Free community services for personal, non-commercial use: data from
                    <a href="https://adsb.fi" target="_blank" rel="noopener" style="color:#6cf;">adsb.fi</a> and
                    <a href="https://adsb.lol" target="_blank" rel="noopener" style="color:#6cf;">adsb.lol</a> (ODbL). A local receiver sends nothing off this FPP.
                </div>
            </div>
            <div class="ads-field">
                <label>Test</label>
                <div class="ads-test">
                    <button type="button" class="ads-btn" id="feedTest">Test connection</button>
                    <span class="ads-test-out" id="feedTestOut"></span>
                </div>
                <div class="ads-help">Asks the service once now, with the location and limits on this page.</div>
            </div>
            <div class="ads-field">
                <label for="feedFallbackSw">Use the other as a backup</label>
                <div>
                    <input type="hidden" name="feedFallback" value="0">
                    <label class="ads-sw"><input type="checkbox" id="feedFallbackSw" name="feedFallback" value="1" <?php echo AirDefender_IsTruthy($v('feedFallback')) ? 'checked' : ''; ?>><span class="track"></span></label>
                </div>
                <div class="ads-help">If adsb.fi or adsb.lol stops answering or limits requests, ask the other one in the same check, and go back when it answers. Never used with a local receiver, so its location stays on this FPP.</div>
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
        <span style="display:flex;gap:8px;">
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
    for (var h = 1; h <= 8; h++) {
        (function (n) {
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
        $('checkWhenHelp').textContent = {
            scheduled: 'Checks only during scheduled shows, starting 2 minutes before each one. To test a sequence at another time, use Check now on the dashboard.',
            playing: 'Checks whenever FPP is playing, scheduled or started by hand, and 2 minutes before a scheduled show.',
            always: 'Checks around the clock, even with no show on. Uses the most flight-data requests.'
        }[w];
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
            help.style.color = '#ffb74d';
        } else {
            help.style.color = '';
            help.appendChild(document.createTextNode(useFpp ? "From FPP's settings. " : ''));
            var a = document.createElement('a');
            a.href = 'https://www.openstreetmap.org/?mlat=' + lat + '&mlon=' + lon + '#map=15/' + lat + '/' + lon;
            a.target = '_blank'; a.rel = 'noopener'; a.style.color = '#6cf'; a.textContent = 'Check it on a map';
            help.appendChild(a);
        }
    }

    /* ---------- section 4 ---------- */
    function updateRate() {
        var nm = parseFloat($('distanceNM').value), ft = parseFloat($('altitudeThresholdFt').value);
        $('kmHint').textContent = nm > 0 ? '≈ ' + (nm * 1.852).toFixed(1) + ' km' : '';
        $('mHint').textContent = ft >= 0 ? '≈ ' + Math.round(ft * 0.3048).toLocaleString() + ' m' : '';
        var sec = Math.max(5, parseInt($('fetchInterval').value, 10) || 10);
        var w = form.querySelector('input[name=checkWhen]:checked');
        var perHour = Math.round(3600 / sec);
        $('rateHint').textContent = (w && w.value === 'always')
            ? '≈ ' + (perHour * 24).toLocaleString() + ' requests a day. A smaller distance may want a quicker check.'
            : '≈ ' + (perHour * 5).toLocaleString() + ' requests in a 5-hour show night. A smaller distance may want a quicker check.';
    }

    /* ---------- section 5 ---------- */
    $('feedTest').addEventListener('click', function () {
        var btn = this, out = $('feedTestOut');
        btn.disabled = true; out.className = 'ads-test-out'; out.textContent = 'Asking…';
        var body = new URLSearchParams({
            action: 'feed_test', url: $('adsbSourceUrl').value,
            lat: $('latitude').value, lon: $('longitude').value,
            nm: $('distanceNM').value, ft: $('altitudeThresholdFt').value
        });
        fetch(API, { method: 'POST', credentials: 'same-origin', cache: 'no-store',
                     headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: body.toString() })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (d.ok) {
                    out.className = 'ads-test-out ok';
                    out.textContent = 'OK · ' + d.aircraft + ' aircraft nearby'
                        + (d.nearest ? ' · nearest ' + d.nearest.id + ' at ' + d.nearest.nm + ' NM' + (d.nearest.ft !== null ? ', ' + d.nearest.ft.toLocaleString() + ' ft' : '') : '')
                        + (d.inside ? ' · ' + d.inside + ' inside your limits' : '') + ' (' + d.ms + ' ms)';
                } else {
                    out.className = 'ads-test-out err';
                    out.textContent = d.error || 'No answer';
                }
            })
            .catch(function (e) { out.className = 'ads-test-out err'; out.textContent = 'Could not run the test: ' + e.message; })
            .then(function () { btn.disabled = false; });
    });

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

    function refreshAll() { updateTop(); allHeads(); updateWhen(); updateLocation(); updateRate(); }
    refreshAll();
    initial = snapshot();
    var msg = $('adsMsg');
    if (msg && msg.classList.contains('ok')) { setTimeout(function () { msg.style.display = 'none'; }, 4000); }
})();
</script>
