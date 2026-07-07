export const API_BASE = "https://api.dawmacpolska.pl";
export const GALLERY_API = "https://galeria.dawmacpolska.pl/api";
export const GALLERY_SITE = "https://galeria.dawmacpolska.pl";

export const WHEELS_ENDPOINT = `${API_BASE}/api/forged/list_wheels.php`;
export const PROJECTS_ENDPOINT = `${GALLERY_API}/get_projects.php`;

export const wheelImg = (path: string) => `${API_BASE}/forged/${path}`;
export const galleryImg = (path: string) => `${API_BASE}/gallery/${path}`;

export const CONTACT = {
  whatsapp: "+48518612358",
  whatsappDisplay: "+48 518 612 358",
  phone: "+48518612358",
  phoneDisplay: "+48 518 612 358",
  email: "forged@dawmacpolska.pl",
};

export const SOCIAL = {
  facebook: "https://www.facebook.com/dawmacpolska",
  instagram: "https://www.instagram.com/dawmacpolska/",
  youtube: "https://www.youtube.com/@dawmac2418",
  tiktok: "https://www.tiktok.com/@dawmacpolska",
};

/** series_id (baza) -> prefiks/seria produktowa */
export const SERIES: Record<
  string,
  { prefix: string; label: string }
> = {
  "1": { prefix: "FM", label: "Forged Mono" },
  "2": { prefix: "FD", label: "Forged Duo" },
  "3": { prefix: "FT", label: "Forged Trio" },
  "4": { prefix: "FMG", label: "Forged Magnesium" },
  "5": { prefix: "FS", label: "Factory Stock" },
};

/** serie z tabelą cen; pozostałe = wycena indywidualna (decyzja: Trio/Magnesium/Factory Stock bez tabel) */
export const PRICED_SERIES = ["1", "2"];

export const seriesLabel = (seriesId: string) =>
  SERIES[seriesId]?.label ?? "";
