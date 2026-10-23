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

function AirDefender_StoredValue($pluginName, $key, $default) {
    $value = GetSetting($pluginName, $key);
    return AirDefender_NormalizeValue($value, $default);
}

function AirDefender_GetFPPDetectedLocation() {
    $lat = null;
    $lon = null;

    if (!function_exists("GetSetting")) {
        return array($lat, $lon);
    }

    $candidates = array(
        array('System', 'Latitude'),
        array('System', 'longitude'),
        array('Settings', 'Latitude'),
        array('Settings', 'longitude'),
        array('Location', 'Latitude'),
        array('Location', 'Longitude'),
        array('fpp', 'latitude'),
        array('fpp', 'longitude'),
        array('Global', 'latitude'),
        array('Global', 'longitude'),
        array('fpp', 'lat'),
        array('fpp', 'lon'),
    );

    foreach ($candidates as $candidate) {
        list($section, $field) = $candidate;
        $value = GetSetting($section, $field);

        if ($value === null || $value === false) {
            continue;
        }

        $text = trim((string) $value);
        if ($text === "" || strtolower($text) === "null") {
            continue;
        }

        if (!is_numeric($text)) {
            continue;
        }

        $numericValue = (float) $text;
        $lowerField = strtolower($field);

        if ($lowerField === 'latitude' || $lowerField === 'lat') {
            if ($numericValue >= -90 && $numericValue <= 90) {
                $lat = $numericValue;
            }
        } elseif ($lowerField === 'longitude' || $lowerField === 'lon') {
            if ($numericValue >= -180 && $numericValue <= 180) {
                $lon = $numericValue;
            }
        }

        if ($lat !== null && $lon !== null) {
            break;
        }
    }

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

$settingGroups = array(
    'Plugin Status' => array('enabled'),
    'Location' => array('useFPPLocation', 'latitude', 'longitude'),
    'Safety Thresholds' => array('distanceNM', 'altitudeThresholdFt', 'fetchInterval'),
    'ADS-B Feed' => array('adsbSource', 'adsbSourceUrl'),
    'Controller' => array('controllerType', 'serialPort', 'dmxOutputs'),
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

$useFppLocationEnabled = AirDefender_IsTruthy($settings['useFPPLocation']['value']);
list($fppLat, $fppLon) = AirDefender_GetFPPDetectedLocation();

if ($useFppLocationEnabled) {
    if ($fppLat !== null) {
        $settings['latitude']['value'] = (string) $fppLat;
    }
    if ($fppLon !== null) {
        $settings['longitude']['value'] = (string) $fppLon;
    }
}
?>

<div style="background:#1e1e1e;padding:20px;border-radius:10px;margin-bottom:20px;">
    <h2 style="color:#6cf;margin:0;">Air Defender Settings</h2>
    <p style="color:#ccc;margin-top:8px;">Configure the ADS-B safety threshold and controller behavior.</p>
</div>

<?php if ($statusMessage !== ''): ?>
<div style="background:#264d2d;padding:15px 20px;border-radius:8px;margin-bottom:20px;color:#dff7e6;">
    <?php echo htmlspecialchars($statusMessage); ?>
</div>
<?php endif; ?>

<form method="POST" action="plugin.php?plugin=AirDefender&page=www/settings.php" style="background:#2a2a2a;padding:20px;border-radius:10px;">
    <?php foreach ($settingGroups as $groupName => $groupKeys): ?>
        <div style="background:#1f1f1f;padding:16px;border-radius:10px;margin-bottom:20px;">
            <h3 style="margin:0 0 14px 0;color:#6cf;"><?php echo htmlspecialchars($groupName); ?></h3>
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:18px;">
                <?php foreach ($groupKeys as $key): ?>
                    <?php $config = $settings[$key]; ?>
                    <div style="background:#171717;padding:12px;border-radius:8px;">
                        <label for="<?php echo htmlspecialchars($key); ?>" style="display:block;color:#d9d9d9;margin-bottom:8px;font-weight:bold;">
                            <?php echo htmlspecialchars($config['label']); ?>
                        </label>

                        <?php if ($config['type'] === 'bool'): ?>
                            <div data-boolean-group="<?php echo htmlspecialchars($key); ?>" style="display:flex;gap:8px;background:#111;padding:4px;border-radius:8px;">
                                <label style="flex:1;display:block;cursor:pointer;">
                                    <input type="radio" name="<?php echo htmlspecialchars($key); ?>" value="1" <?php echo AirDefender_IsTruthy($config['value']) ? 'checked' : ''; ?> style="display:none;">
                                    <span class="ad-toggle-button" style="<?php echo AirDefender_IsTruthy($config['value']) ? 'background:#6cf;color:#111;' : 'background:#202020;color:#bbb;'; ?>">
                                        Yes
                                    </span>
                                </label>
                                <label style="flex:1;display:block;cursor:pointer;">
                                    <input type="radio" name="<?php echo htmlspecialchars($key); ?>" value="0" <?php echo !AirDefender_IsTruthy($config['value']) ? 'checked' : ''; ?> style="display:none;">
                                    <span class="ad-toggle-button" style="<?php echo !AirDefender_IsTruthy($config['value']) ? 'background:#6cf;color:#111;' : 'background:#202020;color:#bbb;'; ?>">
                                        No
                                    </span>
                                </label>
                            </div>
                        <?php elseif ($config['type'] === 'select'): ?>
                            <select name="<?php echo htmlspecialchars($key); ?>" id="<?php echo htmlspecialchars($key); ?>" style="width:100%;padding:8px;border-radius:6px;background:#111;color:#fff;">
                                <?php foreach ($config['options'] as $optionValue => $optionLabel): ?>
                                    <option value="<?php echo htmlspecialchars($optionValue); ?>" <?php echo ((string) $config['value'] === (string) $optionValue) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($optionLabel); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        <?php else: ?>
                            <?php
                                $fieldValue = (string) ($config['value'] !== null && $config['value'] !== '' && $config['value'] !== '[]' && strtolower((string) $config['value']) !== 'null' ? $config['value'] : $config['default']);

                                if ($key === 'latitude' && $useFppLocationEnabled && $fppLat !== null) {
                                    $fieldValue = (string) $fppLat;
                                }

                                if ($key === 'longitude' && $useFppLocationEnabled && $fppLon !== null) {
                                    $fieldValue = (string) $fppLon;
                                }
                            ?>
                            <input
                                type="text"
                                name="<?php echo htmlspecialchars($key); ?>"
                                id="<?php echo htmlspecialchars($key); ?>"
                                value="<?php echo htmlspecialchars($fieldValue); ?>"
                                data-fpp-value="<?php echo htmlspecialchars($key === 'latitude' ? ($fppLat !== null ? (string) $fppLat : '') : ($fppLon !== null ? (string) $fppLon : '')); ?>"
                                <?php if ($key === 'latitude' || $key === 'longitude') { ?>
                                    <?php if ($useFppLocationEnabled): ?>
                                        disabled
                                    <?php endif; ?>
                                <?php } ?>
                                style="width:100%;padding:8px;border-radius:6px;border:1px solid #444;background:#111;color:#fff;<?php echo (($key === 'latitude' || $key === 'longitude') && $useFppLocationEnabled) ? 'background:#1a1a1a;color:#888;cursor:not-allowed;opacity:0.45;' : ''; ?>"
                            >
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endforeach; ?>

    <div style="margin-top:20px;display:flex;justify-content:flex-end;align-items:center;gap:12px;flex-wrap:wrap;">
        <button type="submit" style="padding:10px 18px;border-radius:6px;border:none;background:#6cf;color:#111;font-weight:bold;cursor:pointer;">
            Save Settings
        </button>
        <a href="plugin.php?plugin=AirDefender&page=www/index.php" style="display:inline-block;padding:10px 18px;border-radius:6px;background:#444;color:#fff;text-decoration:none;font-weight:bold;">
            Back to Dashboard
        </a>
    </div>
</form>

<script>
(function () {
    function updateToggleVisuals(group) {
        if (!group) {
            return;
        }

        var radios = group.querySelectorAll('input[type="radio"]');
        radios.forEach(function (radio) {
            var label = radio.nextElementSibling;
            if (!label) {
                return;
            }

            var selected = radio.checked;
            label.style.background = selected ? '#6cf' : '#202020';
            label.style.color = selected ? '#111' : '#bbb';
            label.style.boxShadow = selected ? 'inset 0 0 0 1px rgba(255,255,255,0.08)' : 'none';
            label.style.opacity = '1';
        });
    }

    function updateLocationState() {
        var group = document.querySelector('[data-boolean-group="useFPPLocation"]');
        if (!group) {
            return;
        }

        var yesRadio = group.querySelector('input[type="radio"][value="1"]');
        var useFpp = yesRadio && yesRadio.checked;

        ['latitude', 'longitude'].forEach(function (id) {
            var input = document.getElementById(id);
            if (!input) {
                return;
            }

            if (useFpp) {
                var fppValue = input.getAttribute('data-fpp-value');
                if (fppValue !== null && fppValue !== '') {
                    input.value = fppValue;
                }
            }

            input.disabled = useFpp;
            input.style.background = useFpp ? '#1a1a1a' : '#111';
            input.style.color = useFpp ? '#888' : '#fff';
            input.style.cursor = useFpp ? 'not-allowed' : 'text';
            input.style.opacity = useFpp ? '0.45' : '1';
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-boolean-group]').forEach(function (group) {
            updateToggleVisuals(group);

            group.querySelectorAll('input[type="radio"]').forEach(function (radio) {
                radio.addEventListener('change', function () {
                    updateToggleVisuals(group);
                    if (group.getAttribute('data-boolean-group') === 'useFPPLocation') {
                        updateLocationState();
                    }
                });
            });
        });

        updateLocationState();
    });
})();
</script>
