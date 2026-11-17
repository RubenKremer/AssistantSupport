# --- Stage 1: PHP dependencies (league/commonmark, maxmind-db/reader) -------
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock* ./
RUN composer install --no-dev --no-interaction --prefer-dist --no-progress

# --- Stage 2: the actual web server ------------------------------------------
FROM php:8.4-apache

# Production PHP settings, without the "X-Powered-By" header.
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
 && printf "expose_php = Off\ndisplay_errors = Off\n" > "$PHP_INI_DIR/conf.d/zz-assistant.ini"

# Two sites (bot on 80, admin on 8080), access log WITHOUT query strings
# so the passphrase never lands in a log.
COPY docker/apache.conf /etc/apache2/conf-available/zz-assistant.conf
COPY docker/sites.conf  /etc/apache2/sites-available/assistant.conf
RUN a2enmod headers \
 && a2enconf zz-assistant \
 && a2disconf other-vhosts-access-log \
 && a2dissite 000-default \
 && a2ensite assistant

# Application code.
COPY --from=vendor /app/vendor /var/www/vendor
COPY src/    /var/www/src/
COPY public/ /var/www/html/
COPY admin/  /var/www/admin/
COPY public/fonts/ /var/www/admin/fonts/
COPY public/favicon.svg public/favicon.ico public/apple-touch-icon.png /var/www/admin/

# Default content (welcome page, example context), copied to /data on first start.
COPY defaults/ /defaults/
COPY docker/entrypoint.sh /usr/local/bin/assistant-entrypoint
RUN find /defaults -type f -exec chmod 644 {} + \
 && chmod +x /usr/local/bin/assistant-entrypoint

# Unraid defaults: nobody:users
ENV PUID=99 \
    PGID=100 \
    ADMIN_USER=admin \
    GEO_DOWNLOAD=true \
    DATA_DIR=/data \
    CONFIG_DIR=/config

VOLUME ["/data", "/config"]
# 80 = bot entry point (expose via reverse proxy), 8080 = admin panel (LAN only)
EXPOSE 80 8080

ENTRYPOINT ["assistant-entrypoint"]
CMD ["apache2-foreground"]
