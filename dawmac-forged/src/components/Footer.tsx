import { FaFacebookF, FaInstagram, FaYoutube, FaTiktok } from "react-icons/fa";
import styles from "./Footer.module.css";

export default function Footer() {
  return (
    <footer className={styles.footer}>
      <div className={styles.container}>
        <div>
          <h2 className={styles.heading}>DAWMAC POLSKA</h2>
          <p className={styles.description}>
            Felgi Aluminiowe Dawmac - 18 lat na rynku, 7000 felg na stanie,
            450m² ekspozycji.
          </p>
        </div>

        <div className={styles.links}>
          <a href="https://dawmacpolska.pl/o-nas/" className={styles.link}>
            O nas
          </a>
          <a
            href="https://dawmacpolska.pl/regulamin/"
            className={styles.link}
            target="_blank"
            rel="noreferrer"
          >
            Regulamin
          </a>
          <a
            href="https://dawmacpolska.pl/cennik-wysylek/"
            className={styles.link}
            target="_blank"
            rel="noreferrer"
          >
            Cennik wysyłek
          </a>
          <a
            href="https://dawmacpolska.pl/polityka-prywatnosci/"
            className={styles.link}
            target="_blank"
            rel="noreferrer"
          >
            Polityka prywatności
          </a>
          <a
            href="https://dawmacpolska.pl/wp-content/uploads/2023/04/Formularz-Reklamacyjny-Dawmac.docx"
            className={styles.link}
            target="_blank"
            rel="noreferrer"
          >
            Formularz reklamacyjny
          </a>
        </div>

        <div className={styles.socialSection}>
          <h3 className={styles.socialHeading}>Obserwuj nas</h3>
          <div className={styles.socialIcons}>
            <a
              href="https://www.facebook.com/dawmacpolska"
              target="_blank"
              rel="noopener noreferrer"
              className={styles.socialLink}
            >
              <FaFacebookF />
            </a>
            <a
              href="https://www.instagram.com/dawmacpolska/"
              target="_blank"
              rel="noopener noreferrer"
              className={styles.socialLink}
            >
              <FaInstagram />
            </a>
            <a
              href="https://www.youtube.com/@dawmac2418"
              target="_blank"
              rel="noopener noreferrer"
              className={styles.socialLink}
            >
              <FaYoutube />
            </a>
            <a
              href="https://www.tiktok.com/@dawmacpolska"
              target="_blank"
              rel="noopener noreferrer"
              className={styles.socialLink}
            >
              <FaTiktok />
            </a>
          </div>
        </div>
      </div>

      <div className={styles.bottom}>
        © {new Date().getFullYear()} DAWMAC Polska. Wszelkie prawa zastrzeżone.
      </div>
    </footer>
  );
}
