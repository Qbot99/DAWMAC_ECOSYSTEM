import { FaFacebookF, FaInstagram, FaTiktok, FaYoutube } from "react-icons/fa";
import { SOCIAL, STORE_URL } from "../config";
import { useLang } from "../i18n";

export default function FooterNew() {
  const { t } = useLang();

  return (
    <footer className="footer">
      <div className="footer__grid">
        <div>
          <a href="#top" className="nav__brand">
            <span className="nav__logo">DAWMAC</span>
            <span className="nav__badge">Forged</span>
          </a>
          <p className="footer__desc">{t.heroSub}</p>
        </div>

        <div>
          <div className="footer__heading">Dawmac</div>
          <div className="footer__links">
            <a href={STORE_URL} className="footer__link" target="_blank" rel="noreferrer">
              {t.navStore}
            </a>
            <a href="https://dawmacpolska.pl/o-nas/" className="footer__link" target="_blank" rel="noreferrer">
              {t.footAbout}
            </a>
            <a href="https://dawmacpolska.pl/regulamin/" className="footer__link" target="_blank" rel="noreferrer">
              {t.footTerms}
            </a>
            <a href="https://dawmacpolska.pl/polityka-prywatnosci/" className="footer__link" target="_blank" rel="noreferrer">
              {t.footPrivacy}
            </a>
          </div>
        </div>

        <div>
          <div className="footer__heading">{t.footFollow}</div>
          <div className="footer__social">
            <a href={SOCIAL.facebook} target="_blank" rel="noopener noreferrer" aria-label="Facebook">
              <FaFacebookF />
            </a>
            <a href={SOCIAL.instagram} target="_blank" rel="noopener noreferrer" aria-label="Instagram">
              <FaInstagram />
            </a>
            <a href={SOCIAL.youtube} target="_blank" rel="noopener noreferrer" aria-label="YouTube">
              <FaYoutube />
            </a>
            <a href={SOCIAL.tiktok} target="_blank" rel="noopener noreferrer" aria-label="TikTok">
              <FaTiktok />
            </a>
          </div>
        </div>
      </div>

      <div className="footer__bottom">
        <span>
          © {new Date().getFullYear()} DAWMAC Polska. {t.footRights}
        </span>
        <a
          className="credit"
          href="https://hkubot.com"
          target="_blank"
          rel="noopener noreferrer"
        >
          <span className="credit__line" />
          <span className="credit__text">
            designed &amp; engineered by{" "}
            <span className="credit__name">
              Kubot&nbsp;<span className="credit__h">H</span>
            </span>
          </span>
        </a>
        <span>forged.dawmacpolska.pl</span>
      </div>
    </footer>
  );
}
