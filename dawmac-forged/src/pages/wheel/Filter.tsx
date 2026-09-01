import { useState, useMemo } from "react";
import styles from "./List.module.css";
import { Link } from "react-router-dom";

type WheelData = {
  id: number;
  name: string;
  series_name: string;
  images: string[];
};

type FilterProps = {
  wheels: WheelData[];
};

export default function Filter({ wheels }: FilterProps) {
  const [selectedSeries, setSelectedSeries] = useState<number>(0);

  const filteredWheels = useMemo(() => {
    switch (selectedSeries) {
      case 1:
        return wheels.filter(
          (w) => w.series_name.toLowerCase() === "monoblock"
        );
      case 2:
        return wheels.filter((w) =>
          w.series_name.toLowerCase().includes("dwuczęści")
        );
      case 3:
        return wheels.filter((w) =>
          w.series_name.toLowerCase().includes("trzyczęściowe")
        );
      default:
        return wheels;
    }
  }, [selectedSeries, wheels]);

  return (
    <>
      <div className={styles.filters}>
        <div className={styles.series_filter}>
          <h3>Seria</h3>
          <div className={styles.filterItems}>
            {[0, 1, 2, 3].map((value) => {
              const labels = [
                "Wszystkie",
                "Monoblock",
                "Dwuczęściowe",
                "Trzyczęściowe",
              ];
              return (
                <div key={value} className={styles.filter_item}>
                  <input
                    type="radio"
                    name="series"
                    id={`radio_${value}`}
                    checked={selectedSeries === value}
                    onChange={() => setSelectedSeries(value)}
                  />
                  <label htmlFor={`radio_${value}`}>{labels[value]}</label>
                </div>
              );
            })}
          </div>
        </div>
      </div>

      <div className={styles.list}>
        {filteredWheels.map((wheel) => (
          <Link to={"./" + wheel.name}>
            <div className={styles.wheel} key={wheel.id}>
              <img
                src={`https://api.dawmacpolska.pl/forged/${wheel.images[0]}`}
                alt={`Dawmac Forged ${wheel.name}`}
                className={styles.image}
                width={300}
                height={300}
              />
              <div className={styles.text}>
                <h2 className={styles.title}>Dawmac Forged {wheel.name}</h2>
                <p className={styles.series_name}>{wheel.series_name}</p>
              </div>
            </div>
          </Link>
        ))}
      </div>
    </>
  );
}
