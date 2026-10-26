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
    $default = [
        'safetyMode' => false,
        'lastEvent' => 'No events yet.',
        'lastAircraft' => null,
        'lastDistanceNm' => null,
        'lastAltitudeFt' => null,
        'lastUpdated' => null,
    ];

    $path = AirDefender_StateFile();
    if (!file_exists($path)) {
        return $default;
    }

    $content = @file_get_contents($path);
    if ($content === false || trim($content) === '') {
        return $default;
    }

    $data = json_decode($content, true);
    if (!is_array($data)) {
        return $default;
    }

    return $data;
}

$enabled = AirDefender_StoredValue("AirDefender", "enabled", false);
$distanceNM = AirDefender_StoredValue("AirDefender", "distanceNM", 5);
$altitudeThresholdFt = AirDefender_StoredValue("AirDefender", "altitudeThresholdFt", 3000);
$adsbSourceUrl = AirDefender_StoredValue("AirDefender", "adsbSourceUrl", "https://api.airplanes.live/v2/point/{lat}/{lon}/{radius}");
$nodeRole = AirDefender_StoredValue("AirDefender", "nodeRole", "standalone");

$modeLabels = array(
    'standalone' => 'Standalone',
    'master' => 'Master',
    'remote' => 'Remote',
);
$modeLabel = isset($modeLabels[$nodeRole]) ? $modeLabels[$nodeRole] : ucfirst((string) $nodeRole);

require_once(dirname(__DIR__) . "/scripts/airdefender_test.php");

$state = AirDefender_ReadState();
$test = airdefender_test_status();
$safetyActive = !empty($state['safetyMode']) || $test['active'];
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

<div style="display:flex;flex-wrap:wrap;justify-content:flex-start;">
    <div style="background:#2a2a2a;padding:15px 20px;border-radius:8px;margin:5px;display:inline-block;text-align:center;">
        <h3 style="color:<?php echo !empty($enabled) ? '#4caf50' : '#f44336'; ?>;margin:0 0 10px 0;font-size:18px;">
            Plugin Enabled
        </h3>
        <p style="color:#eee;font-size:16px;margin:0;white-space:nowrap;">
            <?php echo !empty($enabled) ? "Yes" : "No"; ?>
        </p>
    </div>

    <div style="background:#2a2a2a;padding:15px 20px;border-radius:8px;margin:5px;display:inline-block;text-align:center;">
        <h3 id="adSafetyTitle" style="color:<?php echo $safetyActive ? '#ff9800' : '#888'; ?>;margin:0 0 10px 0;font-size:18px;">
            Safety Mode
        </h3>
        <p id="adSafetyValue" style="color:#eee;font-size:16px;margin:0;white-space:nowrap;">
            <?php echo $test['active'] ? "Active (Test)" : ($safetyActive ? "Active" : "Inactive"); ?>
        </p>
    </div>
</div>

<!-- Test Mode: forces safety mode on for a set time so people can go outside and watch -->
<style>
    #adTestCard { background:#2a2a2a; padding:15px 20px; border-radius:8px; margin:15px 5px 0 5px; max-width:560px; border:2px solid transparent; }
    #adTestCard.running { border-color:#ff9800; }
    #adTestCard h3 { color:#6cf; margin:0 0 6px 0; font-size:18px; }
    #adTestCard .ad-note { color:#bbb; font-size:14px; margin:0 0 14px 0; }
    #adTestCard .ad-row { display:flex; flex-wrap:wrap; align-items:center; gap:8px; }
    #adTestCard label { color:#ddd; font-size:14px; }
    #adTestCard input[type=number] { width:80px; padding:8px; border-radius:6px; background:#111; color:#fff; border:1px solid #444; font-size:15px; }
    #adTestCard .ad-chip { padding:7px 11px; border-radius:6px; background:#3a3a3a; color:#eee; border:1px solid #555; cursor:pointer; font-size:13px; }
    #adTestCard .ad-chip:hover { background:#4a4a4a; }
    #adTestCard .ad-chip.selected { background:#6cf; color:#111; border-color:#6cf; font-weight:bold; }
    #adTestCard .ad-btn { padding:10px 18px; border-radius:8px; border:none; font-weight:bold; font-size:15px; cursor:pointer; }
    #adTestCard .ad-btn:disabled { opacity:0.5; cursor:default; }
    #adTestStart { background:#ff9800; color:#111; }
    #adTestStop { background:#f44336; color:#fff; }
    #adTestCountdown { font-family:monospace; font-size:42px; font-weight:bold; color:#ff9800; line-height:1; }
    #adTestBar { height:8px; background:#111; border-radius:4px; overflow:hidden; margin:10px 0 12px 0; }
    #adTestBarFill { height:100%; width:0; background:#ff9800; transition:width 0.25s linear; }
    #adTestError { color:#f66; font-size:14px; margin-top:10px; display:none; }
