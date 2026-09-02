import { useEffect, useRef } from "react";
import { navigateTo } from "../hooks/useWheelRoute";
import { useLang } from "../i18n";

// scroll-driven image sequence: 101 klatek webp (frame_001..frame_101),
// obrót auta przód->tył sterowany postępem scrolla sekcji ~400vh
const FIRST_FRAME = 1;
const FRAME_COUNT = 101;
const LERP = 0.12;

const frameSrc = (i: number) =>
  `/car-seq/frame_${String(FIRST_FRAME + i).padStart(3, "0")}.webp`;

function Letters({ word, base }: { word: string; base: number }) {
  return (
    <>
      {word.split("").map((ch, i) => (
        <span
          key={i}
          className="letter"
          style={{ animationDelay: `${base + i * 55}ms` }}
        >
          {ch === "." ? <span className="dot">.</span> : ch}
        </span>
      ))}
    </>
  );
}

export default function Hero() {
  const { t } = useLang();
  const wrapRef = useRef<HTMLElement>(null);
  const canvasRef = useRef<HTMLCanvasElement>(null);
  const hintRef = useRef<HTMLSpanElement>(null);

  useEffect(() => {
    const wrap = wrapRef.current;
    const canvas = canvasRef.current;
    if (!wrap || !canvas) return;
    const ctx = canvas.getContext("2d");
    if (!ctx) return;

    const images: HTMLImageElement[] = [];
    let target = 0;
    let current = 0;
    let lastDrawn = -1;
    let raf = 0;
    let disposed = false;

    const reduced = window.matchMedia("(prefers-reduced-motion: reduce)").matches;

    // canvas w pikselach urządzenia (ostrość na retinie)
    const resize = () => {
      const dpr = Math.min(window.devicePixelRatio || 1, 2);
      const { clientWidth: w, clientHeight: h } = canvas;
      canvas.width = Math.round(w * dpr);
      canvas.height = Math.round(h * dpr);
      lastDrawn = -1; // wymuś przerysowanie po zmianie rozmiaru
      drawIndex(Math.round(current));
    };

    // rysowanie klatki jak object-fit: contain, na przezroczystym tle
    const drawIndex = (idx: number) => {
      const img = images[idx];
      if (!img || !img.complete || img.naturalWidth === 0) return;
      if (idx === lastDrawn) return;
      const cw = canvas.width;
      const ch = canvas.height;
      ctx.clearRect(0, 0, cw, ch);
      const s = Math.min(cw / img.naturalWidth, ch / img.naturalHeight);
      const w = img.naturalWidth * s;
      const h = img.naturalHeight * s;
      ctx.drawImage(img, (cw - w) / 2, (ch - h) / 2, w, h);
      lastDrawn = idx;
      canvas.dataset.frame = String(idx);
    };

    // postęp scrolla sekcji (0-1) -> docelowy indeks klatki
    const onScroll = () => {
      const rect = wrap.getBoundingClientRect();
      const total = wrap.offsetHeight - window.innerHeight;
      const p = Math.min(1, Math.max(0, -rect.top / Math.max(total, 1)));
      target = p * (FRAME_COUNT - 1);
      if (hintRef.current) {
        hintRef.current.style.opacity = p > 0.04 ? "0" : "1";
      }
    };

    const loop = () => {
      if (disposed) return;
      current += (target - current) * LERP;
      const idx = Math.round(current);
      if (idx !== lastDrawn) drawIndex(idx);
      raf = requestAnimationFrame(loop);
    };

    // preload wszystkich klatek przed startem animacji
    const jobs: Promise<void>[] = [];
    const toLoad = reduced ? 1 : FRAME_COUNT;
    for (let i = 0; i < toLoad; i++) {
      const img = new Image();
      img.src = frameSrc(i);
      images[i] = img;
      jobs.push(
        new Promise((res) => {
          img.onload = () => res();
          img.onerror = () => res();
        })
      );
    }

    resize();
    window.addEventListener("resize", resize);

    // pierwsza klatka od razu po jej załadowaniu (nie czekamy na całość)
    jobs[0].then(() => drawIndex(0));

    if (!reduced) {
      Promise.all(jobs).then(() => {
        if (disposed) return;
        onScroll();
        window.addEventListener("scroll", onScroll, { passive: true });
        raf = requestAnimationFrame(loop);
      });
    }

    return () => {
      disposed = true;
      cancelAnimationFrame(raf);
      window.removeEventListener("scroll", onScroll);
      window.removeEventListener("resize", resize);
    };
  }, []);

  return (
    <section className="hero-seq" id="top" ref={wrapRef}>
      <div className="hero-seq__sticky">
        <div className="hero">
          <div>
            <div className="hero__eyebrow">
              <span className="hero__eyebrow-line" />
              <span className="hero__eyebrow-label">
                // Performance Forged · PL
              </span>
            </div>
            <h1 className="hero__title">
              <Letters word={t.hero1} base={100} />
              <br />
              <Letters word={t.hero2} base={100 + t.hero1.length * 55 + 80} />
            </h1>
            <p className="hero__sub">{t.heroSub}</p>
            <div className="hero__ctas">
              <a
                href="/katalog"
                className="btn-primary"
                onClick={(e) => {
                  e.preventDefault();
                  navigateTo("/katalog");
                }}
              >
                {t.ctaCat} →
              </a>
              <a href="#cennik" className="btn-ghost">
                {t.ctaPrice}
              </a>
            </div>
          </div>

          <div className="hero__visual">
            <div className="hero__glow" />
            <canvas ref={canvasRef} className="hero-canvas" />
            <span className="hero__fig">FIG.01 // FORGED</span>
            <span className="hero__dia">Ø 15-24"</span>
          </div>
        </div>
        <span ref={hintRef} className="hero-seq__hint">
          SCROLL ↓
        </span>
      </div>
    </section>
  );
}
