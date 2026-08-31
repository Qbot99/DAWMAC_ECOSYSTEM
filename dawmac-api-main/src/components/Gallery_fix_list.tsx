import { useState, useEffect } from "react";
import WheelPicker, { type WheelChoice } from "./WheelPicker";

/**
 * Lista robocza — wpisy galerii, które nie trafiają w żaden produkt.
 *
 * Pracujemy na PARACH producent+model, nie na pojedynczych autach. Jedna
 * decyzja poprawia od razu wszystkie wpisy zapisane tak samo, więc lista
 * jest posortowana po tym, ile aut odblokuje pojedyncza poprawka.
 *
 * Podpowiedzi są tylko podpowiedziami. Ta z inną cyfrą w modelu dostaje
 * ostrzeżenie, bo Stuttgart ST4 i ST3 to inne felgi, nie literówka —
 * kliknięcie jej na ślepo zepsułoby dane.
 */

type Suggestion = {
  brand: string;
  model: string;
  brand_norm: string;
  model_norm: string;
  products: number;
  distance: number;
  digit_diff: boolean;
};

type Group = {
  brand: string;
  model: string;
  brand_norm: string;
  model_norm: string;
  cars: number;
  kind: "brand" | "model" | "empty";
  suggestions: Suggestion[];
};

const ETYKIETY: Record<Group["kind"], string> = {
  brand: "nie ma takiego producenta",
  model: "producent jest, model nie pasuje",
  empty: "puste pole",
};

export default function Gallery_fix_list() {
  const [groups, setGroups] = useState<Group[]>([]);
  const [total, setTotal] = useState({ groups: 0, cars: 0 });
  const [ile, setIle] = useState(25);
  const [laduje, setLaduje] = useState(true);
  const [otwarta, setOtwarta] = useState<string | null>(null);
  const [wybor, setWybor] = useState<WheelChoice>({
    brand: "", model: "", fromDict: false, products: 0,
  });
  const [zapisuje, setZapisuje] = useState(false);
  const [komunikat, setKomunikat] = useState<string | null>(null);

  async function pobierz(limit: number) {
    setLaduje(true);
    try {
      const res = await fetch(
        `${import.meta.env.VITE_DOMAIN}api/gallery/get_unmatched.php?limit=${limit}`
      );
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      const data = await res.json();
      setGroups(data.groups ?? []);
      setTotal({ groups: data.groups_total ?? 0, cars: data.cars_total ?? 0 });
    } catch (err) {
      console.error("Nie udało się pobrać listy roboczej:", err);
    } finally {
      setLaduje(false);
    }
  }

  useEffect(() => {
    pobierz(ile);
  }, [ile]);

  const klucz = (g: Group) => `${g.brand_norm}|${g.model_norm}`;

  async function zapisz(g: Group, toBrand: string, toModel: string) {
    setZapisuje(true);
    setKomunikat(null);

    const dane = new FormData();
    dane.append("from_brand", g.brand_norm);
    dane.append("from_model", g.model_norm);
    dane.append("to_brand", toBrand);
    dane.append("to_model", toModel);
    // Gdy nie ma takiego producenta w ogóle, alias marki naprawia od razu
    // wszystkie jej modele — jedna decyzja zamiast kilkunastu.
    dane.append("scope", g.kind === "brand" ? "brand" : "pair");

    try {
      const res = await fetch(
        `${import.meta.env.VITE_DOMAIN}api/gallery/save_alias.php`,
        { method: "POST", body: dane, credentials: "same-origin" }
      );
      const wynik = await res.json();

      if (!res.ok || wynik.error) {
        setKomunikat(wynik.error ?? "Nie udało się zapisać.");
        return;
      }

      setKomunikat(
        `Poprawione. ${g.cars} ${g.cars === 1 ? "auto trafiło" : "aut trafiło"} na karty produktów.`
      );
      setOtwarta(null);
      setWybor({ brand: "", model: "", fromDict: false, products: 0 });
      pobierz(ile);
    } catch (err) {
      console.error(err);
      setKomunikat("Nie udało się zapisać. Spróbuj ponownie.");
    } finally {
      setZapisuje(false);
    }
  }

  return (
    <div id="gallery-fix-list">
      <h2>Do poprawy</h2>

      <p className="fix-podsumowanie">
        {laduje
          ? "Wczytuję…"
          : `${total.groups} wpisów nie trafia w żaden produkt — łącznie ${total.cars} aut. Lista zaczyna się od tych, które odblokują najwięcej.`}
      </p>

      {komunikat && <p className="gallery-add-msg">{komunikat}</p>}

      <ul className="fix-lista">
        {groups.map((g) => {
          const k = klucz(g);
          const rozwinieta = otwarta === k;

          return (
            <li key={k} className={rozwinieta ? "fix-poz otwarta" : "fix-poz"}>
              <button
                type="button"
                className="fix-naglowek"
                onClick={() => {
                  setOtwarta(rozwinieta ? null : k);
                  setWybor({ brand: "", model: "", fromDict: false, products: 0 });
                }}
              >
                <span className="fix-licznik">{g.cars}</span>
                <span className="fix-nazwa">
                  {g.brand || "—"} / {g.model || "—"}
                </span>
                <span className={`fix-rodzaj fix-${g.kind}`}>{ETYKIETY[g.kind]}</span>
              </button>

              {rozwinieta && (
                <div className="fix-tresc">
                  {g.suggestions.length > 0 && (
                    <>
                      <span className="dm-label">Podpowiedzi</span>
                      <div className="fix-podpowiedzi">
                        {g.suggestions.map((s) => (
                          <button
                            key={`${s.brand_norm}|${s.model_norm}`}
                            type="button"
                            className={s.digit_diff ? "fix-sug ryzyko" : "fix-sug"}
                            disabled={zapisuje}
                            onClick={() => zapisz(g, s.brand_norm, s.model_norm)}
                          >
                            <strong>
                              {s.brand} {s.model}
                            </strong>
                            <span>
                              {s.products} prod.
                              {s.digit_diff
                                ? " · uwaga: inna cyfra, prawdopodobnie INNA felga"
                                : ""}
                            </span>
                          </button>
                        ))}
                      </div>
                    </>
                  )}

                  <span className="dm-label">Albo wskaż z listy</span>
                  <WheelPicker value={wybor} onChange={setWybor} />

                  <button
                    type="button"
                    disabled={!wybor.fromDict || zapisuje}
                    onClick={() => zapisz(g, wybor.brand, wybor.model)}
                  >
                    {zapisuje ? "Zapisuję…" : `Popraw ${g.cars} aut`}
                  </button>

                  {g.kind === "brand" && (
                    <span className="wheel-picker-hint">
                      Producenta „{g.brand}” nie ma w sklepie — poprawka obejmie
                      wszystkie jego modele naraz.
                    </span>
                  )}
                </div>
              )}
            </li>
          );
        })}
      </ul>

      {!laduje && groups.length < total.groups && (
        <button type="button" className="wheel-picker-link" onClick={() => setIle(ile + 25)}>
          Pokaż kolejne 25 (z {total.groups})
        </button>
      )}
    </div>
  );
}
