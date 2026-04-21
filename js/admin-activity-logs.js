'use strict';

/* ══════════════════════════════════════════════════════════
   CONFIG  — injected by PHP into window.AL_CONFIG
══════════════════════════════════════════════════════════ */
const API_URL      = window.AL_CONFIG?.apiUrl       ?? '../backend/admin-activity-logs.php';
const IS_SUPERADMIN= window.AL_CONFIG?.isSuperadmin ?? false;
const COL_SPAN     = window.AL_CONFIG?.colSpan      ?? 6;
const PER_PAGE     = 20;

/* ══════════════════════════════════════════════════════════
   STATE
══════════════════════════════════════════════════════════ */
const state = {
    logs:       [],
    kpi:        {},
    page:       1,
    lastPage:   1,
    total:      0,
    sortDir:    'desc',   // 'asc' | 'desc'
    deletingId: null,
    viewingLog: null,
    filters: {
        search:    '',
        user_id:   '',
        module:    '',
        date_from: '',
        date_to:   '',
    },
};

/* ══════════════════════════════════════════════════════════
   MODULE → CSS CLASS MAP
══════════════════════════════════════════════════════════ */
const MODULE_CLS = {
    'Reports':       'mod-reports',
    'Users':         'mod-users',
    'Auth':          'mod-auth',
    'Resources':     'mod-resources',
    'Events':        'mod-events',
    'Welfare':       'mod-welfare',
    'Announcements': 'mod-announcements',
    'System':        'mod-system',
};

/* ══════════════════════════════════════════════════════════
   DOM REFERENCES
══════════════════════════════════════════════════════════ */
const $id = id => document.getElementById(id);

const ui = {
    tbody:          $id('alTableBody'),
    kpiTotal:       $id('kpiTotal'),
    kpiToday:       $id('kpiToday'),
    kpiWeek:        $id('kpiWeek'),
    kpiUsers:       $id('kpiUsers'),
    paginationInfo: $id('alPaginationInfo'),
    pagination:     $id('alPagination'),
    recordCount:    $id('alRecordCount'),
    search:         $id('alSearch'),
    filterUser:     $id('filterUser'),
    filterModule:   $id('filterModule'),
    filterDateFrom: $id('filterDateFrom'),
    filterDateTo:   $id('filterDateTo'),
    sortIcon:       $id('sortIcon'),
    sortLabel:      $id('sortLabel'),
    btnToggleSort:  $id('btnToggleSort'),

    // View modal
    viewModal:      $id('alViewModal'),
    viewModalBody:  $id('alViewModalBody'),

    // Delete modal (superadmin only — may be null)
    deleteModal:    $id('alDeleteModal'),
    deleteTarget:   $id('deleteTargetLabel'),
    btnConfirmDel:  $id('btnConfirmDelete'),

    toastCont:      $id('alToastContainer'),
};

/* ══════════════════════════════════════════════════════════
   INIT
══════════════════════════════════════════════════════════ */
document.addEventListener('DOMContentLoaded', () => {
    loadMeta().then(() => loadLogs());
    bindEvents();
});

/* ══════════════════════════════════════════════════════════
   API CALLS
══════════════════════════════════════════════════════════ */

/**
 * Load user list and module list for filter dropdowns.
 */
async function loadMeta() {
    try {
        const res  = await fetch(`${API_URL}?meta=1`);
        const json = await res.json();
        if (!json.success) { console.error('loadMeta failed:', json.message); return; }

        const { users = [], modules = [] } = json.data;

        // Populate user filter
        users.forEach(u => {
            const opt       = document.createElement('option');
            opt.value       = u.id;
            opt.textContent = `${u.name} (${u.role})`;
            ui.filterUser.appendChild(opt);
        });

        // Populate module filter
        modules.forEach(m => {
            const opt       = document.createElement('option');
            opt.value       = m;
            opt.textContent = m;
            ui.filterModule.appendChild(opt);
        });
    } catch (err) {
        console.error('loadMeta:', err);
    }
}

