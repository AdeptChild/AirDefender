<?php
/*
 * Air Defender - Copyright (C) 2026 G Hancox. Created by G Hancox, built with Claude.
 * Licensed under the GNU GPL v2; see LICENSE.
 *
 * Use the time zone set in FPP (Settings > Time Zone) for every timestamp
 * Air Defender writes.
 *
 * FPP's own web pages do this in config.php, but the detection loop runs
 * from the command line where PHP falls back to UTC, so its log lines (which
 * the dashboard's Last Event shows) came out in UTC. Every plugin file loads
 * this, so the log, the dashboard and the remotes all agree with FPP's clock.
 */

require_once(__DIR__ . "/airdefender_paths.php");

function airdefender_fpp_timezone(): ?string
{
    $valid = timezone_identifiers_list();

    $tz = airdefender_fpp_setting('TimeZone');
    if ($tz !== null && in_array($tz, $valid, true)) {
        return $tz;
    }

    // The system's own zone (FPP sets it from the same setting), in case FPP
    // can't be asked yet (the loop starting while Apache is still coming up).
    $tz = trim((string) @file_get_contents('/etc/timezone'));
    return in_array($tz, $valid, true) ? $tz : null;
}

$airdefenderTz = airdefender_fpp_timezone();
if ($airdefenderTz !== null) {
    date_default_timezone_set($airdefenderTz);
}
unset($airdefenderTz);
