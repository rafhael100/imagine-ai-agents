FROM php:8.4-fpm-bookworm
RUN apt-get update && apt-get install -y --no-install-recommends libpq-dev && docker-php-ext-install pdo_pgsql && rm -rf /var/lib/apt/lists/*
WORKDIR /var/www/html
COPY runtime/central-auto/index.php runtime/central-auto/index.php
COPY runtime/central-auto/strict-json.php runtime/central-auto/strict-json.php
COPY runtime/central-auto/event-status.php runtime/central-auto/event-status.php
RUN printf 'display_errors=Off\nlog_errors=On\nzend.exception_ignore_args=On\nexpose_php=Off\n' > /usr/local/etc/php/conf.d/private.ini
USER www-data
CMD ["php-fpm"]
