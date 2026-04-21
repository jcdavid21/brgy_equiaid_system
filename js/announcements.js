/**
 * announcements.js — Barangay EQUIAID Announcements Page
 * Connects to: backend/announcements.php
 */
'use strict';

const AN_API = '../backend/announcements.php';

async function anFetch(action, params = '') {
    const url = `${AN_API}?action=${action}${params ? '&' + params : ''}`;
    const res  = await fetch(url, {
        headers: { 'Accept': 'application/json' }   // ← add this
    });
    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    const data = await res.json();
    if (!data.ok) throw new Error(data.error || 'API error');
    return data;
}

// ── Helpers ──────────────────────────────────────────────
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
        month: 'long', day: 'numeric', year: 'numeric',
        hour: 'numeric', minute: '2-digit',
    });
}

function fmtDateShort(ts) {
    if (!ts) return '—';
    return new Date(ts.replace(' ','T')).toLocaleString('en-PH', {
        month: 'short', day: 'numeric', year: 'numeric',
    });
}

function timeAgo(ts) {
    if (!ts) return '';
    const diff = (Date.now() - new Date(ts.replace(' ','T'))) / 1000;
    if (diff < 3600)  return `${Math.floor(diff/60)}m ago`;
    if (diff < 86400) return `${Math.floor(diff/3600)}h ago`;
    if (diff < 604800) return `${Math.floor(diff/86400)}d ago`;
    return fmtDateShort(ts);
}

// ── Category config ──────────────────────────────────────
const CAT_CFG = {
    'Alert':      { icon: 'fa-triangle-exclamation', cls: 'alert' },
    'Evacuation': { icon: 'fa-person-walking-arrow-right', cls: 'evacuation' },
    'Relief':     { icon: 'fa-hand-holding-heart',   cls: 'relief' },
    'Weather':    { icon: 'fa-cloud-bolt',            cls: 'weather' },
    'General':    { icon: 'fa-circle-info',           cls: 'general' },
};

// ── State ────────────────────────────────────────────────
const PAGE_SIZE     = 8;
let _currentPage    = 1;
let _all            = [];   // all announcements
let _visible        = [];   // after filter
let _catFilter      = 'all';
let _priorityFilter = 'all';
let _sortMode       = 'newest';
let _searchQ        = '';

// ─────────────────────────────────────────────────────
//  1. LOAD
// ─────────────────────────────────────────────────────
async function loadAll() {
    try {
        const data = await anFetch('announcements');

        _all = data.announcements || [];

        // Hide skeletons
        const skel = document.getElementById('anSkeletons');
        if (skel) skel.hidden = true;

        renderCountBadges(_all);
        renderBreakdown(_all);
        renderActiveEvent(data.active_event);
        updateLastUpdated(data.last_updated);
        applyFilters();

    } catch (err) {
        console.error('[Announcements]', err.message);
        const skel = document.getElementById('anSkeletons');
        if (skel) skel.hidden = true;
        showEmpty('Could not load announcements. Please try again later.');
    }
}

// ─────────────────────────────────────────────────────
//  2. COUNT BADGES ON PILLS
// ─────────────────────────────────────────────────────
function renderCountBadges(items) {
    const counts = { all: items.length, Alert: 0, Evacuation: 0, Relief: 0, Weather: 0, General: 0 };
    items.forEach(a => { if (counts[a.category] !== undefined) counts[a.category]++; });

    const set = (id, val) => {
        const el = document.getElementById(id);
        if (el) el.textContent = val;
    };
    set('anCountAll',       counts.all);
    set('anCountAlert',     counts.Alert);
    set('anCountEvacuation',counts.Evacuation);
    set('anCountRelief',    counts.Relief);
    set('anCountWeather',   counts.Weather);
    set('anCountGeneral',   counts.General);
}

// ─────────────────────────────────────────────────────
//  3. BREAKDOWN SIDEBAR BARS
// ─────────────────────────────────────────────────────
function renderBreakdown(items) {
    const list = document.getElementById('anBreakdown');
    if (!list) return;

    const cats  = ['Alert','Evacuation','Relief','Weather','General'];
    const counts = {};
    items.forEach(a => { counts[a.category] = (counts[a.category] || 0) + 1; });
    const max = Math.max(...cats.map(c => counts[c] || 0), 1);

    list.innerHTML = cats.map(cat => {
        const count = counts[cat] || 0;
        const cfg   = CAT_CFG[cat] || { icon: 'fa-circle-info', cls: 'general' };
        const pct   = Math.round(count / max * 100);
        return `
            <div class="an-bdwn-item">
                <div class="an-bdwn-head">
                    <span class="an-bdwn-label">
                        <i class="fa-solid ${cfg.icon}" style="color:var(--slate-mid)"></i>
                        ${esc(cat)}
                    </span>
                    <span class="an-bdwn-count">${count}</span>
                </div>
                <div class="an-bdwn-bar">
                    <div class="an-bdwn-fill an-bdwn-fill--${cfg.cls}" style="width:${pct}%"></div>
                </div>
            </div>`;
    }).join('');
}

