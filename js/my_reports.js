/**
 * my_reports.js — Barangay EQUIAID My Reports Page
 * Connects to: backend/my_reports.php
 */
'use strict';

const MR_API = '../backend/my_reports.php';

// ── Fetch helper ────────────────────────────────────────
async function mrFetch(action, params = '') {
    const url = `${MR_API}?action=${action}${params ? '&' + params : ''}`;
    const res  = await fetch(url);
    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    const data = await res.json();
    if (!data.ok) throw new Error(data.error || 'API error');
    return data;
}

// ── Helpers ─────────────────────────────────────────────
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
        month: 'short', day: 'numeric', year: 'numeric',
        hour: 'numeric', minute: '2-digit',
    });
}

function fmtDateShort(ts) {
    if (!ts) return '—';
    return new Date(ts.replace(' ','T')).toLocaleString('en-PH', {
        month: 'short', day: 'numeric', year: 'numeric',
    });
}

// ── Type → icon map ─────────────────────────────────────
const TYPE_ICON = {
    'Flood':             { icon: 'fa-water',         cls: 'flood' },
    'Damage':            { icon: 'fa-house-crack',   cls: 'damage' },
    'Blocked Road':      { icon: 'fa-road-barrier',  cls: 'blocked' },
    'Fire':              { icon: 'fa-fire',           cls: 'fire' },
    'Medical Emergency': { icon: 'fa-kit-medical',   cls: 'medical' },
    'Other':             { icon: 'fa-circle-exclamation', cls: 'other' },
};

// ── Status helpers ──────────────────────────────────────
function statusKey(status) {
    return (status || '').toLowerCase().replace(/\s+/g, '-');
}

// ── Pagination state ────────────────────────────────────
const PAGE_SIZE   = 6;
let _currentPage  = 1;
let _allReports   = [];
let _visible      = [];
let _searchQ      = '';
let _statusFilter = 'all';
let _sevFilter    = 'all';

// ─────────────────────────────────────────────────────
//  1. INIT LOAD
// ─────────────────────────────────────────────────────
async function loadReports() {
    try {
        const data = await mrFetch('my_reports');
        _allReports = data.reports || [];
        renderSummary(data.summary || {});
        applyFilters();

        // Hide skeletons, show list
        const skel = document.getElementById('mrSkeletons');
        if (skel) skel.hidden = true;

    } catch (err) {
        console.error('[MyReports]', err.message);
        const skel = document.getElementById('mrSkeletons');
        if (skel) skel.hidden = true;
        showEmpty('Could not load your reports. Please try again later.');
    }
}

// ─────────────────────────────────────────────────────
//  2. SUMMARY STRIP
// ─────────────────────────────────────────────────────
function renderSummary(s) {
    const set = (id, val) => {
        const el = document.getElementById(id);
        if (!el) return;
        el.textContent = val;
        el.classList.remove('sk-loading');
    };
    set('mrSumTotal',    s.total       ?? 0);
    set('mrSumPending',  s.pending     ?? 0);
    set('mrSumProgress', s.in_progress ?? 0);
    set('mrSumResolved', s.resolved    ?? 0);
    set('mrSumVerified', s.verified    ?? 0);
}

// ─────────────────────────────────────────────────────
//  3. FILTER
// ─────────────────────────────────────────────────────
function applyFilters() {
    _visible = _allReports.filter(r => {
        const matchSearch = !_searchQ ||
            (r.street_name || '').toLowerCase().includes(_searchQ) ||
            (r.description || '').toLowerCase().includes(_searchQ) ||
            (r.report_type || '').toLowerCase().includes(_searchQ);
        const matchStatus = _statusFilter === 'all' || r.status === _statusFilter;
        const matchSev    = _sevFilter    === 'all' || r.severity === _sevFilter;
        return matchSearch && matchStatus && matchSev;
    });

    _currentPage = 1;

    // Results label
    const label = document.getElementById('mrResultsLabel');
    const count = document.getElementById('mrVisibleCount');
    if (label) label.hidden = false;
    if (count) count.textContent = _visible.length;

    renderPage();
}

