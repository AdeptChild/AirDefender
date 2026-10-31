<?php
/*
 * Air Defender - Copyright (C) 2026 G Hancox. Created by G Hancox, built with Claude.
 * Licensed under the GNU GPL v2; see LICENSE.
 *
 * What the dashboard shows about the detection loop right now, read from the
 * state.json it writes every second, plus clearing the log. FPP rotates the
 * log itself (daily, keeping 7), as its plugin guidelines ask.
 */

require_once(__DIR__ . "/airdefender_test.php");

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
            'elevationFt' => isset($limits['elevationFt']) ? (int) $limits['elevationFt'] : 0,
        ],
        'aircraft' => $detailed && is_array($state['nearby'] ?? null) ? $state['nearby'] : null,
        'checking' => !array_key_exists('checking', $state) || !empty($state['checking']),
        'checkWhen' => (string) ($state['checkWhen'] ?? 'scheduled'),
        // Read directly, so the dashboard shows a new override at once.
        'overrideUntil' => airdefender_override_until(),
        'nextShow' => is_array($state['nextShow'] ?? null) ? $state['nextShow'] : null,
        'skippedPlaylist' => isset($state['skippedPlaylist']) ? (string) $state['skippedPlaylist'] : null,
        'playlistFilter' => !empty($state['playlistFilter']),
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
    if (file_exists(airdefender_log_file())) {
        $fh = @fopen(airdefender_log_file(), 'c');
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
    foreach (glob(airdefender_log_file() . ".*") ?: [] as $rotated) {
        @unlink($rotated);
    }
    @unlink(airdefender_last_event_file());
    if ($ok) {
        airdefender_test_log("Log cleared from the dashboard.");
    }
    return $ok;
}

/*
 * Log lines between $from and $to (Unix times), newest first: each as
 * ['time' => int, 'text' => string, 'event' => bool]. $eventsOnly keeps just
 * safety turning on/off and tests. Reads FPP's rotated copies too (".1", and
 * older ones gzip-compressed), so yesterday and the last 7 days work.
 * At most $max lines, so a busy week can't swamp the page.
 */
function airdefender_read_log(int $from, int $to, bool $eventsOnly, int $max = 1500): array
{
    $files = [];
    foreach (glob(airdefender_log_file() . '.*') ?: [] as $f) {
        if (preg_match('/\.(\d+)(\.gz)?$/', $f, $m)) {
            $files[(int) $m[1]] = $f;
        }
    }
    krsort($files);                          // oldest first: .7.gz ... .1
    $files[] = airdefender_log_file();
    $from = date('Y-m-d H:i:s', $from);
    $to = date('Y-m-d H:i:s', $to);

    $out = [];
    foreach ($files as $f) {
        $fh = @fopen((substr($f, -3) === '.gz' ? 'compress.zlib://' : '') . $f, 'r');
        if (!$fh) {
            continue;
        }
        while (($line = fgets($fh)) !== false) {
            if (!preg_match('/^\[(\d{4}-\d\d-\d\d \d\d:\d\d:\d\d)\] (.*)$/', rtrim($line, "\r\n"), $m)
                || $m[1] < $from || $m[1] > $to) {
                continue;
            }
            $event = (bool) preg_match('/^(SAFETY (ON|OFF)\b|Test (started|finished|stopped)\b)/', $m[2]);
            if ($eventsOnly && !$event) {
                continue;
            }
            $out[] = ['time' => strtotime($m[1]), 'text' => $m[2], 'event' => $event];
        }
        fclose($fh);
    }
    $out = array_reverse($out);
    return array_slice($out, 0, $max);
}