/**
 * Fetch and render the paginated log list.
 */
async function loadLogs() {
    _setTableLoading();

    const params = new URLSearchParams({
        page:      state.page,
        dir:       state.sortDir,
        sort:      'created_at',
        search:    state.filters.search,
        user_id:   state.filters.user_id,
        module:    state.filters.module,
        date_from: state.filters.date_from,
        date_to:   state.filters.date_to,
    });

    try {
        const res  = await fetch(`${API_URL}?${params}`);
        const json = await res.json();
        if (!json.success) { _setTableError(json.message); return; }

        const { logs, kpi, total, last_page } = json.data;

        state.logs     = logs;
        state.kpi      = kpi;
        state.total    = total;
        state.lastPage = last_page;

        _renderKpi(kpi);
        _renderTable(logs);
        _renderPagination(total, last_page);
    } catch (err) {
        console.error('loadLogs:', err);
        _setTableError('Failed to load activity logs.');
    }
}

/**
 * Delete a single log entry (superadmin only).
 */
async function deleteLog(id) {
    const res = await fetch(`${API_URL}?id=${id}`, { method: 'DELETE' });
    return res.json();
}

/* ══════════════════════════════════════════════════════════
   RENDER — KPI
══════════════════════════════════════════════════════════ */
function _renderKpi(kpi) {
    const animate = el => {
        el.classList.remove('sk-inline');
        el.style.opacity   = '0';
        el.style.transform = 'translateY(6px)';
        requestAnimationFrame(() => {
            el.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
            el.style.opacity    = '1';
            el.style.transform  = 'translateY(0)';
        });
    };

    ui.kpiTotal.textContent = kpi.total      ?? 0; animate(ui.kpiTotal);
    ui.kpiToday.textContent = kpi.today      ?? 0; animate(ui.kpiToday);
    ui.kpiWeek.textContent  = kpi.last_7_days ?? 0; animate(ui.kpiWeek);
    ui.kpiUsers.textContent = kpi.unique_users ?? 0; animate(ui.kpiUsers);
}

/* ══════════════════════════════════════════════════════════
   RENDER — TABLE
══════════════════════════════════════════════════════════ */
function _renderTable(logs) {
    if (!logs.length) {
        ui.tbody.innerHTML = `
            <tr><td colspan="${COL_SPAN}">
                <div class="rr-empty">
                    <div class="rr-empty-icon"><i class="fa-solid fa-scroll"></i></div>
                    <div class="rr-empty-title">No logs found</div>
                    <p class="rr-empty-desc">No activity logs match your current filters. Try adjusting the search or date range.</p>
                </div>
            </td></tr>`;
        ui.recordCount.textContent = '0 logs';
        return;
    }

    const offset = (state.page - 1) * PER_PAGE;
    const rows   = logs.map((log, i) => _buildRow(log, offset + i + 1)).join('');
    ui.tbody.innerHTML = rows;

    // Update record count label
    const start = offset + 1;
    const end   = Math.min(offset + logs.length, state.total);
    ui.recordCount.textContent = `Showing ${start}–${end} of ${state.total.toLocaleString()} log${state.total !== 1 ? 's' : ''}`;

    // Attach row-level event listeners after DOM injection
    _bindRowEvents();
}

/**
 * Build a single <tr> HTML string for one log entry.
 * @param {Object} log
 * @param {number} rowNum - 1-based display number
 * @returns {string}
 */
