import { useEffect, useState } from "react";

export default function Preloader() {
  const [active, setActive] = useState(
    () => !sessionStorage.getItem("dawmacPre")
  );
  const [count, setCount] = useState(0);
  const [closing, setClosing] = useState(false);

  useEffect(() => {
    if (!active) return;
    const iv = setInterval(() => {
      setCount((c) => {
        const next = Math.min(100, c + 3 + Math.floor(Math.random() * 5));
        if (next >= 100) {
          clearInterval(iv);
          sessionStorage.setItem("dawmacPre", "1");
          setTimeout(() => setClosing(true), 250);
          setTimeout(() => setActive(false), 1000);
        }
        return next;
      });
    }, 40);
    return () => clearInterval(iv);
  }, [active]);

  if (!active) return null;

  return (
    <div
      className="preloader"
      style={{ transform: closing ? "translateY(-100%)" : "translateY(0)" }}
    >
      <div className="preloader__logo">
        DAWMAC<span>.</span>
      </div>
      <div className="preloader__track">
        <div className="preloader__fill" style={{ width: `${count}%` }} />
      </div>
      <div className="preloader__count">{count}%</div>
    </div>
  );
}
