import { useState, useEffect } from "react";

/**
 * Wybór felgi: producent z listy, potem model zawężony do tego producenta.
 * Ten sam układ co marka i model samochodu wyżej, więc nie trzeba się uczyć
 * niczego nowego.
 *
 * Obie listy pochodzą z katalogu sklepu (pa_producent + pa_model), więc wybór
 * z listy gwarantuje, że wpis od razu pasuje do produktów. Przy modelu widać,
 * ile produktów go dotyczy i ile aut już mamy — czyli jeszcze przed zapisem
 * wiadomo, na ile kart trafią zdjęcia.
 *
 * Świadomie zostaje furtka na wpisanie ręczne: nowa felga bywa fotografowana,
 * zanim trafi do sklepu. Taki wpis jest oznaczany, żeby dało się go znaleźć
 * na liście roboczej.
 */

type DictBrand = {
  brand: string;
  brand_norm: string;
  models: number;
  products: number;
  projects: number;
};

type DictModel = {
  id: number;
  brand: string;
  model: string;
  brand_norm: string;
  model_norm: string;
  products: number;
  projects: number;
  active: boolean;
  label: string;
};

export type WheelChoice = {
  brand: string;
  model: string;
  /** true = wybrane z list, false = wpisane ręcznie */
  fromDict: boolean;
  products: number;
};

type Props = {
  value: WheelChoice;
  onChange: (choice: WheelChoice) => void;
};

export default function WheelPicker({ value, onChange }: Props) {
  const [brands, setBrands] = useState<DictBrand[]>([]);
  const [models, setModels] = useState<DictModel[]>([]);
  const [brandNorm, setBrandNorm] = useState("");
  const [ladujeModele, setLadujeModele] = useState(false);
  const [recznie, setRecznie] = useState(false);

  // Producenci — raz przy starcie.
  useEffect(() => {
    (async () => {
      try {
        const res = await fetch(
          `${import.meta.env.VITE_DOMAIN}api/gallery/get_wheel_dict.php`
        );
        if (!res.ok) throw new Error(`HTTP ${res.status}`);
        const data = await res.json();
        setBrands(data.brands ?? []);
      } catch (err) {
        console.error("Nie udało się pobrać producentów felg:", err);
      }
    })();
  }, []);

  // Modele — po każdej zmianie producenta.
  useEffect(() => {
    if (!brandNorm) {
      setModels([]);
      return;
    }

    let anulowane = false;
    setLadujeModele(true);

    (async () => {
      try {
        const res = await fetch(
          `${import.meta.env.VITE_DOMAIN}api/gallery/get_wheel_dict.php?brand=${encodeURIComponent(
            brandNorm
          )}`
        );
        if (!res.ok) throw new Error(`HTTP ${res.status}`);
        const data = await res.json();
        if (!anulowane) setModels(data.items ?? []);
      } catch (err) {
        console.error("Nie udało się pobrać modeli felg:", err);
        if (!anulowane) setModels([]);
      } finally {
        if (!anulowane) setLadujeModele(false);
      }
    })();

    return () => {
      anulowane = true;
    };
  }, [brandNorm]);

  function zmienProducenta(norm: string) {
    setBrandNorm(norm);
    const b = brands.find((x) => x.brand_norm === norm);
    // Producent bez modelu to jeszcze nie jest wybór — zerujemy, żeby nie dało
    // się zapisać połowicznej felgi.
    onChange({ brand: b?.brand ?? "", model: "", fromDict: false, products: 0 });
  }

  function zmienModel(id: string) {
    const m = models.find((x) => String(x.id) === id);
    if (!m) {
      onChange({ brand: value.brand, model: "", fromDict: false, products: 0 });
      return;
    }
    onChange({
      brand: m.brand,
      model: m.model,
      fromDict: true,
      products: m.products,
    });
  }

  const wybranyModel = models.find(
    (m) => m.model === value.model && value.fromDict
  );

  return (
    <div className="wheel-picker">
      <span className="wheel-picker-title">Felga</span>

      {!recznie ? (
        <>
          <select
            className="wheel-picker-select"
            value={brandNorm}
            onChange={(e) => zmienProducenta(e.target.value)}
          >
            <option value="">Wybierz producenta felgi</option>
            {brands.map((b) => (
              <option key={b.brand_norm} value={b.brand_norm}>
                {b.brand} ({b.models})
              </option>
            ))}
          </select>

          <select
            className="wheel-picker-select"
            value={wybranyModel ? String(wybranyModel.id) : ""}
            disabled={!brandNorm || ladujeModele}
            onChange={(e) => zmienModel(e.target.value)}
          >
            <option value="">
              {!brandNorm
                ? "Najpierw wybierz producenta"
                : ladujeModele
                ? "Wczytuję modele…"
                : `Wybierz model (${models.length})`}
            </option>
            {models.map((m) => (
              <option key={m.id} value={m.id}>
                {m.model} — {m.products} prod.
                {m.projects > 0 ? ` · ${m.projects} aut` : ""}
                {!m.active ? " · wycofana" : ""}
              </option>
            ))}
          </select>

          {value.fromDict && (
            <span className="wheel-picker-hint ok">
              {value.brand} {value.model} — zdjęcia trafią na {value.products}{" "}
              {value.products === 1 ? "kartę produktu" : "kart produktów"}
            </span>
          )}

          <button
            type="button"
            className="wheel-picker-link"
            onClick={() => {
              setRecznie(true);
              onChange({ brand: "", model: "", fromDict: false, products: 0 });
            }}
          >
            Nie ma tej felgi na liście — wpiszę ręcznie
          </button>
        </>
      ) : (
        <>
          <input
            type="text"
            className="wheel-picker-select"
            placeholder="Producent felgi"
            value={value.brand}
            onChange={(e) =>
              onChange({ ...value, brand: e.target.value, fromDict: false, products: 0 })
            }
          />
          <input
            type="text"
            className="wheel-picker-select"
            placeholder="Model felgi"
            value={value.model}
            onChange={(e) =>
              onChange({ ...value, model: e.target.value, fromDict: false, products: 0 })
            }
          />
          <span className="wheel-picker-hint warn">
            Wpis ręczny — nie ma tego w sklepie, więc zdjęcia nie trafią jeszcze
            na żadną kartę. Wpis czeka na liście roboczej.
          </span>
          <button
            type="button"
            className="wheel-picker-link"
            onClick={() => {
              setRecznie(false);
              setBrandNorm("");
              onChange({ brand: "", model: "", fromDict: false, products: 0 });
            }}
          >
            Wróć do listy
          </button>
        </>
      )}
    </div>
  );
}
