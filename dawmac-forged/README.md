# Dawmac Forged

Strona forged.dawmacpolska.pl - React + TypeScript + Vite.

## Budowanie i wdrożenie

```bash
npm ci
npm run build     # wynik ląduje w dist/ (nie trafia do repo)
./deploy.sh       # build + wysyłka na serwer
```

## Co siedzi w public/

Vite kopiuje ten katalog do builda bez zmian, więc trzymamy tu wszystko,
co ma wylądować na serwerze obok aplikacji:

| Plik | Do czego |
|---|---|
| `.htaccess` | przepisywanie adresów, kierowanie botów do bot.php |
| `bot.php`, `bot2.php` | podglądy linków na Facebooku, WhatsAppie, Twitterze |
| `send_form.php` | obsługa formularza kontaktowego |
| `sitemap.php`, `sitemap2.php` | mapy witryny |
| `robots.txt` | reguły dla robotów |

## Czego tu nie ma

`dist/` - powstaje z builda.

## Co żyje wyłącznie na serwerze

Obok aplikacji leżą rzeczy, których build nie odtworzy i których nie ma
w repo: druga wersja strony (`index2.html`, `assets2/`, `d2/` - 62 MB),
sekwencja klatek `car-seq/` i generowany `sitemap-cache.xml`.
Dlatego `deploy.sh` wysyła bez `--delete`.

---

# React + TypeScript + Vite

This template provides a minimal setup to get React working in Vite with HMR and some ESLint rules.

Currently, two official plugins are available:

- [@vitejs/plugin-react](https://github.com/vitejs/vite-plugin-react/blob/main/packages/plugin-react) uses [Babel](https://babeljs.io/) for Fast Refresh
- [@vitejs/plugin-react-swc](https://github.com/vitejs/vite-plugin-react/blob/main/packages/plugin-react-swc) uses [SWC](https://swc.rs/) for Fast Refresh

## Expanding the ESLint configuration

If you are developing a production application, we recommend updating the configuration to enable type-aware lint rules:

```js
export default tseslint.config([
  globalIgnores(['dist']),
  {
    files: ['**/*.{ts,tsx}'],
    extends: [
      // Other configs...

      // Remove tseslint.configs.recommended and replace with this
      ...tseslint.configs.recommendedTypeChecked,
      // Alternatively, use this for stricter rules
      ...tseslint.configs.strictTypeChecked,
      // Optionally, add this for stylistic rules
      ...tseslint.configs.stylisticTypeChecked,

      // Other configs...
    ],
    languageOptions: {
      parserOptions: {
        project: ['./tsconfig.node.json', './tsconfig.app.json'],
        tsconfigRootDir: import.meta.dirname,
      },
      // other options...
    },
  },
])
```

You can also install [eslint-plugin-react-x](https://github.com/Rel1cx/eslint-react/tree/main/packages/plugins/eslint-plugin-react-x) and [eslint-plugin-react-dom](https://github.com/Rel1cx/eslint-react/tree/main/packages/plugins/eslint-plugin-react-dom) for React-specific lint rules:

```js
// eslint.config.js
import reactX from 'eslint-plugin-react-x'
import reactDom from 'eslint-plugin-react-dom'

export default tseslint.config([
  globalIgnores(['dist']),
  {
    files: ['**/*.{ts,tsx}'],
    extends: [
      // Other configs...
      // Enable lint rules for React
      reactX.configs['recommended-typescript'],
      // Enable lint rules for React DOM
      reactDom.configs.recommended,
    ],
    languageOptions: {
      parserOptions: {
        project: ['./tsconfig.node.json', './tsconfig.app.json'],
        tsconfigRootDir: import.meta.dirname,
      },
      // other options...
    },
  },
])
```