</style>

<div id="adTestCard" class="<?php echo $test['active'] ? 'running' : ''; ?>">
    <h3>Test Mode</h3>
    <p class="ad-note">
        Forces Safety Mode on for the time you pick, then turns it back off, so you can head outside and watch the lights go dark and come back.
    </p>

    <div id="adTestIdle" style="<?php echo $test['active'] ? 'display:none;' : ''; ?>">
        <div class="ad-row" style="margin-bottom:12px;">
            <label for="adTestSeconds">Test length (seconds)</label>
            <input type="number" id="adTestSeconds"
                   min="<?php echo (int) $test['minSec']; ?>"
                   max="<?php echo (int) $test['maxSec']; ?>"
                   step="5"
                   value="<?php echo (int) $test['lastDuration']; ?>">
        </div>
        <div class="ad-row" style="margin-bottom:14px;">
            <button type="button" class="ad-chip" data-sec="30">30 s</button>
            <button type="button" class="ad-chip" data-sec="60">1 min</button>
            <button type="button" class="ad-chip" data-sec="120">2 min</button>
            <button type="button" class="ad-chip" data-sec="300">5 min</button>
            <button type="button" class="ad-chip" data-sec="600">10 min</button>
        </div>
        <button type="button" id="adTestStart" class="ad-btn">Start Test</button>
    </div>

    <div id="adTestRunning" style="<?php echo $test['active'] ? '' : 'display:none;'; ?>">
        <div class="ad-row" style="justify-content:space-between;">
            <div>
                <div style="color:#ddd;font-size:14px;margin-bottom:6px;">Outputs back on in</div>
                <div id="adTestCountdown">--:--</div>
            </div>
            <button type="button" id="adTestStop" class="ad-btn">Stop Test</button>
        </div>
        <div id="adTestBar"><div id="adTestBarFill"></div></div>
    </div>

    <div id="adTestError"></div>
</div>

<script>
(function () {
    var API = 'plugin.php?plugin=AirDefender&page=www/test_api.php&nopage=1';
    var realSafety = <?php echo (!empty($state['safetyMode']) && empty($state['testActive'])) ? 'true' : 'false'; ?>;
    var status = <?php echo json_encode($test); ?>;
    var clockOffset = status.serverTime - Date.now() / 1000; // server clock minus browser clock
    var finishing = false;

    var card = document.getElementById('adTestCard');
    var idle = document.getElementById('adTestIdle');
    var running = document.getElementById('adTestRunning');
    var input = document.getElementById('adTestSeconds');
    var startBtn = document.getElementById('adTestStart');
    var stopBtn = document.getElementById('adTestStop');
    var countdown = document.getElementById('adTestCountdown');
    var barFill = document.getElementById('adTestBarFill');
    var errorBox = document.getElementById('adTestError');
    var safetyTitle = document.getElementById('adSafetyTitle');
    var safetyValue = document.getElementById('adSafetyValue');
    var chips = card.querySelectorAll('.ad-chip');

    function showError(msg) {
        errorBox.textContent = msg || '';
        errorBox.style.display = msg ? 'block' : 'none';
    }

    function markChips() {
        var v = parseInt(input.value, 10);
        for (var i = 0; i < chips.length; i++) {
            chips[i].classList.toggle('selected', parseInt(chips[i].getAttribute('data-sec'), 10) === v);
        }
    }

    function fmt(sec) {
        sec = Math.max(0, Math.ceil(sec));
        var m = Math.floor(sec / 60);
        var s = sec % 60;
        return (m < 10 ? '0' : '') + m + ':' + (s < 10 ? '0' : '') + s;
    }

    function secondsLeft() {
        if (!status.active || !status.endsAt) {
            return 0;
        }
        return status.endsAt - (Date.now() / 1000 + clockOffset);
    }

    function render() {
        var left = secondsLeft();
        var isRunning = status.active && left > 0;

        idle.style.display = isRunning ? 'none' : '';
        running.style.display = isRunning ? '' : 'none';
        card.classList.toggle('running', isRunning);

        if (isRunning) {
            countdown.textContent = fmt(left);
            var pct = status.duration > 0 ? (left / status.duration) * 100 : 0;
            barFill.style.width = Math.max(0, Math.min(100, pct)) + '%';
        }

        var safe = isRunning || realSafety;
        safetyTitle.style.color = safe ? '#ff9800' : '#888';
        safetyValue.textContent = isRunning ? 'Active (Test)' : (safe ? 'Active' : 'Inactive');

        // Timer reached zero in the browser: ask the server, which ends the test and logs it.
        if (status.active && left <= 0 && !finishing) {
            finishing = true;
            request('GET').then(function () { finishing = false; }, function () { finishing = false; });
        }
    }

    function apply(data) {
        status = data;
        if (typeof data.loopSafety === 'boolean') {
            realSafety = data.loopSafety;
        }
        clockOffset = data.serverTime - Date.now() / 1000;
        render();
    }

    function request(method, body) {
        var opts = { method: method, credentials: 'same-origin', cache: 'no-store' };
        if (body) {
            opts.headers = { 'Content-Type': 'application/x-www-form-urlencoded' };
            opts.body = body;
        }
        return fetch(API, opts)
            .then(function (r) {
                return r.json().then(function (d) {
                    if (!r.ok) { throw new Error(d.error || ('HTTP ' + r.status)); }
                    return d;
                });
            })
            .then(function (d) { showError(''); apply(d); return d; });
    }

    for (var i = 0; i < chips.length; i++) {
        chips[i].addEventListener('click', function () {
            input.value = this.getAttribute('data-sec');
            markChips();
        });
    }
    input.addEventListener('input', markChips);

    startBtn.addEventListener('click', function () {
        var sec = parseInt(input.value, 10);
        if (!(sec >= status.minSec && sec <= status.maxSec)) {
            showError('Pick a test length between ' + status.minSec + ' and ' + status.maxSec + ' seconds.');
            return;
        }
        startBtn.disabled = true;
        request('POST', 'action=start&duration=' + encodeURIComponent(sec))
            .catch(function (e) { showError('Could not start the test: ' + e.message); })
            .then(function () { startBtn.disabled = false; });
    });

    stopBtn.addEventListener('click', function () {
        stopBtn.disabled = true;
        request('POST', 'action=stop')
            .catch(function (e) { showError('Could not stop the test: ' + e.message); })
            .then(function () { stopBtn.disabled = false; });
    });

    markChips();
    render();
    setInterval(render, 250);                                  // smooth countdown
    setInterval(function () { request('GET').catch(function () {}); }, 5000); // stay in sync with other browsers
})();
</script>

