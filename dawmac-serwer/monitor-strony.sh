#!/bin/bash
# =====================================================================
#  monitor-strony.sh - pilnuje, czy dawmac.pl NAPRAWDE dziala
#
#  Sklep potrafi wygladac na zywy (LiteSpeed serwuje kopie stron z cache),
#  gdy PHP juz nie odpowiada - wtedy nie dziala koszyk, zamowienia ani
#  filtry. Ten skrypt odpytuje dwa adresy, ktore MUSZA przejsc przez PHP,
#  i wysyla e-mail, gdy przestana odpowiadac.
#
#  Uzycie:
#    monitor-strony.sh           - jedno sprawdzenie (to woła cron co 5 min)
#    monitor-strony.sh --status  - pokaz stan i ostatnie wpisy z logu
#    monitor-strony.sh --test    - wyslij probny e-mail (sprawdzenie poczty)
# =====================================================================

ADRES_EMAIL="hkubot@icloud.com"
NADAWCA="Monitor dawmac.pl <monitor@dawmac.pl>"
DOM="/home/klient.dhosting.pl/dawmac"
STAN="$DOM/.monitor-strony.stan"
LOG="$DOM/monitor-strony.log"
LIMIT_S=25          # po tylu sekundach bez odpowiedzi uznajemy probe za nieudana
PROGI=2             # alarm dopiero po 2 nieudanych sprawdzeniach z rzedu (10 min)
PRZYPOMNIENIE=12    # gdy awaria trwa, przypominaj co 12 sprawdzen (= co godzine)

# Dwa sprawdzenia: PHP WordPressa i silnik filtrow. Losowy parametr
# gwarantuje, ze odpowiedz nie przyjdzie z cache.
ZNACZNIK=$(date +%s)
declare -A URL=(
  ["PHP WordPressa (wp-json)"]="https://dawmac.pl/wp-json/?monitor=$ZNACZNIK"
  ["Silnik filtrow (endpoint)"]="https://dawmac.pl/wp-content/plugins/dawmac-filters/endpoint.php?per_page=1&monitor=$ZNACZNIK"
)
declare -A OCZEKUJ=(
  ["PHP WordPressa (wp-json)"]='"namespaces"'
  ["Silnik filtrow (endpoint)"]='"total"'
)

wyslij_email() {  # $1 temat, $2 tresc
  {
    echo "From: $NADAWCA"
    echo "To: $ADRES_EMAIL"
    echo "Subject: $1"
    echo "Content-Type: text/plain; charset=UTF-8"
    echo
    echo "$2"
    echo
    echo "--"
    echo "Wyslane automatycznie przez $DOM/monitor-strony.sh"
    echo "Log: $LOG"
  } | /usr/sbin/sendmail -t -i
}

zapisz_log() {
  echo "$(date '+%Y-%m-%d %H:%M:%S') $1" >> "$LOG"
  # log nie rosnie w nieskonczonosc - trzymamy ostatnie 3000 linii
  if [ "$(wc -l < "$LOG")" -gt 3500 ]; then
    tail -n 3000 "$LOG" > "$LOG.tmp" && mv "$LOG.tmp" "$LOG"
  fi
}

# ----- tryby pomocnicze -----------------------------------------------
if [ "$1" = "--test" ]; then
  wyslij_email "[dawmac.pl] TEST monitoringu - poczta dziala" \
"To jest probna wiadomosc z monitoringu dawmac.pl.
Jesli ja czytasz, alarmy o awarii beda docieraly na ten adres.

Sprawdzenie wykonano: $(date '+%Y-%m-%d %H:%M:%S')"
  zapisz_log "TEST: wyslano probny e-mail na $ADRES_EMAIL"
  echo "Wyslano probny e-mail na $ADRES_EMAIL"
  exit 0
fi

