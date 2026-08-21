#!/bin/bash
set -e

MAGENTO_DIR="/var/www/html"
BIN_MAGENTO="php ${MAGENTO_DIR}/bin/magento"

# Wait for MariaDB
echo "Waiting for MariaDB at ${MYSQL_HOST:-db}:3306 ..."
until nc -z "${MYSQL_HOST:-db}" 3306; do
    sleep 1
done
echo "MariaDB is ready."

# Wait for OpenSearch
echo "Waiting for OpenSearch at ${OPENSEARCH_HOST:-opensearch}:9200 ..."
until nc -z "${OPENSEARCH_HOST:-opensearch}" 9200; do
    sleep 2
done
echo "OpenSearch is ready."

# Run setup:install on first boot (env.php is created by setup:install)
if [ ! -f "${MAGENTO_DIR}/app/etc/env.php" ]; then
    echo "Running Magento setup:install ..."

    $BIN_MAGENTO setup:install \
        --base-url="${BASE_URL:-http://localhost/}" \
        --db-host="${MYSQL_HOST:-db}" \
        --db-name="${MYSQL_DB:-magento}" \
        --db-user="${MYSQL_USER:-magento}" \
        --db-password="${MYSQL_PASS:-magento}" \
        --admin-firstname="Parc" \
        --admin-lastname="Playground" \
        --admin-email="${ADMIN_EMAIL:-admin@parc-playground.de}" \
        --admin-user="${ADMIN_USER:-admin}" \
        --admin-password="${ADMIN_PASS:-Admin1234!}" \
        --language=de_DE \
        --currency=EUR \
        --timezone=Europe/Berlin \
        --use-rewrites=1 \
        --backend-frontname=admin \
        --search-engine=opensearch \
        --opensearch-host="${OPENSEARCH_HOST:-opensearch}" \
        --opensearch-port="${OPENSEARCH_PORT:-9200}" \
        --use-sample-data

    echo "setup:install complete."
fi

# Switch to developer mode so file changes are picked up immediately
$BIN_MAGENTO deploy:mode:set developer --skip-compilation 2>/dev/null || true

# Disable 2FA for local development — the modules exist but have no value in a playground
$BIN_MAGENTO module:disable Magento_TwoFactorAuth Magento_AdminAdobeImsTwoFactorAuth 2>/dev/null || true

# Disable Advanced Reporting — it tries to establish an external connection to Adobe
# analytics servers on every page load, which is pointless and slow in a playground
$BIN_MAGENTO config:set analytics/subscription/enabled 0 2>/dev/null || true

# Germany defaults — weight in kg, week starts on Monday (ISO 8601), weekend Sat+Sun
# setup:install already sets language=de_DE, currency=EUR, timezone=Europe/Berlin
$BIN_MAGENTO config:set general/country/default    DE    2>/dev/null || true
$BIN_MAGENTO config:set general/locale/weight_unit kgs   2>/dev/null || true
$BIN_MAGENTO config:set general/locale/firstday    1     2>/dev/null || true  # 0=Sun 1=Mon
$BIN_MAGENTO config:set general/locale/weekend     6,0   2>/dev/null || true  # 6=Sat 0=Sun

# Enable the Parc_AddressValidation module if it is volume-mounted into app/code/
MODULE_DIR="${MAGENTO_DIR}/app/code/Parc/AddressValidation"
if [ -d "$MODULE_DIR" ] && [ -f "${MODULE_DIR}/registration.php" ]; then
    echo "Enabling Parc_AddressValidation ..."
    $BIN_MAGENTO module:enable Parc_AddressValidation 2>/dev/null || true

    # Plugin/CronConfigPlugin.php strips the cron job out of the schedule entirely
    # unless this is set — without it, cron:run silently does nothing, forever.
    # API key / order statuses are still left for the developer to configure.
    $BIN_MAGENTO config:set parc_addressvalidation/general/enable 1 2>/dev/null || true
fi

# Apply all pending schema/data upgrades in one pass:
# covers sample data, 2FA disable, and Parc_AddressValidation enable/install
$BIN_MAGENTO setup:upgrade

# Pre-generate interceptors/factories/proxies for the current codebase. generated/
# is not persisted across container recreations, so without this, developer mode
# generates each class lazily on its first request — under concurrent requests
# (asset loads, admin AJAX, an open grid, ...) that first generation can race and
# throw a transient "Class ... does not exist" until the class exists on disk.
# Doesn't cover classes newly introduced by editing the bind-mounted module code
# after this point (still generated on-demand, same as before) — only removes the
# race for everything present at container start.
echo "Compiling DI configuration (pre-generates interceptors/factories to avoid first-request generation races) ..."
$BIN_MAGENTO setup:di:compile

$BIN_MAGENTO cache:flush

# Fix permissions on directories that Apache/PHP need to write to.
# Do NOT chown app/code — it is volume-mounted from the host.
chown -R www-data:www-data \
    "${MAGENTO_DIR}/var" \
    "${MAGENTO_DIR}/pub/static" \
    "${MAGENTO_DIR}/pub/media" \
    "${MAGENTO_DIR}/generated" \
    "${MAGENTO_DIR}/app/etc" 2>/dev/null || true

echo "Magento is ready."
exec "$@"
