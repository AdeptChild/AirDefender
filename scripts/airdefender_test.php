<?php
/*
 * AirDefender test mode helpers.
 *
 * A test forces safety mode on for a set number of seconds so the user can
 * walk outside and see the outputs go dark, then come back on when the timer
 * runs out. The test lives in test.json in the plugin directory, so the
 * dashboard, the test request handler and airdefender_loop.php all read the
 * same thing. The end time is stored, not a remaining count, so a page
 * refresh or a loop restart picks up exactly where the test was.
 */

require_once(__DIR__ . "/airdefender_time.php");
if (!defined('AIRDEFENDER_TEST_MIN_SEC')) {
    define('AIRDEFENDER_TEST_MIN_SEC', 5);
    define('AIRDEFENDER_TEST_MAX_SEC', 1800);   // 30 minutes
    define('AIRDEFENDER_TEST_DEFAULT_SEC', 60);
}

function airdefender_test_file(): string
{
    return dirname(__DIR__) . "/test.json";
}

function airdefender_test_log(string $message): void
{
    $logFile = "/home/fpp/media/logs/airdefender.log";
    $timestamp = date("Y-m-d H:i:s");
    @file_put_contents($logFile, "[{$timestamp}] {$message}\n", FILE_APPEND | LOCK_EX);
}

function airdefender_test_clamp($seconds): int
{
    $seconds = (int) $seconds;
    if ($seconds <= 0) {
        $seconds = AIRDEFENDER_TEST_DEFAULT_SEC;
    }
    return max(AIRDEFENDER_TEST_MIN_SEC, min(AIRDEFENDER_TEST_MAX_SEC, $seconds));
}

function airdefender_test_raw(): array
{
    $default = [
        'active' => false,
        'startedAt' => null,
        'endsAt' => null,
        'duration' => AIRDEFENDER_TEST_DEFAULT_SEC,
        'lastDuration' => AIRDEFENDER_TEST_DEFAULT_SEC,
    ];

    $raw = @file_get_contents(airdefender_test_file());
    if ($raw === false || trim($raw) === '') {
        return $default;
    }

    $data = json_decode($raw, true);
    if (!is_array($data)) {
        return $default;
    }

    return array_merge($default, $data);
}

function airdefender_test_write(array $data): bool
{
    return @file_put_contents(
        airdefender_test_file(),
        json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
        LOCK_EX
    ) !== false;
}

/*
 * Current test status. A test whose end time has passed is closed out here
 * (and logged once), so whichever reader notices first ends it.
 */
function airdefender_test_status(): array
{
    $data = airdefender_test_raw();
    $now = time();

    if (!empty($data['active']) && (int) $data['endsAt'] <= $now) {
        $data['active'] = false;
        airdefender_test_write($data);
        airdefender_test_log("Test finished after {$data['duration']} s; outputs restored.");
    }

    $remaining = !empty($data['active']) ? max(0, (int) $data['endsAt'] - $now) : 0;

    return [
        'active' => !empty($data['active']),
        'remaining' => $remaining,
        'duration' => (int) $data['duration'],
        'lastDuration' => airdefender_test_clamp($data['lastDuration']),
        'endsAt' => $data['endsAt'] !== null ? (int) $data['endsAt'] : null,
        'serverTime' => $now,
        'minSec' => AIRDEFENDER_TEST_MIN_SEC,
        'maxSec' => AIRDEFENDER_TEST_MAX_SEC,
    ];
}

function airdefender_test_start($seconds): array
{
    $seconds = airdefender_test_clamp($seconds);
    $now = time();

    airdefender_test_write([
        'active' => true,
        'startedAt' => $now,
        'endsAt' => $now + $seconds,
        'duration' => $seconds,
        'lastDuration' => $seconds,
    ]);
    airdefender_test_log("Test started for {$seconds} s; safety mode forced active.");

    return airdefender_test_status();
}

function airdefender_test_stop(): array
{
    $data = airdefender_test_raw();
    if (!empty($data['active'])) {
        $data['active'] = false;
        $data['endsAt'] = time();
        airdefender_test_write($data);
        airdefender_test_log("Test stopped early; outputs restored.");
    }

    return airdefender_test_status();
}

/*
 * The newest line of airdefender.log, for the dashboard's Last Event, or
 * $fallback if there is none. Reads only the end of the file, since the log
 * keeps growing through the season.
 */
function airdefender_last_log_line(string $fallback = 'No log entries yet.'): string
{
    $fh = @fopen("/home/fpp/media/logs/airdefender.log", "r");
    if (!$fh) {
        return $fallback;
    }
    $size = fstat($fh)['size'] ?? 0;
    $chunk = min($size, 8192);
    if ($chunk <= 0) {
        fclose($fh);
        return $fallback;
    }
    fseek($fh, -$chunk, SEEK_END);
    $tail = (string) fread($fh, $chunk);
    fclose($fh);

    $lines = preg_split('/\r?\n/', trim($tail));
    $last = trim((string) end($lines));
    return $last !== '' ? $last : $fallback;
}
