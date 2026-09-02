# Dawmac Forged — forged.dawmacpolska.pl

Strona felg kutych Dawmac: landing + katalog Forged (dane na żywo z API) oraz
katalog D2 Forged (642 modele, dane w repo). React 19 + TypeScript + Vite,
bez biblioteki routingu (własny router na History API).

## Uruchomienie lokalnie

```bash
npm install
npm run dev          # http://localhost:5173
```

Build produkcyjny i sprawdzenie typów:

```bash
npx vite build                              # ~2 s -> dist/
npx tsc -p tsconfig.app.json --noEmit       # typy osobno
```

> Uwaga: `npm run build` uruchamia też `tsc -b`, który potrafi się zawieszać.
> Do wdrożeń używaj `npx vite build`.
>
> Projekt musi leżeć **poza iCloud Drive** (`~/Documents` jest synchronizowane) —
> w iCloud build trwał 16 minut zamiast 2 sekund, a `git` zawieszał się na minuty.

## Wdrożenie na serwer

Katalog domeny na serwerze: `forged.dawmacpolska.pl/public_html/`.

1. `npx vite build`
2. Wgraj **zawartość `dist/`** (`index.html` + `assets/` + wszystkie pliki z `public/`)
3. Wgraj pliki serwerowe z `server/`: `bot.php`, `send_form.php`, `sitemap.php`
4. ⚠️ **`.htaccess`** (plik ukryty!) — bez niego nie działają adresy `/katalog`,
   `/cennik`, `/wheel/*` ani podglądy linków na WhatsApp. Jest w dwóch miejscach:
   `public/.htaccess` (trafia do `dist/`) i `server/.htaccess`. W kliencie FTP
   włącz pokazywanie plików ukrytych i sprawdź, czy się wgrał.

Po wdrożeniu warto sprawdzić: `/cennik`, `/katalog`, `/d2`, `/wheel/FM517`,
podgląd linku felgi na WhatsApp, wysyłkę formularza.

## Co robią pliki PHP

| Plik | Rola |
|---|---|
| `bot.php` | Podglądy linków (Open Graph) dla WhatsApp/FB — `.htaccess` kieruje tu boty. Dla `/wheel/NAZWA` dociąga zdjęcie felgi z API; rozumie też stare nazwy (`legacy_name`). |
| `send_form.php` | Formularz kontaktowy → `dawmacpolska@gmail.com`. Telefon wymagany, honeypot + rate-limit. |
| `sitemap.php` | Sitemapa generowana z API (strona główna, `/katalog`, `/cennik` + adres każdej felgi). |

## Zależności zewnętrzne (nie ma ich w tym repo)

- `api.dawmacpolska.pl` — katalog felg (`/api/forged/list_wheels.php`) i **zdjęcia felg**
  (`/forged/...`). Kod API i panelu admina: katalog `dawmac-api` w tym monorepo.
- `galeria.dawmacpolska.pl` — realizacje na autach (API galerii).
- `dawmac.pl/galeria/` — galeria dla użytkownika; zakładka „Galeria" linkuje do
  wersji przefiltrowanej: `?q=forged`.
- `prices.json` (w `public/`) — cennik; edytowany ręcznie, wgrywany razem z buildem.

Repo zawiera natomiast wszystkie własne zasoby: 101 klatek animacji auta
(`public/car-seq`), zdjęcia i dane 642 modeli D2 (`public/d2`,
`src/data/d2-models.json`), zdjęcia serii i favicon.

## Struktura

```
src/
  App.tsx              układ strony + wybór widoku (landing / katalog / D2)
  hooks/useWheelRoute  routing: "/", "/katalog", "/cennik", "/d2",
                         "/wheel/:nazwa", "/d2/wheel/:nazwa"
  i18n.tsx             teksty PL / EN / DE (jedyne miejsce do zmiany treści)
  config.ts            adresy API, dane kontaktowe, linki zewnętrzne
  data/                pobieranie danych z API + dane D2
  components/          sekcje strony i lightboxy
  styles/forged.css    całość stylów (bez frameworka CSS)
public/                zasoby statyczne wgrywane 1:1 na serwer
server/                pliki PHP + .htaccess (wgrywane osobno)
```

## Uwagi

- Ceny: serie 1 i 2 mają tabele w cenniku; 3-częściowe i magnezowe → wycena
  indywidualna. Ceny poczty lotniczej są w `prices.json`, ale **celowo nie są
  pokazywane** na stronie.
- Treści zmienia się wyłącznie w `src/i18n.tsx` (trzy języki obok siebie).
