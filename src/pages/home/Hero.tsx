import styles from "./Hero.module.css";

export default function Hero() {
  return (
    <section className={styles.heroSection}>
      <video
        src="/hd.mp4"
        muted
        autoPlay
        loop
        playsInline
        className={styles.videoBg}
      ></video>
      <div className={styles.content}>
        <h1 className={styles.heading}>DAWMAC FORGED</h1>
        <h2 className={styles.subheading}>Precyzja. Osiągi. Charakter.</h2>
        <div className={styles.button}>
          <a href="/wheel">
            <button className={styles.btnPrimary}>Zobacz felgi</button>
          </a>
          <a
            href="https://dawmacpolska.pl/kontakt/"
            target="_blank"
            rel="noreferrer"
          >
            <button className={styles.btnSecondary}>Zamów projekt</button>
          </a>
        </div>
      </div>
    </section>
  );
}
