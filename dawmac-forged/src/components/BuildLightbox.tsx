import { useEffect, useRef, useState } from "react";
import { PROJECT_DETAILS_ENDPOINT, galleryImg } from "../config";
import type { GalleryProject, GalleryProjectDetails } from "../data/types";

interface Props {
  project: GalleryProject;
  onClose: () => void;
}

/** Lightbox realizacji z galerii: wszystkie zdjęcia projektu, strzałki/swipe/miniatury */
export default function BuildLightbox({ project, onClose }: Props) {
  const [photos, setPhotos] = useState<string[]>(
    project.image ? [project.image] : []
  );
  const [photoIdx, setPhotoIdx] = useState(0);
  const touchX = useRef(0);

  // dociągnięcie pełnej listy zdjęć projektu; obecne API czyta id z GET,
  // wersja z pakietu wdrożeniowego z POST — próbujemy obu po kolei
  useEffect(() => {
    let cancelled = false;
    // images: tablica (nowe API) albo string po przecinkach (obecne API na serwerze)
    const extract = (data: GalleryProjectDetails[]) => {
      const raw = data?.[0]?.images as string[] | string | null | undefined;
      const list = Array.isArray(raw)
        ? raw
        : typeof raw === "string"
          ? raw.split(",")
          : [];
      // API skleja ścieżki przecinkiem i SPACJĄ — bez trim() każda kolejna ma
      // spację na początku i URL zwraca 404 (działało tylko pierwsze zdjęcie)
      return list.map((p) => p.trim()).filter(Boolean);
    };

    async function load() {
      try {
        const get = await fetch(
          `${PROJECT_DETAILS_ENDPOINT}?id=${encodeURIComponent(project.project_id)}`
        );
        let imgs = extract(await get.json());
        if (!imgs.length) {
          const fd = new FormData();
          fd.append("id", project.project_id);
          const post = await fetch(PROJECT_DETAILS_ENDPOINT, {
            method: "POST",
            body: fd,
          });
          imgs = extract(await post.json());
        }
        if (!cancelled && imgs.length) {
          setPhotos(imgs);
          setPhotoIdx(0);
        }
      } catch {
        /* zostaje zdjęcie z listy projektów */
      }
    }
    load();
    return () => {
      cancelled = true;
    };
  }, [project.project_id]);

  const step = (dir: number) =>
    setPhotoIdx((p) => (p + dir + photos.length) % photos.length);

  // klawiatura + blokada scrolla
  useEffect(() => {
    const onKey = (e: KeyboardEvent) => {
      if (e.key === "Escape") onClose();
      if (e.key === "ArrowRight") step(1);
      if (e.key === "ArrowLeft") step(-1);
    };
    window.addEventListener("keydown", onKey);
    document.body.classList.add("modal-open");
    return () => {
      window.removeEventListener("keydown", onKey);
      document.body.classList.remove("modal-open");
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [photos.length]);

  const photo = photos[Math.min(photoIdx, photos.length - 1)];

  return (
    <div className="lightbox" onClick={onClose}>
      <div className="lightbox__panel" onClick={(e) => e.stopPropagation()}>
        <button className="lightbox__close" onClick={onClose}>
          ✕
        </button>
        <div className="lightbox__grid">
          <div className="lightbox__media">
            <span className="lightbox__fig">
              {project.brand} {project.model}
            </span>
            <div
              className="lightbox__mainimg-wrap"
              onTouchStart={(e) => (touchX.current = e.touches[0].clientX)}
              onTouchEnd={(e) => {
                const dx = e.changedTouches[0].clientX - touchX.current;
                if (photos.length < 2) return;
                if (dx > 40) step(-1);
                if (dx < -40) step(1);
              }}
            >
              {photo && (
                <img
                  className="lightbox__mainimg"
                  src={galleryImg(photo)}
                  alt={`${project.brand} ${project.model}`}
                />
              )}
              {photos.length > 1 && (
                <>
                  <button
                    className="lightbox__arrow lightbox__arrow--prev"
                    onClick={() => step(-1)}
                  >
                    ←
                  </button>
                  <button
                    className="lightbox__arrow lightbox__arrow--next"
                    onClick={() => step(1)}
                  >
                    →
                  </button>
                  <span className="lightbox__counter">
                    {photoIdx + 1}/{photos.length}
                  </span>
                </>
              )}
            </div>

            {photos.length > 1 && (
              <div className="lightbox__thumbs">
                {photos.map((p, i) => (
                  <button
                    key={p}
                    className={`lightbox__thumb ${i === photoIdx ? "lightbox__thumb--active" : ""}`}
                    onClick={() => setPhotoIdx(i)}
                    style={{ border: "none" }}
                  >
                    <img
                      src={galleryImg(p)}
                      alt=""
                      loading="lazy"
                      style={{ width: "100%", height: "100%", objectFit: "cover" }}
                    />
                  </button>
                ))}
              </div>
            )}
          </div>
        </div>
      </div>
    </div>
  );
}
