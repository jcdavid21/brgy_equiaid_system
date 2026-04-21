
'use strict';

/* ══════════════════════════════════════════════════════
   CONFIG
════════════════════════════════════════════════════════ */
const API_URL  = '../backend/admin-resident-reports.php';
const PER_PAGE = 15;

/* ══════════════════════════════════════════════════════
   STATE
════════════════════════════════════════════════════════ */
const state = {
    reports:      [],
    streets:      [],
    users:        [],
    kpi:          {},
    page:         1,
    lastPage:     1,
    total:        0,
    updatingId:   null,
    deletingId:   null,
    viewingReport: null,
    filters: {
        search:      '',
        status:      '',
        severity:    '',
        report_type: '',
        street_id:   '',
    },
};

/* ══════════════════════════════════════════════════════
   METADATA MAPS
════════════════════════════════════════════════════════ */
const TYPE_META = {
    'Flood':             { icon: 'fa-water',             cls: 'flood'   },
    'Damage':            { icon: 'fa-house-crack',       cls: 'damage'  },
    'Blocked Road':      { icon: 'fa-road-barrier',      cls: 'road'    },
    'Fire':              { icon: 'fa-fire',               cls: 'fire'    },
    'Medical Emergency': { icon: 'fa-kit-medical',       cls: 'medical' },
    'Other':             { icon: 'fa-circle-exclamation', cls: 'other'  },
};

const SEVERITY_META = {
    Severe:   { icon: 'fa-circle-exclamation', cls: 'severe'   },
    Moderate: { icon: 'fa-minus',              cls: 'moderate' },
    Low:      { icon: 'fa-circle-info',        cls: 'low'      },
};

const STATUS_META = {
    'Pending':     { icon: 'fa-clock',        cls: 'pending'     },
    'Verified':    { icon: 'fa-check-circle', cls: 'verified'    },
    'In Progress': { icon: 'fa-spinner',      cls: 'in-progress' },
    'Resolved':    { icon: 'fa-circle-check', cls: 'resolved'    },
    'Dismissed':   { icon: 'fa-ban',          cls: 'dismissed'   },
};

/* ══════════════════════════════════════════════════════
   DOM REFERENCES
════════════════════════════════════════════════════════ */
const $id = id => document.getElementById(id);

const ui = {
    tbody:          $id('rrTableBody'),
    kpiTotal:       $id('kpiTotal'),
    kpiPending:     $id('kpiPending'),
    kpiInProgress:  $id('kpiInProgress'),
    kpiResolved:    $id('kpiResolved'),
    kpiSevere:      $id('kpiSevere'),
    paginationInfo: $id('rrPaginationInfo'),
    pagination:     $id('rrPagination'),
    recordCount:    $id('rrRecordCount'),
    search:         $id('rrSearch'),
    filterStatus:   $id('filterStatus'),
    filterSeverity: $id('filterSeverity'),
    filterType:     $id('filterType'),
    filterStreet:   $id('filterStreet'),

    // View modal
    viewModal:       $id('rrViewModal'),
    viewModalEyebrow:$id('viewModalEyebrow'),
    viewModalTitle:  $id('viewModalTitle'),
    viewModalBody:   $id('viewModalBody'),
    btnUpdateFromView:$id('btnUpdateFromView'),

    // Update modal
    updateModal:         $id('rrUpdateModal'),
    updateReportId:      $id('updateReportId'),
    updateReportInfo:    $id('updateReportInfo'),
    updateStatusBtns:    $id('updateStatusBtns'),
    updateStatus:        $id('updateStatus'),
    updateResolutionNotes:$id('updateResolutionNotes'),
    btnSubmitUpdate:     $id('btnSubmitUpdate'),
    btnUpdateLabel:      $id('btnUpdateLabel'),

    // Delete modal
    deleteModal:    $id('rrDeleteModal'),
    deleteTarget:   $id('deleteTargetLabel'),
    btnConfirmDel:  $id('btnConfirmDelete'),

    toastCont:      $id('rrToastContainer'),
};

/* ══════════════════════════════════════════════════════
   INIT
════════════════════════════════════════════════════════ */
document.addEventListener('DOMContentLoaded', () => {
    loadMeta().then(() => loadReports());
    bindEvents();
});

