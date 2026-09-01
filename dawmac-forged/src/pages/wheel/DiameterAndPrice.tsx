import { useState } from "react";
import styles from "./DiameterAndPrice.module.css";

type Rozmiar = {
  rozmiar: string;
  cena_katalogowa?: number;
  przedplata_100: number;
  ceny_poczta_lotnicza?: number;
};

type Props = {
  priceData: {
    rozmiary: Rozmiar[];
  };
};

export default function DiameterAndPrice({ priceData }: Props) {
  const [selectedDiameter, setSelectedDiameter] = useState<string>(
    priceData.rozmiary[0].rozmiar
  );

  const selected = priceData.rozmiary.find(
    (r) => r.rozmiar === selectedDiameter
  );

  return (
    <div className={styles.container}>
      <h2 className={styles.title}>Dostępne średnice:</h2>
      <div className={styles.buttonsWrapper}>
        {priceData.rozmiary.map((rozmiar) => (
          <button
            key={rozmiar.rozmiar}
            className={`${styles.button} ${
              selectedDiameter === rozmiar.rozmiar ? styles.buttonActive : ""
            }`}
            onClick={() => setSelectedDiameter(rozmiar.rozmiar)}
          >
            {rozmiar.rozmiar}"
          </button>
        ))}
      </div>

      {selected && (
        <div className={styles.priceBlock}>
          {selected.cena_katalogowa && (
            <p className={styles.priceLine}>
              Cena katalogowa:{" "}
              <span className={styles.priceBold}>
                {selected.cena_katalogowa}zł
              </span>
            </p>
          )}

          <p className={styles.priceLine}>
            Cena przy przedpłacie 100%:{" "}
            <span className={styles.priceBold}>
              {selected.przedplata_100}zł
            </span>
          </p>

          {selected.ceny_poczta_lotnicza && (
            <p className={styles.priceLine}>
              Poczta lotnicza (przedpłata 100%):{" "}
              <span className={styles.priceBold}>
                {selected.ceny_poczta_lotnicza}zł
              </span>
            </p>
          )}
        </div>
      )}
    </div>
  );
}
