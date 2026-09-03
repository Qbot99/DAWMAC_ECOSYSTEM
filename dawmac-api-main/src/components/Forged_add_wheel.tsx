import { useEffect, useRef, useState } from "react";
import { useLoading } from "./loading/LoadingContext";
import { nastepnaNazwa } from "./forgedWheelName";

type WheelRow = { id: number; name: string; series_id: number | string };

/** Wybrane zdjęcie z podglądem; kolejność na liście = kolejność w galerii. */
type Wybrane = { key: string; file: File; url: string };

/** Zdarzenie, po którym panel edycji odświeża swoją listę felg. */
export const FORGED_WHEELS_CHANGED = "forged-wheels-changed";

export default function Forged_add_wheel() {
  const [wheels, setWheels] = useState<WheelRow[]>([]);
  const [series, setSeries] = useState<string>("1");
  const [name, setName] = useState<string>("");
  const [nameTouched, setNameTouched] = useState(false);
  const [description, setDescription] = useState<string>("");
  const [zdjecia, setZdjecia] = useState<Wybrane[]>([]);
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

  // Podglądy to obiekty URL, trzeba je zwolnić, gdy znikają z listy.
  useEffect(() => {
    return () => zdjecia.forEach((z) => URL.revokeObjectURL(z.url));
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  function dodajPliki(files: FileList | null) {
    if (!files) return;
    const nowe: Wybrane[] = [];
    for (const file of Array.from(files)) {
      const key = `${file.name}-${file.size}-${file.lastModified}`;
      if (zdjecia.some((z) => z.key === key) || nowe.some((z) => z.key === key))
        continue;
      nowe.push({ key, file, url: URL.createObjectURL(file) });
    }
    setZdjecia([...zdjecia, ...nowe]);
    // Ten sam plik ma dać się wybrać ponownie po usunięciu z listy.
    if (fileInputRef.current) fileInputRef.current.value = "";
  }

  function przesun(index: number, oIle: number) {
    const cel = index + oIle;
    if (cel < 0 || cel >= zdjecia.length) return;
    const kopia = [...zdjecia];
    [kopia[index], kopia[cel]] = [kopia[cel], kopia[index]];
    setZdjecia(kopia);
  }

  function ustawGlowne(index: number) {
    if (index === 0) return;
    const kopia = [...zdjecia];
    const [wybrane] = kopia.splice(index, 1);
    setZdjecia([wybrane, ...kopia]);
  }

  function usunZdjecie(index: number) {
    URL.revokeObjectURL(zdjecia[index].url);
    setZdjecia(zdjecia.filter((_, i) => i !== index));
  }

  function wyczyscZdjecia() {
    zdjecia.forEach((z) => URL.revokeObjectURL(z.url));
    setZdjecia([]);
    if (fileInputRef.current) fileInputRef.current.value = "";
  }

  function zmienSerie(nowa: string) {
    setSeries(nowa);
    // Kliknięcie w serię ma zawsze podstawić numer z tej serii.
    setNameTouched(false);
  }

  async function dodaj() {
    const nazwa = name.trim();

    if (!nazwa) {
      setKomunikat("Podaj nazwę felgi.");
      return;
    }
    if (zdjecia.length === 0) {
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
    // Kolejność z listy: pierwsze zdjęcie zostaje głównym (is_primary).
    for (const z of zdjecia) {
      formData.append("wheel_image[]", z.file);
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
        wyczyscZdjecia();
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
          onChange={(e) => dodajPliki(e.target.files)}
        />

        {zdjecia.length > 0 && (
          <div className="forged-order">
            <p className="forged-order__hint">
              Pierwsze zdjęcie będzie głównym. Ustaw kolejność strzałkami albo
              przyciskiem „Główne”.
            </p>
            <ol className="forged-order__list">
              {zdjecia.map((z, i) => (
                <li
                  key={z.key}
                  className={
                    "forged-order__item" +
                    (i === 0 ? " forged-order__item--main" : "")
                  }
                >
                  <img src={z.url} alt="" />
                  <span className="forged-order__nr">
                    {i === 0 ? "Główne" : i + 1}
                  </span>
                  <span className="forged-order__name" title={z.file.name}>
                    {z.file.name}
                  </span>
                  <span className="forged-order__btns">
                    <button
                      type="button"
                      className="forged-order__arrow"
                      onClick={() => przesun(i, -1)}
                      disabled={i === 0}
                      title="W górę"
                    >
                      ▲
                    </button>
                    <button
                      type="button"
                      className="forged-order__arrow"
                      onClick={() => przesun(i, 1)}
                      disabled={i === zdjecia.length - 1}
                      title="W dół"
                    >
                      ▼
                    </button>
                    <button
                      type="button"
                      onClick={() => ustawGlowne(i)}
                      disabled={i === 0}
                    >
                      Główne
                    </button>
                    <button type="button" onClick={() => usunZdjecie(i)}>
                      Usuń
                    </button>
                  </span>
                </li>
              ))}
            </ol>
          </div>
        )}

        <button type="submit">Dodaj</button>

        {komunikat && <p className="gallery-add-msg">{komunikat}</p>}
      </form>
    </div>
  );
}
