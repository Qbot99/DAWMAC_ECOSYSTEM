import { useEffect, useRef } from "react";
import { useLang } from "../i18n";

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
  const imgRef = useRef<HTMLImageElement>(null);
  const glowRef = useRef<HTMLDivElement>(null);
  const ringRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    const onMove = (e: MouseEvent) => {
      const dx = e.clientX / window.innerWidth - 0.5;
      const dy = e.clientY / window.innerHeight - 0.5;
      if (imgRef.current)
        imgRef.current.style.transform = `translate(${dx * 18}px, ${dy * 12}px)`;
      if (glowRef.current)
        glowRef.current.style.transform = `translate(${dx * 34}px, ${dy * 26}px)`;
      if (ringRef.current)
        ringRef.current.style.transform = `translate(${dx * -14}px, ${dy * -10}px)`;
    };
    window.addEventListener("mousemove", onMove);
    return () => window.removeEventListener("mousemove", onMove);
  }, []);

  return (
    <section className="hero" id="top">
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
          <a href="#katalog" className="btn-primary">
            {t.ctaCat} →
          </a>
          <a href="#cennik" className="btn-ghost">
            {t.ctaPrice}
          </a>
        </div>
        <div className="hero__stats">
          <div className="hero__stat">
            <div className="hero__stat-val">18</div>
            <div className="hero__stat-label">{t.statYears}</div>
          </div>
          <div className="hero__stat">
            <div className="hero__stat-val">6061</div>
            <div className="hero__stat-label">{t.statAlloy}</div>
          </div>
          <div className="hero__stat">
            <div className="hero__stat-val">15-24"</div>
            <div className="hero__stat-label">{t.statSizes}</div>
          </div>
        </div>
      </div>

      <div className="hero__visual">
        <div ref={ringRef} className="hero__ring" />
        <div ref={glowRef} className="hero__glow" />
        <img
          ref={imgRef}
          className="hero__img"
          src="/wheel-hero.png"
          alt="Dawmac Forged - felga kuta"
          fetchPriority="high"
        />
        <span className="hero__fig">FIG.01 // FORGED</span>
        <span className="hero__dia">Ø 15-24"</span>
      </div>
    </section>
  );
}
