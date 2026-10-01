# -*- coding: utf-8 -*-
"""Testy importu z bazy SQLite alukonfiguratora - malutka baza budowana w tescie.

    python3 test_z_sqlite_alukonfigurator.py
"""
import json
import os
import shutil
import sqlite3
import subprocess
import tempfile
import unittest

from collector import Zbior
from z_sqlite_alukonfigurator import ZRODLO, konwertuj, pcd

TU = os.path.dirname(os.path.abspath(__file__))

SCHEMAT = """
CREATE TABLE type_groups(grp TEXT PRIMARY KEY, make TEXT, model TEXT, type TEXT, type_chain TEXT,
  prod_from TEXT, prod_to TEXT);
CREATE TABLE model_type_groups(make TEXT, model TEXT, grp TEXT);
CREATE TABLE versions(car_tag TEXT PRIMARY KEY, grp TEXT, prod_from TEXT, prod_to TEXT, bolt_pattern TEXT);
CREATE TABLE car_details(car_tag TEXT PRIMARY KEY, found INTEGER, grp TEXT, bolt_pattern TEXT,
  center_bore_front REAL, thread_size INTEGER, thread_pitch INTEGER);
CREATE TABLE car_cocs(car_tag TEXT, seq INTEGER, mixed INTEGER,
  tyre_width_f TEXT, tyre_aspect_f TEXT, diameter_f TEXT, rim_width_f TEXT, offset_f TEXT,
  tyre_width_r TEXT, tyre_aspect_r TEXT, diameter_r TEXT, rim_width_r TEXT, offset_r TEXT);
"""


def baza(sciezka):
    db = sqlite3.connect(sciezka)
    db.executescript(SCHEMAT)
    db.executemany('INSERT INTO type_groups VALUES (?,?,?,?,?,?,?)', [
        ('vw_golf_vii', 'VOLKSWAGEN', 'Golf VII', 'AU', 'Golf VII, Typ: AU, 1K', '2012-01-01', '2017-01-01'),
        ('bmw_i4', 'BMW', 'i4', 'G26', 'i4, Typ: G26', '2021-01-01', '0000-00-00'),
        ('vw_bez_gwintu', 'VOLKSWAGEN', 'Up', '121', 'Up, Typ: 121', '2011-01-01', '2020-01-01'),
        ('chevy', 'CHEVROLET', 'Camaro', 'X', 'Camaro, Typ: X', '2016-01-01', '2023-01-01'),
    ])
    db.executemany('INSERT INTO model_type_groups VALUES (?,?,?)', [
        ('VOLKSWAGEN', 'Golf VII', 'vw_golf_vii'), ('BMW', 'i4', 'bmw_i4'),
        ('VOLKSWAGEN', 'Up', 'vw_bez_gwintu'), ('CHEVROLET', 'Camaro', 'chevy'),
    ])
    db.executemany('INSERT INTO versions VALUES (?,?,?,?,?)', [
        ('g1', 'vw_golf_vii', '2012-01-01', '2016-01-01', '5/112'),
        ('g2', 'vw_golf_vii', '2013-01-01', '2017-01-01', '5/112'),
        ('b1', 'bmw_i4', '2021-01-01', '0000-00-00', '5/112'),
        ('u1', 'vw_bez_gwintu', '2011-01-01', '2020-01-01', '4/100'),
        ('c1', 'chevy', '2016-01-01', '2023-01-01', '5/120'),
    ])
    db.executemany('INSERT INTO car_details VALUES (?,?,?,?,?,?,?)', [
        ('g1', 1, 'vw_golf_vii', '5/112', 57.1, 14, 150),
        ('b1', 1, 'bmw_i4', '5/112', 66.6, 14, 125),
        ('u1', 1, 'vw_bez_gwintu', '4/100', 57.1, 0, 0),
        ('c1', 1, 'chevy', '5/120', 66.9, 14, 150),
    ])
    db.executemany('INSERT INTO car_cocs VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)', [
        # dobry rozmiar, ten sam w drugiej wersji (bez powtorzen w wyniku)
        ('g1', 1, 0, '205', '55', '16', '6,50', '46,00', '', '', '', '', ''),
        ('g2', 1, 0, '205', '55', '16', '6,50', '46,00', '', '', '', '', ''),
        # brak ET i ET z dwoma miejscami po przecinku - wypadaja, generacja zostaje
        ('g2', 2, 0, '225', '45', '17', '7,50', '', '', '', '', '', ''),
        ('g2', 5, 0, '225', '45', '17', '7,50', '49,25', '', '', '', '', ''),
        # ET z polowka mm zostaje
        ('g2', 3, 0, '225', '40', '18', '7,50', '49,50', '', '', '', '', ''),
        # opona bez profilu - felga zostaje bez opony
        ('g2', 4, 0, '175', '', '14', '5,50', '35,00', '', '', '', '', ''),
        # zestaw mieszany: przod i tyl osobno
        ('b1', 1, 1, '245', '40', '19', '8,50', '30,00', '255', '40', '19', '8,50', '35,00'),
    ])
    db.commit()
    db.close()


