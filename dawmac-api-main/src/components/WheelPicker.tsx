import { useState, useEffect, useRef } from "react";

/**
 * Wybór felgi ze słownika zamiast wpisywania marki i modelu z palca.
 *
 * Słownik pochodzi z katalogu sklepu (pa_producent + pa_model), więc wybranie
 * pozycji z listy gwarantuje, że wpis od razu pasuje do produktów. Przy każdej
 * podpowiedzi widać, ile produktów jej dotyczy i ile aut już mamy — czyli
 * jeszcze przed zapisem wiadomo, na ile kart trafi to zdjęcie.
 *
 * Świadomie NIE blokujemy wpisania czegoś spoza słownika: nowa felga bywa
 * fotografowana zanim trafi do sklepu. Taki wpis jest tylko oznaczany, żeby
 * dało się go później znaleźć na liście roboczej.
 */

export type WheelDictItem = {
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
  /** true = wybrane ze słownika, false = wpisane ręcznie */
  fromDict: boolean;
  products: number;
};

type Props = {
  value: WheelChoice;
  onChange: (choice: WheelChoice) => void;
};

export default function WheelPicker({ value, onChange }: Props) {
  const [query, setQuery] = useState("");
  const [items, setItems] = useState<WheelDictItem[]>([]);
  const [open, setOpen] = useState(false);
  const [busy, setBusy] = useState(false);
  const [highlight, setHighlight] = useState(0);
  const boxRef = useRef<HTMLDivElement>(null);

  // Odpytujemy dopiero gdy user przestanie pisać — inaczej przy "japan racing"
  // poleciałoby 12 zapytań zamiast jednego.
  useEffect(() => {
    const szukane = query.trim();

    if (szukane.length < 2) {
      setItems([]);
      return;
    }

    let anulowane = false;
    setBusy(true);

    const timer = setTimeout(async () => {
      try {
        const res = await fetch(
          `${import.meta.env.VITE_DOMAIN}api/gallery/get_wheel_dict.php?q=${encodeURIComponent(
            szukane
          )}&limit=12`
        );
        if (!res.ok) throw new Error(`HTTP ${res.status}`);
        const data = await res.json();
        if (!anulowane) {
          setItems(data.items ?? []);
          setHighlight(0);
        }
      } catch (err) {
        console.error("Nie udało się pobrać słownika felg:", err);
        if (!anulowane) setItems([]);
      } finally {
        if (!anulowane) setBusy(false);
      }
    }, 250);

    return () => {
      anulowane = true;
      clearTimeout(timer);
    };
  }, [query]);

  // Klik poza komponentem zamyka listę.
  useEffect(() => {
    function poza(e: MouseEvent) {
      if (boxRef.current && !boxRef.current.contains(e.target as Node)) {
        setOpen(false);
      }
    }
    document.addEventListener("mousedown", poza);
    return () => document.removeEventListener("mousedown", poza);
  }, []);

  function wybierz(item: WheelDictItem) {
    onChange({
      brand: item.brand,
      model: item.model,
      fromDict: true,
      products: item.products,
    });
    setQuery("");
    setItems([]);
    setOpen(false);
  }

  function klawisz(e: React.KeyboardEvent<HTMLInputElement>) {
    if (!open || items.length === 0) return;

    if (e.key === "ArrowDown") {
      e.preventDefault();
      setHighlight((h) => (h + 1) % items.length);
    } else if (e.key === "ArrowUp") {
      e.preventDefault();
      setHighlight((h) => (h - 1 + items.length) % items.length);
    } else if (e.key === "Enter") {
      e.preventDefault();
      wybierz(items[highlight]);
    } else if (e.key === "Escape") {
      setOpen(false);
    }
  }

  const wybrana = value.brand !== "" || value.model !== "";

  return (
    <div className="wheel-picker" ref={boxRef}>
      <label htmlFor="wheel-picker-input">Felga</label>

      {wybrana ? (
        <div className={`wheel-picker-chosen ${value.fromDict ? "ok" : "manual"}`}>
          <strong>
            {value.brand} {value.model}
          </strong>
          {value.fromDict ? (
            <span className="wheel-picker-hint">
              {value.products} produktów w sklepie — zdjęcia trafią na ich karty
            </span>
          ) : (
            <span className="wheel-picker-hint warn">
              Wpisane ręcznie — nie ma tego w sklepie. Wpis trafi na listę roboczą.
            </span>
          )}
          <button
            type="button"
            onClick={() =>
              onChange({ brand: "", model: "", fromDict: false, products: 0 })
            }
          >
            Zmień
          </button>
        </div>
      ) : (
        <>
          <input
            id="wheel-picker-input"
            type="text"
            autoComplete="off"
            placeholder="Wpisz np. jr21, forzza titan, cvr1…"
            value={query}
            onChange={(e) => {
              setQuery(e.target.value);
              setOpen(true);
            }}
            onFocus={() => setOpen(true)}
            onKeyDown={klawisz}
          />

          {open && query.trim().length >= 2 && (
            <ul className="wheel-picker-list">
              {busy && <li className="wheel-picker-info">Szukam…</li>}

              {!busy &&
                items.map((item, i) => (
                  <li
                    key={item.id}
                    className={i === highlight ? "active" : ""}
                    onMouseEnter={() => setHighlight(i)}
                    onMouseDown={(e) => {
                      e.preventDefault();
                      wybierz(item);
                    }}
                  >
                    <span className="wheel-picker-label">{item.label}</span>
                    <span className="wheel-picker-meta">
                      {item.products} produktów
                      {item.projects > 0 && ` · ${item.projects} aut`}
                      {!item.active && " · wycofana"}
                    </span>
                  </li>
                ))}

              {!busy && items.length === 0 && (
                <li className="wheel-picker-info">
                  Nie ma takiej felgi w sklepie.
                  <button
                    type="button"
                    onMouseDown={(e) => {
                      e.preventDefault();
                      const czesci = query.trim().split(/\s+/);
                      onChange({
                        brand: czesci[0] ?? "",
                        model: czesci.slice(1).join(" "),
                        fromDict: false,
                        products: 0,
                      });
                      setQuery("");
                      setOpen(false);
                    }}
                  >
                    Użyj „{query.trim()}” mimo to
                  </button>
                </li>
              )}
            </ul>
          )}
        </>
      )}
    </div>
  );
}
