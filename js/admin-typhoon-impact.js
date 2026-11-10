(function () {
  'use strict';

  /* ══════════════════════════════════════════════════════
     CONFIG
  ══════════════════════════════════════════════════════ */
  var API = '../backend/admin-typhoon-impact.php';

  /* ══════════════════════════════════════════════════════
     API HELPER
  ══════════════════════════════════════════════════════ */
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

  function fmtPeso(n) {
    if (n == null || isNaN(n)) return '—';
    return '₱' + Number(n).toLocaleString('en-PH', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
  }

  function fmtDate(d) {
    if (!d) return '—';
    return new Date(d).toLocaleString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' });
  }

  function fmtDateTime(d) {
    if (!d) return '—';
    return new Date(d).toLocaleString('en-PH', {
      month: 'short', day: 'numeric', year: 'numeric',
      hour: '2-digit', minute: '2-digit',
    });
  }

  /* Impact level → color */
  function impactColor(level) {
    return { Severe: '#dc2626', High: '#d97706', Moderate: '#ca8a04', None: '#16a34a' }[level] || '#9ca3af';
  }

  /* Impact level → badge */
  function impactBadge(level) {
    var cls = { Severe: 'severe', High: 'high', Moderate: 'moderate', None: 'none' }[level] || 'none';
    var icons = { Severe: 'fa-circle-exclamation', High: 'fa-triangle-exclamation', Moderate: 'fa-circle-half-stroke', None: 'fa-circle-check' };
    return '<span class="ti-impact-badge ti-impact-' + cls + '">'
         + '<i class="fa-solid ' + (icons[level] || 'fa-circle') + '"></i>'
         + esc(level || 'None')
         + '</span>';
  }

  /* Welfare badge */
  function welfareBadge(status) {
    var map = {
      'Needs Help':          ['needs',    'fa-circle-exclamation'],
      'Ongoing Assistance':  ['ongoing',  'fa-rotate'],
      'Resolved':            ['resolved', 'fa-circle-check'],
      'No Impact':           ['none',     'fa-minus'],
    };
    var info = map[status] || ['none', 'fa-minus'];
    return '<span class="ti-welfare-badge ti-welfare-' + info[0] + '">'
         + '<i class="fa-solid ' + info[1] + '"></i>'
         + esc(status || 'Unknown')
         + '</span>';
  }

  /* Risk badge */
  function riskBadge(risk) {
    return '<span class="risk-' + esc(risk || 'GREEN') + '">'
         + '<i class="fa-solid fa-circle" style="font-size:6px;"></i>'
         + esc(risk || 'N/A')
         + '</span>';
  }

  /* Flood badge */
  function floodBadge(status) {
    var cls = status === 'Severely Flooded' ? 'severe'
            : status === 'Flooded'           ? 'flooded'
            : 'none';
    var icons = { 'Severely Flooded': 'fa-house-flood-water', 'Flooded': 'fa-water', 'None': 'fa-droplet-slash' };
    var icon = icons[status] || 'fa-droplet-slash';
    return '<span class="ti-flood-badge ti-flood-' + cls + '">'
         + '<i class="fa-solid ' + icon + '"></i> '
         + esc(status || 'None') + '</span>';
  }

  function el(id) { return document.getElementById(id); }

  /* ══════════════════════════════════════════════════════
     STATE
  ══════════════════════════════════════════════════════ */
  var tiMap        = null;
  var tiMarkers    = [];
  var tiLayer      = 'impact';
  var allImpacts   = [];
  var currentEvent = null;
  var currentEventId = null;
  var modalScrollY = 0;

  /* Filter state */
  var filterImpact = '';
  var filterStatus = '';
  var filterZone   = '';
  var searchQuery  = '';

  /* Pagination */
  var PAGE_SIZE = 10;
  var currentPage = 1;

  /* ══════════════════════════════════════════════════════
     MAP INIT
  ══════════════════════════════════════════════════════ */
  function initMap() {
    if (tiMap || typeof L === 'undefined') return;

    tiMap = L.map('ti-map', {
      center: [14.7435, 120.9842],
      zoom: 15,
      zoomControl: true,
      scrollWheelZoom: true,
    });

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      attribution: '&copy; <a href="https://openstreetmap.org/copyright">OpenStreetMap</a>',
      maxZoom: 19,
    }).addTo(tiMap);
  }

  function clearMarkers() {
    tiMarkers.forEach(function (m) {
      if (tiMap) tiMap.removeLayer(m.marker);
    });
    tiMarkers = [];
  }

  function evacIcon() {
    return L.divIcon({
      className: '',
      html: '<div style="width:24px;height:24px;background:#1d4ed8;border:2.5px solid #fff;'
          + 'border-radius:6px;display:flex;align-items:center;justify-content:center;'
          + 'box-shadow:0 2px 8px rgba(0,0,0,.35);">'
          + '<i class="fa-solid fa-house-chimney" style="color:#fff;font-size:11px;"></i>'
          + '</div>',
      iconSize:   [24, 24],
      iconAnchor: [12, 12],
    });
  }

  var mapLoadGeneration = 0;
  async function loadMapData(eventId) {
    var generation = ++mapLoadGeneration;
    var overlay = el('tiMapOverlay');
    if (overlay) overlay.style.display = '';
    clearMarkers();

    try {
      var data    = await api('map_data', { event_id: eventId });
      if (generation !== mapLoadGeneration || Number(eventId) !== Number(currentEventId)) return;
      var streets = data.streets || [];
      var evacs   = data.evac   || [];

      streets.forEach(function (s) {
        var lat = parseFloat(s.latitude);
        var lng = parseFloat(s.longitude);
        if (!lat || !lng) return;

        var color  = getMarkerColor(s, tiLayer);
        var radius = s.is_affected ? 11 : 8;
        var marker = L.circleMarker([lat, lng], {
          radius:      radius,
          fillColor:   color,
          color:       '#fff',
          weight:      s.is_affected ? 2.5 : 1.5,
          fillOpacity: s.is_affected ? 0.92 : 0.5,
        });

        marker.bindPopup(buildPopup(s), {
          className:  'ti-popup',
          maxWidth:   260,
          minWidth:   220,
        });

        marker.addTo(tiMap);
        tiMarkers.push({ marker: marker, street: s });
      });

      evacs.forEach(function (e) {
        var lat = parseFloat(e.latitude);
        var lng = parseFloat(e.longitude);
        if (!lat || !lng) return;

        var m = L.marker([lat, lng], { icon: evacIcon() });
        m.bindPopup(
          '<div class="ti-map-popup">'
        + '<div class="ti-popup-name"><i class="fa-solid fa-house-chimney" style="color:#1d4ed8;margin-right:5px;"></i>' + esc(e.center_name) + '</div>'
        + '<div class="ti-popup-zone">' + esc(e.zone_name || '—') + '</div>'
        + '<div class="ti-popup-row"><span>Capacity</span><strong>' + esc(e.capacity) + ' persons</strong></div>'
        + '<div class="ti-popup-row"><span>Occupancy</span><strong>' + esc(e.current_occupancy) + '</strong></div>'
        + (e.contact_number ? '<div class="ti-popup-row"><span>Contact</span><strong>' + esc(e.contact_number) + '</strong></div>' : '')
        + '</div>',
          { className: 'ti-popup', maxWidth: 240 }
        );
        m.addTo(tiMap);
        tiMarkers.push({ marker: m, street: null, isEvac: true });
      });

      var pts = tiMarkers
        .filter(function (m) { return !m.isEvac; })
        .map(function (m) { return m.marker.getLatLng(); });
      if (pts.length) tiMap.fitBounds(L.latLngBounds(pts), { padding: [40, 40] });

    } catch (err) {
      console.error('Map load error:', err);
    } finally {
      if (overlay && generation === mapLoadGeneration && Number(eventId) === Number(currentEventId)) overlay.style.display = 'none';
    }
  }

  function getMarkerColor(street, layer) {
    if (layer === 'risk') {
      return { RED: '#dc2626', ORANGE: '#d97706', YELLOW: '#ca8a04', GREEN: '#16a34a' }[street.risk_level] || '#9ca3af';
    }
    if (layer === 'welfare') {
      return { Yes: '#dc2626', Moderate: '#d97706', No: '#16a34a' }[street.needs_welfare] || '#9ca3af';
    }
    if (!street.is_affected) return '#d1d5db';
    return impactColor(street.impact_level);
  }

  function updateMarkerColors() {
    tiMarkers.forEach(function (m) {
      if (m.isEvac || !m.street) return;
      var s = m.street;
      var color  = getMarkerColor(s, tiLayer);
      var radius = s.is_affected ? 11 : 8;
      m.marker.setStyle({
        fillColor:   color,
        radius:      radius,
        fillOpacity: s.is_affected ? 0.92 : 0.5,
        weight:      s.is_affected ? 2.5 : 1.5,
      });
    });
    renderLegend();
  }

  function buildPopup(s) {
    var html = '<div class="ti-map-popup">'
      + '<div class="ti-popup-name">' + esc(s.street_name) + '</div>'
      + '<div class="ti-popup-zone"><i class="fa-solid fa-location-dot" style="color:#9ca3af;margin-right:3px;"></i>' + esc(s.zone_name || '—') + '</div>'
      + '<div class="ti-popup-badges">';

    if (s.is_affected) {
      html += impactBadge(s.impact_level);
    }
    html += riskBadge(s.risk_level);
    html += '</div>';

    if (s.is_affected) {
      html += '<div class="ti-popup-divider"></div>'
        + '<div class="ti-popup-row"><span><i class="fa-solid fa-water" style="width:14px;"></i> Flood</span><strong>' + esc(s.flood_status) + '</strong></div>'
        + '<div class="ti-popup-row"><span><i class="fa-solid fa-hand-holding-heart" style="width:14px;"></i> Response</span><strong>' + esc(s.welfare_status) + '</strong></div>';
    } else {
      html += '<div class="ti-popup-divider"></div>'
        + '<div class="ti-popup-row"><span>Status</span><strong>No recorded impact</strong></div>';
    }

    html += '</div>';
    return html;
  }

  window.__tiOpenDetail = function (streetId) {
    openDetailModal(streetId, currentEventId);
  };

  /* ══════════════════════════════════════════════════════
     LEGEND
  ══════════════════════════════════════════════════════ */
  function renderLegend() {
    var wrap = el('tiMapLegend');
    if (!wrap) return;

    var items;
    if (tiLayer === 'impact') {
      items = [
        { color: '#dc2626', label: 'Severe',      count: countImpact('Severe')   },
        { color: '#d97706', label: 'High',        count: countImpact('High')     },
        { color: '#ca8a04', label: 'Moderate',    count: countImpact('Moderate') },
        { color: '#16a34a', label: 'None',        count: countImpact('None')     },
        { color: '#d1d5db', label: 'No Record',   count: null                    },
        { color: '#1d4ed8', label: 'Evac Center', count: null, square: true      },
      ];
    } else if (tiLayer === 'risk') {
      items = [
        { color: '#dc2626', label: 'Critical (RED)'    },
        { color: '#d97706', label: 'High Risk (ORANGE)' },
        { color: '#ca8a04', label: 'Moderate (YELLOW)'  },
        { color: '#16a34a', label: 'Safe (GREEN)'       },
        { color: '#1d4ed8', label: 'Evac Center', square: true },
      ];
    } else {
      items = [
        { color: '#dc2626', label: 'Needs Welfare'  },
        { color: '#d97706', label: 'Moderate'        },
        { color: '#16a34a', label: 'No Welfare'      },
        { color: '#1d4ed8', label: 'Evac Center', square: true },
      ];
    }

    wrap.innerHTML = items.map(function (i) {
      var dotStyle = i.square
        ? 'width:11px;height:11px;background:' + i.color + ';border-radius:3px;'
        : 'width:9px;height:9px;background:' + i.color + ';border-radius:50%;';
      return '<div class="map-legend-item">'
        + '<span class="map-legend-dot" style="' + dotStyle + '"></span> '
        + esc(i.label)
        + (i.count != null ? ' <span class="map-legend-count">' + i.count + '</span>' : '')
        + '</div>';
    }).join('');
  }

  function countImpact(level) {
    if (!allImpacts.length) return 0;
    if (level === 'None') return allImpacts.filter(function (s) { return !s.is_affected || s.impact_level === 'None'; }).length;
    return allImpacts.filter(function (s) { return s.impact_level === level; }).length;
  }

  /* ══════════════════════════════════════════════════════
     EVENT LOADING
  ══════════════════════════════════════════════════════ */
  async function loadEvents() {
    try {
      var data = await api('events');
      var events = data.events || [];
      var sel = el('tiEventSelect');
      if (!sel) return;

      if (!events.length) {
        sel.innerHTML = '<option value="">Import a typhoon CSV to begin</option>';
        sel.disabled = true;
        currentEvent = null;
        currentEventId = null;
        allImpacts = [];
        clearMarkers();
        renderKPIs({});
        renderTable();
        ['tiEventBanner', 'tiStatusPill', 'tiMapOverlay'].forEach(function (id) {
          var node = el(id);
          if (node) node.style.display = 'none';
        });
        var notice = el('tiImpactDataNotice');
        if (notice) {
          notice.textContent = 'No typhoon events yet. Use Import PAGASA Data to upload the reviewed CSV dataset.';
          notice.style.display = '';
        }
        ['tiImpactBars', 'tiRiskBreakdownBars', 'tiWelfareStatus'].forEach(function (id) {
          var node = el(id);
          if (node) node.textContent = 'No event selected.';
        });
        renderLegend();
        return;
      }

      sel.disabled = false;

      sel.innerHTML = events.map(function (e) {
        var showLocal = e.local_name && String(e.event_name).toUpperCase().indexOf(String(e.local_name).toUpperCase()) === -1;
        var label = e.event_name + (showLocal ? ' (' + e.local_name + ')' : '') + (e.date_started ? ' (' + String(e.date_started).slice(0, 4) + ')' : '');
        return '<option value="' + e.event_id + '" data-status="' + esc(e.status) + '">'
          + esc(label) + '</option>';
      }).join('');

      /* ── Smart auto-select: prefer Active event, else first with data ── */
      /* Try to pick the Active event first, then fall back to first event  */
      var requestedId = Number(new URLSearchParams(window.location.search).get('event_id'));
      var requestedEvent = events.find(function (e) { return Number(e.event_id) === requestedId; });
      var activeEvent = events.find(function (e) { return e.status === 'Active'; });
      var defaultEvent = requestedEvent || activeEvent || events[0];
      sel.value = defaultEvent.event_id;

      /* Keep the dashboard tied to the selected event, including empty events. */
      loadEventData(defaultEvent.event_id);

    } catch (err) {
      console.error('loadEvents error:', err);
      var sel = el('tiEventSelect');
      if (sel) sel.innerHTML = '<option value="">Failed to load events</option>';
    }
  }

  var eventLoadGeneration = 0;
  async function loadEventData(eventId) {
    if (!eventId) return;
    var generation = ++eventLoadGeneration;
    currentEventId = eventId;

    /* Show loading states on KPI cards */
    ['kpi-ti-streets','kpi-ti-flooded','kpi-ti-damaged','kpi-ti-households','kpi-ti-persons','kpi-ti-roads'].forEach(function (id) {
      var e = el(id);
      if (e) { e.textContent = '0'; e.style.opacity = '0.4'; }
    });

    /* Reset impact bars to skeleton */
    var impactBarsWrap = el('tiImpactBars');
    if (impactBarsWrap) {
      impactBarsWrap.innerHTML = '<div class="sk-bar-row"></div><div class="sk-bar-row"></div><div class="sk-bar-row"></div><div class="sk-bar-row"></div>';
    }

    try {
      var [summaryData, impactData] = await Promise.all([
        api('summary',     { event_id: eventId }),
        api('impact_list', { event_id: eventId }),
      ]);

      if (generation !== eventLoadGeneration) return;
      currentEvent = summaryData.event;
      allImpacts   = impactData.impacts || [];

      renderEventBanner(summaryData.event, summaryData.summary);
      renderKPIs(summaryData.summary);
      renderImpactBars();
      renderRiskBreakdown(summaryData.summary.risk_breakdown);
      renderWelfareStatus(allImpacts);
      renderBudget(summaryData.summary.budget);
      renderTable();
      populateZoneFilter(allImpacts);

      loadMapData(eventId);
      renderLegend();

    } catch (err) {
      if (generation !== eventLoadGeneration) return;
      console.error('loadEventData error:', err);
      // REPLACE WITH:
      ['kpi-ti-streets','kpi-ti-flooded','kpi-ti-damaged','kpi-ti-households','kpi-ti-persons','kpi-ti-roads'].forEach(function (id) {
        var e = el(id);
        if (e) { e.textContent = '0'; e.classList.remove('sk-loading'); e.style.opacity = ''; }
      });
    }
  }

  /* ══════════════════════════════════════════════════════
     RENDER: BANNER
  ══════════════════════════════════════════════════════ */
  function renderEventBanner(event, summary) {
    var banner = el('tiEventBanner');
    if (!banner) return;
    banner.style.display = '';

    var nameEl = el('tiBannerName');
    if (nameEl) {
      var statusBadge = event.status === 'Active'
        ? '<span class="ti-status-active"><i class="fa-solid fa-circle-dot"></i> Active</span>'
        : '<span class="ti-status-passed"><i class="fa-solid fa-check"></i> ' + esc(event.status) + '</span>';
      nameEl.innerHTML = esc(event.event_name)
        + (event.local_name && String(event.event_name).toUpperCase().indexOf(String(event.local_name).toUpperCase()) === -1
          ? ' <span style="font-weight:400;opacity:.7;">(' + esc(event.local_name) + ')</span>' : '')
        + ' &nbsp;' + statusBadge;
    }

    var metaEl = el('tiBannerMeta');
    if (metaEl) {
      var parts = [];
      if (event.imported_classification) parts.push('<i class="fa-solid fa-wind" style="margin-right:3px;"></i>' + esc(event.imported_classification));
      else if (event.category)  parts.push('<i class="fa-solid fa-wind" style="margin-right:3px;"></i>Category ' + event.category);
      if (event.wind_speed_kph) parts.push('<i class="fa-solid fa-gauge-high" style="margin-right:3px;"></i>' + event.wind_speed_kph + ' kph');
      if (event.date_started)   parts.push('<i class="fa-solid fa-calendar-plus" style="margin-right:3px;"></i>' + fmtDate(event.date_started));
      if (event.date_ended)     parts.push('<i class="fa-solid fa-calendar-check" style="margin-right:3px;"></i>' + fmtDate(event.date_ended));
      if (event.pagasa_import_id) {
        var revisionLabel = Number(event.report_revision || 0) > 0 ? 'Rev. ' + Number(event.report_revision) : 'Original';
        parts.push('<a href="' + API + '?action=view_pagasa_report&amp;import_id=' + Number(event.pagasa_import_id) + '" target="_blank" rel="noopener" style="color:inherit;font-weight:600;"><i class="fa-solid fa-file-pdf" style="margin-right:3px;"></i>PAGASA ' + esc(revisionLabel) + '</a>');
      }
      metaEl.innerHTML = parts.join(' &nbsp;·&nbsp; ');
    }

    var statsEl = el('tiBannerStats');
    if (statsEl) {
      statsEl.innerHTML = [
        { num: summary.impacted_streets, label: 'Streets Affected', icon: 'fa-road' },
        { num: summary.total_households, label: 'Households',       icon: 'fa-house' },
        { num: summary.total_persons,    label: 'Persons',          icon: 'fa-person' },
        { num: summary.flooded_streets,  label: 'Flooded',          icon: 'fa-water' },
      ].map(function (s) {
        return '<div class="ti-banner-stat">'
          + '<div class="ti-banner-stat-num">' + (allImpacts.length === 0 ? '—' : Number(s.num || 0).toLocaleString('en-PH')) + '</div>'
          + '<div class="ti-banner-stat-label"><i class="fa-solid ' + s.icon + '" style="margin-right:3px;opacity:.6;"></i>' + esc(s.label) + '</div>'
          + '</div>';
      }).join('');
    }

    var pill = el('tiStatusPill');
    var pillText = el('tiStatusText');
    if (pill && pillText) {
      pill.style.display = '';
      pillText.textContent = event.status || 'Active';
      var dot = pill.querySelector('.live-dot');
      if (dot) dot.style.background = event.status === 'Active' ? '#dc2626' : '#16a34a';
    }

    var notice = el('tiImpactDataNotice');
    if (notice) {
      var demo = Number(event.is_demo) === 1;
      notice.style.display = demo || allImpacts.length === 0 ? '' : 'none';
      notice.classList.toggle('ti-demo-notice', demo);
      notice.textContent = demo ? 'DEMO / SYNTHETIC DATA: This scenario and its street impacts are fictitious and are for system demonstrations only. They are not official Bagong Silang disaster reports.' : 'Typhoon details are available. Street-level impact assessments for Bagong Silang have not been supplied, so flood, damage, and affected-household totals are not reported.';
    }
  }

  /* ══════════════════════════════════════════════════════
     RENDER: KPIs
  ══════════════════════════════════════════════════════ */
  function renderKPIs(summary) {
    var map = {
      'kpi-ti-streets':    summary.impacted_streets    || 0,
      'kpi-ti-flooded':    summary.flooded_streets      || 0,
      'kpi-ti-damaged':    summary.damaged_streets      || 0,
      'kpi-ti-households': summary.total_households     || 0,
      'kpi-ti-persons':    summary.total_persons        || 0,
      'kpi-ti-roads':      summary.inaccessible_streets || 0,
    };
    Object.keys(map).forEach(function (id) {
      var e = el(id);
      if (!e) return;
      e.classList.remove('sk-loading');
      e.style.opacity = '';
      e.textContent = allImpacts.length === 0 ? '—' : Number(map[id]).toLocaleString('en-PH');
    });
  }

  /* ══════════════════════════════════════════════════════
     RENDER: IMPACT BARS
     FIX: uses allImpacts (already populated) instead of summary param
  ══════════════════════════════════════════════════════ */
  function renderImpactBars() {
    var wrap = el('tiImpactBars');
    if (!wrap) return;
    if (!allImpacts.length) {
      wrap.textContent = 'No street-impact assessments supplied.';
      return;
    }

    var levels = [
      { label: 'Severe',   color: '#dc2626', icon: 'fa-circle-exclamation' },
      { label: 'High',     color: '#d97706', icon: 'fa-triangle-exclamation' },
      { label: 'Moderate', color: '#ca8a04', icon: 'fa-circle-half-stroke' },
      { label: 'None',     color: '#16a34a', icon: 'fa-circle-check' },
    ];

    var counts = {};
    levels.forEach(function (l) { counts[l.label] = 0; });
    allImpacts.forEach(function (s) {
      if (counts[s.impact_level] !== undefined) counts[s.impact_level]++;
    });

    var total = allImpacts.length || 1;

    wrap.innerHTML = levels.map(function (item) {
      var count = counts[item.label];
      var pct   = Math.round((count / total) * 100);
      return '<div class="ti-impact-bar-row risk-bar-row">'
        + '<div class="ti-impact-bar-label-wrap risk-bar-label-wrap">'
        + '<i class="fa-solid ' + item.icon + '" style="color:' + item.color + ';font-size:11px;width:14px;text-align:center;"></i>'
        + '<span class="ti-bar-text risk-bar-text">' + esc(item.label) + '</span>'
        + '</div>'
        + '<div class="risk-bar-track"><div class="risk-bar-fill" style="background:' + item.color + ';--target-width:' + pct + '%"></div></div>'
        + '<div class="risk-bar-stat"><span class="risk-count">' + count + '</span><span class="risk-pct">' + pct + '%</span></div>'
        + '</div>';
    }).join('');
  }

  /* ══════════════════════════════════════════════════════
     RENDER: RISK BREAKDOWN
  ══════════════════════════════════════════════════════ */
  function renderRiskBreakdown(breakdown) {
    var wrap = el('tiRiskBreakdownBars');
    if (!wrap) return;
    if (!allImpacts.length) {
      wrap.textContent = 'No affected-street assessments available.';
      return;
    }

    var levels = [
      { key: 'RED',    color: '#dc2626', label: 'RED',    icon: 'fa-circle-exclamation' },
      { key: 'ORANGE', color: '#d97706', label: 'ORANGE', icon: 'fa-triangle-exclamation' },
      { key: 'YELLOW', color: '#ca8a04', label: 'YELLOW', icon: 'fa-circle-half-stroke' },
      { key: 'GREEN',  color: '#16a34a', label: 'GREEN',  icon: 'fa-circle-check' },
    ];

    var total = Object.values(breakdown).reduce(function (a, b) { return a + b; }, 0) || 1;

    wrap.innerHTML = levels.map(function (lvl) {
      var count = breakdown[lvl.key] || 0;
      var pct   = Math.round((count / total) * 100);
      return '<div class="risk-bar-row">'
        + '<div class="risk-bar-label-wrap">'
        + '<i class="fa-solid ' + lvl.icon + '" style="color:' + lvl.color + ';font-size:11px;width:14px;text-align:center;"></i>'
        + '<span class="risk-bar-text">' + lvl.label + '</span>'
        + '</div>'
        + '<div class="risk-bar-track"><div class="risk-bar-fill" style="background:' + lvl.color + ';--target-width:' + pct + '%"></div></div>'
        + '<div class="risk-bar-stat"><span class="risk-count">' + count + '</span><span class="risk-pct">' + pct + '%</span></div>'
        + '</div>';
    }).join('');
  }

  /* ══════════════════════════════════════════════════════
     RENDER: WELFARE STATUS SUMMARY
  ══════════════════════════════════════════════════════ */
  function renderWelfareStatus(impacts) {
    var wrap = el('tiWelfareStatus');
    if (!wrap) return;
    if (!impacts.length) {
      wrap.textContent = 'Response summary unavailable without street-impact assessments.';
      return;
    }

    var statuses = [
      { label: 'Needs Help',         icon: 'fa-circle-exclamation', color: '#dc2626' },
      { label: 'Ongoing Assistance', icon: 'fa-rotate',             color: '#d97706' },
      { label: 'Resolved',           icon: 'fa-circle-check',       color: '#16a34a' },
    ];

    var counts = {};
    statuses.forEach(function (s) { counts[s.label] = 0; });
    impacts.forEach(function (s) {
      if (s.welfare_status && counts[s.welfare_status] !== undefined) {
        counts[s.welfare_status]++;
      }
    });

    wrap.innerHTML = statuses.map(function (s) {
      return '<div class="ti-status-pill-row">'
        + '<span class="ti-status-pill-label">'
        + '<i class="fa-solid ' + s.icon + '" style="color:' + s.color + ';margin-right:5px;font-size:10px;"></i>'
        + esc(s.label)
        + '</span>'
        + '<span class="ti-status-pill-count">' + (counts[s.label] || 0) + '</span>'
        + '</div>';
    }).join('');
  }

  /* ══════════════════════════════════════════════════════
     RENDER: BUDGET
  ══════════════════════════════════════════════════════ */
  function renderBudget(budget) {
    var ids = {
      'tiBudgetRec':      budget ? (budget.recommended || 0) : 0,
      'tiBudgetApproved': budget ? (budget.approved    || 0) : 0,
      'tiBudgetSpent':    budget ? (budget.spent        || 0) : 0,
    };
    Object.keys(ids).forEach(function (id) {
      var e = el(id);
      if (e) {
        e.classList.remove('sk-loading');
        e.textContent = fmtPeso(ids[id]);
      }
    });
  }

  /* ══════════════════════════════════════════════════════
     RENDER: TABLE — fully redesigned with Font Awesome icons
  ══════════════════════════════════════════════════════ */
  function getFilteredRows() {
    return allImpacts.filter(function (s) {
      if (filterImpact && s.impact_level !== filterImpact) return false;
      if (filterStatus && s.welfare_status !== filterStatus) return false;
      if (filterZone   && s.zone_name     !== filterZone)   return false;
      if (searchQuery) {
        var q = searchQuery.toLowerCase();
        if (!s.street_name.toLowerCase().includes(q) && !(s.zone_name || '').toLowerCase().includes(q)) return false;
      }
      return true;
    });
  }

  function renderTable() {
    var tbody = el('tiTableTbody');
    if (!tbody) return;

    var rows = getFilteredRows();
    var total = rows.length;
    var start = (currentPage - 1) * PAGE_SIZE;
    var pageRows = rows.slice(start, start + PAGE_SIZE);

    if (!total) {
      var noDataMsg = allImpacts.length === 0
        ? '<div class="ti-empty">'
          + '<div class="ti-empty-icon"><i class="fa-solid fa-cloud-sun"></i></div>'
          + '<div class="ti-empty-text">' + (currentEvent ? 'No impact records for this event.' : 'Ready for CSV import.') + '</div>'
          + '<div class="ti-empty-sub">' + (currentEvent ? 'Verified local assessments have not been supplied. Weather details are available above.' : 'Import the reviewed PAGASA CSV to load actual typhoon records.') + '</div>'
          + '</div>'
        : '<div class="ti-empty">'
          + '<div class="ti-empty-icon"><i class="fa-solid fa-magnifying-glass"></i></div>'
          + '<div class="ti-empty-text">No records match your filters.</div>'
          + '<div class="ti-empty-sub">Try adjusting your filters or clearing the search.</div>'
          + '</div>';
      tbody.innerHTML = '<tr><td colspan="11">' + noDataMsg + '</td></tr>';
      var footer = el('tiTableFooter');
      if (footer) footer.style.display = 'none';
      return;
    }

    tbody.innerHTML = pageRows.map(function (s, idx) {
      var rowClass = 'ti-row-' + (s.impact_level || 'none').toLowerCase();

      /* Road access icon */
      var roadHtml = s.road_accessible
        ? '<span class="ti-road-chip ti-road-ok"><i class="fa-solid fa-circle-check"></i> Open</span>'
        : '<span class="ti-road-chip ti-road-blocked"><i class="fa-solid fa-road-barrier"></i> Blocked</span>';

      /* Flood height */
      var heightHtml = s.flood_height_m != null
        ? '<span class="ti-height-val"><i class="fa-solid fa-ruler-vertical" style="font-size:9px;margin-right:2px;color:var(--slate-mid);"></i>' + s.flood_height_m + ' m</span>'
        : '<span style="color:var(--slate-light);font-size:12px;">—</span>';

      /* Vuln score mini badge */
      var vulnColor = s.vuln_score >= 70 ? '#dc2626' : s.vuln_score >= 45 ? '#d97706' : s.vuln_score >= 25 ? '#ca8a04' : '#16a34a';

      return '<tr class="' + rowClass + '">'
        /* # */
        + '<td class="rank-cell"><span class="ti-row-num">' + (start + idx + 1) + '</span></td>'

        /* Street */
        + '<td>'
        + '<div class="ti-street-cell">'
        + '<div class="ti-street-name">' + esc(s.street_name) + '</div>'
        + '<div class="ti-street-meta">'
        + '<i class="fa-solid fa-location-dot" style="font-size:9px;color:var(--slate-light);margin-right:2px;"></i>'
        + esc(s.zone_name)
        + '</div>'
        + '</div>'
        + '</td>'

        /* Impact Level */
        + '<td>' + impactBadge(s.impact_level) + '</td>'

        /* Flood Status */
        + '<td>' + floodBadge(s.flood_status) + '</td>'

        /* Damage */
        + '<td>'
        + '<span class="ti-damage-text">'
        + (s.damage_status && s.damage_status !== 'None'
            ? '<i class="fa-solid fa-house-chimney-crack" style="color:#9ca3af;margin-right:4px;font-size:10px;"></i>' + esc(s.damage_status)
            : '<span style="color:var(--slate-light);">—</span>')
        + '</span>'
        + '</td>'

        /* Flood Height */
        + '<td>' + heightHtml + '</td>'

        /* Affected HH */
        + '<td>'
        + '<div class="ti-stat-cell">'
        + '<i class="fa-solid fa-house" style="font-size:9px;color:var(--slate-mid);margin-right:3px;"></i>'
        + '<span class="ti-stat-num">' + Number(s.affected_households || 0).toLocaleString('en-PH') + '</span>'
        + '</div>'
        + '</td>'

        /* Affected Persons */
        + '<td>'
        + '<div class="ti-stat-cell">'
        + '<i class="fa-solid fa-person" style="font-size:9px;color:var(--slate-mid);margin-right:3px;"></i>'
        + '<span class="ti-stat-num">' + Number(s.affected_persons || 0).toLocaleString('en-PH') + '</span>'
        + '</div>'
        + '</td>'

        /* Road Access */
        + '<td>' + roadHtml + '</td>'

        /* Response Status */
        + '<td>' + welfareBadge(s.welfare_status) + '</td>'

        /* Actions */
        + '<td>'
        + '<button class="ti-detail-btn" onclick="window.__tiOpenDetail(' + s.street_id + ')" title="View street impact details">'
        + '<i class="fa-solid fa-arrow-up-right-from-square"></i>'
        + '<span>Details</span>'
        + '</button>'
        + '</td>'
        + '</tr>';
    }).join('');

    var footer = el('tiTableFooter');
    var infoEl = el('tiTableInfo');
    if (footer) footer.style.display = '';
    if (infoEl) {
      infoEl.innerHTML = '<i class="fa-solid fa-table-list" style="margin-right:5px;color:var(--slate-mid);"></i>'
        + 'Showing <strong>' + (start + 1) + '–' + Math.min(start + PAGE_SIZE, total) + '</strong> of <strong>' + total + '</strong> streets';
    }

    renderPagination(total);
  }

  function renderPagination(total) {
    var wrap = el('tiTablePagination');
    if (!wrap) return;
    var pages = Math.ceil(total / PAGE_SIZE);
    if (pages <= 1) { wrap.innerHTML = ''; return; }

    var html = '<button class="page-btn page-nav" ' + (currentPage === 1 ? 'disabled' : '') + ' data-page="' + (currentPage - 1) + '">'
      + '<i class="fa-solid fa-chevron-left"></i></button>';

    var start = Math.max(1, currentPage - 2);
    var end   = Math.min(pages, currentPage + 2);
    if (start > 1) html += '<button class="page-btn" data-page="1">1</button>' + (start > 2 ? '<span class="page-ellipsis">…</span>' : '');
    for (var i = start; i <= end; i++) {
      html += '<button class="page-btn' + (i === currentPage ? ' active' : '') + '" data-page="' + i + '">' + i + '</button>';
    }
    if (end < pages) html += (end < pages - 1 ? '<span class="page-ellipsis">…</span>' : '') + '<button class="page-btn" data-page="' + pages + '">' + pages + '</button>';

    html += '<button class="page-btn page-nav" ' + (currentPage === pages ? 'disabled' : '') + ' data-page="' + (currentPage + 1) + '">'
      + '<i class="fa-solid fa-chevron-right"></i></button>';

    wrap.innerHTML = html;
    wrap.querySelectorAll('.page-btn:not([disabled])').forEach(function (btn) {
      btn.addEventListener('click', function () {
        currentPage = parseInt(this.dataset.page);
        renderTable();
        /* Scroll to table top */
        var tableCard = document.querySelector('.dashboard-table');
        if (tableCard) tableCard.closest('.dash-card').scrollIntoView({ behavior: 'smooth', block: 'start' });
      });
    });
  }

  function populateZoneFilter(impacts) {
    var sel = el('tiZoneFilter');
    if (!sel) return;
    var zones = [...new Set(impacts.map(function (s) { return s.zone_name; }))].filter(Boolean).sort();
    sel.innerHTML = '<option value="">All Zones</option>'
      + zones.map(function (z) { return '<option value="' + esc(z) + '">' + esc(z) + '</option>'; }).join('');
  }

  /* ══════════════════════════════════════════════════════
     DETAIL MODAL
  ══════════════════════════════════════════════════════ */
  async function openDetailModal(streetId, eventId) {
    var modal   = el('tiDetailModal');
    var body    = el('tiModalBody');
    var title   = el('tiModalTitle');
    var eyebrow = el('tiModalEyebrow');
    if (!modal) return;

    openModal('tiDetailModal');
    body.innerHTML = '<div class="ti-empty"><div class="map-spinner"></div><div class="ti-empty-text">Loading details…</div></div>';
    if (title) title.textContent = '—';

    try {
      var data      = await api('street_impact', { event_id: eventId, street_id: streetId });
      var street    = data.street;
      var impact    = data.impact;
      var plans     = data.plans     || [];
      var resources = data.resources || [];
      var reports   = data.reports   || [];
      var demo      = data.demo;

      if (title)   title.textContent = street.street_name;
      if (eyebrow) eyebrow.textContent = (currentEvent ? currentEvent.event_name + ' — ' : '') + 'Street Details';

      var html = '';

      /* ── Impact Overview ─────────────────────────────── */
      html += '<div class="ti-modal-section">'
        + '<div class="ti-modal-section-title"><i class="fa-solid fa-hurricane"></i> Impact Overview</div>'
        + '<div class="ti-modal-grid">';

      if (impact) {
        html += field('Impact Level', impactBadge(impact.impact_level))
          + field('Flood Status', floodBadge(impact.flood_status))
          + field('Damage Status', impact.damage_status && impact.damage_status !== 'None'
              ? '<i class="fa-solid fa-house-chimney-crack" style="color:#9ca3af;margin-right:5px;"></i>' + esc(impact.damage_status)
              : '<span style="color:var(--slate-light);">None</span>')
          + field('Flood Height', impact.flood_height_m != null
              ? '<i class="fa-solid fa-ruler-vertical" style="color:var(--slate-mid);margin-right:5px;font-size:12px;"></i>' + impact.flood_height_m + ' m'
              : '—')
          + field('Affected Households', '<div class="ti-modal-big-stat"><i class="fa-solid fa-house" style="color:#d97706;font-size:13px;"></i><strong>' + Number(impact.affected_households).toLocaleString('en-PH') + '</strong></div>')
          + field('Affected Persons',    '<div class="ti-modal-big-stat"><i class="fa-solid fa-person" style="color:#dc2626;font-size:13px;"></i><strong>' + Number(impact.affected_persons).toLocaleString('en-PH') + '</strong></div>')
          + field('Road Accessible', impact.road_accessible
              ? '<span style="color:#16a34a;font-weight:600;"><i class="fa-solid fa-circle-check" style="margin-right:4px;"></i>Accessible</span>'
              : '<span style="color:#dc2626;font-weight:600;"><i class="fa-solid fa-road-barrier" style="margin-right:4px;"></i>Blocked</span>')
          + field('Report Source', '<i class="fa-solid fa-file-lines" style="color:var(--slate-mid);margin-right:5px;font-size:11px;"></i>' + esc(impact.report_source || '—'))
          + field('Date Recorded', '<i class="fa-regular fa-calendar" style="color:var(--slate-mid);margin-right:5px;font-size:11px;"></i>' + fmtDateTime(impact.date_recorded))
          + (impact.notes ? field('Notes', '<i class="fa-solid fa-note-sticky" style="color:var(--slate-mid);margin-right:5px;font-size:11px;"></i>' + esc(impact.notes), true) : '');
      } else {
        html += '<div class="ti-modal-field full"><div class="ti-modal-field-value" style="color:var(--slate-light);">'
          + '<i class="fa-solid fa-circle-info" style="margin-right:6px;color:var(--slate-mid);"></i>No impact record found for this event.'
          + '</div></div>';
      }

      html += '</div></div>';

      /* ── Street Info ──────────────────────────────────── */
      html += '<div class="ti-modal-section">'
        + '<div class="ti-modal-section-title"><i class="fa-solid fa-road"></i> Street Info</div>'
        + '<div class="ti-modal-grid">'
        + field('Zone', '<i class="fa-solid fa-map-pin" style="color:var(--slate-mid);margin-right:5px;font-size:11px;"></i>' + esc(street.zone_name))
        + field('Risk Level', riskBadge(street.current_risk_level))
        + field('Total Households', '<i class="fa-solid fa-house" style="color:var(--slate-mid);margin-right:5px;font-size:11px;"></i>' + Number(street.total_households || 0).toLocaleString('en-PH'))
        + field('Total Population', '<i class="fa-solid fa-people-group" style="color:var(--slate-mid);margin-right:5px;font-size:11px;"></i>' + Number(street.total_population || 0).toLocaleString('en-PH'))
        + field('Welfare Needed', esc(street.needs_welfare || '—'))
        + field('Vuln. Score', '<div class="ti-modal-big-stat"><i class="fa-solid fa-chart-line" style="color:var(--ti-high);font-size:13px;"></i><strong>' + (street.current_vuln_score || 0) + '</strong></div>')
        + '</div></div>';

      /* ── Demographics ─────────────────────────────────── */
      if (demo) {
        html += '<div class="ti-modal-section">'
          + '<div class="ti-modal-section-title"><i class="fa-solid fa-chart-pie"></i> Demographics</div>'
          + '<div class="ti-modal-grid">'
          + field('Poverty Rate', demo.poverty_rate_pct ? '<i class="fa-solid fa-percent" style="font-size:9px;color:var(--slate-mid);margin-right:4px;"></i>' + demo.poverty_rate_pct + '%' : '—')
          + field('4Ps Households', '<i class="fa-solid fa-hand-holding-heart" style="color:var(--slate-mid);font-size:11px;margin-right:4px;"></i>' + (demo.fourps_households || 0))
          + field('PWD', '<i class="fa-solid fa-wheelchair" style="color:var(--slate-mid);font-size:11px;margin-right:4px;"></i>' + (demo.pwd_count || 0))
          + field('Senior Citizens', '<i class="fa-solid fa-person-cane" style="color:var(--slate-mid);font-size:11px;margin-right:4px;"></i>' + (demo.senior_count || 0))
          + field('Informal Settlers', demo.informal_settlers_pct ? demo.informal_settlers_pct + '%' : '—')
          + field('Flood Frequency', demo.flood_frequency ? '<i class="fa-solid fa-water" style="color:var(--ti-blue);font-size:11px;margin-right:4px;"></i>' + demo.flood_frequency + 'x in 5 yrs' : '0')
          + field('Avg Flood Height', demo.avg_flood_height_m ? demo.avg_flood_height_m + 'm' : '—')
          + field('Drainage', '<i class="fa-solid fa-droplet" style="color:var(--slate-mid);font-size:11px;margin-right:4px;"></i>' + esc(demo.drainage_type || '—'))
          + '</div></div>';
      }

      /* ── Welfare Plans ────────────────────────────────── */
      if (plans.length) {
        html += '<div class="ti-modal-section">'
          + '<div class="ti-modal-section-title"><i class="fa-solid fa-hand-holding-heart"></i> Welfare Action Plans</div>';
        html += plans.map(function (p) {
          var statusColor = { Planned: '#ca8a04', Ongoing: '#d97706', Completed: '#16a34a', Cancelled: '#9ca3af' }[p.status] || '#9ca3af';
          var statusIcon  = { Planned: 'fa-clock', Ongoing: 'fa-rotate', Completed: 'fa-circle-check', Cancelled: 'fa-ban' }[p.status] || 'fa-clock';
          return '<div class="ti-plan-item">'
            + '<div class="ti-plan-icon" style="background:' + statusColor + '20;border:1px solid ' + statusColor + '40;"><i class="fa-solid fa-clipboard-list" style="color:' + statusColor + ';"></i></div>'
            + '<div class="ti-plan-body">'
            + '<div class="ti-plan-type">' + esc(p.assistance_type)
            + ' <span style="font-size:10px;font-weight:700;padding:2px 7px;border-radius:100px;background:' + statusColor + '20;color:' + statusColor + ';border:1px solid ' + statusColor + '60;">'
            + '<i class="fa-solid ' + statusIcon + '" style="margin-right:3px;"></i>' + esc(p.status) + '</span>'
            + '</div>'
            + '<div class="ti-plan-desc">' + esc(p.description || '') + '</div>'
            + '</div>'
            + '<div class="ti-plan-date"><i class="fa-regular fa-calendar" style="margin-right:3px;color:var(--slate-light);"></i>' + fmtDate(p.planned_date) + '</div>'
            + '</div>';
        }).join('');
        html += '</div>';
      }

      /* ── Resources ────────────────────────────────────── */
      if (resources.length) {
        html += '<div class="ti-modal-section">'
          + '<div class="ti-modal-section-title"><i class="fa-solid fa-boxes-stacked"></i> Resources Distributed</div>';
        html += resources.map(function (r) {
          return '<div class="ti-resource-item">'
            + '<div class="ti-plan-icon" style="background:var(--bg-subtle);border:1px solid var(--border-light);"><i class="fa-solid fa-box-open" style="color:var(--navy);"></i></div>'
            + '<div style="flex:1;">'
            + '<div class="ti-resource-name">' + esc(r.resource_name) + '</div>'
            + '<div class="ti-resource-meta"><i class="fa-solid fa-tag" style="font-size:9px;margin-right:3px;"></i>' + esc(r.category) + ' &nbsp;·&nbsp; <i class="fa-regular fa-clock" style="font-size:9px;margin-right:3px;"></i>' + fmtDateTime(r.distributed_at) + '</div>'
            + '</div>'
            + '<div class="ti-resource-qty">'
            + r.qty_distributed + ' ' + esc(r.unit)
            + (r.total_cost ? '<div style="font-size:11px;font-weight:500;color:var(--slate-mid);"><i class="fa-solid fa-peso-sign" style="font-size:9px;"></i> ' + fmtPeso(r.total_cost) + '</div>' : '')
            + '</div>'
            + '</div>';
        }).join('');
        html += '</div>';
      }

      /* ── Resident Reports ─────────────────────────────── */
      if (reports.length) {
        html += '<div class="ti-modal-section">'
          + '<div class="ti-modal-section-title"><i class="fa-solid fa-comment-dots"></i> Resident Reports</div>';
        html += reports.map(function (r) {
          var sevColor = { Severe: '#dc2626', Moderate: '#d97706', Low: '#ca8a04' }[r.severity] || '#9ca3af';
          var sevIcon  = { Severe: 'fa-circle-exclamation', Moderate: 'fa-triangle-exclamation', Low: 'fa-circle-info' }[r.severity] || 'fa-circle-info';
          return '<div class="ti-plan-item">'
            + '<div class="ti-plan-icon" style="background:' + sevColor + '18;border:1px solid ' + sevColor + '40;">'
            + '<i class="fa-solid ' + sevIcon + '" style="color:' + sevColor + ';"></i>'
            + '</div>'
            + '<div class="ti-plan-body">'
            + '<div class="ti-plan-type">' + esc(r.report_type) + ' — <span style="color:' + sevColor + ';font-weight:600;">' + esc(r.severity) + '</span></div>'
            + '<div class="ti-plan-desc">' + esc(r.description || '') + '</div>'
            + '</div>'
            + '<div class="ti-plan-date"><i class="fa-regular fa-clock" style="margin-right:3px;color:var(--slate-light);"></i>' + fmtDateTime(r.created_at) + '</div>'
            + '</div>';
        }).join('');
        html += '</div>';
      }

      body.innerHTML = html;

    } catch (err) {
      body.innerHTML = '<div class="ti-empty">'
        + '<div class="ti-empty-icon"><i class="fa-solid fa-circle-exclamation"></i></div>'
        + '<div class="ti-empty-text">Could not load details.</div>'
        + '<div class="ti-empty-sub">' + esc(err.message) + '</div>'
        + '</div>';
    }
  }

  function field(label, value, full) {
    return '<div class="ti-modal-field' + (full ? ' full' : '') + '">'
      + '<div class="ti-modal-field-label">' + esc(label) + '</div>'
      + '<div class="ti-modal-field-value">' + value + '</div>'
      + '</div>';
  }

  /* ══════════════════════════════════════════════════════
     FILTERS & SEARCH
  ══════════════════════════════════════════════════════ */
  function setupFilters() {
    document.querySelectorAll('.sm-chip[data-filter]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var filter = this.dataset.filter;
        var val    = this.dataset.val;

        document.querySelectorAll('.sm-chip[data-filter="' + filter + '"]').forEach(function (b) {
          b.classList.remove('active');
        });
        this.classList.add('active');

        if (filter === 'impact') filterImpact = val;
        if (filter === 'status') filterStatus = val;
        currentPage = 1;
        renderTable();
      });
    });

    var zoneFilter = el('tiZoneFilter');
    if (zoneFilter) {
      zoneFilter.addEventListener('change', function () {
        filterZone  = this.value;
        currentPage = 1;
        renderTable();
      });
    }

    var searchInput = el('tiSearch');
    var searchClear = el('tiSearchClear');
    if (searchInput) {
      searchInput.addEventListener('input', function () {
        searchQuery = this.value.trim();
        if (searchClear) searchClear.style.display = searchQuery ? '' : 'none';
        currentPage = 1;
        renderTable();
      });
    }
    if (searchClear) {
      searchClear.addEventListener('click', function () {
        searchInput.value = '';
        searchQuery = '';
        this.style.display = 'none';
        currentPage = 1;
        renderTable();
      });
    }

    var btnImpact  = el('btnLayerImpact');
    var btnRisk    = el('btnLayerRisk');
    var btnWelfare = el('btnLayerWelfare');
    [btnImpact, btnRisk, btnWelfare].forEach(function (btn) {
      if (!btn) return;
      btn.addEventListener('click', function () {
        tiLayer = btn.id === 'btnLayerRisk'    ? 'risk'
                : btn.id === 'btnLayerWelfare' ? 'welfare'
                : 'impact';
        [btnImpact, btnRisk, btnWelfare].forEach(function (b) { if (b) b.classList.remove('active'); });
        btn.classList.add('active');
        updateMarkerColors();
      });
    });

    var eventSel = el('tiEventSelect');
    if (eventSel) {
      eventSel.addEventListener('change', function () {
        if (this.value) loadEventData(parseInt(this.value));
      });
    }

    var closeBtn = el('tiModalClose');
    var backdrop = el('tiDetailModal');
    if (closeBtn) closeBtn.addEventListener('click', function () { closeModal('tiDetailModal'); });
    if (backdrop) backdrop.addEventListener('click', function (e) { if (e.target === this) closeModal('tiDetailModal'); });

    var exportBtn = el('tiExportBtn');
    if (exportBtn) exportBtn.addEventListener('click', exportCSV);
  }

  /* ══════════════════════════════════════════════════════
     EXPORT CSV
  ══════════════════════════════════════════════════════ */
  function exportCSV() {
    var rows = getFilteredRows();
    var headers = ['Street','Zone','Impact Level','Flood Status','Damage','Flood Height (m)','Affected HH','Affected Persons','Road Accessible','Response Status'];
    var csv = headers.join(',') + '\n'
      + rows.map(function (s) {
        return [
          '"' + (s.street_name || '').replace(/"/g, '""') + '"',
          '"' + (s.zone_name || '') + '"',
          s.impact_level || '',
          '"' + (s.flood_status || '') + '"',
          '"' + (s.damage_status || '') + '"',
          s.flood_height_m || '',
          s.affected_households || 0,
          s.affected_persons || 0,
          s.road_accessible ? 'Yes' : 'No',
          '"' + (s.welfare_status || '') + '"',
        ].join(',');
      }).join('\n');

    var eventName = currentEvent ? currentEvent.event_name.replace(/\s+/g, '_') : 'event';
    var blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    var url  = URL.createObjectURL(blob);
    var a    = document.createElement('a');
    a.href     = url;
    a.download = 'typhoon_impact_' + eventName + '.csv';
    a.click();
    URL.revokeObjectURL(url);
  }

  /* ══════════════════════════════════════════════════════
     PAGASA PDF IMPORT
  ══════════════════════════════════════════════════════ */
  var pendingPagasaImport = null;
  var pendingPagasaText = '';
  var pendingPagasaFile = null;
  var pendingCsvPreview = null;
  var importGeneration = 0;

  function csrfToken() {
    var meta = document.querySelector('meta[name="typhoon-csrf"]');
    return meta ? meta.content : '';
  }

  function resetImportPreview() {
    importGeneration++;
    pendingCsvPreview = null;
    pendingPagasaImport = null;
    pendingPagasaFile = null;
    pendingPagasaText = '';
    ['pagasaCsvPreview', 'pagasaImportPreview', 'tiAddEventSubmit', 'tiAddEventMsg', 'pagasaImportProgress'].forEach(function (id) {
      var node = el(id);
      if (node) node.style.display = 'none';
    });
    var demo = el('pagasaImportFormat').value === 'impact';
    var csv = el('pagasaImportFormat').value !== 'pdf';
    el('pagasaCsvFields').style.display = csv ? '' : 'none';
    el('pagasaPdfFields').style.display = csv ? 'none' : '';
    el('pagasaSourceFields').style.display = csv ? 'none' : '';
    el('tiAnalyzePagasaReport').innerHTML = '<i class="fa-solid fa-magnifying-glass"></i> ' + (csv ? 'Preview CSV' : 'Analyze PDF');
    el('tiAnalyzePagasaReport').disabled = false;
    el('tiImportTitle').textContent = demo ? 'Import Street Impacts CSV' : 'Import PAGASA Data';
    el('tiImportEyebrow').textContent = demo ? 'Bagong Silang street assessments' : 'Official source import';
    el('tiImportNotice').textContent = demo ? 'Match each street assessment to an existing PAGASA typhoon using its name and year. Import the PAGASA dataset first, then review the matching street records before confirming.' : 'Read-only source data. CSV records must match the reviewed PAGASA dataset. Importing weather details does not create street-impact assessments.';
    el('tiCsvFileLabel').textContent = demo ? 'Street-impact dataset (CSV) *' : 'PAGASA dataset (CSV) *';
    el('tiCsvHelp').textContent = demo ? 'Maximum 2 MB / 1,000 rows. Match pagasa_name and report_year to an imported PAGASA typhoon. Street IDs and names must match existing Bagong Silang streets. Export Excel as CSV UTF-8.' : 'Maximum 2 MB. Upload the full dataset or a subset with the same columns and unchanged values.';
    el('tiCsvDownload').href = API + '?action=' + (demo ? 'download_impact_csv_sample' : 'download_pagasa_csv_sample');
    el('tiCsvDownload').textContent = 'Download sample CSV format';
    el('tiCsvDownloadHelp').textContent = 'Column headers and one example record only, for admin preview and format reference. Downloading does not import any data.';
  }

  function renderDemoPreview(data) {
    el('pagasaCsvPreview').innerHTML = '<p><strong>Street impact preview:</strong> ' + data.event_count + ' typhoon events, ' + data.rows.length + ' street assessments. ' + data.counts.create + ' new, ' + data.counts.revise + ' updates, ' + data.counts.skip + ' already stored.</p>'
      + '<p>Records will be saved under the matched existing typhoon. Selecting INDAY shows only its matching year and street records.</p>'
      + '<div class="ti-csv-preview-scroll"><table><thead><tr><th>Typhoon</th><th>Impacted</th><th>Flooded</th><th>Damaged</th><th>Roads closed</th><th>Households</th><th>Persons</th></tr></thead><tbody>'
      + data.events.map(function (e) { return '<tr><td>' + esc(e.event_name) + '</td><td>' + e.impacted_streets + '</td><td>' + e.flooded_streets + '</td><td>' + e.damaged_streets + '</td><td>' + e.inaccessible_streets + '</td><td>' + e.affected_households + '</td><td>' + e.affected_persons + '</td></tr>'; }).join('') + '</tbody></table></div>'
      + '<p>Street assessments</p><div class="ti-csv-preview-scroll"><table><thead><tr><th>Typhoon event</th><th>Street</th><th>Flood / damage</th><th>Road</th><th>Affected</th><th>Action</th></tr></thead><tbody>'
      + data.rows.map(function (r) { return '<tr><td>' + esc(r.event_name) + '</td><td>' + esc(r.street_name) + '</td><td>' + esc(r.flood_status) + ' (' + r.flood_height_m + ' m)<br>' + esc(r.damage_status) + '</td><td>' + (r.road_accessible ? 'Passable' : 'Closed') + '</td><td>' + r.affected_households + ' households<br>' + r.affected_persons + ' persons</td><td>' + esc(r.action) + '</td></tr>'; }).join('') + '</tbody></table></div>';
    el('pagasaCsvPreview').style.display = '';
  }

  function renderCsvPreview(data) {
    var counts = data.counts;
    el('pagasaCsvPreview').innerHTML = '<p><strong>' + data.rows.length + ' reviewed records:</strong> '
      + counts.create + ' new, ' + counts.revise + ' revisions, ' + counts.skip + ' already stored or superseded.</p>'
      + '<p>Historical status: Passed. Street impacts: not supplied by this dataset.</p>'
      + '<div class="ti-csv-preview-scroll"><table><thead><tr><th>Typhoon</th><th>Classification</th><th>Wind</th><th>Action</th><th>Source notes</th></tr></thead><tbody>'
      + data.rows.map(function (r) {
        return '<tr><td><a target="_blank" rel="noopener" href="' + esc(r.source_url) + '">' + esc(r.pagasa_name) + ' ' + r.report_year + '</a><br>Rev. ' + r.report_revision + '</td>'
          + '<td>' + esc(r.classification) + '</td><td>' + esc(r.peak_wind_kph) + ' km/h</td><td>' + esc(r.action) + '</td>'
          + '<td' + (/left blank|normalized|could not/i.test(r.quality_notes) ? ' class="ti-csv-quality"' : '') + '>' + esc(r.quality_notes) + '</td></tr>';
      }).join('') + '</tbody></table></div>';
    el('pagasaCsvPreview').style.display = '';
  }

  var MONTHS = {
    january: 0, february: 1, march: 2, april: 3, may: 4, june: 5,
    july: 6, august: 7, september: 8, october: 9, november: 10, december: 11
  };

  function isoFromPagasaDate(time, day, month, year) {
    var monthIndex = MONTHS[String(month || '').toLowerCase()];
    if (monthIndex == null) return null;
    var cleanTime = String(time || '0000').replace(/\D/g, '');
    // Some PAGASA PDFs contain a duplicated trailing zero (for example 19300).
    if (cleanTime.length === 5 && cleanTime.slice(-1) === '0') cleanTime = cleanTime.slice(0, 4);
    if (cleanTime.length !== 4) return null;
    var hour = Number(cleanTime.slice(0, 2));
    var minute = Number(cleanTime.slice(2, 4));
    if (hour > 23 || minute > 59) return null;
    var date = new Date(Date.UTC(Number(year), monthIndex, Number(day), hour, minute, 0));
    return isNaN(date.getTime()) ? null : date.toISOString().replace('.000Z', 'Z');
  }

  function isoDateOnly(day, month, year) {
    var full = isoFromPagasaDate('0000', day, month, year);
    return full ? full.slice(0, 10) : null;
  }

  function parsePagasaReportText(input, sourceUrl, filename) {
    var text = String(input || '').replace(/\u00a0/g, ' ').replace(/\s+/g, ' ').trim();
    if (!/TROPICAL CYCLONE PRELIMINARY REPORT/i.test(text) || !/PAGASA/i.test(text)) {
      throw new Error('The selected PDF is not recognized as a PAGASA Tropical Cyclone Preliminary Report.');
    }

    var title = text.match(/(Super Typhoon|Severe Tropical Storm|Tropical Depression|Tropical Storm|Typhoon)\s+([A-Z][A-Z0-9 -]*?)\s+([A-Z][A-Z0-9 -]*?)\s*\((\d{4})\)/i);
    if (!title) throw new Error('Could not read the cyclone name and classification from the PDF.');

    var developed = text.match(/Developed into a tropical cyclone\s+(\d{4,5})\s+UTC,?\s+(\d{1,2})\s+([A-Za-z]+)\s+(\d{4})/i);
    var ended = text.match(/Weakened into a remnant low or transitioned into a post-?tropical low\s+(\d{4,5})\s+UTC,?\s+(\d{1,2})\s+([A-Za-z]+)\s+(\d{4})/i);
    var peak = text.match(/Peak intensity\s*\(lifetime\d*\)\s+(\d+(?:\.\d+)?)\s*kt\s*\((\d+(?:\.\d+)?)\s*km\/h\),\s*(\d+(?:\.\d+)?)\s*hPa,\s*(?:Super Typhoon|Severe Tropical Storm|Tropical Depression|Tropical Storm|Typhoon)\s+(\d{4,5})\s+UTC,?\s+(\d{1,2})\s+([A-Za-z]+)\s+(\d{4})/i);
    var published = text.match(/Date Published\s*[|:]?\s*(\d{1,2})\s+([A-Za-z]+)\s+(\d{4})/i);
    var revised = text.match(/Last Revision\s*[|:]?\s*(\d{1,2})\s+([A-Za-z]+)\s+(\d{4})/i);
    var revision = text.match(/\bRev\.?\s*(\d+)\b/i) || String(filename || '').match(/[_ .-]Rev\.?\s*(\d+)/i);
    var landfall = text.match(/Observed landfall(?:\/s|s)? in the Philippines\s+(?!None\b)(.{2,220}?):\s*(\d{4,5})\s+UTC,?\s+(\d{1,2})\s+([A-Za-z]+)\s+(\d{4})/i);
    var tcws = text.match(/Highest level of wind signal hoisted:\s*(\d+)/i);
    var bulletinBlock = text.match(/Tropical Cyclone Bulletins:(.{0,500}?)(?:Tropical Cyclone Warnings for Shipping|WC SIGMET|Tropical Cyclone Wind Signals)/i);
    var bulletinCount = bulletinBlock ? bulletinBlock[1].match(/Total issued:\s*(\d+)/i) : null;
    var exitBlock = text.match(/Exited the PAR region\s*\(as tropical cyclone\)\s*(.{0,220}?)(?=Peak intensity)/i);

    var startedAt = developed ? isoFromPagasaDate(developed[1], developed[2], developed[3], developed[4]) : null;
    if (!startedAt) throw new Error('Could not read the cyclone start date from the PDF.');

    var classMap = {
      'tropical depression': 'Tropical Depression',
      'tropical storm': 'Tropical Storm',
      'severe tropical storm': 'Severe Tropical Storm',
      'typhoon': 'Typhoon',
      'super typhoon': 'Super Typhoon'
    };

    return {
      pagasa_name: title[2].trim().toUpperCase(),
      international_name: title[3].trim().toUpperCase(),
      international_id: title[4],
      classification: classMap[title[1].toLowerCase()],
      report_year: Number(developed[4]),
      report_revision: revision ? Number(revision[1]) : 0,
      report_published_at: published ? isoDateOnly(published[1], published[2], published[3]) : null,
      report_last_revised_at: revised ? isoDateOnly(revised[1], revised[2], revised[3]) : null,
      started_at_utc: startedAt,
      ended_at_utc: ended ? isoFromPagasaDate(ended[1], ended[2], ended[3], ended[4]) : null,
      peak_intensity_at_utc: peak ? isoFromPagasaDate(peak[4], peak[5], peak[6], peak[7]) : null,
      peak_wind_knots: peak ? Number(peak[1]) : null,
      peak_wind_kph: peak ? Number(peak[2]) : null,
      minimum_pressure_hpa: peak ? Number(peak[3]) : null,
      developed_inside_par: /Entered the PAR region\s*\(as tropical cyclone\)\s*Not applicable\s*\(developed within the PAR region\)/i.test(text),
      exited_par: !!(exitBlock && !/^Not applicable/i.test(exitBlock[1].trim())),
      landfall_location: landfall ? landfall[1].trim() : null,
      landfall_at_utc: landfall ? isoFromPagasaDate(landfall[2], landfall[3], landfall[4], landfall[5]) : null,
      highest_tcws: tcws ? Number(tcws[1]) : null,
      bulletins_issued: bulletinCount ? Number(bulletinCount[1]) : null,
      source_url: sourceUrl || null
    };
  }

  async function extractPdfText(file) {
    if (typeof window.pdfjsLib === 'undefined') {
      throw new Error('PDF reader failed to load. Check the internet connection and reload the page.');
    }
    window.pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
    var bytes = new Uint8Array(await file.arrayBuffer());
    var pdf = await window.pdfjsLib.getDocument({ data: bytes }).promise;
    var pages = [];
    for (var pageNumber = 1; pageNumber <= pdf.numPages; pageNumber++) {
      var page = await pdf.getPage(pageNumber);
      var content = await page.getTextContent();
      pages.push(content.items.map(function (item) { return item.str; }).join(' '));
    }
    return pages.join('\n\n');
  }

  function renderPagasaPreview(report) {
    var preview = document.getElementById('pagasaImportPreview');
    var title = document.getElementById('pagasaPreviewTitle');
    var grid = document.getElementById('pagasaPreviewGrid');
    if (!preview || !title || !grid) return;
    title.textContent = report.classification + ' ' + report.pagasa_name;
    var fields = [
      ['International name', report.international_name + ' (' + report.international_id + ')'],
      ['Started (UTC)', report.started_at_utc],
      ['Ended (UTC)', report.ended_at_utc || 'Not found'],
      ['Peak wind', report.peak_wind_kph != null ? report.peak_wind_kph + ' km/h' : 'Not found'],
      ['Minimum pressure', report.minimum_pressure_hpa != null ? report.minimum_pressure_hpa + ' hPa' : 'Not found'],
      ['Landfall', report.landfall_location || 'None reported'],
      ['Highest TCWS', report.highest_tcws != null ? 'Signal No. ' + report.highest_tcws : 'Not found'],
      ['Bulletins issued', report.bulletins_issued != null ? report.bulletins_issued : 'Not found'],
      ['Report revision', report.report_revision ? 'Rev. ' + report.report_revision : 'Original']
    ];
    grid.innerHTML = fields.map(function (field) {
      return '<div class="ti-import-field"><span>' + esc(field[0]) + '</span><strong>' + esc(field[1]) + '</strong></div>';
    }).join('');
    preview.style.display = '';
  }

  // Small public seam used by the parser regression test; the import UI remains the only caller.
  if (typeof window !== 'undefined') {
    window.PagasaReportParser = { parse: parsePagasaReportText };
  }


  /* ══════════════════════════════════════════════════════
     ADMIN FORMS — Reviewed source imports
  ══════════════════════════════════════════════════════ */

  function showFormMsg(elId, msg, ok) {
    var el = document.getElementById(elId);
    if (!el) return;
    el.style.display = '';
    el.style.background = ok ? 'rgba(22,163,74,.15)' : 'rgba(220,38,38,.15)';
    el.style.color = ok ? '#166534' : '#991b1b';
    el.style.border = '1px solid ' + (ok ? '#4ade80' : '#f87171');
    el.textContent = msg;
  }

  function openModal(id) {
    var m = document.getElementById(id);
    if (!m) return;
    modalScrollY = window.scrollY || window.pageYOffset || 0;
    document.body.classList.add('ti-modal-locked');
    document.body.style.top = '-' + modalScrollY + 'px';
    m.classList.add('open');
  }

  function closeModal(id) {
    var m = document.getElementById(id);
    if (m) m.classList.remove('open');
    if (!document.querySelector('.modal-backdrop.open')) {
      document.body.classList.remove('ti-modal-locked');
      document.body.style.top = '';
      window.scrollTo(0, modalScrollY);
    }
  }

  function setupAdminForms() {
    /* ── IMPORT OFFICIAL PAGASA REPORT modal ── */
    var btnAddEvent = document.getElementById('btnAddEvent');
    if (btnAddEvent) btnAddEvent.addEventListener('click', function () {
      el('pagasaImportFormat').value = 'csv';
      resetImportPreview();
      pendingPagasaImport = null;
      pendingPagasaText = '';
      pendingPagasaFile = null;
      var preview = document.getElementById('pagasaImportPreview');
      var submit = document.getElementById('tiAddEventSubmit');
      var message = document.getElementById('tiAddEventMsg');
      if (preview) preview.style.display = 'none';
      if (submit) submit.style.display = 'none';
      if (message) message.style.display = 'none';
      openModal('tiAddEventModal');
    });
    el('btnDemoImpactImport').addEventListener('click', function () {
      el('pagasaImportFormat').value = 'impact';
      resetImportPreview();
      openModal('tiAddEventModal');
    });
    ['pagasaImportFormat', 'pagasaCsvFile', 'pagasaPdfFile', 'pagasaSourceUrl'].forEach(function (id) {
      el(id).addEventListener('change', resetImportPreview);
    });
    ['tiAddEventModalClose','tiAddEventCancel'].forEach(function (id) {
      var b = document.getElementById(id);
      if (b) b.addEventListener('click', function () { closeModal('tiAddEventModal'); });
    });
    document.getElementById('tiAddEventModal').addEventListener('click', function (e) { if (e.target === this) closeModal('tiAddEventModal'); });

    var analyzeButton = document.getElementById('tiAnalyzePagasaReport');
    if (analyzeButton) {
      analyzeButton.addEventListener('click', async function () {
        resetImportPreview();
        var generation = importGeneration;
        if (el('pagasaImportFormat').value !== 'pdf') {
          var isDemo = el('pagasaImportFormat').value === 'impact';
          var csvFile = el('pagasaCsvFile').files[0];
          if (!csvFile || !/\.csv$/i.test(csvFile.name) || csvFile.size > 2 * 1024 * 1024) {
            showFormMsg('tiAddEventMsg', 'Choose a .csv file no larger than 2 MB.', false);
            return;
          }
          analyzeButton.disabled = true;
          el('pagasaImportProgress').style.display = '';
          try {
            var csvForm = new FormData();
            csvForm.append('action', isDemo ? 'preview_street_impacts' : 'preview_pagasa_csv');
            csvForm.append('csrf_token', csrfToken());
            csvForm.append('report_csv', csvFile, csvFile.name);
            var csvResponse = await fetch(API, { method: 'POST', body: csvForm });
            var csvData = await csvResponse.json();
            if (generation !== importGeneration) return;
            if (!csvData.ok) throw new Error(csvData.error || 'CSV validation failed.');
            pendingCsvPreview = csvData.preview_id;
            if (isDemo) renderDemoPreview(csvData); else renderCsvPreview(csvData);
            el('tiAddEventSubmit').style.display = csvData.counts.create + csvData.counts.revise > 0 ? '' : 'none';
            showFormMsg('tiAddEventMsg', csvData.counts.create + csvData.counts.revise > 0 ? (isDemo ? 'Street assessments validated. Review the records, then confirm the import.' : 'Source values verified. Review the records, then confirm the import.') : 'All records are already stored or have a newer revision. Nothing to import.', true);
          } catch (err) {
            if (generation === importGeneration) showFormMsg('tiAddEventMsg', err.message || 'Could not validate the CSV.', false);
          } finally {
            if (generation === importGeneration) {
              analyzeButton.disabled = false;
              el('pagasaImportProgress').style.display = 'none';
            }
          }
          return;
        }
        var fileInput = document.getElementById('pagasaPdfFile');
        var sourceInput = document.getElementById('pagasaSourceUrl');
        var progress = document.getElementById('pagasaImportProgress');
        var submit = document.getElementById('tiAddEventSubmit');
        var file = fileInput && fileInput.files ? fileInput.files[0] : null;
        if (!file) {
          showFormMsg('tiAddEventMsg', 'Please choose an official PAGASA PDF report.', false);
          return;
        }
        if (file.type !== 'application/pdf' && !/\.pdf$/i.test(file.name)) {
          showFormMsg('tiAddEventMsg', 'Only PDF reports are accepted.', false);
          return;
        }
        if (file.size > 15 * 1024 * 1024) {
          showFormMsg('tiAddEventMsg', 'The PDF must not exceed 15 MB.', false);
          return;
        }
        analyzeButton.disabled = true;
        if (progress) progress.style.display = '';
        if (submit) submit.style.display = 'none';
        try {
          var extracted = await extractPdfText(file);
          if (generation !== importGeneration) return;
          var parsed = parsePagasaReportText(extracted, sourceInput ? sourceInput.value.trim() : '', file.name);
          pendingPagasaFile = file;
          pendingPagasaText = extracted;
          pendingPagasaImport = parsed;
          renderPagasaPreview(parsed);
          if (submit) submit.style.display = '';
          showFormMsg('tiAddEventMsg', 'Report recognized. Review the read-only preview, then confirm the import.', true);
        } catch (err) {
          pendingPagasaImport = null;
          pendingPagasaText = '';
          pendingPagasaFile = null;
          showFormMsg('tiAddEventMsg', err.message || 'Could not read the PDF.', false);
        }
        if (progress) progress.style.display = 'none';
        analyzeButton.disabled = false;
      });
    }

    var btnAddEventSubmit = document.getElementById('tiAddEventSubmit');
    if (btnAddEventSubmit) {
      btnAddEventSubmit.addEventListener('click', async function () {
        if (el('pagasaImportFormat').value !== 'pdf') {
          var isDemo = el('pagasaImportFormat').value === 'impact';
          if (!pendingCsvPreview) {
            showFormMsg('tiAddEventMsg', 'Preview the CSV before importing.', false);
            return;
          }
          btnAddEventSubmit.disabled = true;
          try {
            var confirm = new FormData();
            confirm.append('action', isDemo ? 'import_street_impacts' : 'import_pagasa_csv');
            confirm.append('csrf_token', csrfToken());
            confirm.append('preview_id', pendingCsvPreview);
            var csvResult = await fetch(API, { method: 'POST', body: confirm });
            var imported = await csvResult.json();
            if (!imported.ok) throw new Error(imported.error || 'Import failed.');
            pendingCsvPreview = null;
            btnAddEventSubmit.style.display = 'none';
            showFormMsg('tiAddEventMsg', 'Import complete: ' + imported.counts.created + ' new, ' + imported.counts.revised + ' revised, ' + imported.counts.skipped + ' skipped. Reloading events…', true);
            setTimeout(function () {
              if (isDemo && imported.event_id) window.location.href = window.location.pathname + '?event_id=' + imported.event_id;
              else window.location.reload();
            }, 1200);
          } catch (err) {
            showFormMsg('tiAddEventMsg', err.message || 'CSV import failed.', false);
          } finally {
            btnAddEventSubmit.disabled = false;
          }
          return;
        }
        if (!pendingPagasaImport || !pendingPagasaFile || !pendingPagasaText) {
          showFormMsg('tiAddEventMsg', 'Analyze an official PAGASA PDF before importing.', false);
          return;
        }
        btnAddEventSubmit.disabled = true;
        try {
          var form = new FormData();
          form.append('action', 'import_pagasa_report');
          form.append('csrf_token', csrfToken());
          form.append('report_pdf', pendingPagasaFile, pendingPagasaFile.name);
          form.append('parsed_data', JSON.stringify(pendingPagasaImport));
          form.append('extracted_text', pendingPagasaText);
          var res  = await fetch(API, { method: 'POST', body: form });
          var data = await res.json();
          if (data.ok) {
            showFormMsg('tiAddEventMsg', 'Official PAGASA report imported. Reloading events…', true);
            setTimeout(function () { window.location.reload(); }, 1200);
          } else {
            showFormMsg('tiAddEventMsg', data.error || 'Import failed.', false);
          }
        } catch (err) {
          showFormMsg('tiAddEventMsg', 'Network error: ' + err.message, false);
        }
        btnAddEventSubmit.disabled = false;
      });
    }

    /* ── PAGASA REVISION HISTORY modal ── */
    var historyButton = document.getElementById('btnPagasaHistory');
    if (historyButton) {
      historyButton.addEventListener('click', async function () {
        var body = document.getElementById('tiPagasaHistoryBody');
        openModal('tiPagasaHistoryModal');
        if (!currentEventId) {
          if (body) body.textContent = 'Select a typhoon event first.';
          return;
        }
        if (body) body.innerHTML = '<div class="ti-import-progress"><i class="fa-solid fa-circle-notch fa-spin"></i> Loading revisions…</div>';
        try {
          var result = await api('pagasa_import_history', { event_id: currentEventId });
          var imports = result.imports || [];
          if (!imports.length) {
            body.textContent = currentEvent && Number(currentEvent.is_demo) ? 'DEMO / SYNTHETIC scenario. These impact assessments are fictitious; no official PAGASA source is claimed.' : 'This legacy event was not created from an official PAGASA PDF import.';
            return;
          }
          body.innerHTML = imports.map(function (r) {
            var revision = Number(r.report_revision || 0) > 0 ? 'Revision ' + Number(r.report_revision) : 'Original report';
            var current = Number(r.is_latest) === 1 ? '<span class="ti-source-badge"><i class="fa-solid fa-check"></i> Current</span>' : '<span class="ti-source-badge" style="background:#f1f5f9;color:#64748b;">Superseded</span>';
            return '<div class="ti-revision-item ' + (Number(r.is_latest) === 1 ? 'is-latest' : '') + '">'
              + '<div><div class="ti-revision-name">' + esc(r.classification + ' ' + r.pagasa_name) + '</div>'
              + '<div class="ti-revision-meta">' + esc(revision) + ' · imported ' + esc(fmtDateTime(r.imported_at)) + '<br>SHA-256: ' + esc(String(r.file_sha256).slice(0, 16)) + '…</div></div>'
              + '<div class="ti-revision-actions">' + current
              + '<a href="' + API + '?action=view_pagasa_report&amp;import_id=' + Number(r.import_id) + '" target="_blank" rel="noopener"><i class="fa-solid fa-file-pdf"></i> View PDF</a></div>'
              + '</div>';
          }).join('');
        } catch (err) {
          if (body) body.textContent = err.message || 'Could not load revision history.';
        }
      });
    }
    ['tiPagasaHistoryClose', 'tiPagasaHistoryDone'].forEach(function (id) {
      var button = document.getElementById(id);
      if (button) button.addEventListener('click', function () { closeModal('tiPagasaHistoryModal'); });
    });
    var historyModal = document.getElementById('tiPagasaHistoryModal');
    if (historyModal) historyModal.addEventListener('click', function (event) {
      if (event.target === this) closeModal('tiPagasaHistoryModal');
    });

  }

  /* ══════════════════════════════════════════════════════
     INIT
  ══════════════════════════════════════════════════════ */
  document.addEventListener('DOMContentLoaded', function () {
    initMap();
    setupFilters();
    loadEvents();
    setupAdminForms();
  });

})();
