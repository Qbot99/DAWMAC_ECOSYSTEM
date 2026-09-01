import { useEffect, useState } from "react";
import styles from "./List.module.css";
import Filter from "./Filter"; // zakładam, że to client component

type WheelData = {
  id: number;
  name: string;
  series_name: string;
  images: string[];
  youtube_url: string;
};

export default function ListPage() {
  const [wheels, setWheels] = useState<WheelData[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    async function fetchWheels() {
      try {
        const res = await fetch(
          "https://api.dawmacpolska.pl/api/forged/list_wheels.php"
        );
        if (!res.ok) throw new Error("Błąd pobierania danych");
        const data = await res.json();
        setWheels(data);
      } catch (error) {
        console.error(error);
        setWheels([]);
      } finally {
        setLoading(false);
      }
    }
    fetchWheels();
  }, []);

  return (
    <>
      <main className={styles.main}>
        {loading ? <div>Ładowanie filtrów...</div> : <Filter wheels={wheels} />}
      </main>
    </>
  );
}
