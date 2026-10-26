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

$distanceNM = (float) AirDefender_StoredValue("AirDefender", "distanceNM", 5);
$altitudeThresholdFt = (int) AirDefender_StoredValue("AirDefender", "altitudeThresholdFt", 3000);
$holdSec = (int) AirDefender_StoredValue("AirDefender", "clearDelaySec", 30);
require_once(dirname(__DIR__) . "/scripts/airdefender_feeds.php");
list($adsbSourceUrl) = airdefender_effective_feed((string) AirDefender_StoredValue("AirDefender", "adsbSourceUrl", ""));
$feedOptions = airdefender_feed_options();
// The service's name, or just the host of a custom address.
$adsbSourceLabel = isset($feedOptions[$adsbSourceUrl])
    ? preg_replace('/ \(default\)$/', '', $feedOptions[$adsbSourceUrl])
    : (parse_url($adsbSourceUrl, PHP_URL_HOST) ?: 'custom feed');

require_once(dirname(__DIR__) . "/scripts/airdefender_status.php");
require_once(dirname(__DIR__) . "/scripts/airdefender_version.php");

$test = airdefender_test_status();
$live = airdefender_live_status(true);
// Last Event shows only triggers: safety on/off and tests. Everything else is in the log.
$lastEvent = airdefender_last_event();
$pageConfig = array(
    'test' => $test,
    'live' => $live,
    'lastEvent' => $lastEvent,
    'limits' => array('nm' => $distanceNM, 'ft' => $altitudeThresholdFt, 'holdSec' => $holdSec),
);
?>
<style>
    .ad { --card:#262626; --card2:#1d1d1d; --line:#3a3a3a; --text:#ececec; --muted:#9a9a9a; --accent:#6cf;
          --warn:#ff9800; --ok:#4caf50; --bad:#f44336; --test:#42a5f5; color:var(--text); max-width:1100px; }
    .ad * { box-sizing:border-box; }
    .ad-top { display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap; margin-bottom:12px; }
    .ad-top h2 { color:var(--accent); margin:0; }
    .ad-switch { display:inline-flex; background:#111; border-radius:8px; padding:3px; gap:3px; }
    .ad-switch button, .ad-btn { border:1px solid var(--line); background:var(--card); color:var(--text); border-radius:6px;
          padding:7px 12px; font-size:14px; cursor:pointer; text-decoration:none; display:inline-block; line-height:1.2; }
    .ad-switch button { border:none; background:transparent; color:#bbb; font-weight:bold; }
    .ad-switch button.on { background:var(--accent); color:#111; }
    .ad-banner { display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap; border-radius:12px;
          padding:14px 18px; margin-bottom:12px; border:2px solid var(--line); background:var(--card); }
    .ad-banner .t { font-size:22px; font-weight:bold; }
    .ad-banner .s { font-size:14px; margin-top:4px; color:#ddd; }
    .ad-banner.clear { border-color:var(--ok); } .ad-banner.clear .t { color:var(--ok); }
    .ad-banner.safe { border-color:var(--warn); background:#3a2a10; } .ad-banner.safe .t { color:var(--warn); }
    .ad-banner.test { border-color:var(--test); background:#13283a; } .ad-banner.test .t { color:var(--test); }
    .ad-banner.down { border-color:var(--bad); background:#3a1414; } .ad-banner.down .t { color:#ff8a80; }
    .ad-banner.warn { border-color:#c9a227; } .ad-banner.warn .t { color:#e6c34a; }
    .ad-banner.off .t { color:var(--muted); }
    .ad-banner.standby { border-color:#78909c; } .ad-banner.standby .t { color:#b0bec5; }
    .ad-override { display:flex; align-items:center; gap:6px; flex-wrap:wrap; }
    .ad-override select { width:auto; min-width:118px; height:auto; min-height:34px; line-height:1.3;
          padding:6px 30px 6px 10px; border-radius:6px; background-color:#111; color:#fff; border:1px solid #444;
          font-size:14px; text-overflow:clip; }
    .ad-right { display:flex; flex-direction:column; align-items:flex-end; gap:8px; }
    .ad-chips { display:flex; gap:6px; flex-wrap:wrap; }
    .ad-chip { font-size:12px; padding:3px 10px; border-radius:12px; background:#111; color:#bbb; }
    .ad-grid { display:grid; grid-template-columns:repeat(auto-fit, minmax(250px, 1fr)); gap:12px; }
    .ad-card { background:var(--card); border:1px solid var(--line); border-radius:12px; padding:14px 16px; min-width:0; }
    .ad-card h3 { margin:0 0 10px 0; font-size:15px; color:var(--accent); display:flex; justify-content:space-between; gap:8px; }
    .ad-card h3 small { font-size:12px; font-weight:normal; color:var(--muted); }
    .ad-note { font-size:12px; color:var(--muted); margin-top:8px; }
    .ad-presets { display:flex; gap:5px; flex-wrap:wrap; margin-bottom:10px; }
    .ad-presets button { border:1px solid #555; background:#333; color:#ddd; border-radius:6px; padding:5px 9px; font-size:13px; cursor:pointer; }
    .ad-presets button.on { background:var(--accent); color:#111; border-color:var(--accent); font-weight:bold; }
    .ad-row { display:flex; align-items:center; gap:8px; flex-wrap:wrap; }
    .ad-row input { width:80px; padding:7px; border-radius:6px; background:#111; color:#fff; border:1px solid #444; }
    .ad-start { background:var(--warn); color:#111; border:none; font-weight:bold; }
    .ad-stop { background:var(--bad); color:#fff; border:none; font-weight:bold; }
    .ad-count { font-family:monospace; font-size:40px; font-weight:bold; color:var(--test); line-height:1; }
    .ad-bar { height:7px; background:#111; border-radius:4px; overflow:hidden; margin:10px 0; }
    .ad-bar div { height:100%; width:0; background:var(--test); transition:width .5s linear; }
    .ad-err { color:#ff7b72; font-size:13px; margin-top:8px; }
    .ad-head { display:flex; justify-content:space-between; align-items:center; gap:8px; padding:7px 0; border-bottom:1px solid var(--line); font-size:14px; }
    .ad-head:last-child { border-bottom:none; }
    .ad-head .ch { color:var(--muted); font-size:12px; margin-left:6px; }
    .ad-badge { font-size:11px; font-weight:bold; padding:2px 8px; border-radius:10px; white-space:nowrap; }
    .ad-badge.closed { background:var(--warn); color:#111; } .ad-badge.open { background:#333; color:#aaa; }
    .ad-prob { color:#ff7b72; font-size:12px; margin:-2px 0 6px 0; }
    .ad-radar { width:100%; max-width:260px; display:block; margin:0 auto; }
    .ad-foot { display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap; margin-top:12px;
          background:var(--card); border:1px solid var(--line); border-radius:12px; padding:10px 16px; font-size:14px; }
    .ad-foot .ev { overflow-wrap:anywhere; flex:1; min-width:240px; }
    .ad-foot .lim { color:var(--muted); font-size:13px; }
    .ad.lite .ad-detail { display:none; }
    .ad:not(.lite) .ad-liteonly { display:none; }
    .ad-hidden { display:none !important; }
</style>

<div class="ad lite" id="ad">
    <div class="ad-top">
        <h2>Air Defender <span style="font-size:13px;color:#888;font-weight:normal;">v<?php echo htmlspecialchars(airdefender_version()); ?></span></h2>
        <div class="ad-row">
            <div class="ad-switch" role="group" aria-label="Dashboard view">
                <button type="button" data-view="lite" class="on" title="Refreshes every 5 seconds">Lite</button>
                <button type="button" data-view="detailed" title="Adds the radar and every head; refreshes every 2 seconds">Detailed</button>
            </div>
            <a class="ad-btn" href="plugin.php?plugin=AirDefender&page=www/settings.php">Settings</a>
        </div>
    </div>

    <div class="ad-banner" id="adBanner">
        <div>
            <div class="t" id="adBannerTitle">&nbsp;</div>
            <div class="s" id="adBannerSub">&nbsp;</div>
        </div>
        <div class="ad-right">
            <div class="ad-chips">
                <span class="ad-chip" id="adChipRole"></span>
                <span class="ad-chip" id="adChipEnabled"></span>
            </div>
            <div class="ad-override ad-hidden" id="adOverrideOff" title="Check for aircraft now, even outside show times; turns itself off">
                <select id="adOverrideMin" aria-label="How long to check">
                    <option value="30">30 min</option>
                    <option value="60" selected>1 hour</option>
                    <option value="120">2 hours</option>
                    <option value="240">4 hours</option>
                </select>
                <button type="button" class="ad-btn" id="adOverrideStart">Check now</button>
            </div>
            <div class="ad-override ad-hidden" id="adOverrideOn">
                <span class="ad-chip" id="adOverrideLeft"></span>
                <button type="button" class="ad-btn" id="adOverrideEnd">End</button>
            </div>
        </div>
    </div>

    <div class="ad-grid">
        <div class="ad-card" id="adTestCard">
            <h3>Test <small>closes the heads for a while</small></h3>
            <div id="adTestIdle">
                <div class="ad-presets">
                    <button type="button" data-sec="30">30 s</button>
                    <button type="button" data-sec="60">1 min</button>
                    <button type="button" data-sec="120">2 min</button>
                    <button type="button" data-sec="300">5 min</button>
                    <button type="button" data-sec="600">10 min</button>
                </div>
                <div class="ad-row">
                    <input type="number" id="adTestSeconds" min="<?php echo (int) $test['minSec']; ?>" max="<?php echo (int) $test['maxSec']; ?>" step="5"
                        value="<?php echo (int) $test['lastDuration']; ?>" aria-label="Test length in seconds"> <span class="ad-note" style="margin:0;">seconds</span>
                    <button type="button" class="ad-btn ad-start" id="adTestStart">Start test</button>
                </div>
                <div class="ad-note">Go outside and watch the heads close, then open again when time runs out.</div>
            </div>
            <div id="adTestRunning" class="ad-hidden">
                <div class="ad-row" style="justify-content:space-between;">
                    <div>
                        <div class="ad-note" style="margin:0 0 6px 0;">Heads back on in</div>
                        <div class="ad-count" id="adCount">--:--</div>
                    </div>
                    <button type="button" class="ad-btn ad-stop" id="adTestStop">Stop test</button>
                </div>
                <div class="ad-bar"><div id="adBar"></div></div>
            </div>
            <div class="ad-err ad-hidden" id="adTestErr"></div>
        </div>

        <div class="ad-card ad-detail" id="adSkyCard">
            <h3>Sky <small id="adFeed"></small></h3>
            <svg class="ad-radar" id="adRadar" viewBox="0 0 200 200" role="img" aria-label="Aircraft around the show"></svg>
            <div class="ad-note" style="text-align:center;" id="adSkyNote"></div>
        </div>

        <div class="ad-card" id="adHeadsCard">
            <h3>Moving heads <small class="ad-liteonly" id="adFeedLite"></small></h3>
            <div class="ad-liteonly" id="adHeadsSummary"></div>
            <div class="ad-detail" id="adHeadsList"></div>
        </div>
    </div>

    <div class="ad-foot">
        <div class="ev"><span style="color:var(--muted);">Last event</span> &middot; <span id="adLastEvent"><?php echo htmlspecialchars($lastEvent); ?></span></div>
        <div class="ad-row">
            <span class="lim" id="adLimits"></span>
            <button type="button" class="ad-btn" id="adClearLog" title="Delete the Air Defender log, which lists aircraft that passed nearby. It keeps only the last 7 days on its own.">Clear log</button>
        </div>
    </div>
</div>

<script>
(function () {
    var API = 'plugin.php?plugin=AirDefender&page=www/test_api.php&nopage=1';
    var FEED_LABEL = <?php echo json_encode($adsbSourceLabel); ?>;
    var cfg = <?php echo json_encode($pageConfig); ?>;
    var status = cfg.test, live = cfg.live;
    var clockOffset = status.serverTime - Date.now() / 1000;
    var view = 'lite', pollTimer = null, tickTimer = null, finishing = false;

    var $ = function (id) { return document.getElementById(id); };
    var root = $('ad');

    function fmtClock(sec) {
        sec = Math.max(0, Math.ceil(sec));
        var m = Math.floor(sec / 60), s = sec % 60;
        return (m < 10 ? '0' : '') + m + ':' + (s < 10 ? '0' : '') + s;
    }
    function ago(sec) {
        if (sec === null || sec === undefined) { return ''; }
        if (sec < 60) { return sec + ' s ago'; }
        if (sec < 3600) { return Math.floor(sec / 60) + ' min ago'; }
        return Math.floor(sec / 3600) + ' h ago';
    }
    function num(n) { return Number(n).toLocaleString(); }
    function testLeft() {
        return status.active && status.endsAt ? status.endsAt - (Date.now() / 1000 + clockOffset) : 0;
    }
    function limits() {
        var l = (live && live.limits && live.limits.nm !== null) ? live.limits : cfg.limits;
        return { nm: l.nm, ft: l.ft, holdSec: l.holdSec };
    }

    /* ---------- banner ---------- */
    function renderBanner() {
        var b = $('adBanner'), title, sub, cls;
        var left = testLeft();
        if (live && !live.loopRunning) {
            cls = 'down'; title = 'Not running';
            sub = "Air Defender's detection loop is not running, so aircraft are not being checked. Restart FPPD (or reboot).";
        } else if (status.active && left > 0) {
            cls = 'test'; title = 'Test running';
            sub = 'Heads closed · back on in ' + fmtClock(left);
        } else if (live && !live.enabled) {
            cls = 'off'; title = 'Disabled';
            sub = 'Turn on Air Defender in Settings to check for aircraft.';
        } else if (live && live.role !== 'remote' && live.checking === false) {
            cls = 'standby'; title = 'Standby';
            sub = (live.checkWhen === 'playing' ? 'Not checking until FPP plays a show.' : 'Not checking until a scheduled show.');
            if (live.nextShow && live.nextShow.start) {
                var d = new Date(live.nextShow.start * 1000);
                sub += ' Next: ' + (live.nextShow.name || 'show') + ', ' + d.toLocaleDateString([], { weekday: 'short' }) + ' '
                    + d.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' }) + ' (checks start 2 min before).';
            }
        } else if (live && live.safe) {
            cls = 'safe'; title = 'Safety active';
            sub = (live.now || '') + (live.safeSince ? ' · since ' + new Date(live.safeSince * 1000).toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' }) : '');
        } else if (live && live.role !== 'remote' && live.feed && live.feed.error) {
            cls = 'warn'; title = "Can't check the sky";
            sub = live.feed.error;
        } else {
            cls = 'clear'; title = 'All clear';
            sub = (live && live.now) || '';
        }
        if (live && live.outputError) {
            sub += ' · FPP refused the shutter channels: ' + live.outputError;
        }
        // Check now: offered when checks depend on show times; shows time left while on.
        var ovLeft = live && live.overrideUntil ? live.overrideUntil - (Date.now() / 1000 + clockOffset) : 0;
        var canOverride = live && live.loopRunning && live.enabled && live.role !== 'remote' && live.checkWhen !== 'always';
        $('adOverrideOn').classList.toggle('ad-hidden', !(ovLeft > 0));
        $('adOverrideOff').classList.toggle('ad-hidden', !(canOverride && !(ovLeft > 0)));
        if (ovLeft > 0) {
            $('adOverrideLeft').textContent = 'Check now \u00b7 ' + (ovLeft >= 3600 ? Math.floor(ovLeft / 3600) + ' h ' : '')
                + Math.ceil((ovLeft % 3600) / 60) + ' min left';
        }
        b.className = 'ad-banner ' + cls;
        $('adBannerTitle').textContent = title;
        $('adBannerSub').textContent = sub;
        $('adChipRole').textContent = live && live.role === 'remote' ? 'Remote' : 'Master';
        $('adChipEnabled').textContent = live && !live.enabled ? 'Disabled' : 'Enabled';
    }

    /* ---------- test ---------- */
    function renderTest() {
        var left = testLeft(), running = status.active && left > 0;
        $('adTestIdle').classList.toggle('ad-hidden', running);
        $('adTestRunning').classList.toggle('ad-hidden', !running);
        if (running) {
            $('adCount').textContent = fmtClock(left);
            $('adBar').style.width = Math.max(0, Math.min(100, status.duration ? left / status.duration * 100 : 0)) + '%';
        }
        if (status.active && left <= 0 && !finishing) {
            finishing = true;
            poll().then(function () { finishing = false; }, function () { finishing = false; });
        }
        // Tick the countdown only while a test runs.
        if (running && !tickTimer) {
            tickTimer = setInterval(function () { renderTest(); renderBanner(); }, 500);
        } else if (!running && tickTimer) {
            clearInterval(tickTimer);
            tickTimer = null;
        }
    }
    function markPresets() {
        var v = parseInt($('adTestSeconds').value, 10);
        root.querySelectorAll('.ad-presets button').forEach(function (b) {
            b.classList.toggle('on', parseInt(b.getAttribute('data-sec'), 10) === v);
        });
    }
    function testError(msg) {
        $('adTestErr').textContent = msg || '';
        $('adTestErr').classList.toggle('ad-hidden', !msg);
    }

    /* ---------- heads ---------- */
    function renderHeads() {
        var heads = (live && live.heads) || [];
        var list = $('adHeadsList');
        list.textContent = '';
        if (!heads.length) {
            list.innerHTML = '<div class="ad-note" style="margin:0;">No heads enabled. Add them in Settings.</div>';
        }
        heads.forEach(function (h) {
            var row = document.createElement('div');
            row.className = 'ad-head';
            var name = document.createElement('span');
            name.textContent = h.name;
            var ch = document.createElement('span');
            ch.className = 'ch';
            ch.textContent = h.channel ? 'ch ' + h.channel : 'no channel';
            name.appendChild(ch);
            var badge = document.createElement('span');
            badge.className = 'ad-badge ' + (h.closed ? 'closed' : 'open');
            badge.textContent = h.closed ? 'Closed' : 'Show';
            row.appendChild(name);
            row.appendChild(badge);
            list.appendChild(row);
            if (h.problem) {
                var p = document.createElement('div');
                p.className = 'ad-prob';
                p.textContent = h.problem;
                list.appendChild(p);
            }
        });

        var closed = heads.filter(function (h) { return h.closed; }).length;
        var problems = heads.filter(function (h) { return h.problem; }).length;
        var summary = !heads.length ? 'No heads enabled. Add them in Settings.'
            : heads.length + (heads.length === 1 ? ' head' : ' heads') + ' · '
              + (closed === heads.length ? 'all closed' : closed === 0 ? 'the show controls them' : closed + ' closed');
        $('adHeadsSummary').textContent = summary + (problems ? ' · ' + problems + ' need attention (see Detailed)' : '');
        $('adHeadsSummary').style.color = problems ? '#ff7b72' : '';
    }

    /* ---------- sky ---------- */
    function feedText() {
        if (!live) { return ''; }
        if (live.role === 'remote') {
            var m = live.master || {};
            return m.connected ? 'master ' + (m.address || '') + ' OK' : 'master not connected';
        }
        var f = live.feed || {};
        if (live.checking === false) { return 'standby'; }
        if (f.error) { return 'feed problem'; }
        var label = (f.used || FEED_LABEL) + (f.onBackup ? ' (backup: ' + (f.chosen || 'your chosen service') + ' not answering)' : '');
        return f.lastOkAgo !== null && f.lastOkAgo !== undefined ? label + ' · ' + ago(f.lastOkAgo) : 'not checked yet';
    }
    function renderSky() {
        $('adFeed').textContent = feedText();
        $('adFeedLite').textContent = feedText();
        if (view !== 'detailed') { return; }

        var svg = $('adRadar'), lim = limits();
        var NS = 'http://www.w3.org/2000/svg';
        var outer = Math.max(1, Math.ceil(lim.nm || 5));   // the radius the feed is asked about
        var R = 88;
        function el(tag, attrs, text) {
            var e = document.createElementNS(NS, tag);
            for (var k in attrs) { e.setAttribute(k, attrs[k]); }
            if (text !== undefined) { e.textContent = text; }
            svg.appendChild(e);
            return e;
        }
        svg.textContent = '';
        el('circle', { cx: 100, cy: 100, r: R, fill: '#1d1d1d', stroke: '#444', 'stroke-width': 1 });
        el('circle', { cx: 100, cy: 100, r: R / 2, fill: 'none', stroke: '#333', 'stroke-width': 1 });
        el('line', { x1: 100, y1: 100 - R, x2: 100, y2: 100 + R, stroke: '#2e2e2e' });
        el('line', { x1: 100 - R, y1: 100, x2: 100 + R, y2: 100, stroke: '#2e2e2e' });
        el('circle', { cx: 100, cy: 100, r: R * Math.min(1, (lim.nm || outer) / outer), fill: 'none',
                       stroke: '#ff9800', 'stroke-width': 1.5, 'stroke-dasharray': '4 3' });
        el('text', { x: 100, y: 9, fill: '#888', 'font-size': 9, 'text-anchor': 'middle' }, 'N');
        el('text', { x: 196, y: 196, fill: '#777', 'font-size': 8, 'text-anchor': 'end' }, outer + ' NM');
        el('circle', { cx: 100, cy: 100, r: 3.5, fill: '#6cf' });

        if (live.role === 'remote') {
            $('adSkyNote').textContent = 'This Remote follows its master, which checks the sky.';
            return;
        }
        if (live.checking === false) {
            $('adSkyNote').textContent = 'Standby: not checking until there is a show.';
            return;
        }
        var planes = live.aircraft || [], inside = 0;
        planes.forEach(function (p) {
            var r = Math.min(1, p.nm / outer) * R, a = p.brg * Math.PI / 180;
            var c = el('circle', { cx: (100 + r * Math.sin(a)).toFixed(1), cy: (100 - r * Math.cos(a)).toFixed(1),
                                   r: p.in ? 5 : 3, fill: p.in ? '#ff9800' : '#9a9a9a' });
            var t = document.createElementNS(NS, 'title');
            t.textContent = p.id + ' · ' + p.nm + ' NM · ' + (p.ft === null ? '? ft' : num(p.ft) + ' ft');
            c.appendChild(t);
            if (p.in) { inside++; }
        });
        $('adSkyNote').textContent = !live.feed || live.feed.lastOkAgo === null ? 'Waiting for flight data'
            : planes.length + (planes.length === 1 ? ' aircraft' : ' aircraft') + ' around · '
              + inside + ' inside ' + lim.nm + ' NM / ' + num(lim.ft) + ' ft';
    }

    /* ---------- footer ---------- */
    function renderFooter(lastEvent) {
        if (typeof lastEvent === 'string') { $('adLastEvent').textContent = lastEvent; }
        var lim = limits();
        $('adLimits').textContent = lim.nm + ' NM · ' + num(lim.ft) + ' ft · hold ' + lim.holdSec + ' s'
            + (live && live.role === 'remote' ? '' : ' · ' + FEED_LABEL);
    }

    function renderAll(data) {
        if (data) {
            status = data;
            clockOffset = data.serverTime - Date.now() / 1000;
            if (data.live) { live = data.live; }
        }
        renderBanner(); renderTest(); renderHeads(); renderSky(); renderFooter(data ? data.lastEvent : cfg.lastEvent);
    }

    /* ---------- server ---------- */
    function call(method, body) {
        var opts = { method: method, credentials: 'same-origin', cache: 'no-store' };
        if (body) { opts.headers = { 'Content-Type': 'application/x-www-form-urlencoded' }; opts.body = body; }
        return fetch(API + (view === 'detailed' ? '&view=detailed' : ''), opts).then(function (r) {
            return r.json().then(function (d) {
                if (!r.ok) { throw new Error(d.error || ('HTTP ' + r.status)); }
                renderAll(d);
                return d;
            });
        });
    }
    function poll() { return call('GET').catch(function () {}); }
    function schedule() {
        if (pollTimer) { clearInterval(pollTimer); }
        pollTimer = setInterval(poll, view === 'detailed' ? 2000 : 5000);
    }

    /* ---------- view switch ---------- */
    function setView(v, save) {
        view = v === 'detailed' ? 'detailed' : 'lite';
        root.classList.toggle('lite', view === 'lite');
        root.querySelectorAll('.ad-switch button').forEach(function (b) {
            b.classList.toggle('on', b.getAttribute('data-view') === view);
        });
        if (save) { try { localStorage.setItem('airdefenderView', view); } catch (e) {} }
        schedule();
        renderAll();
        if (save) { poll(); }
    }
    root.querySelectorAll('.ad-switch button').forEach(function (b) {
        b.addEventListener('click', function () { setView(b.getAttribute('data-view'), true); });
    });

    /* ---------- controls ---------- */
    root.querySelectorAll('.ad-presets button').forEach(function (b) {
        b.addEventListener('click', function () { $('adTestSeconds').value = b.getAttribute('data-sec'); markPresets(); });
    });
    $('adTestSeconds').addEventListener('input', markPresets);
    $('adTestStart').addEventListener('click', function () {
        var sec = parseInt($('adTestSeconds').value, 10);
        if (!(sec >= status.minSec && sec <= status.maxSec)) {
            testError('Pick a test length between ' + status.minSec + ' and ' + status.maxSec + ' seconds.');
            return;
        }
        testError('');
        this.disabled = true;
        var btn = this;
        call('POST', 'action=start&duration=' + encodeURIComponent(sec))
            .then(function () { setTimeout(poll, 1500); })   // the loop needs about a second to move the heads
            .catch(function (e) { testError('Could not start the test: ' + e.message); })
            .then(function () { btn.disabled = false; });
    });
    $('adTestStop').addEventListener('click', function () {
        var btn = this;
        btn.disabled = true;
        call('POST', 'action=stop')
            .then(function () { setTimeout(poll, 1500); })   // the loop needs about a second to move the heads
            .catch(function (e) { testError('Could not stop the test: ' + e.message); })
            .then(function () { btn.disabled = false; });
    });
    $('adOverrideStart').addEventListener('click', function () {
        var btn = this;
        btn.disabled = true;
        call('POST', 'action=override&minutes=' + encodeURIComponent($('adOverrideMin').value))
            .then(function () { setTimeout(poll, 2500); })   // the loop checks within a couple of seconds
            .catch(function (e) { window.alert('Could not start Check now: ' + e.message); })
            .then(function () { btn.disabled = false; });
    });
    $('adOverrideEnd').addEventListener('click', function () {
        var btn = this;
        btn.disabled = true;
        call('POST', 'action=override_off')
            .then(function () { setTimeout(poll, 2500); })
            .catch(function (e) { window.alert('Could not end Check now: ' + e.message); })
            .then(function () { btn.disabled = false; });
    });
    $('adClearLog').addEventListener('click', function () {
        if (!window.confirm('Delete the Air Defender log? This cannot be undone.')) { return; }
        var btn = this;
        btn.disabled = true;
        call('POST', 'action=clear_log')
            .then(function () { btn.textContent = 'Log cleared'; })
            .catch(function (e) { window.alert('Could not clear the log: ' + e.message); })
            .then(function () { btn.disabled = false; setTimeout(function () { btn.textContent = 'Clear log'; }, 3000); });
    });

    var saved = 'lite';
    try { saved = localStorage.getItem('airdefenderView') || 'lite'; } catch (e) {}
    markPresets();
    setView(saved, false);
})();
</script>
