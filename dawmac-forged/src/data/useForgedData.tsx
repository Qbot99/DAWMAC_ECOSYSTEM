/* eslint-disable react-refresh/only-export-components */
import {
  createContext,
  useContext,
  useEffect,
  useMemo,
  useState,
} from "react";
import type { ReactNode } from "react";
import { WHEELS_ENDPOINT } from "../config";
import type { PricesFile, SeriesPrices, Wheel } from "./types";

interface ForgedData {
  wheels: Wheel[];
  prices: PricesFile;
  loading: boolean;
  error: boolean;
  findWheel: (name: string) => Wheel | undefined;
}

const Ctx = createContext<ForgedData>({
  wheels: [],
  prices: {},
  loading: true,
  error: false,
  findWheel: () => undefined,
});

function normalizeWheel(raw: Wheel): Wheel {
  return {
    ...raw,
    images: (raw.images ?? []).filter(Boolean),
    youtube_url: raw.youtube_url || null,
  };
}

export function ForgedDataProvider({ children }: { children: ReactNode }) {
  const [wheels, setWheels] = useState<Wheel[]>([]);
  const [prices, setPrices] = useState<PricesFile>({});
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(false);

  useEffect(() => {
    let cancelled = false;
    async function load() {
      try {
        const [wRes, pRes] = await Promise.all([
          fetch(WHEELS_ENDPOINT),
          fetch(window.location.origin + "/prices.json"),
        ]);
        if (!wRes.ok || !pRes.ok) throw new Error("fetch failed");
        const w: Wheel[] = await wRes.json();
        const p: PricesFile = await pRes.json();
        if (!cancelled) {
          setWheels(w.map(normalizeWheel));
          setPrices(p);
        }
      } catch (err) {
        console.error("Błąd pobierania danych:", err);
        if (!cancelled) setError(true);
      } finally {
        if (!cancelled) setLoading(false);
      }
    }
    load();
    return () => {
      cancelled = true;
    };
  }, []);

  const value = useMemo<ForgedData>(() => {
    const byName = new Map<string, Wheel>();
    for (const w of wheels) {
      byName.set(w.name.toLowerCase(), w);
      if (w.legacy_name) byName.set(w.legacy_name.toLowerCase(), w);
    }
    return {
      wheels,
      prices,
      loading,
      error,
      findWheel: (name: string) => byName.get(name.toLowerCase()),
    };
  }, [wheels, prices, loading, error]);

  return <Ctx.Provider value={value}>{children}</Ctx.Provider>;
}

export function useForgedData() {
  return useContext(Ctx);
}

export const fmtPrice = (n: number) =>
  n.toLocaleString("pl-PL").replace(/ /g, " ") + " zł";

/** najniższa cena kompletu w serii (przedpłata 100%) */
export function seriesPriceFrom(sp: SeriesPrices | undefined): number | null {
  if (!sp?.rozmiary?.length) return null;
  let min = Infinity;
  for (const r of sp.rozmiary) {
    const v = r.przedplata_100 ?? r.cena_katalogowa;
    if (typeof v === "number" && v < min) min = v;
  }
  return min === Infinity ? null : min;
}

/** zakres średnic serii, np. "15-24" */
export function seriesSizeRange(sp: SeriesPrices | undefined): string | null {
  if (!sp?.rozmiary?.length) return null;
  const sizes = sp.rozmiary.map((r) => parseInt(r.rozmiar, 10));
  return `${Math.min(...sizes)}-${Math.max(...sizes)}"`;
}
