import { useCallback, useEffect, useState } from "react";

export type Page = "home" | "catalog" | "d2";

interface Route {
  page: Page;
  wheelName: string | null;
}

const parsePath = (): Route => {
  const path = window.location.pathname;
  // deep-link felgi otwiera lightbox na stronie katalogu (osobno Forged i D2)
  const d2 = path.match(/^\/d2\/wheel\/([^/]+)/);
  if (d2) return { page: "d2", wheelName: decodeURIComponent(d2[1]) };
  const m = path.match(/^\/wheel\/([^/]+)/);
  if (m) return { page: "catalog", wheelName: decodeURIComponent(m[1]) };
  if (path === "/d2" || path === "/d2/") return { page: "d2", wheelName: null };
  if (path === "/katalog") return { page: "catalog", wheelName: null };
  return { page: "home", wheelName: null };
};

const inD2 = () => window.location.pathname.startsWith("/d2");

/**
 * Nawigacja SPA przez history API (bez react-routera).
 * `state.scrollTo` pozwala wskazać sekcję docelową (np. "#kontakt" przy
 * przejściu z katalogu na stronę główną) — konsumuje ją App.
 */
export const navigateTo = (path: string, state: Record<string, unknown> = {}) => {
  window.history.pushState(state, "", path);
  window.dispatchEvent(new PopStateEvent("popstate", { state }));
};

/**
 * Routing całej strony:
 * - "/" = landing Forged, "/katalog" = katalog Forged,
 * - "/d2" = tryb D2 (katalog D2 Forged),
 * - "/wheel/FM503" i "/d2/wheel/CS-07" = katalog + lightbox felgi (deep-link
 *   do udostępniania; format /wheel/* zgodny z bot.php, który serwuje OG botom),
 * - historia przeglądarki działa (wstecz zamyka lightbox / wraca na landing).
 */
export function useWheelRoute() {
  const [route, setRoute] = useState<Route>(parsePath);

  useEffect(() => {
    const onPop = () => setRoute(parsePath());
    window.addEventListener("popstate", onPop);
    return () => window.removeEventListener("popstate", onPop);
  }, []);

  const openWheel = useCallback((name: string) => {
    navigateTo(`/wheel/${encodeURIComponent(name)}`);
  }, []);

  const openD2Wheel = useCallback((name: string) => {
    navigateTo(`/d2/wheel/${encodeURIComponent(name)}`);
  }, []);

  /** podmienia URL bez dokładania wpisu historii (strzałki prev/next w modalu) */
  const switchWheel = useCallback((name: string) => {
    const prefix = inD2() ? "/d2/wheel/" : "/wheel/";
    window.history.replaceState({}, "", `${prefix}${encodeURIComponent(name)}`);
    window.dispatchEvent(new PopStateEvent("popstate"));
  }, []);

  const closeWheel = useCallback(() => {
    navigateTo(inD2() ? "/d2" : "/katalog");
  }, []);

  return { ...route, openWheel, openD2Wheel, switchWheel, closeWheel };
}
