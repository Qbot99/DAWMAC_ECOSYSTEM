export const API_BASE = "https://api.dawmacpolska.pl";
export const GALLERY_API = "https://galeria.dawmacpolska.pl/api";
/** nowa galeria na dawmac.pl (stara strona galeria.dawmacpolska.pl wycofana z linków; API zostaje) */
export const GALLERY_SITE = "https://dawmac.pl/galeria/";
/** galeria zawężona do realizacji Forged (?q= trafia do wyszukiwarki galerii → search_keyword) */
export const GALLERY_FORGED_URL = `${GALLERY_SITE}?q=forged`;

export const WHEELS_ENDPOINT = `${API_BASE}/api/forged/list_wheels.php`;
export const PROJECTS_ENDPOINT = `${GALLERY_API}/get_projects.php`;
export const PROJECT_DETAILS_ENDPOINT = `${GALLERY_API}/get_project_details.php`;

export const wheelImg = (path: string) => `${API_BASE}/forged/${path}`;
export const galleryImg = (path: string) => `${API_BASE}/gallery/${path}`;
/** deep-link do konkretnej realizacji w nowej galerii (otwiera jej lightbox) */
export const galleryProjectUrl = (projectId: string) =>
  `${GALLERY_SITE}?project=${encodeURIComponent(projectId)}`;

export const CONTACT = {
  // WhatsApp i telefon to ten sam numer
  whatsapp: "+48518612358",
  whatsappDisplay: "+48 518 612 358",
  phone: "+48518612358",
  phoneDisplay: "+48 518 612 358",
  email: "dawmacpolska@gmail.com",
  address: "Perzów 109B, 63-642 Perzów",
  mapsUrl: "https://maps.google.com/?q=Perz%C3%B3w+109B,+63-642+Perz%C3%B3w,+Polska",
};

/** felgi kute dostępne od ręki — sklep na dawmac.pl */
export const STORE_URL = "https://dawmac.pl/kategoria-produktu/dawmac-forged/";

export const SOCIAL = {
  facebook: "https://www.facebook.com/dawmacpolska",
  instagram: "https://www.instagram.com/dawmacpolska/",
  youtube: "https://www.youtube.com/@dawmac2418",
  tiktok: "https://www.tiktok.com/@dawmacpolska",
};

/** series_id (baza) -> prefiks nazw felg; etykiety serii są w i18n (seriesNames) */
export const SERIES: Record<string, { prefix: string }> = {
  "1": { prefix: "FM" },
  "2": { prefix: "FD" },
  "3": { prefix: "FT" },
  "4": { prefix: "FMG" },
  "5": { prefix: "FS" },
};

/** serie z tabelą cen; pozostałe = wycena indywidualna (decyzja: 3-częściowe/Magnesium/Factory Stock bez tabel) */
export const PRICED_SERIES = ["1", "2"];
