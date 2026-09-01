import { FaWhatsapp } from "react-icons/fa";
import { CONTACT } from "../config";

export default function WhatsAppFab() {
  return (
    <a
      className="wa-fab"
      href={`https://wa.me/${CONTACT.whatsapp}`}
      target="_blank"
      rel="noopener noreferrer"
      aria-label="WhatsApp"
    >
      <FaWhatsapp />
    </a>
  );
}
