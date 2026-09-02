import { useRef } from "react";
import { PRICED_SERIES, wheelImg } from "../config";
import {
  fmtPrice,
  seriesPriceFrom,
  seriesSizeRange,
  useForgedData,
} from "../data/useForgedData";
import { navigateTo } from "../hooks/useWheelRoute";
import { seriesName, useLang } from "../i18n";

const STRIP_COUNT = 14;

interface Props {
  onOpenWheel: (name: string) => void;
}

/** Zajawka katalogu na stronie głównej: felgi w poziomym scrollu + "Zobacz wszystkie". */
export default function CatalogStrip({ onOpenWheel }: Props) {
  const { t } = useLang();
  const { wheels, prices, loading, error } = useForgedData();
  const rowRef = useRef<HTMLDivElement>(null);

  if (error) return null;
  const items = wheels.slice(0, STRIP_COUNT);

  const nudge = (dir: 1 | -1) => {
    const row = rowRef.current;
    if (!row) return;
    row.scrollBy({ left: dir * Math.round(row.clientWidth * 0.8), behavior: "smooth" });
  };

  return (
    <section className="section strip" id="katalog-strip">
      <div className="strip__head" data-reveal="0">
        <div>
          <div className="kicker">
            <span className="kicker__line" />
            <span className="kicker__label">{t.catKicker}</span>
          </div>
          <h2 className="section-title">{t.catTitle}</h2>
        </div>
        <div className="strip__nav">
          <button className="strip__arrow" aria-label="&larr;" onClick={() => nudge(-1)}>
            ←
          </button>
          <button className="strip__arrow" aria-label="&rarr;" onClick={() => nudge(1)}>
            →
          </button>
          <a
            className="btn-ghost strip__all"
            href="/katalog"
            onClick={(e) => {
              e.preventDefault();
              navigateTo("/katalog");
            }}
          >
            {t.stripAll} →
          </a>
        </div>
      </div>

      <div className="strip__row" ref={rowRef} data-reveal="1">
        {loading
          ? Array.from({ length: 6 }).map((_, i) => (
              <div key={i} className="skeleton strip__skeleton" />
            ))
          : items.map((w) => {
              const sp = prices[w.series_id];
              const priced = PRICED_SERIES.includes(w.series_id);
              const from = priced ? seriesPriceFrom(sp) : null;
              const range = priced ? seriesSizeRange(sp) : null;
              return (
                <button
                  key={w.id}
                  className="wheel-card strip__card"
                  onClick={() => onOpenWheel(w.name)}
                >
                  <div className="wheel-card__imgwrap">
                    {w.images[0] && (
                      <img
                        className="wheel-card__img"
                        src={wheelImg(w.images[0])}
                        alt={`Dawmac Forged ${w.name}`}
                        loading="lazy"
                      />
                    )}
                    <span className="wheel-card__series">
                      {seriesName(t, w.series_id)}
                    </span>
                  </div>
                  <div className="wheel-card__body">
                    <div className="wheel-card__row">
                      <span className="wheel-card__name">{w.name}</span>
                      {range && <span className="wheel-card__sizes">Ø {range}</span>}
                    </div>
                    <div className="wheel-card__divider" />
                    <div className="wheel-card__foot">
                      <span className="wheel-card__more">→</span>
                      <span className="wheel-card__price">
                        {from ? `${t.from} ${fmtPrice(from)}` : t.indiv}
                      </span>
                    </div>
                  </div>
                </button>
              );
            })}
        {!loading && (
          <a
            className="strip__end"
            href="/katalog"
            onClick={(e) => {
              e.preventDefault();
              navigateTo("/katalog");
            }}
          >
            <span className="strip__end-arrow">→</span>
            <span>{t.ctaCat}</span>
          </a>
        )}
      </div>
    </section>
  );
}
