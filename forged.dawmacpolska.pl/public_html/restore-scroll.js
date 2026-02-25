(function () {
    /**
     * Scroll Restoration Patch
     * 
     * This script handles saving and restoring scroll position for a SPA.
     * It uses sessionStorage to store the scroll Y position for each URL path.
     */

    const STORAGE_KEY_PREFIX = 'scroll_pos_';

    // 1. Save scroll position
    // We throttle the scroll event to avoid performance issues
    let isScrolling;
    window.addEventListener('scroll', () => {
        window.clearTimeout(isScrolling);
        isScrolling = setTimeout(() => {
            // Save the current scroll position for the current path
            const key = STORAGE_KEY_PREFIX + window.location.pathname;
            const scrollY = window.scrollY;
            if (scrollY > 0) {
                sessionStorage.setItem(key, scrollY);
            }
        }, 100);
    });

    // 2. Restore scroll position
    function attemptRestore() {
        // We use pathname as the key. 
        // Note: hashing strategies might require checking hash too, but pathname is safest for general pages.
        const key = STORAGE_KEY_PREFIX + window.location.pathname;
        const savedPos = sessionStorage.getItem(key);

        if (savedPos) {
            const targetPos = parseInt(savedPos, 10);

            // Polling mechanism to wait for content to load
            let attempts = 0;
            const maxAttempts = 50; // 5 seconds approx
            const intervalTime = 100;

            const poll = setInterval(() => {
                // Check if the document is long enough to scroll to the target
                // We use document.documentElement.scrollHeight for better cross-browser compatibility
                const currentHeight = Math.max(
                    document.body.scrollHeight,
                    document.body.offsetHeight,
                    document.documentElement.clientHeight,
                    document.documentElement.scrollHeight,
                    document.documentElement.offsetHeight
                );

                if (currentHeight >= targetPos) {
                    window.scrollTo(0, targetPos);

                    // Double check if we made it
                    if (Math.abs(window.scrollY - targetPos) < 20) {
                        clearInterval(poll);
                    }
                }

                attempts++;
                if (attempts > maxAttempts) {
                    clearInterval(poll);
                }
            }, intervalTime);
        }
    }

    // Attempt to restore on history navigation
    window.addEventListener('popstate', () => {
        // Small delay to allow framework to update URL/DOM
        setTimeout(attemptRestore, 10);
    });

    // Attempt to restore on initial page load (e.g. refresh)
    window.addEventListener('load', () => {
        setTimeout(attemptRestore, 10);
    });

    // Optional: React Router often replaces history state. 
    // We can also observe URL changes if popstate isn't enough, 
    // but popstate usually covers the "Back" button case which is the user's main concern.

    // Log for debugging
    console.log('Scroll restoration script loaded.');

})();
