import styles from "./SeriesSection.module.css";

export default function SeriesSection() {
  return (
    <section className={styles.section}>
      <h2 className={styles.heading}>Nasze serie felg</h2>
      <div className={styles.grid}>
        <a href="/wheel?s=1">
          <div className={styles.card}>
            <img
              src="/monoblock.jpeg"
              alt="Monoblock"
              width={400}
              height={400}
              className={styles.image}
            />
            <h3 className={styles.title}>Monoblock</h3>
            <p className={styles.description}>
              Lekkie, jednolite felgi zaprojektowane z myślą o maksymalnej
              wydajności.
            </p>
            <button className={styles.button}>Zobacz serię</button>
          </div>
        </a>
        <a href="/wheel?s=2">
          <div className={styles.card}>
            <img
              src="/two_piece.jpeg"
              alt="Wieloczęściowe"
              width={400}
              height={400}
              className={styles.image}
            />
            <h3 className={styles.title}>Dwuczęściowe</h3>
            <p className={styles.description}>
              Dla entuzjastów customizacji — pełna kontrola nad stylem i
              osiągami.
            </p>
            <button className={styles.button}>Zobacz serię</button>
          </div>
        </a>
      </div>
    </section>
  );
}
