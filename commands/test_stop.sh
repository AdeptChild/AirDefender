#!/bin/bash
# FPP command "Air Defender: Stop Test". fppd runs this as root;
# the test file is shared with the dashboard, which runs as fpp, so run as fpp.
CLI="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)/scripts/airdefender_test_cli.php"
if [ "$(id -u)" = "0" ] && id fpp >/dev/null 2>&1; then
    command -v runuser >/dev/null 2>&1 && exec runuser -u fpp -- php "${CLI}" stop
    exec sudo -u fpp php "${CLI}" stop
fi
exec php "${CLI}" stop
