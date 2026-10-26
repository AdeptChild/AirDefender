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
require_once(dirname(__DIR__) . "/scripts/airdefender_feeds.php");
list($adsbSourceUrl) = airdefender_effective_feed((string) AirDefender_StoredValue("AirDefender", "adsbSourceUrl", ""));
$feedOptions = airdefender_feed_options();
$adsbSourceLabel = isset($feedOptions[$adsbSourceUrl]) ? preg_replace('/ \(default\)$/', '', $feedOptions[$adsbSourceUrl]) : $adsbSourceUrl;
$nodeRole = AirDefender_StoredValue("AirDefender", "nodeRole", "standalone");

$modeLabels = array(
    'standalone' => 'Master',
    'master' => 'Master',
    'remote' => 'Remote',
);
$modeLabel = isset($modeLabels[$nodeRole]) ? $modeLabels[$nodeRole] : ucfirst((string) $nodeRole);

require_once(dirname(__DIR__) . "/scripts/airdefender_status.php");

$state = AirDefender_ReadState();
$test = airdefender_test_status();
$live = airdefender_live_status();
$safetyActive = !empty($state['safetyMode']) || $test['active'];
// Last Event shows only triggers: safety on/off and tests. Everything else is in the log.
$lastLog = airdefender_last_event();
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

<!-- Right Now: what the detection loop is doing, refreshed with the test status -->
<style>
    #adLive { background:#2a2a2a; padding:15px 20px; border-radius:8px; margin:15px 5px 0 5px; max-width:760px; }
    #adLive h3 { color:#6cf; margin:0 0 10px 0; font-size:18px; }
    #adLive .ad-warn { background:#4a1f1f; color:#ffd7d3; padding:8px 12px; border-radius:6px; margin-bottom:10px; font-size:14px; display:none; }
    #adLive .ad-now { color:#eee; font-size:15px; margin-bottom:12px; }
    #adLive .ad-now.safe { color:#ffb74d; font-weight:bold; }
    #adLive .ad-row { display:flex; gap:10px; flex-wrap:wrap; font-size:14px; margin-bottom:8px; }
    #adLive .ad-k { color:#999; min-width:110px; }
    #adLive .ad-v { color:#ddd; flex:1; min-width:200px; }
    #adLive .ad-v.bad { color:#ff7b72; }
    #adLive .ad-heads { display:flex; flex-wrap:wrap; gap:8px; margin-top:6px; }
    #adLive .ad-head { background:#1f1f1f; border:1px solid #3a3a3a; border-radius:8px; padding:7px 10px; font-size:13px; color:#ddd; }
    #adLive .ad-head b { color:#fff; }
    #adLive .ad-pill { display:inline-block; margin-left:6px; padding:1px 7px; border-radius:10px; font-size:12px; font-weight:bold; }
    #adLive .ad-pill.closed { background:#ff9800; color:#111; }
    #adLive .ad-pill.open { background:#333; color:#aaa; }
    #adLive .ad-head .ad-problem { color:#ff7b72; margin-top:4px; max-width:320px; }
</style>

<div id="adLive">
    <h3>Right Now</h3>
    <div id="adLiveWarn" class="ad-warn"></div>
    <div id="adLiveNow" class="ad-now"></div>
    <div class="ad-row" id="adLiveFeedRow"><span class="ad-k">Flight data</span><span class="ad-v" id="adLiveFeed"></span></div>
    <div class="ad-row" id="adLiveMasterRow"><span class="ad-k">Master FPP</span><span class="ad-v" id="adLiveMaster"></span></div>
    <div class="ad-row" id="adLivePlaneRow"><span class="ad-k">Last aircraft</span><span class="ad-v" id="adLivePlane"></span></div>
    <div class="ad-row" style="margin-bottom:0;"><span class="ad-k">Moving heads</span></div>
    <div id="adLiveHeads" class="ad-heads"></div>
</div>

