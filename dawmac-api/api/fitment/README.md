# Baza dopasowań felg — API

Własna baza tego, co pokazuje wheel-size: jakie auto ma jaką piastę
i jakie rozmiary felg. Wszystkie endpointy tylko czytają; dane wgrywa
`tools/import_fitment.php` z plików w `data/fitment` (format i kolejność
kroków: `data/fitment/README.md`).

| Endpoint | Co zwraca |
|---|---|
| `GET makes.php` | marki z liczbą modeli |
| `GET models.php?make=volkswagen` | modele marki |
| `GET generations.php?make=volkswagen&model=golf` | generacje z rozstawem, otworem centralnym, gwintem, mocowaniem i momentem |
| `GET vehicle.php?id=12` | karta jednej generacji z rozmiarami felg i opon |
| `GET search.php?pcd=5x112&cb=66.6` | auta, na które wejdzie felga, z informacją o pierścieniu centrującym |

Marki i modele podaje się slugiem (`škoda` i `skoda` dają to samo).
`search.php` przyjmuje `verified=1`, żeby zwrócić tylko dane potwierdzone
przez człowieka — tego powinien używać sklep.

Reguła dopasowania (`dawmac_fit_check` w `lib/fitment.php`): rozstaw musi
się zgadzać dokładnie, otwór felgi nie może być mniejszy od piasty, a różnica
do 0.1 mm to ten sam otwór zapisany różnie w katalogach (66.5 / 66.6).

Testy reguł bez bazy: `php tools/test_fitment.php`.
