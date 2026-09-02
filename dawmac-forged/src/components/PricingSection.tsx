import { useState } from "react";
import { fmtPrice, useForgedData } from "../data/useForgedData";
import { addonLabel, seriesName, useLang } from "../i18n";

const TAB_IDS = ["1", "2", "3"];

export default function PricingSection() {
  const { t } = useLang();
  const { prices } = useForgedData();
  const [tab, setTab] = useState("1");
  const TABS = TAB_IDS.map((sid) => ({ sid, label: seriesName(t, sid) }));

  // decyzja: tabele cen tylko dla Mono i Duo; Trio = wycena indywidualna
  const showTable = tab !== "3";
  const sp = prices[tab];
  const addons = Object.entries(sp?.dodatki ?? {});
  // gdy żaden rozmiar w serii nie ma ceny katalogowej, chowamy pustą kolumnę
  const hasCat = !!sp?.rozmiary?.some((r) => r.cena_katalogowa);
  const air = sp?.ceny_poczta_lotnicza ?? [];

  return (
    <section className="section pricing" id="cennik">
      <div data-reveal="0">
        <div className="kicker">
          <span className="kicker__line" />
          <span className="kicker__label">{t.cenKicker}</span>
        </div>
        <h2 className="section-title">{t.cenTitle}</h2>
        <div className="pricing__badge">
          <span className="pricing__badge-dot" />
          <span className="pricing__badge-label">{t.cenBadge}</span>
        </div>
      </div>

      <div className="pricing__tabs">
        {TABS.map(({ sid, label }) => (
          <button
            key={sid}
            className={`tab-btn ${tab === sid ? "tab-btn--active" : ""}`}
            onClick={() => setTab(sid)}
          >
            {label}
          </button>
        ))}
      </div>

      {showTable && sp?.rozmiary?.length ? (
        <div className="pricing__grid">
          <div
            className={`pricing__table ${hasCat ? "" : "pricing__table--nocat"}`}
            data-reveal="1"
          >
            <div className="pricing__thead">
              <span className="pricing__th">Ø</span>
              {hasCat && (
                <span className="pricing__th pricing__th--right">{t.colCat}</span>
              )}
              <span className="pricing__th pricing__th--pre pricing__th--right">
                {t.colPre}
              </span>
            </div>
            {sp.rozmiary.map((r, i) => (
              <div
                key={r.rozmiar}
                className="pricing__row"
                style={{
                  background: i % 2 ? "rgba(255,255,255,.015)" : "transparent",
                }}
              >
                <span className="pricing__size">{r.rozmiar}"</span>
                {hasCat && (
                  <span
                    className={`pricing__cat ${r.cena_katalogowa ? "" : "pricing__cat--plain"}`}
                  >
                    {r.cena_katalogowa ? fmtPrice(r.cena_katalogowa) : "-"}
                  </span>
                )}
                <span className="pricing__pre">
                  {fmtPrice(r.przedplata_100)}
                </span>
              </div>
            ))}
          </div>

          <div data-reveal="2">
            {addons.length > 0 && (
              <>
                <div className="pricing__addons-title">
                  {t.addonsTitle} -{" "}
                  {TABS.find((x) => x.sid === tab)?.label}
                </div>
                <div className="pricing__addons">
                  {addons.map(([key, price]) => (
                    <div key={key} className="addon">
                      <span className="addon__name">{addonLabel(t, key)}</span>
                      <span className="addon__price">+ {fmtPrice(price)}</span>
                    </div>
                  ))}
                </div>
              </>
            )}

            {air.length > 0 && (
              <>
                <div className="pricing__addons-title" style={{ marginTop: 24 }}>
                  {t.colAir} ({t.colPre})
                </div>
                <div className="pricing__addons">
                  {air.map((r) => (
                    <div key={r.rozmiar} className="addon">
                      <span className="addon__name">{r.rozmiar}"</span>
                      <span className="addon__price">
                        {fmtPrice(r.przedplata_100)}
                      </span>
                    </div>
                  ))}
                </div>
              </>
            )}

            <p className="pricing__note">{t.cenNote}</p>
            <a
              href="#kontakt"
              className="btn-primary"
              style={{ marginTop: 22 }}
            >
              {t.orderBtn} →
            </a>
          </div>
        </div>
      ) : (
        <div className="pricing__indiv" data-reveal="1">
          <p>{t.trioIndiv}</p>
          <a href="#kontakt" className="btn-primary">
            {t.contactLink} →
          </a>
        </div>
      )}

      <div className="pricing__magbar" data-reveal="3">
        <span className="pricing__magbar-label">{t.magBar}</span>
        <a href="#kontakt">{t.contactLink} →</a>
      </div>
    </section>
  );
}
