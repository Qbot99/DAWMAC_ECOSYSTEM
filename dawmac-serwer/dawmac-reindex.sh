#!/bin/bash
# Odswiezenie indeksu wtyczki Dawmac Filters.
# Uruchamiane z crona hostingu (panel dhosting): 03:30 i 12:00.

WP_PATH="/home/klient.dhosting.pl/dawmac/dawmac.pl-aid9/public_html"
LOG="$HOME/dawmac-reindex.log"

{
  echo "--- start: $(date "+%Y-%m-%d %H:%M:%S") ---"
  php83 -d memory_limit=512M /usr/local/bin/wp-cli.phar --path="$WP_PATH" dawmac reindex --quiet 2>&1
  echo "--- koniec: $(date "+%Y-%m-%d %H:%M:%S") ---"
} >> "$LOG" 2>&1

# log nie moze rosnac w nieskonczonosc - zostawiamy ostatnie 200 linii
tail -n 200 "$LOG" > "$LOG.tmp" && mv "$LOG.tmp" "$LOG"
