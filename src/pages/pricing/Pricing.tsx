import { useState, useEffect } from "react";
import styles from "./Pricing.module.css";

interface SizePrice {
  rozmiar: string;
  cena_katalogowa?: number;
  przedplata_100: number;
}

interface PriceCategory {
  rozmiary: SizePrice[];
  dodatki?: Record<string, number>;
}

export default function Pricing() {
  const [pricesData, setPricesData] = useState<Record<string, PriceCategory> | null>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    async function fetchPrices() {
      try {
        const res = await fetch(window.location.origin + "/prices.json");
        const data = await res.json();
        setPricesData(data);
      } catch (err) {
        console.error("Failed to fetch prices:", err);
      } finally {
        setLoading(false);
      }
    }
    fetchPrices();
  }, []);

  if (loading) {
    return <div className={styles.main}>Ładowanie cennika...</div>;
  }

  const formatPrice = (price?: number) => price ? `${price.toLocaleString("pl-PL")} zł` : "";
  const formatKeyName = (key: string) => {
    return key
      .split("_")
      .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
      .join(" ");
  };

  const monoblock = pricesData?.["1"];
  const dwuczesciowe = pricesData?.["2"];
  return (
    <>
      {/* TODO: Do kompletnego remontu */}
      <main className={styles.main}>
        <h1 className={styles.title}>AKTUALNA CENA FELG DAWMAC FORGED</h1>
        <p className={styles.subtitle}>(Cena dotyczy kompletu czterech felg)</p>

        <div className={styles.grid}>
          {/* FELGI MONOBLOCK */}
          <section className={styles.section}>
            <h3 className={styles.sectionTitle}>FELGI MONOBLOCK</h3>
            <ul className={styles.list}>
              {monoblock?.rozmiary.map((item) => (
                <li key={item.rozmiar}>
                  <span className="font-bold">{item.rozmiar}″ – {formatPrice(item.cena_katalogowa)}</span>{item.cena_katalogowa ? " cena katalogowa, " : ""}
                  <span className="font-semibold">
                    przy przedpłacie 100% – {formatPrice(item.przedplata_100)}
                    {Number(item.rozmiar) >= 19 ? "!" : ""}
                  </span>
                </li>
              ))}
            </ul>

            {monoblock?.dodatki && (
              <>
                <h4 className={styles.headingMedium}>Dodatki do felg monoblock:</h4>
                <ul className={styles.listDisc}>
                  {Object.entries(monoblock.dodatki).map(([key, value]) => (
                    <li key={key}>
                      {formatKeyName(key)} – <span className="font-bold">{formatPrice(value)}</span>
                    </li>
                  ))}
                </ul>
              </>
            )}
          </section>

          {/* FELGI DWUCZĘŚCIOWE */}
          <section className={styles.section}>
            <h3 className={styles.sectionTitle}>FELGI DWUCZĘŚCIOWE</h3>
            <ul className={styles.list}>
              {dwuczesciowe?.rozmiary.map((item) => (
                <li key={item.rozmiar}>
                  <span className="font-bold">{item.rozmiar}″ – {item.cena_katalogowa ? formatPrice(item.cena_katalogowa) : formatPrice(item.przedplata_100)}</span>
                  {item.cena_katalogowa ? (
                    <>
                      {" "}cena katalogowa,{" "}
                      <span className="font-semibold">
                        przy przedpłacie 100% – {formatPrice(item.przedplata_100)}!
                      </span>
                    </>
                  ) : null}
                </li>
              ))}
            </ul>

            {dwuczesciowe?.dodatki && (
              <>
                <h4 className={styles.headingMedium}>
                  Dodatki do felg dwuczęściowych:
                </h4>
                <ul className={styles.listDisc}>
                  {Object.entries(dwuczesciowe.dodatki).map(([key, value]) => (
                    <li key={key}>
                      {formatKeyName(key)} – <span className="font-bold">{formatPrice(value)}</span>
                    </li>
                  ))}
                </ul>
              </>
            )}
          </section>
        </div>
      </main>
    </>
  );
}
