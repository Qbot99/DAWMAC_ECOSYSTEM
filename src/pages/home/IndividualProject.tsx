import styles from "./IndividualProject.module.css";

export default function IndividualProject() {
  return (
    <div className={styles.wrapper}>
      <div className={styles.content}>
        <h2 className={styles.h2}>Indywidualny projekt</h2>
        <p className={styles.p}>
          Oferujemy również możliwość zaprojektowania felgi dokładnie według
          Twojej wizji. Jeśli masz własny pomysł na wzór felgi – zrealizujemy go
          od zera. Niezależnie, czy chcesz dopasować felgę do charakteru auta,
          czy stworzyć unikalny design – wykonamy indywidualny projekt i
          wyprodukujemy go jako w pełni funkcjonalną felgę kutą. Skontaktuj się
          z nami i stwórz coś, czego nie ma nikt inny.
        </p>
        <a
          href="https://dawmacpolska.pl/kontakt/"
          target="_blank"
          rel="noreferrer"
        >
          <div className={styles.button}>Kontakt</div>
        </a>
      </div>
    </div>
  );
}
