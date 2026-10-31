#!/bin/bash
# Air Defender - Copyright (C) 2026 G Hancox. Created by G Hancox, built with Claude.
# Licensed under the GNU GPL v2; see LICENSE.
# Air Defender upgrade script.
#
# FPP runs this (as root) after pulling a new version from Plugin Manager.
# The detection loop is a long-running background process, so without this
# it would keep running the old code until fppd next restarted. FPP's own
# in-place plugin reload only covers native plugin code, not this loop.
#
# Restart the loop now if fppd is running; if it isn't, postStart.sh starts
# the new code when fppd comes up.

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

if pgrep -x fppd >/dev/null 2>&1; then
    echo "Restarting the Air Defender detection loop with the updated code"
    bash "${SCRIPT_DIR}/postStart.sh"
else
    echo "fppd is not running; the Air Defender loop will start with it"
fi

# fppd reads commands/descriptions.json only when it starts, so ask for a
# restart in case the Air Defender commands changed.
( set +u; source "${FPPDIR:-/opt/fpp}/scripts/common" && setSetting restartFlag 1 ) || true
exit 0
