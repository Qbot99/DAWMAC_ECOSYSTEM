import { useState, useEffect } from "react";
import { useLoading } from "./loading/LoadingContext";
import WheelPicker, { type WheelChoice } from "./WheelPicker";

type CarBrand = { id: number; name: string };
type CarModel = { id: number; name: string; brand_id: number };

const PUSTA_FELGA: WheelChoice = {
  brand: "",
  model: "",
  fromDict: false,
  products: 0,
};

export default function Gallery_add_wheel() {
  const [carBrandList, setCarBrandList] = useState<CarBrand[]>([]);
  const [carModelList, setCarModelList] = useState<CarModel[]>([]);
  const [selectedCarBrand, setSelectedCarBrand] = useState<string>("");
  const [carModelId, setCarModelId] = useState<number>(0);

  const [wheel, setWheel] = useState<WheelChoice>(PUSTA_FELGA);
  const [params, setParams] = useState("");
  const [youtubeUrl, setYoutubeUrl] = useState("");
  const [auctionUrl, setAuctionUrl] = useState("");
  const [komunikat, setKomunikat] = useState<string | null>(null);

  const { setLoading } = useLoading();

  useEffect(() => {
    (async () => {
      try {
        const res = await fetch(
          `${import.meta.env.VITE_DOMAIN}api/gallery/get_car_brands.php`
        );
        if (!res.ok) throw new Error(`HTTP ${res.status}`);
        setCarBrandList(await res.json());
      } catch (error) {
        console.error("Nie udało się pobrać marek aut:", error);
      }
    })();
  }, []);

  useEffect(() => {
    if (!selectedCarBrand) {
      setCarModelList([]);
      return;
    }
    (async () => {
      try {
        const res = await fetch(
          `${import.meta.env.VITE_DOMAIN}api/gallery/get_car_models.php?car_brand_id=${selectedCarBrand}`
        );
        if (!res.ok) throw new Error(`HTTP ${res.status}`);
        setCarModelList(await res.json());
      } catch (error) {
        console.error("Nie udało się pobrać modeli aut:", error);
      }
    })();
  }, [selectedCarBrand]);

  function wyczysc() {
    setWheel(PUSTA_FELGA);
    setParams("");
    setYoutubeUrl("");
    setAuctionUrl("");
    setCarModelId(0);
    const input = document.getElementById("add-wheel-images") as HTMLInputElement | null;
    if (input) input.value = "";
  }

  async function dodaj() {
    const input = document.getElementById("add-wheel-images") as HTMLInputElement | null;
    const files = input?.files;

    // Walidacja przed wysyłką — inaczej powstałby pusty projekt bez zdjęć,
    // czyli dokładnie taki wpis, jakich mamy dziś 322 do posprzątania.
    if (!selectedCarBrand) {
      setKomunikat("Wybierz markę samochodu.");
      return;
    }
    if (wheel.brand === "" && wheel.model === "") {
      setKomunikat("Wybierz felgę.");
      return;
    }
    if (!files || files.length === 0) {
      setKomunikat("Dodaj przynajmniej jedno zdjęcie.");
      return;
    }

    setKomunikat(null);
    setLoading(true);

    const formData = new FormData();
    formData.append("car_brand_id", selectedCarBrand);
    formData.append("car_model_id", String(carModelId));
    formData.append("wheel_brand", wheel.brand);
    formData.append("wheel_model", wheel.model);
    formData.append("wheel_params", params);
    formData.append("youtube_url", youtubeUrl.trim());
    formData.append("auction_url", auctionUrl.trim());
    for (let i = 0; i < files.length; i++) {
      formData.append("images[]", files[i]);
    }

    try {
      const res = await fetch(
        `${import.meta.env.VITE_DOMAIN}api/gallery/add_wheel.php`,
        { method: "POST", body: formData }
      );
      const data = await res.json();

      if (!res.ok || data?.errors?.length) {
        setKomunikat(
          data?.errors?.length
            ? "Zapisano z błędami: " + data.errors.join(" | ")
            : "Nie udało się zapisać."
        );
      } else {
        setKomunikat(
          wheel.fromDict && wheel.products > 0
            ? `Dodano. Zdjęcia trafią na ${wheel.products} kart produktów.`
            : "Dodano. Felga spoza sklepu — wpis czeka na liście roboczej."
        );
        wyczysc();
      }
    } catch (err) {
      console.error("Nie udało się dodać:", err);
      setKomunikat("Nie udało się zapisać. Spróbuj ponownie.");
    } finally {
      setLoading(false);
    }
  }

  return (
    <div id="gallery-add-wheel">
      <h2>Dodaj auto do galerii</h2>

      <select
        name="car_brand"
        id="car_brand"
        value={selectedCarBrand}
        onChange={(e) => {
          setSelectedCarBrand(e.target.value);
          setCarModelId(0);
        }}
      >
        <option value="">Wybierz markę samochodu</option>
        {carBrandList.map((brand) => (
          <option key={brand.id} value={brand.id}>
            {brand.name}
          </option>
        ))}
      </select>

      <select
        name="car_model"
        id="car_model"
        value={carModelId || ""}
        onChange={(e) => setCarModelId(Number(e.target.value))}
      >
        <option value="">Wybierz model samochodu</option>
        {carModelList.map((model) => (
          <option key={model.id} value={model.id}>
            {model.name}
          </option>
        ))}
      </select>

      <WheelPicker value={wheel} onChange={setWheel} />

      <input
        type="text"
        placeholder="Parametry, np. 19&quot; 8.5J ET42 5x112"
        value={params}
        onChange={(e) => setParams(e.target.value)}
      />

      <input
        type="url"
        placeholder="Link do filmu — YouTube lub Facebook (opcjonalnie)"
        value={youtubeUrl}
        onChange={(e) => setYoutubeUrl(e.target.value)}
      />

      <input
        type="url"
        placeholder="Link do aukcji (opcjonalnie)"
        value={auctionUrl}
        onChange={(e) => setAuctionUrl(e.target.value)}
      />

      <input type="file" name="add-wheel-images[]" id="add-wheel-images" multiple accept="image/*" />

      <button type="button" onClick={dodaj}>
        Dodaj
      </button>

      {komunikat && <p className="gallery-add-msg">{komunikat}</p>}
    </div>
  );
}
