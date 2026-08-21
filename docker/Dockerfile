ARG PHP_VERSION=8.4
FROM php:${PHP_VERSION}-apache

# System dependencies
RUN apt-get update && apt-get install -y \
        libpng-dev \
        libjpeg-dev \
        libwebp-dev \
        libfreetype6-dev \
        libzip-dev \
        libxml2-dev \
        libxslt1-dev \
        libicu-dev \
        libonig-dev \
        libcurl4-openssl-dev \
        netcat-openbsd \
        default-mysql-client \
        curl \
        git \
        unzip \
    && rm -rf /var/lib/apt/lists/*

# PHP extensions required by Magento 2
RUN docker-php-ext-configure gd --with-jpeg --with-webp --with-freetype \
    && docker-php-ext-install -j$(nproc) \
        bcmath \
        ftp \
        gd \
        intl \
        mbstring \
        mysqli \
        opcache \
        pdo_mysql \
        posix \
        soap \
        sockets \
        xsl \
        xml \
        zip

# XDebug: installed but NOT enabled by default.
# playground.sh activates it on request via docker exec.
RUN pecl install xdebug && rm -rf /tmp/pear

# Enable Apache modules
RUN a2enmod rewrite headers

# Composer 2
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

ENV COMPOSER_ALLOW_SUPERUSER=1

WORKDIR /var/www/html

# Install Magento Open Source via the MageOS mirror — no repo.magento.com credentials needed.
# The mirror is a community-maintained, public copy of all Magento packages.
ARG MAGENTO_VERSION=2.4.8
RUN composer config --global audit.block-insecure false \
    && composer config --global allow-plugins true \
    && composer create-project \
        --repository-url=https://mirror.mage-os.org/ \
        --no-dev \
        --no-interaction \
        magento/project-community-edition:"${MAGENTO_VERSION}" \
        . \
    && sed -i 's|https://repo.magento.com/|https://mirror.mage-os.org/|g' composer.json

# Install sample data packages at build time so setup:install --use-sample-data at
# runtime (see entrypoint.sh) can import the data into the database without any
# network access. magento/module-sales-sample-data in particular matters here: this
# module validates existing ORDERS, so sample orders are the actual test fixture,
# not just nice-to-have catalog browsing.
# Using --no-update first, then a single composer update keeps the resolver fast.
RUN composer require --no-update --no-interaction \
        magento/module-bundle-sample-data \
        magento/module-catalog-sample-data \
        magento/module-catalog-rule-sample-data \
        magento/module-cms-sample-data \
        magento/module-configurable-sample-data \
        magento/module-customer-sample-data \
        magento/module-downloadable-sample-data \
        magento/module-grouped-product-sample-data \
        magento/module-msrp-sample-data \
        magento/module-offline-shipping-sample-data \
        magento/module-product-links-sample-data \
        magento/module-review-sample-data \
        magento/module-sales-rule-sample-data \
        magento/module-sales-sample-data \
        magento/module-swatches-sample-data \
        magento/module-tax-sample-data \
        magento/module-theme-sample-data \
        magento/module-widget-sample-data \
        magento/module-wishlist-sample-data \
        magento/sample-data-media \
    && composer update --no-dev --no-interaction

# EthanYehuda_CronJobManager — admin-UI cron job manager (timeline view, run/edit/
# remove individual cron_schedule entries). Useful here since Cron\AddressValidation
# is the actual test fixture trigger for this module; avoids having to back-date
# scheduled_at via raw SQL to force a run (see playground.sh's printed hint).
RUN composer require --no-update --no-interaction \
        ethanyehuda/magento2-cronjobmanager \
    && composer update --no-dev --no-interaction

# Fix: Calendar.php lines 114/115 — PHP 8.x "array offset on null" for AmPmMarkers.
# $localeData['calendar']['gregorian']['AmPmMarkers'] can be null for some locales;
# the null-coalescing fallback prevents the warning from being promoted to an exception
# in developer mode. PHP handles the string replacement reliably without sed escaping issues.
RUN php -r "\
\$f = 'vendor/magento/framework/View/Element/Html/Calendar.php'; \
\$c = file_get_contents(\$f); \
\$c = str_replace( \
    [\"['AmPmMarkers']['0'])\", \"['AmPmMarkers']['1'])\"], \
    [\"['AmPmMarkers']['0'] ?? '')\", \"['AmPmMarkers']['1'] ?? '')\"], \
    \$c \
); \
file_put_contents(\$f, \$c); \
echo 'Calendar.php patched.' . PHP_EOL; \
"

# Adminer — single-file DB UI, served alongside the shop at /adminer.php. Useful
# here in particular to inspect the custom parc_addressvalidation table directly.
# mysql-only build keeps it small; pinned version for reproducible builds.
RUN curl -sfL -o pub/adminer.php \
    https://github.com/vrana/adminer/releases/download/v5.5.1/adminer-5.5.1-mysql.php

# PHP dev configuration
COPY docker/php.ini /usr/local/etc/php/conf.d/magento-dev.ini

# Apache virtual host — DocumentRoot points to pub/ as required by Magento
COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf

# Permissions — only the directories Apache/PHP need to write to
RUN chown -R www-data:www-data \
        /var/www/html/var \
        /var/www/html/pub \
        /var/www/html/generated \
        /var/www/html/app/etc \
    && chmod +x /var/www/html/bin/magento

# Startup script
COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

EXPOSE 80

ENTRYPOINT ["/entrypoint.sh"]
CMD ["apache2-foreground"]
