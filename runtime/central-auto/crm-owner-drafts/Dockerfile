FROM php:8.4-fpm-bookworm
RUN apt-get update && apt-get install -y --no-install-recommends libpq-dev && docker-php-ext-install pdo_pgsql && rm -rf /var/lib/apt/lists/*
WORKDIR /var/www/html
# strict-json.php comes unchanged from the pinned Central tree. No legacy
# receiver or draft writer is changed or exposed by this separate image.
COPY runtime/central-auto/strict-json.php runtime/central-auto/strict-json.php
COPY runtime/central-auto/crm-owner-drafts/OwnerDraftContract.php runtime/central-auto/crm-owner-drafts/OwnerDraftContract.php
COPY runtime/central-auto/crm-owner-drafts/endpoint.php runtime/central-auto/crm-owner-drafts/endpoint.php
COPY runtime/central-auto/crm-owner-drafts/create.php runtime/central-auto/crm-owner-drafts/create.php
COPY runtime/central-auto/crm-owner-drafts/read.php runtime/central-auto/crm-owner-drafts/read.php
# A caller must mount exactly one operation-specific pool configuration. The
# default image has no working pool and therefore cannot start either surface.
RUN rm /usr/local/etc/php-fpm.d/www.conf /usr/local/etc/php-fpm.d/docker.conf /usr/local/etc/php-fpm.d/zz-docker.conf && printf '[global]\ndaemonize = no\nerror_log = /proc/self/fd/2\n' > /usr/local/etc/php-fpm.d/00-owner-global.conf && printf 'display_errors=Off\nlog_errors=Off\nzend.exception_ignore_args=On\nexpose_php=Off\n' > /usr/local/etc/php/conf.d/owner-draft.ini
USER www-data
CMD ["php-fpm", "--nodaemonize"]