function _buildRow(log, rowNum) {
    const userName    = log.user_name ?? 'System';
    const userRole    = log.user_role ?? 'system';
    const initials    = _initials(userName);
    const avatarCls   = log.user_id ? (userRole.toLowerCase().replace('_', '') || 'system') : 'system';
    const moduleCls   = MODULE_CLS[log.module] ?? 'mod-default';
    const moduleBadge = log.module
        ? `<span class="al-module-badge ${moduleCls}">${esc(log.module)}</span>`
        : '<span style="color:var(--slate-mid);font-size:12px;">—</span>';
    const ipCls       = (!log.ip_address || log.ip_address === '127.0.0.1' || log.ip_address === '::1') ? 'local' : '';
    const ipText      = log.ip_address ? esc(log.ip_address) : '—';

    const deleteBtn = IS_SUPERADMIN
        ? `<td class="al-col-actions">
               <div class="rr-row-actions" style="justify-content:center">
                   <button class="rr-icon-btn danger" data-del="${log.id}" title="Delete log entry">
                       <i class="fa-solid fa-trash"></i>
                   </button>
               </div>
           </td>`
        : '';

    return `
    <tr data-log-id="${log.id}">
        <td class="al-col-num">
            <span class="al-row-num">${rowNum}</span>
        </td>

        <td class="al-col-user">
            <div class="al-user-cell">
                <div class="al-avatar ${avatarCls}">${esc(initials)}</div>
                <div>
                    <div class="al-user-name">${esc(userName)}</div>
                    <div class="al-user-role">${esc(userRole.replace('_', ' '))}</div>
                </div>
            </div>
        </td>

        <td class="al-col-module">${moduleBadge}</td>

        <td class="al-col-action">
            <span class="al-action-preview" data-view="${log.id}" title="Click to view full details">
                ${esc(log.action)}
            </span>
        </td>

        <td class="al-col-ip">
            <span class="al-ip ${ipCls}">${ipText}</span>
        </td>

        <td class="al-col-date">
            <div class="al-date">${_fmtDate(log.created_at)}</div>
            <div class="al-date-sub">${_fmtTime(log.created_at)} · ${_timeAgo(log.created_at)}</div>
        </td>

        ${deleteBtn}
    </tr>`;
}

/**
 * Bind click handlers on newly injected row elements.
 */
function _bindRowEvents() {
    // View log detail on action text click
    ui.tbody.querySelectorAll('[data-view]').forEach(el => {
        el.addEventListener('click', () => {
            const id  = +el.dataset.view;
            const log = state.logs.find(l => l.id === id);
            if (log) openViewModal(log);
        });
    });

    // Delete buttons (superadmin only)
    if (IS_SUPERADMIN) {
        ui.tbody.querySelectorAll('[data-del]').forEach(el => {
            el.addEventListener('click', () => {
                const id = +el.dataset.del;
                openDeleteModal(id);
            });
        });
    }
}

/* ══════════════════════════════════════════════════════════
   RENDER — PAGINATION
══════════════════════════════════════════════════════════ */
function _renderPagination(total, lastPage) {
    const start = (state.page - 1) * PER_PAGE + 1;
    const end   = Math.min(state.page * PER_PAGE, total);
    ui.paginationInfo.textContent = total
        ? `Showing ${start}–${end} of ${total.toLocaleString()} entries`
        : 'No entries found';

    // Build page buttons: prev, up to 5 page numbers, next
    const buttons = [];

    // Previous
    buttons.push(`<button class="page-btn${state.page <= 1 ? ' disabled' : ''}"
                          data-page="${state.page - 1}"
                          ${state.page <= 1 ? 'disabled' : ''}>
                      <i class="fa-solid fa-chevron-left"></i>
                  </button>`);

    // Page number window
    const delta  = 2;
    const rangeL = Math.max(1, state.page - delta);
    const rangeR = Math.min(lastPage, state.page + delta);

    if (rangeL > 1) {
        buttons.push(`<button class="page-btn" data-page="1">1</button>`);
        if (rangeL > 2) buttons.push(`<span class="page-ellipsis">…</span>`);
    }

    for (let p = rangeL; p <= rangeR; p++) {
        buttons.push(`<button class="page-btn${p === state.page ? ' active' : ''}"
                               data-page="${p}">${p}</button>`);
    }

    if (rangeR < lastPage) {
        if (rangeR < lastPage - 1) buttons.push(`<span class="page-ellipsis">…</span>`);
        buttons.push(`<button class="page-btn" data-page="${lastPage}">${lastPage}</button>`);
    }

    // Next
    buttons.push(`<button class="page-btn${state.page >= lastPage ? ' disabled' : ''}"
                          data-page="${state.page + 1}"
                          ${state.page >= lastPage ? 'disabled' : ''}>
                      <i class="fa-solid fa-chevron-right"></i>
                  </button>`);

    ui.pagination.innerHTML = buttons.join('');

    // Bind pagination clicks
    ui.pagination.querySelectorAll('.page-btn:not([disabled])').forEach(btn => {
        btn.addEventListener('click', () => {
            const p = +btn.dataset.page;
            if (p >= 1 && p <= lastPage && p !== state.page) {
                state.page = p;
                loadLogs();
                window.scrollTo({ top: 0, behavior: 'smooth' });
            }
        });
    });
}

