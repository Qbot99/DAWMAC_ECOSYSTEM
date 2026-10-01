# -*- coding: utf-8 -*-
"""Pliki importu z bazy SQLite wczesniejszego zbioru alukonfigurator.pl.

    python3 z_sqlite_alukonfigurator.py --baza KOPIA.sqlite --out KATALOG

Nic nie pobiera z sieci - czyta tylko baze z zbioru z 22-24.09.2026
(tabele type_groups, model_type_groups, versions, car_details, car_cocs).
Baze otwieramy tylko do odczytu; podawaj kopie, nie oryginal.

Co trafia do pliku:
- generacja = typ auta z alukonfiguratora ("AU, 1K (2012-2017)"); gdy w jednym
  typie sa wersje z roznym rozstawem, kazdy rozstaw to osobna generacja,
- lata = najwczesniejszy i najpozniejszy rok wersji typu (koniec null, gdy
  ktoras wersja jest nadal produkowana),
- rozstaw, otwor centralny i gwint z danych technicznych auta reprezentujacego typ,
- fastener i torque_nm = null - strona ich nie podaje, a zgadywac nie wolno,
- felgi = fabryczne rozmiary z COC wszystkich wersji typu (oem = true).

Typy bez rozstawu, otworu albo gwintu sa pomijane i liczone. Zly rozmiar
felgi wypada sam, nie zabierajac generacji danych piasty.
"""
import argparse
import os
import re
import sqlite3
import sys
from collections import Counter, OrderedDict

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))

from collector import Zbior  # noqa: E402

ZRODLO = 'alukonfigurator.pl (zbior z 22-24.09.2026)'

# API podaje rozstaw jako liczbe calkowita ("5/114"). Te wartosci nie
# istnieja jako rozstawy - to obciete standardy calowe, jednoznaczne.
OBCIETE_PCD = {114: 114.3, 139: 139.7, 101: 101.6, 165: 165.1}

# "x/120" bywa 120 albo 120.65 (5x4.75", auta GM). U tych marek nie wiadomo
# ktore - pomijamy zamiast zgadywac.
MARKI_120_65 = {'CHEVROLET', 'CADILLAC', 'BUICK', 'GMC', 'PONTIAC', 'OLDSMOBILE', 'HUMMER', 'CORVETTE'}

# Skok gwintu w setnych mm. Inne wartosci w bazie to bledy zapisu.
SKOKI_GWINTU = {125: '1.25', 150: '1.5', 175: '1.75', 200: '2'}


def liczba(tekst):
    """'6,50' -> 6.5, '' -> None"""
    tekst = (tekst or '').strip().replace(',', '.')
    try:
        return float(tekst)
    except ValueError:
        return None


def num(x):
    """112.0 -> '112', 114.3 -> '114.3' (jak dawmac_fit_num w PHP)"""
    return ('%.2f' % x).rstrip('0').rstrip('.')


def rok(data):
    """'2017-01-01' -> 2017, '0000-00-00' / '' -> None"""
    poczatek = (data or '')[:4]
    return int(poczatek) or None if poczatek.isdigit() else None


def pcd(bolt_pattern, marka):
    """'5/114' -> ('5x114.3', None); blad -> (None, powod)"""
    m = re.match(r'^\s*(\d+)\s*/\s*(\d+(?:[.,]\d+)?)\s*$', bolt_pattern or '')
    if not m:
        return None, 'brak rozstawu'
    otwory, mm = int(m.group(1)), float(m.group(2).replace(',', '.'))
    if not 3 <= otwory <= 10 or not 90 <= mm <= 210:
        return None, 'nieprawidlowy rozstaw'
    if mm == 120 and marka.upper() in MARKI_120_65:
        return None, 'rozstaw niejednoznaczny (120 albo 120.65)'
    mm = OBCIETE_PCD.get(int(mm), mm) if mm == int(mm) else mm
    return '%dx%s' % (otwory, num(mm)), None


