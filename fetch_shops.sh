#!/usr/bin/env bash

# Downloads minimal Magento installations into shops/ so that phpstan can load
# Magento's autoloader and resolve all framework and module class types.
#
# Each shops/<minor>/ directory contains a full composer project (vendor/ included)
# but no database or web server setup. phpstan.*.neon uses vendor/autoload.php
# from these directories as bootstrapFiles.
#
# Usage:
#   ./fetch_shops.sh           – install / refresh all minor versions
#   ./fetch_shops.sh 2.4.7     – install / refresh a single minor version
#
# To force a reinstall, remove the directory first:
#   rm -rf shops/2.4.7 && ./fetch_shops.sh 2.4.7

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
SHOPS_DIR="${SCRIPT_DIR}/shops"
MIRROR="https://mirror.mage-os.org/"

# Latest release per minor version — restricted to lines still under active
# Adobe support (see playground.sh for the lifecycle research this matches).
# Update when new patches are released.
declare -A LATEST=(
    ["2.4.6"]="2.4.6-p15"
    ["2.4.7"]="2.4.7-p10"
    ["2.4.8"]="2.4.8-p5"
    ["2.4.9"]="2.4.9"
)

# Ordered list so output is deterministic.
declare -a MINORS=("2.4.6" "2.4.7" "2.4.8" "2.4.9")

install_shop() {
    local minor="$1"
    local version="${LATEST[$minor]}"
    local target="${SHOPS_DIR}/${minor}"

    echo ""
    echo "─── shops/${minor} (${version}) ──────────────────────────────────────"

    if [ -d "${target}/vendor" ]; then
        echo "Already installed — skipping."
        echo "To reinstall: rm -rf ${target} && ./fetch_shops.sh ${minor}"
        return
    fi

    # Remove any partial install (e.g. composer.json present but no vendor/)
    if [ -d "${target}" ]; then
        echo "Removing incomplete installation at ${target} ..."
        rm -rf "${target}"
    fi

    mkdir -p "$SHOPS_DIR"

    echo "Running: composer create-project magento/project-community-edition=${version}"
    echo "Source:  ${MIRROR}"
    echo "Target:  ${target}"
    echo ""

    composer create-project \
        --no-scripts \
        --no-interaction \
        --prefer-dist \
        --no-dev \
        --ignore-platform-reqs \
        --repository-url="${MIRROR}" \
        "magento/project-community-edition=${version}" \
        "${target}"

    echo ""
    echo "shops/${minor} ready."
}

if [ $# -eq 1 ]; then
    minor="$1"
    if [ -z "${LATEST[$minor]+x}" ]; then
        echo "Unknown minor version: ${minor}"
        echo "Available: ${MINORS[*]}"
        exit 1
    fi
    install_shop "$minor"
else
    for minor in "${MINORS[@]}"; do
        install_shop "$minor"
    done
fi

echo ""
echo "All done. Run phpstan with the matching config:"
echo "  vendor/bin/phpstan analyse --configuration=phpstan.2.4.8.neon --no-progress"
