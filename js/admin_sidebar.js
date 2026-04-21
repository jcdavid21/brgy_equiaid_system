(function () {
  'use strict';

  var STORAGE_KEY = 'equiaid-admin-sidebar-collapsed';

  function syncSidebarState(sidebar, shell, toggle, isCollapsed) {
    sidebar.classList.toggle('collapsed', isCollapsed);

    if (shell) {
      shell.classList.toggle('sidebar-collapsed', isCollapsed);
    }

    if (toggle) {
      toggle.setAttribute('aria-expanded', String(!isCollapsed));
      toggle.setAttribute('aria-label', isCollapsed ? 'Expand sidebar' : 'Collapse sidebar');
      toggle.title = isCollapsed ? 'Expand sidebar' : 'Collapse sidebar';
    }

    window.dispatchEvent(new CustomEvent('equiaid:sidebar-toggle', {
      detail: { collapsed: isCollapsed }
    }));
  }

  function initSidebarToggle() {
    var sidebar = document.getElementById('adminSidebar');
    var toggle = document.getElementById('sidebarToggle');
    var shell = document.querySelector('.admin-shell');

    if (!sidebar || !toggle) {
      return;
    }

    var savedState = null;

    try {
      savedState = window.localStorage.getItem(STORAGE_KEY);
    } catch (error) {
      savedState = null;
    }

    var isCollapsed = savedState === 'true';
    syncSidebarState(sidebar, shell, toggle, isCollapsed);

    toggle.addEventListener('click', function () {
      isCollapsed = !sidebar.classList.contains('collapsed');
      syncSidebarState(sidebar, shell, toggle, isCollapsed);

      try {
        window.localStorage.setItem(STORAGE_KEY, String(isCollapsed));
      } catch (error) {
        // Ignore storage failures so the toggle still works.
      }
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initSidebarToggle);
  } else {
    initSidebarToggle();
  }
})();