/* ══════════════════════════════════════════════════════════
   MODAL — VIEW LOG DETAILS
══════════════════════════════════════════════════════════ */
function openViewModal(log) {
    state.viewingLog = log;

    const userName  = log.user_name ?? 'System';
    const userRole  = log.user_role ?? 'system';
    const avatarCls = log.user_id ? (userRole.toLowerCase().replace('_', '') || 'system') : 'system';
    const moduleCls = MODULE_CLS[log.module] ?? 'mod-default';
    const ipCls     = (!log.ip_address || log.ip_address === '127.0.0.1' || log.ip_address === '::1') ? 'local' : '';

    ui.viewModalBody.innerHTML = `
        <!-- User strip -->
        <div class="al-detail-user-strip" style="margin-bottom:16px">
            <div class="al-avatar ${avatarCls}" style="width:40px;height:40px;font-size:13px">${esc(_initials(userName))}</div>
            <div class="al-detail-user-info">
                <strong>${esc(userName)}</strong>
                <span>${esc(userRole.replace('_', ' '))}</span>
            </div>
        </div>

        <div class="al-detail-grid">
            <div class="al-detail-field">
                <span class="al-detail-label">Log ID</span>
                <span class="al-detail-value">#${log.id}</span>
            </div>
            <div class="al-detail-field">
                <span class="al-detail-label">Module</span>
                <span class="al-detail-value">
                    ${log.module
                        ? `<span class="al-module-badge ${moduleCls}">${esc(log.module)}</span>`
                        : '—'}
                </span>
            </div>
            <div class="al-detail-field">
                <span class="al-detail-label">Date &amp; Time</span>
                <span class="al-detail-value">
                    ${_fmtDate(log.created_at)} at ${_fmtTime(log.created_at)}
                </span>
            </div>
            <div class="al-detail-field">
                <span class="al-detail-label">IP Address</span>
                <span class="al-detail-value">
                    <span class="al-ip ${ipCls}" style="font-size:13px">
                        ${log.ip_address ? esc(log.ip_address) : '—'}
                    </span>
                </span>
            </div>
            <div class="al-detail-field full">
                <span class="al-detail-label">Action Performed</span>
                <div class="al-action-box">${esc(log.action)}</div>
            </div>
        </div>`;

    _openModal(ui.viewModal);
}

/* ══════════════════════════════════════════════════════════
   MODAL — DELETE CONFIRMATION
══════════════════════════════════════════════════════════ */
function openDeleteModal(id) {
    state.deletingId = id;
    if (ui.deleteTarget) {
        ui.deleteTarget.textContent = `#${id}`;
    }
    _openModal(ui.deleteModal);
}

