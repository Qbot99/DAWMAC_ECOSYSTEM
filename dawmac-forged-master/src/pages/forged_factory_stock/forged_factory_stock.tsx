import styles from "./factory_stock.module.css";
import { FaWhatsapp } from "react-icons/fa";
import { MdOutlineEmail } from "react-icons/md";

export default function FactoryStock() {
  return (
    <>
      <main className={styles.main}>
        <div className={styles.info}>
          <div className={styles.text}>
            <h1>Współpraca z renomowaną fabryką felg kutych</h1>
            <p>
              Nawiązaliśmy współpracę z jedną z największych fabryk
              produkujących felgi kute z aluminium 6061-T6. Felgi te zachowują
              seryjne parametry montażowe. Duża dostępność modeli w magazynie
              fabryki umożliwia szybką realizację zamówień. Transport lotniczy
              pozwala dostarczyć felgi w zaledwie 3–4 tygodnie – od załadunku,
              przez przelot, aż po odprawę celną i dostawę.
            </p>
          </div>

          <div className={styles.pricing}>
            <h2>Cennik</h2>
            <ul className={styles.ul}>
              <li>
                <b>18”</b> - przy przedplacie 100% - 9000zl
              </li>
              <li>
                <b>19”</b> - przy przedplacie 100% - 9400zl
              </li>
              <li>
                <b>20”</b> - przy przedplacie 100% - 10550zl
              </li>
              <li>
                <b>21”</b> - przy przedplacie 100% - 11500zl
              </li>
              <li>
                <b>22”</b> - przy przedplacie 100% - 13200zl
              </li>
            </ul>
            <div className={styles.contact}>
              <h3 className={styles.orderNowText}>
                Po więcej informacji prosimy o kontakt
              </h3>
              <a
                href="https://wa.me/+48518612358"
                className={styles.contactLink}
                target="_blank"
                rel="noreferrer"
              >
                <FaWhatsapp className={styles.socialIcons} />
                <span>+48 518 612 358</span>
              </a>
              <a
                href="mailto:forged@dawmacpolska.pl"
                className={styles.contactLink}
                target="_blank"
                rel="noreferrer"
              >
                <MdOutlineEmail className={styles.socialIcons} />
                <span> forged@dawmacpolska.pl</span>
              </a>
            </div>
          </div>
        </div>

        <div className={styles.linksWrapper}>
          <a href="/certyfikat.png" target="_blank" rel="noreferrer">
            <div className={styles.button}>Certyfikat</div>
          </a>
          <a
            href="/What we can do for youDM.pdf"
            target="_blank"
            rel="noreferrer"
          >
            <div className={styles.button}>Proces produkcji</div>
          </a>
        </div>

        <div className={styles.embedWrapper}>
          <div className={styles.embedElement}>
            <a
              className={styles.button}
              href="https://docs.google.com/spreadsheets/d/1aMDDhkK5X1HkJ0zOw_T38hfS3EDF0al9/edit?usp=sharing&ouid=102213728542570419635&rtpof=true&sd=true"
              target="_blank"
              rel="noreferrer"
            >
              Plik 1
            </a>
            <iframe
              className={`${styles.embed} ${styles.embed1}`}
              src="https://docs.google.com/spreadsheets/d/e/2PACX-1vTzbFknw0JIC6p9qqVYS_UG7Wcd4BcFeBhneb263MC1cjzmrzHsWcivf6OGCRec1g/pubhtml?widget=true&amp;headers=false"
            ></iframe>
          </div>

          <div className={styles.embedElement}>
            <a
              className={styles.button}
              href="https://docs.google.com/spreadsheets/d/10pnH9p_d6UUDJilTmcJWqsE5zTU6sMNv/edit?usp=sharing&ouid=102213728542570419635&rtpof=true&sd=true"
              target="_blank"
              rel="noreferrer"
            >
              Plik 2
            </a>
            <iframe
              className={`${styles.embed} ${styles.embed2}`}
              src="https://docs.google.com/spreadsheets/d/e/2PACX-1vRXIYnOkUk9yYr5cSONQsA4Mf2XK8rGLPsaEEzLG82sosVfa7Nu_at_7xq3H8AZZg/pubhtml?widget=true&amp;headers=false"
            ></iframe>
          </div>

          <div className={styles.embedElement}>
            <a
              className={styles.button}
              href="https://docs.google.com/spreadsheets/d/1f3YSO0R9Ds-pPb95JeaIzdvuMqu8G6OA/edit?usp=sharing&ouid=102012896759522617588&rtpof=true&sd=true"
              target="_blank"
              rel="noreferrer"
            >
              Plik 3
            </a>
            <iframe
              className={`${styles.embed} ${styles.embed3}`}
              src="https://docs.google.com/spreadsheets/d/e/2PACX-1vRxs0IBt_1ZmL9sgiyPR3nT8ZQbA_-uSFnqICNZGtIuQVacz6ScDV7SKLFfqS-qow/pubhtml?widget=true&amp;headers=false"
            ></iframe>
          </div>
        </div>
      </main>
    </>
  );
}
