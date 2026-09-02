import type { MouseEvent } from "react";
import { GALLERY_FORGED_URL, STORE_URL } from "../config";
import { navigateTo } from "../hooks/useWheelRoute";
import type { Page } from "../hooks/useWheelRoute";
import { useLang } from "../i18n";

interface Props {
  page: Page;
}

/** Dolna nawigacja w stylu aplikacji — widoczna tylko na mobile (CSS .bnav, <=700px) */
export default function BottomNav({ page }: Props) {
  const { t } = useLang();

  const goHome = (e: MouseEvent, section?: string) => {
    e.preventDefault();
    if (page !== "home") {
      navigateTo("/", section ? { scrollTo: section } : {});
    } else if (section) {
      document.querySelector(section)?.scrollIntoView({ behavior: "smooth" });
    } else {
      window.scrollTo({ top: 0, behavior: "smooth" });
    }
  };

  const goCatalog = (e: MouseEvent) => {
    e.preventDefault();
    if (page !== "catalog") navigateTo("/katalog");
    else window.scrollTo({ top: 0, behavior: "smooth" });
  };

  return (
    <nav className="bnav" aria-label="Nawigacja mobilna">
      <a
        className={`bnav__item ${page === "home" ? "bnav__item--active" : ""}`}
        href="/"
        onClick={(e) => goHome(e)}
      >
        <span className="bnav__dot" />
        {t.bnavStart}
      </a>
      <a
        className={`bnav__item ${page === "catalog" ? "bnav__item--active" : ""}`}
        href="/katalog"
        onClick={goCatalog}
      >
        <span className="bnav__dot" />
        {t.bnavCatalog}
      </a>
      <a
        className={`bnav__item ${page === "d2" ? "bnav__item--active" : ""}`}
        href="/d2"
        onClick={(e) => {
          e.preventDefault();
          if (page !== "d2") navigateTo("/d2");
          else window.scrollTo({ top: 0, behavior: "smooth" });
        }}
      >
        <span className="bnav__dot" />
        D2
      </a>
      <a className="bnav__item" href="#cennik" onClick={(e) => goHome(e, "#cennik")}>
        <span className="bnav__dot" />
        {t.ctaPrice}
      </a>
      <a
        className="bnav__item"
        href={GALLERY_FORGED_URL}
        target="_blank"
        rel="noopener noreferrer"
      >
        <span className="bnav__dot" />
        {t.navGallery}
      </a>
      <a
        className="bnav__item"
        href={STORE_URL}
        target="_blank"
        rel="noopener noreferrer"
      >
        <span className="bnav__dot" />
        {t.navStore}
      </a>
    </nav>
  );
}