/* ══════════════════════════════════════════════════════
   API CALLS
════════════════════════════════════════════════════════ */
async function loadMeta() {
    try {
        const res  = await fetch(`${API_URL}?meta=1`);
        const json = await res.json();
        if (!json.success) { console.error('loadMeta failed:', json.message); return; }

        state.streets = json.data.streets || [];
        state.users   = json.data.users   || [];

        // Populate street filter dropdown
        state.streets.forEach(s => {
            const opt = document.createElement('option');
            opt.value       = s.street_id;
            opt.textContent = `${s.street_name} — ${s.barangay}`;
            ui.filterStreet.appendChild(opt);
        });
    } catch (err) {
        console.error('loadMeta:', err);
    }
}

async function loadReports() {
    _setTableLoading();

    const params = new URLSearchParams({
        page:        state.page,
        status:      state.filters.status,
        severity:    state.filters.severity,
        report_type: state.filters.report_type,
        street_id:   state.filters.street_id,
        search:      state.filters.search,
    });

    try {
        const res  = await fetch(`${API_URL}?${params}`);
        const json = await res.json();
        if (!json.success) { _setTableError(json.message); return; }

        const { reports, kpi, total, last_page } = json.data;

        state.reports  = reports;
        state.kpi      = kpi;
        state.total    = total;
        state.lastPage = last_page;

        _renderKpi(kpi);
        _renderTable(reports);
        _renderPagination(total, last_page);
    } catch (err) {
        console.error('loadReports:', err);
        _setTableError('Failed to load reports.');
    }
}

async function updateReport(id, payload) {
    const res = await fetch(`${API_URL}?id=${id}`, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
    });
    return res.json();
}

async function deleteReport(id) {
    const res = await fetch(`${API_URL}?id=${id}`, { method: 'DELETE' });
    return res.json();
}

async function fetchSingleReport(id) {
    const res = await fetch(`${API_URL}?id=${id}`);
    return res.json();
}

/* ══════════════════════════════════════════════════════
   RENDER — KPI
════════════════════════════════════════════════════════ */
function _renderKpi(kpi) {
    ui.kpiTotal.textContent      = kpi.total       ?? 0;
    ui.kpiPending.textContent    = kpi.pending      ?? 0;
    ui.kpiInProgress.textContent = kpi.in_progress  ?? 0;
    ui.kpiResolved.textContent   = kpi.resolved     ?? 0;
    ui.kpiSevere.textContent     = kpi.severe       ?? 0;

    // Animate number change
    [ui.kpiTotal, ui.kpiPending, ui.kpiInProgress, ui.kpiResolved, ui.kpiSevere]
        .forEach(el => {
            el.classList.remove('sk-inline'); // Remove skeleton loading class so numbers are visible
            el.style.opacity   = '0';
            el.style.transform = 'translateY(6px)';
            requestAnimationFrame(() => {
                el.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
                el.style.opacity    = '1';
                el.style.transform  = 'translateY(0)';
            });
        });
}

