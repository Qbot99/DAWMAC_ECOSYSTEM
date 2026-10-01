# -*- coding: utf-8 -*-
"""Pobieranie danych dopasowan ze stron do plikow importu.

    python3 scrape.py ADAPTER --out KATALOG [--delay 1.0] [--cache KATALOG]
                      [--make SLUG ...] [--limit N] [--odswiez]

Wynik to pliki JSON w formacie data/fitment/README.md (jeden na marke,
wszystko jako niezweryfikowane, z adresem zrodla przy kazdej generacji)
plus konflikty.json, jesli ta sama generacja przyszla z roznymi danymi.
Dalej:

    php ../import_fitment.php KATALOG/*.json                  # podglad
    php ../import_fitment.php --apply --pomin-bledne KATALOG/*.json

Przerwany przebieg uruchomiony ponownie bierze juz pobrane strony z cache.
"""
import argparse
import os
import sys
import urllib.error

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))

import adapters  # noqa: E402
from collector import Zbior  # noqa: E402
from polite import Pobieracz, Zablokowane  # noqa: E402


def main(argv=None):
    p = argparse.ArgumentParser(description='Pobieranie danych dopasowan felg.')
    p.add_argument('adapter')
    p.add_argument('--out', required=True, help='katalog na pliki JSON do importu')
    p.add_argument('--cache', help='katalog cache stron (domyslnie OUT/.cache)')
    p.add_argument('--delay', type=float, default=1.0, help='sekundy miedzy zapytaniami do jednego hosta (min. 1)')
    p.add_argument('--make', action='append', default=[], help='tylko te marki (slug), mozna powtorzyc')
    p.add_argument('--limit', type=int, default=0, help='najwyzej tyle modeli (do prob)')
    p.add_argument('--odswiez', action='store_true', help='nie czytaj z cache, pobierz od nowa')
    opcje = p.parse_args(argv)

    if opcje.delay < 1:
        p.error('--delay ponizej 1 s to juz nie jest grzeczne pobieranie')

    adapter = adapters.wczytaj(opcje.adapter)
    pobieracz = Pobieracz(opcje.cache or os.path.join(opcje.out, '.cache'), delay=opcje.delay, odswiez=opcje.odswiez)
    zbior = Zbior(getattr(adapter, 'ZRODLO', opcje.adapter))

    kod = 0
    try:
        adapter.run(pobieracz, zbior, opcje)
    except Zablokowane as e:
        # Zapisujemy to, co juz jest - cache pozwoli dokonczyc pozniej.
        print('STOP: serwer zablokowal pobieranie (%s). Nie obchodze blokady.' % e, file=sys.stderr)
        kod = 2
    except urllib.error.URLError as e:
        print('STOP: blad sieci (%s).' % e, file=sys.stderr)
        kod = 3
    except KeyboardInterrupt:
        print('Przerwane - zapisuje to, co juz pobrane.', file=sys.stderr)
        kod = 130

    pliki = zbior.zapisz(opcje.out)
    l = zbior.liczniki()
    print('Zapisano %d plikow: %d marek, %d modeli, %d generacji, %d konfliktow.' % (
        len(pliki), l['marek'], l['modeli'], l['generacji'], l['konfliktow']))
    print('Zapytania: %d z sieci, %d z cache.' % (pobieracz.statystyki['z_sieci'], pobieracz.statystyki['z_cache']))
    return kod


if __name__ == '__main__':
    sys.exit(main())
