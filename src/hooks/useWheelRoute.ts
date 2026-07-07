import { useCallback, useEffect, useState } from "react";

const parsePath = (): string | null => {
  const m = window.location.pathname.match(/^\/wheel\/([^/]+)/);
  return m ? decodeURIComponent(m[1]) : null;
};

/**
 * Lightbox felgi sterowany adresem URL:
 * - wejście na /wheel/FM503 otwiera lightbox tej felgi (deep-link do udostępniania,
 *   format zgodny z bot.php, który serwuje Open Graph botom),
 * - otwarcie/zamknięcie modala aktualizuje historię przeglądarki (wstecz działa).
 */
export function useWheelRoute() {
  const [wheelName, setWheelName] = useState<string | null>(parsePath);

  useEffect(() => {
    const onPop = () => setWheelName(parsePath());
    window.addEventListener("popstate", onPop);
    return () => window.removeEventListener("popstate", onPop);
  }, []);

  const openWheel = useCallback((name: string) => {
    window.history.pushState({}, "", `/wheel/${encodeURIComponent(name)}`);
    setWheelName(name);
  }, []);

  /** podmienia URL bez dokładania wpisu historii (strzałki prev/next w modalu) */
  const switchWheel = useCallback((name: string) => {
    window.history.replaceState({}, "", `/wheel/${encodeURIComponent(name)}`);
    setWheelName(name);
  }, []);

  const closeWheel = useCallback(() => {
    window.history.pushState({}, "", "/");
    setWheelName(null);
  }, []);

  return { wheelName, openWheel, switchWheel, closeWheel };
}
