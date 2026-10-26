<?php
/*
 * What the dashboard shows about the detection loop right now, read from the
 * state.json it writes every second, plus clearing the log.
 */

require_once(__DIR__ . "/airdefender_test.php");

const AIRDEFENDER_LOG_FILE = "/home/fpp/media/logs/airdefender.log";
const AIRDEFENDER_LOG_KEEP_DAYS = 7;
const AIRDEFENDER_LOOP_STALE_SEC = 15;

/*
 * $detailed adds the nearby aircraft for the radar; the Lite view leaves
 * them out to keep each refresh small.
 */
function airdefender_live_status(bool $detailed = false): array
{
    $now = time();
    $state = json_decode((string) @file_get_contents(dirname(__DIR__) . "/state.json"), true);
    $state = is_array($state) ? $state : [];

    $updated = isset($state['lastUpdated']) ? strtotime($state['lastUpdated']) : false;
    $loopAge = $updated ? $now - $updated : null;

    $closedChannels = array_map('intval', array_keys(is_array($state['appliedChannels'] ?? null) ? $state['appliedChannels'] : []));
    $heads = [];
    foreach ((is_array($state['heads'] ?? null) ? $state['heads'] : []) as $n => $h) {
        $channel = isset($h['channel']) ? (int) $h['channel'] : null;
        $heads[] = [
            'name' => (string) ($h['name'] ?? "MH{$n}"),
            'channel' => $channel,
            'closed' => $channel !== null && in_array($channel, $closedChannels, true),
            'problem' => (string) ($h['problem'] ?? ''),
        ];
    }

    $lastOk = isset($state['lastFetchOk']) ? strtotime($state['lastFetchOk']) : false;
    $master = is_array($state['master'] ?? null) ? $state['master'] : null;
    $since = isset($state['safeSince']) ? strtotime($state['safeSince']) : false;
    $limits = is_array($state['limits'] ?? null) ? $state['limits'] : [];
    $masterOk = $master && !empty($master['lastOk']) ? strtotime($master['lastOk']) : false;

    return [
        'loopRunning' => $loopAge !== null && $loopAge <= AIRDEFENDER_LOOP_STALE_SEC,
        'safe' => !empty($state['safetyMode']),
        'reason' => (string) ($state['reason'] ?? ''),
        'now' => (string) ($state['lastEvent'] ?? ''),
        'role' => ($state['role'] ?? 'master') === 'remote' ? 'remote' : 'master',
        'feed' => [
            'aircraft' => isset($state['aircraftCount']) ? (int) $state['aircraftCount'] : null,
            'lastOkAgo' => $lastOk ? max(0, $now - $lastOk) : null,
            'error' => $state['feedError'] ?? null,
            'used' => $state['feedUsed'] ?? null,
            'onBackup' => !empty($state['feedOnBackup']),
            'chosen' => $state['feedChosen'] ?? null,
        ],
        'master' => $master ? [
            'address' => $master['address'] ?? null,
            'connected' => !empty($master['connected']),
            'lastOkAgo' => $masterOk ? max(0, $now - $masterOk) : null,
            'error' => $master['error'] ?? null,
        ] : null,
        'heads' => $heads,
        'lastAircraft' => is_array($state['lastAircraft'] ?? null) ? $state['lastAircraft'] : null,
        'outputError' => $state['outputError'] ?? null,
        'enabled' => !array_key_exists('enabled', $state) || !empty($state['enabled']),
        'safeSince' => $since ?: null,
        'limits' => [
            'nm' => isset($limits['nm']) ? (float) $limits['nm'] : null,
            'ft' => isset($limits['ft']) ? (int) $limits['ft'] : null,
            'holdSec' => isset($limits['holdSec']) ? (int) $limits['holdSec'] : null,
        ],
        'aircraft' => $detailed && is_array($state['nearby'] ?? null) ? $state['nearby'] : null,
        'checking' => !array_key_exists('checking', $state) || !empty($state['checking']),
        'checkWhen' => (string) ($state['checkWhen'] ?? 'scheduled'),
        // Read directly, so the dashboard shows a new override at once.
        'overrideUntil' => airdefender_override_until(),
        'nextShow' => is_array($state['nextShow'] ?? null) ? $state['nextShow'] : null,
    ];
}

/*
 * Empty the Air Defender log, its rotated copies and the Last Event, which
 * hold callsigns of aircraft that passed nearby. Returns false if the log
 * could not be emptied.
 */
function airdefender_clear_log(): bool
{
    $ok = true;
    if (file_exists(AIRDEFENDER_LOG_FILE)) {
        $fh = @fopen(AIRDEFENDER_LOG_FILE, 'c');
        if ($fh && flock($fh, LOCK_EX)) {
            ftruncate($fh, 0);
            flock($fh, LOCK_UN);
        } else {
            $ok = false;
        }
        if ($fh) {
            fclose($fh);
        }
    }
    foreach (glob(AIRDEFENDER_LOG_FILE . ".*") ?: [] as $rotated) {
        @unlink($rotated);
    }
    @unlink(airdefender_last_event_file());
    if ($ok) {
        airdefender_test_log("Log cleared from the dashboard.");
    }
    return $ok;
}

/*
 * Drop log lines older than AIRDEFENDER_LOG_KEEP_DAYS. The loop runs this
 * now and then; lines it cannot date are kept.
 */
function airdefender_trim_log(): void
{
    $fh = @fopen(AIRDEFENDER_LOG_FILE, 'c+');
    if (!$fh) {
        return;
    }
    if (flock($fh, LOCK_EX)) {
        $cutoff = date("Y-m-d H:i:s", time() - AIRDEFENDER_LOG_KEEP_DAYS * 86400);
        $kept = [];
        $dropped = 0;
        while (($line = fgets($fh)) !== false) {
            if (preg_match('/^\[(\d{4}-\d\d-\d\d \d\d:\d\d:\d\d)\]/', $line, $m) && $m[1] < $cutoff) {
                $dropped++;
                continue;
            }
            $kept[] = $line;
        }
        if ($dropped > 0) {
            ftruncate($fh, 0);
            rewind($fh);
            fwrite($fh, implode('', $kept));
            fflush($fh);
        }
        flock($fh, LOCK_UN);
    }
    fclose($fh);

    // Copies FPP's log rotation made are older still.
    foreach (glob(AIRDEFENDER_LOG_FILE . ".*") ?: [] as $rotated) {
        if (@filemtime($rotated) < time() - AIRDEFENDER_LOG_KEEP_DAYS * 86400) {
            @unlink($rotated);
        }
    }
}
