#!/bin/bash
# FPP command "Air Defender: Start Test". fppd runs this as root;
# the test file is shared with the dashboard, which runs as fpp, so run as fpp.
CLI="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)/scripts/airdefender_test_cli.php"
if [ "$(id -u)" = "0" ] && id fpp >/dev/null 2>&1; then
    command -v runuser >/dev/null 2>&1 && exec runuser -u fpp -- php "${CLI}" start "${1:-60}"
    exec sudo -u fpp php "${CLI}" start "${1:-60}"
fi
exec php "${CLI}" start "${1:-60}"
