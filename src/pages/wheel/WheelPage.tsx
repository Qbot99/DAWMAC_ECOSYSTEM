import { useEffect, useState } from "react";
import { useParams } from "react-router-dom";
import DiameterAndPrice from "./DiameterAndPrice";
import WheelPageCarousel from "./WheelPageCarousel";
import ProjectGallery from "./ProjectsGallery";
import styles from "./Wheel.module.css";
import { FaWhatsapp } from "react-icons/fa";
import { MdOutlineEmail } from "react-icons/md";

interface Wheel {
  id: string;
  name: string;
  series_name: string;
  series_id: string;
  images: string[];
  youtube_url: string;
  description?: string;
}

export default function WheelPage() {
  const { name } = useParams<{ name: string }>();
  const [wheels, setWheels] = useState<Wheel[]>([]);
  const [prices, setPrices] = useState<Record<string, unknown>>({});
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    async function fetchData() {
      try {
        const resWheels = await fetch(
          "https://api.dawmacpolska.pl/api/forged/list_wheels.php"
        );
        const wheelsData = await resWheels.json();
        setWheels(wheelsData);

        const resPrices = await fetch(
          // "https://api.dawmacpolska.pl/forged/prices.json"
          // "https://forged.dawmacpolska.pl/prices.json"
          window.location.origin + "/prices.json"
        );
        const pricesData = await resPrices.json();
        setPrices(pricesData);
        console.log("prices" + pricesData);
      } catch (err) {
        console.error("Błąd pobierania danych:", err);
      } finally {
        setLoading(false);
      }
    }

    fetchData();
  }, []);

  if (loading) return <div className={styles.loadingPlaceholder}></div>;

  const wheel = wheels.find(
    (w) => w.name.toLowerCase() === name?.toLowerCase()
  );

  if (!wheel) {
    return <div>Nie znaleziono felgi</div>;
  }

  if (!prices[wheel.series_id]) {
    return <div>Brak danych cenowych dla serii: {wheel.series_id}</div>;
  }

  return (
    <>
      <main className={styles.main}>
        <div className={styles.primaryWrapper}>
          <div className={styles.leftColumn}>
            <h1 className={styles.title}>Dawmac Forged {wheel.name}</h1>
            <WheelPageCarousel slides={wheel.images} />
          </div>

          <div className={styles.rightColumn}>
            <div className={styles.rightContent}>
              <p className={styles.priceLabel}>Cena za komplet 4 felg</p>
              <p className={styles.seriesLabel}>
                Seria:{" "}
                <span className={styles.seriesName}>{wheel.series_name}</span>
              </p>

              {/* @ts-expect-error: priceData type may not match expected prop type */}
              <DiameterAndPrice priceData={prices[wheel.series_id]} />

              <div className={styles.order}>
                <h3 className={styles.orderNowText}>Zamów teraz</h3>
                <a
                  href="https://wa.me/+48518612358"
                  className={styles.contactLink}
                  target="_blank"
                  rel="noopener noreferrer"
                >
                  <FaWhatsapp className={styles.socialIcons} />
                  <span>+48 518 612 358</span>
                </a>
                <a
                  href="mailto:forged@dawmacpolska.pl"
                  className={styles.contactLink}
                  target="_blank"
                  rel="noopener noreferrer"
                >
                  <MdOutlineEmail className={styles.socialIcons} />
                  <span> forged@dawmacpolska.pl</span>
                </a>
              </div>
              <p className={styles.note}>
                Realizacja zamówienia trwa około 8 tygodni (często udaje się
                szybciej). (od chwili akceptacji projektu) na wyprodukowanie
                felg oraz około 14 dni wysyłka pocztą lotniczą i odprawa celna.
              </p>
            </div>
          </div>

          {wheel.description && (
            <div className={styles.description}>
              <b>Opis:</b>
              <br />
              <p>{wheel.description}</p>
            </div>
          )}
        </div>

        <div className={styles.mediaWrapper}>
          {wheel.youtube_url && (
            <iframe
              className={styles.youtubeEmbed}
              src={`https://www.youtube.com/embed/${wheel.youtube_url}`}
              title={wheel.name}
            ></iframe>
          )}
          <ProjectGallery wheel_name={wheel.name} />
        </div>
      </main>
    </>
  );
}