class TestZSqlite(unittest.TestCase):
    def setUp(self):
        self.tmp = tempfile.mkdtemp()
        self.plik = os.path.join(self.tmp, 'alu.sqlite')
        baza(self.plik)
        self.zbior = Zbior(ZRODLO)
        self.liczniki = konwertuj(self.plik, self.zbior)

    def tearDown(self):
        shutil.rmtree(self.tmp)

    def generacja(self, marka, model):
        gens = self.zbior.marki[marka]['models'][model]['generations']
        self.assertEqual(len(gens), 1)
        return list(gens.values())[0]

    def test_golf_piasta_lata_i_felgi(self):
        g = self.generacja('volkswagen', 'golf-vii')
        self.assertEqual(g['name'], 'AU, 1K (2012-2017)')
        self.assertEqual(g['years'], [2012, 2017])
        self.assertEqual((g['pcd'], g['center_bore'], g['thread']), ('5x112', 57.1, 'M14x1.5'))
        self.assertIsNone(g['fastener'])
        self.assertIsNone(g['torque_nm'])
        self.assertFalse(g['verified'])
        self.assertEqual(g['source'], ZRODLO + ': typ vw_golf_vii')
        self.assertEqual(g['wheels'], [
            {'size': '6.5Jx16 ET46', 'oem': True, 'tire': '205/55 R16'},
            {'size': '7.5Jx18 ET49.5', 'oem': True, 'tire': '225/40 R18'},
            {'size': '5.5Jx14 ET35', 'oem': True},
        ])

    def test_produkcja_trwa_i_zestaw_mieszany(self):
        g = self.generacja('bmw', 'i4')
        self.assertEqual(g['years'], [2021, None])
        self.assertEqual(g['thread'], 'M14x1.25')
        self.assertEqual([(k.get('axle'), k['size']) for k in g['wheels']],
                         [('front', '8.5Jx19 ET30'), ('rear', '8.5Jx19 ET35')])

    def test_pominiete_i_liczniki(self):
        self.assertNotIn('up', self.zbior.marki['volkswagen']['models'])
        self.assertNotIn('chevrolet', self.zbior.marki)
        self.assertEqual(self.liczniki['pominiete: brak gwintu'], 1)
        self.assertEqual(self.liczniki['pominiete: rozstaw niejednoznaczny (120 albo 120.65)'], 1)
        self.assertEqual(self.liczniki['felga odrzucona: brak ET'], 1)
        self.assertEqual(self.liczniki['felga odrzucona: ET z wiecej niz 1 miejscem po przecinku'], 1)
        self.assertEqual(self.liczniki['przeniesione'], 2)

    def test_obciety_rozstaw(self):
        self.assertEqual(pcd('5/114', 'TOYOTA'), ('5x114.3', None))
        self.assertEqual(pcd('6/139', 'TOYOTA'), ('6x139.7', None))
        self.assertEqual(pcd('5/120', 'BMW'), ('5x120', None))
        self.assertEqual(pcd('15/130', 'PORSCHE')[0], None)

    def test_wynik_przechodzi_walidacje_importu_php(self):
        if not shutil.which('php'):
            self.skipTest('brak php')
        pliki = [p for p in self.zbior.zapisz(os.path.join(self.tmp, 'out')) if not p.endswith('konflikty.json')]
        wynik = subprocess.run(['php', os.path.join(TU, '..', 'import_fitment.php')] + pliki,
                               capture_output=True, text=True)
        self.assertEqual(wynik.returncode, 0, wynik.stdout + wynik.stderr)
        self.assertIn('2 generacji', wynik.stdout)
        with open(pliki[0], encoding='utf-8') as f:
            self.assertEqual(json.load(f)['source'], ZRODLO)


if __name__ == '__main__':
    unittest.main()
