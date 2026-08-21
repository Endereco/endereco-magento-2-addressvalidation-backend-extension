#!/usr/bin/env bash

# Playground for the Parc_AddressValidation module (this directory is volume-mounted
# into the Magento container — PHP changes are visible immediately, no rebuild needed).
# Sample data is deployed (incl. sample ORDERS) since this module validates existing
# orders via cron — without sample orders there's nothing for it to act on.
#
# Supported Magento 2 versions — restricted to lines still under active Adobe support
# (see https://experienceleague.adobe.com/en/docs/commerce-operations/release/planning/lifecycle-policy).
# 2.4.5 and earlier are past regular support and intentionally excluded.
# Stack: mariadb (official) + opensearchproject/opensearch + custom image
# built from docker/Dockerfile (Magento via MageOS mirror, no credentials needed).
declare -a versions=(
    # 2.4.6 series — regular support ends 2026-08-11 (imminent!)
    "2.4.6"
    "2.4.6-p1" "2.4.6-p2" "2.4.6-p3" "2.4.6-p4" "2.4.6-p5"
    "2.4.6-p6" "2.4.6-p7" "2.4.6-p8" "2.4.6-p9" "2.4.6-p10"
    "2.4.6-p11" "2.4.6-p12" "2.4.6-p13" "2.4.6-p14" "2.4.6-p15"
    # 2.4.7 series — active, support ends 2027-04-09
    "2.4.7"
    "2.4.7-p1" "2.4.7-p2" "2.4.7-p3" "2.4.7-p4" "2.4.7-p5"
    "2.4.7-p6" "2.4.7-p7" "2.4.7-p8" "2.4.7-p9" "2.4.7-p10"
    # 2.4.8 series — active, support ends 2028-04-11
    "2.4.8"
    "2.4.8-p1" "2.4.8-p2" "2.4.8-p3" "2.4.8-p4" "2.4.8-p5"
    # 2.4.9 series — active (GA 2026-05-12), no patch release yet
    "2.4.9"
)

# Helper: check if element is in array
containsElement() {
    local match="$1"
    shift
    for e; do [[ "$e" == "$match" ]] && return 0; done
    return 1
}

echo "Available Magento 2 versions:"
echo "  2.4.6 series (PHP 8.1/8.2): 2.4.6  2.4.6-p1 … 2.4.6-p15   [EOL 2026-08-11 !]"
echo "  2.4.7 series (PHP 8.1/8.2/8.3): 2.4.7  2.4.7-p1 … 2.4.7-p10"
echo "  2.4.8 series (PHP 8.2/8.3/8.4): 2.4.8  2.4.8-p1 … 2.4.8-p5"
echo "  2.4.9 series (PHP 8.3/8.4/8.5): 2.4.9  (no patch release yet)"
echo ""

while true; do
    read -p "Enter the Magento 2 version you want to use: " version
    if containsElement "$version" "${versions[@]}"; then
        break
    fi
    echo "Invalid version. Please choose a version from the list above."
done

read -p "Enable XDebug for debugging? (y/N): " enable_xdebug
read -p "Force image rebuild? (y/N): " force_rebuild

# Derive the minor version (strips -p1, -p2, … suffix) to map PHP/DB/search engine.
# Example: "2.4.7-p2" → base_version="2.4.7"
base_version="${version%%-*}"

# Version-specific settings (keyed on minor version, not patch level).
# PHP constraints verified against composer's actual "require" metadata on
# mirror.mage-os.org (magento/product-community-edition).
# MariaDB versions verified against the ACTUAL SqlVersionProvider regex patterns
# shipped in app/etc/di.xml of magento/magento2-base for each version — not docs,
# which advertise ranges (e.g. "11.4 or 11.8" for 2.4.8) the installer's own
# regex whitelist doesn't actually accept yet (2.4.8 rejects 11.8 at setup:install).
case "$base_version" in
    2.4.6) php_versions=("8.1" "8.2");        php_default="8.2"; mariadb_image="mariadb:10.6"; opensearch_image="opensearchproject/opensearch:2" ;;
    2.4.7) php_versions=("8.1" "8.2" "8.3");  php_default="8.3"; mariadb_image="mariadb:10.6"; opensearch_image="opensearchproject/opensearch:2" ;;
    2.4.8) php_versions=("8.2" "8.3" "8.4");  php_default="8.4"; mariadb_image="mariadb:11.4"; opensearch_image="opensearchproject/opensearch:2" ;;
    2.4.9) php_versions=("8.3" "8.4" "8.5");  php_default="8.4"; mariadb_image="mariadb:11.8"; opensearch_image="opensearchproject/opensearch:3" ;;
