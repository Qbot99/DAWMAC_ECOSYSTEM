# -*- coding: utf-8 -*-
"""Adaptery stron. Kazdy modul ma funkcje:

    run(pobieracz, zbior, opcje) -> None

i wola zbior.dodaj(marka, model, generacja, url) dla kazdego auta.
Adapter zna tylko budowe jednej strony; grzecznosc (robots.txt, przerwy,
cache, stop na blokadzie) zapewnia Pobieracz, a format wyjscia Zbior.
"""
import importlib

ADAPTERY = {
    # 'alukonfigurator': 'adapters.alukonfigurator',
}


def wczytaj(nazwa):
    if nazwa not in ADAPTERY:
        raise SystemExit('Nieznany adapter "%s". Dostepne: %s' % (nazwa, ', '.join(sorted(ADAPTERY)) or 'brak'))
    return importlib.import_module(ADAPTERY[nazwa])
