import React, { useEffect, useState } from "react";
import useEmblaCarousel from "embla-carousel-react";
import styles from "./Carousel.module.css";

interface EmblaCarouselProps {
  images: any;
  setSelectedProjectId: React.Dispatch<React.SetStateAction<number | null>>;
}
function deleteParameterFromUrl(param: string) {
  const params = new URLSearchParams(window.location.search);
  params.delete(param);

  const newUrl = `${window.location.pathname}?${params.toString()}`;
  window.history.pushState({}, "", newUrl);
}
export function EmblaCarousel({
  images,
  setSelectedProjectId,
}: EmblaCarouselProps) {
  const [emblaRef, emblaApi] = useEmblaCarousel({ loop: false });
  const [currentIndex, setCurrentIndex] = useState(0);

  useEffect(() => {
    if (!emblaApi) return;

    console.log(emblaApi.slideNodes()); // Access API

    const updateIndex = () => {
      setCurrentIndex(emblaApi.selectedScrollSnap());
    };

    emblaApi.on("select", updateIndex);
    updateIndex(); // Ustawienie początkowej wartości

    const handleKeydown = (event: KeyboardEvent) => {
      if (event.defaultPrevented) return;

      switch (event.code) {
        case "ArrowLeft":
          emblaApi.scrollPrev();
          break;
        case "ArrowRight":
          emblaApi.scrollNext();
          break;
        case "Escape":
          setSelectedProjectId(null);
          deleteParameterFromUrl("id");

          break;
      }

      event.preventDefault();
    };

    window.addEventListener("keydown", handleKeydown, true);

    return () => {
      emblaApi.off("select", updateIndex);
      window.removeEventListener("keydown", handleKeydown, true);
    };
  }, [emblaApi, setSelectedProjectId]);

  return (
    <>
      <button
        className={styles.carousel_button}
        id={styles.button_prev}
        onClick={(e) => {
          e.stopPropagation();
          emblaApi && emblaApi.scrollPrev();
        }}
        style={{
          opacity: currentIndex > 0 ? 1 : 0.2,

          transition: "opacity 0.3s ease-in-out",
        }}
      >
        <img
          src="../chevron_left_24dp_E8EAED_FILL0_wght400_GRAD0_opsz24.svg"
          alt="prev"
        />
      </button>

      <button
        className={styles.carousel_button}
        id={styles.button_next}
        onClick={(e) => {
          e.stopPropagation();
          emblaApi && emblaApi.scrollNext();
        }}
        style={{
          opacity: currentIndex < images.length - 1 ? 1 : 0.2,

          transition: "opacity 0.3s ease-in-out",
        }}
      >
        <img
          src="../chevron_right_24dp_E8EAED_FILL0_wght400_GRAD0_opsz24.svg"
          alt="next"
        />
      </button>

      <div className={styles.embla} ref={emblaRef}>
        <div className={styles.embla__container}>
          {images.map((image: string, index: number) => (
            <div key={index} className={styles.embla__slide}>
              <img
                className={styles.carousel_img}
                // src={`https://${import.meta.env.PUBLIC_DOMAIN}/${image}`}
                src={`https://${
                  import.meta.env.PUBLIC_API_DOMAIN
                }/gallery/${image}`}
                width="500"
                alt=""
                onClick={(e) => e.stopPropagation()}
              />
            </div>
          ))}
        </div>
      </div>
    </>
  );
}

export default EmblaCarousel;
