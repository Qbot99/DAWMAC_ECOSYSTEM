#!/bin/bash
# Build i wysyłka forged.dawmacpolska.pl na serwer.
#
# Vite kopiuje public/ do dist/ bez zmian, więc razem z aplikacją jadą
# .htaccess, bot.php (podglądy linków w social mediach) i resize.php.
#
# Duże pliki (PDF, wideo) mieszkają tylko na serwerze - są w EXCLUDE,
# żeby wysyłka ich nie skasowała i żeby nie ciążyły w repo.

set -e

SERWER="dawmac@dawmac.ssh.dhosting.pl"
ZDALNY="/home/klient.dhosting.pl/dawmac/forged.dawmacpolska.pl/public_html/"
EXCLUDE=(--exclude "What we can do for youDM.pdf" --exclude "hd.mp4" --exclude "awstats")

echo "==> build"
npm ci
npm run build

echo "==> wysyłka na serwer"
rsync -az --delete "${EXCLUDE[@]}" dist/ "$SERWER:$ZDALNY"

echo "==> gotowe: https://forged.dawmacpolska.pl"
