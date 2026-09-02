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
      "Projektujemy i produkujemy felgi kute na zamówienie - monoblock, 2-częściowe, 3-częściowe i magnezowe. Aluminium 6061 T6, dowolny wzór, dowolne parametry, dowolne malowanie.",
    seriesNames: {
      "1": "Monoblock",
      "2": "2-częściowe",
      "3": "3-częściowe",
      "4": "Forged Magnesium",
      "5": "Factory Stock",
    } as Record<string, string>,
    statYears: "lat w branży",
    statAlloy: "stop T6",
    statSizes: "rozmiary",
    catKicker: "Katalog / 01",
    catTitle: "Katalog felg",
    searchPh: "Szukaj, np. FM12...",
    gateChoose: "Wybierz ofertę",
    gateForgedSub: "Felgi kute na zamówienie — nasza produkcja. Dowolny wzór, parametry i malowanie.",
    gateD2Sub: "Katalog ponad 600 wzorów felg kutych D2 Forged w rozmiarach 18-22 cale.",
    gateEnter: "Wejdź",
    stripAll: "Zobacz wszystkie",
    gateForgedTag: "Produkcja własna",
    gateD2Tag: "Katalog 600+ wzorów",
    gateForgedSpecs: ["Dowolny wzór", "Dowolne parametry", "Dowolne malowanie"],
    gateD2Specs: ["600+ wzorów", "Rozmiary 18-22\""],
    d2Kicker: "D2 / Katalog",
    d2Title: "Felgi D2 Forged",
    d2Sub:
      "Ponad 600 wzorów felg kutych D2 Forged w rozmiarach 18-22 cale — wszystkie w konstrukcji 2-częściowej. Wybierz model, sprawdź cenę kompletu i zapytaj o wycenę pod swoje auto.",
    d2SearchPh: "Szukaj, np. CS-07...",
    d2PriceNote: "cena za komplet 4 felg",
    d2LbDesc: "Felga kuta D2 Forged — produkcja na zamówienie pod parametry Twojego auta.",
    d2Note: "Cena za komplet 4 felg przy 100% przedpłacie. Parametry (ET, PCD, szerokość) dobieramy pod samochód.",
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
      "Monoblock, 2-częściowe, 3-częściowe i magnezowe",
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
    navGallery: "Galeria",
    navStore: "W magazynie",
    bnavStart: "Start",
    bnavCatalog: "Katalog",
    formTitle: "Napisz do nas",
    formTag: "// wycena",
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
      duze_aluminiowe_kapsle: "Duże pływające kapsle",
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
      "We design and manufacture custom forged wheels - monoblock, 2-piece, 3-piece and magnesium. 6061 T6 aluminium, any design, any specs, any finish.",
    seriesNames: {
      "1": "Monoblock",
      "2": "2-piece",
      "3": "3-piece",
      "4": "Forged Magnesium",
      "5": "Factory Stock",
    } as Record<string, string>,
    statYears: "years in the industry",
    statAlloy: "T6 alloy",
    statSizes: "sizes",
    catKicker: "Catalog / 01",
    catTitle: "Wheel catalog",
    searchPh: "Search, e.g. FM12...",
    gateChoose: "Choose your line",
    gateForgedSub: "Custom forged wheels — our own production. Any design, specs and finish.",
    gateD2Sub: "Catalog of over 600 D2 Forged wheel designs in 18-22 inch sizes.",
    gateEnter: "Enter",
    stripAll: "View all",
    gateForgedTag: "In-house production",
    gateD2Tag: "Catalog of 600+ designs",
    gateForgedSpecs: ["Any design", "Any specs", "Any finish"],
    gateD2Specs: ["600+ designs", "Sizes 18-22\""],
    d2Kicker: "D2 / Catalog",
    d2Title: "D2 Forged wheels",
    d2Sub:
      "Over 600 D2 Forged wheel designs in 18-22 inch sizes — all in 2-piece construction. Pick a model, check the set price and ask for a quote for your car.",
    d2SearchPh: "Search, e.g. CS-07...",
    d2PriceNote: "price per set of 4 wheels",
    d2LbDesc: "D2 Forged wheel — made to order for your car's specs.",
    d2Note: "Price per set of 4 wheels with 100% prepayment. Specs (ET, PCD, width) are matched to your car.",
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
      "Monoblock, 2-piece, 3-piece and magnesium",
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
    navGallery: "Gallery",
    navStore: "In stock",
    bnavStart: "Start",
    bnavCatalog: "Catalog",
    formTitle: "Write to us",
    formTag: "// quote",
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
      duze_aluminiowe_kapsle: "Large floating caps",
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
      "Wir entwerfen und fertigen Schmiederäder nach Maß - Monoblock, 2-teilig, 3-teilig und Magnesium. Aluminium 6061 T6, beliebiges Design, beliebige Parameter, beliebige Lackierung.",
    seriesNames: {
      "1": "Monoblock",
      "2": "2-teilig",
      "3": "3-teilig",
      "4": "Forged Magnesium",
      "5": "Factory Stock",
    } as Record<string, string>,
    statYears: "Jahre in der Branche",
    statAlloy: "T6-Legierung",
    statSizes: "Größen",
    catKicker: "Katalog / 01",
    catTitle: "Felgenkatalog",
    searchPh: "Suche, z.B. FM12...",
    gateChoose: "Wählen Sie Ihre Linie",
    gateForgedSub: "Schmiedefelgen nach Maß — eigene Produktion. Beliebiges Design, Parameter und Lackierung.",
    gateD2Sub: "Katalog mit über 600 D2 Forged Felgendesigns in 18-22 Zoll.",
    gateEnter: "Eintreten",
    stripAll: "Alle ansehen",
    gateForgedTag: "Eigene Produktion",
    gateD2Tag: "Katalog mit 600+ Designs",
    gateForgedSpecs: ["Beliebiges Design", "Beliebige Parameter", "Beliebige Lackierung"],
    gateD2Specs: ["600+ Designs", "Größen 18-22\""],
    d2Kicker: "D2 / Katalog",
    d2Title: "D2 Forged Felgen",
    d2Sub:
      "Über 600 D2 Forged Felgendesigns in 18-22 Zoll — alle in 2-teiliger Bauweise. Modell wählen, Satzpreis prüfen und ein Angebot für Ihr Auto anfragen.",
    d2SearchPh: "Suche, z.B. CS-07...",
    d2PriceNote: "Preis pro Satz (4 Felgen)",
    d2LbDesc: "D2 Forged Schmiedefelge — Fertigung auf Bestellung nach Ihren Parametern.",
    d2Note: "Preis pro Satz (4 Felgen) bei 100% Vorauszahlung. Parameter (ET, PCD, Breite) passend zum Fahrzeug.",
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
      "Monoblock, 2-teilig, 3-teilig und Magnesium",
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
    navGallery: "Galerie",
    navStore: "Auf Lager",
    bnavStart: "Start",
    bnavCatalog: "Katalog",
    formTitle: "Schreiben Sie uns",
    formTag: "// Angebot",
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
      duze_aluminiowe_kapsle: "Große schwimmende Deckel",
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
  // decyzja: strona zawsze startuje po polsku; język przeglądarki ignorujemy,
  // pamiętamy tylko świadomy wybór użytkownika z przełącznika
  try {
    const saved = localStorage.getItem("dawmac_lang");
    if (saved === "pl" || saved === "en" || saved === "de") return saved;
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

/** nazwa serii (series_id z bazy) w bieżącym języku */
export function seriesName(t: Dict, seriesId: string): string {
  return t.seriesNames[seriesId] ?? "";
}

/** nazwa dodatku z prices.json -> etykieta w bieżącym języku */
export function addonLabel(t: Dict, key: string): string {
  return (
    t.addonNames[key] ??
    key.replaceAll("_", " ").replace(/^./, (c) => c.toUpperCase())
  );
}
