# -*- coding: utf-8 -*-
"""Grzeczne pobieranie stron do bazy dopasowan.

Zasady pilnowane w kodzie, zeby nikt nie musial o nich pamietac:

- robots.txt: adres zakazany dla naszego User-Agenta nie jest pobierany,
  a Crawl-delay z robots.txt wydluza przerwe miedzy zapytaniami,
- jedno zapytanie na `delay` sekund na host (domyslnie 1 s),
- 429 i 5xx: czekamy coraz dluzej i probujemy ponownie,
- 401/403 albo strona z captcha: STOP calego przebiegu. Blokad nie
  obchodzimy - to sygnal, ze serwer nie chce byc pobierany automatycznie,
- cache na dysku: przerwany przebieg wznawia sie bez ponownego pytania
  serwera o strony, ktore juz mamy.

Tylko biblioteka standardowa, zeby dzialalo tak samo w chmurze, na Macu
i na serwerze bez instalowania czegokolwiek.
"""
import hashlib
import json
import os
import time
import urllib.error
import urllib.request
import urllib.robotparser
from urllib.parse import urlsplit

USER_AGENT = 'DawmacFitmentBot/1.0 (+https://dawmac.pl)'

# Fragmenty stron "sprawdzamy, czy jestes czlowiekiem". Trafienie = stop.
ZNACZNIKI_CAPTCHA = ('g-recaptcha', 'h-captcha', 'hcaptcha.com', 'cf-chl-', 'challenge-platform', 'cf-turnstile')


class Zablokowane(Exception):
    """Serwer odmowil dostepu (401/403/captcha). Przebieg ma sie zatrzymac."""


class ZakazRobots(Exception):
    """robots.txt zabrania pobrania tego adresu."""


class Pobieracz:
    def __init__(self, cache_dir, delay=1.0, user_agent=USER_AGENT, timeout=30,
                 max_prob=4, odswiez=False, opener=None, sleep=time.sleep, clock=time.monotonic):
        self.cache_dir = cache_dir
        self.delay = float(delay)
        self.user_agent = user_agent
        self.timeout = timeout
        self.max_prob = max_prob
        self.odswiez = odswiez
        self.opener = opener or urllib.request.build_opener()
        self.sleep = sleep
        self.clock = clock
        self.robots = {}
        self.ostatnie = {}
        self.statystyki = {'z_sieci': 0, 'z_cache': 0}
        os.makedirs(cache_dir, exist_ok=True)

    # -- publiczne -------------------------------------------------------

    def get(self, url):
        """Tekst strony. Wyjatki: ZakazRobots, Zablokowane, urllib.error.HTTPError (404 itp.)."""
        plik = os.path.join(self.cache_dir, hashlib.sha1(url.encode('utf-8')).hexdigest() + '.json')

        if not self.odswiez and os.path.exists(plik):
            with open(plik, encoding='utf-8') as f:
                self.statystyki['z_cache'] += 1
                return json.load(f)['body']

        if not self.wolno(url):
            raise ZakazRobots(url)

        body = self._pobierz(url)

        with open(plik + '.tmp', 'w', encoding='utf-8') as f:
            json.dump({'url': url, 'pobrano': time.strftime('%Y-%m-%dT%H:%M:%S'), 'body': body}, f, ensure_ascii=False)
        os.replace(plik + '.tmp', plik)

        return body

    def get_json(self, url):
        return json.loads(self.get(url))

    def wolno(self, url):
        return self._robots(url).can_fetch(self.user_agent, url)

    # -- wewnetrzne ------------------------------------------------------

    def _robots(self, url):
        czesci = urlsplit(url)
        host = czesci.scheme + '://' + czesci.netloc

        if host not in self.robots:
            rp = urllib.robotparser.RobotFileParser()
            try:
                rp.parse(self._pobierz(host + '/robots.txt', robots=True).splitlines())
            except urllib.error.HTTPError as e:
                # Brak robots.txt (404 i inne 4xx) = brak zakazow.
                # 401/403/429 i 5xx traktujemy jak zakaz wszystkiego - tak jak
                # urllib.robotparser i RFC 9309 dla niedostepnego pliku.
                if e.code in (401, 403, 429) or e.code >= 500:
                    rp.disallow_all = True
                else:
                    rp.allow_all = True
            self.robots[host] = rp

        return self.robots[host]

    def _czekaj(self, host):
        przerwa = self.delay
        rp = self.robots.get(host)
        if rp is not None:
            crawl = rp.crawl_delay(self.user_agent)
            if crawl:
                przerwa = max(przerwa, float(crawl))

        ostatnie = self.ostatnie.get(host)
        if ostatnie is not None:
            zostalo = przerwa - (self.clock() - ostatnie)
            if zostalo > 0:
                self.sleep(zostalo)
        self.ostatnie[host] = self.clock()

    def _pobierz(self, url, robots=False):
        czesci = urlsplit(url)
        host = czesci.scheme + '://' + czesci.netloc
        req = urllib.request.Request(url, headers={
            'User-Agent': self.user_agent,
            'Accept-Language': 'pl,en;q=0.8',
        })

        for proba in range(self.max_prob + 1):
            self._czekaj(host)
            try:
                with self.opener.open(req, timeout=self.timeout) as resp:
                    body = resp.read().decode(resp.headers.get_content_charset() or 'utf-8', errors='replace')
                self.statystyki['z_sieci'] += 1
                if not robots and any(z in body for z in ZNACZNIKI_CAPTCHA):
                    raise Zablokowane('captcha na ' + url)
                return body
            except urllib.error.HTTPError as e:
                if e.code in (401, 403) and not robots:
                    raise Zablokowane('%d na %s' % (e.code, url))
                if (e.code == 429 or e.code >= 500) and proba < self.max_prob:
                    # Retry-After w sekundach, jesli serwer go podal; inaczej 5, 10, 20, 40 s.
                    try:
                        odczekaj = float(e.headers.get('Retry-After', ''))
                    except (TypeError, ValueError):
                        odczekaj = 5 * (2 ** proba)
                    self.sleep(odczekaj)
                    continue
                raise