/* ══════════════════════════════════════════════════════════
   EXPORT — CSV
══════════════════════════════════════════════════════════ */
function exportCsv() {
    if (!state.logs.length) {
        toast('No data to export. Apply filters or wait for logs to load.', 'info');
        return;
    }

    const headers = ['ID', 'User Name', 'Role', 'Module', 'Action', 'IP Address', 'Date & Time'];
    const rows    = state.logs.map(l => [
        l.id,
        l.user_name ?? 'System',
        l.user_role ?? '',
        l.module    ?? '',
        `"${(l.action ?? '').replace(/"/g, '""')}"`,   // quote-escape action
        l.ip_address ?? '',
        l.created_at ?? '',
    ]);

    const csv  = [headers, ...rows].map(r => r.join(',')).join('\n');
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const url  = URL.createObjectURL(blob);
    const a    = document.createElement('a');
    a.href     = url;
    a.download = `activity-logs-p${state.page}-${Date.now()}.csv`;
    a.click();
    URL.revokeObjectURL(url);

    toast('CSV exported successfully.', 'success');
}

/* ══════════════════════════════════════════════════════════
   BIND EVENTS
══════════════════════════════════════════════════════════ */
function bindEvents() {

    // ── Search (debounced) ─────────────────────────────
    let searchTimer;
    ui.search.addEventListener('input', () => {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => {
            state.filters.search = ui.search.value.trim();
            state.page = 1;
            loadLogs();
        }, 350);
    });

    // ── Dropdown filters ───────────────────────────────
    ui.filterUser.addEventListener('change', () => {
        state.filters.user_id = ui.filterUser.value;
        state.page = 1; loadLogs();
    });
    ui.filterModule.addEventListener('change', () => {
        state.filters.module = ui.filterModule.value;
        state.page = 1; loadLogs();
    });
    ui.filterDateFrom.addEventListener('change', () => {
        state.filters.date_from = ui.filterDateFrom.value;
        state.page = 1; loadLogs();
    });
    ui.filterDateTo.addEventListener('change', () => {
        state.filters.date_to = ui.filterDateTo.value;
        state.page = 1; loadLogs();
    });

    // ── Sort direction toggle ──────────────────────────
    ui.btnToggleSort.addEventListener('click', () => {
        state.sortDir = state.sortDir === 'desc' ? 'asc' : 'desc';
        ui.sortLabel.textContent = state.sortDir === 'desc' ? 'Newest' : 'Oldest';
        ui.sortIcon.className    = state.sortDir === 'desc'
            ? 'fa-solid fa-arrow-down-wide-short'
            : 'fa-solid fa-arrow-up-wide-short';
        state.page = 1;
        loadLogs();
    });

    // ── Export ─────────────────────────────────────────
    $id('btnExportCsv').addEventListener('click', exportCsv);

    // ── View modal close ───────────────────────────────
    $id('btnCloseViewModal').addEventListener('click',       () => _closeModal(ui.viewModal));
    $id('btnCloseViewModalBottom').addEventListener('click', () => _closeModal(ui.viewModal));

    // ── Delete modal (superadmin only) ─────────────────
    if (IS_SUPERADMIN && ui.deleteModal) {
        $id('btnCloseDeleteModal').addEventListener('click', () => _closeModal(ui.deleteModal));
        $id('btnCancelDelete').addEventListener('click',     () => _closeModal(ui.deleteModal));

        ui.btnConfirmDel.addEventListener('click', async () => {
            if (!state.deletingId) return;

            ui.btnConfirmDel.disabled  = true;
            ui.btnConfirmDel.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin"></i> Deleting…';

            const json = await deleteLog(state.deletingId);

            ui.btnConfirmDel.disabled  = false;
            ui.btnConfirmDel.innerHTML = '<i class="fa-solid fa-trash"></i> Delete Entry';

            if (json.success) {
                toast(json.message, 'success');
                _closeModal(ui.deleteModal);
                state.page = 1;
                loadLogs();
            } else {
                toast(json.message || 'Delete failed.', 'error');
            }
            state.deletingId = null;
        });
    }

    // ── Close modals on backdrop click ─────────────────
    const modals = [ui.viewModal, ui.deleteModal].filter(Boolean);
    modals.forEach(modal => {
        modal.addEventListener('click', e => {
            if (e.target === modal) _closeModal(modal);
        });
    });

    // ── ESC key ────────────────────────────────────────
    document.addEventListener('keydown', e => {
        if (e.key === 'Escape') {
            modals.forEach(m => {
                if (m && m.classList.contains('open')) _closeModal(m);
            });
        }
    });
}

