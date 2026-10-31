#!/bin/bash
# Air Defender - Copyright (C) 2026 G Hancox. Created by G Hancox, built with Claude.
# Licensed under the GNU GPL v2; see LICENSE.
# Air Defender upgrade script. FPP runs this (as root) after pulling a new
# version from Plugin Manager.
#
# The detection loop is a long-running process, so it has to be restarted to
# run the new code. FPP 10.3 and later do that themselves straight after this
# script: they ask fppd to unload the plugin and load it again, which stops
# the loop and starts the new one through callbacks.sh. Earlier FPP 10
# versions don't, so on those this script asks fppd for the same unload and
# load. Either way fppd starts the loop, never this script, so it runs under
# fppd and not under the web server.

FPPDIR="${FPPDIR:-/opt/fpp}"
API="http://127.0.0.1/api/fppd/plugin/AirDefender"

if grep -q "PluginReloadAfterUpgrade" "${FPPDIR}/www/api/controllers/plugin.php" 2>/dev/null; then
    echo "FPP will reload Air Defender with the updated code"
    exit 0
fi

if ! pgrep -x fppd >/dev/null 2>&1; then
    echo "fppd is not running; Air Defender's updated code starts with it"
    exit 0
fi

ok() { curl -s -m 30 -X POST "$1" 2>/dev/null | grep -q '"Status" *: *"OK"'; }
if ok "${API}/unload" && ok "${API}/load"; then
    echo "Reloaded Air Defender in fppd with the updated code"
else
    echo "fppd could not reload Air Defender; restart FPPD to finish the update"
    ( set +u; source "${FPPDIR}/scripts/common" && setSetting restartFlag 1 ) || true
fi
exit 0
