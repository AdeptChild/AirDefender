<?php
/*
 * AirDefender test mode endpoint, used by the dashboard.
 *
 * Called as plugin.php?plugin=AirDefender&page=www/test_api.php&nopage=1
 * (nopage=1 stops FPP wrapping the response in its page header and menu).
 *
 *   GET                                  -> current test status
 *   POST action=start&duration=<seconds> -> start a test
 *   POST action=stop                     -> end a test early
 *
 * Starting and stopping change state, so they are POST only.
 */

require_once(dirname(__DIR__) . "/scripts/airdefender_test.php");

header('Content-Type: application/json');
header('Cache-Control: no-store');

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = $method === 'POST' ? ($_POST['action'] ?? '') : 'status';

switch ($action) {
    case 'start':
        $status = airdefender_test_start($_POST['duration'] ?? 0);
        break;
    case 'stop':
        $status = airdefender_test_stop();
        break;
    case 'status':
        $status = airdefender_test_status();
        break;
    default:
        http_response_code(400);
        echo json_encode(['error' => 'Unknown action']);
        exit;
}

if ($action === 'start' && !$status['active']) {
    http_response_code(500);
    echo json_encode(['error' => 'Could not save the test state. Check that the plugin folder is writable.']);
    exit;
}

echo json_encode($status);
