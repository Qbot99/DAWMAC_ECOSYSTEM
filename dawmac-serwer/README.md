# dawmac-serwer

Skrypty, które chodzą bezpośrednio na hostingu dhosting (konto `dawmac`),
poza WordPressem. Źródło trzymamy tutaj, żeby nie zginęło razem z serwerem.

Wdrożenie: skopiować plik do katalogu domowego na serwerze i nadać `chmod 700`.
`crontab` z SSH jest zablokowany - wpisy cron dodaje się w dPanelu
(https://dpanel.dhosting.pl -> CRON), zawsze: Program **BASH**, Aplikacja `~/nazwa.sh`.

| Skrypt | Co robi | Cron |
|---|---|---|
| `dawmac-reindex.sh` | Przebudowa indeksu wtyczki Dawmac Filters (`wp dawmac reindex`), log w `~/dawmac-reindex.log` | `30 3 * * *` i `0 12 * * *` |
| `monitor-strony.sh` | Pilnuje, czy PHP na dawmac.pl odpowiada; alarm e-mail przy awarii | `*/5 * * * *` |

## monitor-strony.sh

Sklep potrafi wyglądać na żywy (LiteSpeed serwuje kopie stron z cache), gdy PHP
już nie odpowiada - wtedy nie działa koszyk, zamówienia ani filtry. Tak było
6 września 2026 przez ~22 godziny.

Co 5 minut skrypt odpytuje dwa adresy, które muszą przejść przez PHP
(z losowym parametrem, więc nigdy z cache):

- `https://dawmac.pl/wp-json/` - PHP WordPressa
- `.../dawmac-filters/endpoint.php?per_page=1` - silnik filtrów

Próba jest nieudana, gdy nie ma HTTP 200 w 25 s albo odpowiedź nie zawiera
oczekiwanego fragmentu JSON. Dopiero **2 nieudane próby z rzędu** (10 minut)
wysyłają alarm - pojedyncza czkawka serwera nie budzi nikogo w nocy. Gdy awaria
trwa, przypomnienie idzie co godzinę; po powrocie jeden e-mail „OK".

Stan między uruchomieniami: `~/.monitor-strony.stan`, log: `~/monitor-strony.log`.

```bash
~/monitor-strony.sh --status   # stan i ostatnie wpisy z logu
~/monitor-strony.sh --test     # próbny e-mail (sprawdzenie poczty)
```

Adres alarmów jest w zmiennej `ADRES_EMAIL` na górze skryptu.