// ─────────────────────────────────────────────────────
//  4. RENDER PAGE
// ─────────────────────────────────────────────────────
function renderPage() {
    const list  = document.getElementById('mrList');
    const empty = document.getElementById('mrEmpty');
    const msg   = document.getElementById('mrEmptyMsg');
    const action= document.getElementById('mrEmptyAction');

    const total      = _visible.length;
    const totalPages = Math.max(1, Math.ceil(total / PAGE_SIZE));
    _currentPage     = Math.min(_currentPage, totalPages);

    const start = (_currentPage - 1) * PAGE_SIZE;
    const slice = _visible.slice(start, start + PAGE_SIZE);

    const isEmpty = total === 0;
    list.hidden   = isEmpty;
    if (empty) empty.hidden = !isEmpty;

    if (isEmpty) {
        const hasFilters = _searchQ || _statusFilter !== 'all' || _sevFilter !== 'all';
        if (msg) msg.textContent = hasFilters
            ? 'No reports match your current filters.'
            : 'You haven\'t submitted any reports yet.';
        if (action) {
            if (hasFilters) {
                action.textContent = 'Clear Filters';
                action.href = '#';
                action.onclick = e => { e.preventDefault(); clearFilters(); };
            } else {
                action.innerHTML = '<i class="fa-solid fa-plus"></i> Submit Your First Report';
                action.href = 'report.php';
                action.onclick = null;
            }
        }
        renderPagination(1);
        return;
    }

    list.innerHTML = slice.map((r, i) => buildCard(r, i)).join('');

    // Wire card clicks
    list.querySelectorAll('.mr-card[data-id]').forEach(card => {
        card.addEventListener('click', () => openModal(parseInt(card.dataset.id)));
    });

    renderPagination(totalPages);
}

// ─────────────────────────────────────────────────────
//  5. BUILD CARD HTML
// ─────────────────────────────────────────────────────
function buildCard(r, i) {
    const sk   = statusKey(r.status);
    const ti   = TYPE_ICON[r.report_type] || TYPE_ICON['Other'];
    const sevK = (r.severity || 'moderate').toLowerCase();

    return `
        <div class="mr-card mr-card--${sk}" data-id="${r.report_id}"
             style="animation-delay:${i * 0.05}s" role="button" tabindex="0"
             aria-label="View details for report #${r.report_id}">

            <div class="mr-card-aside">
                <div class="mr-type-icon mr-type-icon--${ti.cls}">
                    <i class="fa-solid ${ti.icon}"></i>
                </div>
            </div>

            <div class="mr-card-body">
                <div class="mr-card-meta">
                    <span>Report #${r.report_id}</span>
                    &middot;
                    <span>${esc(r.report_type)}</span>
                    ${r.event_name ? `&middot; <span>${esc(r.event_name)}</span>` : ''}
                </div>
                <div class="mr-card-street">${esc(r.street_name || '—')}</div>
                ${r.description
                    ? `<div class="mr-card-desc">${esc(r.description)}</div>`
                    : `<div class="mr-card-desc" style="color:var(--slate-light);font-style:italic">No description provided.</div>`
                }
            </div>

            <div class="mr-card-right">
                <span class="mr-status-badge mr-status-badge--${sk}">
                    ${statusDot(r.status)} ${esc(r.status)}
                </span>
                <span class="mr-sev-badge mr-sev-badge--${sevK}">${esc(r.severity)}</span>
                <span class="mr-card-date">${fmtDateShort(r.created_at)}</span>
                <i class="fa-solid fa-chevron-right mr-card-chevron"></i>
            </div>

        </div>`;
}

function statusDot(status) {
    const dots = {
        'Pending':     '<i class="fa-solid fa-clock" style="font-size:8px"></i>',
        'Verified':    '<i class="fa-solid fa-circle-check" style="font-size:8px"></i>',
        'In Progress': '<i class="fa-solid fa-circle-notch fa-spin" style="font-size:8px"></i>',
        'Resolved':    '<i class="fa-solid fa-check" style="font-size:8px"></i>',
        'Dismissed':   '<i class="fa-solid fa-ban" style="font-size:8px"></i>',
    };
    return dots[status] || '';
}

// ─────────────────────────────────────────────────────
//  6. PAGINATION
// ─────────────────────────────────────────────────────
function renderPagination(totalPages) {
    const wrap = document.getElementById('mrPagination');
    if (!wrap) return;

    if (totalPages <= 1) {
        wrap.hidden = true;
        wrap.innerHTML = '';
        return;
    }

    wrap.hidden = false;
    const cp = _currentPage;

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
        if (p === '…') return `<span class="mr-pg-ellipsis">…</span>`;
        const active = p === cp ? ' mr-pg-num--active' : '';
        return `<button class="mr-pg-num${active}" data-page="${p}" aria-label="Page ${p}" aria-current="${p === cp ? 'page' : 'false'}">${p}</button>`;
    }).join('');

    wrap.innerHTML = `
        <div class="mr-pg-inner">
            <button class="mr-pg-arrow" data-page="${cp - 1}" ${cp === 1 ? 'disabled' : ''} aria-label="Previous page">
                <i class="fa-solid fa-chevron-left"></i>
            </button>
            <div class="mr-pg-pages">${pageItems}</div>
            <button class="mr-pg-arrow" data-page="${cp + 1}" ${cp === totalPages ? 'disabled' : ''} aria-label="Next page">
                <i class="fa-solid fa-chevron-right"></i>
            </button>
        </div>
        <p class="mr-pg-info">Page ${cp} of ${totalPages}</p>`;

    wrap.querySelectorAll('[data-page]:not([disabled])').forEach(btn => {
        btn.addEventListener('click', () => {
            const pg = parseInt(btn.dataset.page, 10);
            if (!isNaN(pg) && pg >= 1 && pg <= totalPages && pg !== _currentPage) {
                _currentPage = pg;
                renderPage();
                document.getElementById('mrList')
                    ?.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        });
    });
}

