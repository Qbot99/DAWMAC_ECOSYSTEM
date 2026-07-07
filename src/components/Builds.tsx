import { useEffect, useState } from "react";
import { GALLERY_SITE, PROJECTS_ENDPOINT, galleryImg } from "../config";
import type { GalleryProject } from "../data/types";
import { useLang } from "../i18n";

export default function Builds() {
  const { t } = useLang();
  const [projects, setProjects] = useState<GalleryProject[]>([]);

  useEffect(() => {
    let cancelled = false;
    // ?forged=1 — auta oznaczone „ptaszkiem" w panelu galerii; starsza wersja API
    // ignoruje parametr i zwraca wszystkie (bierzemy wtedy najnowsze)
    fetch(`${PROJECTS_ENDPOINT}?forged=1`)
      .then((r) => r.json())
      .then((data: GalleryProject[]) => {
        if (!cancelled && Array.isArray(data)) {
          setProjects(data.filter((p) => p.image).slice(0, 12));
        }
      })
      .catch(() => {});
    return () => {
      cancelled = true;
    };
  }, []);

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
      <div className="builds__track">
        {projects.map((p) => (
          <a
            key={p.project_id}
            className="builds__item"
            href={`${GALLERY_SITE}/?Search=${encodeURIComponent(`${p.brand} ${p.model}`)}`}
            target="_blank"
            rel="noopener noreferrer"
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
          </a>
        ))}
      </div>
    </section>
  );
}