// ─────────────────────────────────────────────────────
//  4. ACTIVE TYPHOON EVENT CARD
// ─────────────────────────────────────────────────────
function renderActiveEvent(ev) {
    const card = document.getElementById('anEventCard');
    if (!card || !ev) return;

    document.getElementById('anEventName').textContent = ev.event_name;
    document.getElementById('anEventMeta').textContent =
        `Category ${ev.category ?? '—'} · ${ev.wind_speed_kph ? Math.round(ev.wind_speed_kph) + ' km/h winds' : '—'} · ${ev.status}`;
    card.hidden = false;
}

// ─────────────────────────────────────────────────────
//  5. LAST UPDATED
// ─────────────────────────────────────────────────────
function updateLastUpdated(ts) {
    const el = document.getElementById('an-last-updated');
    if (!el || !ts) return;
    el.textContent = fmtDateShort(ts);
    el.classList.remove('sk-loading', 'sk-inline');
}

// ─────────────────────────────────────────────────────
//  6. FILTER + SORT
// ─────────────────────────────────────────────────────
function applyFilters() {
    _visible = _all.filter(a => {
        const matchCat   = _catFilter      === 'all' || a.category === _catFilter;
        const matchPri   = _priorityFilter === 'all' || a.priority === _priorityFilter;
        const matchSearch = !_searchQ ||
            a.title.toLowerCase().includes(_searchQ) ||
            (a.body || '').toLowerCase().includes(_searchQ);
        return matchCat && matchPri && matchSearch;
    });

    // Sort
    _visible.sort((a, b) => {
        if (_sortMode === 'oldest')   return new Date(a.created_at) - new Date(b.created_at);
        if (_sortMode === 'priority') {
            const order = { Critical: 0, High: 1, Normal: 2 };
            return (order[a.priority] ?? 9) - (order[b.priority] ?? 9);
        }
        return new Date(b.created_at) - new Date(a.created_at); // newest
    });

    _currentPage = 1;

    const label = document.getElementById('anResultsLabel');
    const count = document.getElementById('anVisibleCount');
    if (label) label.hidden = false;
    if (count) count.textContent = _visible.length;

    renderPage();
}

// ─────────────────────────────────────────────────────
//  7. RENDER PAGE
// ─────────────────────────────────────────────────────
function renderPage() {
    const list      = document.getElementById('anList');
    const empty     = document.getElementById('anEmpty');
    const pinnedWrap= document.getElementById('anPinnedWrap');

    const total      = _visible.length;
    const totalPages = Math.max(1, Math.ceil(total / PAGE_SIZE));
    _currentPage     = Math.min(_currentPage, totalPages);

    const start = (_currentPage - 1) * PAGE_SIZE;
    const slice = _visible.slice(start, start + PAGE_SIZE);

    const isEmpty = total === 0;
    list.hidden   = isEmpty;
    if (empty) empty.hidden = !isEmpty;

    if (isEmpty) {
        if (pinnedWrap) pinnedWrap.hidden = true;
        renderPagination(1);
        return;
    }

    // Pinned item — only on page 1, first result if pinned
    const firstPinned = _currentPage === 1 && slice[0]?.is_pinned ? slice[0] : null;
    if (pinnedWrap) {
        if (firstPinned) {
            pinnedWrap.hidden = false;
            pinnedWrap.innerHTML = buildPinnedBanner(firstPinned);
            pinnedWrap.querySelector('.an-pinned-banner')
                ?.addEventListener('click', () => openModal(firstPinned));
        } else {
            pinnedWrap.hidden = true;
        }
    }

    // Cards — skip pinned if shown in banner
    const cardsSlice = firstPinned ? slice.slice(1) : slice;
    list.innerHTML = cardsSlice.map((a, i) => buildCard(a, i)).join('');

    // Wire card clicks
    list.querySelectorAll('.an-card[data-id]').forEach(card => {
        card.addEventListener('click', () => {
            const id = parseInt(card.dataset.id);
            const item = _all.find(a => a.announcement_id == id);
            if (item) openModal(item);
        });
    });

    renderPagination(totalPages);
}

