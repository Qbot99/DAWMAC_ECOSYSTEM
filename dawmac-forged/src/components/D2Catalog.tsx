import { useEffect, useMemo, useRef, useState } from "react";
import {
  D2_MODELS,
  D2_PRICES,
  D2_SERIES,
  d2PriceFrom,
  d2SizeRange,
} from "../data/d2";
import { fmtPrice } from "../data/useForgedData";
import { useLang } from "../i18n";

const PAGE = 48;

interface Props {
  onOpenWheel: (name: string) => void;
}

export default function D2Catalog({ onOpenWheel }: Props) {
  const { t } = useLang();
  const [filter, setFilter] = useState("all");
  const [search, setSearch] = useState("");
  const [shown, setShown] = useState(PAGE);

  const filtered = useMemo(() => {
    let list = D2_MODELS;
    if (filter !== "all") list = list.filter((m) => m.series === filter);
    const q = search.trim().toLowerCase();
    if (q) list = list.filter((m) => m.name.toLowerCase().includes(q));
    return list;
  }, [filter, search]);

  const visible = filtered.slice(0, shown);

  // infinite scroll: sentinel pod siatką dogrywa kolejną stronę, gdy zbliża się
  // do viewportu; obserwator odtwarzany po każdej partii, żeby przy szybkim
  // scrollu od razu dograł następną, jeśli sentinel wciąż jest widoczny
  const moreRef = useRef<HTMLDivElement>(null);
  useEffect(() => {
    const el = moreRef.current;
    if (!el) return;
    const io = new IntersectionObserver(
      (entries) => {
        if (entries[0].isIntersecting) setShown((s) => s + PAGE);
      },
      { rootMargin: "900px 0px" },
    );
    io.observe(el);
    return () => io.disconnect();
  }, [shown, filtered.length]);

  return (
    <section className="section" id="d2-katalog">
      <div className="catalog__head" data-reveal="0">
        <div>
          <div className="kicker">
            <span className="kicker__line" />
            <span className="kicker__label">{t.d2Kicker}</span>
          </div>
          <h2 className="section-title">{t.d2Title}</h2>
          <p className="d2__sub">{t.d2Sub}</p>
        </div>
        <input
          className="catalog__search"
          type="search"
          placeholder={t.d2SearchPh}
          value={search}
          onChange={(e) => {
            setSearch(e.target.value);
            setShown(PAGE);
          }}
        />
      </div>

      <div className="d2__prices" data-reveal="1">
        {Object.entries(D2_PRICES).map(([size, price]) => (
          <div key={size} className="d2__price-chip">
            <span className="d2__price-size">{size}"</span>
            <span className="d2__price-val">{fmtPrice(price)}</span>
          </div>
        ))}
        <span className="d2__price-note">{t.d2PriceNote}</span>
      </div>

      <div className="catalog__tabs catalog__tabs--d2" data-reveal="2">
        <button
          className={`tab-btn ${filter === "all" ? "tab-btn--active" : ""}`}
          onClick={() => {
            setFilter("all");
            setShown(PAGE);
          }}
        >
          {t.fAll} ({D2_MODELS.length})
        </button>
        {D2_SERIES.map((s) => (
          <button
            key={s}
            className={`tab-btn ${filter === s ? "tab-btn--active" : ""}`}
            onClick={() => {
              setFilter(s);
              setShown(PAGE);
            }}
          >
            {s}
          </button>
        ))}
      </div>

      <div className="catalog__grid">
        {visible.map((m, i) => {
          const from = d2PriceFrom(m.sizes);
          return (
            <button
              key={m.name}
              className="wheel-card"
              data-reveal={String(i % 4)}
              onClick={() => onOpenWheel(m.name)}
            >
              <div className="wheel-card__imgwrap">
                <img
                  className="wheel-card__img"
                  src={m.img}
                  alt={`D2 Forged ${m.name}`}
                  loading="lazy"
                />
                <span className="wheel-card__series">{m.series} Series</span>
              </div>
              <div className="wheel-card__body">
                <div className="wheel-card__row">
                  <span className="wheel-card__name">{m.name}</span>
                  <span className="wheel-card__sizes">Ø {d2SizeRange(m.sizes)}</span>
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

      {visible.length === 0 && <p className="catalog__note">{t.noResults}</p>}

      {filtered.length > shown && (
        <div ref={moreRef} className="catalog__sentinel" aria-hidden="true">
          <span className="catalog__sentinel-dot" />
          <span className="catalog__sentinel-dot" />
          <span className="catalog__sentinel-dot" />
        </div>
      )}
    </section>
  );
}
