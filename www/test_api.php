<?php
/*
 * AirDefender dashboard endpoint.
 *
 * Called as plugin.php?plugin=AirDefender&page=www/test_api.php&nopage=1
 * (nopage=1 stops FPP wrapping the response in its page header and menu).
 *
 *   GET                                  -> test status, live status, Last Event
 *   POST action=start&duration=<seconds> -> start a test
 *   POST action=stop                     -> end a test early
 *   POST action=clear_log                -> empty the Air Defender log
 *
 * Anything that changes state is POST only.
 */

require_once(dirname(__DIR__) . "/scripts/airdefender_status.php");

header('Content-Type: application/json');
header('Cache-Control: no-store');

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = $method === 'POST' ? ($_POST['action'] ?? '') : 'status';

switch ($action) {
    case 'start':
        $status = airdefender_test_start($_POST['duration'] ?? 0);
        if (!$status['active']) {
            http_response_code(500);
            echo json_encode(['error' => 'Could not save the test state. Check that the plugin folder is writable.']);
            exit;
        }
        break;
    case 'stop':
        $status = airdefender_test_stop();
        break;
    case 'clear_log':
        if (!airdefender_clear_log()) {
            http_response_code(500);
            echo json_encode(['error' => 'Could not clear the log. Check that the log file is writable.']);
            exit;
        }
        $status = airdefender_test_status();
        break;
    case 'status':
        $status = airdefender_test_status();
        break;
    default:
        http_response_code(400);
        echo json_encode(['error' => 'Unknown action']);
        exit;
}

$live = airdefender_live_status();
// The loop's own safety state, apart from a test (the test shows separately).
$status['loopSafety'] = $live['safe'] && $live['reason'] !== 'test';
$status['live'] = $live;
$status['lastEvent'] = airdefender_last_event();

echo json_encode($status);
