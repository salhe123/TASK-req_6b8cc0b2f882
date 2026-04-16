#!/bin/bash
set -e

echo "========================================="
echo "  Starting MySQL..."
echo "========================================="

service mariadb start || service mysql start

RETRIES=15
until mysqladmin ping --silent 2>/dev/null; do
    RETRIES=$((RETRIES - 1))
    if [ $RETRIES -le 0 ]; then echo "ERROR: MySQL did not start"; exit 1; fi
    sleep 1
done
echo "MySQL is ready."

mysql -u root <<SQL
CREATE DATABASE IF NOT EXISTS precision_portal;
CREATE USER IF NOT EXISTS 'portal_user'@'localhost' IDENTIFIED BY 'portal_secret';
GRANT ALL PRIVILEGES ON precision_portal.* TO 'portal_user'@'localhost';
FLUSH PRIVILEGES;
SQL

echo ""
echo "========================================="
echo "  Running schema and seeds..."
echo "========================================="

mysql -u root precision_portal < /app/database/schema.sql
mysql -u root precision_portal < /app/database/seed.sql

# Tests hammer the API well beyond the production 60 rpm default — raise the
# configured limit to something unreachable so the global ThrottleMiddleware does
# not falsely block them. The enforcement itself is still exercised by the
# dedicated MiddlewareIntegrationTest.
mysql -u root precision_portal <<'SQL_TESTTUNING'
UPDATE pp_throttle_config SET value = 100000 WHERE `key` = 'requests_per_minute';
UPDATE pp_throttle_config SET value = 100000 WHERE `key` = 'appointments_per_hour';
SQL_TESTTUNING

cat > /app/.env <<'ENVFILE'
APP_DEBUG = false

; Dev-only keys — require_secret_key() refuses to start without these.
APP_KEY = test-env-app-key-32byte-minimum-length-xxx
HMAC_KEY = test-env-hmac-key-32byte-minimum-length-xxx

[APP]
DEFAULT_TIMEZONE = America/New_York

[DATABASE]
TYPE = mysql
HOSTNAME = 127.0.0.1
DATABASE = precision_portal
USERNAME = portal_user
PASSWORD = portal_secret
HOSTPORT = 3306
CHARSET = utf8mb4
PREFIX = pp_
DEBUG = false

[SESSION]
TYPE = file
EXPIRE = 900

[LANG]
default_lang = en-us
ENVFILE

php /app/database/seed.php

echo ""
echo "========================================="
echo "  1. Running Unit + Integration Tests (with coverage)..."
echo "========================================="

vendor/bin/phpunit --testsuite Unit,Integration --testdox --coverage-text --coverage-html runtime/coverage/html --coverage-clover runtime/coverage/clover.xml 2>&1 | tee /tmp/coverage_output.txt

echo ""
echo "========================================="
echo "  2. Starting PHP server for API tests..."
echo "========================================="

php -S 0.0.0.0:80 -t public public/router.php &>/tmp/php_server.log &
SERVER_PID=$!
sleep 2

if ! kill -0 $SERVER_PID 2>/dev/null; then
    echo "ERROR: PHP server failed to start"
    cat /tmp/php_server.log
    exit 1
fi
echo "PHP server running on port 80 (PID: $SERVER_PID)"

echo ""
echo "========================================="
echo "  3. Running API Tests (real HTTP)..."
echo "========================================="

vendor/bin/phpunit --testsuite API --testdox --no-coverage

kill $SERVER_PID 2>/dev/null

# Extract coverage percentage and enforce 90% gate
COVERAGE=$(grep -oP 'Lines:\s+\K[\d.]+' /tmp/coverage_output.txt | head -1)
echo ""
echo "========================================="
echo "  Coverage: ${COVERAGE:-unknown}%"
echo "  Threshold: 90%"
echo "========================================="

if [ -n "$COVERAGE" ]; then
    # Use awk for comparison (no bc or python needed)
    PASS=$(awk "BEGIN {print ($COVERAGE >= 90) ? 1 : 0}")
    if [ "$PASS" != "1" ]; then
        echo "FAIL: Coverage ${COVERAGE}% is below 90% threshold"
        exit 1
    fi
    echo "PASS: Coverage meets threshold"
else
    echo "FAIL: Could not parse coverage percentage"
    exit 1
fi

echo ""
echo "========================================="
echo "  All tests passed!"
echo "========================================="
