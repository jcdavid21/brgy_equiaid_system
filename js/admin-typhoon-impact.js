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

  async function loadMapData(eventId) {
    var overlay = el('tiMapOverlay');
    if (overlay) overlay.style.display = '';
    clearMarkers();

    try {
      var data    = await api('map_data', { event_id: eventId });
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

        marker.on('click', function () {
          openDetailModal(s.street_id, eventId);
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
      if (overlay) overlay.style.display = 'none';
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
        + '<div class="ti-popup-row"><span><i class="fa-solid fa-house-chimney-crack" style="width:14px;"></i> Damage</span><strong>' + esc(s.damage_status) + '</strong></div>'
        + '<div class="ti-popup-row"><span><i class="fa-solid fa-people-group" style="width:14px;"></i> HH</span><strong>' + esc(s.affected_households) + '</strong></div>'
        + '<div class="ti-popup-row"><span><i class="fa-solid fa-person" style="width:14px;"></i> Persons</span><strong>' + esc(s.affected_persons) + '</strong></div>'
        + (s.flood_height_m ? '<div class="ti-popup-row"><span><i class="fa-solid fa-ruler-vertical" style="width:14px;"></i> Height</span><strong>' + s.flood_height_m + 'm</strong></div>' : '')
        + '<div class="ti-popup-row"><span><i class="fa-solid fa-hand-holding-heart" style="width:14px;"></i> Response</span><strong>' + esc(s.welfare_status) + '</strong></div>';
    } else {
      html += '<div class="ti-popup-divider"></div>'
        + '<div class="ti-popup-row"><span>Status</span><strong>No recorded impact</strong></div>';
    }

    html += '<button class="ti-popup-btn" onclick="window.__tiOpenDetail(' + s.street_id + ')">'
      + '<i class="fa-solid fa-circle-info" style="margin-right:5px;"></i>View Full Details'
      + '</button>'
      + '</div>';
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
        sel.innerHTML = '<option value="">No typhoon events found</option>';
        return;
      }

      sel.innerHTML = events.map(function (e) {
        var statusTag = e.status === 'Active' ? ' 🔴' : ' ✓';
        var label = e.event_name + (e.local_name && e.local_name !== e.event_name ? ' (' + e.local_name + ')' : '') + statusTag;
        return '<option value="' + e.event_id + '" data-status="' + esc(e.status) + '">'
          + esc(label) + '</option>';
      }).join('');

      /* ── Smart auto-select: prefer Active event, else first with data ── */
      /* Try to pick the Active event first, then fall back to first event  */
      var activeEvent = events.find(function (e) { return e.status === 'Active'; });
      var defaultEvent = activeEvent || events[0];
      sel.value = defaultEvent.event_id;

      /* Load the chosen event; if it has no data try each remaining event  */
      loadEventData(defaultEvent.event_id, events);

    } catch (err) {
      console.error('loadEvents error:', err);
      var sel = el('tiEventSelect');
      if (sel) sel.innerHTML = '<option value="">Failed to load events</option>';
    }
  }

  async function loadEventData(eventId, allEvents) {
    if (!eventId) return;
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

      currentEvent = summaryData.event;
      allImpacts   = impactData.impacts || [];

      /* ── Smart fallback: if this event has ZERO impact records and we were  ──
         called from loadEvents (allEvents provided), silently switch to the   
         first event in the list that actually has data.                       */
      if (allImpacts.length === 0 && allEvents && allEvents.length > 1) {
        var others = allEvents.filter(function (e) { return e.event_id !== eventId; });
        for (var i = 0; i < others.length; i++) {
          var checkData = await api('impact_list', { event_id: others[i].event_id });
          if (checkData.impacts && checkData.impacts.length > 0) {
            /* Found an event with data — switch to it */
            var sel = el('tiEventSelect');
            if (sel) sel.value = others[i].event_id;
            loadEventData(others[i].event_id); /* no allEvents = no further fallback */
            return;
          }
        }
        /* All events have no data — render the current one as-is (genuinely empty) */
      }

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
        + (event.local_name ? ' <span style="font-weight:400;opacity:.7;">(' + esc(event.local_name) + ')</span>' : '')
        + ' &nbsp;' + statusBadge;
    }

    var metaEl = el('tiBannerMeta');
    if (metaEl) {
      var parts = [];
      if (event.category)       parts.push('<i class="fa-solid fa-wind" style="margin-right:3px;"></i>Category ' + event.category);
      if (event.wind_speed_kph) parts.push('<i class="fa-solid fa-gauge-high" style="margin-right:3px;"></i>' + event.wind_speed_kph + ' kph');
      if (event.date_started)   parts.push('<i class="fa-solid fa-calendar-plus" style="margin-right:3px;"></i>' + fmtDate(event.date_started));
      if (event.date_ended)     parts.push('<i class="fa-solid fa-calendar-check" style="margin-right:3px;"></i>' + fmtDate(event.date_ended));
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
          + '<div class="ti-banner-stat-num">' + Number(s.num || 0).toLocaleString('en-PH') + '</div>'
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
      e.textContent = Number(map[id]).toLocaleString('en-PH');
    });
  }

  /* ══════════════════════════════════════════════════════
     RENDER: IMPACT BARS
     FIX: uses allImpacts (already populated) instead of summary param
  ══════════════════════════════════════════════════════ */
  function renderImpactBars() {
    var wrap = el('tiImpactBars');
    if (!wrap) return;

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
          + '<div class="ti-empty-text">No impact records for this event.</div>'
          + '<div class="ti-empty-sub">This typhoon event has no street-level impact data recorded yet. Select a different event or add records.</div>'
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

    modal.classList.add('open');
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
    if (closeBtn) closeBtn.addEventListener('click', function () { if (backdrop) backdrop.classList.remove('open'); });
    if (backdrop) backdrop.addEventListener('click', function (e) { if (e.target === this) this.classList.remove('open'); });

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
     INIT
  ══════════════════════════════════════════════════════ */
  document.addEventListener('DOMContentLoaded', function () {
    initMap();
    setupFilters();
    loadEvents();
  });

})();