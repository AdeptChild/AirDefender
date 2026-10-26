<?php
/*
 * AirDefender master status, polled once a second by every Remote.
 *
 *   GET plugin.php?plugin=AirDefender&page=www/master_status.php&nopage=1
 *
 * Answers whether this FPP wants safety mode on (an aircraft, the hold after
 * one, or a test), plus how fresh its detection loop is, so a remote can
 * tell a quiet master from a dead one. Read-only.
 */

require_once(dirname(__DIR__) . "/scripts/airdefender_test.php");

header('Content-Type: application/json');
header('Cache-Control: no-store');

$now = time();

$settings = [];
$lines = @file("/home/fpp/media/config/plugin.AirDefender", FILE_IGNORE_NEW_LINES);
foreach (is_array($lines) ? $lines : [] as $line) {
    $parts = explode('=', $line, 2);
    if (count($parts) === 2) {
        $settings[trim($parts[0])] = trim(trim($parts[1]), "\"' ");
    }
}
$role = strtolower($settings['nodeRole'] ?? 'master') === 'remote' ? 'remote' : 'master';

$state = json_decode((string) @file_get_contents(dirname(__DIR__) . "/state.json"), true);
$state = is_array($state) ? $state : [];
$updated = isset($state['lastUpdated']) ? strtotime($state['lastUpdated']) : false;

// Read the test directly rather than from state.json, so a test reaches the
// remotes without waiting a second for the loop to notice it.
$test = airdefender_test_status();

echo json_encode([
    'plugin' => 'AirDefender',
    'role' => $role,
    'safe' => $test['active'] || !empty($state['safetyMode']),
    'reason' => $test['active'] ? 'test' : ($state['reason'] ?? 'unknown'),
    'event' => $test['active'] ? "Test running, {$test['remaining']} s left" : ($state['lastEvent'] ?? ''),
    'testActive' => $test['active'],
    'testRemaining' => $test['remaining'],
    'loopAge' => $updated ? $now - $updated : null,
    'serverTime' => $now,
]);