// ─────────────────────────────────────────────────────
//  7. DETAIL MODAL
// ─────────────────────────────────────────────────────
async function openModal(reportId) {
    const overlay = document.getElementById('mrModal');
    const loading = document.getElementById('mrLoadingOverlay');
    if (!overlay) return;

    if (loading) loading.hidden = false;

    try {
        const data   = await mrFetch('report_detail', `id=${reportId}`);
        const r      = data.report;
        const verifier = data.verifier;

        if (loading) loading.hidden = true;

        // Header
        const ti = TYPE_ICON[r.report_type] || TYPE_ICON['Other'];
        const iconEl = document.getElementById('mrModalIcon');
        if (iconEl) {
            iconEl.className = `mr-modal-type-icon mr-type-icon--${ti.cls}`;
            iconEl.innerHTML = `<i class="fa-solid ${ti.icon}"></i>`;
        }
        document.getElementById('mrModalType').textContent   = r.report_type;
        document.getElementById('mrModalStreet').textContent = r.street_name || '—';

        // Status timeline
        renderTimeline(r.status);

        // Meta row
        const sk   = statusKey(r.status);
        const sevK = (r.severity || '').toLowerCase();
        document.getElementById('mrModalMetaRow').innerHTML = `
            <span class="mr-status-badge mr-status-badge--${esc(sk)}">${statusDot(r.status)} ${esc(r.status)}</span>
            <span class="mr-sev-badge mr-sev-badge--${esc(sevK)}">${esc(r.severity)}</span>
            <span style="font-size:12px;color:var(--slate-mid)"><i class="fa-regular fa-clock" style="margin-right:4px"></i>${fmtDate(r.created_at)}</span>
            ${r.report_id ? `<span style="font-size:12px;color:var(--slate-light)">Report #${r.report_id}</span>` : ''}
        `;

        // Description
        const descEl = document.getElementById('mrModalDesc');
        if (descEl) descEl.textContent = r.description || 'No description provided.';

        // Image
        const imgSection = document.getElementById('mrModalImgSection');
        const imgEl      = document.getElementById('mrModalImg');
        if (imgSection && imgEl) {
            if (r.image_path) {
                imgEl.src      = `../${esc(r.image_path)}`;
                imgSection.hidden = false;
            } else {
                imgSection.hidden = true;
            }
        }

        // Location
        const locEl = document.getElementById('mrModalLoc');
        if (locEl) {
            if (r.latitude && r.longitude) {
                locEl.innerHTML = `<i class="fa-solid fa-location-dot"></i> ${esc(r.street_name)}, ${esc(r.barangay)} &mdash; <span style="font-family:monospace;font-size:12px">${parseFloat(r.latitude).toFixed(5)}, ${parseFloat(r.longitude).toFixed(5)}</span>`;
            } else {
                locEl.innerHTML = `<i class="fa-solid fa-road"></i> ${esc(r.street_name || '—')}, ${esc(r.barangay || 'Bagong Silang')}`;
            }
        }

        // Resolution notes
        const resSection = document.getElementById('mrModalResSection');
        const resNotes   = document.getElementById('mrModalResNotes');
        if (resSection && resNotes) {
            if (r.resolution_notes && ['Resolved','Dismissed'].includes(r.status)) {
                resNotes.textContent = r.resolution_notes;
                resSection.hidden    = false;
            } else {
                resSection.hidden = true;
            }
        }

        // Verified by
        const verSection = document.getElementById('mrModalVerSection');
        const verEl      = document.getElementById('mrModalVer');
        if (verSection && verEl) {
            if (verifier && r.verified_at) {
                verEl.innerHTML = `<i class="fa-solid fa-user-check"></i> ${esc(verifier.name)} &middot; ${fmtDate(r.verified_at)}`;
                verSection.hidden = false;
            } else {
                verSection.hidden = true;
            }
        }

        overlay.hidden = false;
        document.body.style.overflow = 'hidden';

    } catch (err) {
        if (loading) loading.hidden = true;
        console.error('[Modal]', err.message);
        alert('Could not load report details. Please try again.');
    }
}

function renderTimeline(currentStatus) {
    const STEPS = ['Pending', 'Verified', 'In Progress', 'Resolved'];
    const ORDER = { 'Pending': 0, 'Verified': 1, 'In Progress': 2, 'Resolved': 3, 'Dismissed': 3 };

    const currentOrder = ORDER[currentStatus] ?? 0;
    const isDismissed  = currentStatus === 'Dismissed';

    const steps = document.querySelectorAll('.mr-status-step');
    const lines = document.querySelectorAll('.mr-status-line');

    steps.forEach((step, i) => {
        const stepStatus = step.dataset.step;
        const stepOrder  = ORDER[stepStatus] ?? 0;

        step.classList.remove('is-done', 'is-active', 'is-resolved');

        if (isDismissed && stepStatus === 'Resolved') {
            // Replace last step with dismissed
            step.querySelector('span').textContent = 'Dismissed';
            step.classList.add('is-active');
            step.querySelector('.mr-status-dot').style.background = '#dc2626';
            step.querySelector('.mr-status-dot').style.boxShadow = '0 0 0 3px rgba(220,38,38,.2)';
            step.querySelector('span').style.color = '#dc2626';
        } else if (stepOrder < currentOrder) {
            step.classList.add('is-done');
            if (stepStatus !== 'Resolved') {
                step.querySelector('span').textContent = STEPS[i] || stepStatus;
            }
            step.querySelector('.mr-status-dot').style.cssText = '';
            step.querySelector('span').style.cssText = '';
        } else if (stepOrder === currentOrder && !isDismissed) {
            step.classList.add(currentStatus === 'Resolved' ? 'is-resolved' : 'is-active');
            step.querySelector('.mr-status-dot').style.cssText = '';
            step.querySelector('span').style.cssText = '';
            step.querySelector('span').textContent = STEPS[i] || stepStatus;
        } else {
            step.querySelector('.mr-status-dot').style.cssText = '';
            step.querySelector('span').style.cssText = '';
            step.querySelector('span').textContent = STEPS[i] || stepStatus;
        }
    });

    lines.forEach((line, i) => {
        line.classList.toggle('is-filled', i < currentOrder);
    });
}

function closeModal() {
    const overlay = document.getElementById('mrModal');
    if (overlay) overlay.hidden = true;
    document.body.style.overflow = '';
}

document.getElementById('mrModalClose')?.addEventListener('click', closeModal);
document.getElementById('mrModalCloseBtn')?.addEventListener('click', closeModal);
document.getElementById('mrModal')?.addEventListener('click', e => {
    if (e.target === document.getElementById('mrModal')) closeModal();
});
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') closeModal();
});

// Keyboard support on cards
document.addEventListener('keydown', e => {
    if (e.key === 'Enter' || e.key === ' ') {
        const card = e.target.closest('.mr-card[data-id]');
        if (card) { e.preventDefault(); openModal(parseInt(card.dataset.id)); }
    }
});

// ─────────────────────────────────────────────────────
//  8. FILTER CONTROLS
// ─────────────────────────────────────────────────────
let _searchTimer = null;

document.getElementById('mrSearch')?.addEventListener('input', e => {
    clearTimeout(_searchTimer);
    _searchTimer = setTimeout(() => {
        _searchQ = e.target.value.toLowerCase().trim();
        applyFilters();
    }, 260);
});

document.getElementById('mrStatusFilter')?.addEventListener('change', e => {
    _statusFilter = e.target.value;
    applyFilters();
});

document.getElementById('mrSevFilter')?.addEventListener('change', e => {
    _sevFilter = e.target.value;
    applyFilters();
});

function clearFilters() {
    _searchQ      = '';
    _statusFilter = 'all';
    _sevFilter    = 'all';
    const s = document.getElementById('mrSearch');
    const st = document.getElementById('mrStatusFilter');
    const sv = document.getElementById('mrSevFilter');
    if (s)  s.value  = '';
    if (st) st.value = 'all';
    if (sv) sv.value = 'all';
    applyFilters();
}

// ─────────────────────────────────────────────────────
//  9. STICKY TOOLBAR
// ─────────────────────────────────────────────────────
const toolbar = document.getElementById('mrToolbar');
if (toolbar) {
    new IntersectionObserver(
        ([e]) => toolbar.classList.toggle('is-stuck', e.intersectionRatio < 1),
        { threshold: [1], rootMargin: '-1px 0px 0px 0px' }
    ).observe(toolbar);
}

function showEmpty(msg) {
    const empty = document.getElementById('mrEmpty');
    const msgEl = document.getElementById('mrEmptyMsg');
    if (empty) empty.hidden = false;
    if (msgEl) msgEl.textContent = msg;
}

// ─────────────────────────────────────────────────────
//  INIT
// ─────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', loadReports);