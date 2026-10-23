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

    return in_array(strtolower((string) $value), array('1', 'true', 'yes', 'on'), true);
}

$settings = array(
    'Plugin' => array(
        'enabled' => array(
            'label' => 'Enable Air Defender',
            'type' => 'bool',
            'default' => false,
        ),
        'safetyMode' => array(
            'label' => 'Safety Mode Enabled',
            'type' => 'bool',
            'default' => false,
        ),
    ),
    'Location' => array(
        'useFPPLocation' => array(
            'label' => 'Use FPP Location',
            'type' => 'bool',
            'default' => true,
        ),
        'overrideLocation' => array(
            'label' => 'Use Override Location',
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
    ),
    'Detection' => array(
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
    ),
    'ADS-B Feed' => array(
        'adsbSourceUrl' => array(
            'label' => 'ADS-B JSON URL',
            'type' => 'string',
            'default' => '',
        ),
    ),
    'Controller' => array(
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
    ),
);

$statusMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($settings as $sectionName => $sectionSettings) {
        foreach ($sectionSettings as $key => $config) {
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
    }

    $statusMessage = "Settings saved.";
}

foreach ($settings as $sectionName => $sectionSettings) {
    foreach ($sectionSettings as $key => $config) {
        $value = AirDefender_StoredValue($pluginName, $key, $config['default']);
        $settings[$sectionName][$key]['value'] = $value;
    }
}

$overrideEnabled = !empty($settings['Location']['overrideLocation']['value']) ? true : false;
?>

<div style="background:#1e1e1e;padding:20px;border-radius:10px;margin-bottom:20px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;">
    <div>
        <h2 style="color:#6cf;margin:0;">Air Defender Settings</h2>
        <p style="color:#ccc;margin-top:8px;">Defaults are loaded automatically when no saved value exists.</p>
    </div>
    <div>
        <a href="plugin.php?plugin=AirDefender&page=www/index.php" style="display:inline-block;padding:9px 14px;border-radius:8px;background:#6cf;color:#111;text-decoration:none;font-weight:bold;">
            Back to Dashboard
        </a>
    </div>
</div>

<?php if ($statusMessage !== ''): ?>
<div style="background:#264d2d;padding:15px 20px;border-radius:8px;margin-bottom:20px;color:#dff7e6;">
    <?php echo htmlspecialchars($statusMessage); ?>
</div>
<?php endif; ?>

<form method="POST" action="plugin.php?plugin=AirDefender&page=www/settings.php" style="background:#2a2a2a;padding:20px;border-radius:10px;">
    <?php foreach ($settings as $sectionName => $sectionSettings): ?>
        <div style="margin-bottom:28px;">
            <h3 style="color:#6cf;margin:0 0 12px 0;font-size:18px;">
                <?php echo htmlspecialchars($sectionName); ?>
            </h3>

            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:18px;">
                <?php foreach ($sectionSettings as $key => $config): ?>
                    <div style="background:#1f1f1f;padding:12px;border-radius:8px;">
                        <label for="<?php echo htmlspecialchars($key); ?>" style="display:block;color:#d9d9d9;margin-bottom:8px;font-weight:bold;">
                            <?php echo htmlspecialchars($config['label']); ?>
                        </label>

                        <?php if ($config['type'] === 'bool'): ?>
                            <select name="<?php echo htmlspecialchars($key); ?>" id="<?php echo htmlspecialchars($key); ?>" style="width:100%;padding:8px;border-radius:6px;background:#111;color:#fff;">
                                <option value="1" <?php echo (!empty($config['value']) && (string) $config['value'] !== '0') ? 'selected' : ''; ?>>Yes</option>
                                <option value="0" <?php echo (empty($config['value']) || (string) $config['value'] === '0') ? 'selected' : ''; ?>>No</option>
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
                                <?php if (($sectionName === 'Location' && in_array($key, array('latitude', 'longitude'), true)) && !$overrideEnabled): ?>
                                    disabled
                                    style="width:100%;padding:8px;border-radius:6px;border:1px solid #444;background:#2a2a2a;color:#777;cursor:not-allowed;"
                                <?php else: ?>
                                    style="width:100%;padding:8px;border-radius:6px;border:1px solid #444;background:#111;color:#fff;"
                                <?php endif; ?>
                            >
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endforeach; ?>

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
    var overrideSelect = document.getElementById('overrideLocation');
    var latInput = document.getElementById('latitude');
    var lonInput = document.getElementById('longitude');

    function updateManualLocationState() {
        var enabled = overrideSelect && overrideSelect.value === '1';

        if (latInput) {
            latInput.disabled = !enabled;
            latInput.style.background = enabled ? '#111' : '#2a2a2a';
            latInput.style.color = enabled ? '#fff' : '#777';
            latInput.style.cursor = enabled ? 'text' : 'not-allowed';
        }

        if (lonInput) {
            lonInput.disabled = !enabled;
            lonInput.style.background = enabled ? '#111' : '#2a2a2a';
            lonInput.style.color = enabled ? '#fff' : '#777';
            lonInput.style.cursor = enabled ? 'text' : 'not-allowed';
        }
    }

    if (overrideSelect) {
        overrideSelect.addEventListener('change', updateManualLocationState);
        updateManualLocationState();
    }
})();
</script>
