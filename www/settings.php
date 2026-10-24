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

function AirDefender_StoredValue($pluginName, $key, $default) {
    $value = GetSetting($pluginName, $key);
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
            'adsbSourceUrl' => array(
        'label' => 'ADS-B JSON URL',
        'type' => 'select',
        'default' => 'https://api.airplanes.live/v2/point/{lat}/{lon}/{radius}',
        'options' => array(
            'https://api.airplanes.live/v2/point/{lat}/{lon}/{radius}' => 'airplanes.live (default)',
            'https://api.adsb.lol/v2/point/{lat}/{lon}/{radius}' => 'adsb.lol',
            'https://opendata.adsb.fi/api/v2/lat/{lat}/lon/{lon}/dist/{radius}' => 'adsb.fi',
            'http://localhost/tar1090/data/aircraft.json' => 'Local receiver (tar1090)',
            'http://localhost:8080/data/aircraft.json' => 'Local receiver (dump1090-fa)',
        ),
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
    'ADS-B Feed' => array('adsbSourceUrl'),
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

    $statusMessage = "Settings saved.";
}

foreach ($settings as $key => $config) {
    $value = AirDefender_StoredValue($pluginName, $key, $config['default']);
    $settings[$key]['value'] = $value;
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
                            <div data-boolean-group="<?php echo htmlspecialchars($key); ?>" style="display:inline-flex;gap:8px;background:#111;padding:4px;border-radius:8px;">
                                <label style="flex:0 0 auto;display:block;cursor:pointer;">
                                    <input type="radio" name="<?php echo htmlspecialchars($key); ?>" value="1" <?php echo AirDefender_IsTruthy($config['value']) ? 'checked' : ''; ?> style="display:none;">
                                    <span style="display:block;text-align:center;padding:8px 12px;width:100px;box-sizing:border-box;border-radius:6px;font-weight:bold;line-height:1.1;white-space:nowrap;<?php echo AirDefender_IsTruthy($config['value']) ? 'background:#6cf;color:#111;' : 'background:#202020;color:#bbb;'; ?>">
                                        Yes
                                    </span>
                                </label>
                                <label style="flex:0 0 auto;display:block;cursor:pointer;">
                                    <input type="radio" name="<?php echo htmlspecialchars($key); ?>" value="0" <?php echo !AirDefender_IsTruthy($config['value']) ? 'checked' : ''; ?> style="display:none;">
                                    <span style="display:block;text-align:center;padding:8px 12px;width:100px;box-sizing:border-box;border-radius:6px;font-weight:bold;line-height:1.1;white-space:nowrap;<?php echo !AirDefender_IsTruthy($config['value']) ? 'background:#6cf;color:#111;' : 'background:#202020;color:#bbb;'; ?>">
                                        No
                                    </span>
                                </label>
                            </div>
                            <?php if ($key === 'useFPPLocation'): ?>
                                <div style="margin-top:12px;padding:8px 10px;background:#111;border-radius:6px;">
                                    <div style="color:#6cf;font-weight:bold;font-size:13px;margin-bottom:4px;">Current FPP Location</div>
                                    <div style="color:#ccc;font-size:13px;">Latitude: <?php echo $fppLat !== null ? htmlspecialchars((string) $fppLat) : 'Not detected'; ?></div>
                                    <div style="color:#ccc;font-size:13px;">Longitude: <?php echo $fppLon !== null ? htmlspecialchars((string) $fppLon) : 'Not detected'; ?></div>
                                </div>
                            <?php endif; ?>
                        <?php elseif ($config['type'] === 'select'): ?>
                            <select name="<?php echo htmlspecialchars($key); ?>" id="<?php echo htmlspecialchars($key); ?>" style="width:100%;max-width:320px;padding:8px;border-radius:6px;background:#111;color:#fff;">
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
        <a href="plugin.php?plugin=AirDefender&page=www/index.php" style="display:inline-block;padding:8px 12px;border-radius:6px;background:#333;color:#fff;text-decoration:none;font-weight:bold;line-height:1.1;">
            Dashboard
        </a>
        <button type="submit" style="padding:8px 12px;border-radius:6px;border:none;background:#6cf;color:#111;font-weight:bold;cursor:pointer;line-height:1.1;">
            Save Settings
        </button>
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
            label.style.border = selected ? '1px solid #6cf' : '1px solid #555';
            label.style.boxShadow = selected ? 'inset 0 0 0 1px rgba(255,255,255,0.08)' : 'none';
            label.style.opacity = '1';
            label.style.padding = '8px 12px';
            label.style.width = '100px';
            label.style.boxSizing = 'border-box';
            label.style.borderRadius = '6px';
            label.style.fontWeight = 'bold';
            label.style.cursor = 'pointer';
            label.style.lineHeight = '1.1';
            label.style.display = 'block';
            label.style.textAlign = 'center';
            label.style.whiteSpace = 'nowrap';
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
