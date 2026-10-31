#!/bin/bash
# Air Defender - Copyright (C) 2026 G Hancox. Created by G Hancox, built with Claude.
# Licensed under the GNU GPL v2; see LICENSE.
#
# fppd's plugin callbacks. Air Defender uses only the lifecycle ones, so its
# detection loop lives and dies with fppd:
#   startup  - fppd started, or loaded the plugin after an install or update
#   shutdown - fppd is stopping, or unloading the plugin to uninstall or update
# fppd waits for these, so both return straight away: the loop is started in
# the background (scripts/airdefender_start.sh).

DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

case "$1" in
    --list)
        echo "lifecycle"
        ;;
    --type)
        if [ "$2" = "lifecycle" ]; then
            case "$3" in
                "startup")  bash "${DIR}/scripts/airdefender_start.sh" ;;
                "shutdown") bash "${DIR}/scripts/airdefender_stop.sh" ;;
            esac
        fi
        ;;
esac
exit 0