esac

# PHP version selection — skip prompt when only one version is supported
if [ "${#php_versions[@]}" -eq 1 ]; then
    php_version="${php_versions[0]}"
else
    echo "Supported PHP versions for Magento ${version}: ${php_versions[*]}"
    read -p "PHP version [${php_default}]: " php_version
    if [ -z "$php_version" ]; then
        php_version="$php_default"
    elif ! containsElement "$php_version" "${php_versions[@]}"; then
        echo "Invalid PHP version, using default ${php_default}."
        php_version="$php_default"
    fi
fi

# Image tag includes the PHP version so that e.g. 2.4.8-php8.3 and 2.4.8-php8.4
# are stored as separate cached images and don't overwrite each other.
image_tag="parc-av-playground:${version}-php${php_version}"
container_magento="parc-av-${version}"
container_db="parc-av-${version}-db"
container_search="parc-av-${version}-search"
network="parc-av-${version}-net"

# The directory this script lives in IS the module — it gets volume-mounted
# into the container at app/code/Parc/AddressValidation.
script_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

# Admin credentials
admin_user="admin"
admin_pass="Admin1234!"
admin_email="admin@parc-playground.de"

# ─── Port conflict detection ───────────────────────────────────────────────────
host_port=80
blocking_container=$(docker ps --format "{{.Names}}\t{{.Ports}}" \
    | grep "0\.0\.0\.0:${host_port}->" | awk '{print $1}' | head -1)
if [ -n "$blocking_container" ] || lsof -iTCP:"${host_port}" -sTCP:LISTEN -t > /dev/null 2>&1; then
    echo ""
    if [ -n "$blocking_container" ]; then
        echo "Port ${host_port} is already used by Docker container: ${blocking_container}"
    else
        echo "Port ${host_port} is already in use by another process."
    fi
    echo "  1) Stop ${blocking_container:-the blocking process} and use port ${host_port}"
    echo "  2) Use a different port"
    while true; do
        read -p "Choice (1/2): " port_choice
        [[ "$port_choice" == "1" || "$port_choice" == "2" ]] && break
        echo "Please enter 1 or 2."
    done
    if [[ "$port_choice" == "1" ]]; then
        if [ -n "$blocking_container" ]; then
            echo "Stopping ${blocking_container} ..."
            docker rm -f "$blocking_container"
            blocking_db="${blocking_container}-db"
            blocking_search="${blocking_container}-search"
            if [ "$(docker ps -aq -f name="^${blocking_db}$")" ]; then
                docker rm -f "$blocking_db"
            fi
            if [ "$(docker ps -aq -f name="^${blocking_search}$")" ]; then
                docker rm -f "$blocking_search"
            fi
            blocking_net="${blocking_container}-net"
            if docker network inspect "$blocking_net" > /dev/null 2>&1; then
                docker network rm "$blocking_net"
            fi
        else
            echo "Cannot automatically stop a non-Docker process."
            echo "Please free port ${host_port} manually and restart."
            exit 1
        fi
    else
        while true; do
            read -p "Enter port to use (81-89 or 8080-8089): " host_port
            if [[ "$host_port" =~ ^8[1-9]$ ]] || \
               [[ "$host_port" =~ ^808[0-9]$ ]]; then
                break
            fi
            echo "Invalid port. Please enter a port in range 81-89 or 8080-8089."
        done
    fi
fi

if [ "$host_port" -eq 80 ]; then
    base_url="http://localhost/"
else
    base_url="http://localhost:${host_port}/"
fi

echo ""
echo "Preparing Magento ${version} (PHP ${php_version}) ..."
if [[ "$enable_xdebug" =~ ^[Yy]$ ]]; then
    echo "XDebug will be configured after startup."
fi

# ─── Remove existing containers ───────────────────────────────────────────────
for name in "$container_magento" "$container_db" "$container_search"; do
    if [ "$(docker ps -aq -f name="^${name}$")" ]; then
        echo "Removing existing container: ${name}"
        docker rm -f "$name"
    fi
done

# Remove existing network
if docker network inspect "$network" > /dev/null 2>&1; then
    docker network rm "$network"
fi

# ─── Build Magento image (cached by default; rebuild on request) ───────────────
if [[ "$force_rebuild" =~ ^[Yy]$ ]] && docker image inspect "$image_tag" > /dev/null 2>&1; then
    echo "Removing cached image ${image_tag} for rebuild ..."
    blocking=$(docker ps -aq --filter "ancestor=${image_tag}")
    if [ -n "$blocking" ]; then
        echo "Removing leftover containers using the image ..."
        docker rm -f $blocking
    fi
    docker rmi "$image_tag"
