import { seriesName, useLang } from "../i18n";

export default function Marquee() {
  const { t } = useLang();
  const items = [
    seriesName(t, "1"),
    seriesName(t, "2"),
    seriesName(t, "3"),
    seriesName(t, "4"),
    'Ø 15-24"',
    "Custom PCD / ET",
    "CNC",
    "Aluminium 6061 T6",
  ];
  const doubled = [...items, ...items];
  return (
    <div className="marquee">
      <div className="marquee__track">
        {doubled.map((text, i) => (
          <span
            key={i}
            className="marquee__item"
            style={{ color: i % 2 ? "#565d66" : "#e6e9ec" }}
          >
            {text} <span className="marquee__x">✕</span>
          </span>
        ))}
      </div>
    </div>
  );
}
