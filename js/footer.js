/* =====================================================
   BARANGAY EQUIAID — Footer JavaScript
   Include on EVERY page. Handles:
     - Auto copyright year update
     - Smooth scroll-to-top button (if present)
   ===================================================== */

(function () {
  'use strict';

  /* ── Auto copyright year ────────────────────────── */
  const yearEl = document.getElementById('footer-year');
  if (yearEl) {
    yearEl.textContent = new Date().getFullYear();
  }

  /* ── Scroll-to-top button ───────────────────────── */
  const scrollTopBtn = document.getElementById('scrollTopBtn');

  if (scrollTopBtn) {
    window.addEventListener('scroll', () => {
      scrollTopBtn.classList.toggle('visible', window.scrollY > 400);
    }, { passive: true });

    scrollTopBtn.addEventListener('click', () => {
      window.scrollTo({ top: 0, behavior: 'smooth' });
    });
  }

})();