import styles from "./Header.module.css";
import { Link, useLocation } from "react-router-dom";
import { GiHamburgerMenu } from "react-icons/gi";
import { useState } from "react";

export default function Header() {
  const location = useLocation();
  const isHome = location.pathname === "/";
  const [showMobileMenu, setShowMobileMenu] = useState(false);

  return (
    <>
      <header className={`${styles.header} ${isHome ? styles.fixed : ""}`}>
        <Link to="/">
          <img
            src="/logo/dawmac_logo_3.png"
            alt="Dawmac Logo"
            height={45}
            width={75}
            className={styles.logo}
          />
        </Link>

        <nav className={styles.desktop_nav}>
          <Link to="/wheel">Katalog</Link>
          <Link to="/pricing">Cennik</Link>
          <a
            href="https://dawmacpolska.pl/felgi-aluminiowe/s-for-dawmac-forged/dostepnosc-magazyn-dawmac/"
            target="_blank"
            rel="noreferrer"
          >
            Na magazynie
          </a>
          <Link to="/forged_facotry_stock">FORGED FACTORY STOCK</Link>
          <a href="https://dawmacpolska.pl" target="_blank" rel="noreferrer">
            Sklep
          </a>
          <a
            href="https://dawmacpolska.pl/kontakt/"
            target="_blank"
            rel="noreferrer"
          >
            Kontakt
          </a>
          <a
            href="https://galeria.dawmacpolska.pl/?Search=Dawmac+Forged"
            target="_blank"
            rel="noreferrer"
          >
            Galeria
          </a>
          <a
            href="https://youtube.com/playlist?list=PL0oSn87_uDoXnIUx0gDvlFdXGAfH_Yeqs&si=pE9scEFisobe1zPV"
            target="_blank"
            rel="noreferrer"
          >
            Youtube
          </a>
        </nav>

        <button
          className={styles.mobile_menu_icon}
          onClick={() => setShowMobileMenu(!showMobileMenu)}
        >
          <GiHamburgerMenu className={styles.mobile_menu_icon} />
        </button>
      </header>

      {showMobileMenu && (
        <div
          className={styles.not_navbar}
          onClick={() => setShowMobileMenu(false)}
        ></div>
      )}

      <div
        className={styles.mobile_nav_overlay}
        onClick={() => setShowMobileMenu(false)}
      />

      <nav
        className={`${styles.mobile_nav} ${showMobileMenu ? styles.open : ""} ${
          isHome ? styles.mobile_nav_margin_top : ""
        }`}
      >
        <Link to="/wheel">Katalog</Link>
        <Link to="/pricing">Cennik</Link>
        <a
          href="https://dawmacpolska.pl/felgi-aluminiowe/s-for-dawmac-forged/dostepnosc-magazyn-dawmac/"
          target="_blank"
          rel="noreferrer"
        >
          Na magazynie
        </a>
        <Link to="/forged_facotry_stock">FORGED FACTORY STOCK</Link>
        <a href="https://dawmacpolska.pl" target="_blank" rel="noreferrer">
          Sklep
        </a>
        <a
          href="https://dawmacpolska.pl/kontakt/"
          target="_blank"
          rel="noreferrer"
        >
          Kontakt
        </a>
        <a
          href="https://galeria.dawmacpolska.pl/?Search=Dawmac+Forged"
          target="_blank"
          rel="noreferrer"
        >
          Galeria
        </a>
        <a
          href="https://youtube.com/playlist?list=PL0oSn87_uDoXnIUx0gDvlFdXGAfH_Yeqs&si=pE9scEFisobe1zPV"
          target="_blank"
          rel="noreferrer"
        >
          Youtube
        </a>
      </nav>
    </>
  );
}
