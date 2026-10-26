#!/bin/bash
# Air Defender: start the detection loop after fppd starts.
# FPP runs this synchronously as root, so the loop is detached into the
# background and run as the fpp user (the dashboard, which runs as fpp,
# shares its files).

PLUGIN_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
LOOP="${PLUGIN_DIR}/scripts/airdefender_loop.php"
LOG="/home/fpp/media/logs/airdefender.log"

# Stop a copy left over from before (the loop also refuses to run twice).
pkill -f "${LOOP}" 2>/dev/null

if [ "$(id -u)" = "0" ] && id fpp >/dev/null 2>&1; then
    RUN_AS="runuser -u fpp --"
    command -v runuser >/dev/null 2>&1 || RUN_AS="sudo -u fpp"
else
    RUN_AS=""
fi

echo "Starting Air Defender detection loop"
nohup setsid ${RUN_AS} php "${LOOP}" >>"${LOG}" 2>&1 </dev/null &
