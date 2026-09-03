import { useEffect, useRef, useState } from "react";
import { useLoading } from "./loading/LoadingContext";
import { nastepnaNazwa } from "./forgedWheelName";

type WheelRow = { id: number; name: string; series_id: number | string };

/** Zdarzenie, po którym panel edycji odświeża swoją listę felg. */
export const FORGED_WHEELS_CHANGED = "forged-wheels-changed";

export default function Forged_add_wheel() {
  const [wheels, setWheels] = useState<WheelRow[]>([]);
  const [series, setSeries] = useState<string>("1");
  const [name, setName] = useState<string>("");
  const [nameTouched, setNameTouched] = useState(false);
  const [description, setDescription] = useState<string>("");
  const [komunikat, setKomunikat] = useState<string | null>(null);
  const fileInputRef = useRef<HTMLInputElement | null>(null);
  const { setLoading } = useLoading();

  async function pobierzFelgi(): Promise<WheelRow[]> {
    try {
      const res = await fetch(
        `${import.meta.env.VITE_DOMAIN}api/forged/list_wheels.php`
      );
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      return await res.json();
    } catch (err) {
      console.error("Nie udało się pobrać listy felg:", err);
      return [];
    }
  }

  useEffect(() => {
    pobierzFelgi().then(setWheels);
  }, []);

  // Podstawiamy kolejny numer, dopóki użytkownik sam nie zmienił nazwy.
  useEffect(() => {
    if (nameTouched) return;
    setName(nastepnaNazwa(series, wheels));
  }, [series, wheels, nameTouched]);

  function zmienSerie(nowa: string) {
    setSeries(nowa);
    // Kliknięcie w serię ma zawsze podstawić numer z tej serii.
    setNameTouched(false);
  }

  async function dodaj() {
    const files = fileInputRef.current?.files;
    const nazwa = name.trim();

    if (!nazwa) {
      setKomunikat("Podaj nazwę felgi.");
      return;
    }
    if (!files || files.length === 0) {
      setKomunikat("Dodaj przynajmniej jedno zdjęcie.");
      return;
    }
    const duplikat = wheels.find(
      (w) => w.name.trim().toLowerCase() === nazwa.toLowerCase()
    );
    if (duplikat) {
      setKomunikat(`Felga ${duplikat.name} już istnieje (ID ${duplikat.id}).`);
      return;
    }

    setKomunikat(null);
    setLoading(true);

    const formData = new FormData();
    formData.append("wheel_name", nazwa);
    formData.append("series", series);
    formData.append("description", description.trim());
    for (let i = 0; i < files.length; i++) {
      formData.append("wheel_image[]", files[i]);
    }

    try {
      const res = await fetch(
        `${import.meta.env.VITE_DOMAIN}api/forged/add_wheel.php`,
        { method: "POST", body: formData }
      );
      const data = await res.json();

      if (!res.ok || data?.error || data?.errors?.length) {
        const bledy = data?.errors
          ?.map((e: { file: string; error: string }) => `${e.file}: ${e.error}`)
          .join(" | ");
        setKomunikat(
          data?.error ??
            (bledy ? "Zapisano z błędami: " + bledy : "Nie udało się zapisać.")
        );
      } else {
        setKomunikat(`Dodano felgę ${nazwa}.`);
        setDescription("");
        setNameTouched(false);
        if (fileInputRef.current) fileInputRef.current.value = "";
        window.dispatchEvent(new Event(FORGED_WHEELS_CHANGED));
      }
    } catch (err) {
      console.error("Nie udało się dodać felgi:", err);
      setKomunikat("Nie udało się zapisać. Spróbuj ponownie.");
    } finally {
      // Świeża lista = świeży numer dla kolejnej felgi.
      setWheels(await pobierzFelgi());
      setLoading(false);
    }
  }

  return (
    <div id="forged-add-wheel">
      <h2>Dodaj felgę</h2>
      <form
        id="forged-add-wheel-form"
        onSubmit={(e) => {
          e.preventDefault();
          dodaj();
        }}
      >
        <div className="forged-series">
          <label>
            <input
              type="radio"
              name="series"
              value="1"
              checked={series === "1"}
              onChange={() => zmienSerie("1")}
            />
            Monoblock
          </label>
          <label>
            <input
              type="radio"
              name="series"
              value="2"
              checked={series === "2"}
              onChange={() => zmienSerie("2")}
            />
            Dwuczęściowe
          </label>
        </div>

        <input
          type="text"
          name="wheel_name"
          placeholder="Nazwa felgi"
          value={name}
          onChange={(e) => {
            setName(e.target.value);
            setNameTouched(true);
          }}
        />

        <textarea
          name="description"
          placeholder="Opis felgi (opcjonalnie)"
          rows={3}
          value={description}
          onChange={(e) => setDescription(e.target.value)}
        />

        <input
          ref={fileInputRef}
          type="file"
          name="wheel_image[]"
          id="wheel_image"
          multiple
          accept="image/*"
        />

        <button type="submit">Dodaj</button>

        {komunikat && <p className="gallery-add-msg">{komunikat}</p>}
      </form>
    </div>
  );
}