fi

if docker image inspect "$image_tag" > /dev/null 2>&1; then
    echo "Using cached image ${image_tag}."
else
    echo "Building image ${image_tag} — installs Magento via MageOS mirror."
    echo "This takes 10–20 minutes on first run. Subsequent runs use the cached image."
    if ! docker build \
        --build-arg PHP_VERSION="$php_version" \
        --build-arg MAGENTO_VERSION="$version" \
        -t "$image_tag" \
        -f "${script_dir}/docker/Dockerfile" \
        "$script_dir"; then
        echo "Image build failed. See errors above."
        exit 1
    fi
fi

# ─── Create network ────────────────────────────────────────────────────────────
docker network create "$network"

# ─── MariaDB ──────────────────────────────────────────────────────────────────
echo "Starting MariaDB ..."
docker run -d \
    --name "$container_db" \
    --network "$network" \
    -e MARIADB_ROOT_PASSWORD=root \
    -e MARIADB_DATABASE=magento \
    -e MARIADB_USER=magento \
    -e MARIADB_PASSWORD=magento \
    "${mariadb_image}"

echo "Waiting for MariaDB ..."
# MariaDB 11+ images ship only "mariadb-admin"; older images (10.11) still have "mysqladmin".
# Try both so the check works regardless of which image is in use.
until docker exec "$container_db" sh -c \
    'mariadb-admin ping -uroot -proot --silent 2>/dev/null || mysqladmin ping -uroot -proot --silent 2>/dev/null'; do
    echo -n "."
    sleep 2
done
echo ""

# ─── OpenSearch ───────────────────────────────────────────────────────────────
echo "Starting OpenSearch ..."
docker run -d \
    --name "$container_search" \
    --network "$network" \
    -e "discovery.type=single-node" \
    -e "DISABLE_SECURITY_PLUGIN=true" \
    -e "OPENSEARCH_INITIAL_ADMIN_PASSWORD=Admin_pass1!" \
    -e "OPENSEARCH_JAVA_OPTS=-Xms512m -Xmx512m" \
    "${opensearch_image}"

echo "Waiting for OpenSearch ..."
max_wait=120
wait_count=0
until docker exec "$container_search" curl -s http://localhost:9200 > /dev/null 2>&1; do
    if [ $wait_count -ge $max_wait ]; then
        echo ""
        echo "OpenSearch did not become ready in time."
        echo "Check logs with: docker logs ${container_search}"
        exit 1
    fi
    echo -n "."
    sleep 3
    (( wait_count += 3 ))
done
echo ""

# ─── Magento ──────────────────────────────────────────────────────────────────
echo "Starting Magento ${version} ..."
echo "The entrypoint runs setup:install on first boot (incl. sample data/orders) — this takes several minutes."
echo ""

docker_options=(
    -d
    --name "$container_magento"
    --network "$network"
    -e MYSQL_HOST="$container_db"
    -e MYSQL_DB=magento
    -e MYSQL_USER=magento
    -e MYSQL_PASS=magento
    -e OPENSEARCH_HOST="$container_search"
    -e OPENSEARCH_PORT=9200
    -e BASE_URL="${base_url}"
    -e ADMIN_USER="${admin_user}"
    -e ADMIN_PASS="${admin_pass}"
    -e ADMIN_EMAIL="${admin_email}"
    -v "${script_dir}:/var/www/html/app/code/Parc/AddressValidation"
    # Shadow local dev-tooling dirs out of the bind mount with anonymous volumes.
    # setup:di:compile recursively scans every *.php under app/code with zero
    # exclusions of its own, so anything non-Magento nested in here (composer's
    # own vendor/, PHPUnit fixtures under Test/, the full nested shop checkouts
    # fetch_shops.sh writes under shops/) gets scanned as if it were module code
    # and blows up on the first file that assumes an unavailable autoloader
    # (e.g. a PHPUnit TestCase). None of these three are needed inside the
    # container — they only matter for host-side tooling — so hiding them here
    # is safe; each mount must come after the parent mount above to shadow it.
    -v "/var/www/html/app/code/Parc/AddressValidation/vendor"
    -v "/var/www/html/app/code/Parc/AddressValidation/Test"
    -v "/var/www/html/app/code/Parc/AddressValidation/shops"
    -p "${host_port}:80"
)

