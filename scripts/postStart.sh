#!/bin/bash
# Air Defender: start the detection loop after fppd starts.
# FPP runs this synchronously as root, so the loop is detached into the
# background and run as the fpp user (the dashboard, which runs as fpp,
# shares its files). It runs under a small watchdog that starts it again
# if it ever stops on its own.

PLUGIN_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
SUPERVISOR="${PLUGIN_DIR}/scripts/airdefender_supervise.sh"
LOOP="${PLUGIN_DIR}/scripts/airdefender_loop.php"
LOG="/home/fpp/media/logs/airdefender.log"

# Stop copies left over from before: the watchdog first, so it cannot restart the loop.
pkill -f "${SUPERVISOR}" 2>/dev/null
pkill -f "${LOOP}" 2>/dev/null
sleep 0.5

if [ "$(id -u)" = "0" ] && id fpp >/dev/null 2>&1; then
    RUN_AS="runuser -u fpp --"
    command -v runuser >/dev/null 2>&1 || RUN_AS="sudo -u fpp"
else
    RUN_AS=""
fi

echo "Starting Air Defender detection loop"
nohup setsid ${RUN_AS} bash "${SUPERVISOR}" >>"${LOG}" 2>/dev/null </dev/null &
