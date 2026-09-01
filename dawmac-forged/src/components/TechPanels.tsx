import { useMemo } from "react";
import { wheelImg } from "../config";
import {
  fmtPrice,
  seriesPriceFrom,
  useForgedData,
} from "../data/useForgedData";
import { useLang } from "../i18n";

const FALLBACK: Record<string, string> = {
  "1": "/monoblock.jpeg",
  "2": "/two_piece.jpeg",
};

export default function TechPanels() {
  const { t } = useLang();
  const { wheels, prices } = useForgedData();

  // reprezentacyjne zdjęcie serii = pierwsza felga tej serii ze zdjęciem
  const seriesImg = useMemo(() => {
    const map: Record<string, string | undefined> = {};
    for (const sid of ["1", "2", "3", "4"]) {
      const w = wheels.find((x) => x.series_id === sid && x.images[0]);
      map[sid] = w ? wheelImg(w.images[0]) : FALLBACK[sid];
    }
    return map;
  }, [wheels]);

  const panels = [
    {
      sid: "1",
      name: "Forged Mono",
      local: t.techMonoDesc,
      desc: t.techMonoTxt,
      priced: true,
    },
    {
      sid: "2",
      name: "Forged Duo",
      local: t.techDuoDesc,
      desc: t.techDuoTxt,
      priced: true,
    },
    {
      sid: "3",
      name: "Forged Trio",
      local: t.techTrioDesc,
      desc: t.techTrioTxt,
      priced: false,
    },
    {
      sid: "4",
      name: "Forged Magnesium",
      local: t.techMagDesc,
      desc: t.techMagTxt,
      priced: false,
      badge: t.techMagBadge,
    },
  ];

  return (
    <section className="section" id="technologie">
      <div data-reveal="0">
        <div className="kicker">
          <span className="kicker__line" />
          <span className="kicker__label">{t.techKicker}</span>
        </div>
        <h2 className="section-title">{t.techTitle}</h2>
      </div>
      <div className="tech__grid">
        {panels.map((p, i) => {
          const from = p.priced ? seriesPriceFrom(prices[p.sid]) : null;
          return (
            <a
              key={p.sid}
              className="tech-card"
              data-reveal={String(i)}
              href={p.priced ? "#cennik" : "#kontakt"}
            >
              <div className="tech-card__imgwrap">
                {seriesImg[p.sid] && (
                  <img
                    className="tech-card__img"
                    src={seriesImg[p.sid]}
                    alt={p.name}
                    loading="lazy"
                  />
                )}
              </div>
              {p.badge && <span className="tech-card__badge">{p.badge}</span>}
              <div className="tech-card__body">
                <div className="tech-card__row">
                  <span className="tech-card__name">{p.name}</span>
                  <span className="tech-card__num">0{i + 1}</span>
                </div>
                <div className="tech-card__local">{p.local}</div>
                <div className="tech-card__divider" />
                <p className="tech-card__desc">{p.desc}</p>
                <span className="tech-card__cta">
                  {from
                    ? `${t.techFrom} ${fmtPrice(from)} · ${t.techGoPrice}`
                    : `${t.techMagCta} →`}
                </span>
              </div>
            </a>
          );
        })}
      </div>
    </section>
  );
}
