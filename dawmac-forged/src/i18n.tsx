/* eslint-disable react-refresh/only-export-components */
import { createContext, useContext, useEffect, useState } from "react";
import type { ReactNode } from "react";

export type Lang = "pl" | "en" | "de";

const dict = {
  pl: {
    nav: ["Cennik", "Kontakt"],
    ctaCat: "Zobacz katalog",
    ctaPrice: "Cennik",
    hero1: "FELGI",
    hero2: "KUTE.",
    heroSub:
      "Projektujemy i produkujemy koła kute na zamówienie - Forged Mono, Duo, Trio i Magnesium. Aluminium 6061 T6, dowolny wzór, dowolne parametry, dowolne malowanie.",
    statYears: "lat w branży",
    statAlloy: "stop T6",
    statSizes: "rozmiary",
    catKicker: "Katalog / 01",
    catTitle: "Katalog felg",
    searchPh: "Szukaj, np. FM12...",
    fAll: "Wszystkie",
    showMore: "Pokaż więcej",
    from: "od",
    indiv: "wycena indywidualna",
    noResults: "Brak felg dla tego filtra.",
    loadError: "Nie udało się pobrać katalogu. Odśwież stronę.",
    lbSize: "Średnica",
    lbCat: "Cena katalogowa",
    lbPre: "Przedpłata 100%",
    lbAir: "Poczta lotnicza (przedpłata 100%)",
    lbAirVal: "wycena przy zamówieniu",
    lbBuilds: "Realizacje z tą felgą",
    lbAskBtn: "Zapytaj o tę felgę",
    lbShare: "Udostępnij",
    toast: "Link skopiowany!",
    lbNote: "Cena za komplet 4 felg. Dostawa ok. 6 tygodni.",
    lbMore: "Zobacz więcej w galerii",
    indivTitle: "Wycena indywidualna",
    indivDesc:
      "Ten model wyceniamy indywidualnie pod rozmiar, parametry i wykończenie. Napisz do nas - odpowiemy z pełną wyceną.",
    techKicker: "Technologie / 02",
    techTitle: "Cztery serie",
    techMonoDesc: "felgi jednoczęściowe",
    techDuoDesc: "felgi dwuczęściowe",
    techTrioDesc: "felgi trzyczęściowe",
    techMagDesc: "felgi magnezowe",
    techMonoTxt:
      "Jeden blok kutego aluminium 6061 T6. Maksymalna sztywność i najniższa masa przy klasycznej linii.",
    techDuoTxt:
      "Obręcz i rant skręcane. Głębokie profile, wymienne ranty i najszerszy wachlarz wykończeń.",
    techTrioTxt:
      "Trzyczęściowa konstrukcja z wymiennymi rantami. Pełna personalizacja profilu i głębokości.",
    techMagTxt:
      "Felgi magnezowe wykonujemy indywidualnie pod projekt i samochód. Motorsportowa masa absolutna.",
    techFrom: "Komplet od",
    techMagBadge: "Na zamówienie",
    techMagCta: "Zapytaj o wycenę",
    techGoPrice: "Cennik",
    cenKicker: "Cennik / 03",
    cenTitle: "Aktualne ceny",
    cenBadge: "Cena dotyczy kompletu czterech felg",
    colCat: "Katalogowa",
    colPre: "Przedpłata 100%",
    colAir: "Poczta lotnicza",
    addonsTitle: "Dodatki",
    cenNote:
      "Ceny przedpłatowe obowiązują przy 100% przedpłacie. Ceny dodatków liczone za komplet. ET i rozstaw PCD dobierane pod samochód. Realizacja ok. 6 tygodni.",
    trioIndiv:
      "Ceny felg trzyczęściowych ustalamy indywidualnie pod projekt. Napisz do nas z rozmiarem i modelem auta - odpowiemy z pełną wyceną.",
    magBar: "Forged Magnesium - wycena indywidualna",
    contactLink: "Skontaktuj się",
    orderBtn: "Zamów komplet",
    whyKicker: "Wiedza / 04",
    whyTitle: "Dlaczego kute",
    why1T: "Wytrzymałość",
    why1D:
      "Kucie zagęszcza strukturę stopu - koło znosi obciążenia, których odlew nie przetrwa.",
    why2T: "Niska masa",
    why2D:
      "Mniej masy nieresorowanej - lepsze prowadzenie, przyspieszenie i hamowanie.",
    why3T: "Personalizacja",
    why3D: "Rozmiar, ET, kolor i wykończenie dobierane pod konkretny samochód.",
    realKicker: "Realizacje / 05",
    realTitle: "Realizacje",
    conKicker: "Kontakt / 06",
    conTitle1: "Zapytaj",
    conTitle2: "o wycenę",
    conSub:
      "Napisz jaki masz samochód i czego szukasz - odpowiemy z doborem rozmiaru, ET i wyceną kompletu. Dostawa ok. 6 tygodni.",
    points: [
      "Najwyższa jakość - aluminium 6061 T6",
      "Dowolny wzór felg",
      "Dowolne parametry",
      "Dowolne malowanie",
      "Mono, Duo, Trio i Magnesium",
      "Dostawa ok. 6 tygodni",
    ],
    fName: "Imię",
    fContact: "Telefon lub e-mail",
    fCar: "Marka i model auta",
    fCarPh: "np. BMW M4 G82",
    fMsg: "Wiadomość",
    fMsgPh: "Opisz czego szukasz...",
    send: "Wyślij zapytanie",
    sending: "Wysyłanie...",
    sentT: "Wysłane!",
    sentM: "Dzięki za wiadomość - odezwiemy się z wyceną.",
    sendErr: "Nie udało się wysłać. Napisz na WhatsApp lub e-mail.",
    asking: "Pytanie o:",
    chWhats: "WhatsApp",
    chPhone: "Telefon",
    chMail: "E-mail",
    chLoc: "Lokalizacja",
    chLocVal: "Perzów 109B, 63-642 Perzów",
    navStore: "W magazynie",
    footAbout: "O nas",
    footTerms: "Regulamin",
    footPrivacy: "Polityka prywatności",
    footFollow: "Obserwuj nas",
    footRights: "Wszelkie prawa zastrzeżone.",
    addonNames: {
      chrom: "Chrom",
      plywajacy_kapsel: "Pływający kapsel",
      aluminium_szczotkowane: "Aluminium szczotkowane",
      polerowany_front: "Polerowany front",
      dwa_kolory: "Dwa kolory",
      duze_aluminiowe_kapsle: "Duże aluminiowe kapsle",
      grawerunek: "Grawerunek",
      extreme_deep_concave: "Extreme Deep Concave",
      electroplating_and_polishing: "Electroplating & Polishing",
    } as Record<string, string>,
  },
  en: {
    nav: ["Pricing", "Contact"],
    ctaCat: "View catalog",
    ctaPrice: "Pricing",
    hero1: "FORGED",
    hero2: "WHEELS.",
    heroSub:
      "We design and manufacture custom forged wheels - Forged Mono, Duo, Trio and Magnesium. 6061 T6 aluminium, any design, any specs, any finish.",
    statYears: "years in the industry",
    statAlloy: "T6 alloy",
    statSizes: "sizes",
    catKicker: "Catalog / 01",
    catTitle: "Wheel catalog",
    searchPh: "Search, e.g. FM12...",
    fAll: "All",
    showMore: "Show more",
    from: "from",
    indiv: "individual quote",
    noResults: "No wheels match this filter.",
    loadError: "Could not load the catalog. Please refresh the page.",
    lbSize: "Diameter",
    lbCat: "List price",
    lbPre: "100% prepayment",
    lbAir: "Air mail (100% prepayment)",
    lbAirVal: "quoted at order",
    lbBuilds: "Builds with this wheel",
    lbAskBtn: "Ask about this wheel",
    lbShare: "Share",
    toast: "Link copied!",
    lbNote: "Price per set of 4 wheels. Delivery approx. 6 weeks.",
    lbMore: "See more in the gallery",
    indivTitle: "Individual quote",
    indivDesc:
      "This model is quoted individually based on size, specs and finish. Write to us - we will reply with a full quote.",
    techKicker: "Technologies / 02",
    techTitle: "Four series",
    techMonoDesc: "one-piece wheels",
    techDuoDesc: "two-piece wheels",
    techTrioDesc: "three-piece wheels",
    techMagDesc: "magnesium wheels",
    techMonoTxt:
      "A single block of forged 6061 T6 aluminium. Maximum stiffness and lowest weight with a classic line.",
    techDuoTxt:
      "Bolted barrel and lip. Deep profiles, replaceable lips and the widest range of finishes.",
    techTrioTxt:
      "Three-piece construction with replaceable lips. Full customization of profile and depth.",
    techMagTxt:
      "Magnesium wheels are made individually per project and car. Absolute motorsport weight.",
    techFrom: "Set from",
    techMagBadge: "Made to order",
    techMagCta: "Ask for a quote",
    techGoPrice: "Pricing",
    cenKicker: "Pricing / 03",
    cenTitle: "Current prices",
    cenBadge: "Price applies to a set of four wheels",
    colCat: "List price",
    colPre: "100% prepayment",
    colAir: "Air mail",
    addonsTitle: "Add-ons",
    cenNote:
      "Prepayment prices apply with 100% prepayment. Add-on prices per set. ET and PCD matched to your car. Lead time approx. 6 weeks.",
    trioIndiv:
      "Three-piece wheel prices are quoted individually per project. Write to us with your size and car model - we will reply with a full quote.",
    magBar: "Forged Magnesium - individual quote",
    contactLink: "Contact us",
    orderBtn: "Order a set",
    whyKicker: "Knowledge / 04",
    whyTitle: "Why forged",
    why1T: "Strength",
    why1D:
      "Forging densifies the alloy structure - the wheel takes loads a cast wheel would not survive.",
    why2T: "Low weight",
    why2D: "Less unsprung mass - better handling, acceleration and braking.",
    why3T: "Customization",
    why3D: "Size, ET, color and finish matched to your specific car.",
    realKicker: "Builds / 05",
    realTitle: "Our builds",
    conKicker: "Contact / 06",
    conTitle1: "Ask for",
    conTitle2: "a quote",
    conSub:
      "Tell us what car you drive and what you are looking for - we will reply with sizing, ET and a full quote. Delivery approx. 6 weeks.",
    points: [
      "Top quality - 6061 T6 aluminium",
      "Any wheel design",
      "Any specs",
      "Any paint finish",
      "Mono, Duo, Trio and Magnesium",
      "Delivery approx. 6 weeks",
    ],
    fName: "Name",
    fContact: "Phone or e-mail",
    fCar: "Car make and model",
    fCarPh: "e.g. BMW M4 G82",
    fMsg: "Message",
    fMsgPh: "Describe what you are looking for...",
    send: "Send inquiry",
    sending: "Sending...",
    sentT: "Sent!",
    sentM: "Thanks for your message - we will get back with a quote.",
    sendErr: "Sending failed. Please use WhatsApp or e-mail.",
    asking: "Asking about:",
    chWhats: "WhatsApp",
    chPhone: "Phone",
    chMail: "E-mail",
    chLoc: "Location",
    chLocVal: "Perzów 109B, 63-642 Perzów, Poland",
    navStore: "In stock",
    footAbout: "About us",
    footTerms: "Terms",
    footPrivacy: "Privacy policy",
    footFollow: "Follow us",
    footRights: "All rights reserved.",
    addonNames: {
      chrom: "Chrome",
      plywajacy_kapsel: "Floating center cap",
      aluminium_szczotkowane: "Brushed aluminium",
      polerowany_front: "Polished face",
      dwa_kolory: "Two-tone finish",
      duze_aluminiowe_kapsle: "Large aluminium caps",
      grawerunek: "Engraving",
      extreme_deep_concave: "Extreme Deep Concave",
      electroplating_and_polishing: "Electroplating & Polishing",
    } as Record<string, string>,
  },
  de: {
    nav: ["Preisliste", "Kontakt"],
    ctaCat: "Katalog ansehen",
    ctaPrice: "Preisliste",
    hero1: "SCHMIEDE",
    hero2: "RÄDER.",
    heroSub:
      "Wir entwerfen und fertigen Schmiederäder nach Maß - Forged Mono, Duo, Trio und Magnesium. Aluminium 6061 T6, beliebiges Design, beliebige Parameter, beliebige Lackierung.",
    statYears: "Jahre in der Branche",
    statAlloy: "T6-Legierung",
    statSizes: "Größen",
    catKicker: "Katalog / 01",
    catTitle: "Felgenkatalog",
    searchPh: "Suche, z.B. FM12...",
    fAll: "Alle",
    showMore: "Mehr anzeigen",
    from: "ab",
    indiv: "Preis auf Anfrage",
    noResults: "Keine Felgen für diesen Filter.",
    loadError: "Katalog konnte nicht geladen werden. Bitte Seite neu laden.",
    lbSize: "Durchmesser",
    lbCat: "Listenpreis",
    lbPre: "100% Vorkasse",
    lbAir: "Luftpost (100% Vorkasse)",
    lbAirVal: "Preis bei Bestellung",
    lbBuilds: "Projekte mit dieser Felge",
    lbAskBtn: "Zu dieser Felge anfragen",
    lbShare: "Teilen",
    toast: "Link kopiert!",
    lbNote: "Preis pro Satz (4 Felgen). Lieferzeit ca. 6 Wochen.",
    lbMore: "Mehr in der Galerie",
    indivTitle: "Preis auf Anfrage",
    indivDesc:
      "Dieses Modell kalkulieren wir individuell nach Größe, Parametern und Finish. Schreiben Sie uns - wir antworten mit einem Angebot.",
    techKicker: "Technologien / 02",
    techTitle: "Vier Serien",
    techMonoDesc: "einteilige Räder",
    techDuoDesc: "zweiteilige Räder",
    techTrioDesc: "dreiteilige Räder",
    techMagDesc: "Magnesiumräder",
    techMonoTxt:
      "Ein Block geschmiedetes Aluminium 6061 T6. Maximale Steifigkeit bei geringstem Gewicht.",
    techDuoTxt:
      "Verschraubtes Bett und Horn. Tiefe Profile, wechselbare Hörner, größte Finish-Auswahl.",
    techTrioTxt:
      "Dreiteilige Konstruktion mit wechselbaren Hörnern. Volle Individualisierung von Profil und Tiefe.",
    techMagTxt:
      "Magnesiumräder fertigen wir individuell pro Projekt und Fahrzeug. Absolutes Motorsport-Gewicht.",
    techFrom: "Satz ab",
    techMagBadge: "Auf Bestellung",
    techMagCta: "Angebot anfragen",
    techGoPrice: "Preisliste",
    cenKicker: "Preisliste / 03",
    cenTitle: "Aktuelle Preise",
    cenBadge: "Preis gilt für einen Satz von vier Felgen",
    colCat: "Listenpreis",
    colPre: "100% Vorkasse",
    colAir: "Luftpost",
    addonsTitle: "Extras",
    cenNote:
      "Vorkasse-Preise gelten bei 100% Vorauszahlung. Extras pro Satz. ET und Lochkreis passend zum Fahrzeug. Fertigung ca. 6 Wochen.",
    trioIndiv:
      "Preise für dreiteilige Räder kalkulieren wir individuell pro Projekt. Schreiben Sie uns Größe und Fahrzeugmodell - wir antworten mit einem Angebot.",
    magBar: "Forged Magnesium - Preis auf Anfrage",
    contactLink: "Kontakt aufnehmen",
    orderBtn: "Satz bestellen",
    whyKicker: "Wissen / 04",
    whyTitle: "Warum geschmiedet",
    why1T: "Festigkeit",
    why1D:
      "Schmieden verdichtet das Gefüge - das Rad erträgt Lasten, die ein Gussrad nicht überlebt.",
    why2T: "Geringes Gewicht",
    why2D:
      "Weniger ungefederte Masse - besseres Handling, Beschleunigen und Bremsen.",
    why3T: "Individualisierung",
    why3D: "Größe, ET, Farbe und Finish passend zu Ihrem Fahrzeug.",
    realKicker: "Projekte / 05",
    realTitle: "Realisierungen",
    conKicker: "Kontakt / 06",
    conTitle1: "Angebot",
    conTitle2: "anfragen",
    conSub:
      "Schreiben Sie uns Ihr Fahrzeug und was Sie suchen - wir antworten mit Größe, ET und einem Komplettangebot. Lieferzeit ca. 6 Wochen.",
    points: [
      "Höchste Qualität - Aluminium 6061 T6",
      "Beliebiges Felgendesign",
      "Beliebige Parameter",
      "Beliebige Lackierung",
      "Mono, Duo, Trio und Magnesium",
      "Lieferzeit ca. 6 Wochen",
    ],
    fName: "Name",
    fContact: "Telefon oder E-Mail",
    fCar: "Fahrzeugmarke und -modell",
    fCarPh: "z.B. BMW M4 G82",
    fMsg: "Nachricht",
    fMsgPh: "Beschreiben Sie, was Sie suchen...",
    send: "Anfrage senden",
    sending: "Wird gesendet...",
    sentT: "Gesendet!",
    sentM: "Danke für Ihre Nachricht - wir melden uns mit einem Angebot.",
    sendErr: "Senden fehlgeschlagen. Bitte WhatsApp oder E-Mail nutzen.",
    asking: "Anfrage zu:",
    chWhats: "WhatsApp",
    chPhone: "Telefon",
    chMail: "E-Mail",
    chLoc: "Standort",
    chLocVal: "Perzów 109B, 63-642 Perzów, Polen",
    navStore: "Auf Lager",
    footAbout: "Über uns",
    footTerms: "AGB",
    footPrivacy: "Datenschutz",
    footFollow: "Folgen Sie uns",
    footRights: "Alle Rechte vorbehalten.",
    addonNames: {
      chrom: "Chrom",
      plywajacy_kapsel: "Schwebender Nabendeckel",
      aluminium_szczotkowane: "Gebürstetes Aluminium",
      polerowany_front: "Polierte Front",
      dwa_kolory: "Zweifarbig",
      duze_aluminiowe_kapsle: "Große Aluminium-Deckel",
      grawerunek: "Gravur",
      extreme_deep_concave: "Extreme Deep Concave",
      electroplating_and_polishing: "Galvanik & Politur",
    } as Record<string, string>,
  },
};

