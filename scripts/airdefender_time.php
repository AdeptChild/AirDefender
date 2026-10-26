<?php
/*
 * Use the time zone set in FPP (Settings > Time Zone) for every timestamp
 * Air Defender writes.
 *
 * FPP's own web pages do this in config.php, but the detection loop runs
 * from the command line where PHP falls back to UTC, so its log lines (which
 * the dashboard's Last Event shows) came out in UTC. Every plugin file loads
 * this, so the log, the dashboard and the remotes all agree with FPP's clock.
 */

function airdefender_fpp_timezone(): ?string
{
    $valid = timezone_identifiers_list();

    $lines = @file('/home/fpp/media/settings', FILE_IGNORE_NEW_LINES);
    foreach (is_array($lines) ? $lines : [] as $line) {
        $parts = explode('=', $line, 2);
        if (count($parts) === 2 && trim($parts[0]) === 'TimeZone') {
            $tz = trim(trim($parts[1]), "\"' ");
            if (in_array($tz, $valid, true)) {
                return $tz;
            }
        }
    }

    // The system's own zone, in case the setting is missing.
    $tz = trim((string) @file_get_contents('/etc/timezone'));
    return in_array($tz, $valid, true) ? $tz : null;
}

$airdefenderTz = airdefender_fpp_timezone();
if ($airdefenderTz !== null) {
    date_default_timezone_set($airdefenderTz);
}
unset($airdefenderTz);
