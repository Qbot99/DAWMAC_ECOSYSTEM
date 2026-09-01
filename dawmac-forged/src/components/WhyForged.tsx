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
    </section>
  );
}
