import { useEffect } from "react";

/**
 * Scroll-reveal jak w prototypie: elementy z [data-reveal] wjeżdżają kaskadowo,
 * elementy z [data-bar] wypełniają się do zadanej szerokości (%).
 * Wywoływane raz na poziomie App; obserwuje też elementy dodane później.
 */
export function useReveal(active: boolean) {
  useEffect(() => {
    if (!active) return;

    const io = new IntersectionObserver(
      (entries) => {
        for (const en of entries) {
          if (!en.isIntersecting) continue;
          const el = en.target as HTMLElement;
          if (el.hasAttribute("data-bar")) {
            el.style.width = el.getAttribute("data-bar") + "%";
          } else {
            el.style.opacity = "1";
            el.style.transform = "translateY(0)";
          }
          io.unobserve(el);
        }
      },
      { threshold: 0.12 }
    );

    const setup = () => {
      document.querySelectorAll<HTMLElement>("[data-reveal]").forEach((el) => {
        if (el.dataset.revealInit) return;
        el.dataset.revealInit = "1";
        // elementy już widoczne przy starcie nie animują (unikamy migotania nad foldem)
        if (el.getBoundingClientRect().top < window.innerHeight * 0.9) return;
        const idx = parseInt(el.getAttribute("data-reveal") || "0", 10);
        el.style.opacity = "0";
        el.style.transform = "translateY(34px)";
        el.style.transition = `opacity .9s cubic-bezier(.16,1,.3,1) ${idx * 90}ms, transform .9s cubic-bezier(.16,1,.3,1) ${idx * 90}ms`;
        io.observe(el);
      });
      document.querySelectorAll<HTMLElement>("[data-bar]").forEach((el) => {
        if (el.dataset.barInit) return;
        el.dataset.barInit = "1";
        io.observe(el);
      });
    };

    setup();
    const mo = new MutationObserver(setup);
    mo.observe(document.body, { childList: true, subtree: true });

    return () => {
      io.disconnect();
      mo.disconnect();
    };
  }, [active]);
}
