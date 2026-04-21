(function () {
  'use strict';

  const API = '../backend/home.php';

  // ── Generic fetch wrapper ────────────────────────────────
  async function api(action) {
    const res = await fetch(`${API}?action=${action}`);
    if (!res.ok) throw new Error(`HTTP ${res.status} for action="${action}"`);
    const data = await res.json();
    if (!data.ok) throw new Error(data.error || `API error for action="${action}"`);
    return data;
  }

  // ── Skeleton helpers ─────────────────────────────────────
  function shimmer(el) {
    el.classList.add('sk-loading');
  }
  function unshimmer(el) {
    el.classList.remove('sk-loading');
  }

  // ── Safely set text/html on an element ──────────────────
  function setText(sel, value, fallback = '—') {
    const el = document.querySelector(sel);
    if (el) el.textContent = value ?? fallback;
  }

  function setHTML(sel, html) {
    const el = document.querySelector(sel);
    if (el) el.innerHTML = html;
  }

  // ── Number formatter ─────────────────────────────────────
  const fmt = (n) => Number(n).toLocaleString();

  // ════════════════════════════════════════════════════════
  // 1.  KPI STRIP + ALERT BANNER
  // ════════════════════════════════════════════════════════
  async function loadKPI() {
    const strip = document.getElementById('kpi-strip');
    if (strip) shimmer(strip);

    const data = await api('kpi');
    const s    = data.stats;
    const t    = data.typhoon;

    // ── Alert banner (typhoon active) ─────────────────────
    const banner = document.getElementById('alert-banner');
    if (banner && t && t.status === 'Active') {
      document.getElementById('alert-typhoon-name').textContent = t.event_name;
      document.getElementById('alert-typhoon-cat').textContent  = t.category ?? '—';
      document.getElementById('alert-typhoon-kph').textContent  =
        t.wind_speed_kph ? Number(t.wind_speed_kph).toLocaleString() : '—';
      banner.hidden = false;
    }

    // ── KPI values ────────────────────────────────────────
    setText('#kpi-total',      fmt(s.total_streets));
    setText('#kpi-critical',   fmt(s.critical_streets));
    setText('#kpi-high',       fmt(s.high_streets));
    setText('#kpi-pct',        s.pct_affected + '%');
    setText('#kpi-population', fmt(s.total_population));

    // ── Hero description count ────────────────────────────
    const heroCount = document.getElementById('hero-affected-count');
    if (heroCount) {
      heroCount.textContent =
        `Currently ${fmt(s.affected_streets)} of ${fmt(s.total_streets)} streets are in an affected state.`;
    }

    if (strip) unshimmer(strip);
  }

  // ════════════════════════════════════════════════════════
  // 2.  MAP LEGEND COUNTS
  // ════════════════════════════════════════════════════════
  async function loadRiskCounts() {
    const data = await api('risk_counts');
    const c    = data.counts;

    const map = {
      RED:    'legend-count-red',
      ORANGE: 'legend-count-orange',
      YELLOW: 'legend-count-yellow',
      GREEN:  'legend-count-green',
    };

    for (const [level, id] of Object.entries(map)) {
      const el = document.getElementById(id);
      if (!el) continue;
      const n   = c[level] ?? 0;
      el.textContent = `${n} street${n !== 1 ? 's' : ''}`;
      el.hidden = false;
    }
  }

  // ════════════════════════════════════════════════════════
  // 3.  RISK LEVEL CARDS — example streets
  // ════════════════════════════════════════════════════════
  async function loadRiskExamples() {
    const data = await api('risk_examples');
    const ex   = data.examples;

    const levels = ['RED', 'ORANGE', 'YELLOW', 'GREEN'];
    levels.forEach(level => {
      const el = document.getElementById(`risk-example-${level}`);
      if (!el) return;
      if (ex[level]) {
        el.innerHTML =
          `<i class="fa-solid fa-location-dot"></i>
           ${escHtml(ex[level].street_name)}
           &mdash; ${Number(ex[level].vuln_score).toFixed(1)}% vulnerability`;
        el.hidden = false;
      }
    });
  }

  // ════════════════════════════════════════════════════════
  // 4.  RESOURCES — live inventory cards
  // ════════════════════════════════════════════════════════
  async function loadResources() {
    const grid = document.getElementById('resources-grid');
    if (!grid) return;
    shimmer(grid);

    const data = await api('resources');
    const rows = data.resources;

    if (!rows.length) {
      unshimmer(grid);
      return;
    }

    const iconMap = {
      Food:      'fa-solid fa-bowl-food',
      Medical:   'fa-solid fa-kit-medical',
      Water:     'fa-solid fa-droplet',
      Shelter:   'fa-solid fa-house-chimney',
      Transport: 'fa-solid fa-van-shuttle',
    };

    grid.innerHTML = rows.map(r => {
      const icon = iconMap[r.category] ?? 'fa-solid fa-boxes-stacked';
      const pct  = Math.min(Number(r.pct_distributed) || 0, 100);
      return `
        <article class="assist-card" role="listitem">
          <div class="assist-icon" aria-hidden="true">
            <i class="${icon}"></i>
          </div>
          <h3 class="assist-title">${escHtml(r.resource_name)}</h3>
          <div class="resource-qty">
            ${fmt(r.qty_remaining)}
            <span>${escHtml(r.unit)} remaining</span>
          </div>
          <div class="resource-bar-wrap">
            <div class="resource-bar-label">
              <span>Distributed</span>
              <span>${pct}%</span>
            </div>
            <div class="resource-bar-track">
              <div class="resource-bar-fill" style="width:${pct}%"></div>
            </div>
          </div>
        </article>`;
    }).join('');

    unshimmer(grid);
  }

  // ════════════════════════════════════════════════════════
  // 5.  ANNOUNCEMENTS
  //     Tries the real `announcements` table first (via
  //     backend/announcements.php). Falls back gracefully
  //     to the old typhoon + welfare items from home.php.
  // ════════════════════════════════════════════════════════
  async function loadAnnouncements() {
    const list = document.getElementById('ann-list');
    if (!list) return;
    shimmer(list);

    let items = [];

    // ── Try real announcements table ─────────────────────
    try {
      const res = await fetch('../backend/announcements.php?action=announcements');
      if (res.ok) {
        const json = await res.json();
        if (json.ok && json.announcements?.length) {
          // Map announcements table rows to the same shape used below
          items = json.announcements.slice(0, 3).map(a => {
            const d = new Date(a.created_at.replace(' ', 'T'));
            return {
              tag:      a.category,
              tag_icon: {
                Alert:      'fa-solid fa-triangle-exclamation',
                Evacuation: 'fa-solid fa-person-walking-arrow-right',
                Relief:     'fa-solid fa-hand-holding-heart',
                Weather:    'fa-solid fa-cloud-bolt',
                General:    'fa-solid fa-circle-info',
              }[a.category] ?? 'fa-solid fa-bullhorn',
              title:   a.title,
              preview: a.body?.slice(0, 160) + (a.body?.length > 160 ? '…' : ''),
              date:    a.created_at,
              day:     String(d.getDate()).padStart(2, '0'),
              month:   d.toLocaleString('en', { month: 'short' }),
            };
          });
        }
      }
    } catch (_) { /* silent — fall through to home.php */ }

    // ── Fallback: typhoon events + welfare plans ─────────
    if (!items.length) {
      try {
        const data = await api('announcements');
        items = data.announcements || [];
      } catch (_) {}
    }

    if (!items.length) {
      list.innerHTML = `
        <article class="ann-item" role="listitem">
          <div class="ann-date-block">
            <div class="ann-day">${new Date().getDate().toString().padStart(2,'0')}</div>
            <div class="ann-month">${new Date().toLocaleString('en',{month:'short'})}</div>
          </div>
          <div class="ann-body">
            <span class="ann-tag"><i class="fa-solid fa-circle-info"></i> System</span>
            <h3 class="ann-title">No announcements yet</h3>
            <p class="ann-preview">
              Announcements will appear here once barangay staff have posted updates.
            </p>
          </div>
        </article>`;
      unshimmer(list);
      return;
    }

    list.innerHTML = items.map(a => `
      <article class="ann-item" role="listitem">
        <div class="ann-date-block" aria-label="Date: ${escHtml(a.month)} ${escHtml(a.day)}">
          <div class="ann-day">${escHtml(a.day)}</div>
          <div class="ann-month">${escHtml(a.month)}</div>
        </div>
        <div class="ann-body">
          <span class="ann-tag">
            <i class="${escHtml(a.tag_icon)}" aria-hidden="true"></i>
            ${escHtml(a.tag)}
          </span>
          <h3 class="ann-title">${escHtml(a.title)}</h3>
          <p class="ann-preview">${escHtml(a.preview)}</p>
        </div>
        <div class="ann-action">
          <a href="announcements.php" class="btn-text">
            Read More <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
          </a>
        </div>
      </article>`).join('');

    unshimmer(list);
  }

  // ════════════════════════════════════════════════════════
  // 6.  PENDING REPORTS BADGE
  // ════════════════════════════════════════════════════════
  async function loadPendingReports() {
    const data  = await api('pending_reports');
    const count = data.count;
    const badge = document.getElementById('pending-badge');
    if (!badge) return;

    if (count > 0) {
      document.getElementById('pending-count').textContent = count;
      document.getElementById('pending-plural').textContent = count !== 1 ? 's' : '';
      badge.hidden = false;
    }
  }

  // ════════════════════════════════════════════════════════
  // SCROLL ANIMATIONS (IntersectionObserver)
  // ════════════════════════════════════════════════════════
  function initScrollAnimations() {
    if (!('IntersectionObserver' in window)) return;

    const targets = document.querySelectorAll(
      '.feature-card, .risk-card, .assist-card, .ann-item, .report-step'
    );

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

  // Re-run after dynamic content is injected
  function observeNewCards() {
    if (!('IntersectionObserver' in window)) return;

    const targets = document.querySelectorAll(
      '#resources-grid .assist-card, #ann-list .ann-item'
    );
    targets.forEach((el, i) => {
      if (el.style.opacity === '0') return; // already observed
      el.style.opacity         = '0';
      el.style.transform       = 'translateY(20px)';
      el.style.transition      = 'opacity 0.46s ease, transform 0.46s ease';
      el.style.transitionDelay = `${(i % 4) * 0.08}s`;

      const io = new IntersectionObserver((entries) => {
        entries.forEach(e => {
          if (!e.isIntersecting) return;
          e.target.style.opacity   = '1';
          e.target.style.transform = 'translateY(0)';
          io.unobserve(e.target);
        });
      }, { threshold: 0.1 });
      io.observe(el);
    });
  }

  // ════════════════════════════════════════════════════════
  // XSS HELPER
  // ════════════════════════════════════════════════════════
  function escHtml(str) {
    if (str == null) return '';
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  // ════════════════════════════════════════════════════════
  // 7.  MAP — Leaflet.js with DB street markers
  // ════════════════════════════════════════════════════════

  // Risk level → Leaflet circle marker color
  const RISK_COLOR = {
    RED:    '#b91c1c',
    ORANGE: '#d97706',
    YELLOW: '#ca8a04',
    GREEN:  '#16a34a',
  };

  // Risk level → fill opacity
  const RISK_FILL = {
    RED: 0.9, ORANGE: 0.85, YELLOW: 0.8, GREEN: 0.75,
  };

  function buildStreetPopup(s) {
    const impact = s.typhoon_impact;
    const riskLabel = {
      RED: 'Critical', ORANGE: 'High Risk',
      YELLOW: 'Moderate', GREEN: 'Safe',
    }[s.risk_level] || s.risk_level;

    let impactHtml = '';
    if (impact) {
      impactHtml = `
        <div class="map-popup-divider"></div>
        <div class="map-popup-row">
          <strong>Typhoon</strong>${escHtml(impact.typhoon_name)}
        </div>
        <div class="map-popup-row">
          <strong>Flood</strong>${escHtml(impact.flood_status)}
        </div>
        <div class="map-popup-row">
          <strong>Damage</strong>${escHtml(impact.damage_status)}
        </div>
        ${impact.flood_height_m
          ? `<div class="map-popup-row"><strong>Height</strong>${parseFloat(impact.flood_height_m).toFixed(2)} m</div>`
          : ''}
        <div class="map-popup-row">
          <strong>Affected HH</strong>${Number(impact.affected_households).toLocaleString()}
        </div>`;
    }

    return `
      <div class="map-popup">
        <div class="map-popup-name">${escHtml(s.street_name)}</div>
        <div class="map-popup-zone">${escHtml(s.zone_name)}</div>
        <span class="map-popup-badge ${escHtml(s.risk_level)}">
          ${escHtml(s.risk_level)} &mdash; ${riskLabel}
        </span>
        <div class="map-popup-row">
          <strong>Vuln. Score</strong>${parseFloat(s.vuln_score).toFixed(1)}%
        </div>
        <div class="map-popup-row">
          <strong>Population</strong>${Number(s.total_population).toLocaleString()}
        </div>
        <div class="map-popup-row">
          <strong>Households</strong>${Number(s.total_households).toLocaleString()}
        </div>
        <div class="map-popup-row">
          <strong>Needs Welfare</strong>${escHtml(s.needs_welfare)}
        </div>
        ${impactHtml}
      </div>`;
  }

  function buildEvacPopup(e) {
    const pct = e.capacity > 0
      ? Math.round(e.current_occupancy / e.capacity * 100)
      : 0;
    return `
      <div class="map-popup">
        <div class="map-popup-name">${escHtml(e.center_name)}</div>
        <div class="map-popup-zone">${escHtml(e.zone_name)}</div>
        <span class="map-popup-badge EVAC">
          <i class="fa-solid fa-person-walking-arrow-right"></i>
          Evacuation Center
        </span>
        <div class="map-popup-row">
          <strong>Capacity</strong>${Number(e.capacity).toLocaleString()} persons
        </div>
        <div class="map-popup-row">
          <strong>Occupancy</strong>${Number(e.current_occupancy).toLocaleString()} (${pct}%)
        </div>
        ${e.address
          ? `<div class="map-popup-row"><strong>Address</strong>${escHtml(e.address)}</div>`
          : ''}
      </div>`;
  }

  async function loadMap() {
    const mapEl = document.getElementById('home-map');
    if (!mapEl || typeof L === 'undefined') return;

    const data = await api('map_data');
    const streets = data.streets || [];
    const evacs   = data.evac    || [];

    if (!streets.length) return;

    // Centre map on average of all street coordinates
    const avgLat = streets.reduce((s, r) => s + parseFloat(r.latitude),  0) / streets.length;
    const avgLng = streets.reduce((s, r) => s + parseFloat(r.longitude), 0) / streets.length;

    // Initialise Leaflet map
    const map = L.map('home-map', {
      center:        [avgLat, avgLng],
      zoom:          16,
      zoomControl:   true,
      scrollWheelZoom: false,   // prevents accidental scroll-zoom on the homepage
    });

    // OpenStreetMap tile layer
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
      maxZoom: 19,
    }).addTo(map);

    // ── Street markers (color-coded circles) ──────────────
    const bounds = [];

    streets.forEach(s => {
      const lat   = parseFloat(s.latitude);
      const lng   = parseFloat(s.longitude);
      const level = s.risk_level || 'GREEN';
      const color = RISK_COLOR[level] || '#16a34a';

      const marker = L.circleMarker([lat, lng], {
        radius:      10,
        fillColor:   color,
        color:       '#ffffff',
        weight:      2,
        opacity:     1,
        fillOpacity: RISK_FILL[level] || 0.8,
      }).addTo(map);

      marker.bindPopup(buildStreetPopup(s), {
        maxWidth: 260,
        className: 'equiaid-popup',
      });

      // Pulse effect on RED markers
      if (level === 'RED') {
        marker.on('add', () => {
          const el = marker.getElement();
          if (el) el.style.animation = 'pulse-marker 1.8s infinite';
        });
      }

      bounds.push([lat, lng]);
    });

    // ── Evacuation center markers (blue hospital icon) ─────
    const evacIcon = L.divIcon({
      className: '',
      html: `<div style="
        width:28px;height:28px;
        background:#1d4ed8;
        border:2px solid #fff;
        border-radius:50%;
        display:flex;align-items:center;justify-content:center;
        box-shadow:0 2px 8px rgba(0,0,0,0.3);
        font-size:12px;color:#fff;">
        <i class='fa-solid fa-house-medical'></i>
      </div>`,
      iconSize:   [28, 28],
      iconAnchor: [14, 14],
    });

    evacs.forEach(e => {
      const lat = parseFloat(e.latitude);
      const lng = parseFloat(e.longitude);

      L.marker([lat, lng], { icon: evacIcon })
        .addTo(map)
        .bindPopup(buildEvacPopup(e), {
          maxWidth: 240,
          className: 'equiaid-popup',
        });

      bounds.push([lat, lng]);
    });

    // Fit map to show all markers with padding
    if (bounds.length) {
      map.fitBounds(bounds, { padding: [32, 32] });
    }

    // Mark map as ready (hides loading spinner)
    mapEl.classList.add('map-ready');
  }

  // ════════════════════════════════════════════════════════
  // BOOTSTRAP — run all fetches in parallel
  // ════════════════════════════════════════════════════════
  async function init() {
    initScrollAnimations();

    // Run all API calls in parallel — independent of each other
    await Promise.allSettled([
      loadKPI().catch(err => console.error('[KPI]', err)),
      loadRiskCounts().catch(err => console.error('[RiskCounts]', err)),
      loadRiskExamples().catch(err => console.error('[RiskExamples]', err)),
      loadResources().catch(err => console.error('[Resources]', err)),
      loadAnnouncements().catch(err => console.error('[Announcements]', err)),
      loadPendingReports().catch(err => console.error('[PendingReports]', err)),
      loadMap().catch(err => console.error('[Map]', err)),
    ]);

    // Re-observe dynamically injected cards for animations
    observeNewCards();
  }

  // Start after DOM is ready
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

})();