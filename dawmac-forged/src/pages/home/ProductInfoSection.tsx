import styles from "./ProductInfoSection.module.css";

export default function ProductInfoSection() {
  return (
    <section className={styles.section}>
      <img
        src="/d.jpg"
        alt="Chevrolet Corvette"
        width={800}
        height={600}
        className={styles.image}
      />
      <div className={styles.contentWrapper}>
        <div className={styles.content}>
          <h3 className={styles.heading}>Autorskie felgi klasy premium</h3>
          <p className={styles.paragraph}>
            Felgi naszej autorskiej marki, wykonane z najwyższej jakości
            aluminium używanego do produkcji felg – <strong>6061-T6</strong>.
            <br />
            Bardzo lekkie i wytrzymałe, zaprojektowane z myślą o stylu i
            osiągach.
          </p>
          <br />
          <span className={styles.paragraph}>
            W ofercie dostępna całkowita personalizacja felg:
            <ul className={styles.list}>
              <li>możliwość zamówienia felg monoblock lub dwuczęściowych</li>
              <li>możliwość wykonania dowolnego wzoru felg</li>
              <li>
                wykonanie indywidualnych parametrów felg (rozmiar, szerokość,
                ET, rozstaw, otwór centrujący)
              </li>
            </ul>
          </span>
        </div>
        <button className={styles.button}>Kontakt</button>
      </div>
    </section>
  );
}
