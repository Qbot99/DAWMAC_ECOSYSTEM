#!/bin/sh
# Codzienna synchronizacja slownika felg: sklep -> tabela wheel_dict w galerii.
# Dzieki temu nowy model felgi w sklepie pojawia sie nastepnego dnia jako
# podpowiedz w panelu galerii, a liczniki produktow i aut sa aktualne.
#
# UWAGA: sciezka do PHP musi byc jawna. Domyslne /usr/bin/php to wersja 5.4,
# na ktorej ten skrypt sie nie uruchomi.
#
# Wpiete do crona 2026-09-01. Log: tools/sync_wheel_dict.log (ostatnie 200 linii).

KATALOG="/home/klient.dhosting.pl/dawmac/api.dawmacpolska.pl/tools"
PHP="/opt/alt/php82/usr/bin/php"
LOG="$KATALOG/sync_wheel_dict.log"

cd "$KATALOG" || exit 1

# tee, a nie zwykle przekierowanie: WP-Cron odpala ten skrypt i pokazuje
# jego wyjscie w kokpicie, wiec musi ono trafic i do logu, i na stdout.
{
    echo "=== $(date '+%Y-%m-%d %H:%M:%S') ==="
    "$PHP" sync_wheel_dict.php --apply 2>&1
    echo
} | tee -a "$LOG"

# Log ma nie rosnac w nieskonczonosc - trzymamy ostatnie 200 linii.
tail -n 200 "$LOG" > "$LOG.tmp" && mv "$LOG.tmp" "$LOG"
