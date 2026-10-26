#!/bin/bash
# Air Defender install script. FPP runs this (as root) after a fresh install.
# (Updates run scripts/fpp_upgrade.sh instead.)

. ${FPPDIR}/scripts/common

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

# Start detecting right away if fppd is running, rather than waiting for its
# next restart. postStart.sh starts it from then on.
if pgrep -x fppd >/dev/null 2>&1; then
    bash "${SCRIPT_DIR}/postStart.sh"
fi

# fppd reads commands/descriptions.json only when it starts, so ask for a
# restart to make the Air Defender commands show up in playlists, schedules
# and command presets.
setSetting restartFlag 1
