import { useEffect, useMemo, useRef, useState } from "react";
import { FaWhatsapp } from "react-icons/fa";
import { FiLink } from "react-icons/fi";
import { CONTACT } from "../config";
import { D2_MODELS, d2PriceFor } from "../data/d2";
import type { D2Model } from "../data/d2";
import { fmtPrice } from "../data/useForgedData";
import { useLang } from "../i18n";
import { useToast } from "./Toast";

interface Props {
  model: D2Model;
  onSwitch: (name: string) => void;
  onClose: () => void;
  onAsk: (name: string) => void;
}

export default function D2Lightbox({ model, onSwitch, onClose, onAsk }: Props) {
  const { t, lang } = useLang();
  const toast = useToast();
  const [photoIdx, setPhotoIdx] = useState(0);
  const [size, setSize] = useState<number | null>(null);
  const touchX = useRef(0);

  const selSize = size && model.sizes.includes(size) ? size : model.sizes[0];
  const selPrice = d2PriceFor(selSize);

  const photos = [model.img, ...model.extra];
  const photo = photos[Math.min(photoIdx, photos.length - 1)];

  // modele w tej samej kolejności co katalog — strzałki prev/next
  const idx = useMemo(
    () => D2_MODELS.findIndex((m) => m.name === model.name),
    [model.name]
  );
  const step = (dir: number) => {
    if (idx < 0) return;
    const next = D2_MODELS[(idx + dir + D2_MODELS.length) % D2_MODELS.length];
    setPhotoIdx(0);
    setSize(null);
    onSwitch(next.name);
  };

  useEffect(() => {
    setPhotoIdx(0);
    setSize(null);
  }, [model.name]);

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
  }, [idx]);

  const wheelUrl = `${window.location.origin}/d2/wheel/${encodeURIComponent(model.name)}`;

  const share = () => {
    if (navigator.share && /Mobi|Android/i.test(navigator.userAgent)) {
      navigator
        .share({ title: `D2 Forged ${model.name}`, url: wheelUrl })
        .catch(() => {});
      return;
    }
    navigator.clipboard
      .writeText(wheelUrl)
      .then(() => toast(t.toast))
      .catch(() => toast(wheelUrl));
  };

  // wiadomość zawiera link do strony felgi — sprzedawca od razu wie, o który model chodzi
  const waText = {
    pl: `Dzień dobry, pytam o felgę D2 ${model.name}\n${wheelUrl}`,
    en: `Hello, I'm asking about the D2 ${model.name} wheel\n${wheelUrl}`,
    de: `Guten Tag, ich interessiere mich für die D2 Felge ${model.name}\n${wheelUrl}`,
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
                FIG.{model.name} // D2 {model.series.toUpperCase()} SERIES
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
                <img
                  className="lightbox__mainimg"
                  src={photo}
                  alt={`D2 Forged ${model.name}`}
                />
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
                        src={p}
                        alt=""
                        loading="lazy"
                        style={{ width: "100%", height: "100%", objectFit: "cover" }}
                      />
                    </button>
                  ))}
                </div>
              )}
            </div>

            <div className="lightbox__info">
              <span className="lightbox__series">D2 {model.series} Series</span>
              <h3 className="lightbox__name">{model.name}</h3>
              <p className="lightbox__series-desc">{t.d2LbDesc}</p>

              <div className="lightbox__label">{t.lbSize}</div>
              <div className="lightbox__sizes">
                {model.sizes.map((s) => (
                  <button
                    key={s}
                    className={`size-btn ${s === selSize ? "size-btn--active" : ""}`}
                    onClick={() => setSize(s)}
                  >
                    {s}"
                  </button>
                ))}
              </div>

              {selPrice && (
                <div className="lightbox__pricing">
                  <div className="lightbox__price-row lightbox__price-row--hl">
                    <span className="lightbox__price-label">{t.lbPre}</span>
                    <span className="lightbox__price-pre">{fmtPrice(selPrice)}</span>
                  </div>
                </div>
              )}

              <p className="lightbox__note">{t.d2Note}</p>

              <div className="lightbox__actions">
                <a
                  href="#kontakt"
                  className="btn-primary"
                  onClick={() => onAsk(model.name)}
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
  );
}
