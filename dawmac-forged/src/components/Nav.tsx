import { useState } from "react";
import { STORE_URL } from "../config";
import { useLang } from "../i18n";
import type { Lang } from "../i18n";

const HREFS = ["#cennik", "#kontakt"];

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
        <a
          href={STORE_URL}
          className="nav__link"
          target="_blank"
          rel="noopener noreferrer"
          onClick={() => setOpen(false)}
        >
          {t.navStore} ↗
        </a>
        <select
          className="nav__lang-select"
          value={lang}
          onChange={(e) => setLang(e.target.value as Lang)}
          aria-label="Język / Language"
        >
          <option value="pl">PL</option>
          <option value="en">EN</option>
          <option value="de">DE</option>
        </select>
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
