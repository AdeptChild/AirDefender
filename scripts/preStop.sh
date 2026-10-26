#!/bin/bash
# Air Defender: stop the detection loop before fppd stops. Its shutter
# overlays live in fppd, so they go away with it.

PLUGIN_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
echo "Stopping Air Defender detection loop"
# The watchdog first, so it does not start the loop again.
pkill -f "${PLUGIN_DIR}/scripts/airdefender_supervise.sh" 2>/dev/null
pkill -f "${PLUGIN_DIR}/scripts/airdefender_loop.php" 2>/dev/null
exit 0
