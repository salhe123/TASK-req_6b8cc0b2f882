FROM php:8.1-apache

# System dependencies. No `npm`/`wget` fetches — the build runs fully offline
# once the repo tarball + vendor/ are present.
RUN apt-get update && apt-get install -y \
    libzip-dev \
    libpng-dev \
    libjpeg-dev \
    unzip \
    cron \
    && docker-php-ext-configure gd --with-jpeg \
    && docker-php-ext-install pdo_mysql zip gd bcmath opcache \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Composer is included from the official image. In fully-offline builds callers
# must vendor dependencies beforehand (composer install --no-dev ; commit vendor/).
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN a2enmod rewrite headers

ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

RUN sed -i '/<Directory \/var\/www\/>/,/<\/Directory>/ s/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf

WORKDIR /var/www/html

# Copy the full project (including the committed vendor/ and public/static/layui bundles).
COPY . .

# Offline-first dependency strategy — see Dockerfile.test for the same logic.
# For air-gapped deployments, commit vendor/ to the build context before
# `docker compose build`. Networked builds still resolve via composer.lock.
RUN if [ -f vendor/autoload.php ]; then \
        echo "Using committed vendor/ — no network fetch"; \
    elif [ -f composer.lock ]; then \
        composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist --no-scripts; \
    else \
        echo "WARN: neither vendor/ nor composer.lock present — offline deploys will fail"; \
        composer install --no-dev --optimize-autoloader --no-interaction --no-scripts || true; \
    fi

RUN mkdir -p runtime storage/uploads/photos storage/uploads/documents storage/uploads/csv

RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html \
    && chmod -R 777 runtime/ storage/

# Cron schedules auto-expire, nightly risk scoring, and archive jobs.
COPY docker/crontab /etc/cron.d/portal-cron
RUN chmod 0644 /etc/cron.d/portal-cron && crontab /etc/cron.d/portal-cron

# Custom entrypoint: start cron alongside apache foreground.
COPY docker/entrypoint.sh /usr/local/bin/pp-entrypoint
RUN chmod +x /usr/local/bin/pp-entrypoint

EXPOSE 80

CMD ["/usr/local/bin/pp-entrypoint"]