/* ══════════════════════════════════════════════════════
   RENDER — TABLE
════════════════════════════════════════════════════════ */
function _renderTable(reports) {
    if (!reports.length) {
        ui.tbody.innerHTML = `
            <tr><td colspan="8">
                <div class="rr-empty">
                    <div class="rr-empty-icon"><i class="fa-solid fa-flag-checkered"></i></div>
                    <div class="rr-empty-title">No reports found</div>
                    <p class="rr-empty-desc">No incident reports match your current filters. Try adjusting the search or filters above.</p>
                </div>
            </td></tr>`;
        ui.recordCount.textContent = '0 reports';
        return;
    }

    const rows = reports.map((r, idx) => {
        const typeMeta     = TYPE_META[r.report_type]     ?? TYPE_META['Other'];
        const severityMeta = SEVERITY_META[r.severity]    ?? SEVERITY_META['Moderate'];
        const statusMeta   = STATUS_META[r.status]        ?? STATUS_META['Pending'];
        const initials     = _initials(r.reporter_name    ?? 'Unknown');
        const statusCls    = r.status.toLowerCase().replace(' ', '-');
        const offset       = (state.page - 1) * PER_PAGE;
        const rowNum       = offset + idx + 1;

        const descPreview = r.description
            ? `<div class="rr-desc-preview" title="${esc(r.description)}">${esc(r.description)}</div>`
            : '<span style="color:var(--slate-mid);font-size:12px">—</span>';

        const imageBadge = r.image_path
            ? `<span class="rr-has-image"><i class="fa-solid fa-image"></i> Photo</span>`
            : '';

        const verifiedBy = r.verifier_name
            ? `<div style="font-size:12px;color:var(--text-mid)">${esc(r.verifier_name)}</div>
               <div style="font-size:11px;color:var(--slate-mid)">${_fmtDateTime(r.verified_at)}</div>`
            : `<span style="font-size:12px;color:var(--slate-mid)">—</span>`;

        return `
        <tr>
            <td style="font-size:12px;color:var(--slate-mid);font-weight:600">${rowNum}</td>
            <td>
                <div class="rr-reporter-cell">
                    <div class="rr-avatar">${initials}</div>
                    <div>
                        <div class="rr-reporter-name">${esc(r.reporter_name ?? 'Unknown')}</div>
                        <div class="rr-reporter-phone">${esc(r.reporter_phone ?? '—')}</div>
                    </div>
                </div>
            </td>
            <td>
                <div class="rr-type-cell">
                    <div class="rr-type-name">
                        <span class="rr-type-icon ${typeMeta.cls}">
                            <i class="fa-solid ${typeMeta.icon}"></i>
                        </span>
                        ${esc(r.report_type)}
                        ${imageBadge}
                    </div>
                    <div class="rr-street-name">${esc(r.street_name ?? '—')}, ${esc(r.barangay ?? '')}</div>
                    ${descPreview}
                </div>
            </td>
            <td>
                <span class="rr-severity ${severityMeta.cls}">
                    <i class="fa-solid ${severityMeta.icon}"></i>
                    ${esc(r.severity)}
                </span>
            </td>
            <td>
                <span class="rr-status ${statusCls}">
                    <i class="fa-solid ${statusMeta.icon}"></i>
                    ${esc(r.status)}
                </span>
            </td>
            <td>
                <div class="rr-date">${_fmtDate(r.created_at)}</div>
                <div class="rr-date-sub">${_timeAgo(r.created_at)}</div>
            </td>
            <td>${verifiedBy}</td>
            <td>
                <div class="rr-row-actions">
                    <button class="rr-icon-btn" title="View details" onclick="openViewModal(${r.report_id})">
                        <i class="fa-solid fa-eye"></i>
                    </button>
                    <button class="rr-icon-btn update" title="Update status" onclick="openUpdateModal(${r.report_id})">
                        <i class="fa-solid fa-pen-to-square"></i>
                    </button>
                    <button class="rr-icon-btn danger" title="Delete report" onclick="openDeleteModal(${r.report_id}, '${esc(r.reporter_name ?? 'Unknown')}')">
                        <i class="fa-solid fa-trash"></i>
                    </button>
                </div>
            </td>
        </tr>`;
    }).join('');

    ui.tbody.innerHTML = rows;

    const showing = Math.min(state.total, (state.page - 1) * PER_PAGE + reports.length);
    ui.recordCount.textContent = `Showing ${(state.page - 1) * PER_PAGE + 1}–${showing} of ${state.total} reports`;
}

/* ══════════════════════════════════════════════════════
   RENDER — PAGINATION
════════════════════════════════════════════════════════ */
function _renderPagination(total, lastPage) {
    const p = state.page;
    ui.paginationInfo.textContent = `Page ${p} of ${lastPage}`;

    if (lastPage <= 1) { ui.pagination.innerHTML = ''; return; }

    let html = '';
    // Prev
    html += `<button class="page-btn" ${p === 1 ? 'disabled' : ''} onclick="goPage(${p - 1})">
                <i class="fa-solid fa-chevron-left"></i>
             </button>`;

    // Pages — show window around current
    const pages = _pageRange(p, lastPage);
    pages.forEach(pg => {
        if (pg === '…') {
            html += `<span class="page-ellipsis">…</span>`;
        } else {
            html += `<button class="page-btn ${pg === p ? 'active' : ''}" onclick="goPage(${pg})">${pg}</button>`;
        }
    });

    // Next
    html += `<button class="page-btn" ${p === lastPage ? 'disabled' : ''} onclick="goPage(${p + 1})">
                <i class="fa-solid fa-chevron-right"></i>
             </button>`;

    ui.pagination.innerHTML = html;
}

