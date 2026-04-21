/**
 * assistance.js — Barangay EQUIAID Assistance Page
 * Pagination mirrors street_status.js renderPagination() exactly.
 */
'use strict';

const AS_API = '../backend/assistance.php';

// ── Safe fetch ──────────────────────────────────────────
async function asFetch(action) {
    const res = await fetch(`${AS_API}?action=${action}`, {
        headers: { 'Accept': 'application/json' }   // ← add this
    });
    if (res.status === 401) {
        window.location.href = '../components/login.php?session_expired=1';
        throw new Error('Not authenticated');
    }
    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    const data = await res.json();
    if (!data.ok) throw new Error(data.error || 'API error');
    return data;
}
// ── Helpers ─────────────────────────────────────────────
const fmt    = n => Number(n || 0).toLocaleString();
const fmtPHP = n => '₱' + Number(n || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

function esc(str) {
    if (str == null) return '';
    return String(str)
        .replace(/&/g,'&amp;').replace(/</g,'&lt;')
        .replace(/>/g,'&gt;').replace(/"/g,'&quot;')
        .replace(/'/g,'&#39;');
}

function fmtDate(ts) {
    if (!ts) return '—';
    return new Date(ts.replace(' ','T')).toLocaleString('en-PH', {
        month:'short', day:'numeric', year:'numeric'
    });
}

// ── Pagination state ────────────────────────────────────
const PAGE_SIZE   = 5;   // plans per page
let _currentPage  = 1;
let _visiblePlans = [];  // filtered slice — array of plan objects
let _allPlans     = [];
let _statusFilter = 'all';
let _typeFilter   = 'all';

// ─────────────────────────────────────────────────────
//  1. LOAD ALL
// ─────────────────────────────────────────────────────
async function loadAll() {
    try {
        const [summary, plans, distributions, resources, evac] = await Promise.all([
            asFetch('summary'),
            asFetch('plans'),
            asFetch('distributions'),
            asFetch('resources'),
            asFetch('evac'),
        ]);

        renderKPIs(summary);
        updateLastUpdated(summary.last_updated);

        // Hide skeleton BEFORE rendering real content — prevents layout gap
        const skel = document.getElementById('asPlansGridSkeleton');
        if (skel) skel.hidden = true;

        _allPlans = plans.plans || [];
        applyFilters();   // renders plans + pagination

        renderDistributions(distributions.distributions || []);
        renderInventory(resources.resources || []);
        renderEvac(evac.centers || []);
        renderBreakdown(_allPlans);

    } catch (err) {
        console.error('[Assistance]', err.message);
    }
}

// ─────────────────────────────────────────────────────
//  2. KPIs
// ─────────────────────────────────────────────────────
function renderKPIs(s) {
    const set = (id, val) => {
        const el = document.getElementById(id);
        if (!el) return;
        el.textContent = val;
        el.classList.remove('sk-loading');
    };
    set('as-kpi-plans',        s.active_plans      ?? '—');
    set('as-kpi-distributed',  fmt(s.total_distributed));
    set('as-kpi-families',     fmt(s.families_served));
    set('as-kpi-budget',       fmtPHP(s.total_disbursed));
    set('as-kpi-evac',         `${s.evac_occupancy ?? 0}%`);
}

function updateLastUpdated(ts) {
    const el = document.getElementById('as-last-updated');
    if (!el || !ts) return;
    el.textContent = new Date(ts.replace(' ','T')).toLocaleString('en-PH', {
        month:'short', day:'numeric', year:'numeric',
        hour:'numeric', minute:'2-digit',
    });
    el.classList.remove('sk-loading','sk-inline');
}

// ─────────────────────────────────────────────────────
//  3. FILTER + PAGINATE
// ─────────────────────────────────────────────────────
function applyFilters() {
    _visiblePlans = _allPlans.filter(p => {
        const matchStatus = _statusFilter === 'all' || p.status === _statusFilter;
        const matchType   = _typeFilter   === 'all' || p.assistance_type === _typeFilter;
        return matchStatus && matchType;
    });

    _currentPage = 1;

    const totalEl   = document.getElementById('asTotalCount');
    const visibleEl = document.getElementById('asVisibleCount');
    const bar       = document.getElementById('asResultsBar');
    if (totalEl)   totalEl.textContent   = _allPlans.length;
    if (visibleEl) visibleEl.textContent = _visiblePlans.length;
    if (bar)       bar.hidden = false;

    renderPage();
}

// ─────────────────────────────────────────────────────
//  4. RENDER PAGE  (mirrors street_status.js renderPage)
// ─────────────────────────────────────────────────────
function renderPage() {
    const grid      = document.getElementById('asPlansGrid');
    const emptyEl   = document.getElementById('asPlansEmpty');
    if (!grid) return;

    const total      = _visiblePlans.length;
    const totalPages = Math.max(1, Math.ceil(total / PAGE_SIZE));
    _currentPage     = Math.min(_currentPage, totalPages);

    const start = (_currentPage - 1) * PAGE_SIZE;
    const end   = Math.min(start + PAGE_SIZE, total);
    const slice = _visiblePlans.slice(start, end);

    // Empty state
    const isEmpty = total === 0;
    grid.hidden   = isEmpty;
    if (emptyEl) emptyEl.hidden = !isEmpty;

    if (!isEmpty) {
        grid.innerHTML = slice.map((p, i) => buildPlanCard(p, i)).join('');
    }

    renderPagination(totalPages);
}

// ─────────────────────────────────────────────────────
//  5. PAGINATION  (exact copy of street_status.js logic)
// ─────────────────────────────────────────────────────
function renderPagination(totalPages) {
    const wrap = document.getElementById('asPagination');
    if (!wrap) return;

    if (totalPages <= 1) {
        wrap.hidden = true;
        wrap.innerHTML = '';
        return;
    }

    wrap.hidden = false;
    const cp = _currentPage;

    // Compact page list with ellipsis — identical to street_status.js
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

    // Wire clicks
    wrap.querySelectorAll('[data-page]:not([disabled])').forEach(btn => {
        btn.addEventListener('click', () => {
            const pg = parseInt(btn.dataset.page, 10);
            if (!isNaN(pg) && pg >= 1 && pg <= totalPages && pg !== _currentPage) {
                _currentPage = pg;
                renderPage();
                document.getElementById('asPlansGrid')
                    ?.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        });
    });
}

// ─────────────────────────────────────────────────────
//  6. BUILD PLAN CARD HTML
// ─────────────────────────────────────────────────────
function buildPlanCard(p, i) {
    const statusKey = (p.status || '').toLowerCase();
    const riskKey   = (p.risk_level_before || '').toLowerCase();

    const TYPE_ICON = {
        'Food Distribution':  'fa-box-open',
        'Medical Assistance': 'fa-briefcase-medical',
        'Water Supply':       'fa-droplet',
        'Shelter Repair':     'fa-house-chimney-crack',
    };

    const icon = TYPE_ICON[p.assistance_type] || 'fa-hand-holding-heart';

    const dateLabel = p.status === 'Completed'
        ? `Completed ${fmtDate(p.completed_at)}`
        : p.status === 'Ongoing'
        ? `Started ${fmtDate(p.started_at)}`
        : `Planned ${fmtDate(p.planned_date)}`;

    return `
        <div class="as-plan-card as-plan-card--${esc(statusKey)}" style="animation-delay:${i * 0.05}s">
            <div class="as-plan-body">
                <div class="as-plan-meta">
                    <i class="fa-solid ${esc(icon)}"></i>
                    ${esc(p.street_name || '—')}${p.zone_id ? ` &middot; Zone ${esc(String(p.zone_id))}` : ''}
                </div>
                <div class="as-plan-type">${esc(p.assistance_type)}</div>
                ${p.description ? `<p class="as-plan-desc">${esc(p.description)}</p>` : ''}
                ${riskKey ? `
                <span class="as-risk-pill as-risk-pill--${esc(riskKey)}">
                    Before: ${esc(p.risk_level_before)}${p.vuln_score_before ? ` · ${parseFloat(p.vuln_score_before).toFixed(0)}` : ''}
                </span>` : ''}
            </div>
            <div class="as-plan-side">
                <span class="as-status-badge as-status-badge--${esc(statusKey)}">${esc(p.status)}</span>
                <div class="as-plan-date">${esc(dateLabel)}</div>
            </div>
        </div>`;
}

// ─────────────────────────────────────────────────────
//  7. FILTER LISTENERS
// ─────────────────────────────────────────────────────
document.getElementById('planStatusFilter')?.addEventListener('change', e => {
    _statusFilter = e.target.value;
    applyFilters();
});

document.getElementById('planTypeFilter')?.addEventListener('change', e => {
    _typeFilter = e.target.value;
    applyFilters();
});

document.getElementById('asClearFilters')?.addEventListener('click', () => {
    _statusFilter = 'all';
    _typeFilter   = 'all';
    const s = document.getElementById('planStatusFilter');
    const t = document.getElementById('planTypeFilter');
    if (s) s.value = 'all';
    if (t) t.value = 'all';
    applyFilters();
});

// ─────────────────────────────────────────────────────
//  8. DISTRIBUTION LOG
// ─────────────────────────────────────────────────────
function renderDistributions(rows) {
    const tbody = document.getElementById('asDistBody');
    if (!tbody) return;

    if (!rows.length) {
        tbody.innerHTML = `<tr><td colspan="6" style="text-align:center;padding:32px;color:var(--text-muted)">No distribution records found.</td></tr>`;
        return;
    }

    const CAT_ICON = {
        Food:      { icon: 'fa-box-open',            cls: 'food' },
        Medical:   { icon: 'fa-briefcase-medical',          cls: 'medical' },
        Water:     { icon: 'fa-droplet',              cls: 'water' },
        Shelter:   { icon: 'fa-house-chimney-crack',  cls: 'shelter' },
        Transport: { icon: 'fa-van-shuttle',          cls: 'transport' },
        Other:     { icon: 'fa-box',                  cls: 'other' },
    };

    tbody.innerHTML = rows.map(r => {
        const cat = CAT_ICON[r.category] || CAT_ICON.Other;
        return `
            <tr>
                <td><span class="as-dist-street">${esc(r.street_name || '—')}</span></td>
                <td>
                    <div class="as-dist-resource">
                        <div class="as-dist-resource-icon as-dist-resource-icon--${cat.cls}">
                            <i class="fa-solid ${cat.icon}"></i>
                        </div>
                        ${esc(r.resource_name)}
                    </div>
                </td>
                <td><span class="as-dist-qty">${fmt(r.qty_distributed)}</span> <span style="font-size:11px;color:var(--slate-light)">${esc(r.unit)}</span></td>
                <td style="color:var(--text-mid)">${fmt(r.recipient_count)} HH</td>
                <td><span class="as-dist-cost">${fmtPHP(r.total_cost)}</span></td>
                <td style="color:var(--slate-mid);font-size:12px;white-space:nowrap">${fmtDate(r.distributed_at)}</td>
            </tr>`;
    }).join('');
}

// ─────────────────────────────────────────────────────
//  9. RESOURCE INVENTORY
// ─────────────────────────────────────────────────────
function renderInventory(resources) {
    const list = document.getElementById('asInventoryList');
    if (!list) return;

    if (!resources.length) {
        list.innerHTML = '<p style="font-size:13px;color:var(--text-muted)">No resources found.</p>';
        return;
    }

    const CAT_ICON = {
        Food: 'fa-box-open', Medical: 'fa-briefcase-medical', Water: 'fa-droplet',
        Shelter: 'fa-house-chimney-crack', Transport: 'fa-van-shuttle', Other: 'fa-box',
    };

    list.innerHTML = resources.map(r => {
        const total  = (r.qty_available || 0) + (r.qty_distributed || 0);
        const pct    = total > 0 ? Math.round(r.qty_available / total * 100) : 0;
        const isLow  = r.qty_available <= r.restock_threshold;
        const isWarn = !isLow && r.qty_available <= r.restock_threshold * 2;
        const fillCls = isLow ? 'as-inv-fill--low' : isWarn ? 'as-inv-fill--warn' : '';
        const tagCls  = isLow ? 'as-inv-tag--low'  : isWarn ? 'as-inv-tag--warn'  : 'as-inv-tag--ok';
        const tagLbl  = isLow ? 'Low Stock'          : isWarn ? 'Monitor'           : 'In Stock';
        const icon    = CAT_ICON[r.category] || 'fa-box';

        return `
            <div class="as-inv-item">
                <div class="as-inv-head">
                    <span class="as-inv-name"><i class="fa-solid ${icon}"></i> ${esc(r.resource_name)}</span>
                    <span class="as-inv-tag ${tagCls}">${tagLbl}</span>
                </div>
                <div class="as-inv-counts">
                    <span>Available: <strong>${fmt(r.qty_available)} ${esc(r.unit)}</strong></span>
                    <span>Dist.: ${fmt(r.qty_distributed)}</span>
                </div>
                <div class="as-inv-bar">
                    <div class="as-inv-fill ${fillCls}" style="width:${pct}%"></div>
                </div>
            </div>`;
    }).join('');
}

// ─────────────────────────────────────────────────────
//  10. EVACUATION CENTERS
// ─────────────────────────────────────────────────────
function renderEvac(centers) {
    const list = document.getElementById('asEvacList');
    if (!list) return;

    if (!centers.length) {
        list.innerHTML = '<p style="font-size:13px;color:var(--text-muted)">No centers found.</p>';
        return;
    }

    list.innerHTML = centers.map(c => {
        const pct     = c.capacity > 0 ? Math.round(c.current_occupancy / c.capacity * 100) : 0;
        const fillCls = pct > 80 ? 'as-evac-fill--full' : pct > 50 ? 'as-evac-fill--warn' : '';
        return `
            <div class="as-evac-item">
                <div class="as-evac-name">${esc(c.center_name)}</div>
                <div class="as-evac-meta">
                    <span>${esc(c.address || 'Zone ' + c.zone_id)}</span>
                    <span class="as-evac-pct">${pct}%</span>
                </div>
                <div style="font-size:11px;color:var(--slate-light);margin-bottom:5px">${fmt(c.current_occupancy)} / ${fmt(c.capacity)} persons</div>
                <div class="as-evac-bar">
                    <div class="as-evac-fill ${fillCls}" style="width:${pct}%"></div>
                </div>
            </div>`;
    }).join('');
}

// ─────────────────────────────────────────────────────
//  11. BREAKDOWN BY TYPE
// ─────────────────────────────────────────────────────
function renderBreakdown(plans) {
    const list = document.getElementById('asBreakdownList');
    if (!list) return;

    const counts = {};
    plans.forEach(p => { counts[p.assistance_type] = (counts[p.assistance_type] || 0) + 1; });

    const sorted = Object.entries(counts).sort((a, b) => b[1] - a[1]);
    const max    = sorted[0]?.[1] || 1;

    const TYPE_ICON = {
        'Food Distribution':  'fa-box-open',
        'Medical Assistance': 'fa-briefcase-medical',
        'Water Supply':       'fa-droplet',
        'Shelter Repair':     'fa-house-chimney-crack',
    };

    if (!sorted.length) {
        list.innerHTML = '<p style="font-size:13px;color:var(--text-muted)">No data.</p>';
        return;
    }

    list.innerHTML = sorted.map(([type, count]) => `
        <div class="as-breakdown-item">
            <div class="as-breakdown-head">
                <span class="as-breakdown-label">
                    <i class="fa-solid ${TYPE_ICON[type] || 'fa-hand-holding-heart'}"></i>
                    ${esc(type)}
                </span>
                <span class="as-breakdown-count">${count}</span>
            </div>
            <div class="as-breakdown-bar">
                <div class="as-breakdown-fill" style="width:${Math.round(count / max * 100)}%"></div>
            </div>
        </div>`).join('');
}

// ─────────────────────────────────────────────────────
//  INIT
// ─────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', loadAll);