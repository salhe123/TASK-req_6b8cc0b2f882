#!/bin/bash
# Precision Portal container entrypoint.
# Starts cron (for appointment auto-expire, nightly risk scoring, audit archive)
# alongside apache2 in the foreground. If cron fails we still launch apache so
# the web tier is available; operators can inspect /var/log/cron.log.
set -e

touch /var/log/cron.log

# Ensure the scheduled jobs are loaded for www-data since PHP runs as that user.
if [ -f /etc/cron.d/portal-cron ]; then
    crontab -u root /etc/cron.d/portal-cron 2>/dev/null || true
fi

# Launch cron in the background. `-f` keeps the daemon in foreground under its
# own process, but we want apache2 to own PID 1, so we background cron here.
service cron start || cron || echo "[pp-entrypoint] cron failed to start — scheduled jobs will not run"

echo "[pp-entrypoint] cron status:"
service cron status || true

# Delegate to the Apache foreground launcher.
exec apache2-foreground
