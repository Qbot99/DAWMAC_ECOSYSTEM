import { useEffect, useMemo, useRef, useState } from "react";
import { FaWhatsapp } from "react-icons/fa";
import { FiLink } from "react-icons/fi";
import {
  CONTACT,
  GALLERY_SITE,
  PRICED_SERIES,
  PROJECTS_ENDPOINT,
  galleryImg,
  seriesLabel,
  wheelImg,
} from "../config";
import { fmtPrice, useForgedData } from "../data/useForgedData";
import type { GalleryProject, Wheel } from "../data/types";
import { useLang } from "../i18n";
import { useToast } from "./Toast";

interface Props {
  wheel: Wheel;
  wheels: Wheel[];
  onSwitch: (name: string) => void;
  onClose: () => void;
  onAsk: (name: string) => void;
}

const seriesDescKey = {
  "1": "techMonoDesc",
  "2": "techDuoDesc",
  "3": "techTrioDesc",
  "4": "techMagDesc",
} as const;

export default function WheelLightbox({
  wheel,
  wheels,
  onSwitch,
  onClose,
  onAsk,
}: Props) {
  const { t, lang } = useLang();
  const { prices } = useForgedData();
  const toast = useToast();
  const [photoIdx, setPhotoIdx] = useState(0);
  const [size, setSize] = useState<string | null>(null);
  const [builds, setBuilds] = useState<GalleryProject[]>([]);
  const touchX = useRef(0);

  const sp = prices[wheel.series_id];
  const priced = PRICED_SERIES.includes(wheel.series_id) && !!sp?.rozmiary?.length;
  const sizes = priced ? sp.rozmiary : [];
  const selSize = size && sizes.some((r) => r.rozmiar === size)
    ? size
    : sizes[0]?.rozmiar ?? null;
  const selPrice = sizes.find((r) => r.rozmiar === selSize);
  const airPrice = sp?.ceny_poczta_lotnicza?.find(
    (r) => r.rozmiar === selSize
  );

  const photos = wheel.images;
  const photo = photos[Math.min(photoIdx, photos.length - 1)];

  // felgi w tej samej kolejności co katalog — strzałki prev/next
  const idx = useMemo(
    () => wheels.findIndex((w) => w.id === wheel.id),
    [wheels, wheel.id]
  );
  const step = (dir: number) => {
    if (idx < 0 || wheels.length === 0) return;
    const next = wheels[(idx + dir + wheels.length) % wheels.length];
    setPhotoIdx(0);
    setSize(null);
    onSwitch(next.name);
  };

  useEffect(() => {
    setPhotoIdx(0);
    setSize(null);
  }, [wheel.id]);

  // realizacje z tą felgą (galeria)
  useEffect(() => {
    let cancelled = false;
    fetch(
      `${PROJECTS_ENDPOINT}?search_keyword=${encodeURIComponent(`Dawmac Forged ${wheel.name} `)}`
    )
      .then((r) => r.json())
      .then((data: GalleryProject[]) => {
        if (!cancelled && Array.isArray(data)) setBuilds(data.slice(0, 3));
      })
      .catch(() => {});
    return () => {
      cancelled = true;
    };
  }, [wheel.name]);

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
  }, [idx, wheels]);

  const share = () => {
    const url = `${window.location.origin}/wheel/${encodeURIComponent(wheel.name)}`;
    if (navigator.share && /Mobi|Android/i.test(navigator.userAgent)) {
      navigator.share({ title: `Dawmac Forged ${wheel.name}`, url }).catch(() => {});
      return;
    }
    navigator.clipboard
      .writeText(url)
      .then(() => toast(t.toast))
      .catch(() => toast(url));
  };

  const waText = {
    pl: `Dzień dobry, pytam o felgę ${wheel.name}`,
    en: `Hello, I'm asking about the ${wheel.name} wheel`,
    de: `Guten Tag, ich interessiere mich für die Felge ${wheel.name}`,
  }[lang];

  return (
    <>
      <div className="lightbox" onClick={onClose}>
        <div className="lightbox__panel" onClick={(e) => e.stopPropagation()}>
          <button className="lightbox__close" onClick={onClose}>
            ✕
          </button>
          <div className="lightbox__grid">
            <div className="lightbox__media">
              <span className="lightbox__fig">
                FIG.{wheel.name} // {seriesLabel(wheel.series_id).toUpperCase()}
              </span>
              <div
                className="lightbox__mainimg-wrap"
                onTouchStart={(e) => (touchX.current = e.touches[0].clientX)}
                onTouchEnd={(e) => {
                  const dx = e.changedTouches[0].clientX - touchX.current;
                  if (photos.length < 2) return;
                  if (dx > 40)
                    setPhotoIdx((p) => (p + photos.length - 1) % photos.length);
                  if (dx < -40) setPhotoIdx((p) => (p + 1) % photos.length);
                }}
              >
                {photo && (
                  <img
                    className="lightbox__mainimg"
                    src={wheelImg(photo)}
                    alt={`Dawmac Forged ${wheel.name}`}
                  />
                )}
                {photos.length > 1 && (
                  <>
                    <button
                      className="lightbox__arrow lightbox__arrow--prev"
                      onClick={() =>
                        setPhotoIdx((p) => (p + photos.length - 1) % photos.length)
                      }
                    >
                      ←
                    </button>
                    <button
                      className="lightbox__arrow lightbox__arrow--next"
                      onClick={() => setPhotoIdx((p) => (p + 1) % photos.length)}
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
                        src={wheelImg(p)}
                        alt=""
                        loading="lazy"
                        style={{ width: "100%", height: "100%", objectFit: "cover" }}
                      />
                    </button>
                  ))}
                </div>
              )}

              {builds.length > 0 && (
                <>
                  <div className="lightbox__builds-title">{t.lbBuilds}</div>
                  <div className="lightbox__builds">
                    {builds.map((b) => (
                      <a
                        key={b.project_id}
                        href={`${GALLERY_SITE}/?Search=Dawmac+Forged+${encodeURIComponent(wheel.name)}+`}
                        target="_blank"
                        rel="noopener noreferrer"
                        title={`${b.brand} ${b.model} — ${t.lbMore}`}
                      >
                        <img
                          className="lightbox__build"
                          src={galleryImg(b.image)}
                          alt={`${b.brand} ${b.model}`}
                          loading="lazy"
                        />
                      </a>
                    ))}
                  </div>
                </>
              )}

              {wheel.youtube_url && (
                <iframe
                  className="lightbox__video"
                  src={`https://www.youtube.com/embed/${wheel.youtube_url}`}
                  title={`Dawmac Forged ${wheel.name}`}
                  allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                  allowFullScreen
                />
              )}
            </div>

            <div className="lightbox__info">
              <span className="lightbox__series">
                {seriesLabel(wheel.series_id)}
              </span>
              <h3 className="lightbox__name">{wheel.name}</h3>
              {wheel.series_id in seriesDescKey && (
                <p className="lightbox__series-desc">
                  {t[seriesDescKey[wheel.series_id as keyof typeof seriesDescKey]]}
                </p>
              )}

              {priced ? (
                <>
                  <div className="lightbox__label">{t.lbSize}</div>
                  <div className="lightbox__sizes">
                    {sizes.map((r) => (
                      <button
                        key={r.rozmiar}
                        className={`size-btn ${r.rozmiar === selSize ? "size-btn--active" : ""}`}
                        onClick={() => setSize(r.rozmiar)}
                      >
                        {r.rozmiar}"
                      </button>
                    ))}
                  </div>

                  {selPrice && (
                    <div className="lightbox__pricing">
                      {selPrice.cena_katalogowa && (
                        <div className="lightbox__price-row">
                          <span className="lightbox__price-label">{t.lbCat}</span>
                          <span className="lightbox__price-cat">
                            {fmtPrice(selPrice.cena_katalogowa)}
                          </span>
                        </div>
                      )}
                      <div className="lightbox__price-row lightbox__price-row--hl">
                        <span className="lightbox__price-label">{t.lbPre}</span>
                        <span className="lightbox__price-pre">
                          {fmtPrice(selPrice.przedplata_100)}
                        </span>
                      </div>
                      <div className="lightbox__price-row">
                        <span className="lightbox__price-label">{t.lbAir}</span>
                        <span className="lightbox__price-air">
                          {airPrice ? fmtPrice(airPrice.przedplata_100) : t.lbAirVal}
                        </span>
                      </div>
                    </div>
                  )}
                </>
              ) : (
                <div className="lightbox__indiv">
                  <div className="lightbox__indiv-title">{t.indivTitle}</div>
                  <div className="lightbox__indiv-desc">{t.indivDesc}</div>
                </div>
              )}

              <p className="lightbox__note">{t.lbNote}</p>

              <div className="lightbox__actions">
                <a
                  href="#kontakt"
                  className="btn-primary"
                  onClick={() => onAsk(wheel.name)}
                >
                  {t.lbAskBtn} →
                </a>
                <a
                  className="lightbox__wa"
                  href={`https://wa.me/${CONTACT.whatsapp}?text=${encodeURIComponent(waText)}`}
                  target="_blank"
                  rel="noopener noreferrer"
                  aria-label="WhatsApp"
                >
                  <FaWhatsapp />
                </a>
                <button className="lightbox__share" onClick={share}>
                  <FiLink /> {t.lbShare}
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>

      {wheels.length > 1 && (
        <>
          <button
            className="lightbox__wheel-arrow lightbox__wheel-arrow--prev"
            onClick={() => step(-1)}
            aria-label="Poprzednia felga"
          >
            ←
          </button>
          <button
            className="lightbox__wheel-arrow lightbox__wheel-arrow--next"
            onClick={() => step(1)}
            aria-label="Następna felga"
          >
            →
          </button>
        </>
      )}
    </>
  );
}