function _pageRange(cur, last) {
    if (last <= 7) return Array.from({length: last}, (_, i) => i + 1);
    if (cur <= 4)  return [1,2,3,4,5,'…',last];
    if (cur >= last - 3) return [1,'…',last-4,last-3,last-2,last-1,last];
    return [1,'…',cur-1,cur,cur+1,'…',last];
}

window.goPage = function(page) {
    state.page = page;
    loadReports();
    window.scrollTo({ top: 0, behavior: 'smooth' });
};

/* ══════════════════════════════════════════════════════
   MODAL — VIEW REPORT
════════════════════════════════════════════════════════ */
window.openViewModal = async function(id) {
    _openModal(ui.viewModal);
    ui.viewModalBody.innerHTML = `<div style="text-align:center;padding:32px 0;color:var(--slate-mid)">
        <i class="fa-solid fa-circle-notch fa-spin fa-lg"></i>
    </div>`;

    try {
        const json = await fetchSingleReport(id);
        if (!json.success) { toast(json.message, 'error'); _closeModal(ui.viewModal); return; }

        const r = json.data;
        state.viewingReport = r;

        const typeMeta     = TYPE_META[r.report_type]  ?? TYPE_META['Other'];
        const severityMeta = SEVERITY_META[r.severity] ?? SEVERITY_META['Moderate'];
        const statusMeta   = STATUS_META[r.status]     ?? STATUS_META['Pending'];
        const statusCls    = r.status.toLowerCase().replace(' ', '-');

        ui.viewModalEyebrow.textContent = `Report #${r.report_id}`;
        ui.viewModalTitle.textContent   = r.report_type;

        const imageHtml = r.image_path
            ? `<div class="rr-view-field full">
                   <div class="rr-view-label">Attached Photo</div>
                   <img src="../${esc(r.image_path)}" class="rr-view-image"
                        alt="Report photo" onerror="this.style.display='none'">
               </div>`
            : '';

        const gpsHtml = (r.latitude && r.longitude)
            ? `<a href="https://www.google.com/maps?q=${r.latitude},${r.longitude}"
                  target="_blank" rel="noopener" class="rr-gps-link">
                   <i class="fa-solid fa-location-dot"></i>
                   ${r.latitude}, ${r.longitude}
               </a>`
            : '—';

        const resolutionHtml = r.resolution_notes
            ? `<div class="rr-resolution-box">${esc(r.resolution_notes)}</div>`
            : '<span style="color:var(--slate-mid);font-size:13px">No resolution notes yet.</span>';

        const verifiedHtml = r.verifier_name
            ? `${esc(r.verifier_name)} <span style="color:var(--slate-mid);font-size:11px">on ${_fmtDateTime(r.verified_at)}</span>`
            : '—';

        ui.viewModalBody.innerHTML = `
            <div class="rr-view-header-row">
                <span class="rr-severity ${severityMeta.cls}">
                    <i class="fa-solid ${severityMeta.icon}"></i>${esc(r.severity)}
                </span>
                <span class="rr-status ${statusCls}">
                    <i class="fa-solid ${statusMeta.icon}"></i>${esc(r.status)}
                </span>
                ${r.event_name ? `<span style="font-size:12px;color:var(--slate-mid)"><i class="fa-solid fa-hurricane" style="margin-right:4px"></i>${esc(r.event_name)}</span>` : ''}
            </div>
            <div class="rr-view-grid">
                <div class="rr-view-field">
                    <div class="rr-view-label">Reporter</div>
                    <div class="rr-view-value">${esc(r.reporter_name ?? '—')}</div>
                </div>
                <div class="rr-view-field">
                    <div class="rr-view-label">Phone</div>
                    <div class="rr-view-value">${esc(r.reporter_phone ?? '—')}</div>
                </div>
                <div class="rr-view-field">
                    <div class="rr-view-label">Location (Street)</div>
                    <div class="rr-view-value">${esc(r.street_name ?? '—')}, ${esc(r.barangay ?? '')}</div>
                </div>
                <div class="rr-view-field">
                    <div class="rr-view-label">Report Type</div>
                    <div class="rr-view-value">
                        <span class="rr-type-icon ${typeMeta.cls}" style="display:inline-flex;margin-right:6px;vertical-align:middle">
                            <i class="fa-solid ${typeMeta.icon}"></i>
                        </span>${esc(r.report_type)}
                    </div>
                </div>
                <div class="rr-view-field">
                    <div class="rr-view-label">Date Submitted</div>
                    <div class="rr-view-value">${_fmtDateTime(r.created_at)}</div>
                </div>
                <div class="rr-view-field">
                    <div class="rr-view-label">Last Updated</div>
                    <div class="rr-view-value">${_fmtDateTime(r.updated_at)}</div>
                </div>
                <div class="rr-view-field">
                    <div class="rr-view-label">GPS Coordinates</div>
                    <div class="rr-view-value">${gpsHtml}</div>
                </div>
                <div class="rr-view-field">
                    <div class="rr-view-label">Verified By</div>
                    <div class="rr-view-value">${verifiedHtml}</div>
                </div>
                <div class="rr-view-field full">
                    <div class="rr-view-label">Description</div>
                    <div class="rr-view-value">${esc(r.description ?? '—')}</div>
                </div>
                <div class="rr-view-field full">
                    <div class="rr-view-label">Resolution Notes</div>
                    ${resolutionHtml}
                </div>
                ${imageHtml}
            </div>`;

        ui.btnUpdateFromView.dataset.id = id;

    } catch (err) {
        console.error('openViewModal:', err);
        toast('Failed to load report details.', 'error');
        _closeModal(ui.viewModal);
    }
};

