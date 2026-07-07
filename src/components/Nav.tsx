import { useState } from "react";
import { useLang } from "../i18n";
import type { Lang } from "../i18n";

const HREFS = ["#katalog", "#technologie", "#cennik", "#dlaczego", "#kontakt"];
const LANGS: Lang[] = ["pl", "en", "de"];

export default function Nav() {
  const { lang, setLang, t } = useLang();
  const [open, setOpen] = useState(false);

  return (
    <header className="nav">
      <a href="#top" className="nav__brand">
        <span className="nav__logo">DAWMAC</span>
        <span className="nav__badge">Forged</span>
      </a>

      <nav className={`nav__links ${open ? "nav__links--open" : ""}`}>
        {t.nav.map((label, i) => (
          <a
            key={HREFS[i]}
            href={HREFS[i]}
            className="nav__link"
            onClick={() => setOpen(false)}
          >
            {label}
          </a>
        ))}
        <div className="nav__lang">
          {LANGS.map((l) => (
            <button
              key={l}
              className={`nav__lang-btn ${lang === l ? "nav__lang-btn--active" : ""}`}
              onClick={() => setLang(l)}
            >
              {l.toUpperCase()}
            </button>
          ))}
        </div>
      </nav>

      <a href="#katalog" className="nav__cta">
        {t.ctaCat}
      </a>
      <button
        className="nav__burger"
        aria-label="Menu"
        onClick={() => setOpen(!open)}
      >
        {open ? "✕" : "≡"}
      </button>
    </header>
  );
}
