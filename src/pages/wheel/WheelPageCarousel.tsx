import type { EmblaOptionsType } from "embla-carousel";
import {
  PrevButton,
  NextButton,
  usePrevNextButtons,
} from "../../components/embla/EmblaCarouselArrowButtons";
import useEmblaCarousel from "embla-carousel-react";
import styles from "./WheelPageCarousel.module.css";
import { useState } from "react";

type PropType = {
  slides: string[];
  options?: EmblaOptionsType;
};

export default function WheelPageCarousel({ slides, options }: PropType) {
  const [showLargeImage, setShowLargeImage] = useState<string>("");
  const [emblaRef, emblaApi] = useEmblaCarousel({
    loop: false,
    align: "start",
    slidesToScroll: 1,
    ...options,
  });

  const {
    prevBtnDisabled,
    nextBtnDisabled,
    onPrevButtonClick,
    onNextButtonClick,
  } = usePrevNextButtons(emblaApi);

  console.log("WheelPageCarousel slides", slides);

  return (
    <>
      {showLargeImage && (
        <div
          className={styles.largeImageOverlay}
          onClick={() => setShowLargeImage("")}
        >
          <img
            src={`https://api.dawmacpolska.pl/forged/${showLargeImage}`}
            alt="Large wheel"
            className={styles.largeImage}
          />
        </div>
      )}
      <section className={styles.embla}>
        <div className={styles.emblaViewport} ref={emblaRef}>
          <div className={styles.emblaContainer}>
            {slides.map((url, i) => (
              <div className={styles.emblaSlide} key={i}>
                <img
                  src={`https://api.dawmacpolska.pl/forged/${url}`}
                  alt={`Wheel image ${i + 1}`}
                  className={styles.emblaImage}
                  onClick={() => {
                    setShowLargeImage(url);
                  }}
                />
              </div>
            ))}
          </div>
        </div>

        <div className={styles.emblaControls}>
          <div className={styles.emblaButtons}>
            <PrevButton
              onClick={onPrevButtonClick}
              disabled={prevBtnDisabled}
              className={styles.emblaButton}
            />
            <NextButton
              onClick={onNextButtonClick}
              disabled={nextBtnDisabled}
              className={styles.emblaButton}
            />
          </div>
        </div>
      </section>
    </>
  );
}
