(function () {
  'use strict';

  const API = '../backend/street_status.php';

  // ── Generic fetch wrapper ────────────────────────────
  async function api(action) {
    const res = await fetch(`${API}?action=${action}`);
    if (res.status === 401) {
      window.location.href = '../components/login.php?session_expired=1';
      throw new Error('Not authenticated');
    }
    if (!res.ok) throw new Error(`HTTP ${res.status} for action="${action}"`);
    const data = await res.json();
    if (!data.ok) throw new Error(data.error || `API error for action="${action}"`);
    return data;
  }

  // ── Skeleton helpers ─────────────────────────────────
  function shimmer(el)   { el.classList.add('sk-loading'); }
  function unshimmer(el) { el.classList.remove('sk-loading'); }

  // ── Number formatter ─────────────────────────────────
  const fmt = (n) => Number(n).toLocaleString();

  // ── XSS helper ───────────────────────────────────────
  function escHtml(str) {
    if (str == null) return '';
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  // ── Risk metadata ─────────────────────────────────────
  const RISK_LABEL = {
    RED: 'Critical', ORANGE: 'High Risk', YELLOW: 'Moderate', GREEN: 'Safe',
  };

  const RISK_ICON = {
    RED:    'fa-circle-xmark',
    ORANGE: 'fa-triangle-exclamation',
    YELLOW: 'fa-circle-minus',
    GREEN:  'fa-circle-check',
  };

  const WELFARE_ICON = {
    'Yes':      'fa-triangle-exclamation',
    'Moderate': 'fa-circle-exclamation',
    'No':       'fa-circle-check',
  };

  // ── Filter value → risk level mapping ─────────────────
  // Supports both color-based ("red") and label-based ("critical") filter values
  const FILTER_TO_RISK = {
    'red':      'red',
    'critical': 'red',
    'orange':   'orange',
    'high':     'orange',
    'yellow':   'yellow',
    'moderate': 'yellow',
    'green':    'green',
    'safe':     'green',
    'all':      'all',
  };

  // ════════════════════════════════════════════════════════
  // PAGINATION STATE  (hoisted so all functions can see them)
  // ════════════════════════════════════════════════════════
  let PAGE_SIZE     = 9;   // cards per page — change freely
  let _currentPage  = 1;
  let _visibleCards = [];  // filtered + sorted result before pagination

  // ════════════════════════════════════════════════════════
  // 1.  SUMMARY — KPI strip + typhoon alert banner
  // ════════════════════════════════════════════════════════
  async function loadSummary() {
    const strip = document.getElementById('ss-kpi-strip');
    if (strip) shimmer(strip);

    const data = await api('summary');
    const s    = data.summary;
    const t    = data.typhoon;

    // ── Typhoon alert banner ──────────────────────────────
    const banner = document.getElementById('ss-alert-banner');
    if (banner && t && t.status === 'Active') {
      document.getElementById('ss-alert-name').textContent = t.event_name;
      document.getElementById('ss-alert-local').textContent =
        t.local_name ? `(${t.local_name})` : '';
      document.getElementById('ss-alert-cat').textContent  = t.category   ?? '—';
      document.getElementById('ss-alert-kph').textContent  =
        t.wind_speed_kph ? fmt(t.wind_speed_kph) : '—';
      banner.hidden = false;
    }

    // ── KPI counts ────────────────────────────────────────
    const total = s.total || 0;

    ['red', 'orange', 'yellow', 'green'].forEach(level => {
      const count = s[level] ?? 0;
      const pct   = total > 0 ? Math.round(count / total * 100) : 0;

      const countEl = document.getElementById(`ss-kpi-count-${level}`);
      const pctEl   = document.getElementById(`ss-kpi-pct-${level}`);
      const barEl   = document.getElementById(`ss-kpi-bar-${level}`);

      if (countEl) countEl.textContent = count;
      if (pctEl)   pctEl.textContent   = `${pct}% of streets`;
      if (barEl)   barEl.style.width   = `${pct}%`;
    });

    const totalEl = document.getElementById('ss-kpi-count-total');
    if (totalEl) totalEl.textContent = total;

    // ── Last updated timestamp ────────────────────────────
    const tsEl = document.getElementById('ss-last-updated');
    if (tsEl && s.last_updated) {
      const d = new Date(s.last_updated.replace(' ', 'T'));
      tsEl.textContent = d.toLocaleString('en-PH', {
        month: 'short', day: 'numeric', year: 'numeric',
        hour: 'numeric', minute: '2-digit',
      });
      tsEl.classList.remove('sk-loading', 'sk-inline');
    }

    if (strip) unshimmer(strip);
  }

  // ════════════════════════════════════════════════════════
  // 2.  ZONES — populate the filter <select> dynamically
  // ════════════════════════════════════════════════════════
  async function loadZones() {
    const sel = document.getElementById('zoneFilter');
    if (!sel) return;

    const data  = await api('zones');
    const zones = data.zones || [];

    zones.forEach(z => {
      const opt       = document.createElement('option');
      opt.value       = z;
      opt.textContent = z;
      sel.appendChild(opt);
    });
  }

  // ════════════════════════════════════════════════════════
  // 3.  STREETS — build all cards from API data
  // ════════════════════════════════════════════════════════
  async function loadStreets() {
    const grid = document.getElementById('ssGrid');
    if (!grid) return;
    shimmer(grid);

    const data    = await api('streets');
    const streets = data.streets || [];

    if (!streets.length) {
      unshimmer(grid);
      showEmpty(true);
      return;
    }

    // Render all cards into a document fragment (hidden initially)
    const frag = document.createDocumentFragment();
    streets.forEach(s => {
      const tmp = document.createElement('div');
      tmp.innerHTML = buildCard(s);
      const card = tmp.firstElementChild;
      frag.appendChild(card);
    });
    grid.innerHTML = '';
    grid.appendChild(frag);

    // Store full card list for the filter engine
    _cards = Array.from(grid.querySelectorAll('.ss-card'));

    // Update total count badge on filter pills
    const allPill = document.querySelector('.ss-filter-pill[data-filter="all"] .ss-filter-count');
    if (allPill) allPill.textContent = streets.length;

    // Update per-risk count badges on pills
    const riskCounts = { red: 0, orange: 0, yellow: 0, green: 0 };
    streets.forEach(s => {
      const lvl = (s.risk_level || 'GREEN').toLowerCase();
      if (lvl in riskCounts) riskCounts[lvl]++;
    });
    Object.entries(riskCounts).forEach(([lvl, n]) => {
      // Match pill by data-filter that resolves to this risk level
      const pill = document.querySelector(
        `.ss-filter-pill[data-filter="${lvl}"] .ss-filter-count, ` +
        `.ss-filter-pill[data-filter="${Object.keys(FILTER_TO_RISK).find(k => FILTER_TO_RISK[k] === lvl && k !== lvl)}"] .ss-filter-count`
      );
      if (pill) pill.textContent = n;
    });

    // Hide skeleton loaders
    const skeleton = document.getElementById('ss-skeleton');
    if (skeleton) skeleton.classList.add('is-hidden');

    // Show results bar
    const resultsBar = document.getElementById('ssResultsBar');
    if (resultsBar) resultsBar.hidden = false;

    unshimmer(grid);
    applyFilters();
    initScrollAnimations();
  }

  // ── Card HTML builder ─────────────────────────────────
  function buildCard(s) {
    const level      = (s.risk_level || 'GREEN').toUpperCase();
    const lvlLower   = level.toLowerCase();
    const score      = parseFloat(s.vuln_score || 0);
    const welfare    = s.needs_welfare || 'No';
    const accessible = s.road_accessible !== false;
    const env        = s.env || {};
    const impact     = s.typhoon_impact;

    const dash = Math.round(Math.min(score, 100));

    // Typhoon impact block
    let impactHtml = '';
    if (impact) {
      const floodLine = (impact.flood_status && impact.flood_status !== 'None')
        ? `<span class="ss-impact-item">
             <i class="fa-solid fa-water" aria-hidden="true"></i>
             ${escHtml(impact.flood_status)}
             ${impact.flood_height_m
               ? `<em>(${parseFloat(impact.flood_height_m).toFixed(2)}m)</em>` : ''}
           </span>` : '';

      const damageLine = (impact.damage_status && impact.damage_status !== 'None')
        ? `<span class="ss-impact-item">
             <i class="fa-solid fa-house-crack" aria-hidden="true"></i>
             ${escHtml(impact.damage_status)}
           </span>` : '';

      const activePill = impact.typhoon_status === 'Active'
        ? `<span class="ss-active-pill">Active</span>` : '';

      const affectedLine = impact.affected_households
        ? `<div class="ss-impact-affected">
             <i class="fa-solid fa-users" aria-hidden="true"></i>
             ${fmt(impact.affected_households)} HH &bull;
             ${fmt(impact.affected_persons)} persons affected
           </div>` : '';

      impactHtml = `
        <div class="ss-impact-block">
          <div class="ss-impact-label">
            <i class="fa-solid fa-hurricane" aria-hidden="true"></i>
            ${escHtml(impact.typhoon_name || 'Recent Event')}
            ${activePill}
          </div>
          <div class="ss-impact-row">${floodLine}${damageLine}</div>
          ${affectedLine}
        </div>`;
    }

    // Welfare row
    const wIcon       = WELFARE_ICON[welfare] || 'fa-circle-check';
    const wText       = welfare === 'Yes'      ? 'Needs Welfare Assistance'
                      : welfare === 'Moderate' ? 'Moderate Welfare Need'
                      :                         'No Immediate Welfare Need';
    const welfareSuffix = welfare === 'Yes' ? 'yes'
                        : welfare === 'Moderate' ? 'moderate' : 'no';

    // Environmental chips
    const envChips = [
      env.drainage_type
        ? `<span class="ss-env-item" title="Drainage type">
             <i class="fa-solid fa-pipe-section" aria-hidden="true"></i>
             ${escHtml(env.drainage_type)}
           </span>` : '',
      env.road_surface
        ? `<span class="ss-env-item" title="Road surface">
             <i class="fa-solid fa-road" aria-hidden="true"></i>
             ${escHtml(env.road_surface)}
           </span>` : '',
      env.flood_frequency > 0
        ? `<span class="ss-env-item" title="Flood frequency (last 5 years)">
             <i class="fa-solid fa-droplet" aria-hidden="true"></i>
             Flooded ${env.flood_frequency}&times; / 5 yrs
           </span>` : '',
    ].filter(Boolean).join('');

    const envRow = envChips
      ? `<div class="ss-env-row">${envChips}</div>` : '';

    const reportLink = (level === 'RED' || level === 'ORANGE')
      ? `<a href="report.php?street=${s.street_id}" class="ss-report-link">
           <i class="fa-solid fa-circle-plus" aria-hidden="true"></i> Report
         </a>` : '';

    const roadClosed = !accessible
      ? `<span class="ss-road-closed" title="Road inaccessible">
           <i class="fa-solid fa-road-barrier" aria-hidden="true"></i>
         </span>` : '';

    return `
      <article
        class="ss-card ss-card--${lvlLower}"
        data-risk="${lvlLower}"
        data-zone="${escHtml(s.zone_name)}"
        data-name="${escHtml((s.street_name || '').toLowerCase())}"
        data-score="${score}"
        aria-label="${escHtml(s.street_name)} — ${RISK_LABEL[level] || level}"
      >
        <div class="ss-card-topbar">
          <span class="ss-risk-badge ss-risk-badge--${lvlLower}">
            <span class="ss-risk-dot" aria-hidden="true"></span>
            ${RISK_LABEL[level] || level}
          </span>
          <div class="ss-card-meta-right">
            ${roadClosed}
            <span class="ss-zone-pill">
              <i class="fa-solid fa-location-dot" aria-hidden="true"></i>
              ${escHtml(s.zone_name)}
            </span>
          </div>
        </div>

        <div class="ss-card-title-row">
          <h2 class="ss-card-name">${escHtml(s.street_name)}</h2>
          <div class="ss-score-ring" aria-label="Vulnerability score ${Math.round(score)}">
            <svg viewBox="0 0 36 36" class="ss-score-svg" aria-hidden="true">
              <circle cx="18" cy="18" r="15.9" fill="none" stroke-width="3" class="ss-score-track"/>
              <circle cx="18" cy="18" r="15.9" fill="none" stroke-width="3"
                class="ss-score-fill"
                stroke-dasharray="${dash},100"
                stroke-dashoffset="25"
                transform="rotate(-90 18 18)"/>
            </svg>
            <span class="ss-score-num">${Math.round(score)}</span>
          </div>
        </div>

        <div class="ss-card-chips">
          <span class="ss-chip">
            <i class="fa-solid fa-people-group" aria-hidden="true"></i>
            ${fmt(s.total_population)} residents
          </span>
          <span class="ss-chip">
            <i class="fa-solid fa-house" aria-hidden="true"></i>
            ${fmt(s.total_households)} households
          </span>
        </div>

        ${impactHtml}

        <div class="ss-welfare-row ss-welfare--${welfareSuffix}">
          <i class="fa-solid ${wIcon}" aria-hidden="true"></i>
          <span>${wText}</span>
        </div>

        ${envRow}

        <div class="ss-card-footer">
          <button class="ss-card-link ss-detail-btn" data-id="${s.street_id}">
            View Details <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
          </button>
          ${reportLink}
        </div>
      </article>`;
  }

  // ════════════════════════════════════════════════════════
  // FILTER + SORT + PAGINATION ENGINE
  // ════════════════════════════════════════════════════════
  let _cards        = [];
  let _activeFilter = 'all';
  // Note: _currentPage, _visibleCards, PAGE_SIZE declared above loadSummary

  function applyFilters() {
    const grid        = document.getElementById('ssGrid');
    const emptyState  = document.getElementById('ssEmpty');
    const countEl     = document.getElementById('ssVisibleCount');
    const searchInput = document.getElementById('streetSearch');
    const zoneSelect  = document.getElementById('zoneFilter');
    const sortSelect  = document.getElementById('streetSort');

    if (!grid || !_cards.length) return;

    const q    = (searchInput?.value || '').toLowerCase().trim();
    const zone = zoneSelect?.value || '';
    const sort = sortSelect?.value || 'risk';

    // Resolve active filter → canonical risk level ("red", "orange", etc.)
    const activeRisk = FILTER_TO_RISK[_activeFilter.toLowerCase()] ?? _activeFilter.toLowerCase();

    let visible = _cards.filter(c => {
      const cardRisk = (c.dataset.risk || '').toLowerCase();
      const matchRisk = activeRisk === 'all' || cardRisk === activeRisk;
      const matchName = !q || c.dataset.name.includes(q);
      const matchZone = !zone || c.dataset.zone === zone;
      return matchRisk && matchName && matchZone;
    });

    // Sort
    const riskOrder = { red: 0, orange: 1, yellow: 2, green: 3 };
    visible.sort((a, b) => {
      if (sort === 'score_desc') return parseFloat(b.dataset.score) - parseFloat(a.dataset.score);
      if (sort === 'score_asc')  return parseFloat(a.dataset.score) - parseFloat(b.dataset.score);
      if (sort === 'name')       return a.dataset.name.localeCompare(b.dataset.name);
      return (riskOrder[a.dataset.risk] ?? 9) - (riskOrder[b.dataset.risk] ?? 9);
    });

    _visibleCards = visible;
    _currentPage  = 1; // reset to page 1 on every filter change

    const hasResults = visible.length > 0;
    if (countEl)    countEl.textContent = visible.length;
    if (emptyState) emptyState.hidden   = hasResults;
    // Never hide the grid — renderPage handles card visibility
    if (grid) grid.hidden = false;

    renderPage();
  }

  // ════════════════════════════════════════════════════════
  // PAGINATION RENDERER
  // ════════════════════════════════════════════════════════

  // Hidden holding node — cards not on the current page live here
  const _cardBench = document.createElement('div');
  _cardBench.setAttribute('aria-hidden', 'true');
  _cardBench.style.cssText = 'display:none!important;position:absolute;visibility:hidden;';

  function renderPage() {
    const grid = document.getElementById('ssGrid');
    if (!grid) return;

    // Attach bench once
    if (!_cardBench.parentNode) document.body.appendChild(_cardBench);

    const total      = _visibleCards.length;
    const totalPages = Math.max(1, Math.ceil(total / PAGE_SIZE));
    _currentPage     = Math.min(_currentPage, totalPages);

    const start = (_currentPage - 1) * PAGE_SIZE;
    const end   = Math.min(start + PAGE_SIZE, total);

    // Step 1 — move ALL cards to the bench (clear the grid)
    _cards.forEach(c => _cardBench.appendChild(c));

    // Step 2 — move only this page's slice back into the grid
    _visibleCards.forEach((c, i) => {
      if (i >= start && i < end) {
        c.style.animationDelay = ((i - start) * 0.04) + 's';
        c.classList.remove('ss-card-anim');
        grid.appendChild(c);
        void c.offsetWidth; // force reflow so animation restarts
        c.classList.add('ss-card-anim');
      }
    });

    renderPagination(totalPages);
  }

  function renderPagination(totalPages) {
    const wrap = document.getElementById('ssPagination');
    if (!wrap) return;

    if (totalPages <= 1) {
      wrap.hidden = true;
      wrap.innerHTML = '';
      return;
    }

    wrap.hidden = false;
    const cp = _currentPage;

    // Build compact page list with ellipsis
    const pages = [];
    if (totalPages <= 5) {
      for (let i = 1; i <= totalPages; i++) pages.push(i);
    } else {
      pages.push(1);
      if (cp > 3) pages.push('…');
      const lo = Math.max(2, cp - 1);
      const hi = Math.min(totalPages - 1, cp + 1);
      for (let i = lo; i <= hi; i++) pages.push(i);
      if (cp < totalPages - 2) pages.push('…');
      pages.push(totalPages);
    }

    // Build HTML
    const pageItems = pages.map(p => {
      if (p === '…') return `<span class="ss-pg-ellipsis">…</span>`;
      const active = p === cp ? ' ss-pg-num--active' : '';
      return `<button class="ss-pg-num${active}" data-page="${p}" aria-label="Go to page ${p}" aria-current="${p === cp ? 'page' : 'false'}">${p}</button>`;
    }).join('');

    wrap.innerHTML = `
      <div class="ss-pg-inner">
        <button class="ss-pg-arrow" data-page="${cp - 1}" ${cp === 1 ? 'disabled' : ''} aria-label="Previous page">
          <i class="fa-solid fa-chevron-left"></i>
        </button>
        <div class="ss-pg-pages">${pageItems}</div>
        <button class="ss-pg-arrow" data-page="${cp + 1}" ${cp === totalPages ? 'disabled' : ''} aria-label="Next page">
          <i class="fa-solid fa-chevron-right"></i>
        </button>
      </div>
      <p class="ss-pg-info">Page ${cp} of ${totalPages}</p>`;

    // Wire clicks — single delegated listener on the wrapper
    wrap.querySelectorAll('[data-page]:not([disabled])').forEach(btn => {
      btn.addEventListener('click', () => {
        const pg = parseInt(btn.dataset.page, 10);
        if (!isNaN(pg) && pg >= 1 && pg <= totalPages && pg !== _currentPage) {
          _currentPage = pg;
          renderPage();
          document.getElementById('ssGrid')
            ?.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
      });
    });
  }

  // ════════════════════════════════════════════════════════
  // SCROLL ANIMATIONS (IntersectionObserver)
  // ════════════════════════════════════════════════════════
  function initScrollAnimations() {
    if (!('IntersectionObserver' in window)) return;

    const targets = document.querySelectorAll('.ss-card:not([hidden])');

    targets.forEach((el, i) => {
      el.style.opacity         = '0';
      el.style.transform       = 'translateY(20px)';
      el.style.transition      = 'opacity 0.46s ease, transform 0.46s ease';
      el.style.transitionDelay = `${(i % 4) * 0.08}s`;
    });

    const io = new IntersectionObserver((entries) => {
      entries.forEach(entry => {
        if (!entry.isIntersecting) return;
        entry.target.style.opacity   = '1';
        entry.target.style.transform = 'translateY(0)';
        io.unobserve(entry.target);
      });
    }, { threshold: 0.1, rootMargin: '0px 0px -28px 0px' });

    targets.forEach(el => io.observe(el));
  }

  // ════════════════════════════════════════════════════════
  // UI CONTROLS — wire up after DOM is ready
  // ════════════════════════════════════════════════════════
  function initControls() {
    // ── Filter pills ──────────────────────────────────────
    const filterPills = document.querySelectorAll('.ss-filter-pill');
    filterPills.forEach(pill => {
      pill.addEventListener('click', () => {
        filterPills.forEach(p => p.classList.remove('active'));
        pill.classList.add('active');
        _activeFilter = pill.dataset.filter || 'all';
        applyFilters();
      });
    });

    // ── Search, zone, sort ────────────────────────────────
    document.getElementById('streetSearch')
      ?.addEventListener('input', applyFilters);
    document.getElementById('zoneFilter')
      ?.addEventListener('change', applyFilters);
    document.getElementById('streetSort')
      ?.addEventListener('change', applyFilters);

    // ── Clear filters button ──────────────────────────────
    document.getElementById('ssClearFilters')
      ?.addEventListener('click', () => {
        const search = document.getElementById('streetSearch');
        const zone   = document.getElementById('zoneFilter');
        const sort   = document.getElementById('streetSort');
        if (search) search.value = '';
        if (zone)   zone.value   = '';
        if (sort)   sort.value   = 'risk';
        filterPills.forEach(p => p.classList.remove('active'));
        filterPills[0]?.classList.add('active');
        _activeFilter = 'all';
        applyFilters();
      });

    // ── View toggle (grid / list) ─────────────────────────
    const viewBtns = document.querySelectorAll('.ss-view-btn');
    viewBtns.forEach(btn => {
      btn.addEventListener('click', () => {
        viewBtns.forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        const grid = document.getElementById('ssGrid');
        if (grid) grid.dataset.view = btn.dataset.view;
      });
    });

    // ── Sticky toolbar shadow ─────────────────────────────
    const toolbar = document.getElementById('ssToolbar');
    if (toolbar) {
      const io = new IntersectionObserver(
        ([e]) => toolbar.classList.toggle('is-stuck', e.intersectionRatio < 1),
        { threshold: [1] }
      );
      io.observe(toolbar);
    }

    // ── Per-page selector (optional) ─────────────────────
    document.getElementById('ssPerPage')
      ?.addEventListener('change', (e) => {
        // If you add a per-page dropdown, wire it here
        // PAGE_SIZE = parseInt(e.target.value, 10) || 12;
        applyFilters();
      });
  }

  // ════════════════════════════════════════════════════════
  // BOOTSTRAP
  // ════════════════════════════════════════════════════════
  async function init() {
    initControls();
    initModal();
    initMap();

    await Promise.allSettled([
      loadSummary().catch(err => console.error('[Summary]', err)),
      loadZones().catch(err   => console.error('[Zones]', err)),
      loadStreets().catch(err => console.error('[Streets]', err)),
    ]);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

  // ════════════════════════════════════════════════════════
  // STREET DETAIL MODAL
  // ════════════════════════════════════════════════════════
  const RISK_COLOR = { RED:'#dc2626', ORANGE:'#d97706', YELLOW:'#b45309', GREEN:'#16a34a' };

  function initModal() {
    const overlay = document.getElementById('ssModal');
    if (!overlay) return;

    // Close on backdrop click
    overlay.addEventListener('click', e => { if (e.target === overlay) closeModal(); });

    // Close button
    document.getElementById('sdmClose')?.addEventListener('click', closeModal);

    // Keyboard: Escape
    document.addEventListener('keydown', e => {
      if (e.key === 'Escape' && !overlay.hidden) closeModal();
    });

    // Tab switcher — delegated
    document.getElementById('sdmTabs')?.addEventListener('click', e => {
      const btn = e.target.closest('.sdm-tab');
      if (!btn) return;
      document.querySelectorAll('.sdm-tab').forEach(t => {
        t.classList.remove('active');
        t.setAttribute('aria-selected', 'false');
      });
      btn.classList.add('active');
      btn.setAttribute('aria-selected', 'true');
      const tab = btn.dataset.tab;
      document.querySelectorAll('.sdm-panel-content').forEach(p => p.hidden = true);
      const panel = document.getElementById(`sdmTab-${tab}`);
      if (panel) panel.hidden = false;
    });

    // Open modal on "View Details" button click — delegated on grid container
    document.addEventListener('click', e => {
      const btn = e.target.closest('.ss-detail-btn');
      if (!btn) return;
      openModal(parseInt(btn.dataset.id, 10));
    });
  }

  function openModal(streetId) {
    const overlay = document.getElementById('ssModal');
    if (!overlay) return;

    // Reset tabs to Overview
    document.querySelectorAll('.sdm-tab').forEach((t, i) => {
      t.classList.toggle('active', i === 0);
      t.setAttribute('aria-selected', i === 0 ? 'true' : 'false');
    });
    document.querySelectorAll('.sdm-panel-content').forEach((p, i) => p.hidden = i !== 0);

    // Show overlay
    overlay.hidden = false;
    document.body.style.overflow = 'hidden';

    // Show loading, hide error
    document.getElementById('sdmLoading').hidden = false;
    document.getElementById('sdmError').hidden   = true;

    // Reset header to skeleton state
    document.getElementById('sdmTitle').textContent    = 'Loading…';
    document.getElementById('sdmSubtitle').textContent = '';
    document.getElementById('sdmRiskLabel').textContent= '—';
    document.getElementById('sdmZone').textContent     = '—';
    document.getElementById('sdmScoreNum').textContent = '—';

    fetch(`${API}?action=street_detail&id=${streetId}`)
      .then(r => {
        if (r.status === 401) {
          window.location.href = '../components/login.php?session_expired=1';
          throw new Error('Not authenticated');
        }
        return r.json();
      })
      .then(data => {
        if (!data.ok) throw new Error(data.error || 'API error');
        document.getElementById('sdmLoading').hidden = true;
        renderModal(data);
      })
      .catch(err => {
        document.getElementById('sdmLoading').hidden  = true;
        document.getElementById('sdmError').hidden    = false;
        document.getElementById('sdmErrorMsg').textContent = err.message || 'Could not load details.';
        console.error('[Modal]', err);
      });
  }

  function closeModal() {
    const overlay = document.getElementById('ssModal');
    if (overlay) overlay.hidden = true;
    document.body.style.overflow = '';
  }

  function renderModal(data) {
    const s   = data.street;
    const d   = data.demo    || {};
    const f   = data.features|| {};
    const lvl = (s.risk_level || 'GREEN').toUpperCase();
    const col = RISK_COLOR[lvl] || '#16a34a';

    // ── Header ──────────────────────────────────────────
    const badge = document.getElementById('sdmRiskBadge');
    badge.style.setProperty('--sdm-risk-color', col);
    document.getElementById('sdmRiskLabel').textContent = RISK_LABEL[lvl] || lvl;
    document.getElementById('sdmZone').textContent      = s.zone_name || '—';
    document.getElementById('sdmTitle').textContent     = s.street_name;
    document.getElementById('sdmSubtitle').textContent  = `${s.barangay}, ${s.city}`;

    // ── Score ring ────────────────────────────────────
    const score = Math.round(s.vuln_score || 0);
    document.getElementById('sdmScoreNum').textContent  = score;
    const fill = document.getElementById('sdmScoreFill');
    if (fill) {
      fill.setAttribute('stroke-dasharray', `${score},100`);
      fill.style.stroke = col;
    }

    // ── Last updated / footer ─────────────────────────
    const ts = document.getElementById('sdmLastUpdated');
    if (ts && s.last_predicted_at) {
      const d2 = new Date(s.last_predicted_at.replace(' ', 'T'));
      ts.textContent = 'Updated ' + d2.toLocaleString('en-PH', {
        month:'short', day:'numeric', year:'numeric', hour:'numeric', minute:'2-digit'
      });
    }
    const reportLink = document.getElementById('sdmReportLink');
    if (reportLink) reportLink.href = `report.php?street=${s.street_id}`;

    // ── Welfare banner (welfare row under title) ──────
    const welfareMap = {
      'Yes':      ['fa-triangle-exclamation','Needs Welfare Assistance','yes'],
      'Moderate': ['fa-circle-exclamation',  'Moderate Welfare Need',   'moderate'],
      'No':       ['fa-circle-check',        'No Immediate Welfare Need','no'],
    };
    const [wIco, wTxt, wSuffix] = welfareMap[s.needs_welfare] || welfareMap['No'];

    // ── Overview tab ──────────────────────────────────
    document.getElementById('sdmPop').textContent    = fmt(s.total_population);
    document.getElementById('sdmHH').textContent     = fmt(s.total_households);
    document.getElementById('sdmPoverty').textContent= d.poverty_rate_pct ? d.poverty_rate_pct + '%' : '—';
    document.getElementById('sdmIncome').textContent = d.avg_monthly_income
      ? '₱' + fmt(d.avg_monthly_income) : '—';
    document.getElementById('sdmPWD').textContent    = d.pwd_count    != null ? fmt(d.pwd_count)    : '—';
    document.getElementById('sdmSenior').textContent = d.senior_count != null ? fmt(d.senior_count) : '—';
    document.getElementById('sdmChild').textContent  = d.child_count  != null ? fmt(d.child_count)  : '—';
    document.getElementById('sdm4Ps').textContent    = d.fourps_households != null ? fmt(d.fourps_households) : '—';

    // Env grid
    const envItems = [
      ['fa-water',        'Drainage',    d.drainage_type],
      ['fa-road',         'Road',        d.road_surface],
      ['fa-mountain',     'Elevation',   d.elevation_m     != null ? d.elevation_m + ' m'  : null],
      ['fa-arrows-left-right','Waterway',d.dist_to_waterway_m != null ? d.dist_to_waterway_m + ' m away' : null],
      ['fa-droplet',      'Flood freq.', d.flood_frequency  ? d.flood_frequency + '× / 5 yrs' : '0× / 5 yrs'],
      ['fa-ruler-horizontal','Avg. depth',d.avg_flood_height_m ? d.avg_flood_height_m + ' m' : null],
    ].filter(([,, v]) => v != null);

    document.getElementById('sdmEnvGrid').innerHTML = envItems.length
      ? envItems.map(([ico, lbl, val]) =>
          `<div class="sdm-env-item">
             <i class="fa-solid ${ico}"></i>
             <div><span class="sdm-env-lbl">${lbl}</span><span class="sdm-env-val">${escHtml(String(val))}</span></div>
           </div>`
        ).join('')
      : '<p class="sdm-empty-note">No environmental data.</p>';

    // AI scores
    document.getElementById('sdmAI').innerHTML = f.image_flood_score != null
      ? `<div class="sdm-ai-bars">
           ${renderAIBar('Flood probability', f.image_flood_score,  '#dc2626')}
           ${renderAIBar('Damage probability',f.image_damage_score, '#d97706')}
           ${renderAIBar('Safe probability',  f.image_safe_score,   '#16a34a')}
         </div>
         <p class="sdm-ai-note">Composite risk score: <strong>${f.composite_risk_score ?? '—'}</strong></p>`
      : '<p class="sdm-empty-note">No AI assessment data available.</p>';

    // ── Impact tab ────────────────────────────────────
    document.getElementById('sdmImpactList').innerHTML = data.impacts?.length
      ? data.impacts.map(imp => `
          <div class="sdm-impact-card">
            <div class="sdm-impact-head">
              <strong>${escHtml(imp.event_name)}</strong>
              ${imp.typhoon_status === 'Active'
                ? '<span class="sdm-active-pill">Active</span>' : ''}
              <span class="sdm-impact-source">${escHtml(imp.report_source)}</span>
            </div>
            <div class="sdm-stat-row">
              <div class="sdm-stat"><span class="sdm-stat-val">${escHtml(imp.flood_status)}</span><span class="sdm-stat-lbl">Flood</span></div>
              <div class="sdm-stat"><span class="sdm-stat-val">${escHtml(imp.damage_status)}</span><span class="sdm-stat-lbl">Damage</span></div>
              <div class="sdm-stat"><span class="sdm-stat-val">${imp.flood_height_m ? imp.flood_height_m + ' m' : '—'}</span><span class="sdm-stat-lbl">Height</span></div>
              <div class="sdm-stat"><span class="sdm-stat-val">${imp.road_accessible ? 'Yes' : 'No'}</span><span class="sdm-stat-lbl">Road Open</span></div>
            </div>
            <div class="sdm-impact-affected">
              <i class="fa-solid fa-users"></i>
              ${fmt(imp.affected_households)} households &bull; ${fmt(imp.affected_persons)} persons affected
            </div>
            <div class="sdm-impact-date">${fmtDate(imp.date_recorded)}</div>
          </div>`).join('')
      : '<p class="sdm-empty-note">No typhoon impact records.</p>';

    // ── Welfare tab ───────────────────────────────────
    document.getElementById('sdmWelfareList').innerHTML =
      `<div class="ss-welfare-row ss-welfare--${wSuffix}" style="margin-bottom:16px">
         <i class="fa-solid ${wIco}"></i><span>${wTxt}</span>
       </div>` +
      (data.plans?.length
        ? data.plans.map(p => `
            <div class="sdm-plan-card">
              <div class="sdm-plan-head">
                <strong>${escHtml(p.assistance_type)}</strong>
                <span class="sdm-plan-status sdm-plan-status--${(p.status||'').toLowerCase().replace(' ','-')}">${escHtml(p.status)}</span>
              </div>
              ${p.description ? `<p class="sdm-plan-desc">${escHtml(p.description)}</p>` : ''}
              <div class="sdm-plan-meta">
                ${p.event_name ? `<span><i class="fa-solid fa-hurricane"></i> ${escHtml(p.event_name)}</span>` : ''}
                ${p.planned_date ? `<span><i class="fa-regular fa-calendar"></i> ${fmtDate(p.planned_date)}</span>` : ''}
              </div>
            </div>`).join('')
        : '<p class="sdm-empty-note">No welfare action plans on record.</p>');

    // ── Resources tab ─────────────────────────────────
    document.getElementById('sdmResourceList').innerHTML = data.resources?.length
      ? `<table class="sdm-table">
           <thead><tr><th>Resource</th><th>Category</th><th>Qty</th><th>Recipients</th><th>Date</th></tr></thead>
           <tbody>${data.resources.map(r =>
             `<tr>
               <td>${escHtml(r.resource_name)}</td>
               <td>${escHtml(r.category)}</td>
               <td>${fmt(r.qty_distributed)} ${escHtml(r.unit)}</td>
               <td>${fmt(r.recipient_count)}</td>
               <td>${fmtDate(r.distributed_at)}</td>
             </tr>`).join('')}
           </tbody>
         </table>`
      : '<p class="sdm-empty-note">No resource distributions on record.</p>';

    document.getElementById('sdmBudgetList').innerHTML = data.budgets?.length
      ? `<table class="sdm-table">
           <thead><tr><th>Period</th><th>Recommended</th><th>Approved</th><th>Spent</th><th>Priority</th></tr></thead>
           <tbody>${data.budgets.map(b =>
             `<tr>
               <td>${escHtml(b.fiscal_period || b.event_name || '—')}</td>
               <td>₱${fmt(b.recommended_budget)}</td>
               <td>${b.approved_budget != null ? '₱'+fmt(b.approved_budget) : '—'}</td>
               <td>₱${fmt(b.actual_spent)}</td>
               <td>${b.priority_score ?? '—'}</td>
             </tr>`).join('')}
           </tbody>
         </table>`
      : '<p class="sdm-empty-note">No budget allocations on record.</p>';

    // ── Reports tab ───────────────────────────────────
    document.getElementById('sdmReportList').innerHTML = data.reports?.length
      ? data.reports.map(r => `
          <div class="sdm-report-item">
            <div class="sdm-report-head">
              <span class="sdm-report-type">${escHtml(r.report_type)}</span>
              <span class="sdm-report-severity sdm-sev--${(r.severity||'').toLowerCase()}">${escHtml(r.severity)}</span>
              <span class="sdm-report-status">${escHtml(r.status)}</span>
            </div>
            ${r.description ? `<p class="sdm-report-desc">${escHtml(r.description)}</p>` : ''}
            <div class="sdm-report-date">${fmtDate(r.created_at)}</div>
          </div>`).join('')
      : '<p class="sdm-empty-note">No community reports filed.</p>';
  }

  function renderAIBar(label, score, color) {
    const pct = score != null ? Math.round(score * 100) : 0;
    return `<div class="sdm-ai-bar-wrap">
      <div class="sdm-ai-bar-label">${label}<span>${pct}%</span></div>
      <div class="sdm-ai-bar-track"><div class="sdm-ai-bar-fill" style="width:${pct}%;background:${color}"></div></div>
    </div>`;
  }

  function fmtDate(str) {
    if (!str) return '—';
    const d = new Date(str.replace(' ', 'T'));
    return isNaN(d) ? str : d.toLocaleDateString('en-PH', { month:'short', day:'numeric', year:'numeric' });
  }

  // ════════════════════════════════════════════════════════
  // MAP — Leaflet with street markers, evac centers,
  //       routing line (Lalamove-style) + Locate Nearest Evac
  // ════════════════════════════════════════════════════════
  const MAP_RISK_COLOR = {
    RED:    '#dc2626',
    ORANGE: '#d97706',
    YELLOW: '#ca8a04',
    GREEN:  '#16a34a',
  };
  const MAP_RISK_FILL = { RED: 0.92, ORANGE: 0.88, YELLOW: 0.82, GREEN: 0.78 };

  // Module-level map state
  let _map            = null;
  let _routingControl = null;
  let _userMarker     = null;
  let _userLat        = null;   // locked on first GPS fix — never changes
  let _userLng        = null;
  let _evacMarkers    = [];   // [{marker, data}]

  function initMap() {
    const mapEl = document.getElementById('ss-street-map');
    if (!mapEl) return;

    // Lazy-load: only init when element scrolls into view
    if (!('IntersectionObserver' in window)) {
      loadMap();
      return;
    }

    const io = new IntersectionObserver((entries) => {
      if (!entries[0].isIntersecting) return;
      io.disconnect();
      loadMap();
    }, { threshold: 0.1 });
    io.observe(mapEl);
  }

  async function loadMap() {
    const mapEl    = document.getElementById('ss-street-map');
    const spinner  = document.getElementById('ssMapSpinner');
    if (!mapEl || typeof L === 'undefined') return;

    let data;
    try {
      data = await api('map_data');
    } catch (err) {
      console.error('[Map]', err);
      if (spinner) spinner.innerHTML =
        '<i class="fa-solid fa-triangle-exclamation"></i><span>Map failed to load.</span>';
      return;
    }

    const streets = data.streets || [];
    const evacs   = data.evac    || [];
    if (!streets.length) return;

    // Center on average of all markers
    const avgLat = streets.reduce((s, r) => s + r.latitude,  0) / streets.length;
    const avgLng = streets.reduce((s, r) => s + r.longitude, 0) / streets.length;

    // Init Leaflet
    _map = L.map('ss-street-map', {
      center:          [avgLat, avgLng],
      zoom:            16,
      zoomControl:     true,
      scrollWheelZoom: false,
    });

    // Tile layer — CartoDB Positron (clean, light, like Lalamove)
    L.tileLayer('https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png', {
      attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> &copy; <a href="https://carto.com/">CARTO</a>',
      subdomains:  'abcd',
      maxZoom:     19,
    }).addTo(_map);

    const bounds = [];

    // ── Street markers ──────────────────────────────────
    streets.forEach(s => {
      const level = (s.risk_level || 'GREEN').toUpperCase();
      const color = MAP_RISK_COLOR[level] || '#16a34a';

      const marker = L.circleMarker([s.latitude, s.longitude], {
        radius:      11,
        fillColor:   color,
        color:       '#ffffff',
        weight:      2.5,
        opacity:     1,
        fillOpacity: MAP_RISK_FILL[level] || 0.8,
      }).addTo(_map);

      marker.bindPopup(buildMapStreetPopup(s), {
        maxWidth: 260,
        className: 'equiaid-popup',
      });

      // Pulse animation on RED markers
      if (level === 'RED') {
        marker.on('add', () => {
          const el = marker.getElement();
          if (el) el.style.animation = 'ss-pulse-marker 1.8s ease-in-out infinite';
        });
      }

      bounds.push([s.latitude, s.longitude]);
    });

    // ── Evac center markers ──────────────────────────────
    _evacMarkers = []; // always reset before populating
    evacs.forEach(e => {
      const icon = L.divIcon({
        className: '',
        html: `<div class="ss-evac-icon"><i class="fa-solid fa-house-medical"></i></div>`,
        iconSize:   [32, 32],
        iconAnchor: [16, 16],
        popupAnchor:[0, -18],
      });

      const marker = L.marker([e.latitude, e.longitude], { icon })
        .addTo(_map)
        .bindPopup(buildMapEvacPopup(e), {
          maxWidth: 250,
          className: 'equiaid-popup',
        });

      _evacMarkers.push({ marker, data: e });
      bounds.push([e.latitude, e.longitude]);
    });

    // Fit all markers
    if (bounds.length) {
      _map.fitBounds(bounds, { padding: [36, 36] });
    }

    // Hide spinner
    if (spinner) spinner.hidden = true;
    mapEl.classList.add('ss-map-ready');

    // Wire locate button
    document.getElementById('ssLocateEvac')?.addEventListener('click', locateNearestEvac);
    document.getElementById('ssClearRoute')?.addEventListener('click', clearRoute);
  }

  // ── Popup builders ─────────────────────────────────────
  function buildMapStreetPopup(s) {
    const level = (s.risk_level || 'GREEN').toUpperCase();
    const label = RISK_LABEL[level] || level;
    const impact = s.typhoon_impact;

    let impactHtml = '';
    if (impact) {
      impactHtml = `
        <div class="map-popup-divider"></div>
        <div class="map-popup-row"><strong>Typhoon</strong>${escHtml(impact.typhoon_name || '—')}</div>
        <div class="map-popup-row"><strong>Flood</strong>${escHtml(impact.flood_status)}</div>
        <div class="map-popup-row"><strong>Damage</strong>${escHtml(impact.damage_status)}</div>
        ${impact.flood_height_m
          ? `<div class="map-popup-row"><strong>Height</strong>${parseFloat(impact.flood_height_m).toFixed(2)} m</div>`
          : ''}
        <div class="map-popup-row"><strong>Affected HH</strong>${fmt(impact.affected_households)}</div>`;
    }

    return `
      <div class="map-popup">
        <div class="map-popup-name">${escHtml(s.street_name)}</div>
        <div class="map-popup-zone">${escHtml(s.zone_name)}</div>
        <span class="map-popup-badge map-popup-badge--${level.toLowerCase()}">${label}</span>
        <div class="map-popup-row"><strong>Vuln. Score</strong>${parseFloat(s.vuln_score).toFixed(1)}</div>
        <div class="map-popup-row"><strong>Population</strong>${fmt(s.total_population)}</div>
        <div class="map-popup-row"><strong>Households</strong>${fmt(s.total_households)}</div>
        <div class="map-popup-row"><strong>Welfare</strong>${escHtml(s.needs_welfare)}</div>
        ${impactHtml}
      </div>`;
  }

  function buildMapEvacPopup(e) {
    const pct = e.capacity > 0
      ? Math.round(e.current_occupancy / e.capacity * 100) : 0;
    const barColor = pct > 80 ? '#dc2626' : pct > 50 ? '#d97706' : '#16a34a';
    return `
      <div class="map-popup">
        <div class="map-popup-name">${escHtml(e.center_name)}</div>
        <div class="map-popup-zone">${escHtml(e.zone_name || '')}</div>
        <span class="map-popup-badge map-popup-badge--evac">
          <i class="fa-solid fa-person-walking-arrow-right"></i> Evacuation Center
        </span>
        <div class="map-popup-row"><strong>Capacity</strong>${fmt(e.capacity)} persons</div>
        <div class="map-popup-row"><strong>Occupancy</strong>${fmt(e.current_occupancy)} (${pct}%)</div>
        <div style="height:4px;background:#e5e7eb;border-radius:2px;margin:6px 0">
          <div style="height:100%;width:${pct}%;background:${barColor};border-radius:2px;transition:width .4s"></div>
        </div>
        ${e.address ? `<div class="map-popup-row"><strong>Address</strong>${escHtml(e.address)}</div>` : ''}
        ${e.contact_number ? `<div class="map-popup-row"><strong>Contact</strong>${escHtml(e.contact_number)}</div>` : ''}
      </div>`;
  }

  // ── Locate nearest evac center ─────────────────────────
  function locateNearestEvac() {
    if (!_map) { console.warn('[Locate] map not ready'); return; }
    if (!_evacMarkers.length) { console.warn('[Locate] no evac markers'); return; }

    // If we already have the user's position locked, just redraw — no GPS call
    if (_userLat !== null && _userLng !== null) {
      drawRouteTo(_userLat, _userLng);
      return;
    }

    const btn = document.getElementById('ssLocateEvac');
    if (btn) {
      btn.disabled = true;
      btn.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin"></i> Locating…';
    }

    if (!navigator.geolocation) {
      alert('Geolocation is not supported by your browser.');
      resetLocateBtn();
      return;
    }

    navigator.geolocation.getCurrentPosition(
      (pos) => {
        // Lock coords — never call GPS again after this
        _userLat = pos.coords.latitude;
        _userLng = pos.coords.longitude;
        try {
          drawRouteTo(_userLat, _userLng);
        } catch (err) {
          console.error('[Route]', err);
        }
        resetLocateBtn();
      },
      (err) => {
        console.warn('[Geolocation error]', err.code, err.message);
        // Use centre between evac centers as demo fallback
        if (_evacMarkers.length) {
          const allLats = _evacMarkers.map(m => parseFloat(m.data.latitude));
          const allLngs = _evacMarkers.map(m => parseFloat(m.data.longitude));
          _userLat = allLats.reduce((a,b) => a+b, 0) / allLats.length - 0.003;
          _userLng = allLngs.reduce((a,b) => a+b, 0) / allLngs.length - 0.003;
          drawRouteTo(_userLat, _userLng);
        }
        resetLocateBtn();
      },
      { enableHighAccuracy: true, timeout: 10000, maximumAge: 30000 }
    );
  }

  // Core drawing function — always draws, no async, no external API
  function drawRouteTo(userLat, userLng) {
    // 1. Clear previous route lines only (NOT the user marker)
    clearRouteLayer();

    // 2. Create user marker once — never move it after creation
    if (!_userMarker) {
      const userIcon = L.divIcon({
        className: '',
        html: `<div class="ss-user-icon"><i class="fa-solid fa-person"></i></div>`,
        iconSize:   [32, 32],
        iconAnchor: [16, 28],
        popupAnchor:[0, -30],
      });
      _userMarker = L.marker([userLat, userLng], { icon: userIcon, zIndexOffset: 1000 })
        .addTo(_map)
        .bindPopup(
          '<div class="map-popup"><div class="map-popup-name" style="color:#7c3aed"><i class="fa-solid fa-person" style="margin-right:6px"></i>Your Location</div></div>',
          { className: 'equiaid-popup' }
        );
    }

    const legend = document.getElementById('ssUserLegend');
    if (legend) legend.hidden = false;

    // 3. Find nearest evac
    let nearest = null, minDist = Infinity;
    _evacMarkers.forEach(({ data }) => {
      const eLat = parseFloat(data.latitude);
      const eLng = parseFloat(data.longitude);
      const d = haversine(userLat, userLng, eLat, eLng);
      console.log(`[Nearest] ${data.center_name}: ${(d/1000).toFixed(2)} km`);
      if (d < minDist) { minDist = d; nearest = data; }
    });
    console.log(`[Nearest] → Selected: ${nearest?.center_name} (${(minDist/1000).toFixed(2)} km)`);

    if (!nearest) return;

    const destLat = parseFloat(nearest.latitude);
    const destLng = parseFloat(nearest.longitude);
    const coords  = [[userLat, userLng], [destLat, destLng]];

    // 4. Draw 3-layer Lalamove-style route line
    // Layer A — wide outer glow
    _routeGlow = L.polyline(coords, {
      color:   '#1d4ed8',
      weight:  12,
      opacity: 0.15,
      lineCap: 'round',
      lineJoin:'round',
    }).addTo(_map);

    // Layer B — solid blue main line
    _routeLine = L.polyline(coords, {
      color:   '#1d4ed8',
      weight:  5,
      opacity: 1,
      lineCap: 'round',
      lineJoin:'round',
    }).addTo(_map);

    // Layer C — white dashed overlay (motion feel)
    _routeDash = L.polyline(coords, {
      color:      '#ffffff',
      weight:     2,
      opacity:    0.7,
      dashArray:  '10, 16',
      lineCap:    'round',
    }).addTo(_map);

    // 5. Fit both points in view
    _map.fitBounds(L.latLngBounds(coords), { padding: [60, 60], maxZoom: 17 });

    // 6. Update info panel
    const distKm   = (minDist / 1000).toFixed(2);
    const timeMins = Math.ceil(minDist / 83); // ~5 km/h walking

    const panel = document.getElementById('ssRoutePanel');
    if (panel) panel.hidden = false;
    const destEl = document.getElementById('ssRouteDest');
    if (destEl) destEl.textContent = nearest.center_name;
    const distEl = document.getElementById('ssRouteDist');
    if (distEl) distEl.innerHTML = `<i class="fa-solid fa-route"></i> ${distKm} km`;
    const timeEl = document.getElementById('ssRouteTime');
    if (timeEl) timeEl.innerHTML = `<i class="fa-regular fa-clock"></i> ~${timeMins} min on foot`;

    const clearBtn = document.getElementById('ssClearRoute');
    if (clearBtn) clearBtn.hidden = false;

    // 7. Open evac popup
    const nearestMarkerEntry = _evacMarkers.find(m => m.data.center_id == nearest.center_id);
    if (nearestMarkerEntry) nearestMarkerEntry.marker.openPopup();

    // 8. Try OSRM road-following in background — upgrade line if it works
    fetchOsrmRoute(userLat, userLng, destLat, destLng)
      .then(roadCoords => {
        if (!roadCoords || !_routeLine) return; // cleared in the meantime
        clearRouteLayer();
        _routeGlow = L.polyline(roadCoords, { color:'#1d4ed8', weight:12, opacity:0.15, lineCap:'round', lineJoin:'round' }).addTo(_map);
        _routeLine = L.polyline(roadCoords, { color:'#1d4ed8', weight:5,  opacity:1,    lineCap:'round', lineJoin:'round' }).addTo(_map);
        _routeDash = L.polyline(roadCoords, { color:'#ffffff', weight:2,  opacity:0.7,  dashArray:'10,16', lineCap:'round' }).addTo(_map);
        _map.fitBounds(L.latLngBounds(roadCoords), { padding:[60,60], maxZoom:17 });
      })
      .catch(() => {}); // silent — straight line already showing
  }

  async function fetchOsrmRoute(lat1, lng1, lat2, lng2) {
    const url = `https://router.project-osrm.org/route/v1/foot/${lng1},${lat1};${lng2},${lat2}?overview=full&geometries=geojson`;
    const res  = await fetch(url, { signal: AbortSignal.timeout(8000) });
    const json = await res.json();
    if (json.code !== 'Ok' || !json.routes?.length) return null;
    return json.routes[0].geometry.coordinates.map(([lng, lat]) => [lat, lng]);
  }

  // Separate route layer refs
  let _routeLine = null;
  let _routeGlow = null;
  let _routeDash = null;

  function clearRouteLayer() {
    [_routeGlow, _routeLine, _routeDash].forEach(l => {
      if (l && _map) { try { _map.removeLayer(l); } catch(_){} }
    });
    _routeGlow = _routeLine = _routeDash = null;
    if (_routingControl && _map) {
      try { _map.removeControl(_routingControl); } catch(_) {}
      _routingControl = null;
    }
  }

  function clearRoute() {
    clearRouteLayer();
    if (_userMarker && _map) { try { _map.removeLayer(_userMarker); } catch(_){} _userMarker = null; }
    // Reset cached position so the next click re-acquires GPS
    _userLat = null;
    _userLng = null;
    const panel    = document.getElementById('ssRoutePanel');
    const clearBtn = document.getElementById('ssClearRoute');
    const legend   = document.getElementById('ssUserLegend');
    if (panel)    panel.hidden    = true;
    if (clearBtn) clearBtn.hidden = true;
    if (legend)   legend.hidden   = true;
    if (_map) _map.closePopup();
  }

  function resetLocateBtn() {
    const btn = document.getElementById('ssLocateEvac');
    if (btn) {
      btn.disabled = false;
      btn.innerHTML = '<i class="fa-solid fa-location-crosshairs"></i> Nearest Evacuation Center';
    }
  }

  // Haversine distance in metres
  function haversine(lat1, lng1, lat2, lng2) {
    const R  = 6371000;
    const φ1 = lat1 * Math.PI / 180;
    const φ2 = lat2 * Math.PI / 180;
    const Δφ = (lat2 - lat1) * Math.PI / 180;
    const Δλ = (lng2 - lng1) * Math.PI / 180;
    const a  = Math.sin(Δφ/2)**2 + Math.cos(φ1)*Math.cos(φ2)*Math.sin(Δλ/2)**2;
    return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
  }

})();