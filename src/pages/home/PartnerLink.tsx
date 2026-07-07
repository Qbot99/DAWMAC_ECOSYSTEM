import styles from "./PartnerLink.module.css";

export default function PartnerLink() {
  return (
    <div className={styles.wrapper}>
      <div className={styles.text}>
        <h2>Zapraszamy również do zapoznania się z ofertą naszego partnera.</h2>
        <p>Oferuje on wysokiej jakości felgi kute w okazyjnych cenach.</p>
      </div>
      <div className={styles.pliki}>
        <a
          href="https://docs.google.com/spreadsheets/d/1aMDDhkK5X1HkJ0zOw_T38hfS3EDF0al9/edit?usp=sharing&ouid=102213728542570419635&rtpof=true&sd=true"
          target="_blank"
          rel="noreferrer"
          className={styles.button}
        >
          Plik 1
        </a>
        <a
          href="https://docs.google.com/spreadsheets/d/10pnH9p_d6UUDJilTmcJWqsE5zTU6sMNv/edit?usp=sharing&ouid=102213728542570419635&rtpof=true&sd=true"
          target="_blank"
          rel="noreferrer"
          className={styles.button}
        >
          Plik 2
        </a>
        <a
          href="https://docs.google.com/spreadsheets/d/1f3YSO0R9Ds-pPb95JeaIzdvuMqu8G6OA/edit?usp=sharing&ouid=102012896759522617588&rtpof=true&sd=true"
          target="_blank"
          rel="noreferrer"
          className={styles.button}
        >
          Plik 3
        </a>
      </div>
    </div>
  );
}