<div style="display:flex;flex-wrap:wrap;justify-content:flex-start;margin-top:20px;">
    <div style="background:#2a2a2a;padding:15px 20px;border-radius:8px;margin:5px;display:inline-block;text-align:center;">
        <h3 style="color:#6cf;margin:0 0 10px 0;font-size:18px;">Mode</h3>
        <p style="color:#eee;font-size:16px;margin:0;white-space:nowrap;">
            <?php echo htmlspecialchars($modeLabel); ?>
        </p>
    </div>

    <div style="background:#2a2a2a;padding:15px 20px;border-radius:8px;margin:5px;display:inline-block;text-align:center;">
        <h3 style="color:#6cf;margin:0 0 10px 0;font-size:18px;">Distance Threshold</h3>
        <p style="color:#eee;font-size:16px;margin:0;white-space:nowrap;">
            <?php echo isset($distanceNM) ? $distanceNM : 0; ?> NM
        </p>
    </div>

    <div style="background:#2a2a2a;padding:15px 20px;border-radius:8px;margin:5px;display:inline-block;text-align:center;">
        <h3 style="color:#6cf;margin:0 0 10px 0;font-size:18px;">Altitude Threshold</h3>
        <p style="color:#eee;font-size:16px;margin:0;white-space:nowrap;">
            <?php echo isset($altitudeThresholdFt) ? $altitudeThresholdFt : 3000; ?> ft
        </p>
    </div>

    <?php if (!empty($adsbSourceUrl)): ?>
    <div style="background:#2a2a2a;padding:15px 20px;border-radius:8px;margin:5px;display:inline-block;text-align:center;">
        <h3 style="color:#6cf;margin:0 0 10px 0;font-size:18px;">ADS-B Feed</h3>
        <p style="color:#eee;font-size:16px;margin:0;white-space:nowrap;">
            <?php echo htmlspecialchars((string) $adsbSourceUrl); ?>
        </p>
    </div>
    <?php endif; ?>
</div>

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

<div style="background:#2a2a2a;padding:15px 20px;border-radius:8px;margin:20px 0 0 0;display:table;text-align:center;">
    <h3 style="color:#6cf;margin:0 0 10px 0;">Last Event</h3>
    <p style="color:#eee;margin:0;white-space:nowrap;">
        <?php echo htmlspecialchars($lastLog); ?>
    </p>
</div>
