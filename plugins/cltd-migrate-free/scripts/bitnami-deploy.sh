#!/usr/bin/env bash
#
# Pre-deploy helper for Bitnami (Lightsail) stacks where the plugin folder is
# owned by root:root and not writable during SFTP or build syncs.
# Usage:
#   ./scripts/bitnami-deploy.sh
#   ./scripts/bitnami-deploy.sh /custom/path/to/wp-content/plugins/cltd-migrate-free
#
# The script accepts the following optional environment overrides:
#   PLUGIN_OWNER (default: bitnami)
#   PLUGIN_GROUP (default: daemon)
#   PLUGIN_PERMS (default: 775)

set -euo pipefail

PLUGIN_PATH="${1:-/opt/bitnami/wordpress/wp-content/plugins/cltd-migrate-free}"
PLUGIN_OWNER="${PLUGIN_OWNER:-bitnami}"
PLUGIN_GROUP="${PLUGIN_GROUP:-daemon}"
PLUGIN_PERMS="${PLUGIN_PERMS:-775}"
NEEDED_DIRS=(
    ""
    "assets"
    "assets/css"
    "assets/js"
)

if [[ ! -d "${PLUGIN_PATH}" ]]; then
    echo ">> Creating plugin directory at ${PLUGIN_PATH}"
    sudo mkdir -p "${PLUGIN_PATH}"
fi

echo ">> Ensuring ownership ${PLUGIN_OWNER}:${PLUGIN_GROUP} on ${PLUGIN_PATH}"
sudo chown -R "${PLUGIN_OWNER}:${PLUGIN_GROUP}" "${PLUGIN_PATH}"

echo ">> Applying ${PLUGIN_PERMS} permissions recursively to ${PLUGIN_PATH}"
sudo chmod -R "${PLUGIN_PERMS}" "${PLUGIN_PATH}"

for dir in "${NEEDED_DIRS[@]}"; do
    target="${PLUGIN_PATH}/${dir}"
    [[ -d "${target}" ]] || sudo mkdir -p "${target}"
    sudo chown -R "${PLUGIN_OWNER}:${PLUGIN_GROUP}" "${target}"
    sudo chmod -R "${PLUGIN_PERMS}" "${target}"
done

echo "✅ Bitnami permissions updated. Safe to build/sync CLTD Migrate Free."