// ─────────────────────────────────────────────────────
//  8. BUILD PINNED BANNER
// ─────────────────────────────────────────────────────
function buildPinnedBanner(a) {
    const cfg = CAT_CFG[a.category] || CAT_CFG.General;
    return `
        <div class="an-pinned-banner" data-id="${a.announcement_id}">
            <div class="an-pinned-tag">
                <i class="fa-solid fa-thumbtack"></i> Pinned &middot; ${esc(a.category)}
            </div>
            <div class="an-pinned-title">${esc(a.title)}</div>
            <div class="an-pinned-excerpt">${esc(a.body || '')}</div>
            <div class="an-pinned-foot">
                <span class="an-pinned-date">
                    <i class="fa-regular fa-clock"></i>
                    ${fmtDateShort(a.created_at)}
                </span>
                <span class="an-pinned-cta">
                    Read more <i class="fa-solid fa-arrow-right"></i>
                </span>
            </div>
        </div>`;
}

// ─────────────────────────────────────────────────────
//  9. BUILD CARD HTML
// ─────────────────────────────────────────────────────
function buildCard(a, i) {
    const cfg = CAT_CFG[a.category] || CAT_CFG.General;
    const pri = (a.priority || 'Normal').toLowerCase();

    return `
        <div class="an-card an-card--${cfg.cls}" data-id="${a.announcement_id}"
             style="animation-delay:${i * 0.04}s"
             role="button" tabindex="0"
             aria-label="Read: ${esc(a.title)}">

            <div class="an-card-aside">
                <div class="an-cat-icon an-cat-icon--${cfg.cls}">
                    <i class="fa-solid ${cfg.icon}"></i>
                </div>
            </div>

            <div class="an-card-body">
                <div class="an-card-meta">
                    <span>${esc(a.category)}</span>
                    ${a.event_name ? `&middot; <span>${esc(a.event_name)}</span>` : ''}
                    &middot; <span>${timeAgo(a.created_at)}</span>
                </div>
                <div class="an-card-title">${esc(a.title)}</div>
                <div class="an-card-excerpt">${esc(a.body || '')}</div>
            </div>

            <div class="an-card-right">
                <span class="an-priority-badge an-priority-badge--${pri}">
                    ${a.priority === 'Critical' ? '<i class="fa-solid fa-circle-exclamation"></i>' : ''}
                    ${esc(a.priority)}
                </span>
                <span class="an-card-date">${fmtDateShort(a.created_at)}</span>
                <i class="fa-solid fa-chevron-right an-card-chevron"></i>
            </div>

        </div>`;
}

// ─────────────────────────────────────────────────────
//  10. PAGINATION
// ─────────────────────────────────────────────────────
function renderPagination(totalPages) {
    const wrap = document.getElementById('anPagination');
    if (!wrap) return;

    if (totalPages <= 1) { wrap.hidden = true; wrap.innerHTML = ''; return; }

    wrap.hidden = false;
    const cp = _currentPage;

    const pages = [];
    if (totalPages <= 5) {
        for (let i = 1; i <= totalPages; i++) pages.push(i);
    } else {
        pages.push(1);
        if (cp > 3) pages.push('…');
        const lo = Math.max(2, cp - 1), hi = Math.min(totalPages - 1, cp + 1);
        for (let i = lo; i <= hi; i++) pages.push(i);
        if (cp < totalPages - 2) pages.push('…');
        pages.push(totalPages);
    }

    const pageItems = pages.map(p => {
        if (p === '…') return `<span class="an-pg-ellipsis">…</span>`;
        const active = p === cp ? ' an-pg-num--active' : '';
        return `<button class="an-pg-num${active}" data-page="${p}" aria-label="Page ${p}">${p}</button>`;
    }).join('');

    wrap.innerHTML = `
        <div class="an-pg-inner">
            <button class="an-pg-arrow" data-page="${cp-1}" ${cp===1?'disabled':''} aria-label="Previous">
                <i class="fa-solid fa-chevron-left"></i>
            </button>
            <div class="an-pg-pages">${pageItems}</div>
            <button class="an-pg-arrow" data-page="${cp+1}" ${cp===totalPages?'disabled':''} aria-label="Next">
                <i class="fa-solid fa-chevron-right"></i>
            </button>
        </div>
        <p class="an-pg-info">Page ${cp} of ${totalPages}</p>`;

    wrap.querySelectorAll('[data-page]:not([disabled])').forEach(btn => {
        btn.addEventListener('click', () => {
            const pg = parseInt(btn.dataset.page);
            if (!isNaN(pg) && pg >= 1 && pg <= totalPages && pg !== _currentPage) {
                _currentPage = pg;
                renderPage();
                document.getElementById('anList')
                    ?.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        });
    });
}

// ─────────────────────────────────────────────────────
//  11. DETAIL MODAL
// ─────────────────────────────────────────────────────
function openModal(a) {
    const overlay = document.getElementById('anModal');
    if (!overlay) return;

    const cfg = CAT_CFG[a.category] || CAT_CFG.General;
    const pri = (a.priority || 'Normal').toLowerCase();

    // Category bar in header
    document.getElementById('anModalCategoryBar').innerHTML = `
        <div class="an-cat-icon an-cat-icon--${cfg.cls}" style="width:32px;height:32px;font-size:13px;border-radius:8px">
            <i class="fa-solid ${cfg.icon}"></i>
        </div>
        <span class="an-priority-badge an-priority-badge--${pri}">${esc(a.priority)}</span>
        <span style="font-size:11px;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:var(--slate-mid)">${esc(a.category)}</span>
    `;

    // Title
    document.getElementById('anModalTitle').textContent = a.title;

    // Meta row
    document.getElementById('anModalMeta').innerHTML = `
        <i class="fa-regular fa-clock"></i>
        ${fmtDate(a.created_at)}
        <span class="an-modal-meta-sep">·</span>
        <i class="fa-solid fa-user"></i>
        ${esc(a.author_name || 'Barangay EQUIAID')}
        ${a.event_name ? `<span class="an-modal-meta-sep">·</span>
        <i class="fa-solid fa-hurricane"></i> ${esc(a.event_name)}` : ''}
    `;

    // Body
    document.getElementById('anModalContent').textContent = a.body || 'No content available.';

    // Footer meta
    document.getElementById('anModalFooterMeta').innerHTML =
        `<i class="fa-regular fa-calendar"></i> Posted ${fmtDate(a.created_at)}` +
        (a.updated_at && a.updated_at !== a.created_at
            ? ` &nbsp;·&nbsp; <i class="fa-regular fa-pen-to-square"></i> Updated ${fmtDate(a.updated_at)}`
            : '');

    overlay.hidden = false;
    document.body.style.overflow = 'hidden';
}

function closeModal() {
    const overlay = document.getElementById('anModal');
    if (overlay) overlay.hidden = true;
    document.body.style.overflow = '';
}

document.getElementById('anModalClose')?.addEventListener('click', closeModal);
document.getElementById('anModalCloseBtn')?.addEventListener('click', closeModal);
document.getElementById('anModal')?.addEventListener('click', e => {
    if (e.target === document.getElementById('anModal')) closeModal();
});
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeModal(); });

