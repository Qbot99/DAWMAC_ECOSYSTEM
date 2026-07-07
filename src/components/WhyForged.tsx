import { useLang } from "../i18n";

export default function WhyForged() {
  const { t } = useLang();
  const panels = [
    { num: "/01", title: t.why1T, desc: t.why1D },
    { num: "/02", title: t.why2T, desc: t.why2D },
    { num: "/03", title: t.why3T, desc: t.why3D },
  ];

  return (
    <section id="dlaczego">
      <div className="section" style={{ paddingBottom: 0 }}>
        <div data-reveal="0">
          <div className="kicker">
            <span className="kicker__line" />
            <span className="kicker__label">{t.whyKicker}</span>
          </div>
          <h2 className="section-title">{t.whyTitle}</h2>
        </div>
      </div>
      <div className="why" style={{ borderTop: "none", marginTop: 32 }}>
        {panels.map((p, i) => (
          <div key={p.num} className="why__panel" data-reveal={String(i)}>
            <div className="why__num">{p.num}</div>
            <div className="why__title">{p.title}</div>
            <p className="why__desc">{p.desc}</p>
          </div>
        ))}
      </div>
      <div className="cmp">
        <div className="cmp__title" data-reveal="0">
          {t.cmpTitle}
        </div>
        <div className="cmp__row">
          <span className="cmp__label cmp__label--forged">{t.cmpForged}</span>
          <div className="cmp__track">
            <div className="cmp__fill cmp__fill--forged" data-bar="92" />
          </div>
        </div>
        <div className="cmp__row">
          <span className="cmp__label cmp__label--cast">{t.cmpCast}</span>
          <div className="cmp__track">
            <div className="cmp__fill cmp__fill--cast" data-bar="44" />
          </div>
        </div>
        <div className="cmp__note">{t.cmpNote}</div>
      </div>
    </section>
  );
}
