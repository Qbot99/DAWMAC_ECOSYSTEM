# -*- coding: utf-8 -*-
"""Testy scrapera bez internetu: lokalny serwer HTTP udaje strone.

    python3 test_scraper.py
"""
import json
import os
import shutil
import subprocess
import tempfile
import threading
import unittest
import urllib.request
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer

from collector import Zbior, slug
from polite import Pobieracz, Zablokowane, ZakazRobots

TU = os.path.dirname(os.path.abspath(__file__))


class Strona(BaseHTTPRequestHandler):
    """Odpowiedzi ustawiane per test w Strona.trasy: sciezka -> (kod, tresc, naglowki)."""
    trasy = {}
    wizyty = []

    def do_GET(self):
        Strona.wizyty.append(self.path)
        kod, tresc, naglowki = Strona.trasy.get(self.path, (404, 'brak', {}))
        if callable(tresc):
            kod, tresc, naglowki = tresc()
        self.send_response(kod)
        for k, v in naglowki.items():
            self.send_header(k, v)
        self.send_header('Content-Type', 'text/html; charset=utf-8')
        self.end_headers()
        self.wfile.write(tresc.encode('utf-8'))

    def log_message(self, *args):
        pass


class Zegar:
    """Udawany czas: sleep() przesuwa zegar zamiast czekac naprawde."""
    def __init__(self):
        self.t = 1000.0
        self.uspienia = []

    def clock(self):
        return self.t

    def sleep(self, s):
        self.uspienia.append(round(s, 3))
        self.t += s


