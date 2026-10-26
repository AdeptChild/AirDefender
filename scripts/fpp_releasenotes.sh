#!/bin/bash
# Air Defender release notes. FPP runs this (as the fpp user) when Release
# Notes is clicked in Plugin Manager, and shows what it prints as plain text.
PLUGIN_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
if [ -s "${PLUGIN_DIR}/CHANGELOG.md" ]; then
    # Plain text: drop the markdown heading marks and bold, keep the rest.
    sed -e 's/^#\{1,6\} //' -e 's/\*\*//g' "${PLUGIN_DIR}/CHANGELOG.md"
    exit 0
fi
echo "No release notes are available for this plugin."
exit 0
