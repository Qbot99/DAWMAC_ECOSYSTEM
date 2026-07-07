import { useCallback } from "react";
import styles from "./Gallery.module.css";
import type { EmblaCarouselType } from "embla-carousel";
import {
  DotButton,
  useDotButton,
} from "../../components/embla/EmblaCarouselDotButton";
import Autoplay from "embla-carousel-autoplay";
import useEmblaCarousel from "embla-carousel-react";

// type PropType = {
//   slides?: number[];
//   options?: EmblaOptionsType;
// };

export default function Gallery() {
  const [emblaRef, emblaApi] = useEmblaCarousel({ loop: true }, [Autoplay()]);

  const onNavButtonClick = useCallback((emblaApi: EmblaCarouselType) => {
    const autoplay = emblaApi?.plugins()?.autoplay;
    if (!autoplay) return;

    const resetOrStop =
      autoplay.options.stopOnInteraction === false
        ? autoplay.reset
        : autoplay.stop;

    resetOrStop();
  }, []);

  const { selectedIndex, scrollSnaps, onDotButtonClick } = useDotButton(
    emblaApi,
    onNavButtonClick
  );

  return (
    <section className={styles.section}>
      <div className={styles.leftContent}>
        <h3 className={styles.heading}>Galeria</h3>
        <p className={styles.paragraph}>
          Zobacz, jak prezentują się felgi Dawmac Forged na samochodach naszych
          klientów. Każdy zestaw to połączenie indywidualnego stylu,
          precyzyjnego wykonania i doskonałych osiągów. Od klasycznych coupe po
          nowoczesne sportowe konstrukcje – nasze felgi podkreślają charakter
          każdego auta.
        </p>
        <a
          className={styles.link}
          href="https://galeria.dawmacpolska.pl/?Search=Dawmac+Forged"
          target="_blank"
          rel="noreferrer"
        >
          Zobacz więcej
        </a>
      </div>

      {/* EMBLA Carousel */}
      <div className={styles.emblaWrapper}>
        <div className={styles.embla}>
          <div className={styles.embla__viewport} ref={emblaRef}>
            <div className={styles.embla__container}>
              {[
                { src: "/hero-baner-img/378.webp", alt: "Chevrolet Corvette" },
                { src: "/hero-baner-img/871.webp", alt: "Subaru WRX STI" },
                { src: "/hero-baner-img/367.webp", alt: "Porsche 718 Cayman" },
              ].map((img, i) => (
                <div key={i} className={styles.embla__slide}>
                  <img
                    src={img.src}
                    alt={`${img.alt} Dawmac Forged`}
                    className={styles.embla__image}
                  />
                </div>
              ))}
            </div>
          </div>

          <div className={styles["embla__controls"]}>
            <div className={styles["embla__dots"]}>
              {scrollSnaps.map((_, index) => (
                <DotButton
                  key={index}
                  onClick={() => onDotButtonClick(index)}
                  className={`${styles["embla__dot"]} ${
                    index === selectedIndex
                      ? styles["embla__dot--selected"]
                      : ""
                  }`}
                />
              ))}
            </div>
          </div>
        </div>
      </div>
    </section>
  );
}