class TestPobieracz(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.serwer = ThreadingHTTPServer(('127.0.0.1', 0), Strona)
        threading.Thread(target=cls.serwer.serve_forever, daemon=True).start()
        cls.baza = 'http://127.0.0.1:%d' % cls.serwer.server_address[1]

    @classmethod
    def tearDownClass(cls):
        cls.serwer.shutdown()

    def setUp(self):
        self.tmp = tempfile.mkdtemp()
        self.zegar = Zegar()
        Strona.wizyty = []
        Strona.trasy = {'/robots.txt': (200, 'User-agent: *\nDisallow: /prywatne/\n', {})}

    def tearDown(self):
        shutil.rmtree(self.tmp)

    def pobieracz(self, **kw):
        # Bez proxy: lokalny serwer nie moze isc przez bramke sieciowa.
        opener = urllib.request.build_opener(urllib.request.ProxyHandler({}))
        return Pobieracz(self.tmp, opener=opener, sleep=self.zegar.sleep, clock=self.zegar.clock, **kw)

    def test_robots_zabrania_i_nie_pyta_serwera(self):
        p = self.pobieracz()
        with self.assertRaises(ZakazRobots):
            p.get(self.baza + '/prywatne/auta')
        self.assertNotIn('/prywatne/auta', Strona.wizyty)

    def test_przerwa_miedzy_zapytaniami(self):
        Strona.trasy['/a'] = (200, 'A', {})
        Strona.trasy['/b'] = (200, 'B', {})
        p = self.pobieracz(delay=1.5)
        p.get(self.baza + '/a')
        p.get(self.baza + '/b')
        # robots.txt, /a, /b - kazde kolejne zapytanie czeka 1.5 s.
        self.assertEqual(self.zegar.uspienia, [1.5, 1.5])

    def test_crawl_delay_z_robots_wydluza_przerwe(self):
        Strona.trasy['/robots.txt'] = (200, 'User-agent: *\nCrawl-delay: 5\n', {})
        Strona.trasy['/a'] = (200, 'A', {})
        Strona.trasy['/b'] = (200, 'B', {})
        p = self.pobieracz(delay=1)
        p.get(self.baza + '/a')
        p.get(self.baza + '/b')
        self.assertEqual(self.zegar.uspienia[-1], 5.0)

    def test_cache_nie_pyta_drugi_raz(self):
        Strona.trasy['/a'] = (200, 'A', {})
        p = self.pobieracz()
        self.assertEqual(p.get(self.baza + '/a'), 'A')
        self.assertEqual(self.pobieracz().get(self.baza + '/a'), 'A')
        self.assertEqual(Strona.wizyty.count('/a'), 1)

    def test_429_czeka_retry_after_i_ponawia(self):
        odpowiedzi = [(429, 'wolniej', {'Retry-After': '7'}), (200, 'OK', {})]
        Strona.trasy['/a'] = (200, lambda: odpowiedzi.pop(0), {})
        p = self.pobieracz()
        self.assertEqual(p.get(self.baza + '/a'), 'OK')
        self.assertIn(7.0, self.zegar.uspienia)

    def test_403_zatrzymuje(self):
        Strona.trasy['/a'] = (403, 'nie', {})
        with self.assertRaises(Zablokowane):
            self.pobieracz().get(self.baza + '/a')

    def test_captcha_zatrzymuje_i_nie_trafia_do_cache(self):
        Strona.trasy['/a'] = (200, '<div class="g-recaptcha"></div>', {})
        with self.assertRaises(Zablokowane):
            self.pobieracz().get(self.baza + '/a')
        self.assertEqual([f for f in os.listdir(self.tmp) if f.endswith('.json')], [])

    def test_robots_403_to_zakaz_wszystkiego(self):
        Strona.trasy['/robots.txt'] = (403, 'nie', {})
        Strona.trasy['/a'] = (200, 'A', {})
        with self.assertRaises(ZakazRobots):
            self.pobieracz().get(self.baza + '/a')

    def test_brak_robots_to_brak_zakazow(self):
        del Strona.trasy['/robots.txt']
        Strona.trasy['/a'] = (200, 'A', {})
        self.assertEqual(self.pobieracz().get(self.baza + '/a'), 'A')


GOLF = {
    'name': 'VIII', 'years': [2019, None], 'pcd': '5x112', 'center_bore': 57.1,
    'thread': 'M14x1.5', 'fastener': 'bolt', 'torque_nm': 140,
    'wheels': [{'size': '6.5Jx16 ET46', 'tire': '205/55 R16', 'oem': True}],
}


class TestZbior(unittest.TestCase):
    def setUp(self):
        self.tmp = tempfile.mkdtemp()

    def tearDown(self):
        shutil.rmtree(self.tmp)

    def test_zrodlo_i_niezweryfikowane(self):
        z = Zbior('przyklad.pl')
        z.dodaj('Volkswagen', 'Golf', dict(GOLF, verified=True), 'https://przyklad.pl/vw/golf')
        g = z.marki['volkswagen']['models']['golf']['generations']['viii']
        self.assertEqual(g['source'], 'przyklad.pl: https://przyklad.pl/vw/golf')
        self.assertIs(g['verified'], False)

    def test_powtorzenie_sumuje_rozmiary(self):
        z = Zbior('przyklad.pl')
        z.dodaj('Volkswagen', 'Golf', GOLF, 'u1')
        z.dodaj('Volkswagen', 'Golf', dict(GOLF, wheels=[
            {'size': '6.5Jx16 ET46', 'tire': '205/55 R16', 'oem': True},
            {'size': '7.5Jx17 ET51', 'tire': '225/45 R17'},
        ]), 'u2')
        g = z.marki['volkswagen']['models']['golf']['generations']['viii']
        self.assertEqual([k['size'] for k in g['wheels']], ['6.5Jx16 ET46', '7.5Jx17 ET51'])
        self.assertEqual(z.konflikty, [])

    def test_rozne_dane_piasty_to_konflikt(self):
        z = Zbior('przyklad.pl')
        z.dodaj('Volkswagen', 'Golf', GOLF, 'u1')
        z.dodaj('Volkswagen', 'Golf', dict(GOLF, center_bore=57.0), 'u2')
        self.assertEqual(len(z.konflikty), 1)
        self.assertEqual(z.konflikty[0]['roznice'], {'center_bore': (57.1, 57.0)})
        g = z.marki['volkswagen']['models']['golf']['generations']['viii']
        self.assertEqual(g['center_bore'], 57.1)

    def test_wynik_przechodzi_walidacje_importu_php(self):
        z = Zbior('przyklad.pl')
        z.dodaj('Volkswagen', 'Golf', GOLF, 'u1', kraj='DE')
        z.dodaj('Škoda', 'Octavia', dict(GOLF, name='IV', years=[2020, None], wheels=[]), 'u2', kraj='CZ')
        pliki = z.zapisz(self.tmp)
        wynik = subprocess.run(['php', os.path.join(TU, '..', 'import_fitment.php')] + pliki,
                               capture_output=True, text=True)
        self.assertEqual(wynik.returncode, 0, wynik.stdout + wynik.stderr)
        self.assertIn('2 generacji', wynik.stdout)


class TestSlugZgodnyZPhp(unittest.TestCase):
    def test_te_same_slugi(self):
        przyklady = ['Mercedes-Benz', 'Škoda', ' VIII (CD1) ', 'Łódź Żółta', 'ID.4 GTX+',
                     'Citroën C4', 'Lynk & Co', 'Tank 300', 'İstanbul', 'Æro', '  ', 'BYD Atto 3']
        php = ("require '%s'; foreach (json_decode(stream_get_contents(STDIN)) as $v) "
               "echo dawmac_fit_slug($v), \"\\n\";") % os.path.join(TU, '..', '..', 'api', 'fitment', 'lib', 'fitment.php')
        wynik = subprocess.run(['php', '-r', php], input=json.dumps(przyklady), capture_output=True, text=True, check=True)
        self.assertEqual(wynik.stdout.split('\n')[:-1], [slug(v) for v in przyklady])


if __name__ == '__main__':
    unittest.main(verbosity=1)
