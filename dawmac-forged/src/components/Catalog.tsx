import { useMemo, useState } from "react";
import { PRICED_SERIES, SERIES, seriesLabel, wheelImg } from "../config";
import {
  fmtPrice,
  seriesPriceFrom,
  seriesSizeRange,
  useForgedData,
} from "../data/useForgedData";
import { useLang } from "../i18n";

const PAGE = 12;
const FILTERS = ["all", "1", "2", "3", "5"]; // Wszystkie / Mono / Duo / Trio / Factory Stock

interface Props {
  onOpenWheel: (name: string) => void;
}

export default function Catalog({ onOpenWheel }: Props) {
  const { t } = useLang();
  const { wheels, prices, loading, error } = useForgedData();
  const [filter, setFilter] = useState("all");
  const [search, setSearch] = useState("");
  const [shown, setShown] = useState(PAGE);

  const filtered = useMemo(() => {
    let list = wheels;
    if (filter !== "all") list = list.filter((w) => w.series_id === filter);
    const q = search.trim().toLowerCase();
    if (q) list = list.filter((w) => w.name.toLowerCase().includes(q));
    return list;
  }, [wheels, filter, search]);

  const visible = filtered.slice(0, shown);

  return (
    <section className="section" id="katalog">
      <div className="catalog__head" data-reveal="0">
        <div>
          <div className="kicker">
            <span className="kicker__line" />
            <span className="kicker__label">{t.catKicker}</span>
          </div>
          <h2 className="section-title">{t.catTitle}</h2>
        </div>
        <input
          className="catalog__search"
          type="search"
          placeholder={t.searchPh}
          value={search}
          onChange={(e) => {
            setSearch(e.target.value);
            setShown(PAGE);
          }}
        />
      </div>

      <div className="catalog__tabs" data-reveal="1">
        {FILTERS.map((f) => (
          <button
            key={f}
            className={`tab-btn ${filter === f ? "tab-btn--active" : ""}`}
            onClick={() => {
              setFilter(f);
              setShown(PAGE);
            }}
          >
            {f === "all" ? t.fAll : SERIES[f].label}
          </button>
        ))}
      </div>

      {loading && (
        <div className="catalog__grid">
          {Array.from({ length: 8 }).map((_, i) => (
            <div key={i} className="skeleton" />
          ))}
        </div>
      )}

      {error && <p className="catalog__note">{t.loadError}</p>}

      {!loading && !error && (
        <>
          <div className="catalog__grid">
            {visible.map((w, i) => {
              const sp = prices[w.series_id];
              const priced = PRICED_SERIES.includes(w.series_id);
              const from = priced ? seriesPriceFrom(sp) : null;
              const range = priced ? seriesSizeRange(sp) : null;
              return (
                <button
                  key={w.id}
                  className="wheel-card"
                  data-reveal={String(i % 4)}
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
                      {seriesLabel(w.series_id)}
                    </span>
                  </div>
                  <div className="wheel-card__body">
                    <div className="wheel-card__row">
                      <span className="wheel-card__name">{w.name}</span>
                      {range && (
                        <span className="wheel-card__sizes">Ø {range}</span>
                      )}
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
          </div>

          {visible.length === 0 && (
            <p className="catalog__note">{t.noResults}</p>
          )}

          {filtered.length > shown && (
            <div className="catalog__more-wrap">
              <button
                className="btn-ghost"
                onClick={() => setShown((s) => s + PAGE)}
              >
                {t.showMore} ({filtered.length - shown})
              </button>
            </div>
          )}
        </>
      )}
    </section>
  );
}