/* ══════════════════════════════════════════════════════
   MODAL — UPDATE STATUS
════════════════════════════════════════════════════════ */
window.openUpdateModal = async function(id) {
    // Grab latest data
    let report = state.reports.find(r => r.report_id === id);
    if (!report) {
        const json = await fetchSingleReport(id);
        if (!json.success) { toast(json.message, 'error'); return; }
        report = json.data;
    }

    state.updatingId = id;
    ui.updateReportId.value = id;

    // Pre-fill status buttons
    _setActiveStatusBtn(report.status);

    // Pre-fill resolution notes (clear so they can type fresh)
    ui.updateResolutionNotes.value = '';

    // Report info strip
    const typeMeta = TYPE_META[report.report_type] ?? TYPE_META['Other'];
    ui.updateReportInfo.innerHTML = `
        <div class="rr-update-info-icon ${typeMeta.cls}">
            <i class="fa-solid ${typeMeta.icon}"></i>
        </div>
        <div class="rr-update-info-text">
            <strong>${esc(report.report_type)} — ${esc(report.severity)} Severity</strong>
            <span>${esc(report.street_name ?? '—')}, ${esc(report.barangay ?? '')} &bull; by ${esc(report.reporter_name ?? 'Unknown')}</span>
        </div>`;

    // Disable submit until a status is actively chosen
    ui.btnSubmitUpdate.disabled = true;
    _openModal(ui.updateModal);
};

function _setActiveStatusBtn(status) {
    ui.updateStatus.value = status;
    document.querySelectorAll('.rr-status-choice').forEach(btn => {
        btn.classList.toggle('active', btn.dataset.value === status);
    });
}

/* ══════════════════════════════════════════════════════
   MODAL — DELETE
════════════════════════════════════════════════════════ */
window.openDeleteModal = function(id, reporterName) {
    state.deletingId = id;
    ui.deleteTarget.textContent = reporterName || 'this resident';
    _openModal(ui.deleteModal);
};

