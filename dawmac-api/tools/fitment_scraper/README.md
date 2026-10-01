# Scraper danych dopasowań

Pobiera dane aut (rozstaw, otwór centralny, gwint, rozmiary felg) ze stron
i zapisuje je jako pliki importu bazy dopasowań. Do bazy dane trafiają
wyłącznie przez `tools/import_fitment.php`, czyli przez tę samą walidację
co wszystko inne.

```
python3 scrape.py ADAPTER --out /sciezka/na/wynik [--make bmw] [--limit 20]
php ../import_fitment.php /sciezka/na/wynik/*.json                       # podgląd
php ../import_fitment.php --apply --pomin-bledne /sciezka/na/wynik/*.json
```

Co robi sam, bez pamiętania o tym:

- czyta robots.txt i nie pobiera zakazanych adresów,
- robi najwyżej jedno zapytanie na sekundę na stronę (`--delay`, min. 1 s),
  dłużej, jeśli robots.txt prosi o Crawl-delay,
- przy 429 i błędach serwera czeka i ponawia,
- przy 401/403 albo captchy zatrzymuje cały przebieg i zapisuje to, co ma,
- trzyma pobrane strony w cache, więc przerwany przebieg można wznowić,
- każdą generację zapisuje z adresem strony źródłowej i jako
  niezweryfikowaną,
- gdy ta sama generacja przyjdzie z różnymi danymi piasty, zostawia pierwszą
  i zapisuje różnicę w `konflikty.json` do sprawdzenia.

Adapter strony (`adapters/<nazwa>.py`) zna tylko jej budowę: ma funkcję
`run(pobieracz, zbior, opcje)` i woła `zbior.dodaj(marka, model, generacja, url)`.

Testy bez internetu: `python3 test_scraper.py`.

## Dane z wcześniejszego zbioru alukonfigurator.pl

`z_sqlite_alukonfigurator.py` nie pobiera niczego z sieci. Zamienia bazę
SQLite z zbioru z 22–24.09.2026 na pliki importu:

```
python3 z_sqlite_alukonfigurator.py --baza KOPIA.sqlite --out ../../data/fitment/alukonfigurator
```

Podawaj kopię bazy (jest otwierana tylko do odczytu). Generacja to typ auta
z alukonfiguratora, a felgi to fabryczne rozmiary z COC. Strona nie podaje
śruba/nakrętka ani momentu, więc `fastener` i `torque_nm` są `null`.
Rozstaw jest w źródle obcięty do pełnych mm: 114/139/101/165 zamieniamy na
114.3/139.7/101.6/165.1, a `x/120` u marek GM jest pomijany (120 albo 120.65).
Rozmiary bez ET albo z ET z połówką mm wypadają. Wynik nie trafia do gita
(`data/fitment/alukonfigurator/` jest w `.gitignore`).

Testy: `python3 test_z_sqlite_alukonfigurator.py`.
