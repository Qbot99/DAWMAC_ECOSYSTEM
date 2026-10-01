#!/usr/bin/env bash
# Pełny test API: czysta baza z tests/test.env, wbudowany serwer PHP, scenariusz z api_test.php.
set -euo pipefail
cd "$(dirname "$0")/.."
set -a; source tests/test.env; set +a
export GIELDA_ENV_FILE=/dev/null

mysql -u"$DB_USER" -p"$DB_PASSWORD" "$GIELDA_DB_NAME" -e "
  SET FOREIGN_KEY_CHECKS=0;
  DROP TABLE IF EXISTS g_messages, g_conversations, g_favorites, g_listing_images, g_reports, g_notifications, g_mod_log, g_rate_limit, g_tokens, g_listings, g_users, car_model, car_brand;
  SET FOREIGN_KEY_CHECKS=1;"
mysql -u"$DB_USER" -p"$DB_PASSWORD" "$GIELDA_DB_NAME" < sql/schema.sql
mysql -u"$DB_USER" -p"$DB_PASSWORD" "$GIELDA_DB_NAME" < tests/seed_cars.sql
rm -rf storage uploads; mkdir -p storage

php -S 127.0.0.1:8099 tests/dev-router.php > storage/server.log 2>&1 &
SERVER=$!
trap 'kill $SERVER 2>/dev/null; ' EXIT
sleep 1

php tests/api_test.php
