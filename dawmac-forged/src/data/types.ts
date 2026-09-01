export interface Wheel {
  id: string;
  name: string;
  series_name: string;
  series_id: string;
  images: string[];
  youtube_url: string | null;
  /** stara nazwa sprzed ujednolicenia (po migracji bazy) */
  legacy_name?: string | null;
}

export interface SizePrice {
  rozmiar: string;
  cena_katalogowa?: number;
  przedplata_100: number;
}

export interface AirMailPrice {
  rozmiar: string;
  przedplata_100: number;
}

export interface SeriesPrices {
  rozmiary: SizePrice[];
  dodatki?: Record<string, number>;
  ceny_poczta_lotnicza?: AirMailPrice[];
}

export type PricesFile = Record<string, SeriesPrices>;

export interface GalleryProject {
  project_id: string;
  brand: string;
  model: string;
  image: string;
}
