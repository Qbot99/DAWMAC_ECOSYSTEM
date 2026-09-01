#!/bin/bash
# Build i wysyłka forged.dawmacpolska.pl na serwer.
#
# Vite kopiuje public/ do dist/ bez zmian, więc razem z aplikacją jadą
# .htaccess, bot.php, bot2.php, send_form.php, sitemap*.php i robots.txt.
#
# CELOWO BEZ --delete. Na serwerze żyją rzeczy, których nie ma w repo
# i których build nie odtworzy:
#   index2.html + assets2/ + d2/  - druga wersja strony (62 MB)
#   car-seq/                      - sekwencja klatek do animacji
#   sitemap-cache.xml             - generowany przez sitemap.php
# Z --delete wysyłka skasowałaby to wszystko przy pierwszym uruchomieniu.

set -e

SERWER="dawmac@dawmac.ssh.dhosting.pl"
ZDALNY="/home/klient.dhosting.pl/dawmac/forged.dawmacpolska.pl/public_html/"

echo "==> build"
npm ci
npm run build

echo "==> wysyłka na serwer (bez kasowania treści serwerowych)"
rsync -az dist/ "$SERWER:$ZDALNY"

echo "==> gotowe: https://forged.dawmacpolska.pl"