export type Dict = (typeof dict)["pl"];

const LangContext = createContext<{
  lang: Lang;
  setLang: (l: Lang) => void;
  t: Dict;
}>({ lang: "pl", setLang: () => {}, t: dict.pl });

function detectLang(): Lang {
  try {
    const saved = localStorage.getItem("dawmac_lang");
    if (saved === "pl" || saved === "en" || saved === "de") return saved;
    const nav = (navigator.language || "").slice(0, 2).toLowerCase();
    if (nav === "de") return "de";
    if (nav !== "pl") return "en";
  } catch {
    /* SSR/prywatny tryb */
  }
  return "pl";
}

export function LangProvider({ children }: { children: ReactNode }) {
  const [lang, setLangState] = useState<Lang>(detectLang);

  const setLang = (l: Lang) => {
    setLangState(l);
    try {
      localStorage.setItem("dawmac_lang", l);
    } catch {
      /* ignore */
    }
  };

  useEffect(() => {
    document.documentElement.lang = lang;
  }, [lang]);

  return (
    <LangContext.Provider value={{ lang, setLang, t: dict[lang] }}>
      {children}
    </LangContext.Provider>
  );
}

export function useLang() {
  return useContext(LangContext);
}

/** nazwa dodatku z prices.json -> etykieta w bieżącym języku */
export function addonLabel(t: Dict, key: string): string {
  return (
    t.addonNames[key] ??
    key.replaceAll("_", " ").replace(/^./, (c) => c.toUpperCase())
  );
}