/* ══════════════════════════════════════════════════════════
   MODAL HELPERS
══════════════════════════════════════════════════════════ */
function _openModal(modal) {
    if (!modal) return;
    modal.classList.add('open');
    document.body.style.overflow = 'hidden';
}

function _closeModal(modal) {
    if (!modal) return;
    modal.classList.remove('open');
    document.body.style.overflow = '';
}

/* ══════════════════════════════════════════════════════════
   TABLE LOADING / ERROR STATES
══════════════════════════════════════════════════════════ */
function _setTableLoading() {
    ui.tbody.innerHTML = `
        <tr><td colspan="${COL_SPAN}" class="tbl-loading">
            <i class="fa-solid fa-circle-notch fa-spin"></i>&nbsp; Loading activity logs…
        </td></tr>`;
}

function _setTableError(msg) {
    ui.tbody.innerHTML = `
        <tr><td colspan="${COL_SPAN}" class="tbl-loading" style="color:#dc2626">
            <i class="fa-solid fa-circle-exclamation"></i>&nbsp; ${esc(msg)}
        </td></tr>`;
}

/* ══════════════════════════════════════════════════════════
   TOAST NOTIFICATIONS
══════════════════════════════════════════════════════════ */
function toast(message, type = 'info') {
    const icons = { success: 'fa-circle-check', error: 'fa-circle-xmark', info: 'fa-circle-info' };
    const el    = document.createElement('div');
    el.className = `rr-toast ${type}`;
    el.innerHTML = `<i class="fa-solid ${icons[type] ?? icons.info}"></i><span>${esc(message)}</span>`;
    ui.toastCont.appendChild(el);

    requestAnimationFrame(() => {
        requestAnimationFrame(() => el.classList.add('show'));
    });

    setTimeout(() => {
        el.classList.remove('show');
        setTimeout(() => el.remove(), 350);
    }, 3800);
}

/* ══════════════════════════════════════════════════════════
   UTILITY HELPERS
══════════════════════════════════════════════════════════ */

/** HTML-escape a string to prevent XSS. */
function esc(str) {
    if (!str && str !== 0) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

/** Format a datetime string as a short date (e.g. "Apr 7, 2026"). */
function _fmtDate(dtStr) {
    if (!dtStr) return '—';
    return new Date(dtStr).toLocaleDateString('en-PH', {
        month: 'short', day: 'numeric', year: 'numeric',
    });
}

/** Format a datetime string as HH:MM AM/PM. */
function _fmtTime(dtStr) {
    if (!dtStr) return '';
    return new Date(dtStr).toLocaleTimeString('en-PH', {
        hour: '2-digit', minute: '2-digit',
    });
}

/** Relative time label (e.g. "3m ago", "2h ago"). */
function _timeAgo(dtStr) {
    if (!dtStr) return '';
    const diff = Date.now() - new Date(dtStr).getTime();
    const m    = Math.floor(diff / 60_000);
    if (m < 1)   return 'Just now';
    if (m < 60)  return `${m}m ago`;
    const h = Math.floor(m / 60);
    if (h < 24)  return `${h}h ago`;
    const d = Math.floor(h / 24);
    if (d < 7)   return `${d}d ago`;
    return _fmtDate(dtStr);
}

/** Generate up-to-2-letter initials from a full name. */
function _initials(name) {
    if (!name) return '?';
    const parts = name.trim().split(/\s+/);
    if (parts.length === 1) return parts[0][0].toUpperCase();
    return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
}