import { useState } from "react";
import type { MouseEvent } from "react";
import { GALLERY_FORGED_URL, STORE_URL } from "../config";
import { navigateTo } from "../hooks/useWheelRoute";
import type { Page } from "../hooks/useWheelRoute";
import { useLang } from "../i18n";
import type { Lang } from "../i18n";

const HREFS = ["#cennik", "#kontakt"];

interface Props {
  page: Page;
}

export default function Nav({ page }: Props) {
  const { lang, setLang, t } = useLang();
  const [open, setOpen] = useState(false);
  const isD2 = page === "d2";

  // główny przełącznik oferty: FORGED (własna produkcja) / D2 (katalog D2 Forged)
  const setMode = (e: MouseEvent, d2: boolean) => {
    e.preventDefault();
    setOpen(false);
    if (d2 !== isD2) navigateTo(d2 ? "/d2" : "/");
  };

  // sekcje (#cennik/#kontakt) żyją na landingu — z katalogu najpierw wracamy na "/"
  const goSection = (e: MouseEvent, href: string) => {
    e.preventDefault();
    setOpen(false);
    if (window.location.pathname !== "/") {
      navigateTo("/", { scrollTo: href });
    } else {
      document.querySelector(href)?.scrollIntoView({ behavior: "smooth" });
    }
  };

  const goCatalog = (e: MouseEvent) => {
    e.preventDefault();
    setOpen(false);
    navigateTo("/katalog");
  };

  return (
    <header className="nav">
      {/* przełącznik ofert jest jednocześnie logotypem — bez osobnego logo obok */}
      <div className="nav__mode" role="group" aria-label="Oferta">
        <a
          href="/"
          className={`nav__mode-btn ${!isD2 ? "nav__mode-btn--active" : ""}`}
          onClick={(e) => setMode(e, false)}
        >
          DAWMAC <em>FORGED</em>
        </a>
        <span className="nav__mode-sep" />
        <a
          href="/d2"
          className={`nav__mode-btn ${isD2 ? "nav__mode-btn--active" : ""}`}
          onClick={(e) => setMode(e, true)}
        >
          <img className="nav__mode-logo" src="/d2/logo.png" alt="D2" /> <em>FORGED</em>
        </a>
      </div>

      <nav className={`nav__links ${open ? "nav__links--open" : ""}`}>
        {t.nav.map((label, i) => (
          <a
            key={HREFS[i]}
            href={HREFS[i]}
            className="nav__link"
            onClick={(e) => goSection(e, HREFS[i])}
          >
            {label}
          </a>
        ))}
        <a
          href={GALLERY_FORGED_URL}
          className="nav__link"
          target="_blank"
          rel="noopener noreferrer"
          onClick={() => setOpen(false)}
        >
          {t.navGallery} ↗
        </a>
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

      <a href="/katalog" className="nav__cta" onClick={goCatalog}>
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
