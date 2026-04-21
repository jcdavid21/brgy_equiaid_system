/**
 * disaster_map.js — Barangay EQUIAID Disaster Map
 * Connects to: backend/disaster_map.php
 */
(function () {
    'use strict';

    const API = '../backend/disaster_map.php';

    // ── Safe fetch ─────────────────────────────────────────
    async function apiFetch(action, params = '') {
        const url = `${API}?action=${action}${params ? '&' + params : ''}`;
        const res = await fetch(url);
        if (res.status === 401) {
            window.location.href = '../components/login.php?session_expired=1';
            throw new Error('Not authenticated');
        }
        if (!res.ok) throw new Error(`HTTP ${res.status}`);
        const data = await res.json();
        if (!data.ok) throw new Error(data.error || 'API error');
        return data;
    }

    // ── Number formatter ───────────────────────────────────
    const fmt = n => Number(n || 0).toLocaleString();

    // ── XSS helper ─────────────────────────────────────────
    function esc(str) {
        if (str == null) return '';
        return String(str)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;')
            .replace(/>/g, '&gt;').replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    // ─────────────────────────────────────────────────────
    //  STATE
    // ─────────────────────────────────────────────────────
    let _map        = null;
    let _userLat    = null;
    let _userLng    = null;
    let _userMarker = null;

    // Route line layers (straight-line + OSRM upgrade)
    let _routeLine = null;
    let _routeGlow = null;
    let _routeDash = null;
    let _evacMarkers = []; // populated in renderEvacMarkers

    // Layer groups
    const _layers = {
        streets: null,
        evac:    null,
        reports: null,
        welfare: null,
    };

    // Visibility state
    const _visible = { streets: true, evac: true, reports: true, welfare: true };

    // Raw data cache
    let _streetsData  = [];
    let _evacData     = [];
    let _reportsData  = [];
    let _welfareData  = [];

    // Active risk filter
    let _riskFilter = 'all';

    // ── Weather tile layers (OWM Maps 1.0) ────────────────
    // Replace 'YOUR_OWM_API_KEY' with your OpenWeatherMap API key
    const OWM_KEY = 'YOUR_OWM_API_KEY';
    const OWM_BASE = 'https://tile.openweathermap.org/map';

    const WEATHER_LAYERS = {
        precipitation: {
            label: 'Precipitation',
            icon:  'fa-cloud-rain',
            owmLayer: 'precipitation_new',
            tile: null,
            active: false,
        },
        wind: {
            label: 'Wind',
            icon:  'fa-wind',
            owmLayer: 'wind_new',
            tile: null,
            active: false,
        },
        clouds: {
            label: 'Clouds',
            icon:  'fa-cloud',
            owmLayer: 'clouds_new',
            tile: null,
            active: false,
        },
        temp: {
            label: 'Temperature',
            icon:  'fa-temperature-half',
            owmLayer: 'temp_new',
            tile: null,
            active: false,
        },
    };

    // Current weather data for the barangay (fetched once)
    let _currentWeather = null;

    // ─────────────────────────────────────────────────────
    //  RISK COLOUR HELPERS
    // ─────────────────────────────────────────────────────
    const RISK_COLOR = {
        RED: '#dc2626', ORANGE: '#d97706',
        YELLOW: '#ca8a04', GREEN: '#16a34a',
    };

    const RISK_LABEL = {
        RED: 'Critical', ORANGE: 'High Risk',
        YELLOW: 'Moderate', GREEN: 'Safe',
    };

    const RISK_ICON = {
        RED: 'fa-circle-xmark', ORANGE: 'fa-triangle-exclamation',
        YELLOW: 'fa-circle-minus', GREEN: 'fa-circle-check',
    };

    function riskBannerClass(level) {
        return `dmpd-risk-banner--${(level || 'green').toLowerCase()}`;
    }

    function riskBadgeClass(level) {
        return `dmpd-risk-badge--${(level || 'green').toLowerCase()}`;
    }

    // ─────────────────────────────────────────────────────
    //  1. MAP INIT
    // ─────────────────────────────────────────────────────
    function initMap() {
        _map = L.map('dm-map', {
            center: [14.7430, 120.9845],
            zoom: 16,
            zoomControl: true,
        });

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© <a href="https://openstreetmap.org/copyright">OpenStreetMap</a>',
            maxZoom: 19,
        }).addTo(_map);

        // Init layer groups
        Object.keys(_layers).forEach(k => {
            _layers[k] = L.layerGroup().addTo(_map);
        });

        const spinner = document.getElementById('dmMapSpinner');
        if (spinner) spinner.hidden = true;
    }

    // ─────────────────────────────────────────────────────
    //  2. LOAD EVERYTHING
    // ─────────────────────────────────────────────────────
    async function loadAll() {
        try {
            const [summary, streets, evac, reports, welfare] = await Promise.all([
                apiFetch('summary'),
                apiFetch('streets'),
                apiFetch('evac_centers'),
                apiFetch('reports'),
                apiFetch('welfare'),
            ]);

            _streetsData  = streets.streets  || [];
            _evacData     = evac.centers     || [];
            _reportsData  = reports.reports  || [];
            _welfareData  = welfare.welfare  || [];

            renderKPIs(summary);
            renderActiveEvent(summary);
            renderStreetMarkers();
            renderEvacMarkers();
            renderReportMarkers();
            renderWelfareMarkers();
            renderRecentReports();
            updateLastUpdated(summary.last_updated);

        } catch (err) {
            console.error('[DisasterMap]', err.message);
        }
    }

    // ─────────────────────────────────────────────────────
    //  3. KPI STRIP
    // ─────────────────────────────────────────────────────
    function renderKPIs(s) {
        const set = (id, val) => {
            const el = document.getElementById(id);
            if (!el) return;
            el.textContent = val;
            el.classList.remove('sk-loading');
        };

        set('dm-kpi-red',      s.streets_red    ?? '—');
        set('dm-kpi-orange',   s.streets_orange ?? '—');
        set('dm-kpi-reports',  s.open_reports   ?? '—');
        set('dm-kpi-evac',     s.evac_centers   ?? '—');
        set('dm-kpi-affected', fmt(s.affected_persons));

        // Quick stats in panel
        const qp = n => { const e = document.getElementById(n); if (e) e.classList.remove('sk-loading'); };
        const setQ = (id, val) => {
            const el = document.getElementById(id);
            if (el) { el.textContent = val; el.classList.remove('sk-loading'); }
        };
        setQ('dmQsPersons', fmt(s.affected_persons));
        setQ('dmQsHH',      fmt(s.affected_households));
        setQ('dmQsWelfare', s.active_welfare ?? '—');
    }

    function renderActiveEvent(s) {
        if (!s.active_event) return;
        const ev = s.active_event;
        const banner = document.getElementById('dm-alert-banner');
        if (banner) {
            document.getElementById('dm-alert-name').textContent = ev.event_name;
            document.getElementById('dm-alert-local').textContent = ev.local_name ? `(${ev.local_name})` : '';
            document.getElementById('dm-alert-cat').textContent  = ev.category   ?? '—';
            document.getElementById('dm-alert-kph').textContent  = ev.wind_speed_kph ? fmt(ev.wind_speed_kph) : '—';
            banner.hidden = false;
        }

        const panelEvent = document.getElementById('dmPanelEvent');
        if (panelEvent) {
            document.getElementById('dmPanelEventName').textContent = ev.event_name;
            document.getElementById('dmPanelEventMeta').textContent =
                `Cat. ${ev.category ?? '—'} · ${ev.wind_speed_kph ? fmt(ev.wind_speed_kph) + ' km/h' : '—'}`;
            panelEvent.hidden = false;
        }
    }

    function updateLastUpdated(ts) {
        const el = document.getElementById('dm-last-updated');
        if (!el || !ts) return;
        const d = new Date(ts.replace(' ', 'T'));
        el.textContent = d.toLocaleString('en-PH', {
            month: 'short', day: 'numeric', year: 'numeric',
            hour: 'numeric', minute: '2-digit',
        });
        el.classList.remove('sk-loading', 'sk-inline');
    }

    // ─────────────────────────────────────────────────────
    //  4. STREET MARKERS
    // ─────────────────────────────────────────────────────
    function renderStreetMarkers() {
        _layers.streets.clearLayers();

        const filtered = _riskFilter === 'all'
            ? _streetsData
            : _streetsData.filter(s => s.current_risk_level === _riskFilter);

        filtered.forEach(street => {
            const level = (street.current_risk_level || 'GREEN').toUpperCase();
            const color = RISK_COLOR[level] || '#6b7280';
            const isRed = level === 'RED';

            const icon = L.divIcon({
                className: '',
                html: `<div class="dm-street-marker${isRed ? ' dm-marker--pulse' : ''}"
                            style="width:14px;height:14px;background:${color}"></div>`,
                iconSize:   [14, 14],
                iconAnchor: [7, 7],
            });

            const marker = L.marker(
                [parseFloat(street.latitude), parseFloat(street.longitude)],
                { icon }
            ).addTo(_layers.streets);

            marker.on('click', () => showStreetPanel(street));

            marker.bindTooltip(`
                <strong>${esc(street.street_name)}</strong><br>
                <span style="font-size:11px;opacity:.7">${RISK_LABEL[level] || level}</span>
            `, { className: 'equiaid-tooltip', direction: 'top', offset: [0, -10] });
        });
    }

    // ─────────────────────────────────────────────────────
    //  5. EVAC MARKERS
    // ─────────────────────────────────────────────────────
    function renderEvacMarkers() {
        _layers.evac.clearLayers();
        _evacMarkers = [];

        _evacData.forEach(center => {
            const icon = L.divIcon({
                className: '',
                html: `<div class="ss-evac-icon"><i class="fa-solid fa-house-medical"></i></div>`,
                iconSize:   [32, 32],
                iconAnchor: [16, 28],
                popupAnchor:[0, -30],
            });

            const pct = center.capacity > 0
                ? Math.round(center.current_occupancy / center.capacity * 100)
                : 0;

            const marker = L.marker(
                [parseFloat(center.latitude), parseFloat(center.longitude)],
                { icon }
            )
            .addTo(_layers.evac)
            .bindPopup(`
                <div class="map-popup">
                    <div class="map-popup-badge map-popup-badge--evac">
                        <i class="fa-solid fa-house-medical"></i> Evacuation Center
                    </div>
                    <div class="map-popup-name">${esc(center.center_name)}</div>
                    ${center.address ? `<div class="map-popup-zone">${esc(center.address)}</div>` : ''}
                    <div class="map-popup-row">
                        <strong>Capacity</strong>
                        <span>${fmt(center.capacity)} persons</span>
                    </div>
                    <div class="map-popup-row">
                        <strong>Occupancy</strong>
                        <span>${fmt(center.current_occupancy)} (${pct}%)</span>
                    </div>
                    ${center.contact_number ? `<div class="map-popup-row"><strong>Contact</strong><span>${esc(center.contact_number)}</span></div>` : ''}
                    <div style="height:4px;background:#e5e7eb;border-radius:2px;margin:6px 0">
                        <div style="height:100%;width:${pct}%;background:${pct>80?'#dc2626':pct>50?'#d97706':'#16a34a'};border-radius:2px"></div>
                    </div>
                </div>
            `, { className: 'equiaid-popup' })
            .on('click', () => showEvacPanel(center));

            _evacMarkers.push({ marker, data: center });
        });
    }

    // ─────────────────────────────────────────────────────
    //  6. REPORT MARKERS
    // ─────────────────────────────────────────────────────
    function renderReportMarkers() {
        _layers.reports.clearLayers();

        _reportsData.forEach(report => {
            if (!report.latitude || !report.longitude) return;

            const icon = L.divIcon({
                className: '',
                html: `<div class="dm-report-marker"><i class="fa-solid fa-flag"></i></div>`,
                iconSize:   [28, 28],
                iconAnchor: [14, 28],
                popupAnchor:[0, -30],
            });

            L.marker(
                [parseFloat(report.latitude), parseFloat(report.longitude)],
                { icon }
            )
            .addTo(_layers.reports)
            .on('click', () => showReportPanel(report));
        });
    }

    // ─────────────────────────────────────────────────────
    //  7. WELFARE MARKERS
    // ─────────────────────────────────────────────────────
    function renderWelfareMarkers() {
        _layers.welfare.clearLayers();

        _welfareData.forEach(plan => {
            if (!plan.latitude || !plan.longitude) return;

            const icon = L.divIcon({
                className: '',
                html: `<div class="dm-welfare-marker"><i class="fa-solid fa-hand-holding-heart"></i></div>`,
                iconSize:   [28, 28],
                iconAnchor: [14, 14],
                popupAnchor:[0, -18],
            });

            L.marker(
                [parseFloat(plan.latitude), parseFloat(plan.longitude)],
                { icon }
            )
            .addTo(_layers.welfare)
            .on('click', () => showWelfarePanel(plan));
        });
    }

    // ─────────────────────────────────────────────────────
    //  8. PANEL: STREET DETAIL
    // ─────────────────────────────────────────────────────
    function showStreetPanel(s) {
        const level = (s.current_risk_level || 'GREEN').toUpperCase();
        const score = parseFloat(s.current_vuln_score || 0).toFixed(0);

        // Fetch welfare plans for this street
        const welfare = _welfareData.filter(w => w.street_id == s.street_id);

        let welfareHtml = '';
        if (welfare.length) {
            welfareHtml = `
                <div class="dmpd-section-title">Welfare Plans</div>
                ${welfare.map(w => `
                    <div class="dmpd-welfare-item">
                        <div class="dmpd-welfare-dot dmpd-welfare-dot--${(w.status || '').toLowerCase()}"></div>
                        <div>
                            <div class="dmpd-welfare-type">${esc(w.assistance_type)}</div>
                            <div class="dmpd-welfare-desc">${esc(w.description || '—')}</div>
                        </div>
                    </div>
                `).join('')}
            `;
        }

        // Fetch reports for this street
        const reports = _reportsData.filter(r => r.street_id == s.street_id);
        let reportsHtml = '';
        if (reports.length) {
            reportsHtml = `
                <div class="dmpd-section-title">Recent Reports (${reports.length})</div>
                ${reports.map(r => `
                    <div class="dmpd-row">
                        <span>${esc(r.report_type)} — ${esc(r.severity)}</span>
                        <span class="dmpd-status-badge dmpd-status-badge--${statusClass(r.status)}">${esc(r.status)}</span>
                    </div>
                `).join('')}
            `;
        }

        setPanelContent(`
            <div class="dmpd-risk-banner ${riskBannerClass(level)}">
                <div>
                    <div class="dmpd-risk-name">${esc(s.street_name)}</div>
                    <div class="dmpd-risk-zone">${esc(s.barangay)} · Zone ${esc(s.zone_id)}</div>
                </div>
                <div class="dmpd-risk-badge ${riskBadgeClass(level)}">
                    <i class="fa-solid ${RISK_ICON[level]}"></i>
                    ${RISK_LABEL[level] || level}
                </div>
            </div>

            <div class="dmpd-stats">
                <div class="dmpd-stat">
                    <span class="dmpd-stat-val">${score}</span>
                    <span class="dmpd-stat-lbl">Vuln. Score</span>
                </div>
                <div class="dmpd-stat">
                    <span class="dmpd-stat-val">${esc(s.needs_welfare || '—')}</span>
                    <span class="dmpd-stat-lbl">Needs Welfare</span>
                </div>
                <div class="dmpd-stat">
                    <span class="dmpd-stat-val">${fmt(s.total_population)}</span>
                    <span class="dmpd-stat-lbl">Population</span>
                </div>
                <div class="dmpd-stat">
                    <span class="dmpd-stat-val">${fmt(s.total_households)}</span>
                    <span class="dmpd-stat-lbl">Households</span>
                </div>
            </div>

            ${welfareHtml}
            ${reportsHtml}

            <div class="dmpd-action">
                <a href="report.php?street_id=${s.street_id}" class="btn btn-primary" style="width:100%;justify-content:center">
                    <i class="fa-solid fa-flag"></i> Report Incident Here
                </a>
            </div>
        `);

        // Pan map to marker
        _map.panTo([parseFloat(s.latitude), parseFloat(s.longitude)]);
    }

    // ─────────────────────────────────────────────────────
    //  9. PANEL: EVACUATION CENTER DETAIL
    // ─────────────────────────────────────────────────────
    function showEvacPanel(c) {
        const pct = c.capacity > 0
            ? Math.round(c.current_occupancy / c.capacity * 100) : 0;
        const fillClass = pct > 80 ? 'dmpd-capacity-fill--full'
                        : pct > 50 ? 'dmpd-capacity-fill--high' : '';

        setPanelContent(`
            <div class="dmpd-evac-header">
                <div class="dmpd-evac-icon"><i class="fa-solid fa-house-medical"></i></div>
                <div>
                    <div class="dmpd-evac-name">${esc(c.center_name)}</div>
                    <div class="dmpd-evac-addr">${esc(c.address || '—')}</div>
                </div>
            </div>

            <div class="dmpd-capacity-bar-wrap">
                <div class="dmpd-capacity-label">
                    <span>Occupancy</span>
                    <span>${fmt(c.current_occupancy)} / ${fmt(c.capacity)} persons (${pct}%)</span>
                </div>
                <div class="dmpd-capacity-bar">
                    <div class="dmpd-capacity-fill ${fillClass}" style="width:${pct}%"></div>
                </div>
            </div>

            <div class="dmpd-section-title">Details</div>
            <div class="dmpd-row">
                <span>Zone</span>
                <span>Zone ${esc(c.zone_id) || '—'}</span>
            </div>
            <div class="dmpd-row">
                <span>Contact Person</span>
                <span>${esc(c.contact_person || '—')}</span>
            </div>
            <div class="dmpd-row">
                <span>Contact Number</span>
                <span>${esc(c.contact_number || '—')}</span>
            </div>
            <div class="dmpd-row">
                <span>Status</span>
                <span>${c.is_active ? '<span style="color:#16a34a;font-weight:600">Active</span>' : '<span style="color:#dc2626">Inactive</span>'}</span>
            </div>
        `);

        _map.panTo([parseFloat(c.latitude), parseFloat(c.longitude)]);
    }

    // ─────────────────────────────────────────────────────
    //  10. PANEL: REPORT DETAIL
    // ─────────────────────────────────────────────────────
    function showReportPanel(r) {
        const statusCls = statusClass(r.status);

        setPanelContent(`
            <div class="dmpd-report-header">
                <div class="dmpd-report-icon"><i class="fa-solid fa-water"></i></div>
                <div>
                    <div class="dmpd-report-type">${esc(r.report_type)}</div>
                    <span class="dmpd-status-badge dmpd-status-badge--${statusCls}">${esc(r.status)}</span>
                </div>
            </div>

            <div class="dmpd-section-title">Report Details</div>
            <div class="dmpd-row">
                <span>Street</span>
                <span>${esc(r.street_name || '—')}</span>
            </div>
            <div class="dmpd-row">
                <span>Severity</span>
                <span style="font-weight:600;color:${r.severity === 'Severe' ? '#dc2626' : r.severity === 'Moderate' ? '#d97706' : '#6b7280'}">${esc(r.severity)}</span>
            </div>
            <div class="dmpd-row">
                <span>Reported</span>
                <span>${esc(formatDate(r.created_at))}</span>
            </div>
            ${r.description ? `
            <div class="dmpd-section-title">Description</div>
            <p style="font-size:12.5px;color:var(--text-muted);line-height:1.65;margin:0">${esc(r.description)}</p>
            ` : ''}
        `);
    }

    // ─────────────────────────────────────────────────────
    //  11. PANEL: WELFARE DETAIL
    // ─────────────────────────────────────────────────────
    function showWelfarePanel(plan) {
        const streetPlans = _welfareData.filter(w => w.street_id == plan.street_id);

        setPanelContent(`
            <div style="margin-bottom:16px">
                <div style="font-size:9px;font-weight:700;letter-spacing:.14em;text-transform:uppercase;color:var(--slate-mid);margin-bottom:4px">Welfare Deployment</div>
                <div style="font-size:20px;color:var(--navy);margin-bottom:2px">${esc(plan.street_name || 'Unknown Street')}</div>
            </div>

            <div class="dmpd-section-title">Active Plans</div>
            ${streetPlans.map(w => `
                <div class="dmpd-welfare-item">
                    <div class="dmpd-welfare-dot dmpd-welfare-dot--${(w.status || 'planned').toLowerCase()}"></div>
                    <div>
                        <div class="dmpd-welfare-type">${esc(w.assistance_type)} <span style="font-weight:400;color:var(--slate-mid);font-size:11px">(${esc(w.status)})</span></div>
                        <div class="dmpd-welfare-desc">${esc(w.description || '—')}</div>
                        ${w.planned_date ? `<div style="font-size:11px;color:var(--slate-light);margin-top:2px"><i class="fa-regular fa-calendar" style="margin-right:3px"></i>${esc(w.planned_date)}</div>` : ''}
                    </div>
                </div>
            `).join('')}
        `);
    }

    // ─────────────────────────────────────────────────────
    //  12. RECENT REPORTS STRIP
    // ─────────────────────────────────────────────────────
    function renderRecentReports() {
        const grid = document.getElementById('dmReportsGrid');
        if (!grid) return;

        if (!_reportsData.length) {
            grid.innerHTML = `<p style="color:var(--text-muted);font-size:14px;grid-column:1/-1">No recent reports found.</p>`;
            return;
        }

        const recent = _reportsData.slice(0, 6);
        grid.innerHTML = recent.map(r => {
            const sevClass = (r.severity || '').toLowerCase();
            return `
                <div class="dm-report-card" data-report-id="${r.report_id}">
                    <div class="drc-head">
                        <span class="drc-type">
                            <i class="fa-solid fa-water"></i>
                            ${esc(r.report_type)}
                        </span>
                        <span class="drc-sev drc-sev--${sevClass}">${esc(r.severity)}</span>
                    </div>
                    <div class="drc-street">${esc(r.street_name || '—')}</div>
                    ${r.description ? `<div class="drc-desc">${esc(r.description)}</div>` : ''}
                    <div class="drc-foot">
                        <span><i class="fa-regular fa-clock"></i>${esc(formatDate(r.created_at))}</span>
                        <span class="dmpd-status-badge dmpd-status-badge--${statusClass(r.status)}">${esc(r.status)}</span>
                    </div>
                </div>
            `;
        }).join('');

        // Click report cards → pan map + show panel
        grid.querySelectorAll('.dm-report-card').forEach(card => {
            card.addEventListener('click', () => {
                const id = parseInt(card.dataset.reportId);
                const report = _reportsData.find(r => r.report_id == id);
                if (report) {
                    showReportPanel(report);
                    if (report.latitude && report.longitude) {
                        _map.panTo([parseFloat(report.latitude), parseFloat(report.longitude)], { animate: true });
                    }
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                }
            });
        });
    }

    // ─────────────────────────────────────────────────────
    //  13. PANEL HELPERS
    // ─────────────────────────────────────────────────────
    function setPanelContent(html) {
        const def    = document.getElementById('dmPanelDefault');
        const detail = document.getElementById('dmPanelDetail');
        const content= document.getElementById('dmPanelContent');
        if (def)     def.hidden    = true;
        if (detail)  detail.hidden = false;
        if (content) content.innerHTML = html;
    }

    document.getElementById('dmPanelBack')?.addEventListener('click', () => {
        document.getElementById('dmPanelDefault').hidden = false;
        document.getElementById('dmPanelDetail').hidden  = true;
    });

    function statusClass(status) {
        const map = {
            'Pending': 'pending', 'Verified': 'verified',
            'In Progress': 'progress', 'Resolved': 'resolved', 'Dismissed': 'resolved',
        };
        return map[status] || 'pending';
    }

    function formatDate(ts) {
        if (!ts) return '—';
        const d = new Date(ts.replace(' ', 'T'));
        return d.toLocaleString('en-PH', { month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' });
    }

    // ─────────────────────────────────────────────────────
    //  14. LAYER SELECT DROPDOWN
    // ─────────────────────────────────────────────────────
    const _layerTrigger  = document.getElementById('dmLayerTrigger');
    const _layerDropdown = document.getElementById('dmLayerDropdown');

    // Toggle dropdown open/close
    _layerTrigger?.addEventListener('click', e => {
        e.stopPropagation();
        const isOpen = !_layerDropdown.hidden;
        _layerDropdown.hidden = isOpen;
        _layerTrigger.classList.toggle('active', !isOpen);
    });

    // Close when clicking outside
    document.addEventListener('click', e => {
        if (!e.target.closest('#dmLayerSelectBox')) {
            if (_layerDropdown) _layerDropdown.hidden = true;
            if (_layerTrigger) _layerTrigger.classList.remove('active');
        }
    });

    // Checkbox changes
    document.querySelectorAll('.dm-select-option input[data-layer]').forEach(cb => {
        cb.addEventListener('change', () => {
            const layer = cb.dataset.layer;
            if (!_layers[layer]) return;
            _visible[layer] = cb.checked;
            cb.closest('.dm-select-option').classList.toggle('is-checked', cb.checked);
            if (cb.checked) {
                _map.addLayer(_layers[layer]);
            } else {
                _map.removeLayer(_layers[layer]);
            }
            updateLayerTriggerText();
        });
    });

    // Set initial checked style
    document.querySelectorAll('.dm-select-option input[data-layer]').forEach(cb => {
        cb.closest('.dm-select-option').classList.toggle('is-checked', cb.checked);
    });

    function updateLayerTriggerText() {
        const checked = [...document.querySelectorAll('.dm-select-option input[data-layer]')]
            .filter(cb => cb.checked)
            .map(cb => {
                const labels = { streets: 'Streets', evac: 'Evac', reports: 'Reports', welfare: 'Welfare' };
                return labels[cb.dataset.layer] || cb.dataset.layer;
            });
        const textEl = document.getElementById('dmLayerTriggerText');
        if (!textEl) return;
        if (checked.length === 4) {
            textEl.textContent = 'All Layers';
        } else if (checked.length === 0) {
            textEl.textContent = 'No Layers';
        } else {
            textEl.textContent = checked.join(', ');
        }
    }

    // ─────────────────────────────────────────────────────
    //  15. RISK FILTER
    // ─────────────────────────────────────────────────────
    document.querySelectorAll('.dm-risk-pill').forEach(pill => {
        pill.addEventListener('click', () => {
            document.querySelectorAll('.dm-risk-pill').forEach(p => p.classList.remove('active'));
            pill.classList.add('active');
            _riskFilter = pill.dataset.risk;
            renderStreetMarkers();
        });
    });

    // ─────────────────────────────────────────────────────
    //  16. MY LOCATION
    // ─────────────────────────────────────────────────────
    const locateBtn = document.getElementById('dmLocateBtn');

    locateBtn?.addEventListener('click', () => {
        if (_userLat !== null && _userLng !== null) {
            drawRouteToNearestEvac(_userLat, _userLng);
            return;
        }

        if (!navigator.geolocation) {
            alert('Geolocation is not supported by this browser.');
            return;
        }

        locateBtn.disabled = true;
        locateBtn.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin"></i> Locating…';

        const onSuccess = pos => {
            _userLat = pos.coords.latitude;
            _userLng = pos.coords.longitude;
            placeUserMarker(_userLat, _userLng);
            drawRouteToNearestEvac(_userLat, _userLng);
            locateBtn.disabled = false;
            locateBtn.innerHTML = '<i class="fa-solid fa-location-crosshairs"></i> My Location';
        };

        const onError = err => {
            if (err.code === 2) {
                navigator.geolocation.getCurrentPosition(
                    onSuccess,
                    () => {
                        locateBtn.disabled = false;
                        locateBtn.innerHTML = '<i class="fa-solid fa-location-crosshairs"></i> My Location';
                    },
                    { enableHighAccuracy: false, timeout: 10000, maximumAge: 60000 }
                );
                return;
            }
            locateBtn.disabled = false;
            locateBtn.innerHTML = '<i class="fa-solid fa-location-crosshairs"></i> My Location';
        };

        navigator.geolocation.getCurrentPosition(
            onSuccess, onError,
            { enableHighAccuracy: true, timeout: 10000, maximumAge: 30000 }
        );
    });

    // Wire clear-route button (add id="dmClearRoute" to your HTML button)
    document.getElementById('dmClearRoute')?.addEventListener('click', clearDmRoute);

    function placeUserMarker(lat, lng) {
        if (_userMarker) _map.removeLayer(_userMarker);
        const icon = L.divIcon({
            className: '',
            html: `<div class="dm-user-marker"><i class="fa-solid fa-person"></i></div>`,
            iconSize:   [32, 32],
            iconAnchor: [16, 28],
            popupAnchor:[0, -30],
        });
        _userMarker = L.marker([lat, lng], { icon, zIndexOffset: 1000 })
            .addTo(_map)
            .bindPopup('<div class="map-popup"><div class="map-popup-name" style="color:#0f1f3d"><i class="fa-solid fa-person" style="margin-right:6px"></i>Your Location</div></div>',
                       { className: 'equiaid-popup' });
        const legend = document.getElementById('dmUserLegend');
        if (legend) legend.hidden = false;
    }

    // ── Haversine distance (metres) ───────────────────────
    function haversineDm(lat1, lng1, lat2, lng2) {
        const R = 6371000;
        const φ1 = lat1 * Math.PI / 180, φ2 = lat2 * Math.PI / 180;
        const Δφ = (lat2 - lat1) * Math.PI / 180;
        const Δλ = (lng2 - lng1) * Math.PI / 180;
        const a = Math.sin(Δφ/2)**2 + Math.cos(φ1)*Math.cos(φ2)*Math.sin(Δλ/2)**2;
        return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
    }

    function clearDmRouteLayer() {
        [_routeGlow, _routeLine, _routeDash].forEach(l => {
            if (l && _map) { try { _map.removeLayer(l); } catch(_){} }
        });
        _routeGlow = _routeLine = _routeDash = null;
    }

    function clearDmRoute() {
        clearDmRouteLayer();
        if (_userMarker && _map) { try { _map.removeLayer(_userMarker); } catch(_){} _userMarker = null; }
        _userLat = null;
        _userLng = null;
        const panel    = document.getElementById('dmRoutePanel');
        const clearBtn = document.getElementById('dmClearRoute');
        const legend   = document.getElementById('dmUserLegend');
        if (panel)    panel.hidden    = true;
        if (clearBtn) clearBtn.hidden = true;
        if (legend)   legend.hidden   = true;
        if (_map) _map.closePopup();
    }

    function drawRouteToNearestEvac(userLat, userLng) {
        if (!_evacMarkers.length) return;
        clearDmRouteLayer();

        // Find nearest evac by straight-line distance
        let nearest = null, minDist = Infinity;
        _evacMarkers.forEach(({ data }) => {
            const d = haversineDm(userLat, userLng, parseFloat(data.latitude), parseFloat(data.longitude));
            if (d < minDist) { minDist = d; nearest = data; }
        });
        if (!nearest) return;

        const destLat = parseFloat(nearest.latitude);
        const destLng = parseFloat(nearest.longitude);
        const coords  = [[userLat, userLng], [destLat, destLng]];

        // 3-layer Lalamove-style line (straight, instant)
        _routeGlow = L.polyline(coords, { color:'#1d4ed8', weight:12, opacity:0.15, lineCap:'round', lineJoin:'round' }).addTo(_map);
        _routeLine = L.polyline(coords, { color:'#1d4ed8', weight:5,  opacity:1,    lineCap:'round', lineJoin:'round' }).addTo(_map);
        _routeDash = L.polyline(coords, { color:'#ffffff', weight:2,  opacity:0.7,  dashArray:'10,16', lineCap:'round' }).addTo(_map);

        _map.fitBounds(L.latLngBounds(coords), { padding:[60,60], maxZoom:17 });

        // Update route info panel (id="dmRoutePanel" in your HTML)
        const distKm   = (minDist / 1000).toFixed(2);
        const timeMins = Math.ceil(minDist / 83); // ~5 km/h walking
        const panel    = document.getElementById('dmRoutePanel');
        if (panel) panel.hidden = false;
        const destEl = document.getElementById('dmRouteDest');
        if (destEl) destEl.textContent = nearest.center_name;
        const distEl = document.getElementById('dmRouteDist');
        if (distEl) distEl.innerHTML = `<i class="fa-solid fa-route"></i> ${distKm} km`;
        const timeEl = document.getElementById('dmRouteTime');
        if (timeEl) timeEl.innerHTML = `<i class="fa-regular fa-clock"></i> ~${timeMins} min on foot`;
        const clearBtn = document.getElementById('dmClearRoute');
        if (clearBtn) clearBtn.hidden = false;

        // Open evac popup
        const entry = _evacMarkers.find(m => m.data.center_id == nearest.center_id);
        if (entry) entry.marker.openPopup();

        // Background upgrade: try OSRM road-following route
        fetchDmOsrmRoute(userLat, userLng, destLat, destLng)
            .then(roadCoords => {
                if (!roadCoords || !_routeLine) return;
                clearDmRouteLayer();
                _routeGlow = L.polyline(roadCoords, { color:'#1d4ed8', weight:12, opacity:0.15, lineCap:'round', lineJoin:'round' }).addTo(_map);
                _routeLine = L.polyline(roadCoords, { color:'#1d4ed8', weight:5,  opacity:1,    lineCap:'round', lineJoin:'round' }).addTo(_map);
                _routeDash = L.polyline(roadCoords, { color:'#ffffff', weight:2,  opacity:0.7,  dashArray:'10,16', lineCap:'round' }).addTo(_map);
                _map.fitBounds(L.latLngBounds(roadCoords), { padding:[60,60], maxZoom:17 });
            })
            .catch(() => {}); // silent — straight line already showing
    }

    async function fetchDmOsrmRoute(lat1, lng1, lat2, lng2) {
        const url = `https://router.project-osrm.org/route/v1/foot/${lng1},${lat1};${lng2},${lat2}?overview=full&geometries=geojson`;
        const res  = await fetch(url, { signal: AbortSignal.timeout(8000) });
        const json = await res.json();
        if (json.code !== 'Ok' || !json.routes?.length) return null;
        return json.routes[0].geometry.coordinates.map(([lng, lat]) => [lat, lng]);
    }

    // ─────────────────────────────────────────────────────
    //  17. STICKY TOOLBAR
    // ─────────────────────────────────────────────────────
    const toolbar = document.getElementById('dmToolbar');
    if (toolbar) {
        const observer = new IntersectionObserver(
            ([e]) => toolbar.classList.toggle('is-stuck', e.intersectionRatio < 1),
            { threshold: [1], rootMargin: '-1px 0px 0px 0px' }
        );
        observer.observe(toolbar);
    }


    // ─────────────────────────────────────────────────────
    //  18. WEATHER TILE LAYERS
    // ─────────────────────────────────────────────────────

    function toggleWeatherLayer(key) {
        const wl = WEATHER_LAYERS[key];
        if (!wl) return;

        wl.active = !wl.active;
        const btn = document.querySelector(`.dm-weather-btn[data-weather="${key}"]`);
        if (btn) btn.classList.toggle('active', wl.active);

        if (wl.active) {
            if (!wl.tile) {
                wl.tile = L.tileLayer(
                    `${OWM_BASE}/${wl.owmLayer}/{z}/{x}/{y}.png?appid=${OWM_KEY}`,
                    { opacity: 0.65, maxZoom: 19, zIndex: 200,
                      attribution: '© <a href="https://openweathermap.org">OpenWeatherMap</a>' }
                );
            }
            wl.tile.addTo(_map);
        } else {
            if (wl.tile) _map.removeLayer(wl.tile);
        }
    }

    // Wire up weather buttons
    document.querySelectorAll('.dm-weather-btn').forEach(btn => {
        btn.addEventListener('click', () => toggleWeatherLayer(btn.dataset.weather));
    });

    // ─────────────────────────────────────────────────────
    //  19. CURRENT WEATHER WIDGET
    // ─────────────────────────────────────────────────────

    async function loadCurrentWeather() {
        const widget = document.getElementById('dmWeatherWidget');

        // Show widget immediately with loading state
        if (widget) widget.hidden = false;

        // No key set — show setup prompt and stop
        if (!OWM_KEY || OWM_KEY === 'YOUR_OWM_API_KEY') {
            showWeatherSetup();
            return;
        }

        // Barangay Bagong Silang, Caloocan City coordinates
        const lat = 14.7430, lon = 120.9845;

        // AbortSignal.timeout() fallback for older browsers
        let signal;
        try {
            signal = AbortSignal.timeout(8000);
        } catch (_) {
            const ctrl = new AbortController();
            setTimeout(() => ctrl.abort(), 8000);
            signal = ctrl.signal;
        }

        try {
            const res = await fetch(
                `https://api.openweathermap.org/data/2.5/weather?lat=${lat}&lon=${lon}&appid=${OWM_KEY}&units=metric`,
                { signal }
            );

            const data = await res.json();

            // OWM uses cod:401 for invalid key, cod:200 for success
            if (data.cod && String(data.cod) !== '200') {
                throw new Error(data.message || `OWM error ${data.cod}`);
            }

            _currentWeather = data;
            renderWeatherWidget(_currentWeather);

        } catch (err) {
            console.warn('[Weather]', err.message);
            showWeatherError(err.message);
        }
    }

    function showWeatherSetup() {
        const widget = document.getElementById('dmWeatherWidget');
        if (!widget) return;
        widget.querySelector('.dm-wx-body').innerHTML = `
            <div class="dm-wx-setup">
                <i class="fa-solid fa-key"></i>
                <p>Add your <strong>OpenWeatherMap API key</strong> to <code>disaster_map.js</code> to enable live weather.</p>
                <a href="https://openweathermap.org/api" target="_blank" rel="noopener" class="dm-wx-setup-link">
                    Get a free key <i class="fa-solid fa-arrow-up-right-from-square"></i>
                </a>
            </div>
        `;
        widget.hidden = false;
    }

    function showWeatherError(msg) {
        const widget = document.getElementById('dmWeatherWidget');
        if (!widget) return;
        widget.querySelector('.dm-wx-body').innerHTML = `
            <div class="dm-wx-setup">
                <i class="fa-solid fa-triangle-exclamation" style="color:#fca5a5"></i>
                <p>Weather unavailable: <strong>${esc(msg)}</strong></p>
            </div>
        `;
        widget.hidden = false;
    }

    function renderWeatherWidget(w) {
        const widget = document.getElementById('dmWeatherWidget');
        if (!widget) return;

        const temp    = Math.round(w.main?.temp ?? 0);
        const feels   = Math.round(w.main?.feels_like ?? 0);
        const desc    = w.weather?.[0]?.description ?? '—';
        const icon    = w.weather?.[0]?.icon ?? '01d';
        const humidity= w.main?.humidity ?? '—';
        const windSpd = Math.round((w.wind?.speed ?? 0) * 3.6); // m/s → km/h
        const windDir = compassDir(w.wind?.deg ?? 0);
        const visibility = w.visibility ? (w.visibility / 1000).toFixed(1) + ' km' : '—';
        const rain1h  = w.rain?.['1h'] ? w.rain['1h'].toFixed(1) + ' mm' : '—';

        document.getElementById('dmWxTemp').textContent    = `${temp}°C`;
        document.getElementById('dmWxFeels').textContent   = `Feels like ${feels}°C`;
        document.getElementById('dmWxDesc').textContent    = capitalise(desc);
        document.getElementById('dmWxHumidity').textContent = `${humidity}%`;
        document.getElementById('dmWxWind').textContent    = `${windSpd} km/h ${windDir}`;
        document.getElementById('dmWxVisibility').textContent = visibility;
        document.getElementById('dmWxRain').textContent   = rain1h;

        const iconEl = document.getElementById('dmWxIcon');
        if (iconEl) iconEl.src = `https://openweathermap.org/img/wn/${icon}@2x.png`;

        // Flood-risk weather alert
        const alertEl = document.getElementById('dmWxAlert');
        if (alertEl) {
            const isHeavyRain = (w.rain?.['1h'] ?? 0) > 5 || desc.includes('heavy') || desc.includes('storm');
            const isStrongWind = windSpd > 60;
            if (isHeavyRain || isStrongWind) {
                alertEl.textContent = isHeavyRain
                    ? '⚠ Heavy rainfall — elevated flood risk'
                    : '⚠ Strong winds detected';
                alertEl.hidden = false;
            }
        }

        widget.hidden = false;
    }

    function compassDir(deg) {
        const dirs = ['N','NE','E','SE','S','SW','W','NW'];
        return dirs[Math.round(deg / 45) % 8];
    }

    function capitalise(str) {
        return str.charAt(0).toUpperCase() + str.slice(1);
    }

    // ─────────────────────────────────────────────────────
    //  INIT
    // ─────────────────────────────────────────────────────
    document.addEventListener('DOMContentLoaded', () => {
        initMap();
        loadAll();
        loadCurrentWeather();
    });

})();