/* ══════════════════════════════════════════════════════
   CSV EXPORT
════════════════════════════════════════════════════════ */
function exportCsv() {
    if (!state.reports.length) {
        toast('No reports to export. Apply filters first, then export.', 'info');
        return;
    }

    const headers = ['#', 'Reporter', 'Phone', 'Report Type', 'Street', 'Severity', 'Status',
                     'Description', 'GPS', 'Submitted', 'Verified By', 'Resolution Notes'];

    const rows = state.reports.map((r, i) => [
        (state.page - 1) * PER_PAGE + i + 1,
        r.reporter_name ?? '',
        r.reporter_phone ?? '',
        r.report_type,
        `${r.street_name ?? ''} ${r.barangay ?? ''}`.trim(),
        r.severity,
        r.status,
        (r.description ?? '').replace(/"/g, '""'),
        (r.latitude && r.longitude) ? `${r.latitude},${r.longitude}` : '',
        _fmtDate(r.created_at),
        r.verifier_name ?? '',
        (r.resolution_notes ?? '').replace(/"/g, '""'),
    ]);

    const csv = [headers, ...rows]
        .map(row => row.map(v => `"${v}"`).join(','))
        .join('\n');

    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const url  = URL.createObjectURL(blob);
    const a    = document.createElement('a');
    a.href     = url;
    a.download = `resident-reports-${new Date().toISOString().slice(0,10)}.csv`;
    a.click();
    URL.revokeObjectURL(url);
    toast('CSV exported successfully.', 'success');
}

/* ══════════════════════════════════════════════════════
   BIND EVENTS
════════════════════════════════════════════════════════ */
function bindEvents() {

    // ── Toolbar filters ─────────────────────────────────
    let searchTimer;
    ui.search.addEventListener('input', () => {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => {
            state.filters.search = ui.search.value.trim();
            state.page = 1;
            loadReports();
        }, 350);
    });

    ui.filterStatus.addEventListener('change', () => {
        state.filters.status = ui.filterStatus.value;
        state.page = 1; loadReports();
    });
    ui.filterSeverity.addEventListener('change', () => {
        state.filters.severity = ui.filterSeverity.value;
        state.page = 1; loadReports();
    });
    ui.filterType.addEventListener('change', () => {
        state.filters.report_type = ui.filterType.value;
        state.page = 1; loadReports();
    });
    ui.filterStreet.addEventListener('change', () => {
        state.filters.street_id = ui.filterStreet.value;
        state.page = 1; loadReports();
    });

    // ── Export ───────────────────────────────────────────
    $id('btnExportCsv').addEventListener('click', exportCsv);

    // ── View modal ──────────────────────────────────────
    $id('btnCloseViewModal').addEventListener('click',       () => _closeModal(ui.viewModal));
    $id('btnCloseViewModalBottom').addEventListener('click', () => _closeModal(ui.viewModal));
    ui.btnUpdateFromView.addEventListener('click', () => {
        const id = +ui.btnUpdateFromView.dataset.id;
        _closeModal(ui.viewModal);
        setTimeout(() => openUpdateModal(id), 160);
    });

    // ── Update modal: status choice buttons ─────────────
    document.querySelectorAll('.rr-status-choice').forEach(btn => {
        btn.addEventListener('click', () => {
            _setActiveStatusBtn(btn.dataset.value);
            ui.btnSubmitUpdate.disabled = false;
        });
    });

    // ── Update modal: submit ─────────────────────────────
    ui.btnSubmitUpdate.addEventListener('click', async () => {
        const newStatus = ui.updateStatus.value;
        if (!newStatus) { toast('Please select a new status.', 'error'); return; }

        const payload = {
            status: newStatus,
            resolution_notes: ui.updateResolutionNotes.value.trim() || null,
        };

        ui.btnSubmitUpdate.disabled  = true;
        ui.btnSubmitUpdate.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin"></i> Saving…';

        const json = await updateReport(state.updatingId, payload);

        ui.btnSubmitUpdate.disabled  = false;
        ui.btnSubmitUpdate.innerHTML = '<i class="fa-solid fa-floppy-disk"></i> <span>Save Changes</span>';

        if (json.success) {
            toast(json.message, 'success');

            // ✅ Log it
            const report = state.reports.find(r => r.report_id === state.updatingId);
            const label  = report ? `#${report.report_id} (${report.report_type})` : `#${state.updatingId}`;
            logActivity(`Updated report ${label} status to "${payload.status}"`, 'Reports');

            _closeModal(ui.updateModal);
            state.page = 1;
            loadReports();
        } else {
            toast(json.message || 'An error occurred.', 'error');
        }
        state.updatingId = null;
    });

    $id('btnCloseUpdateModal').addEventListener('click', () => _closeModal(ui.updateModal));
    $id('btnCancelUpdate').addEventListener('click',     () => _closeModal(ui.updateModal));

    // ── Delete modal ─────────────────────────────────────
    $id('btnCloseDeleteModal').addEventListener('click', () => _closeModal(ui.deleteModal));
    $id('btnCancelDelete').addEventListener('click',     () => _closeModal(ui.deleteModal));

    ui.btnConfirmDel.addEventListener('click', async () => {
        if (!state.deletingId) return;

        ui.btnConfirmDel.disabled  = true;
        ui.btnConfirmDel.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin"></i> Deleting…';

        const json = await deleteReport(state.deletingId);

        ui.btnConfirmDel.disabled  = false;
        ui.btnConfirmDel.innerHTML = '<i class="fa-solid fa-trash"></i> Delete Report';

        if (json.success) {
            toast(json.message, 'success');
            // ✅ Log it
            const report = state.reports.find(r => r.report_id === state.deletingId);
            const label  = report ? `#${report.report_id} (${report.report_type})` : `#${state.deletingId}`;
            logActivity(`Deleted report ${label}`, 'Reports');
            _closeModal(ui.deleteModal);
            state.page = 1;
            loadReports();
        } else {
            toast(json.message || 'Delete failed.', 'error');
        }
        state.deletingId = null;
    });

    // ── Close modals on backdrop click ───────────────────
    [ui.viewModal, ui.updateModal, ui.deleteModal].forEach(modal => {
        modal.addEventListener('click', e => {
            if (e.target === modal) _closeModal(modal);
        });
    });

    // ── ESC key ─────────────────────────────────────────
    document.addEventListener('keydown', e => {
        if (e.key === 'Escape') {
            [ui.viewModal, ui.updateModal, ui.deleteModal].forEach(m => {
                if (m.classList.contains('open')) _closeModal(m);
            });
        }
    });
}

/* ══════════════════════════════════════════════════════
   MODAL HELPERS
════════════════════════════════════════════════════════ */
function _openModal(modal) {
    modal.classList.add('open');
    document.body.style.overflow = 'hidden';
}

function _closeModal(modal) {
    modal.classList.remove('open');
    document.body.style.overflow = '';
}

/* ══════════════════════════════════════════════════════
   TABLE LOADING STATE
════════════════════════════════════════════════════════ */
function _setTableLoading() {
    ui.tbody.innerHTML = `<tr><td colspan="8" class="tbl-loading">
        <i class="fa-solid fa-circle-notch fa-spin"></i>&nbsp; Loading reports…
    </td></tr>`;
}

function _setTableError(msg) {
    ui.tbody.innerHTML = `<tr><td colspan="8" class="tbl-loading" style="color:#dc2626">${msg}</td></tr>`;
}

/* ══════════════════════════════════════════════════════
   TOAST
════════════════════════════════════════════════════════ */
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

/* ══════════════════════════════════════════════════════
   HELPERS
════════════════════════════════════════════════════════ */

/** HTML-escape a string */
function esc(str) {
    if (!str) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

/** Format date string to short human-readable */
function _fmtDate(dateStr) {
    if (!dateStr) return '—';
    const d = new Date(dateStr);
    return d.toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' });
}

/** Format datetime string */
function _fmtDateTime(dtStr) {
    if (!dtStr) return '—';
    const d = new Date(dtStr);
    return d.toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' })
         + ' '
         + d.toLocaleTimeString('en-PH', { hour: '2-digit', minute: '2-digit' });
}

/** Relative time (e.g. "2 hours ago") */
function _timeAgo(dateStr) {
    if (!dateStr) return '';
    const diff = Date.now() - new Date(dateStr).getTime();
    const m    = Math.floor(diff / 60000);
    if (m < 1)   return 'Just now';
    if (m < 60)  return `${m}m ago`;
    const h = Math.floor(m / 60);
    if (h < 24)  return `${h}h ago`;
    const d = Math.floor(h / 24);
    if (d < 7)   return `${d}d ago`;
    return _fmtDate(dateStr);
}

/** Generate 2-letter initials from a name */
function _initials(name) {
    if (!name) return '?';
    const parts = name.trim().split(/\s+/);
    if (parts.length === 1) return parts[0][0].toUpperCase();
    return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
}