

'use strict';


(function initScrollShadow() {
  const navbar = document.getElementById('navbar');
  if (!navbar) return;

  function onScroll() {
    navbar.classList.toggle('scrolled', window.scrollY > 8);
  }

  window.addEventListener('scroll', onScroll, { passive: true });
  onScroll(); 
})();



(function initHamburger() {
  const hamburger  = document.getElementById('hamburger');
  const mobileMenu = document.getElementById('mobile-menu');
  if (!hamburger || !mobileMenu) return;

  function openMenu() {
    hamburger.classList.add('open');
    mobileMenu.classList.add('open');
    hamburger.setAttribute('aria-expanded', 'true');
  }

  function closeMenu() {
    hamburger.classList.remove('open');
    mobileMenu.classList.remove('open');
    hamburger.setAttribute('aria-expanded', 'false');
  }

  hamburger.addEventListener('click', () => {
    hamburger.classList.contains('open') ? closeMenu() : openMenu();
  });

  // Close on outside click
  document.addEventListener('click', (e) => {
    if (!hamburger.contains(e.target) && !mobileMenu.contains(e.target)) {
      closeMenu();
    }
  });

  // Close on Escape
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') closeMenu();
  });
})();


/* ─────────────────────────────────────────────────────────────
   3.  ACTIVE LINK HIGHLIGHT
───────────────────────────────────────────────────────────── */
(function highlightActiveLink() {
  const page  = window.location.pathname.split('/').pop() || 'index.php';
  const links = document.querySelectorAll('.nav-links a, .mobile-menu a');

  links.forEach(link => {
    const href = (link.getAttribute('href') || '').split('/').pop();
    if (href && href === page) {
      link.classList.add('active');
      link.setAttribute('aria-current', 'page');
    }

    if (href === "street_status.php" && page === "street_status.php") {
      link.classList.add('active');
      link.setAttribute('aria-current', 'page');
    }
  });
})();


/* ─────────────────────────────────────────────────────────────
   4.  PROFILE DROPDOWN
───────────────────────────────────────────────────────────── */
(function initProfileDropdown() {
  const btn      = document.getElementById('nav-avatar-btn');
  const dropdown = document.getElementById('nav-profile-dropdown');
  if (!btn || !dropdown) return;   // user is logged out — elements absent

  /* ── Open ────────────────────────────────────────────── */
  function openDropdown() {
    dropdown.hidden = false;
    // Next frame so CSS transition fires after display change
    requestAnimationFrame(() => dropdown.classList.add('is-open'));
    btn.setAttribute('aria-expanded', 'true');
    // Move focus to first menu item for keyboard users
    dropdown.querySelector('[role="menuitem"]')?.focus();
  }

  /* ── Close ───────────────────────────────────────────── */
  function closeDropdown(returnFocus) {
    dropdown.classList.remove('is-open');
    btn.setAttribute('aria-expanded', 'false');
    // Wait for the 0.18 s CSS transition before hiding
    dropdown.addEventListener('transitionend', () => {
      dropdown.hidden = true;
    }, { once: true });
    if (returnFocus) btn.focus();
  }

  /* ── Toggle ──────────────────────────────────────────── */
  btn.addEventListener('click', (e) => {
    e.stopPropagation();
    btn.getAttribute('aria-expanded') === 'true'
      ? closeDropdown(false)
      : openDropdown();
  });

  /* ── Close on outside click ──────────────────────────── */
  document.addEventListener('click', (e) => {
    if (
      btn.getAttribute('aria-expanded') === 'true' &&
      !btn.contains(e.target) &&
      !dropdown.contains(e.target)
    ) {
      closeDropdown(false);
    }
  });

  /* ── Keyboard navigation inside the dropdown ─────────── */
  dropdown.addEventListener('keydown', (e) => {
    const items   = [...dropdown.querySelectorAll('[role="menuitem"]')];
    const focused = document.activeElement;
    const idx     = items.indexOf(focused);

    switch (e.key) {
      case 'Escape':
        closeDropdown(true);
        break;
      case 'ArrowDown':
        e.preventDefault();
        items[(idx + 1) % items.length]?.focus();
        break;
      case 'ArrowUp':
        e.preventDefault();
        items[(idx - 1 + items.length) % items.length]?.focus();
        break;
      case 'Tab':
        // Close when tabbing out of the last (or first with Shift) item
        if (!e.shiftKey && idx === items.length - 1) closeDropdown(false);
        if (e.shiftKey  && idx === 0)                closeDropdown(false);
        break;
    }
  });

  /* ── Keyboard trigger on the button ─────────────────── */
  btn.addEventListener('keydown', (e) => {
    if (e.key === 'ArrowDown' || e.key === 'Enter' || e.key === ' ') {
      e.preventDefault();
      openDropdown();
    }
  });

  /* ── Close when focus leaves the whole component ─────── */
  document.addEventListener('focusin', (e) => {
    if (
      btn.getAttribute('aria-expanded') === 'true' &&
      !btn.contains(e.target) &&
      !dropdown.contains(e.target)
    ) {
      closeDropdown(false);
    }
  });
})();