def gwint(srednica, skok):
    if not srednica or not skok:
        return None, 'brak gwintu'
    if skok not in SKOKI_GWINTU or srednica not in (10, 12, 14, 15, 16, 18):
        return None, 'nietypowy gwint'
    return 'M%dx%s' % (srednica, SKOKI_GWINTU[skok]), None


def jedno_miejsce(x):
    """Czy liczba ma najwyzej jedno miejsce po przecinku (48.5 tak, 48.25 nie).
    Z tolerancja, bo 48.3 * 10 we floatach to nie zawsze rowno 483."""
    return abs(x * 10 - round(x * 10)) < 1e-6


def felga(szer, sred, et):
    """('7,50', '17', '41,00') -> '7.5Jx17 ET41', ('7,00', '17', '48,50') -> '7Jx17 ET48.5',
    albo None, gdy nie przejdzie walidacji (ET najwyzej z jednym miejscem po przecinku)."""
    s, d, e = liczba(szer), liczba(sred), liczba(et)
    if s is None or d is None or e is None:
        return None
    if not 3 <= s <= 14 or (s * 2) % 1 or d % 1 or not 12 <= d <= 26 or not jedno_miejsce(e) or not -100 <= e <= 100:
        return None
    return '%sJx%d ET%s' % (num(s), d, num(e))


def opona(szer, profil, sred):
    """('225', '45', '17') -> '225/45 R17' albo None"""
    s, p, d = liczba(szer), liczba(profil), liczba(sred)
    if s is None or p is None or d is None or s % 1 or p % 1 or d % 1:
        return None
    if not 125 <= s <= 395 or not 20 <= p <= 90 or not 12 <= d <= 26:
        return None
    return '%d/%d R%d' % (s, p, d)


def kola_z_coc(wiersze, liczniki):
    """Wiersze car_cocs -> lista kol w formacie importu (bez powtorzen)."""
    kola = OrderedDict()
    for r in wiersze:
        osie = [('both', 'f')] if not r['mixed'] else [('front', 'f'), ('rear', 'r')]
        for os_, s in osie:
            rozmiar = felga(r['rim_width_' + s], r['diameter_' + s], r['offset_' + s])
            if rozmiar is None:
                et = liczba(r['offset_' + s])
                if et is None:
                    liczniki['felga odrzucona: brak ET'] += 1
                elif not jedno_miejsce(et):
                    # Import przyjmuje ET z jednym miejscem po przecinku; nie zaokraglamy za strone.
                    liczniki['felga odrzucona: ET z wiecej niz 1 miejscem po przecinku'] += 1
                else:
                    liczniki['felga odrzucona: inny blad'] += 1
                continue
            k = {'size': rozmiar, 'oem': True}
            if os_ != 'both':
                k['axle'] = os_
            op = opona(r['tyre_width_' + s], r['tyre_aspect_' + s], r['diameter_' + s])
            if op is not None:
                k['tire'] = op
            else:
                liczniki['opona pominieta (felga zostaje)'] += 1
            klucz = (os_, rozmiar, k.get('tire'))
            if klucz not in kola:
                kola[klucz] = k
    return list(kola.values())


