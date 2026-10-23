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

function AirDefender_NormalizeValue($value, $default) {
    if ($value === null || $value === false || $value === '') {
        return $default;
    }

    if (is_array($value)) {
        return $default;
    }

    return $value;
}

function AirDefender_StoredValue($pluginName, $key, $default) {
    $value = GetSetting($pluginName, $key);
    return AirDefender_NormalizeValue($value, $default);
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

function AirDefender_ResolveAdsbSource($source, $customUrl) {
    $source = strtolower((string) $source);
    $customUrl = trim((string) $customUrl);

    $defaults = array(
        'piaware' => 'http://localhost:8080/data/aircraft.json',
        'dump1090' => 'http://localhost:8080/data/aircraft.json',
        'adsbexchange' => 'https://api.adsbexchange.com/api/aircraft/v2/lat/0/lon/0/dist/100?fDstL=0&fDstU=100',
        'radarbox' => 'https://api.radarbox.com/v2/aircraft?lat=0&lon=0&distance=50',
        'opensky' => 'https://opensky-network.org/api/states/all',
    );

    if ($source === 'custom' && $customUrl !== '') {
        return $customUrl;
    }

    if (isset($defaults[$source])) {
        return $defaults[$source];
    }

    return $customUrl !== '' ? $customUrl : '';
}

$settings = array(
    'enabled' => array(
        'label' => 'Enable Air Defender',
        'type' => 'bool',
        'default' => false,
    ),
    'safetyMode' => array(
        'label' => 'Enable Safety Mode',
        'type' => 'bool',
        'default' => false,
    ),
    'useFPPLocation' => array(
        'label' => 'Use FPP Location',
        'type' => 'bool',
        'default' => true,
    ),
    'overrideLocation' => array(
        'label' => 'Override Location',
        'type' => 'bool',
        'default' => false,
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
    'adsbSource' => array(
        'label' => 'ADS-B Feed Source',
        'type' => 'select',
        'default' => 'custom',
        'options' => array(
            'custom' => 'Custom URL',
            'piaware' => 'PiAware / dump1090',
            'adsbexchange' => 'ADS-B Exchange',
            'radarbox' => 'RadarBox',
            'opensky' => 'OpenSky',
        ),
    ),
    'adsbSourceUrl' => array(
        'label' => 'Custom ADS-B JSON URL',
        'type' => 'string',
        'default' => '',
    ),
    'controllerType' => array(
        'label' => 'Controller Type',
        'type' => 'select',
        'default' => 'falcon',
        'options' => array('falcon' => 'Falcon', 'kulp' => 'Kulp', 'generic' => 'Generic'),
    ),
    'serialPort' => array(
        'label' => 'Serial Port',
        'type' => 'string',
        'default' => '',
    ),
    'dmxOutputs' => array(
        'label' => 'DMX Outputs (comma-separated)',
        'type' => 'string',
        'default' => '',
    ),
);

$statusMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($settings as $key => $config) {
        if (!isset($_POST[$key])) {
            continue;
        }

        $postedValue = $_POST[$key];
        $finalValue = $config['default'];

        if ($config['type'] === 'bool') {
            $finalValue = AirDefender_PostBool($key);
        } elseif ($config['type'] === 'int') {
            $finalValue = (int) $postedValue;
        } elseif ($config['type'] === 'select') {
            $finalValue = (string) $postedValue;
        } else {
            $finalValue = trim((string) $postedValue);
        }

        if (function_exists('SetSetting')) {
            SetSetting($pluginName, $key, $finalValue);
        }
    }

    $source = isset($_POST['adsbSource']) ? (string) $_POST['adsbSource'] : 'custom';
    $customUrl = isset($_POST['adsbSourceUrl']) ? trim((string) $_POST['adsbSourceUrl']) : '';
    $resolvedUrl = AirDefender_ResolveAdsbSource($source, $customUrl);

    if (function_exists('SetSetting')) {
        SetSetting($pluginName, 'adsbSource', $source);
        SetSetting($pluginName, 'adsbSourceUrl', $resolvedUrl);
    }

    $statusMessage = 'Settings saved.';
}

foreach ($settings as $key => $config) {
    $value = AirDefender_StoredValue($pluginName, $key, $config['default']);
    $settings[$key]['value'] = $value;
}

$overrideLocationEnabled = AirDefender_IsTruthy($settings['overrideLocation']['value']);
$selectedAdsbSource = isset($settings['adsbSource']['value']) ? (string) $settings['adsbSource']['value'] : 'custom';
$showCustomAdsbUrl = ($selectedAdsbSource === 'custom');
?>

<style>
    .ad-toggle-group {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }

    .ad-toggle-option {
        display: inline-block;
        flex: 1;
        min-width: 110px;
        cursor: pointer;
        transition: transform 0.12s ease, opacity 0.12s ease, box-shadow 0.12s ease;
    }

    .ad-toggle-option:hover {
        transform: translateY(-1px);
        opacity: 1;
    }

    .ad-toggle-option input {
        position: absolute;
        opacity: 0;
        pointer-events: none;
    }

    .ad-toggle-button {
        display: block;
        padding: 10px 18px;
        border-radius: 6px;
        border: 1px solid rgba(255,255,255,0.12);
        font-weight: bold;
        text-align: center;
        color: #fff;
        background: #4a4a4a;
        transition: all 0.12s ease;
    }

    .ad-toggle-option:hover .ad-toggle-button {
        box-shadow: 0 0 0 1px rgba(255,255,255,0.15), 0 8px 18px rgba(0,0,0,0.22);
        filter: brightness(1.12);
    }

    .ad-toggle-option.is-on .ad-toggle-button {
        background: #2e7d32;
        color: #fff;
        border-color: #3da34a;
    }

    .ad-toggle-option.is-off .ad-toggle-button {
        background: #b71c1c;
        color: #fff;
        border-color: #d84343;
    }

    .ad-toggle-option.disabled .ad-toggle-button {
        background: #3a3a3a;
        color: #888;
        border-color: #4a4a4a;
        cursor: not-allowed;
        box-shadow: none;
        filter: none;
    }

    .ad-toggle-option.is-muted {
        opacity: 0.5;
    }
</style>

<div style="background:#1e1e1e;padding:20px;border-radius:10px;margin-bottom:20px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;">
    <div>
        <h2 style="color:#6cf;margin:0;">Air Defender Settings</h2>
        <p style="color:#ccc;margin-top:8px;">Saved settings are loaded automatically when you revisit this page.</p>
    </div>
    <div>
        <a href="plugin.php?plugin=AirDefender&page=www/index.php" style="display:inline-block;padding:9px 14px;border-radius:8px;background:#6cf;color:#111;text-decoration:none;font-weight:bold;">
            Dashboard
        </a>
    </div>
</div>

<?php if ($statusMessage !== ''): ?>
<div style="background:#264d2d;padding:15px 20px;border-radius:8px;margin-bottom:20px;color:#dff7e6;">
    <?php echo htmlspecialchars($statusMessage); ?>
</div>
<?php endif; ?>

<form method="POST" action="plugin.php?plugin=AirDefender&page=www/settings.php" style="background:#2a2a2a;padding:20px;border-radius:10px;">
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:18px;">
        <div style="background:#1f1f1f;padding:12px;border-radius:8px;">
            <label style="display:block;color:#d9d9d9;margin-bottom:10px;font-weight:bold;">Enable Air Defender</label>
            <div class="ad-toggle-group" data-toggle-group="enabled">
                <label class="ad-toggle-option is-on" data-toggle-value="1" data-toggle-name="enabled">
                    <input type="radio" name="enabled" value="1" <?php echo AirDefender_IsTruthy($settings['enabled']['value']) ? 'checked' : ''; ?>>
                    <span class="ad-toggle-button">On</span>
                </label>
                <label class="ad-toggle-option is-off" data-toggle-value="0" data-toggle-name="enabled">
                    <input type="radio" name="enabled" value="0" <?php echo !AirDefender_IsTruthy($settings['enabled']['value']) ? 'checked' : ''; ?>>
                    <span class="ad-toggle-button">Off</span>
                </label>
            </div>
        </div>

        <div style="background:#1f1f1f;padding:12px;border-radius:8px;">
            <label style="display:block;color:#d9d9d9;margin-bottom:10px;font-weight:bold;">Enable Safety Mode</label>
            <div class="ad-toggle-group" data-toggle-group="safetyMode">
                <label class="ad-toggle-option is-on" data-toggle-value="1" data-toggle-name="safetyMode">
                    <input type="radio" name="safetyMode" value="1" <?php echo AirDefender_IsTruthy($settings['safetyMode']['value']) ? 'checked' : ''; ?>>
                    <span class="ad-toggle-button">On</span>
                </label>
                <label class="ad-toggle-option is-off" data-toggle-value="0" data-toggle-name="safetyMode">
                    <input type="radio" name="safetyMode" value="0" <?php echo !AirDefender_IsTruthy($settings['safetyMode']['value']) ? 'checked' : ''; ?>>
                    <span class="ad-toggle-button">Off</span>
                </label>
            </div>
        </div>

        <?php foreach ($settings as $key => $config): ?>
            <?php if (in_array($key, array('enabled', 'safetyMode'), true)) continue; ?>
            <div style="background:#1f1f1f;padding:12px;border-radius:8px;">
                <label for="<?php echo htmlspecialchars($key); ?>" style="display:block;color:#d9d9d9;margin-bottom:8px;font-weight:bold;">
                    <?php echo htmlspecialchars($config['label']); ?>
                </label>

                <?php if ($config['type'] === 'bool'): ?>
                    <select name="<?php echo htmlspecialchars($key); ?>" id="<?php echo htmlspecialchars($key); ?>" style="width:100%;padding:8px;border-radius:6px;background:#111;color:#fff;">
                        <option value="1" <?php echo !empty($config['value']) ? 'selected' : ''; ?>>Yes</option>
                        <option value="0" <?php echo empty($config['value']) ? 'selected' : ''; ?>>No</option>
                    </select>
                <?php elseif ($config['type'] === 'select'): ?>
                    <select name="<?php echo htmlspecialchars($key); ?>" id="<?php echo htmlspecialchars($key); ?>" style="width:100%;padding:8px;border-radius:6px;background:#111;color:#fff;">
                        <?php foreach ($config['options'] as $optionValue => $optionLabel): ?>
                            <option value="<?php echo htmlspecialchars($optionValue); ?>" <?php echo ((string) $config['value'] === (string) $optionValue) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($optionLabel); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                <?php else: ?>
                    <input
                        type="text"
                        name="<?php echo htmlspecialchars($key); ?>"
                        id="<?php echo htmlspecialchars($key); ?>"
                        value="<?php echo htmlspecialchars((string) $config['value']); ?>"
                        <?php if ($key === 'latitude' || $key === 'longitude') { ?>
                            <?php if (!$overrideLocationEnabled): ?>
                                disabled
                            <?php endif; ?>
                        <?php } ?>
                        style="width:100%;padding:8px;border-radius:6px;border:1px solid #444;background:<?php echo (($key === 'latitude' || $key === 'longitude') && !$overrideLocationEnabled) ? '#2a2a2a' : '#111'; ?>;color:<?php echo (($key === 'latitude' || $key === 'longitude') && !$overrideLocationEnabled) ? '#777' : '#fff'; ?>;cursor:<?php echo (($key === 'latitude' || $key === 'longitude') && !$overrideLocationEnabled) ? 'not-allowed' : 'text'; ?>;"
                    >
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>

    <div style="margin-top:20px;display:flex;gap:12px;flex-wrap:wrap;">
        <button type="submit" style="padding:10px 18px;border-radius:6px;border:none;background:#6cf;color:#111;font-weight:bold;cursor:pointer;">
            Save Settings
        </button>
        <a href="plugin.php?plugin=AirDefender&page=www/index.php" style="display:inline-block;padding:10px 18px;border-radius:6px;background:#444;color:#fff;text-decoration:none;font-weight:bold;">
            Back to Dashboard
        </a>
    </div>
</form>

<script>
(function() {
    function updateToggleGroup(groupName) {
        var group = document.querySelectorAll('[data-toggle-group="' + groupName + '"] .ad-toggle-option');
        group.forEach(function(option) {
            var input = option.querySelector('input');
            var isChecked = input && input.checked;
            option.classList.toggle('is-muted', !isChecked);
            option.classList.toggle('disabled', !isChecked);
            option.classList.toggle('is-on', input && input.value === '1' && isChecked);
            option.classList.toggle('is-off', input && input.value === '0' && isChecked);
        });
    }

    function updateLocationState() {
        var overrideLocation = document.getElementById('overrideLocation');
        var enabled = overrideLocation && overrideLocation.value === '1';

        ['latitude', 'longitude'].forEach(function(id) {
            var field = document.getElementById(id);
            if (!field) {
                return;
            }

            field.disabled = !enabled;
            field.style.background = enabled ? '#111' : '#2a2a2a';
            field.style.color = enabled ? '#fff' : '#777';
            field.style.cursor = enabled ? 'text' : 'not-allowed';
        });
    }

    function updateAdsbSourceState() {
        var adsbSource = document.getElementById('adsbSource');
        var customUrlField = document.getElementById('adsbSourceUrl');
        if (!adsbSource || !customUrlField) {
            return;
        }

        var showCustom = adsbSource.value === 'custom';
        customUrlField.style.display = showCustom ? 'block' : 'none';
        customUrlField.disabled = !showCustom;
        customUrlField.style.background = showCustom ? '#111' : '#2a2a2a';
        customUrlField.style.color = showCustom ? '#fff' : '#777';
    }

    document.querySelectorAll('[data-toggle-group]').forEach(function(group) {
        var name = group.getAttribute('data-toggle-group');
        group.querySelectorAll('input[type="radio"]').forEach(function(input) {
            input.addEventListener('change', function() {
                updateToggleGroup(name);
            });
        });
        updateToggleGroup(name);
    });

    if (document.getElementById('overrideLocation')) {
        document.getElementById('overrideLocation').addEventListener('change', updateLocationState);
        updateLocationState();
    }

    if (document.getElementById('adsbSource')) {
        document.getElementById('adsbSource').addEventListener('change', updateAdsbSourceState);
        updateAdsbSourceState();
    }
})();
</script>