# On Linux without Docker Desktop, host.docker.internal must be added explicitly.
if [[ "$enable_xdebug" =~ ^[Yy]$ ]] && [[ "$(uname)" != "Darwin" ]]; then
    docker_options+=(--add-host host.docker.internal=host-gateway)
fi

if ! docker run "${docker_options[@]}" "$image_tag"; then
    echo "Failed to start Magento container. See errors above."
    exit 1
fi

# ─── Wait for Magento ─────────────────────────────────────────────────────────
# The entrypoint handles setup:install before starting Apache.
# Apache responds only after setup is complete, so HTTP 200 means full readiness.
echo "Waiting for Magento (setup:install runs inside the container — check progress with: docker logs -f ${container_magento}) ..."
max_wait=1800
wait_count=0
until curl -s --max-time 5 -o /dev/null -w "%{http_code}" "${base_url}" 2>/dev/null | grep -qE "^(200|301|302)"; do
    if [ $wait_count -ge $max_wait ]; then
        echo ""
        echo "Magento did not become ready in time."
        echo "Check logs with: docker logs ${container_magento}"
        exit 1
    fi
    if (( wait_count > 0 && wait_count % 60 == 0 )); then
        echo " (${wait_count}s elapsed)"
    fi
    echo -n "."
    sleep 5
    (( wait_count += 5 ))
done
echo ""

# ─── XDebug ───────────────────────────────────────────────────────────────────
if [[ "$enable_xdebug" =~ ^[Yy]$ ]]; then
    echo "Configuring XDebug ..."
    docker exec -u root "$container_magento" bash -c '
cat > /usr/local/etc/php/conf.d/99-xdebug.ini << EOF
zend_extension=xdebug
xdebug.mode=debug
xdebug.start_with_request=yes
xdebug.client_host=host.docker.internal
xdebug.client_port=9003
xdebug.log=/tmp/xdebug.log
EOF
apache2ctl graceful
echo "XDebug enabled and Apache reloaded."
'
fi

echo ""
echo "Magento ${version} is running."
echo ""
echo "  Shop:    ${base_url}"
echo "  Admin:   ${base_url}admin/"
echo "  Adminer: ${base_url}adminer.php?mysql=${container_db}&username=magento&db=magento (password: magento)"
if [[ "$enable_xdebug" =~ ^[Yy]$ ]]; then
echo "  XDebug:  port 9003 (IDE listen mode, trigger: start_with_request)"
fi
echo ""
echo "Default admin credentials:"
echo "  Username: ${admin_user}"
echo "  Password: ${admin_pass}"
echo "  E-Mail:   ${admin_email}"
echo ""
echo "Parc_AddressValidation module mounted at: /var/www/html/app/code/Parc/AddressValidation/ (this repo, live)"
echo "Changes to PHP files are visible immediately (opcache disabled, developer mode)."
echo ""
echo "'Enabled' is already set to Yes (Plugin/CronConfigPlugin.php strips the cron job out of"
echo "the schedule entirely otherwise — cron:run would silently do nothing, forever)."
echo ""
echo "Remaining module setup (Admin → Stores → Configuration → endereco Backend Address Validation → Address Validation):"
echo "  1. Set your Endereco API key (parc_addressvalidation/general/api_key)"
echo "  2. Pick order statuses to validate, e.g. 'processing' — sample data ships orders in that status"
echo "  3. Create + select a dedicated 'Validation Hold Status' (README recommends not reusing a built-in one)"
echo ""
echo "The cron job (Cron\\AddressValidation, group 'parcnetwork', default */10 * * * *) won't fire on its"
echo "own inside this short-lived container. Trigger it on demand instead:"
echo "  docker exec ${container_magento} php /var/www/html/bin/magento cron:run"
echo "First cron:run only SCHEDULES the job (scheduled_at up to schedule_ahead_for minutes in the"
echo "future); it won't execute until that time or a later cron:run passes it. To force it to run"
echo "right now instead of waiting, back-date the pending entry, then run cron:run again:"
echo "  docker exec ${container_db} mariadb -umagento -pmagento magento \\"
echo "    -e \"UPDATE cron_schedule SET scheduled_at = NOW() WHERE job_code = 'parc_addressvalidation' AND status = 'pending';\""
echo ""
echo "Useful commands:"
echo "  docker logs -f ${container_magento}"
echo "  docker exec -it ${container_magento} bash"
echo "  docker exec ${container_magento} php /var/www/html/bin/magento cache:flush"
echo ""
echo "To stop:   docker rm -f ${container_magento} ${container_db} ${container_search} && docker network rm ${network}"
