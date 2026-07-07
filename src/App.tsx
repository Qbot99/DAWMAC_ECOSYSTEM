import { useState } from "react";
import Builds from "./components/Builds";
import Catalog from "./components/Catalog";
import ContactSection from "./components/ContactSection";
import CursorGlow from "./components/CursorGlow";
import FooterNew from "./components/FooterNew";
import Hero from "./components/Hero";
import Marquee from "./components/Marquee";
import Nav from "./components/Nav";
import Preloader from "./components/Preloader";
import PricingSection from "./components/PricingSection";
import TechPanels from "./components/TechPanels";
import WhatsAppFab from "./components/WhatsAppFab";
import WheelLightbox from "./components/WheelLightbox";
import WhyForged from "./components/WhyForged";
import { useForgedData } from "./data/useForgedData";
import { useReveal } from "./hooks/useReveal";
import { useWheelRoute } from "./hooks/useWheelRoute";

export default function App() {
  const { wheels, findWheel, loading } = useForgedData();
  const { wheelName, openWheel, switchWheel, closeWheel } = useWheelRoute();
  const [formTopic, setFormTopic] = useState<string | null>(null);
  useReveal(!loading);

  const selectedWheel = wheelName ? findWheel(wheelName) : undefined;

  return (
    <div className="page">
      <Preloader />
      <CursorGlow />
      <div className="hazard" />
      <Nav />
      <main>
        <Hero />
        <Marquee />
        <Catalog onOpenWheel={openWheel} />
        <div className="hazard--thin" />
        <TechPanels />
        <PricingSection />
        <WhyForged />
        <Builds />
        <ContactSection
          topic={formTopic}
          onClearTopic={() => setFormTopic(null)}
        />
      </main>
      <FooterNew />
      <WhatsAppFab />

      {selectedWheel && (
        <WheelLightbox
          wheel={selectedWheel}
          wheels={wheels}
          onSwitch={switchWheel}
          onClose={closeWheel}
          onAsk={(name) => {
            setFormTopic(name);
            closeWheel();
          }}
        />
      )}
    </div>
  );
}
