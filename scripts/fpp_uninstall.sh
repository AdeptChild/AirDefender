#!/bin/bash
# Air Defender uninstall script. FPP runs this (as root) just before it
# deletes the plugin's folder.

PLUGIN_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

# Stop the detection loop; otherwise it keeps running from memory after its
# files are gone.
pkill -f "${PLUGIN_DIR}/scripts/airdefender_supervise.sh" 2>/dev/null
pkill -f "${PLUGIN_DIR}/scripts/airdefender_loop.php" 2>/dev/null

# If safety mode was on, hand any shutter channels the loop was holding back
# to the sequence now, rather than leaving them closed until fppd restarts.
if [ -f "${PLUGIN_DIR}/state.json" ]; then
    php -r '
        $s = json_decode((string) @file_get_contents($argv[1]), true);
        foreach (array_keys($s["appliedChannels"] ?? []) as $ch) { echo (int) $ch, "\n"; }
    ' "${PLUGIN_DIR}/state.json" 2>/dev/null | while read -r CH; do
        [ -n "${CH}" ] && curl -s -m 3 -X PUT -H 'Content-Type: application/json' \
            -d '{"delete":true}' "http://127.0.0.1/api/overlays/range/${CH}" >/dev/null
    done
fi

# fppd reads commands/descriptions.json only at startup, so the Air Defender
# commands stay listed until it restarts. Ask for one.
. ${FPPDIR}/scripts/common
setSetting restartFlag 1
