# -*- coding: utf-8 -*-
"""Dopasowanie tytulow filmikow z YouTube do slownika producent+model ze sklepu.

TRZY RZECZY, KTORE MUSIALY BYC NAPRAWIONE PO PIERWSZYM PRZEBIEGU:

1. Rozmiar sklejony z modelem. "JR4919" to JR49 + 19 cali, nie model JR4919.
   Wstawiamy spacje przed dwiema cyframi, po ktorych stoi znak cala.

2. Producent skrocony w tytule. Sklep ma "Ferrada Wheels", a na filmiku jest
   samo "Ferrada". Dopuszczamy prefiks, ale tylko gdy pasuje do dokladnie
   jednego producenta - inaczej zgadywalibysmy.

3. Nazwa auta udajaca model felgi. "Przymiarki do A5" to Audi, nie felga;
   "Jeep Grand Cherokee" dawalo model "Grand"; "et minus 18" dawalo "Minus".
   Model bez zakotwiczenia w producencie przyjmujemy tylko gdy ma cyfre
   albo gdy tuz przed nim stoi slowo "model".
"""
import re
from collections import defaultdict, Counter

def norm(s):
    s = s.replace(' ', ' ').replace('​', ' ').strip()
    if not s:
        return ''
    return re.sub(r'[\s\-_`´’\'".,]+', '', s.upper())[:64]

CAL = '″”"′\''

def rozdziel_cale(t):
    """JR4919" -> JR49 19" ; XE24418" -> XE244 18\""""
    return re.sub(r'(\d{2})\s*(?=[' + CAL + r'])', r' \1 ', t)

# --- slownik felg -------------------------------------------------------
modele_producenta = defaultdict(set)
producent_po_normie, nazwa_modelu = {}, {}
model_do_producentow = defaultdict(set)

for line in open('slownik.tsv', encoding='utf-8'):
    cz = line.rstrip('\n').split('\t')
    if len(cz) < 3:
        continue
    prod, model = cz[0].strip(), cz[1].strip()
    np_, nm = norm(prod), norm(model)
    if not np_ or not nm:
        continue
    producent_po_normie[np_] = prod
    nazwa_modelu[nm] = model
    modele_producenta[np_].add(nm)
    model_do_producentow[nm].add(np_)

# --- nazwy aut: to NIE sa modele felg -----------------------------------
stop = set()
for plik in ('auta_marki.txt', 'auta_modele.txt'):
    for line in open(plik, encoding='utf-8'):
        pelna = norm(line)
        if pelna:
            stop.add(pelna)
        for slowo in line.split():          # "Grand Cherokee" -> GRAND, CHEROKEE
            w = norm(slowo)
            if len(w) >= 3:
                stop.add(w)

# Oznaczenia nadwozi i slowa parametrow, ktorych nie ma w bazie aut.
stop |= {norm(x) for x in (
    'A1 A3 A4 A5 A6 A7 A8 Q2 Q3 Q4 Q5 Q7 Q8 E30 E36 E38 E39 E46 E60 E90 E92 '
    'F10 F11 F30 G30 G20 W204 W205 W212 W213 CLA GLC GLE '
    'MINUS PLUS CONCAVE ZIMA LATO KOMPLET PRZOD TYL SZEROKO'
).split()}

# Czlony, ktore wolno pominac szukajac producenta.
CZLON_FIRMOWY = {'WHEELS', 'WHEEL', 'RACING', 'FORGED', 'PERFORMANCE',
                 'ITALY', 'ITALIA', 'CORSE', 'LINE', 'OFFROAD', '4X4',
                 'MAGS', 'METAL', 'POLSKA', 'GROUP', 'DESIGN'}

# Marki opon i aut, ktore przetrwaly filtr kategorii "Opony".
stop |= {'CONTINENTAL', 'SPORTCONTACT', 'VENTUS', 'RAINSPORT', 'PILOT',
         'PZERO', 'CROSSCLIMATE', 'PRIMACY', 'EAGLE', 'TURANZA'}

MIN_PROD, MIN_MODEL = 2, 2

def kandydaci(tytul):
    slowa = [norm(w) for w in re.split(r'[\s/|+]+', rozdziel_cale(tytul))]
    slowa = [w for w in slowa if w]
    zbior, pozycje = set(), defaultdict(list)
    for n in (4, 3, 2, 1):
        for i in range(len(slowa) - n + 1):
            s = ''.join(slowa[i:i + n])
            if s:
                zbior.add(s)
                pozycje[s].append(i)
    return zbior, pozycje, slowa

def dopasuj(tytul):
    zbior, pozycje, slowa = kandydaci(tytul)

    # --- producent: najpierw dokladnie, potem jednoznaczny prefiks ---
    trafieni = [p for p in producent_po_normie
                if len(p) >= MIN_PROD and p in zbior and p not in stop]
    sposob_prod = 'dokladny'
    if not trafieni:
        # Skracamy WYLACZNIE o ogolny czlon firmowy: "Yanar" -> "Yanar Wheels".
        # Nigdy o czesc wlasciwej nazwy - inaczej "Kolor: Hyper Black" lapalo
        # producenta "Black Rhino", a "Bentley Continental GT" - opone.
        for c in sorted((c for c in zbior if len(c) >= 4), key=len, reverse=True):
            kand = [p for p in producent_po_normie
                    if p != c and p.startswith(c) and p[len(c):] in CZLON_FIRMOWY]
            if len(kand) == 1:
                trafieni, sposob_prod = kand, 'prefiks'
                break
    trafieni.sort(key=len, reverse=True)

    if trafieni:
        p = trafieni[0]
        modele = [m for m in modele_producenta[p] if len(m) >= MIN_MODEL and m in zbior]
        if modele:
            modele.sort(key=len, reverse=True)
            return (producent_po_normie[p], nazwa_modelu[modele[0]],
                    'producent+model' + ('' if sposob_prod == 'dokladny' else ' (prefiks)'))
        return (producent_po_normie[p], '', 'sam producent')

    # --- model bez producenta: tylko jednoznaczny i wiarygodny ---
    najlepszy = None
    for m in model_do_producentow:
        if len(m) < MIN_MODEL or m not in zbior or m in stop:
            continue
        if len(model_do_producentow[m]) != 1:
            continue
        ma_cyfre = any(z.isdigit() for z in m)
        po_slowie_model = any(i > 0 and slowa[i - 1].startswith('MODEL') for i in pozycje[m])
        if not (ma_cyfre or po_slowie_model):
            continue
        if najlepszy is None or len(m) > len(najlepszy):
            najlepszy = m
    if najlepszy:
        p = next(iter(model_do_producentow[najlepszy]))
        return (producent_po_normie[p], nazwa_modelu[najlepszy], 'sam model')

    return ('', '', 'brak')

wynik = []
for line in open('yt_all.tsv', encoding='utf-8'):
    cz = line.rstrip('\n').split('\t')
    if len(cz) < 2:
        continue
    prod, model, jak = dopasuj(cz[1])
    wynik.append((cz[0], prod, model, jak, cz[1]))

with open('yt_dopasowane.tsv', 'w', encoding='utf-8') as f:
    for r in wynik:
        f.write('\t'.join(r) + '\n')

c = Counter(r[3] for r in wynik)
print(f'filmikow: {len(wynik)}')
for jak, ile in c.most_common():
    print(f'  {jak:26s} {ile:4d}  ({ile*100//len(wynik)}%)')
przypisane = sum(v for k, v in c.items() if k.startswith('producent+model') or k == 'sam model')
print(f'\nz konkretnym modelem felgi: {przypisane} ({przypisane*100//len(wynik)}%)')
