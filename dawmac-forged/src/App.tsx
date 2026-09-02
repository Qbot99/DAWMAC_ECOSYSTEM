import { useEffect, useState } from "react";
import BottomNav from "./components/BottomNav";
import BrandGate from "./components/BrandGate";
import Builds from "./components/Builds";
import Catalog from "./components/Catalog";
import CatalogStrip from "./components/CatalogStrip";
import ContactSection from "./components/ContactSection";
import CursorGlow from "./components/CursorGlow";
import D2Catalog from "./components/D2Catalog";
import D2Lightbox from "./components/D2Lightbox";
import FooterNew from "./components/FooterNew";
import Hero from "./components/Hero";
import Marquee from "./components/Marquee";
import Nav from "./components/Nav";
import PricingSection from "./components/PricingSection";
import TechPanels from "./components/TechPanels";
import WhatsAppFab from "./components/WhatsAppFab";
import WheelLightbox from "./components/WheelLightbox";
import { findD2Model } from "./data/d2";
import { useForgedData } from "./data/useForgedData";
import { useReveal } from "./hooks/useReveal";
import { navigateTo, useWheelRoute } from "./hooks/useWheelRoute";

export default function App() {
  const { wheels, findWheel, loading } = useForgedData();
  const { page, wheelName, openWheel, openD2Wheel, switchWheel, closeWheel } =
    useWheelRoute();
  const [formTopic, setFormTopic] = useState<string | null>(null);

  // bramka wyboru oferty: tylko wejście na "/" i raz na sesję
  const [gateOpen, setGateOpen] = useState(
    () =>
      window.location.pathname === "/" &&
      !sessionStorage.getItem("dawmac-mode"),
  );
  const pickMode = (mode: "forged" | "d2") => {
    sessionStorage.setItem("dawmac-mode", mode);
    setGateOpen(false);
    if (mode === "d2") navigateTo("/d2");
  };

  useReveal(!loading && page === "home" && !gateOpen);

  const selectedWheel =
    page !== "d2" && wheelName ? findWheel(wheelName) : undefined;
  const selectedD2 = page === "d2" && wheelName ? findD2Model(wheelName) : undefined;

  // zmiana strony: scroll na górę, chyba że nawigacja wskazała sekcję (state.scrollTo)
  useEffect(() => {
    const target = (window.history.state as { scrollTo?: string } | null)?.scrollTo;
    if (target) {
      requestAnimationFrame(() =>
        document.querySelector(target)?.scrollIntoView({ behavior: "smooth" })
      );
    } else {
      window.scrollTo(0, 0);
    }
  }, [page]);

  if (gateOpen) {
    return (
      <div className="page">
        <CursorGlow />
        <div className="hazard" />
        <BrandGate onPick={pickMode} />
      </div>
    );
  }

  return (
    <div className="page">
      <CursorGlow />
      <div className="hazard" />
      <Nav page={page} />
      <main>
        {page === "home" ? (
          <>
            <Hero />
            <Marquee />
            <TechPanels />
            <CatalogStrip onOpenWheel={openWheel} />
            <PricingSection />
            <Builds />
            <ContactSection
              topic={formTopic}
              onClearTopic={() => setFormTopic(null)}
            />
          </>
        ) : page === "d2" ? (
          <D2Catalog onOpenWheel={openD2Wheel} />
        ) : (
          <Catalog onOpenWheel={openWheel} />
        )}
      </main>
      <FooterNew />
      <WhatsAppFab />
      <BottomNav page={page} />

      {selectedWheel && (
        <WheelLightbox
          wheel={selectedWheel}
          wheels={wheels}
          onSwitch={switchWheel}
          onClose={closeWheel}
          onAsk={(name) => {
            setFormTopic(name);
            navigateTo("/", { scrollTo: "#kontakt" });
          }}
        />
      )}

      {selectedD2 && (
        <D2Lightbox
          model={selectedD2}
          onSwitch={switchWheel}
          onClose={closeWheel}
          onAsk={(name) => {
            setFormTopic(`D2 ${name}`);
            navigateTo("/", { scrollTo: "#kontakt" });
          }}
        />
      )}
    </div>
  );
}
