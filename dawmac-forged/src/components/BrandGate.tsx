import { useEffect, useRef, useState } from "react";
import { useLang } from "../i18n";

interface Props {
  onPick: (mode: "forged" | "d2") => void;
}

const FRAME_COUNT = 101;
const START_FRAME = 50; // bok auta
const FRAME_MS = 80;

const frameSrc = (i: number) => `/car-seq/frame_${String(i + 1).padStart(3, "0")}.webp`;

/** Obracające się Audi: pętla po klatkach car-seq, pomija klatki jeszcze nie załadowane. */
function SpinningCar() {
  const imgRef = useRef<HTMLImageElement>(null);

  useEffect(() => {
    if (window.matchMedia("(prefers-reduced-motion: reduce)").matches) return;
    const loaded = new Array<boolean>(FRAME_COUNT).fill(false);
    const images: HTMLImageElement[] = [];
    for (let i = 0; i < FRAME_COUNT; i++) {
      const im = new Image();
      im.onload = () => (loaded[i] = true);
      im.src = frameSrc(i);
      images.push(im);
    }
    let frame = START_FRAME;
    const timer = setInterval(() => {
      let next = (frame + 1) % FRAME_COUNT;
      // przeskocz dziury w preloadzie (max jeden pełny obrót szukania)
      for (let hop = 0; hop < FRAME_COUNT && !loaded[next]; hop++) {
        next = (next + 1) % FRAME_COUNT;
      }
      if (loaded[next] && imgRef.current) {
        frame = next;
        imgRef.current.src = images[frame].src;
      }
    }, FRAME_MS);
    return () => clearInterval(timer);
  }, []);

  return (
    <img
      ref={imgRef}
      className="gate__car"
      src={frameSrc(START_FRAME)}
      alt=""
      loading="eager"
    />
  );
}

/**
 * Bramka wyboru oferty wg projektu z Claude Design: dwa panele hover-expand,
 * glow za kursorem, obracające się Audi (Dawmac Forged) i felga D2.
 * Pokazywana raz na sesję — logiką steruje App.
 */
export default function BrandGate({ onPick }: Props) {
  const { t, lang } = useLang();
  const [hover, setHover] = useState<"left" | "right" | null>(null);
  const [clock, setClock] = useState("");
  const glowLeftRef = useRef<HTMLDivElement>(null);
  const glowRightRef = useRef<HTMLDivElement>(null);
  const hoverRef = useRef(hover);
  hoverRef.current = hover;

  useEffect(() => {
    const locale = lang === "pl" ? "pl-PL" : lang === "de" ? "de-DE" : "en-GB";
    const tick = () =>
      setClock(`${new Date().toLocaleTimeString(locale)} / ${lang.toUpperCase()}`);
    tick();
    const timer = setInterval(tick, 1000);
    return () => clearInterval(timer);
  }, [lang]);

  const onMove = (e: React.MouseEvent) => {
    for (const [ref, side] of [
      [glowLeftRef, "left"],
      [glowRightRef, "right"],
    ] as const) {
      const el = ref.current;
      if (!el || !el.parentElement) continue;
      const r = el.parentElement.getBoundingClientRect();
      el.style.left = `${e.clientX - r.left - 260}px`;
      el.style.top = `${e.clientY - r.top - 260}px`;
      el.style.opacity = hoverRef.current === side ? "1" : "0";
    }
  };

  const panelCls = (side: "left" | "right") =>
    [
      "gate__panel",
      hover === side ? "gate__panel--hot" : "",
      hover && hover !== side ? "gate__panel--dim" : "",
    ].join(" ");

  return (
    <div className="gate" onMouseMove={onMove}>
      <div className="gate__pulse" />
      <div className="gate__grid" />
      <header className="gate__head">
        <span className="gate__logo">DAWMAC</span>
        <span className="gate__kicker">// {t.gateChoose}</span>
        <span className="gate__clock">{clock}</span>
      </header>
      <main className="gate__panels">
        <button
          className={panelCls("left")}
          onClick={() => onPick("forged")}
          onMouseEnter={() => setHover("left")}
          onMouseLeave={() => setHover(null)}
        >
          <div ref={glowLeftRef} className="gate__glow" />
          <span className="gate__tag">{t.gateForgedTag}</span>
          <div className="gate__stage">
            <ul className="gate__specs">
              {t.gateForgedSpecs.map((s) => (
                <li key={s}>— {s}</li>
              ))}
            </ul>
            <SpinningCar />
          </div>
          <div className="gate__body">
            <h1 className="gate__name">
              <span className="gate__name-w">DAWMAC </span>
              <span className="gate__name-r">FORGED</span>
            </h1>
            <p className="gate__sub">{t.gateForgedSub}</p>
            <span className="gate__enter">
              {t.gateEnter} <span className="gate__arrow">→</span>
            </span>
            <div className="gate__bar">
              <div className="gate__bar-fill" />
            </div>
          </div>
        </button>
        <button
          className={`${panelCls("right")} gate__panel--d2`}
          onClick={() => onPick("d2")}
          onMouseEnter={() => setHover("right")}
          onMouseLeave={() => setHover(null)}
        >
          <div ref={glowRightRef} className="gate__glow" />
          <span className="gate__tag">{t.gateD2Tag}</span>
          <div className="gate__stage">
            <ul className="gate__specs">
              {t.gateD2Specs.map((s) => (
                <li key={s}>— {s}</li>
              ))}
            </ul>
            <div className="gate__wheel-wrap">
              <img className="gate__wheel" src="/d2/wheels/CS-387.webp" alt="" loading="eager" />
            </div>
          </div>
          <div className="gate__body">
            <h1 className="gate__name">
              <img className="gate__d2-logo" src="/d2/logo-wide.png" alt="D2" />
              <span className="gate__name-r">FORGED</span>
            </h1>
            <p className="gate__sub">{t.gateD2Sub}</p>
            <span className="gate__enter">
              {t.gateEnter} <span className="gate__arrow">→</span>
            </span>
            <div className="gate__bar">
              <div className="gate__bar-fill" />
            </div>
          </div>
        </button>
      </main>
    </div>
  );
}
