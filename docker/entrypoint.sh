#!/bin/sh
# Prepares /data and /config, keeps the geo database fresh, then starts Apache.
set -e

# Run Apache as PUID:PGID (Unraid default 99:100 = nobody:users), so files in
# /data and /config are owned by the same user as your other shares.
groupmod -o -g "${PGID:-100}" www-data
usermod  -o -u "${PUID:-99}"  www-data

# Copy default content on first start. Existing files are never overwritten.
mkdir -p /data /config
cp -r --update=none /defaults/data/. /data/
chown -R www-data:www-data /data /config

# The $ in this PHP one-liner belongs to PHP, not to the shell:
# shellcheck disable=SC2016
if ! php -r '$s = json_decode((string) @file_get_contents("/config/settings.json"), true); exit(empty($s["passphrase_hash"]) ? 1 : 0);'; then
    echo "WARNING: no passphrase set yet. Set one under Settings in the admin panel; the private area stays closed until then."
fi
if [ -z "$ADMIN_PASSWORD" ]; then
    echo "WARNING: ADMIN_PASSWORD is not set; the admin panel (port 8080) stays disabled until it is."
fi

# Geo database for country checks: download now if missing, then check daily
# (it is refreshed when older than 30 days). Set GEO_DOWNLOAD=false to disable.
if [ "${GEO_DOWNLOAD:-true}" = "true" ]; then
    (
        while true; do
            php /var/www/src/cli/update-geo.php && chown -R www-data:www-data /data/geo || true
            sleep 86400
        done
    ) &
fi

exec docker-php-entrypoint "$@"