def konwertuj(baza, zbior, marki=None):
    """Czyta baze i wola zbior.dodaj(). Zwraca Counter z licznikami."""
    db = sqlite3.connect('file:%s?mode=ro' % baza, uri=True)
    db.row_factory = sqlite3.Row
    liczniki = Counter()

    modele = {}
    for r in db.execute('SELECT make, model, grp FROM model_type_groups ORDER BY make, model'):
        modele.setdefault(r['grp'], []).append((r['make'], r['model']))

    for t in db.execute('SELECT * FROM type_groups ORDER BY make, model, prod_from, grp').fetchall():
        auta = modele.get(t['grp']) or [(t['make'], t['model'])]
        if marki and not any(m.upper() in marki for m, _ in auta):
            continue

        wersje = db.execute('SELECT car_tag, prod_from, prod_to, coalesce(bolt_pattern, \'\') bp '
                            'FROM versions WHERE grp = ?', (t['grp'],)).fetchall()
        dane = db.execute('SELECT * FROM car_details WHERE grp = ? AND found = 1 ORDER BY car_tag',
                          (t['grp'],)).fetchall()

        # Wersje grupujemy po rozstawie; dane piasty bierzemy z auta o tym rozstawie.
        po_rozstawie = OrderedDict()
        for w in wersje:
            po_rozstawie.setdefault(w['bp'], []).append(w)
        if not po_rozstawie:
            po_rozstawie[''] = []

        for bp, grupa in po_rozstawie.items():
            liczniki['typow'] += 1
            d = next((x for x in dane if x['bolt_pattern'] == bp), None) or (dane[0] if len(po_rozstawie) == 1 and dane else None)
            if d is None:
                liczniki['pominiete: brak danych technicznych'] += 1
                continue

            rozstaw, powod = pcd(d['bolt_pattern'] or bp, t['make'])
            if powod:
                liczniki['pominiete: ' + powod] += 1
                continue
            cb = d['center_bore_front'] or None
            if not cb or not 40 <= cb <= 180:
                liczniki['pominiete: brak otworu centralnego'] += 1
                continue
            gw, powod = gwint(d['thread_size'], d['thread_pitch'])
            if powod:
                liczniki['pominiete: ' + powod] += 1
                continue

            od = [rok(w['prod_from']) for w in grupa if rok(w['prod_from'])] or [rok(t['prod_from'])]
            if not od[0]:
                liczniki['pominiete: brak lat produkcji'] += 1
                continue
            trwa = any(not rok(w['prod_to']) for w in grupa) if grupa else not rok(t['prod_to'])
            do = None if trwa else max([rok(w['prod_to']) for w in grupa] or [rok(t['prod_to'])])
            lata = [min(od), do if do is None or do >= min(od) else min(od)]

            tagi = [w['car_tag'] for w in grupa]
            coc = []
            for i in range(0, len(tagi), 500):
                czesc = tagi[i:i + 500]
                coc += db.execute('SELECT * FROM car_cocs WHERE car_tag IN (%s) ORDER BY car_tag, seq'
                                  % ','.join('?' * len(czesc)), czesc).fetchall()
            kola = kola_z_coc(coc, liczniki)
            liczniki['felgi zachowane'] += len(kola)

            typ = (t['type_chain'] or '').split('Typ:', 1)[-1].strip() or t['type'] or t['grp']
            nazwa = '%s (%d-%s)' % (typ, lata[0], lata[1] or '')
            if len(po_rozstawie) > 1:
                nazwa += ' ' + rozstaw

            for marka, model in auta:
                if marki and marka.upper() not in marki:
                    continue
                zbior.dodaj(marka, model, {
                    'name': nazwa,
                    'years': lata,
                    'pcd': rozstaw,
                    'center_bore': round(float(cb), 2),
                    'thread': gw,
                    'fastener': None,
                    'torque_nm': None,
                    'wheels': kola,
                }, 'typ ' + t['grp'])
            liczniki['przeniesione'] += 1

    db.close()
    return liczniki


def main(argv=None):
    p = argparse.ArgumentParser(description='Import danych alukonfigurator.pl z lokalnej bazy SQLite.')
    p.add_argument('--baza', required=True, help='KOPIA alukonfigurator.sqlite (otwierana tylko do odczytu)')
    p.add_argument('--out', required=True, help='katalog na pliki JSON do importu')
    p.add_argument('--make', action='append', default=[], help='tylko te marki (jak w bazie, np. AUDI)')
    opcje = p.parse_args(argv)

    zbior = Zbior(ZRODLO)
    liczniki = konwertuj(opcje.baza, zbior, {m.upper() for m in opcje.make} or None)
    pliki = zbior.zapisz(opcje.out)

    l = zbior.liczniki()
    print('Zapisano %d plikow: %d marek, %d modeli, %d generacji, %d konfliktow.' % (
        len(pliki), l['marek'], l['modeli'], l['generacji'], l['konfliktow']))
    for k, v in sorted(liczniki.items()):
        print('  %-45s %d' % (k, v))
    return 0


if __name__ == '__main__':
    sys.exit(main())
