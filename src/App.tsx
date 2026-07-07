import Header from "./components/Header";
import Footer from "./components/Footer";
// import PartnerLink from "./components/PartnerLink";
import { Routes, Route } from "react-router-dom";
import Home from "./pages/home/Home";
import FactoryStock from "./pages/forged_factory_stock/forged_factory_stock";
import ListPage from "./pages/wheel/ListPage";
import WheelPage from "./pages/wheel/WheelPage";
import Pricing from "./pages/pricing/Pricing";

export default function App() {
  return (
    <>
      <Header />
      <main>
        <Routes>
          <Route path="/" element={<Home />} />
          <Route path="/forged_facotry_stock" element={<FactoryStock />} />
          <Route path="/wheel" element={<ListPage />} />
          <Route path="/wheel/:name" element={<WheelPage />} />
          <Route path="pricing" element={<Pricing />} />
        </Routes>
      </main>
      <Footer />
    </>
  );
}
