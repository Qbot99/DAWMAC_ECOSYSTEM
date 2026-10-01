# -*- coding: utf-8 -*-
"""Zbieranie pobranych aut i zapis w formacie importu (data/fitment/README.md).

Adapter strony oddaje tu pojedyncze generacje, a Zbior:
- grupuje je w marka -> model -> generacja,
- dopisuje adres strony, z ktorej pochodzi wpis (pole `source`),
- zawsze ustawia verified = false - pobrane dane czekaja na czlowieka,
- skleja powtorzenia tej samej generacji: rozmiary felg sumuje, a gdy
  parametry piasty sie roznia, zostawia pierwszy wpis i zapisuje konflikt
  do osobnego pliku do sprawdzenia.

Klucze generacji licza sie ta sama regula co dawmac_fit_slug() w PHP
(test_scraper.py pilnuje zgodnosci), bo import odrzuca plik, w ktorym
ta sama generacja wystepuje dwa razy.
"""
import json
import os
import re

POLSKIE = {
    'ą': 'a', 'ć': 'c', 'ę': 'e', 'ł': 'l', 'ń': 'n', 'ó': 'o', 'ś': 's', 'ź': 'z', 'ż': 'z',
    'Ą': 'a', 'Ć': 'c', 'Ę': 'e', 'Ł': 'l', 'Ń': 'n', 'Ó': 'o', 'Ś': 's', 'Ź': 'z', 'Ż': 'z',
    'ä': 'a', 'ö': 'o', 'ü': 'u', 'ß': 'ss', 'Ä': 'a', 'Ö': 'o', 'Ü': 'u',
    'š': 's', 'č': 'c', 'ř': 'r', 'ž': 'z', 'ý': 'y', 'á': 'a', 'í': 'i', 'é': 'e', 'ě': 'e', 'ů': 'u', 'ú': 'u',
    'Š': 's', 'Č': 'c', 'Ř': 'r', 'Ž': 'z', 'Ý': 'y', 'Á': 'a', 'Í': 'i', 'É': 'e', 'Ě': 'e', 'Ů': 'u', 'Ú': 'u',
    'ë': 'e', 'è': 'e', 'ê': 'e', 'à': 'a', 'â': 'a', 'ç': 'c', 'ñ': 'n', 'ô': 'o', 'î': 'i', 'ï': 'i',
    'Ë': 'e', 'È': 'e', 'Ê': 'e', 'À': 'a', 'Â': 'a', 'Ç': 'c', 'Ñ': 'n', 'Ô': 'o', 'Î': 'i', 'Ï': 'i',
    '+': ' plus ',
}

POLA_PIASTY = ('pcd', 'center_bore', 'thread', 'fastener', 'torque_nm')


def slug(value):
    """Ta sama regula co dawmac_fit_slug() w api/fitment/lib/fitment.php."""
    value = (value or '').strip()
    value = ''.join(POLSKIE.get(z, z) for z in value)
    # Tylko A-Z, jak strtolower() w PHP 8 - str.lower() zmienialby tez
    # litery spoza ASCII i slug rozjechalby sie z tym w bazie.
    value = re.sub(r'[A-Z]', lambda z: z.group(0).lower(), value)
    value = re.sub(r'[^a-z0-9]+', '-', value)
    return value.strip('-')[:96]


class Zbior:
    def __init__(self, zrodlo):
        self.zrodlo = zrodlo          # np. "alukonfigurator.pl"
        self.marki = {}               # slug -> {'name', 'country', 'models': {slug -> {'name', 'generations': {slug -> gen}}}}
        self.konflikty = []

    def dodaj(self, marka, model, generacja, url, kraj=None):
        """generacja: dict w formacie importu (name, years, pcd, center_bore, thread, fastener, torque_nm, wheels)."""
        m = self.marki.setdefault(slug(marka), {'name': marka.strip(), 'country': kraj, 'models': {}})
        if kraj and not m['country']:
            m['country'] = kraj

        md = m['models'].setdefault(slug(model), {'name': model.strip(), 'generations': {}})

        g = dict(generacja)
        g['name'] = g['name'].strip()
        g['source'] = '%s: %s' % (self.zrodlo, url)
        g['verified'] = False
        g['wheels'] = list(g.get('wheels') or [])

        klucz = slug(g['name'])
        stara = md['generations'].get(klucz)

        if stara is None:
            md['generations'][klucz] = g
            return

        rozne = {p: (stara.get(p), g.get(p)) for p in POLA_PIASTY if stara.get(p) != g.get(p)}
        if rozne:
            self.konflikty.append({
                'auto': '%s %s %s' % (m['name'], md['name'], g['name']),
                'zostaje': stara['source'],
                'pominiete': g['source'],
                'roznice': rozne,
            })

        znane = {self._klucz_kola(k) for k in stara['wheels']}
        for k in g['wheels']:
            if self._klucz_kola(k) not in znane:
                stara['wheels'].append(k)
                znane.add(self._klucz_kola(k))

    def liczniki(self):
        modeli = sum(len(m['models']) for m in self.marki.values())
        generacji = sum(len(md['generations']) for m in self.marki.values() for md in m['models'].values())
        return {'marek': len(self.marki), 'modeli': modeli, 'generacji': generacji, 'konfliktow': len(self.konflikty)}

    def zapisz(self, katalog):
        """Jeden plik na marke (<slug>.json) + konflikty.json. Zwraca liste zapisanych plikow."""
        os.makedirs(katalog, exist_ok=True)
        pliki = []

        for s, m in sorted(self.marki.items()):
            dane = {
                'source': self.zrodlo,
                'makes': [{
                    'name': m['name'],
                    'country': m['country'],
                    'models': [
                        {'name': md['name'], 'generations': list(md['generations'].values())}
                        for _, md in sorted(m['models'].items())
                    ],
                }],
            }
            sciezka = os.path.join(katalog, s + '.json')
            self._zapisz_json(sciezka, dane)
            pliki.append(sciezka)

        if self.konflikty:
            sciezka = os.path.join(katalog, 'konflikty.json')
            self._zapisz_json(sciezka, self.konflikty)
            pliki.append(sciezka)

        return pliki

    @staticmethod
    def _klucz_kola(k):
        return (k.get('axle', 'both'), k.get('size'), k.get('tire'))

    @staticmethod
    def _zapisz_json(sciezka, dane):
        with open(sciezka + '.tmp', 'w', encoding='utf-8') as f:
            json.dump(dane, f, ensure_ascii=False, indent=1)
            f.write('\n')
        os.replace(sciezka + '.tmp', sciezka)
