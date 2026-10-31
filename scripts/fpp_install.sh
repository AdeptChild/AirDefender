#!/bin/bash
# Air Defender - Copyright (C) 2026 G Hancox. Created by G Hancox, built with Claude.
# Licensed under the GNU GPL v2; see LICENSE.
set -e
# Air Defender install script. FPP runs this (as root) after a fresh install.
# (Updates run scripts/fpp_upgrade.sh instead.)
#
# Nothing to set up here. Straight after this, FPP asks fppd to load the
# plugin, which registers the Air Defender commands and starts the detection
# loop through callbacks.sh, so it runs under fppd and stops with it.

. ${FPPDIR}/scripts/common

# Only an FPP without live plugin loading (older than 10.0, which Air
# Defender does not list itself for) needs fppd restarted to pick it up.
if ! grep -q "FPPDPluginLifecycle" "${FPPDIR}/www/api/controllers/plugin.php" 2>/dev/null; then
    setSetting restartFlag 1
fi
