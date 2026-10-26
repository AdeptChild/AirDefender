#!/bin/bash
# Air Defender watchdog. postStart.sh starts this; it runs the detection loop
# and starts it again if it ever stops on its own (a PHP error, say), so a
# crash mid-show does not leave the heads unprotected until fppd restarts.
# preStop.sh stops this first, then the loop.

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
LOOP="${SCRIPT_DIR}/airdefender_loop.php"
LOG="/home/fpp/media/logs/airdefender.log"

note() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1" >> "${LOG}"
}

quick=0
while true; do
    started=$(date +%s)
    # If the log cannot be written (wrong owner, full disk), still run the
    # loop: protecting the heads matters more than its error output.
    if { : >>"${LOG}"; } 2>/dev/null; then
        php "${LOOP}" 2>>"${LOG}"
    else
        php "${LOOP}" 2>/dev/null
    fi
    code=$?

    if [ "${code}" -eq 3 ]; then
        # Another copy of the loop is already running: nothing to watch.
        exit 0
    fi

    ran=$(( $(date +%s) - started ))
    if [ "${ran}" -lt 30 ]; then
        quick=$((quick + 1))
    else
        quick=0
    fi

    # Back off if it keeps stopping straight away, so a broken file cannot spin.
    if [ "${quick}" -ge 5 ]; then
        wait=60
    else
        wait=5
    fi
    note "Detection loop stopped unexpectedly (exit ${code} after ${ran} s); restarting it in ${wait} s."
    sleep "${wait}"
done
