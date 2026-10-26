<?php
/*
 * Start or stop a test from the command line. Used by the FPP commands in
 * commands/ (Air Defender: Start Test / Stop Test).
 *
 *   php airdefender_test_cli.php start <seconds>
 *   php airdefender_test_cli.php stop
 */

if (PHP_SAPI !== 'cli') {
    exit(1);
}

require_once(__DIR__ . "/airdefender_test.php");

$action = $argv[1] ?? '';
if ($action === 'start') {
    $status = airdefender_test_start($argv[2] ?? 0);
    echo $status['active'] ? "Test started for {$status['duration']} s\n" : "Could not start the test\n";
    exit($status['active'] ? 0 : 1);
}
if ($action === 'stop') {
    airdefender_test_stop();
    echo "Test stopped\n";
    exit(0);
}

fwrite(STDERR, "Usage: php airdefender_test_cli.php start <seconds> | stop\n");
exit(2);
