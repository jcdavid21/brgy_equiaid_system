(function () {
  'use strict';

  /* ══════════════════════════════════════════════════════
     API
  ══════════════════════════════════════════════════════ */
  var API = '../backend/admin_dashboard.php';

  async function api(action, params) {
    var qs = '?action=' + encodeURIComponent(action);
    if (params) {
      Object.keys(params).forEach(function (k) {
        qs += '&' + encodeURIComponent(k) + '=' + encodeURIComponent(params[k]);
      });
    }
    var res = await fetch(API + qs);
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
      .replace(/&/g,  '&amp;')
      .replace(/</g,  '&lt;')
      .replace(/>/g,  '&gt;')
      .replace(/"/g,  '&quot;')
      .replace(/'/g,  '&#39;');
  }

  function updateTimestamp() {
    var el = document.getElementById('lastUpdated');
    if (!el) return;
    el.textContent = new Date().toLocaleString('en-PH', {
      month: 'short', day: 'numeric', year: 'numeric',
      hour: '2-digit', minute: '2-digit'
    });
  }

  function countUp(el, target) {
    if (!el || isNaN(target)) return;
    target = Math.round(target);
    var start = null;
    function step(ts) {
      if (!start) start = ts;
      var p = Math.min((ts - start) / 800, 1);
      el.textContent = Math.floor((1 - Math.pow(2, -10 * p)) * target);
      if (p < 1) requestAnimationFrame(step);
      else el.textContent = target;
    }
    requestAnimationFrame(step);
  }

  function shimmer(el)   { if (el) el.classList.add('sk-loading'); }
  function unshimmer(el) { if (el) el.classList.remove('sk-loading'); }

  /* ══════════════════════════════════════════════════════
     MODAL
  ══════════════════════════════════════════════════════ */
  var modal        = document.getElementById('rowModal');
  var modalClose   = document.getElementById('modalClose');
  var modalEyebrow = document.getElementById('modalEyebrow');
  var modalTitle   = document.getElementById('modalTitle');
  var modalBody    = document.getElementById('modalBody');

  function openModal(eyebrow, title, html) {
    if (!modal) return;
    modalEyebrow.textContent = eyebrow || 'Details';
    modalTitle.textContent   = title   || '—';
    modalBody.innerHTML      = html    || '';
    modal.classList.add('open');
    document.body.style.overflow = 'hidden';
  }

  function closeModal() {
    if (!modal) return;
    modal.classList.remove('open');
    document.body.style.overflow = '';
  }

  if (modalClose) modalClose.addEventListener('click', closeModal);

  if (modal) {
    modal.addEventListener('click', function (e) {
      if (e.target === modal) closeModal();
    });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') closeModal();
    });
  }

  /* Build a grid of label/value pairs for the modal */
  function buildModalFields(fields) {
    var html = '<div class="modal-field-grid">';
    fields.forEach(function (f) {
      if (f.divider) {
        html += '<div class="modal-section-title">' + esc(f.label) + '</div>';
        return;
      }
      html += '<div class="modal-field' + (f.full ? ' full' : '') + '">';
      html += '<span class="modal-field-label">' + esc(f.label) + '</span>';
      html += '<span class="modal-field-value">' + (f.html || esc(f.value || '—')) + '</span>';
      html += '</div>';
    });
    html += '</div>';
    return html;
  }

  /* ══════════════════════════════════════════════════════
     PAGINATION HELPER
     Renders pagination controls and calls renderFn(page)
  ══════════════════════════════════════════════════════ */
  function makePaginator(opts) {
    /* opts: { rows, pageSize, tbodyId, footerId, infoId, paginId, renderRow, emptyMsg, colSpan } */
    var rows     = opts.rows     || [];
    var pageSize = opts.pageSize || 5;
    var total    = rows.length;
    var pages    = Math.max(1, Math.ceil(total / pageSize));
    var current  = 1;

    var tbody  = document.getElementById(opts.tbodyId);
    var footer = document.getElementById(opts.footerId);
    var infoEl = document.getElementById(opts.infoId);
    var paginEl= document.getElementById(opts.paginId);

    function renderPage(page) {
      current = Math.max(1, Math.min(page, pages));
      if (!tbody) return;

      var start = (current - 1) * pageSize;
      var slice = rows.slice(start, start + pageSize);

      if (!slice.length) {
        tbody.innerHTML = '<tr><td colspan="' + opts.colSpan + '" class="tbl-empty">' + (opts.emptyMsg || 'No data found.') + '</td></tr>';
      } else {
        tbody.innerHTML = slice.map(opts.renderRow).join('');
        initRowClick(tbody, opts.onRowClick);
      }

      /* Info text */
      if (infoEl) {
        var from = total ? start + 1 : 0;
        var to   = Math.min(start + pageSize, total);
        infoEl.innerHTML = 'Showing <strong>' + from + '–' + to + '</strong> of <strong>' + total + '</strong>';
      }

      /* Pagination buttons */
      if (paginEl) {
        var html = '';
        html += '<button class="page-btn" data-page="prev" ' + (current === 1 ? 'disabled' : '') + '><i class="fa-solid fa-chevron-left"></i></button>';

        var windowSize = 3;
        var startP = Math.max(1, current - 1);
        var endP   = Math.min(pages, startP + windowSize - 1);
        if (endP - startP < windowSize - 1) startP = Math.max(1, endP - windowSize + 1);

        if (startP > 1) html += '<button class="page-btn" data-page="1">1</button>' + (startP > 2 ? '<span class="page-btn" style="border:none;cursor:default;">…</span>' : '');

        for (var i = startP; i <= endP; i++) {
          html += '<button class="page-btn' + (i === current ? ' active' : '') + '" data-page="' + i + '">' + i + '</button>';
        }

        if (endP < pages) html += (endP < pages - 1 ? '<span class="page-btn" style="border:none;cursor:default;">…</span>' : '') + '<button class="page-btn" data-page="' + pages + '">' + pages + '</button>';

        html += '<button class="page-btn" data-page="next" ' + (current === pages ? 'disabled' : '') + '><i class="fa-solid fa-chevron-right"></i></button>';
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

    /* Show footer and render first page */
    if (footer && total > 0) footer.style.display = '';
    renderPage(1);
  }

  /* ══════════════════════════════════════════════════════
     ROW CLICK HANDLER
  ══════════════════════════════════════════════════════ */
  function initRowClick(tbody, onRowClick) {
    if (!tbody) return;
    tbody.querySelectorAll('tr').forEach(function (row) {
      row.addEventListener('click', function (e) {
        if (e.target.closest('a, button')) return;
        tbody.querySelectorAll('tr').forEach(function (r) { r.classList.remove('row-selected'); });
        row.classList.add('row-selected');
        if (onRowClick) onRowClick(row);
      });
    });
  }

  /* ══════════════════════════════════════════════════════
     1. OVERVIEW KPIs
  ══════════════════════════════════════════════════════ */
  async function loadOverview() {
    var data = await api('overview');
    var d    = data.data;
    var map  = {
      'kpi-total-streets': d.total_streets,
      'kpi-affected':      d.affected_streets,
      'kpi-welfare':       d.need_welfare,
      'kpi-resources':     d.resources,
      'kpi-reports':       d.active_reports,
      'kpi-evac':          d.evac_centers,
    };
    Object.keys(map).forEach(function (id) {
      var el = document.getElementById(id);
      if (!el) return;
      el.classList.remove('sk-inline');
      countUp(el, map[id]);
    });
  }

  /* ══════════════════════════════════════════════════════
     2. RISK DISTRIBUTION
  ══════════════════════════════════════════════════════ */
  async function loadRiskDist() {
    var wrap   = document.getElementById('riskChartWrap');
    var sumRow = document.getElementById('riskSummaryRow');
    if (wrap) shimmer(wrap);

    var data  = await api('risk_dist');
    var dist  = data.data;
    var total = Object.values(dist).reduce(function (a, b) { return a + b; }, 0) || 1;

    var meta = {
      RED:    { label: 'Critical',      cls: 'red',    dotCls: 'risk-dot-red' },
      ORANGE: { label: 'High Risk',     cls: 'orange', dotCls: 'risk-dot-orange' },
      YELLOW: { label: 'Moderate Risk', cls: 'yellow', dotCls: 'risk-dot-yellow' },
      GREEN:  { label: 'Safe',          cls: 'green',  dotCls: 'risk-dot-green' },
    };

    if (wrap) {
      wrap.innerHTML = Object.keys(meta).map(function (key) {
        var m   = meta[key];
        var cnt = dist[key] || 0;
        var pct = Math.round(cnt / total * 100);
        return (
          '<div class="risk-bar-row">' +
            '<div class="risk-bar-label-wrap">' +
              '<span class="risk-dot ' + m.dotCls + '"></span>' +
              '<span class="risk-bar-text">' + esc(m.label) + '</span>' +
            '</div>' +
            '<div class="risk-bar-track">' +
              '<div class="risk-bar-fill risk-fill-' + m.cls + '" style="--target-width:' + pct + '%;animation-play-state:paused;"></div>' +
            '</div>' +
            '<div class="risk-bar-stat">' +
              '<span class="risk-count">' + cnt + '</span>' +
              '<span class="risk-pct">' + pct + '%</span>' +
            '</div>' +
          '</div>'
        );
      }).join('');
      unshimmer(wrap);
      requestAnimationFrame(function () {
        wrap.querySelectorAll('.risk-bar-fill').forEach(function (b) { b.style.animationPlayState = 'running'; });
      });
    }

    if (sumRow) {
      var items  = sumRow.querySelectorAll('.risk-summary-item');
      var levels = Object.keys(meta);
      items.forEach(function (item, i) {
        var key = levels[i];
        if (!key) return;
        var numEl = item.querySelector('.rs-num');
        if (numEl) {
          numEl.classList.remove('sk-inline');
          numEl.className = 'rs-num rs-' + meta[key].cls;
          numEl.textContent = dist[key] || 0;
        }
      });
    }

    var legMap = { RED: 'leg-red', ORANGE: 'leg-orange', YELLOW: 'leg-yellow', GREEN: 'leg-green' };
    Object.keys(legMap).forEach(function (key) {
      var el = document.getElementById(legMap[key]);
      if (el) el.textContent = dist[key] || 0;
    });
  }

  /* ══════════════════════════════════════════════════════
     3. AI SUMMARY
  ══════════════════════════════════════════════════════ */
  async function loadAiSummary() {
    var data = await api('ai_summary');
    var d    = data.data;

    ['ai-analyzed', 'ai-high', 'ai-moderate', 'ai-safe'].forEach(function (id) {
      var map = { 'ai-analyzed': d.analyzed, 'ai-high': d.high_risk, 'ai-moderate': d.moderate, 'ai-safe': d.safe };
      var el  = document.getElementById(id);
      if (!el) return;
      el.classList.remove('sk-inline');
      countUp(el, map[id] || 0);
    });

    var conf    = d.avg_confidence || 0;
    var confPct = document.getElementById('ai-conf-pct');
    var confBar = document.getElementById('confFill');
    if (confPct) confPct.textContent = conf + '%';
    if (confBar) {
      confBar.style.setProperty('--conf', conf + '%');
      confBar.style.animationPlayState = 'running';
    }
  }

  /* ══════════════════════════════════════════════════════
     4. IMPACT SUMMARY
  ══════════════════════════════════════════════════════ */
  async function loadImpact() {
    var data   = await api('impact_summary');
    var d      = data.data;
    var before = d.before != null ? parseFloat(d.before) : null;
    var after  = d.after  != null ? parseFloat(d.after)  : null;

    var elBefore  = document.getElementById('impact-before');
    var elAfter   = document.getElementById('impact-after');
    var barBefore = document.getElementById('impactBarBefore');
    var barAfter  = document.getElementById('impactBarAfter');
    var deltaEl   = document.getElementById('impactDeltaText');

    if (elBefore) { elBefore.classList.remove('sk-inline'); elBefore.textContent = before != null ? before + '%' : '—'; }
    if (elAfter)  { elAfter.classList.remove('sk-inline');  elAfter.textContent  = after  != null ? after  + '%' : '—'; }
    if (barBefore && before != null) { barBefore.style.setProperty('--w', before + '%'); barBefore.style.animationPlayState = 'running'; }
    if (barAfter  && after  != null) { barAfter.style.setProperty('--w', after   + '%'); barAfter.style.animationPlayState  = 'running'; }

    if (deltaEl && before != null && after != null) {
      var diff = Math.round((before - after) * 100) / 100;
      deltaEl.textContent = diff > 0 ? diff + '% reduction in affected streets' : 'No change recorded yet';
    } else if (deltaEl) {
      deltaEl.textContent = 'Insufficient data for comparison';
    }
  }

  /* ══════════════════════════════════════════════════════
     5. MAP
  ══════════════════════════════════════════════════════ */
  var RISK_COLOR = { RED: '#b91c1c', ORANGE: '#d97706', YELLOW: '#ca8a04', GREEN: '#16a34a' };
  var RISK_FILL  = { RED: 0.9, ORANGE: 0.85, YELLOW: 0.8, GREEN: 0.75 };

  function buildStreetPopup(s) {
    var riskLabel = { RED:'Critical', ORANGE:'High Risk', YELLOW:'Moderate', GREEN:'Safe' }[s.risk_level] || s.risk_level;
    var impact    = s.typhoon_impact;
    var impHtml   = '';
    if (impact) {
      impHtml =
        '<div class="map-popup-divider"></div>' +
        '<div class="map-popup-row"><strong>Typhoon</strong>'     + esc(impact.typhoon_name)  + '</div>' +
        '<div class="map-popup-row"><strong>Flood</strong>'       + esc(impact.flood_status)  + '</div>' +
        '<div class="map-popup-row"><strong>Damage</strong>'      + esc(impact.damage_status) + '</div>' +
        (impact.flood_height_m ? '<div class="map-popup-row"><strong>Height</strong>' + parseFloat(impact.flood_height_m).toFixed(2) + ' m</div>' : '') +
        '<div class="map-popup-row"><strong>Affected HH</strong>' + Number(impact.affected_households).toLocaleString() + '</div>';
    }
    return (
      '<div class="map-popup">' +
        '<div class="map-popup-name">' + esc(s.street_name) + '</div>' +
        '<div class="map-popup-zone">' + esc(s.zone_name)   + '</div>' +
        '<span class="map-popup-badge ' + esc(s.risk_level) + '">' + esc(s.risk_level) + ' &mdash; ' + esc(riskLabel) + '</span>' +
        '<div class="map-popup-row"><strong>Vuln. Score</strong>'   + parseFloat(s.vuln_score).toFixed(1)         + '%</div>' +
        '<div class="map-popup-row"><strong>Population</strong>'    + Number(s.total_population).toLocaleString() + '</div>' +
        '<div class="map-popup-row"><strong>Households</strong>'    + Number(s.total_households).toLocaleString() + '</div>' +
        '<div class="map-popup-row"><strong>Needs Welfare</strong>' + esc(s.needs_welfare)                        + '</div>' +
        impHtml +
      '</div>'
    );
  }

  function buildEvacPopup(e) {
    var pct = e.capacity > 0 ? Math.round(e.current_occupancy / e.capacity * 100) : 0;
    return (
      '<div class="map-popup">' +
        '<div class="map-popup-name">' + esc(e.center_name) + '</div>' +
        '<div class="map-popup-zone">' + esc(e.zone_name)   + '</div>' +
        '<span class="map-popup-badge EVAC"><i class="fa-solid fa-person-walking-arrow-right"></i> Evacuation Center</span>' +
        '<div class="map-popup-row"><strong>Capacity</strong>'  + Number(e.capacity).toLocaleString()          + ' persons</div>' +
        '<div class="map-popup-row"><strong>Occupancy</strong>' + Number(e.current_occupancy).toLocaleString() + ' (' + pct + '%)</div>' +
        (e.address ? '<div class="map-popup-row"><strong>Address</strong>' + esc(e.address) + '</div>' : '') +
      '</div>'
    );
  }

  async function loadMap() {
    var mapEl = document.getElementById('dashboard-map');
    if (!mapEl || typeof L === 'undefined') return;

    var data    = await api('map_data');
    var streets = data.streets || [];
    var evacs   = data.evacs   || [];

    var overlay = mapEl.parentElement ? mapEl.parentElement.querySelector('.map-loading-overlay') : null;
    function hideOverlay() {
      mapEl.classList.add('map-ready');
      if (overlay) { overlay.style.opacity = '0'; overlay.style.pointerEvents = 'none'; }
    }

    if (!streets.length) { hideOverlay(); return; }

    var avgLat = streets.reduce(function (s, r) { return s + parseFloat(r.latitude);  }, 0) / streets.length;
    var avgLng = streets.reduce(function (s, r) { return s + parseFloat(r.longitude); }, 0) / streets.length;

    var map = L.map('dashboard-map', { center: [avgLat, avgLng], zoom: 16, zoomControl: true, scrollWheelZoom: false });

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
      maxZoom: 19,
    }).addTo(map);

    var bounds = [];

    streets.forEach(function (s) {
      var lat   = parseFloat(s.latitude);
      var lng   = parseFloat(s.longitude);
      var level = s.risk_level || 'GREEN';
      var marker = L.circleMarker([lat, lng], {
        radius: 10, fillColor: RISK_COLOR[level] || '#16a34a',
        color: '#fff', weight: 2, opacity: 1, fillOpacity: RISK_FILL[level] || 0.8,
      }).addTo(map);
      marker.bindPopup(buildStreetPopup(s), { maxWidth: 260, className: 'equiaid-popup' });
      if (level === 'RED') {
        marker.on('add', function () {
          var el = marker.getElement();
          if (el) el.style.animation = 'pulse-marker 1.8s infinite';
        });
      }
      bounds.push([lat, lng]);
    });

    var evacIcon = L.divIcon({
      className: '',
      html: '<div style="width:28px;height:28px;background:#1d4ed8;border:2px solid #fff;border-radius:50%;display:flex;align-items:center;justify-content:center;box-shadow:0 2px 8px rgba(0,0,0,.3);font-size:12px;color:#fff;"><i class="fa-solid fa-house-medical"></i></div>',
      iconSize: [28, 28], iconAnchor: [14, 14],
    });

    evacs.forEach(function (e) {
      var lat = parseFloat(e.latitude);
      var lng = parseFloat(e.longitude);
      L.marker([lat, lng], { icon: evacIcon }).addTo(map)
        .bindPopup(buildEvacPopup(e), { maxWidth: 240, className: 'equiaid-popup' });
      bounds.push([lat, lng]);
    });

    if (bounds.length) map.fitBounds(bounds, { padding: [28, 28] });

    var levelCount = { RED: 0, ORANGE: 0, YELLOW: 0, GREEN: 0 };
    streets.forEach(function (s) { if (levelCount[s.risk_level] !== undefined) levelCount[s.risk_level]++; });
    var legMap = { RED:'leg-red', ORANGE:'leg-orange', YELLOW:'leg-yellow', GREEN:'leg-green' };
    Object.keys(legMap).forEach(function (key) {
      var el = document.getElementById(legMap[key]);
      if (el) el.textContent = levelCount[key];
    });
    var legEvac = document.getElementById('leg-evac');
    if (legEvac) legEvac.textContent = evacs.length;

    map.on('load', hideOverlay);
    setTimeout(hideOverlay, 2000);
    setTimeout(function () { map.invalidateSize(); }, 300);
  }

  /* ══════════════════════════════════════════════════════
     6. MOST VULNERABLE STREETS — paginated + modal
  ══════════════════════════════════════════════════════ */
  async function loadTopStreets() {
    var tbody = document.getElementById('vulnerableTbody');
    if (tbody) shimmer(tbody);

    var data = await api('top_streets', { limit: 20 });
    var rows = data.data || [];

    if (tbody) unshimmer(tbody);
    if (!rows.length) {
      if (tbody) tbody.innerHTML = '<tr><td colspan="5" class="tbl-empty">No street data found.</td></tr>';
      return;
    }

    makePaginator({
      rows:     rows,
      pageSize: 8,
      tbodyId:  'vulnerableTbody',
      footerId: 'vulnerableFooter',
      infoId:   'vulnerableInfo',
      paginId:  'vulnerablePagination',
      colSpan:  5,
      emptyMsg: 'No streets found.',
      renderRow: function (row) {
        return (
          '<tr data-row=\'' + JSON.stringify(row).replace(/'/g,"&#39;") + '\'>' +
            '<td class="rank-cell">' + esc(row.rank) + '</td>' +
            '<td><div class="street-name-cell"><i class="fa-solid fa-location-dot street-pin"></i>' + esc(row.name) + '</div></td>' +
            '<td>' + esc(row.zone) + '</td>' +
            '<td>' +
              '<div class="score-wrap">' +
                '<span class="score-num">' + parseFloat(row.score).toFixed(1) + '%</span>' +
                '<div class="score-mini-bar"><div class="score-mini-fill" style="width:' + parseFloat(row.score) + '%"></div></div>' +
              '</div>' +
            '</td>' +
            '<td><span class="risk-pill risk-' + esc(row.level.toLowerCase()) + '">' + esc(row.level) + '</span></td>' +
          '</tr>'
        );
      },
      onRowClick: function (row) {
        var d = JSON.parse(row.dataset.row);
        openModal('Vulnerable Street', d.name, buildModalFields([
          { label: 'Street Name', value: d.name, full: true },
          { label: 'Zone',        value: d.zone },
          { label: 'Vuln. Score', value: parseFloat(d.score).toFixed(1) + '%' },
          { label: 'Risk Level',  html: '<span class="risk-pill risk-' + esc(d.level.toLowerCase()) + '">' + esc(d.level) + '</span>' },
          { label: 'Rank',        value: '#' + d.rank },
        ]));
      },
    });
  }

  /* ══════════════════════════════════════════════════════
     7. RECENT RESIDENT REPORTS — paginated + modal
  ══════════════════════════════════════════════════════ */
  async function loadRecentReports() {
    var tbody = document.getElementById('reportsTbody');
    if (tbody) shimmer(tbody);

    var data = await api('recent_reports', { limit: 20 });
    var rows = data.data || [];

    if (tbody) unshimmer(tbody);
    if (!rows.length) {
      if (tbody) tbody.innerHTML = '<tr><td colspan="6" class="tbl-empty">No reports found.</td></tr>';
      return;
    }

    var typeIcon = { Flood: 'fa-water', Damage: 'fa-house-crack', 'Blocked Road': 'fa-road-barrier' };

    makePaginator({
      rows:     rows,
      pageSize: 6,
      tbodyId:  'reportsTbody',
      footerId: 'reportsFooter',
      infoId:   'reportsInfo',
      paginId:  'reportsPagination',
      colSpan:  6,
      emptyMsg: 'No reports found.',
      renderRow: function (r) {
        var icon = typeIcon[r.type] || 'fa-circle-exclamation';
        return (
          '<tr data-row=\'' + JSON.stringify(r).replace(/'/g,"&#39;") + '\'>' +
            '<td><div class="user-cell">' +
              '<div class="user-avatar">' + esc((r.resident || '?').charAt(0).toUpperCase()) + '</div>' +
              '<span>' + esc(r.resident) + '</span>' +
            '</div></td>' +
            '<td>' + esc(r.street) + '</td>' +
            '<td><span class="report-type-wrap"><i class="fa-solid ' + icon + '" style="font-size:12px;color:var(--slate-mid);"></i> ' + esc(r.type) + '</span></td>' +
            '<td class="date-cell">' + esc(r.date_submitted) + '</td>' +
            '<td><span class="status-pill status-' + esc((r.status || '').toLowerCase().replace(' ', '-')) + '">' + esc(r.status) + '</span></td>' +
            '<td><a href="resident-reports.php?id=' + esc(r.report_id) + '" class="tbl-action-btn"><i class="fa-solid fa-eye"></i> View</a></td>' +
          '</tr>'
        );
      },
      onRowClick: function (row) {
        var d = JSON.parse(row.dataset.row);
        openModal('Resident Report', d.resident, buildModalFields([
          { label: 'Resident',      value: d.resident, full: true },
          { label: 'Street',        value: d.street },
          { label: 'Report Type',   value: d.type },
          { label: 'Date Submitted',value: d.date_submitted },
          { label: 'Status', html: '<span class="status-pill status-' + esc((d.status||'').toLowerCase().replace(' ','-')) + '">' + esc(d.status) + '</span>' },
        ]));
      },
    });
  }

  /* ══════════════════════════════════════════════════════
     8. WELFARE ACTIONS — paginated + modal
  ══════════════════════════════════════════════════════ */
  async function loadWelfareActions() {
    var tbody = document.getElementById('welfareTbody');
    if (tbody) shimmer(tbody);

    var data = await api('welfare_actions', { limit: 20 });
    var rows = data.data || [];

    if (tbody) unshimmer(tbody);
    if (!rows.length) {
      if (tbody) tbody.innerHTML = '<tr><td colspan="5" class="tbl-empty">No welfare actions found.</td></tr>';
      return;
    }

    makePaginator({
      rows:     rows,
      pageSize: 5,
      tbodyId:  'welfareTbody',
      footerId: 'welfareFooter',
      infoId:   'welfareInfo',
      paginId:  'welfarePagination',
      colSpan:  5,
      emptyMsg: 'No welfare actions found.',
      renderRow: function (w) {
        return (
          '<tr data-row=\'' + JSON.stringify(w).replace(/'/g,"&#39;") + '\'>' +
            '<td>' + esc(w.street) + '</td>' +
            '<td>' + esc(w.type)   + '</td>' +
            '<td class="qty-cell">' + esc(w.qty) + '</td>' +
            '<td class="date-cell">' + esc(w.date_provided) + '</td>' +
            '<td><span class="status-pill status-' + esc((w.status || '').toLowerCase()) + '">' + esc(w.status) + '</span></td>' +
          '</tr>'
        );
      },
      onRowClick: function (row) {
        var d = JSON.parse(row.dataset.row);
        openModal('Welfare Action', d.type, buildModalFields([
          { label: 'Street',          value: d.street, full: true },
          { label: 'Assistance Type', value: d.type },
          { label: 'Resources',       value: d.qty },
          { label: 'Date',            value: d.date_provided },
          { label: 'Status', html: '<span class="status-pill status-' + esc((d.status||'').toLowerCase()) + '">' + esc(d.status) + '</span>' },
        ]));
      },
    });
  }

  /* ══════════════════════════════════════════════════════
     9. DISASTERS — paginated + modal
  ══════════════════════════════════════════════════════ */
  async function loadDisasters() {
    var tbody = document.getElementById('disastersTbody');
    if (tbody) shimmer(tbody);

    var data = await api('disasters', { limit: 20 });
    var rows = data.data || [];

    if (tbody) unshimmer(tbody);
    if (!rows.length) {
      if (tbody) tbody.innerHTML = '<tr><td colspan="4" class="tbl-empty">No disaster records found.</td></tr>';
      return;
    }

    var typeIcon = { Flood: 'fa-water', 'Infrastructure Damage': 'fa-house-crack', 'Road Blockage': 'fa-road-barrier' };

    makePaginator({
      rows:     rows,
      pageSize: 5,
      tbodyId:  'disastersTbody',
      footerId: 'disastersFooter',
      infoId:   'disastersInfo',
      paginId:  'disastersPagination',
      colSpan:  4,
      emptyMsg: 'No disaster records found.',
      renderRow: function (d) {
        var icon = typeIcon[d.type] || 'fa-circle-exclamation';
        return (
          '<tr data-row=\'' + JSON.stringify(d).replace(/'/g,"&#39;") + '\'>' +
            '<td>' + esc(d.street) + '</td>' +
            '<td><span class="report-type-wrap"><i class="fa-solid ' + icon + '" style="font-size:12px;color:var(--slate-mid);"></i> ' + esc(d.type) + '</span></td>' +
            '<td><span class="severity-pill severity-' + esc((d.severity || '').toLowerCase()) + '">' + esc(d.severity) + '</span></td>' +
            '<td class="date-cell">' + esc(d.date_recorded) + '</td>' +
          '</tr>'
        );
      },
      onRowClick: function (row) {
        var d = JSON.parse(row.dataset.row);
        openModal('Disaster Record', d.street, buildModalFields([
          { label: 'Street',        value: d.street, full: true },
          { label: 'Disaster Type', value: d.type },
          { label: 'Date',          value: d.date_recorded },
          { label: 'Severity', html: '<span class="severity-pill severity-' + esc((d.severity||'').toLowerCase()) + '">' + esc(d.severity) + '</span>' },
        ]));
      },
    });
  }

  /* ══════════════════════════════════════════════════════
     CARD FADE-IN ON SCROLL
  ══════════════════════════════════════════════════════ */
  function initCardFadeIn() {
    if (!('IntersectionObserver' in window)) return;
    var cards = document.querySelectorAll('.dash-card, .kpi-card');
    cards.forEach(function (card) {
      card.style.opacity   = '0';
      card.style.transform = 'translateY(12px)';
      card.style.transition= 'opacity 0.4s ease, transform 0.4s ease';
    });
    var obs = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (!entry.isIntersecting) return;
        var siblings = Array.from(entry.target.parentElement ? entry.target.parentElement.children : []);
        var delay    = Math.min(siblings.indexOf(entry.target) * 55, 220);
        setTimeout(function () {
          entry.target.style.opacity   = '1';
          entry.target.style.transform = 'translateY(0)';
        }, delay);
        obs.unobserve(entry.target);
      });
    }, { threshold: 0.04 });
    cards.forEach(function (card) { obs.observe(card); });
  }

  /* ══════════════════════════════════════════════════════
     INIT
  ══════════════════════════════════════════════════════ */
  async function init() {
    updateTimestamp();
    initCardFadeIn();

    await Promise.allSettled([
      loadOverview()       .catch(function (e) { console.error('[Overview]',   e); }),
      loadRiskDist()       .catch(function (e) { console.error('[RiskDist]',   e); }),
      loadAiSummary()      .catch(function (e) { console.error('[AiSummary]',  e); }),
      loadImpact()         .catch(function (e) { console.error('[Impact]',     e); }),
      loadMap()            .catch(function (e) { console.error('[Map]',        e); }),
      loadTopStreets()     .catch(function (e) { console.error('[Streets]',    e); }),
      loadRecentReports()  .catch(function (e) { console.error('[Reports]',    e); }),
      loadWelfareActions() .catch(function (e) { console.error('[Welfare]',    e); }),
      loadDisasters()      .catch(function (e) { console.error('[Disasters]',  e); }),
    ]);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

})();