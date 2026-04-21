(function () {
  'use strict';

  /* ══════════════════════════════════════════════════════
     API  — mirrors admin_dashboard.js pattern exactly
  ══════════════════════════════════════════════════════ */
  var API = '../backend/street_monitoring.php';

  async function api(action, params) {
    var qs = '?action=' + encodeURIComponent(action);
    if (params) {
      Object.keys(params).forEach(function (k) {
        if (params[k] !== undefined && params[k] !== null) {
          qs += '&' + encodeURIComponent(k) + '=' + encodeURIComponent(params[k]);
        }
      });
    }
    var res = await fetch(API + qs);
    if (!res.ok) throw new Error('HTTP ' + res.status);
    var data = await res.json();
    if (!data.ok) throw new Error(data.error || 'API error');
    return data;
  }

  async function apiPost(action, body) {
    var res = await fetch(API + '?action=' + encodeURIComponent(action), {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(body),
    });
    if (!res.ok) throw new Error('HTTP ' + res.status);
    var data = await res.json();
    if (!data.ok) throw new Error(data.error || 'API error');
    return data;
  }

  /* ══════════════════════════════════════════════════════
     HELPERS
  ══════════════════════════════════════════════════════ */
  function esc(str) {
    if (str == null) return '';
    return String(str)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;')
      .replace(/>/g, '&gt;').replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  /* Fix image path: DB stores relative from project root e.g.
     "uploads/streets/2024/07/file.jpg"
     Admin page lives at /admin/ so we need ../uploads/...
     Also handle paths that already start with ../ or / */
  function imgSrc(filePath) {
    if (!filePath) return '';
    if (filePath.startsWith('http') || filePath.startsWith('/') || filePath.startsWith('../')) {
      return filePath;
    }
    return '../' + filePath;
  }

  function riskColor(r) {
    return { RED: '#dc2626', ORANGE: '#d97706', YELLOW: '#ca8a04', GREEN: '#16a34a' }[
      (r || '').toUpperCase()
    ] || '#9ca3af';
  }

  function riskClass(r)    { return 'risk-' + (r || 'green').toLowerCase(); }
  function welfareClass(w) {
    return { Yes: 'status-ongoing', Moderate: 'status-pending', No: 'status-resolved' }[w] || '';
  }
  function drainClass(d) {
    return {
      'None': 'sm-drain-none', 'Open Canal': 'sm-drain-open',
      'Closed Drainage': 'sm-drain-closed', 'Underground': 'sm-drain-underground'
    }[d] || '';
  }

  function shimmer(el)   { if (el) el.classList.add('sk-loading'); }
  function unshimmer(el) { if (el) el.classList.remove('sk-loading'); }

  function showFeedback(id, msg, type) {
    var el = document.getElementById(id);
    if (!el) return;
    el.textContent = msg;
    el.className = 'sm-upload-feedback ' + type;
    el.style.display = '';
  }

  function hideFeedback(id) {
    var el = document.getElementById(id);
    if (el) el.style.display = 'none';
  }

  function updateTimestamp() {
    var el = document.getElementById('lastUpdated');
    if (el) el.textContent = new Date().toLocaleString('en-PH', {
      month: 'short', day: 'numeric', year: 'numeric',
      hour: '2-digit', minute: '2-digit'
    });
  }

  function countUp(el, target) {
    if (!el || isNaN(target)) return;
    target = Math.round(target);
    var start = null;
    (function step(ts) {
      if (!start) start = ts;
      var p = Math.min((ts - start) / 800, 1);
      el.textContent = Math.floor((1 - Math.pow(2, -10 * p)) * target);
      if (p < 1) requestAnimationFrame(step);
      else el.textContent = target;
    })(performance.now());
  }

  /* ══════════════════════════════════════════════════════
     PAGINATOR — identical logic to admin_dashboard.js
  ══════════════════════════════════════════════════════ */
  function makePaginator(opts) {
    var rows     = opts.rows     || [];
    var pageSize = opts.pageSize || 10;
    var total    = rows.length;
    var pages    = Math.max(1, Math.ceil(total / pageSize));
    var current  = 1;
    var tbody    = document.getElementById(opts.tbodyId);
    var footer   = document.getElementById(opts.footerId);
    var infoEl   = document.getElementById(opts.infoId);
    var paginEl  = document.getElementById(opts.paginId);

    function renderPage(page) {
      current = Math.max(1, Math.min(page, pages));
      if (!tbody) return;
      var slice = rows.slice((current - 1) * pageSize, current * pageSize);

      if (!slice.length) {
        tbody.innerHTML = '<tr><td colspan="' + opts.colSpan + '" class="tbl-empty">' +
          (opts.emptyMsg || 'No data.') + '</td></tr>';
      } else {
        tbody.innerHTML = slice.map(opts.renderRow).join('');
        if (opts.afterRender) opts.afterRender(tbody);
        initRowClick(tbody, opts.onRowClick);
      }

      if (infoEl) {
        var from = total ? (current - 1) * pageSize + 1 : 0;
        var to   = Math.min(current * pageSize, total);
        infoEl.innerHTML = 'Showing <strong>' + from + '\u2013' + to +
          '</strong> of <strong>' + total + '</strong>';
      }

      if (paginEl) {
        var html = '';
        var winSz = 3;
        var s = Math.max(1, current - 1);
        var e = Math.min(pages, s + winSz - 1);
        if (e - s < winSz - 1) s = Math.max(1, e - winSz + 1);

        html += '<button class="page-btn" data-page="prev"' + (current === 1 ? ' disabled' : '') + '>' +
          '<i class="fa-solid fa-chevron-left"></i></button>';
        if (s > 1) html += '<button class="page-btn" data-page="1">1</button>' +
          (s > 2 ? '<span class="page-btn" style="border:none;cursor:default;">\u2026</span>' : '');
        for (var i = s; i <= e; i++) {
          html += '<button class="page-btn' + (i === current ? ' active' : '') + '" data-page="' + i + '">' + i + '</button>';
        }
        if (e < pages) html += (e < pages - 1 ? '<span class="page-btn" style="border:none;cursor:default;">\u2026</span>' : '') +
          '<button class="page-btn" data-page="' + pages + '">' + pages + '</button>';
        html += '<button class="page-btn" data-page="next"' + (current === pages ? ' disabled' : '') + '>' +
          '<i class="fa-solid fa-chevron-right"></i></button>';

        paginEl.innerHTML = html;
        paginEl.querySelectorAll('.page-btn[data-page]').forEach(function (btn) {
          btn.addEventListener('click', function () {
            var p = btn.dataset.page;
            if (p === 'prev') renderPage(current - 1);
            else if (p === 'next') renderPage(current + 1);
            else renderPage(parseInt(p, 10));
          });
        });
      }
    }

    if (footer && total > 0) footer.style.display = '';
    renderPage(1);
    return { setRows: function (r) { rows = r; total = r.length; pages = Math.max(1, Math.ceil(total / pageSize)); renderPage(1); } };
  }

  function initRowClick(tbody, cb) {
    if (!tbody || !cb) return;
    tbody.querySelectorAll('tr').forEach(function (row) {
      row.addEventListener('click', function (e) {
        if (e.target.closest('a, button')) return;
        tbody.querySelectorAll('tr').forEach(function (r) { r.classList.remove('row-selected'); });
        row.classList.add('row-selected');
        cb(row);
      });
    });
  }

  /* ══════════════════════════════════════════════════════
     CARD FADE-IN — mirrors admin_dashboard.js
  ══════════════════════════════════════════════════════ */
  function initCardFadeIn() {
    if (!('IntersectionObserver' in window)) return;
    var cards = document.querySelectorAll('.dash-card, .kpi-card');
    cards.forEach(function (c) {
      c.style.opacity = '0';
      c.style.transform = 'translateY(12px)';
      c.style.transition = 'opacity 0.4s ease, transform 0.4s ease';
    });
    var obs = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (!entry.isIntersecting) return;
        var siblings = Array.from(entry.target.parentElement ? entry.target.parentElement.children : []);
        var delay = Math.min(siblings.indexOf(entry.target) * 55, 220);
        setTimeout(function () {
          entry.target.style.opacity = '1';
          entry.target.style.transform = 'translateY(0)';
        }, delay);
        obs.unobserve(entry.target);
      });
    }, { threshold: 0.04 });
    cards.forEach(function (c) { obs.observe(c); });
  }

  /* ══════════════════════════════════════════════════════
     MODALS
  ══════════════════════════════════════════════════════ */
  function openModal(id) {
    var el = document.getElementById(id);
    if (el) { el.classList.add('open'); document.body.style.overflow = 'hidden'; }
  }
  function closeModal(id) {
    var el = document.getElementById(id);
    if (el) { el.classList.remove('open'); document.body.style.overflow = ''; }
  }

  /* Wire close buttons + backdrop click + ESC */
  ['streetModal', 'imgLightbox', 'editStreetModal', 'demoModal', 'confirmModal'].forEach(function (id) {
    var el = document.getElementById(id);
    if (!el) return;
    el.addEventListener('click', function (e) { if (e.target === el) closeModal(id); });
  });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
      closeModal('streetModal'); closeModal('imgLightbox');
      closeModal('editStreetModal'); closeModal('demoModal');
      closeModal('confirmModal');
    }
  });
  ['streetModalClose', 'imgLightboxClose', 'editStreetClose', 'esCancel',
   'demoModalClose', 'ddCancel', 'confirmModalClose', 'confirmCancelBtn'].forEach(function (btnId) {
    var btn = document.getElementById(btnId);
    if (!btn) return;
    btn.addEventListener('click', function () {
      closeModal('streetModal'); closeModal('imgLightbox');
      closeModal('editStreetModal'); closeModal('demoModal');
      closeModal('confirmModal');
    });
  });

  /* ── Reusable confirm dialog ──────────────────────────
     showConfirm(message, eyebrow, asyncCallback)
     The callback is awaited — spinner shown while running.
  ──────────────────────────────────────────────────── */
  var _confirmCallback = null;

  function showConfirm(message, eyebrow, onConfirm) {
    var msgEl   = document.getElementById('confirmMessage');
    var eyeEl   = document.getElementById('confirmModalEyebrow');
    var titleEl = document.getElementById('confirmModalTitle');
    if (msgEl)   msgEl.textContent   = message || 'This action cannot be undone.';
    if (eyeEl)   eyeEl.textContent   = eyebrow  || 'Confirm Action';
    if (titleEl) titleEl.textContent = 'Delete Record?';
    _confirmCallback = onConfirm;
    openModal('confirmModal');
  }

  var confirmOkBtn = document.getElementById('confirmOkBtn');
  if (confirmOkBtn) {
    confirmOkBtn.addEventListener('click', async function () {
      if (typeof _confirmCallback !== 'function') return;
      confirmOkBtn.disabled = true;
      confirmOkBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Deleting\u2026';
      try {
        await _confirmCallback();
      } finally {
        confirmOkBtn.disabled = false;
        confirmOkBtn.innerHTML = '<i class="fa-solid fa-trash"></i> Delete';
        _confirmCallback = null;
        closeModal('confirmModal');
      }
    });
  }

  /* Street detail modal */
  function openStreetModal(s) {
    document.getElementById('streetModalEyebrow').textContent = 'Zone ' + (s.zone_name || '\u2014');
    document.getElementById('streetModalTitle').textContent   = s.street_name || '\u2014';

    var riskHtml = '<span class="risk-pill ' + riskClass(s.risk_level) + '">' + esc(s.risk_level || 'N/A') + '</span>';
    var welHtml  = '<span class="status-pill ' + welfareClass(s.needs_welfare) + '">' + esc(s.needs_welfare || 'N/A') + '</span>';

    document.getElementById('streetModalBody').innerHTML =
      '<div class="modal-field-grid">' +
        '<div class="modal-section-title">Vulnerability</div>' +
        '<div class="modal-field"><span class="modal-field-label">Risk Level</span><span class="modal-field-value">' + riskHtml + '</span></div>' +
        '<div class="modal-field"><span class="modal-field-label">Vuln. Score</span><span class="modal-field-value">' + esc(s.vuln_score != null ? s.vuln_score + ' / 100' : '\u2014') + '</span></div>' +
        '<div class="modal-field"><span class="modal-field-label">Needs Welfare</span><span class="modal-field-value">' + welHtml + '</span></div>' +
        '<div class="modal-field"><span class="modal-field-label">Welfare Priority</span><span class="modal-field-value">' + esc(s.welfare_priority ? '#' + s.welfare_priority : '\u2014') + '</span></div>' +
        '<div class="modal-field"><span class="modal-field-label">Image Contribution</span><span class="modal-field-value">' + esc(s.image_contribution_pct != null ? s.image_contribution_pct + '%' : '\u2014') + '</span></div>' +
        '<div class="modal-field"><span class="modal-field-label">Demo Contribution</span><span class="modal-field-value">' + esc(s.demographic_contribution_pct != null ? s.demographic_contribution_pct + '%' : '\u2014') + '</span></div>' +
        '<div class="modal-field"><span class="modal-field-label">Last Predicted</span><span class="modal-field-value">' + esc(s.predicted_at || '\u2014') + '</span></div>' +
        '<div class="modal-field"><span class="modal-field-label">Trigger</span><span class="modal-field-value">' + esc(s.trigger_type || '\u2014') + '</span></div>' +
        '<div class="modal-section-title">Demographics</div>' +
        '<div class="modal-field"><span class="modal-field-label">4Ps Households</span><span class="modal-field-value">' + esc(s.fourps_households != null ? s.fourps_households : '\u2014') + '</span></div>' +
        '<div class="modal-field"><span class="modal-field-label">Poverty Rate</span><span class="modal-field-value">' + esc(s.poverty_rate_pct != null ? s.poverty_rate_pct + '%' : '\u2014') + '</span></div>' +
        '<div class="modal-field"><span class="modal-field-label">PWD Count</span><span class="modal-field-value">' + esc(s.pwd_count != null ? s.pwd_count : '\u2014') + '</span></div>' +
        '<div class="modal-field"><span class="modal-field-label">Senior Citizens</span><span class="modal-field-value">' + esc(s.senior_count != null ? s.senior_count : '\u2014') + '</span></div>' +
        '<div class="modal-field"><span class="modal-field-label">Flood Frequency</span><span class="modal-field-value">' + esc(s.flood_frequency != null ? s.flood_frequency + 'x (5 yrs)' : '\u2014') + '</span></div>' +
        '<div class="modal-field"><span class="modal-field-label">Avg Flood Height</span><span class="modal-field-value">' + esc(s.avg_flood_height_m != null ? s.avg_flood_height_m + ' m' : '\u2014') + '</span></div>' +
        '<div class="modal-field"><span class="modal-field-label">Drainage</span><span class="modal-field-value">' + esc(s.drainage_type || '\u2014') + '</span></div>' +
        '<div class="modal-field"><span class="modal-field-label">Road Surface</span><span class="modal-field-value">' + esc(s.road_surface || '\u2014') + '</span></div>' +
      '</div>';

    /* Wire the Edit button inside the modal */
    var editBtn = document.getElementById('streetModalEdit');
    if (editBtn) {
      editBtn.onclick = function () { closeModal('streetModal'); openEditModal(s); };
    }

    openModal('streetModal');
  }

  /* Edit / Add modal */
  var editForm = document.getElementById('editStreetForm');

  function openEditModal(street) {
    hideFeedback('esFeedback');
    var eyebrow = document.getElementById('editStreetEyebrow');
    var title   = document.getElementById('editStreetTitle');

    if (street) {
      if (eyebrow) eyebrow.textContent = 'Edit Street';
      if (title)   title.textContent   = street.street_name || 'Edit';
      document.getElementById('esStreetId').value   = street.street_id   || '';
      document.getElementById('esStreetName').value = street.street_name || '';
      document.getElementById('esZone').value       = street.zone_id     || '';
      document.getElementById('esStatus').value     = street.is_active == 1 ? 'Active' : 'Inactive';
    } else {
      if (eyebrow) eyebrow.textContent = 'New Street';
      if (title)   title.textContent   = 'Add New Street';
      if (editForm) editForm.reset();
      document.getElementById('esStreetId').value = '';
    }
    openModal('editStreetModal');
  }

  var addStreetBtn = document.getElementById('smAddStreetBtn');
  if (addStreetBtn) addStreetBtn.addEventListener('click', function () { openEditModal(null); });

  if (editForm) {
    editForm.addEventListener('submit', async function (e) {
      e.preventDefault();
      var streetId = document.getElementById('esStreetId').value;
      var payload  = {
        street_id:   streetId || null,
        street_name: document.getElementById('esStreetName').value.trim(),
        zone_id:     document.getElementById('esZone').value,
        is_active:   document.getElementById('esStatus').value === 'Active' ? 1 : 0,
      };
      if (!payload.street_name) { showFeedback('esFeedback', 'Street name is required.', 'error'); return; }
      if (!payload.zone_id)     { showFeedback('esFeedback', 'Zone is required.', 'error'); return; }
      showFeedback('esFeedback', 'Saving\u2026', 'loading');
      try {
        await apiPost(streetId ? 'update_street' : 'add_street', payload);
        showFeedback('esFeedback', 'Saved successfully.', 'success');
        logActivity(
            streetId
                ? 'Updated street "' + payload.street_name + '"'
                : 'Added new street "' + payload.street_name + '"',
            'System'
        );
        setTimeout(function () { closeModal('editStreetModal'); loadStreetsTable(); }, 800);
      } catch (err) {
        showFeedback('esFeedback', 'Error: ' + (err.message || 'Could not save.'), 'error');
      }
    });
  }

  /* Lightbox */
  function openLightbox(src, meta) {
    var img  = document.getElementById('imgLightboxSrc');
    var metaEl = document.getElementById('imgLightboxMeta');
    if (img) img.src = src;
    if (metaEl) metaEl.textContent = meta || '';
    openModal('imgLightbox');
  }

  /* ══════════════════════════════════════════════════════
     1. KPIs
  ══════════════════════════════════════════════════════ */
  async function loadKpis() {
    var data = await api('kpis');
    var d    = data.data || {};

    function set(id, val, color) {
      var el = document.getElementById(id);
      if (!el) return;
      el.classList.remove('sk-inline');
      if (color) el.style.color = color;
      countUp(el, val || 0);
    }

    set('kpi-sm-total',   d.total_streets);
    set('kpi-sm-red',     d.streets_red,    '#b91c1c');
    set('kpi-sm-orange',  d.streets_orange, '#d97706');
    set('kpi-sm-yellow',  d.streets_yellow, '#ca8a04');
    set('kpi-sm-green',   d.streets_green,  '#16a34a');
    set('kpi-sm-welfare', d.streets_needs_welfare);

    function setLeg(id, val) { var e = document.getElementById(id); if (e) e.textContent = val || 0; }
    setLeg('leg-sm-red',    d.streets_red);
    setLeg('leg-sm-orange', d.streets_orange);
    setLeg('leg-sm-yellow', d.streets_yellow);
    setLeg('leg-sm-green',  d.streets_green);
  }

  /* ══════════════════════════════════════════════════════
     2. RISK + WELFARE BARS
  ══════════════════════════════════════════════════════ */
  async function loadRiskDist() {
    var data  = await api('risk_distribution');
    var d     = data.data || {};
    var total = (d.RED || 0) + (d.ORANGE || 0) + (d.YELLOW || 0) + (d.GREEN || 0);

    var chartWrap = document.getElementById('smRiskChart');
    if (chartWrap) {
      var riskRows = [
        { label: 'Critical (RED)',     key: 'RED',    dot: 'risk-dot-red',    fill: 'risk-fill-red'    },
        { label: 'High Risk (ORANGE)', key: 'ORANGE', dot: 'risk-dot-orange', fill: 'risk-fill-orange' },
        { label: 'Moderate (YELLOW)',  key: 'YELLOW', dot: 'risk-dot-yellow', fill: 'risk-fill-yellow' },
        { label: 'Safe (GREEN)',        key: 'GREEN',  dot: 'risk-dot-green',  fill: 'risk-fill-green'  },
      ];
      chartWrap.innerHTML = riskRows.map(function (r) {
        var cnt = d[r.key] || 0;
        var pct = total ? ((cnt / total) * 100).toFixed(1) : '0.0';
        return '<div class="risk-bar-row">' +
          '<div class="risk-bar-label-wrap"><span class="risk-dot ' + r.dot + '"></span>' +
          '<span class="risk-bar-text">' + r.label + '</span></div>' +
          '<div class="risk-bar-track"><div class="risk-bar-fill ' + r.fill +
          '" style="--target-width:' + pct + '%;"></div></div>' +
          '<div class="risk-bar-stat"><span class="risk-count">' + cnt + '</span>' +
          '<span class="risk-pct">' + pct + '%</span></div></div>';
      }).join('');
    }

    var summary = document.getElementById('smRiskSummary');
    if (summary) {
      summary.querySelectorAll('.rs-num').forEach(function (el) { el.classList.remove('sk-inline'); });
      var nums = summary.querySelectorAll('.rs-num');
      ['RED', 'ORANGE', 'YELLOW', 'GREEN'].forEach(function (k, i) { countUp(nums[i], d[k] || 0); });
    }

    var wd     = data.welfare || {};
    var wWrap  = document.getElementById('smWelfareBars');
    if (wWrap) {
      var wTotal = (wd.Yes || 0) + (wd.Moderate || 0) + (wd.No || 0);
      var wRows  = [
        { label: 'Needs Welfare', key: 'Yes',      color: '#dc2626' },
        { label: 'Moderate',      key: 'Moderate', color: '#d97706' },
        { label: 'No Welfare',    key: 'No',        color: '#16a34a' },
      ];
      wWrap.innerHTML = wRows.map(function (r) {
        var cnt = wd[r.key] || 0;
        var pct = wTotal ? ((cnt / wTotal) * 100).toFixed(1) : '0.0';
        return '<div class="risk-bar-row">' +
          '<div class="risk-bar-label-wrap"><span class="risk-dot" style="background:' + r.color + ';"></span>' +
          '<span class="risk-bar-text">' + r.label + '</span></div>' +
          '<div class="risk-bar-track"><div class="risk-bar-fill" style="background:' + r.color +
          ';--target-width:' + pct + '%;"></div></div>' +
          '<div class="risk-bar-stat"><span class="risk-count">' + cnt + '</span>' +
          '<span class="risk-pct">' + pct + '%</span></div></div>';
      }).join('');
    }
  }

  /* ══════════════════════════════════════════════════════
     3. MAP — matches disaster_map.js pattern
     Streets in this DB have NO lat/lng columns.
     We use zone centroid fallback coordinates so the
     map always shows something meaningful.
  ══════════════════════════════════════════════════════ */
  var smMap     = null;
  var smMarkers = [];
  var smMapLayer= 'risk';

  /* Zone centroids for Barangay Bagong Silang, North Caloocan */
  var ZONE_CENTROIDS = {
    'Zone 1': [14.7450, 120.9855],
    'Zone 2': [14.7430, 120.9840],
    'Zone 3': [14.7410, 120.9820],
    'Zone 4': [14.7470, 120.9870],
  };
  /* Small jitter so markers in the same zone don't stack */
  function jitter() { return (Math.random() - 0.5) * 0.0018; }

  async function loadMap() {
    var mapEl   = document.getElementById('sm-map');
    var overlay = document.getElementById('smMapOverlay');
    if (!mapEl || typeof L === 'undefined') return;

    smMap = L.map('sm-map', {
      center: [14.7435, 120.9842],
      zoom: 16,
      zoomControl: true,
      scrollWheelZoom: true,
    });

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      attribution: '&copy; <a href="https://openstreetmap.org/copyright">OpenStreetMap</a>',
      maxZoom: 19,
    }).addTo(smMap);

    var data    = await api('map_streets');
    var streets = data.data || [];

    streets.forEach(function (s) {
      /* Use real coords if they exist, otherwise zone centroid + jitter */
      var lat, lng;
      if (s.latitude && s.longitude) {
        lat = parseFloat(s.latitude);
        lng = parseFloat(s.longitude);
      } else {
        var centroid = ZONE_CENTROIDS['Zone ' + s.zone_name] || ZONE_CENTROIDS['Zone 1'];
        lat = centroid[0] + jitter();
        lng = centroid[1] + jitter();
      }

      var color  = riskColor(s.risk_level);
      var marker = L.circleMarker([lat, lng], {
        radius: 9, fillColor: color, color: '#fff',
        weight: 2, fillOpacity: 0.88,
      });

      /* Popup matches disaster_map.js equiaid-popup style */
      marker.bindPopup(
        '<div class="map-popup">' +
          '<div class="map-popup-name">' + esc(s.street_name) + '</div>' +
          '<div class="map-popup-zone">Zone ' + esc(s.zone_name || '\u2014') + '</div>' +
          '<span class="map-popup-badge ' + esc(s.risk_level || '') + '">' +
            esc(s.risk_level || 'N/A') + '</span>' +
          '<div class="map-popup-divider"></div>' +
          '<div class="map-popup-row"><span>Vuln. Score</span><strong>' +
            esc(s.vuln_score != null ? s.vuln_score : '\u2014') + '</strong></div>' +
          '<div class="map-popup-row"><span>Needs Welfare</span><strong>' +
            esc(s.needs_welfare || '\u2014') + '</strong></div>' +
          '<div class="map-popup-row"><span>Flood Freq.</span><strong>' +
            esc(s.flood_frequency != null ? s.flood_frequency + 'x' : '\u2014') + '</strong></div>' +
        '</div>',
        { className: 'equiaid-popup', maxWidth: 260 }
      );

      marker.addTo(smMap);
      smMarkers.push({ marker: marker, street: s });
    });

    /* Hide loading overlay */
    if (overlay) overlay.style.display = 'none';
    mapEl.classList.add('map-ready');

    /* Layer toggles */
    var btnRisk    = document.getElementById('btnLayerRisk');
    var btnWelfare = document.getElementById('btnLayerWelfare');

    function setLayer(layer) {
      smMapLayer = layer;
      [btnRisk, btnWelfare].forEach(function (b) { if (b) b.classList.remove('active'); });
      if (layer === 'risk' && btnRisk)       btnRisk.classList.add('active');
      if (layer === 'welfare' && btnWelfare) btnWelfare.classList.add('active');
      smMarkers.forEach(function (m) {
        var c = layer === 'welfare'
          ? ({ Yes: '#dc2626', Moderate: '#d97706', No: '#16a34a' }[m.street.needs_welfare] || '#9ca3af')
          : riskColor(m.street.risk_level);
        m.marker.setStyle({ fillColor: c });
      });
    }

    if (btnRisk)    btnRisk.addEventListener('click',    function () { setLayer('risk'); });
    if (btnWelfare) btnWelfare.addEventListener('click', function () { setLayer('welfare'); });

    /* Load evacuation centers — always shown regardless of layer */
    loadEvacCenters();
  }

  /* Custom square marker icon for evac centers (matches disaster_map.js style) */
  function evacIcon() {
    return L.divIcon({
      className: '',
      html: '<div style="' +
        'width:22px;height:22px;background:#1d4ed8;border:2.5px solid #fff;' +
        'border-radius:4px;display:flex;align-items:center;justify-content:center;' +
        'box-shadow:0 2px 8px rgba(0,0,0,.35);">' +
        '<svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.5">' +
        '<path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>' +
        '<polyline points="9 22 9 12 15 12 15 22"/>' +
        '</svg></div>',
      iconSize:   [22, 22],
      iconAnchor: [11, 11],
      popupAnchor:[0, -14],
    });
  }

  async function loadEvacCenters() {
    if (!smMap) return;
    try {
      var data    = await api('evac_centers');
      var centers = data.data || [];

      centers.forEach(function (c) {
        if (!c.latitude || !c.longitude) return;
        var lat = parseFloat(c.latitude);
        var lng = parseFloat(c.longitude);

        var pct   = c.capacity > 0 ? Math.round((c.current_occupancy / c.capacity) * 100) : 0;
        var avail = Math.max(0, (c.capacity || 0) - (c.current_occupancy || 0));

        var marker = L.marker([lat, lng], { icon: evacIcon(), zIndexOffset: 500 });

        marker.bindPopup(
          '<div class="map-popup">' +
            '<div class="map-popup-name">' + esc(c.center_name) + '</div>' +
            '<div class="map-popup-zone">' + esc(c.address || 'Zone ' + (c.zone_name || '')) + '</div>' +
            '<span class="map-popup-badge EVAC">Evacuation Center</span>' +
            '<div class="map-popup-divider"></div>' +
            '<div class="map-popup-row"><span>Capacity</span><strong>' + esc(c.capacity || 0) + ' persons</strong></div>' +
            '<div class="map-popup-row"><span>Current Occupancy</span><strong>' + esc(c.current_occupancy || 0) + ' (' + pct + '%)</strong></div>' +
            '<div class="map-popup-row"><span>Available Slots</span><strong>' + avail + '</strong></div>' +
            (c.contact_person ? '<div class="map-popup-row"><span>Contact</span><strong>' + esc(c.contact_person) + '</strong></div>' : '') +
            (c.contact_number ? '<div class="map-popup-row"><span>Phone</span><strong>' + esc(c.contact_number) + '</strong></div>' : '') +
          '</div>',
          { className: 'equiaid-popup', maxWidth: 260 }
        );

        marker.addTo(smMap);
      });

      /* Update map legend to show evac count */
      var legendCount = centers.length;
      var legEl = document.getElementById('leg-sm-evac');
      if (legEl) legEl.textContent = legendCount;

    } catch (e) {
      console.warn('[SM Evac]', e.message);
    }
  }

  /* ══════════════════════════════════════════════════════
     4. STREETS TABLE
  ══════════════════════════════════════════════════════ */
  var smAllStreets    = [];
  var smActiveRisk    = '';
  var smActiveWelfare = '';
  var smActiveZone    = '';
  var smSearchQuery   = '';
  var smSortBy        = 'score_desc';

  async function loadStreetsTable() {
    var tbody = document.getElementById('smTableTbody');
    if (tbody) shimmer(tbody);

    var data     = await api('streets', { limit: 500 });
    smAllStreets = data.data || [];
    if (tbody) unshimmer(tbody);

    /* Populate zone filter */
    var seen   = {};
    var zoneEl = document.getElementById('smZoneFilter');
    smAllStreets.forEach(function (s) {
      if (s.zone_id && s.zone_name && !seen[s.zone_id]) {
        seen[s.zone_id] = true;
        var opt = document.createElement('option');
        opt.value = s.zone_id;
        opt.textContent = 'Zone ' + s.zone_name;
        if (zoneEl) zoneEl.appendChild(opt);
      }
    });

    /* Populate image filter & edit-modal zone dropdowns */
    var imgFilter   = document.getElementById('smImgStreetFilter');
    var editZoneSel = document.getElementById('esZone');
    var seenZones   = {};
    smAllStreets.forEach(function (s) {
      if (imgFilter) {
        var o = document.createElement('option');
        o.value = s.street_id; o.textContent = s.street_name;
        imgFilter.appendChild(o);
      }
      if (editZoneSel && s.zone_id && !seenZones[s.zone_id]) {
        seenZones[s.zone_id] = true;
        var z = document.createElement('option');
        z.value = s.zone_id; z.textContent = 'Zone ' + s.zone_name;
        editZoneSel.appendChild(z);
      }
    });

    renderStreetsTable();
  }

  function applyFiltersAndSort() {
    return smAllStreets.filter(function (s) {
      if (smActiveRisk    && s.risk_level    !== smActiveRisk)    return false;
      if (smActiveWelfare && s.needs_welfare !== smActiveWelfare) return false;
      if (smActiveZone    && String(s.zone_id) !== smActiveZone)  return false;
      if (smSearchQuery) {
        var q = smSearchQuery.toLowerCase();
        if (!(s.street_name || '').toLowerCase().includes(q) &&
            !('zone ' + (s.zone_name || '')).toLowerCase().includes(q)) return false;
      }
      return true;
    }).sort(function (a, b) {
      if (smSortBy === 'score_desc') return (b.vuln_score || 0) - (a.vuln_score || 0);
      if (smSortBy === 'score_asc')  return (a.vuln_score || 0) - (b.vuln_score || 0);
      if (smSortBy === 'name_asc')   return (a.street_name || '').localeCompare(b.street_name || '');
      if (smSortBy === 'name_desc')  return (b.street_name || '').localeCompare(a.street_name || '');
      return 0;
    });
  }

  function renderStreetsTable() {
    var filtered = applyFiltersAndSort();
    var footer   = document.getElementById('smTableFooter');
    if (!filtered.length) {
      var tbody = document.getElementById('smTableTbody');
      if (tbody) tbody.innerHTML = '<tr><td colspan="11" class="tbl-empty">No streets match the current filters.</td></tr>';
      if (footer) footer.style.display = 'none';
      return;
    }
    makePaginator({
      rows: filtered, pageSize: 10,
      tbodyId: 'smTableTbody', footerId: 'smTableFooter',
      infoId: 'smTableInfo', paginId: 'smTablePagination',
      colSpan: 11, emptyMsg: 'No streets found.',
      renderRow: function (s) {
        var score    = s.vuln_score != null ? parseFloat(s.vuln_score) : null;
        var fillCls  = 'fill-' + (s.risk_level || 'green').toLowerCase();
        var scoreHtml = score !== null
          ? '<div class="score-wrap"><span class="score-num">' + score.toFixed(1) + '</span>' +
            '<div class="score-mini-bar"><div class="score-mini-fill ' + fillCls +
            '" style="width:' + score + '%"></div></div></div>'
          : '<span style="color:var(--slate-light);">\u2014</span>';

        return '<tr data-row=\'' + JSON.stringify(s).replace(/'/g, '&#39;') + '\'>' +
          '<td class="rank-cell">' + esc(s.street_id) + '</td>' +
          '<td><div class="street-name-cell"><i class="fa-solid fa-location-dot street-pin"></i>' + esc(s.street_name) + '</div></td>' +
          '<td>Zone ' + esc(s.zone_name || '\u2014') + '</td>' +
          '<td>' + scoreHtml + '</td>' +
          '<td><span class="risk-pill ' + riskClass(s.risk_level) + '">' + esc(s.risk_level || 'N/A') + '</span></td>' +
          '<td><span class="status-pill ' + welfareClass(s.needs_welfare) + '">' + esc(s.needs_welfare || 'N/A') + '</span></td>' +
          '<td style="text-align:center;">' + esc(s.flood_frequency != null ? s.flood_frequency + 'x' : '\u2014') + '</td>' +
          '<td style="text-align:center;">' + esc((s.pwd_count || 0) + ' / ' + (s.senior_count || 0)) + '</td>' +
          '<td style="text-align:center;">' + esc(s.fourps_households != null ? s.fourps_households : '\u2014') + '</td>' +
          '<td class="date-cell">' + esc(s.predicted_at || '\u2014') + '</td>' +
          '<td><div class="sm-row-actions">' +
            '<button class="sm-act-btn" title="View" data-action="view"><i class="fa-solid fa-eye"></i></button>' +
            '<button class="sm-act-btn" title="Edit" data-action="edit"><i class="fa-solid fa-pen"></i></button>' +
            '<button class="sm-act-btn danger" title="Deactivate street" data-action="delete"><i class="fa-solid fa-trash"></i></button>' +
          '</div></td></tr>';
      },
      afterRender: function (tbody) {
        tbody.querySelectorAll('[data-action="view"]').forEach(function (btn) {
          btn.addEventListener('click', function () {
            openStreetModal(JSON.parse(btn.closest('tr').dataset.row));
          });
        });
        tbody.querySelectorAll('[data-action="edit"]').forEach(function (btn) {
          btn.addEventListener('click', function () {
            openEditModal(JSON.parse(btn.closest('tr').dataset.row));
          });
        });
        tbody.querySelectorAll('[data-action="delete"]').forEach(function (btn) {
          btn.addEventListener('click', function () {
            var s = JSON.parse(btn.closest('tr').dataset.row);
            showConfirm(
              'Deactivate "' + (s.street_name || 'this street') + '"? It will be hidden from all monitoring views. ' +
              'Existing data (predictions, demographics, images) will be preserved.',
              'Deactivate Street',
              async function () {
                await apiPost('delete_street', { street_id: s.street_id });
                logActivity('Deactivated street "' + (s.street_name || 'ID ' + s.street_id) + '"', 'System');
                loadStreetsTable();
                loadKpis().catch(function () {});
                loadRiskDist().catch(function () {});
              }
            );
          });
        });
      },
      onRowClick: function (row) { openStreetModal(JSON.parse(row.dataset.row)); },
    });
  }

  /* Filter chip / sort / search listeners */
  document.querySelectorAll('.sm-chip[data-filter="risk"]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      document.querySelectorAll('.sm-chip[data-filter="risk"]').forEach(function (b) { b.classList.remove('active'); });
      btn.classList.add('active');
      smActiveRisk = btn.dataset.val;
      renderStreetsTable();
    });
  });
  document.querySelectorAll('.sm-chip[data-filter="welfare"]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      document.querySelectorAll('.sm-chip[data-filter="welfare"]').forEach(function (b) { b.classList.remove('active'); });
      btn.classList.add('active');
      smActiveWelfare = btn.dataset.val;
      renderStreetsTable();
    });
  });

  var zoneEl = document.getElementById('smZoneFilter');
  if (zoneEl) zoneEl.addEventListener('change', function () { smActiveZone = zoneEl.value; renderStreetsTable(); });

  var sortEl = document.getElementById('smSortBy');
  if (sortEl) sortEl.addEventListener('change', function () { smSortBy = sortEl.value; renderStreetsTable(); });

  var searchEl    = document.getElementById('smSearch');
  var clearBtn    = document.getElementById('smSearchClear');
  var searchTimer = null;
  if (searchEl) {
    searchEl.addEventListener('input', function () {
      smSearchQuery = searchEl.value.trim();
      if (clearBtn) clearBtn.style.display = smSearchQuery ? '' : 'none';
      clearTimeout(searchTimer);
      searchTimer = setTimeout(renderStreetsTable, 260);
    });
  }
  if (clearBtn) {
    clearBtn.addEventListener('click', function () {
      if (searchEl) searchEl.value = '';
      smSearchQuery = '';
      clearBtn.style.display = 'none';
      renderStreetsTable();
    });
  }

  /* CSV Export */
  var exportBtn = document.getElementById('smExportBtn');
  if (exportBtn) {
    exportBtn.addEventListener('click', function () {
      var rows   = applyFiltersAndSort();
      var header = ['ID','Street','Zone','Score','Risk','Welfare','Flood Freq','PWD','Senior','4Ps HH','Predicted At'];
      var csv    = [header.join(',')].concat(rows.map(function (s) {
        return [s.street_id, s.street_name, 'Zone ' + (s.zone_name || ''),
                s.vuln_score || '', s.risk_level || '', s.needs_welfare || '',
                s.flood_frequency || '', s.pwd_count || 0, s.senior_count || 0,
                s.fourps_households || 0, s.predicted_at || '']
          .map(function (v) { return '"' + String(v).replace(/"/g, '""') + '"'; }).join(',');
      })).join('\n');
      var a  = Object.assign(document.createElement('a'), {
        href:     URL.createObjectURL(new Blob([csv], { type: 'text/csv' })),
        download: 'streets_' + new Date().toISOString().slice(0, 10) + '.csv',
      });
      document.body.appendChild(a); a.click(); document.body.removeChild(a);
    });
  }

  /* ══════════════════════════════════════════════════════
     5. IMAGES GRID
     file_path in DB: "uploads/streets/YYYY/MM/file.jpg"
     Admin is at /admin/ → needs "../" prefix
  ══════════════════════════════════════════════════════ */
  var smAllImages = [];

  async function loadImages() {
    var data    = await api('images', { limit: 48 });
    smAllImages = data.data || [];
    renderImagesGrid(smAllImages);

    var filterEl = document.getElementById('smImgStreetFilter');
    if (filterEl) {
      filterEl.addEventListener('change', function () {
        var sid = filterEl.value;
        renderImagesGrid(sid
          ? smAllImages.filter(function (img) { return String(img.street_id) === sid; })
          : smAllImages
        );
      });
    }
  }

  var PLACEHOLDER_HTML = '<div class="sm-img-thumb-placeholder"><i class="fa-solid fa-image"></i></div>';

  function renderImagesGrid(images) {
    var grid = document.getElementById('smImageGrid');
    if (!grid) return;

    if (!images.length) {
      grid.innerHTML = '<p style="color:var(--slate-light);font-size:13px;padding:20px 0;grid-column:1/-1;">No images uploaded yet.</p>';
      return;
    }

    /* Build HTML — NO inline onerror (breaks in PHP-rendered page context).
       Error handling is wired via addEventListener after DOM insert. */
    grid.innerHTML = images.map(function (img) {
      var src       = imgSrc(img.file_path);
      var statusCls = 'sm-img-status status-' + (img.analysis_status || 'queued').toLowerCase();

      /* Always render an <img> if we have a src; replace with placeholder on error below */
      var thumb = src
        ? '<img class="sm-img-thumb" src="' + esc(src) + '" alt="Street image" loading="lazy">'
        : PLACEHOLDER_HTML;

      return (
        '<div class="sm-img-card" data-src="' + esc(src) + '" data-meta="' +
          esc((img.street_name || '\u2014') + ' \u00b7 ' + (img.uploaded_at || '') +
              ' \u00b7 ' + (img.analysis_status || 'Queued')) + '">' +
          thumb +
          '<div class="sm-img-info">' +
            '<span class="sm-img-street">' + esc(img.street_name || '\u2014') + '</span>' +
            '<div class="sm-img-meta">' +
              '<span>' + esc(img.uploaded_at || '\u2014') + '</span>' +
              '<span class="' + statusCls + '">' + esc(img.analysis_status || 'Queued') + '</span>' +
            '</div>' +
          '</div>' +
        '</div>'
      );
    }).join('');

    /* Safe error handler — no inline attribute quoting needed */
    grid.querySelectorAll('img.sm-img-thumb').forEach(function (imgEl) {
      imgEl.addEventListener('error', function () {
        var ph = document.createElement('div');
        ph.className = 'sm-img-thumb-placeholder';
        ph.innerHTML = '<i class="fa-solid fa-image"></i>';
        imgEl.replaceWith(ph);
      });
    });

    /* Lightbox on click — skip if image failed to load */
    grid.querySelectorAll('.sm-img-card').forEach(function (card) {
      card.addEventListener('click', function () {
        var src   = card.dataset.src;
        var imgEl = card.querySelector('img.sm-img-thumb');
        /* If img present but broken (naturalWidth=0 after load), skip lightbox */
        if (imgEl && imgEl.complete && imgEl.naturalWidth === 0) return;
        if (src) openLightbox(src, card.dataset.meta);
      });
    });
  }

  /* ══════════════════════════════════════════════════════
     6. DEMOGRAPHICS TABLE + MODAL
  ══════════════════════════════════════════════════════ */
  async function loadDemographics() {
    var tbody = document.getElementById('smDemoTbody');
    if (tbody) shimmer(tbody);

    var data = await api('demographics', { limit: 200 });
    var rows = data.data || [];
    if (tbody) unshimmer(tbody);

    if (!rows.length) {
      if (tbody) tbody.innerHTML = '<tr><td colspan="12" class="tbl-empty">No demographic data yet. Click <strong>Add Demographics</strong> to add the first survey record.</td></tr>';
      return;
    }

    makePaginator({
      rows: rows, pageSize: 10,
      tbodyId: 'smDemoTbody', footerId: 'smDemoFooter',
      infoId: 'smDemoInfo', paginId: 'smDemoPagination',
      colSpan: 12, emptyMsg: 'No demographic data.',
      renderRow: function (d) {
        var pov = d.poverty_rate_pct        != null ? parseFloat(d.poverty_rate_pct)        : null;
        var inf = d.informal_settlers_pct   != null ? parseFloat(d.informal_settlers_pct)   : null;

        var povHtml = pov !== null
          ? '<div class="sm-pct-wrap"><span class="sm-pct-num">' + pov.toFixed(1) + '%</span>' +
            '<div class="sm-pct-mini"><div class="sm-pct-fill" style="width:' + Math.min(pov,100) + '%;background:#dc2626;"></div></div></div>'
          : '\u2014';
        var infHtml = inf !== null
          ? '<div class="sm-pct-wrap"><span class="sm-pct-num">' + inf.toFixed(1) + '%</span>' +
            '<div class="sm-pct-mini"><div class="sm-pct-fill" style="width:' + Math.min(inf,100) + '%;background:#d97706;"></div></div></div>'
          : '\u2014';

        return '<tr data-row=\'' + JSON.stringify(d).replace(/\'/g, "&#39;") + '\'>' +
          '<td><div class="street-name-cell"><i class="fa-solid fa-location-dot street-pin"></i>' + esc(d.street_name) + '</div></td>' +
          '<td class="date-cell">' + esc(d.survey_date || '\u2014') + '</td>' +
          '<td style="text-align:center;">' + esc(d.fourps_households || 0) + '</td>' +
          '<td>' + povHtml + '</td>' +
          '<td style="text-align:center;">' + esc(d.pwd_count || 0) + '</td>' +
          '<td style="text-align:center;">' + esc(d.senior_count || 0) + '</td>' +
          '<td style="text-align:center;">' + esc(d.pregnant_count || 0) + '</td>' +
          '<td>' + infHtml + '</td>' +
          '<td style="text-align:center;">' + esc(d.flood_frequency != null ? d.flood_frequency + 'x' : '\u2014') + '</td>' +
          '<td style="text-align:center;">' + esc(d.avg_flood_height_m != null ? d.avg_flood_height_m + ' m' : '\u2014') + '</td>' +
          '<td><span class="sm-drain-pill ' + drainClass(d.drainage_type) + '">' + esc(d.drainage_type || 'None') + '</span></td>' +
          '<td><div class="sm-row-actions">' +
            '<button class="sm-act-btn" title="Edit demographics" data-action="edit-demo">' +
              '<i class="fa-solid fa-pen"></i>' +
            '</button>' +
            '<button class="sm-act-btn danger" title="Delete record" data-action="delete-demo">' +
              '<i class="fa-solid fa-trash"></i>' +
            '</button>' +
          '</div></td>' +
          '</tr>';
      },
      afterRender: function (tbody) {
        tbody.querySelectorAll('[data-action="edit-demo"]').forEach(function (btn) {
          btn.addEventListener('click', function () {
            var d = JSON.parse(btn.closest('tr').dataset.row);
            openDemoModal(d);
          });
        });
        tbody.querySelectorAll('[data-action="delete-demo"]').forEach(function (btn) {
          btn.addEventListener('click', function () {
            var d = JSON.parse(btn.closest('tr').dataset.row);
            showConfirm(
              'Delete the demographic record for "' + (d.street_name || 'this street') +
              '" surveyed on ' + (d.survey_date || 'unknown date') + '? This cannot be undone.',
              'Delete Demographics',
              async function () {
                await apiPost('delete_demographics', { demo_id: d.demo_id });
                logActivity('Deleted demographics record for "' + (d.street_name || 'ID ' + d.demo_id) + '"', 'System');
                loadDemographics();
              }
            );
          });
        });
      },
    });
  }

  /* ── Demographics Modal ───────────────────────────── */
  var demoForm = document.getElementById('demoForm');

  function n(id) { return document.getElementById(id); }

  function openDemoModal(d) {
    hideFeedback('ddFeedback');
    var isEdit = d && d.demo_id;
    if (n('demoModalEyebrow')) n('demoModalEyebrow').textContent = isEdit ? 'Edit Survey Record' : 'New Survey Record';
    if (n('demoModalTitle'))   n('demoModalTitle').textContent   = isEdit ? (d.street_name || 'Edit Demographics') : 'Add Demographic Indicators';

    /* Populate street dropdown from already-loaded smAllStreets */
    var sel = n('ddStreet');
    if (sel) {
      sel.innerHTML = '<option value="">Select a street…</option>';
      smAllStreets.forEach(function (s) {
        var opt = document.createElement('option');
        opt.value = s.street_id;
        opt.textContent = s.street_name + ' (Zone ' + (s.zone_name || '—') + ')';
        sel.appendChild(opt);
      });
    }

    /* Fill fields */
    if (n('ddDemoId'))       n('ddDemoId').value       = d ? (d.demo_id       || '') : '';
    if (n('ddStreet'))       n('ddStreet').value        = d ? (d.street_id     || '') : '';
    if (n('ddSurveyDate'))   n('ddSurveyDate').value    = d ? (d.survey_date_raw || d.survey_date || '') : '';
    if (n('ddDataSource'))   n('ddDataSource').value    = d ? (d.data_source   || '') : '';
    if (n('dd4Ps'))          n('dd4Ps').value           = d ? (d.fourps_households       != null ? d.fourps_households       : '') : '';
    if (n('ddPovertyRate'))  n('ddPovertyRate').value   = d ? (d.poverty_rate_pct        != null ? d.poverty_rate_pct        : '') : '';
    if (n('ddAvgIncome'))    n('ddAvgIncome').value     = d ? (d.avg_monthly_income       != null ? d.avg_monthly_income       : '') : '';
    if (n('ddInformalPct'))  n('ddInformalPct').value   = d ? (d.informal_settlers_pct   != null ? d.informal_settlers_pct   : '') : '';
    if (n('ddPwd'))          n('ddPwd').value           = d ? (d.pwd_count               != null ? d.pwd_count               : '') : '';
    if (n('ddSenior'))       n('ddSenior').value        = d ? (d.senior_count            != null ? d.senior_count            : '') : '';
    if (n('ddPregnant'))     n('ddPregnant').value      = d ? (d.pregnant_count          != null ? d.pregnant_count          : '') : '';
    if (n('ddChildren'))     n('ddChildren').value      = d ? (d.child_count             != null ? d.child_count             : '') : '';
    if (n('ddConcrete'))     n('ddConcrete').value      = d ? (d.concrete_houses_pct     != null ? d.concrete_houses_pct     : '') : '';
    if (n('ddLightMat'))     n('ddLightMat').value      = d ? (d.light_materials_pct     != null ? d.light_materials_pct     : '') : '';
    if (n('ddDrainage'))     n('ddDrainage').value      = d ? (d.drainage_type           || 'None') : 'None';
    if (n('ddRoadSurface'))  n('ddRoadSurface').value   = d ? (d.road_surface            || 'Unpaved') : 'Unpaved';
    if (n('ddStreetWidth'))  n('ddStreetWidth').value   = d ? (d.street_width_m          != null ? d.street_width_m          : '') : '';
    if (n('ddElevation'))    n('ddElevation').value     = d ? (d.elevation_m             != null ? d.elevation_m             : '') : '';
    if (n('ddDistWaterway')) n('ddDistWaterway').value  = d ? (d.dist_to_waterway_m      != null ? d.dist_to_waterway_m      : '') : '';
    if (n('ddFloodFreq'))    n('ddFloodFreq').value     = d ? (d.flood_frequency         != null ? d.flood_frequency         : '') : '';
    if (n('ddFloodHeight'))  n('ddFloodHeight').value   = d ? (d.avg_flood_height_m      != null ? d.avg_flood_height_m      : '') : '';

    openModal('demoModal');
  }

  /* Add Demographics button */
  var addDemoBtn = n('smAddDemoBtn');
  if (addDemoBtn) addDemoBtn.addEventListener('click', function () { openDemoModal(null); });

  /* demoModal close buttons */
  var ddCancel = n('ddCancel');
  if (ddCancel) ddCancel.addEventListener('click', function () { closeModal('demoModal'); });
  var demoModalClose = n('demoModalClose');
  if (demoModalClose) demoModalClose.addEventListener('click', function () { closeModal('demoModal'); });
  var demoModalEl = n('demoModal');
  if (demoModalEl) demoModalEl.addEventListener('click', function (e) { if (e.target === demoModalEl) closeModal('demoModal'); });

  /* Form submit */
  if (demoForm) {
    demoForm.addEventListener('submit', async function (e) {
      e.preventDefault();

      var streetId = n('ddStreet') ? n('ddStreet').value : '';
      var surveyDate = n('ddSurveyDate') ? n('ddSurveyDate').value : '';

      if (!streetId)   { showFeedback('ddFeedback', 'Please select a street.', 'error'); return; }
      if (!surveyDate) { showFeedback('ddFeedback', 'Survey date is required.', 'error'); return; }

      function numVal(id) {
        var v = n(id) ? n(id).value.trim() : '';
        return v === '' ? null : parseFloat(v);
      }
      function intVal(id) {
        var v = n(id) ? n(id).value.trim() : '';
        return v === '' ? null : parseInt(v, 10);
      }
      function strVal(id) {
        return n(id) ? n(id).value.trim() || null : null;
      }

      var payload = {
        demo_id:               strVal('ddDemoId'),
        street_id:             streetId,
        survey_date:           surveyDate,
        data_source:           strVal('ddDataSource'),
        fourps_households:     intVal('dd4Ps'),
        poverty_rate_pct:      numVal('ddPovertyRate'),
        avg_monthly_income:    numVal('ddAvgIncome'),
        informal_settlers_pct: numVal('ddInformalPct'),
        pwd_count:             intVal('ddPwd'),
        senior_count:          intVal('ddSenior'),
        pregnant_count:        intVal('ddPregnant'),
        child_count:           intVal('ddChildren'),
        concrete_houses_pct:   numVal('ddConcrete'),
        light_materials_pct:   numVal('ddLightMat'),
        drainage_type:         strVal('ddDrainage'),
        road_surface:          strVal('ddRoadSurface'),
        street_width_m:        numVal('ddStreetWidth'),
        elevation_m:           numVal('ddElevation'),
        dist_to_waterway_m:    numVal('ddDistWaterway'),
        flood_frequency:       intVal('ddFloodFreq'),
        avg_flood_height_m:    numVal('ddFloodHeight'),
      };

      var action = payload.demo_id ? 'update_demographics' : 'add_demographics';
      showFeedback('ddFeedback', 'Saving…', 'loading');

      try {
        await apiPost(action, payload);
        showFeedback('ddFeedback', 'Demographic data saved successfully.', 'success');
        var streetName = n('ddStreet')
            ? (n('ddStreet').selectedOptions[0]?.textContent || 'unknown street')
            : 'unknown street';
        logActivity(
            payload.demo_id
                ? 'Updated demographics for "' + streetName + '"'
                : 'Added demographics for "' + streetName + '"',
            'System'
        );
        setTimeout(function () {
          closeModal('demoModal');
          loadDemographics();
        }, 800);
      } catch (err) {
        showFeedback('ddFeedback', 'Error: ' + (err.message || 'Could not save.'), 'error');
      }
    });
  }

  /* ══════════════════════════════════════════════════════
     INIT
  ══════════════════════════════════════════════════════ */
  async function init() {
    updateTimestamp();
    initCardFadeIn();

    await Promise.allSettled([
      loadKpis()        .catch(function (e) { console.error('[KPIs]',    e); }),
      loadRiskDist()    .catch(function (e) { console.error('[Risk]',    e); }),
      loadMap()         .catch(function (e) { console.error('[Map]',     e); }),
      loadStreetsTable().catch(function (e) { console.error('[Table]',   e); }),
      loadImages()      .catch(function (e) { console.error('[Images]',  e); }),
      loadDemographics().catch(function (e) { console.error('[Demo]',    e); }),
    ]);

    setInterval(function () {
      updateTimestamp();
      loadKpis()    .catch(function () {});
      loadRiskDist().catch(function () {});
    }, 120000);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

})();