// Keyboard nav on cards
document.addEventListener('keydown', e => {
    if (e.key === 'Enter' || e.key === ' ') {
        const card = e.target.closest('.an-card[data-id]');
        if (card) { e.preventDefault(); card.click(); }
    }
});

// ─────────────────────────────────────────────────────
//  12. FILTER CONTROLS
// ─────────────────────────────────────────────────────
// Category pills
document.querySelectorAll('.an-pill').forEach(pill => {
    pill.addEventListener('click', () => {
        document.querySelectorAll('.an-pill').forEach(p => p.classList.remove('active'));
        pill.classList.add('active');
        _catFilter = pill.dataset.cat;
        applyFilters();
    });
});

// Priority + sort selects
document.getElementById('anPriorityFilter')?.addEventListener('change', e => {
    _priorityFilter = e.target.value;
    applyFilters();
});

document.getElementById('anSortFilter')?.addEventListener('change', e => {
    _sortMode = e.target.value;
    applyFilters();
});

// Search with debounce
let _searchTimer = null;
document.getElementById('anSearch')?.addEventListener('input', e => {
    clearTimeout(_searchTimer);
    _searchTimer = setTimeout(() => {
        _searchQ = e.target.value.toLowerCase().trim();
        applyFilters();
    }, 260);
});

// Clear filters button
document.getElementById('anClearFilters')?.addEventListener('click', () => {
    _catFilter = 'all'; _priorityFilter = 'all'; _sortMode = 'newest'; _searchQ = '';
    const s  = document.getElementById('anSearch');
    const p  = document.getElementById('anPriorityFilter');
    const so = document.getElementById('anSortFilter');
    if (s)  s.value  = '';
    if (p)  p.value  = 'all';
    if (so) so.value = 'newest';
    document.querySelectorAll('.an-pill').forEach(pill =>
        pill.classList.toggle('active', pill.dataset.cat === 'all'));
    applyFilters();
});

// ─────────────────────────────────────────────────────
//  13. STICKY TOOLBAR
// ─────────────────────────────────────────────────────
const toolbar = document.getElementById('anToolbar');
if (toolbar) {
    new IntersectionObserver(
        ([e]) => toolbar.classList.toggle('is-stuck', e.intersectionRatio < 1),
        { threshold: [1], rootMargin: '-1px 0px 0px 0px' }
    ).observe(toolbar);
}

function showEmpty(msg) {
    const empty = document.getElementById('anEmpty');
    const msgEl = document.getElementById('anEmptyMsg');
    const list  = document.getElementById('anList');
    if (empty) empty.hidden = false;
    if (msgEl) msgEl.textContent = msg;
    if (list)  list.hidden = true;
}

// ─────────────────────────────────────────────────────
//  INIT
// ─────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', loadAll);