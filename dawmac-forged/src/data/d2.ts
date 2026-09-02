import raw from "./d2-models.json";

export interface D2Model {
  name: string;
  series: string;
  sizes: number[];
  img: string;
  extra: string[];
}

export const D2_MODELS = raw as D2Model[];

/** serie w kolejności liczebności (jak w ofercie D2) */
export const D2_SERIES = [...new Set(D2_MODELS.map((m) => m.series))];

/** cennik D2 Forged — komplet 4 felg, przedpłata 100%, per średnica */
export const D2_PRICES: Record<number, number> = {
  18: 21250,
  19: 23053,
  20: 24090,
  21: 26139,
  22: 27893,
};

export const d2PriceFor = (size: number): number | null =>
  D2_PRICES[size] ?? null;

/** najniższa cena kompletu dla dostępnych średnic modelu */
export const d2PriceFrom = (sizes: number[]): number | null => {
  const prices = sizes
    .map((s) => D2_PRICES[s])
    .filter((v): v is number => typeof v === "number");
  return prices.length ? Math.min(...prices) : null;
};

export const d2SizeRange = (sizes: number[]): string =>
  sizes.length === 1
    ? `${sizes[0]}"`
    : `${Math.min(...sizes)}-${Math.max(...sizes)}"`;

const byName = new Map(D2_MODELS.map((m) => [m.name.toLowerCase(), m]));
export const findD2Model = (name: string): D2Model | undefined =>
  byName.get(name.toLowerCase());
