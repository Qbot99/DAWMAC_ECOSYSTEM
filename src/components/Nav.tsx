import { useEffect, useRef, useState } from "react";
import { DotLottie } from "@lottiefiles/dotlottie-web";
import { STORE_URL } from "../config";

// renderer WASM hostowany lokalnie (kopia w public/, bez zależności od CDN);
// przy aktualizacji @lottiefiles/dotlottie-web trzeba odświeżyć public/dotlottie-player.wasm
DotLottie.setWasmUrl("/dotlottie-player.wasm");
import { useLang } from "../i18n";
import type { Lang } from "../i18n";

const HREFS = ["#cennik", "#kontakt"];
const INTRO_MS = 2000; // czas przejazdu koła przez belkę menu

export default function Nav() {
  const { lang, setLang, t } = useLang();
  const [open, setOpen] = useState(false);
  // animacja wejściowa: logo -> przejeżdżające koło -> menu wyłania się za nim
  const [intro, setIntro] = useState(
    () => !sessionStorage.getItem("dawmacNavIntro")
  );
  const canvasRef = useRef<HTMLCanvasElement>(null);

  useEffect(() => {
    if (!intro) return;
    let lottie: DotLottie | null = null;
    if (canvasRef.current) {
      lottie = new DotLottie({
        canvas: canvasRef.current,
        src: "/wheel.lottie",
        loop: true,
        autoplay: true,
      });
    }
    const t1 = setTimeout(() => {
      sessionStorage.setItem("dawmacNavIntro", "1");
      setIntro(false);
    }, INTRO_MS + 400);
    return () => {
      clearTimeout(t1);
      lottie?.destroy();
    };
  }, [intro]);

  // elementy menu odsłaniają się kolejno, w rytmie przejazdu koła
  const revealStyle = (i: number) =>
    intro
      ? { animation: `navReveal .5s ease ${350 + i * 260}ms both` }
      : undefined;

  return (
    <header className="nav">
      {intro && (
        <div className="nav__wheel" aria-hidden="true">
          <canvas ref={canvasRef} width={140} height={140} />
        </div>
      )}

      <a
        href="#top"
        className="nav__brand"
        style={intro ? { animation: "fadeIn .4s ease both" } : undefined}
      >
        <span className="nav__logo">DAWMAC</span>
        <span className="nav__badge">Forged</span>
      </a>

      <nav className={`nav__links ${open ? "nav__links--open" : ""}`}>
        {t.nav.map((label, i) => (
          <a
            key={HREFS[i]}
            href={HREFS[i]}
            className="nav__link"
            style={revealStyle(i)}
            onClick={() => setOpen(false)}
          >
            {label}
          </a>
        ))}
        <a
          href={STORE_URL}
          className="nav__link"
          style={revealStyle(2)}
          target="_blank"
          rel="noopener noreferrer"
          onClick={() => setOpen(false)}
        >
          {t.navStore} ↗
        </a>
        <select
          className="nav__lang-select"
          style={revealStyle(3)}
          value={lang}
          onChange={(e) => setLang(e.target.value as Lang)}
          aria-label="Język / Language"
        >
          <option value="pl">PL</option>
          <option value="en">EN</option>
          <option value="de">DE</option>
        </select>
      </nav>

      <a href="#katalog" className="nav__cta" style={revealStyle(4)}>
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
