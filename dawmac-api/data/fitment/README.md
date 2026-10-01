# Dane dopasowań felg

Pliki JSON, z których `tools/import_fitment.php` wypełnia bazę dopasowań
(tabele `fit_*`, patrz `tools/migrate_05_fitment.php`). Repozytorium trzyma
historię zmian danych, a baza na serwerze jest tym, co czyta API.

## Kolejność na serwerze

```
php tools/migrate_05_fitment.php --apply     # raz: tworzy tabele
php tools/import_fitment.php                 # sprawdza pliki, nic nie zapisuje
php tools/import_fitment.php --apply         # zapisuje
```

Import najpierw sprawdza wszystkie pliki. Jeden błędny wpis zatrzymuje cały
import i wypisuje, co poprawić. Import tylko dopisuje i aktualizuje, nigdy
nie usuwa.

## Format

```json
{
  "source": "Skąd są dane (domyślne dla całego pliku)",
  "makes": [
    {
      "name": "Volkswagen",
      "country": "DE",
      "models": [
        {
          "name": "Golf",
          "generations": [
            {
              "name": "VIII",
              "years": [2019, null],
              "pcd": "5x112",
              "center_bore": 57.1,
              "thread": "M14x1.5",
              "fastener": "bolt",
              "torque_nm": 140,
              "verified": false,
              "source": "opcjonalnie: źródło tej jednej generacji",
              "wheels": [
                { "size": "6.5Jx16 ET46", "tire": "205/55 R16", "oem": true },
                { "size": "8Jx18 ET45", "axle": "rear" }
              ]
            }
          ]
        }
      ]
    }
  ]
}
```

| Pole | Znaczenie |
|---|---|
| `years` | `[od, do]`; `null` jako drugi rok, gdy produkcja trwa |
| `pcd` | rozstaw śrub, np. `5x112`, `5x114.3` |
| `center_bore` | otwór centralny piasty w mm |
| `thread` | gwint, np. `M14x1.5`, `M12x1.25`, `1/2-20 UNF` |
| `fastener` | `bolt` (śruba), `nut` (nakrętka na szpilce) albo `null`, gdy nieznane |
| `torque_nm` | moment dokręcania w Nm albo `null`, gdy nieznany |
| `verified` | `true` tylko wtedy, gdy człowiek potwierdził dane |
| `wheels[].size` | felga w zapisie `szerokośćJxśrednica ETodsadzenie` |
| `wheels[].axle` | `both` (domyślnie), `front` albo `rear` dla rozmiarów na różne osie |
| `wheels[].tire` | opona, np. `225/45 R17`; średnica musi zgadzać się z felgą |
| `wheels[].oem` | `true` dla rozmiaru fabrycznego |

## Zweryfikowane a niezweryfikowane

Generacja z `verified: true` w bazie jest chroniona: import danych
niezweryfikowanych ją pomija. Dzięki temu automatyczne dopisywanie nowych
modeli nie nadpisze niczego, co ktoś już sprawdził. Wyszukiwarka API
przyjmuje `verified=1`, żeby sklep pokazywał tylko potwierdzone dane.

`start.json` to kilka aut na start, wszystkie jako niezweryfikowane.