if [ "$1" = "--status" ]; then
  echo "Stan: $(head -1 "$STAN" 2>/dev/null || echo 'brak (jeszcze nie sprawdzano)')"
  echo "Nieudanych z rzedu: $(sed -n 2p "$STAN" 2>/dev/null || echo 0)"
  echo "Ostatnie wpisy:"
  tail -n 8 "$LOG" 2>/dev/null | sed 's/^/  /'
  exit 0
fi

# ----- wlasciwe sprawdzenie -------------------------------------------
BLEDY=""
SZCZEGOLY=""
for nazwa in "${!URL[@]}"; do
  ODP=$(mktemp)
  KOD=$(curl -sS --max-time "$LIMIT_S" -o "$ODP" -w "%{http_code} %{time_total}" "${URL[$nazwa]}" 2>/dev/null)
  HTTP=${KOD%% *}; CZAS=${KOD##* }
  if [ "$HTTP" = "200" ] && grep -q "${OCZEKUJ[$nazwa]}" "$ODP"; then
    SZCZEGOLY+="  OK    $nazwa - HTTP 200 w ${CZAS}s"$'\n'
  else
    [ -z "$HTTP" ] || [ "$HTTP" = "000" ] && HTTP="brak odpowiedzi (timeout ${LIMIT_S}s)"
    BLEDY+="$nazwa: $HTTP"$'\n'
    SZCZEGOLY+="  BLAD  $nazwa - $HTTP"$'\n'
  fi
  rm -f "$ODP"
done

POPRZEDNI=$(head -1 "$STAN" 2>/dev/null || echo "UP")
LICZNIK=$(sed -n 2p "$STAN" 2>/dev/null || echo 0)

if [ -n "$BLEDY" ]; then
  LICZNIK=$((LICZNIK + 1))
  zapisz_log "BLAD (${LICZNIK}. z rzedu): $(echo "$BLEDY" | tr '\n' ';')"
  if [ "$LICZNIK" -eq "$PROGI" ]; then
    wyslij_email "[dawmac.pl] AWARIA: strona nie odpowiada" \
"dawmac.pl nie odpowiada od co najmniej $((PROGI * 5)) minut.

$SZCZEGOLY
Sklep moze wygladac na dzialajacy (cache), ale koszyk, zamowienia
i filtry NIE dzialaja.

Co zrobic (pomoglo 2026-09-06):
  1. dpanel.dhosting.pl -> Strony WWW -> dawmac.pl -> Akcje -> Edytuj
  2. Zmien wersje PHP na inna z rodziny 8.x i zapisz
  3. Odczekaj minute - nastepny e-mail potwierdzi powrot

Jesli to nie pomoze: Helpdesk 24h w dPanelu."
    zapisz_log "ALARM: wyslano e-mail o awarii"
    POPRZEDNI="DOWN"
  elif [ "$LICZNIK" -gt "$PROGI" ] && [ $((LICZNIK % PRZYPOMNIENIE)) -eq 0 ]; then
    wyslij_email "[dawmac.pl] AWARIA TRWA od $((LICZNIK * 5)) min" \
"dawmac.pl nadal nie odpowiada (od ok. $((LICZNIK * 5)) minut).

$SZCZEGOLY"
    zapisz_log "PRZYPOMNIENIE: wyslano e-mail (awaria trwa)"
  fi
  printf '%s\n%s\n' "$POPRZEDNI" "$LICZNIK" > "$STAN"
else
  if [ "$POPRZEDNI" = "DOWN" ]; then
    wyslij_email "[dawmac.pl] OK: strona znowu dziala" \
"dawmac.pl odpowiada poprawnie po awarii trwajacej ok. $((LICZNIK * 5)) minut.

$SZCZEGOLY"
    zapisz_log "POWROT: strona dziala, wyslano e-mail"
  fi
  zapisz_log "OK: $(echo "$SZCZEGOLY" | grep -oE 'w [0-9.]+s' | tr '\n' ' ')"
  printf 'UP\n0\n' > "$STAN"
fi
