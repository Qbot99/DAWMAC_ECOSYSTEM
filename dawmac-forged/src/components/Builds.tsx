import { useEffect, useRef, useState } from "react";
import { PROJECTS_ENDPOINT, galleryImg } from "../config";
import type { GalleryProject } from "../data/types";
import { useLang } from "../i18n";
import BuildLightbox from "./BuildLightbox";

const PAGE = 10;

export default function Builds() {
  const { t } = useLang();
  const [projects, setProjects] = useState<GalleryProject[]>([]);
  const [shown, setShown] = useState(PAGE);
  const [selected, setSelected] = useState<GalleryProject | null>(null);
  const trackRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    let cancelled = false;
    // warunek: słowo "forged" (bez względu na wielkość liter) w marce lub modelu felgi
    fetch(PROJECTS_ENDPOINT)
      .then((r) => r.json())
      .then((data: GalleryProject[]) => {
        if (!cancelled && Array.isArray(data)) {
          setProjects(
            data.filter(
              (p) =>
                p.image &&
                `${p.brand} ${p.model}`.toLowerCase().includes("forged")
            )
          );
        }
      })
      .catch(() => {});
    return () => {
      cancelled = true;
    };
  }, []);

  // doładowanie kolejnych kart, gdy przewijanie w poziomie dojedzie blisko końca
  const onScroll = () => {
    const el = trackRef.current;
    if (!el) return;
    if (el.scrollLeft + el.clientWidth >= el.scrollWidth - 320) {
      setShown((s) => (s < projects.length ? s + PAGE : s));
    }
  };

  if (projects.length === 0) return null;

  return (
    <section className="section">
      <div data-reveal="0">
        <div className="kicker">
          <span className="kicker__line" />
          <span className="kicker__label">{t.realKicker}</span>
        </div>
        <h2 className="section-title">{t.realTitle}</h2>
      </div>
      <div className="builds__track" ref={trackRef} onScroll={onScroll}>
        {projects.slice(0, shown).map((p) => (
          <button
            key={p.project_id}
            className="builds__item"
            onClick={() => setSelected(p)}
          >
            <img
              className="builds__img"
              src={galleryImg(p.image)}
              alt={`${p.brand} ${p.model}`}
              loading="lazy"
            />
            <span className="builds__caption">
              {p.brand} {p.model}
            </span>
          </button>
        ))}
      </div>

      {selected && (
        <BuildLightbox project={selected} onClose={() => setSelected(null)} />
      )}
    </section>
  );
}