<script>
(function () {
    function ago(sec) {
        if (sec === null || sec === undefined) { return ''; }
        if (sec < 60) { return sec + ' s ago'; }
        if (sec < 3600) { return Math.floor(sec / 60) + ' min ago'; }
        return Math.floor(sec / 3600) + ' h ago';
    }
    function set(id, text, bad) {
        var el = document.getElementById(id);
        el.textContent = text;
        el.classList.toggle('bad', !!bad);
    }
    function show(id, on) {
        document.getElementById(id).style.display = on ? '' : 'none';
    }

    window.adRenderLive = function (live) {
        if (!live) { return; }

        var warn = document.getElementById('adLiveWarn');
        var warnText = !live.loopRunning
            ? 'The Air Defender detection loop is not running, so aircraft are not being checked. Restart FPPD (or reboot) to start it.'
            : (live.outputError ? 'Could not set the shutter channels in FPP: ' + live.outputError : '');
        warn.textContent = warnText;
        warn.style.display = warnText ? 'block' : 'none';

        var now = document.getElementById('adLiveNow');
        now.textContent = live.loopRunning ? (live.now || '') : 'Not running';
        now.classList.toggle('safe', live.loopRunning && live.safe);

        var remote = live.role === 'remote';
        show('adLiveFeedRow', !remote);
        show('adLiveMasterRow', remote);
        if (!remote) {
            var f = live.feed || {};
            if (f.error) {
                set('adLiveFeed', 'Problem: ' + f.error + (f.lastOkAgo !== null ? ' (last good answer ' + ago(f.lastOkAgo) + ')' : ''), true);
            } else if (f.lastOkAgo !== null) {
                set('adLiveFeed', 'OK \u00b7 ' + f.aircraft + ' aircraft nearby \u00b7 checked ' + ago(f.lastOkAgo), false);
            } else {
                set('adLiveFeed', 'Not checked yet', false);
            }
        } else {
            var m = live.master || {};
            if (m.connected) {
                set('adLiveMaster', 'Connected to ' + (m.address || 'the master') + ' \u00b7 checked ' + ago(m.lastOkAgo), false);
            } else {
                set('adLiveMaster', 'Not connected' + (m.address ? ' to ' + m.address : '') + (m.error ? ': ' + m.error : ''), true);
            }
        }

        var p = live.lastAircraft;
        show('adLivePlaneRow', !!p);
        if (p) {
            set('adLivePlane', (p.flight || 'Unknown') + ' \u00b7 ' + p.distanceNm + ' NM \u00b7 ' + p.altitudeFt + ' ft'
                + (p.seenAt ? ' \u00b7 ' + new Date(p.seenAt).toLocaleTimeString() : ''), false);
        }

        var box = document.getElementById('adLiveHeads');
        box.textContent = '';
        if (!live.heads || !live.heads.length) {
            var none = document.createElement('div');
            none.className = 'ad-head';
            none.textContent = 'No heads enabled. Add them in Settings \u2192 Moving Heads.';
            box.appendChild(none);
        }
        (live.heads || []).forEach(function (h) {
            var chip = document.createElement('div');
            chip.className = 'ad-head';
            var name = document.createElement('b');
            name.textContent = h.name;
            chip.appendChild(name);
            chip.appendChild(document.createTextNode(h.channel ? ' \u00b7 ch ' + h.channel : ' \u00b7 no channel'));
            var pill = document.createElement('span');
            pill.className = 'ad-pill ' + (h.closed ? 'closed' : 'open');
            pill.textContent = h.closed ? 'CLOSED' : 'show controls it';
            chip.appendChild(pill);
            if (h.problem) {
                var prob = document.createElement('div');
                prob.className = 'ad-problem';
                prob.textContent = h.problem;
                chip.appendChild(prob);
            }
            box.appendChild(chip);
        });
    };

    window.adRenderLive(<?php echo json_encode($live); ?>);
})();
</script>

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
        if (data.live && window.adRenderLive) {
            window.adRenderLive(data.live);
        }
        if (typeof data.lastEvent === 'string') {
            var lastEvent = document.getElementById('adLastEvent');
            if (lastEvent && lastEvent.textContent.trim() !== data.lastEvent) {
                lastEvent.textContent = data.lastEvent;
            }
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
    setInterval(function () { request('GET').catch(function () {}); }, 2000); // live status, Last Event, other browsers
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
            <?php echo htmlspecialchars((string) $adsbSourceLabel); ?>
        </p>
    </div>
    <?php endif; ?>
</div>


<div style="background:#2a2a2a;padding:15px 20px;border-radius:8px;margin:20px 5px 0 5px;max-width:760px;">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:10px;">
        <h3 style="color:#6cf;margin:0;">Last Event</h3>
        <button type="button" id="adClearLog" title="Delete the Air Defender log, which lists aircraft that passed nearby. It also keeps only the last 7 days on its own."
            style="padding:6px 12px;border-radius:6px;border:1px solid #555;background:#333;color:#ddd;font-size:13px;cursor:pointer;">Clear log</button>
    </div>
    <p id="adLastEvent" style="color:#eee;margin:0;overflow-wrap:anywhere;">
        <?php echo htmlspecialchars($lastLog); ?>
    </p>
    <p style="color:#888;font-size:12px;margin:8px 0 0 0;">Safety mode turning on and off, and tests. Everything else is in the full log (<code>airdefender.log</code>), which keeps the last 7 days.</p>
</div>

<script>
(function () {
    var btn = document.getElementById('adClearLog');
    btn.addEventListener('click', function () {
        if (!window.confirm('Delete the Air Defender log? This cannot be undone.')) {
            return;
        }
        btn.disabled = true;
        fetch('plugin.php?plugin=AirDefender&page=www/test_api.php&nopage=1', {
            method: 'POST', credentials: 'same-origin', cache: 'no-store',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'action=clear_log'
        }).then(function (r) {
            return r.json().then(function (d) {
                if (!r.ok) { throw new Error(d.error || ('HTTP ' + r.status)); }
                document.getElementById('adLastEvent').textContent = d.lastEvent;
                btn.textContent = 'Log cleared';
            });
        }).catch(function (e) {
            window.alert('Could not clear the log: ' + e.message);
        }).then(function () {
            btn.disabled = false;
            setTimeout(function () { btn.textContent = 'Clear log'; }, 3000);
        });
    });
})();
</script>
