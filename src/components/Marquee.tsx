const ITEMS = [
  "Forged Mono",
  "Forged Duo",
  "Forged Trio",
  "Forged Magnesium",
  'Ø 15-24"',
  "Custom PCD / ET",
  "CNC",
  "Aluminium 6061 T6",
];

export default function Marquee() {
  const doubled = [...ITEMS, ...ITEMS];
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
