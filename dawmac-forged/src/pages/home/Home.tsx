import Hero from "./Hero";
import SeriesSection from "./SeriesSection";
import ProductInfoSection from "./ProductInfoSection";
import Gallery from "./Gallery";
// import PartnerLink from "./components/PartnerLink";
import IndividualProject from "./IndividualProject";

export default function Home() {
  return (
    <>
      <main>
        <Hero />
        <SeriesSection />
        <ProductInfoSection />
        <Gallery />
        {/* <PartnerLink /> */}
        <IndividualProject />
      </main>
    </>
  );
}
