/**
 * Automatyczna numeracja felg forged.
 *
 * Monoblock to seria 1 (nazwy FM…), dwuczęściowe to seria 2 (nazwy FD…).
 * Bierzemy najwyższy numer z nazw danej serii i proponujemy kolejny,
 * np. FM551 -> FM552, FD26 -> FD27. Nazwy w stylu "FM 423 BLACK" też
 * się liczą (spacja i dopisek po numerze są dozwolone), a "FMG32" nie,
 * bo to inny prefiks.
 */

export const SERIA_PREFIKS: Record<string, string> = {
  "1": "FM",
  "2": "FD",
};

export function nastepnaNazwa(
  seriesId: string,
  wheels: { name: string; series_id: number | string }[]
): string {
  const prefiks = SERIA_PREFIKS[seriesId];
  if (!prefiks) return "";

  const wzorzec = new RegExp(`^${prefiks}\\s?(\\d+)`, "i");
  let max = 0;

  for (const w of wheels) {
    if (String(w.series_id) !== seriesId) continue;
    const m = wzorzec.exec(w.name.trim());
    if (!m) continue;
    const n = parseInt(m[1], 10);
    if (n > max) max = n;
  }

  return prefiks + (max + 1);
}
