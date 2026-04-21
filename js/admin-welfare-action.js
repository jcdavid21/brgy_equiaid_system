/**
 * welfare-action.js
 * Barangay EQUIAID — Welfare Action Plan page logic
 * Handles CRUD operations, table rendering, filters, pagination, and modals.
 */

'use strict';

/* ══════════════════════════════════════════════════════
   CONFIG
════════════════════════════════════════════════════════ */
const API_URL  = '../backend/admin-welfare-action.php';
const PER_PAGE = 15;

/* ══════════════════════════════════════════════════════
   STATE
═══════════════════════════════════════════════════════ */
const state = {
    plans:      [],
    streets:    [],
    users:      [],
    events:     [],
    kpi:        {},
    page:       1,
    lastPage:   1,
    total:      0,
    editingId:  null,
    deletingId: null,
    viewingPlan: null,
    stepCount:  0,
    filters: {
        search:    '',
        status:    '',
        priority:  '',
        type:      '',
    },
};

/* ══════════════════════════════════════════════════════
   NEED LABEL / ICON MAP
════════════════════════════════════════════════════════ */
const NEED_META = {
    food:       { label: 'Food',       icon: 'fa-utensils',         cls: 'food'      },
    medical:    { label: 'Medical',    icon: 'fa-briefcase-medical', cls: 'medical'   },
    financial:  { label: 'Financial',  icon: 'fa-peso-sign',         cls: 'financial' },
    shelter:    { label: 'Shelter',    icon: 'fa-house',             cls: 'shelter'   },
    water:      { label: 'Water',      icon: 'fa-droplet',           cls: 'water'     },
    livelihood: { label: 'Livelihood', icon: 'fa-briefcase',         cls: 'financial' },
};

const PRIORITY_META = {
    High:   { icon: 'fa-arrow-up',    cls: 'high'   },
    Medium: { icon: 'fa-minus',       cls: 'medium' },
    Low:    { icon: 'fa-arrow-down',  cls: 'low'    },
};

const STATUS_META = {
    Planned:   { icon: 'fa-clock',        cls: 'planned'   },
    Ongoing:   { icon: 'fa-spinner',      cls: 'ongoing'   },
    Completed: { icon: 'fa-circle-check', cls: 'completed' },
    Cancelled: { icon: 'fa-ban',          cls: 'cancelled' },
};

const BTYPE_META = {
    street:     { label: 'Street',     cls: 'street'     },
    family:     { label: 'Family',     cls: 'family'     },
    individual: { label: 'Individual', cls: 'individual' },
};

/* ══════════════════════════════════════════════════════
   DOM REFERENCES
════════════════════════════════════════════════════════ */
const $ = id => document.getElementById(id);

const ui = {
    tbody:         $('wapTableBody'),
    kpiTotal:      $('kpiTotal'),
    kpiPlanned:    $('kpiPlanned'),
    kpiOngoing:    $('kpiOngoing'),
    kpiCompleted:  $('kpiCompleted'),
    kpiHighPrio:   $('kpiHighPriority'),
    paginationInfo:$('wapPaginationInfo'),
    pagination:    $('wapPagination'),
    recordCount:   $('wapRecordCount'),
    search:        $('wapSearch'),
    filterStatus:  $('filterStatus'),
    filterPriority:$('filterPriority'),
    filterType:    $('filterType'),

    // Form modal
    formModal:     $('wapFormModal'),
    form:          $('wapForm'),
    formPlanId:    $('formPlanId'),
    formStreetId:  $('formStreetId'),
    formBType:     $('formBeneficiaryType'),
    formBName:     $('formBeneficiaryName'),
    formAssType:   $('formAssistanceType'),
    formEventId:   $('formEventId'),
    formAssignedTo:$('formAssignedTo'),
    formPriority:  $('formPriority'),
    formStatus:    $('formStatus'),
    formRiskBefore: $('formRiskBefore'),
    formRiskAfter:  $('formRiskAfter'),
    formPlannedDate:$('formPlannedDate'),
    formTargetDate:$('formTargetDate'),
    formDesc:      $('formDescription'),
    formRemarks:   $('formRemarks'),
    stepsContainer:$('stepsContainer'),
    btnAddStep:    $('btnAddStep'),
    modalEyebrow:  $('wapModalEyebrow'),
    modalTitle:    $('wapModalTitle'),
    btnSubmitLabel:$('btnSubmitLabel'),
    btnSubmit:     $('btnSubmitForm'),

    // View modal
    viewModal:     $('wapViewModal'),
    viewModalTitle:$('viewModalTitle'),
    viewModalBody: $('viewModalBody'),
    btnEditFromView:$('btnEditFromView'),

    // Delete modal
    deleteModal:   $('wapDeleteModal'),
    deleteTarget:  $('deleteTargetLabel'),
    btnConfirmDel: $('btnConfirmDelete'),

    toastCont:     $('wapToastContainer'),
};

/* ══════════════════════════════════════════════════════
   INIT
════════════════════════════════════════════════════════ */
document.addEventListener('DOMContentLoaded', () => {
    loadMeta().then(() => loadPlans());
    bindEvents();
});

/* ══════════════════════════════════════════════════════
   API CALLS
════════════════════════════════════════════════════════ */
async function loadMeta() {
    try {
        const res  = await fetch(`${API_URL}?meta=1`);
        const json = await res.json();
        if (!json.success) {
            console.error('loadMeta failed:', json.message);
            toast('Failed to load form options: ' + json.message, 'error'); // ← add this
            return;
        }

        state.streets = json.data.streets || [];
        state.users   = json.data.users   || [];
        state.events  = json.data.events  || [];

        _populateSelect(ui.formStreetId,  state.streets, 'street_id',  s => `${s.street_name} — ${s.barangay}`);
        _populateSelect(ui.formAssignedTo,state.users,   'id',          u => `${u.name} (${u.role})`);
        _populateSelect(ui.formEventId,   state.events,  'event_id',    e => `${e.event_name} (${e.event_date})`);
    } catch (err) {
        console.error('loadMeta:', err);
    }
}

async function loadPlans() {
    _setTableLoading();

    const params = new URLSearchParams({
        page:   state.page,
        status: state.filters.status,
        priority: state.filters.priority,
        assistance_type: state.filters.type,
        search: state.filters.search,
    });

    try {
        const res  = await fetch(`${API_URL}?${params}`);
        const json = await res.json();
        if (!json.success) { _setTableError(json.message); return; }

        const { plans, kpi, total, last_page } = json.data;

        state.plans    = plans;
        state.kpi      = kpi;
        state.total    = total;
        state.lastPage = last_page;

        _renderKpi(kpi);
        _renderTable(plans);
        _renderPagination(total, last_page);
    } catch (err) {
        console.error('loadPlans:', err);
        _setTableError('Failed to load plans.');
    }
}

async function savePlan(payload) {
    const isEdit = !!state.editingId;
    const url    = isEdit ? `${API_URL}?id=${state.editingId}` : API_URL;
    const method = isEdit ? 'PUT' : 'POST';

    const res  = await fetch(url, {
        method,
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
    });
    return res.json();
}

async function deletePlan(id) {
    const res  = await fetch(`${API_URL}?id=${id}`, { method: 'DELETE' });
    return res.json();
}

async function fetchSinglePlan(id) {
    const res  = await fetch(`${API_URL}?id=${id}`);
    return res.json();
}

/* ══════════════════════════════════════════════════════
   RENDER — KPI
════════════════════════════════════════════════════════ */
function _renderKpi(kpi) {
    const highPrio = state.plans.filter(p => p.priority === 'High').length;

    ui.kpiTotal.textContent     = kpi.total      ?? 0;
    ui.kpiPlanned.textContent   = kpi.planned     ?? 0;
    ui.kpiOngoing.textContent   = kpi.ongoing     ?? 0;
    ui.kpiCompleted.textContent = kpi.completed   ?? 0;
    ui.kpiHighPrio.textContent  = highPrio;

    [ui.kpiTotal, ui.kpiPlanned, ui.kpiOngoing, ui.kpiCompleted, ui.kpiHighPrio]
        .forEach(el => el.classList.remove('sk-inline'));
}

/* ══════════════════════════════════════════════════════
   RENDER — TABLE
════════════════════════════════════════════════════════ */
function _renderTable(plans) {
    if (!plans || plans.length === 0) {
        ui.tbody.innerHTML = `
            <tr>
                <td colspan="9">
                    <div class="wap-empty">
                        <div class="wap-empty-icon"><i class="fa-solid fa-file-shield"></i></div>
                        <div class="wap-empty-title">No Plans Found</div>
                        <p class="wap-empty-desc">No welfare action plans match your current filters. Try adjusting your search or create a new plan.</p>
                    </div>
                </td>
            </tr>`;
        ui.recordCount.textContent = '';
        return;
    }

    const offset = (state.page - 1) * PER_PAGE;
    ui.tbody.innerHTML = plans.map((p, i) => _buildRow(p, offset + i + 1)).join('');

    // Attach row-level events
    ui.tbody.querySelectorAll('[data-action]').forEach(btn => {
        btn.addEventListener('click', e => {
            e.stopPropagation();
            const action = btn.dataset.action;
            const id     = +btn.dataset.id;
            if (action === 'view')   openViewModal(id);
            if (action === 'edit')   openEditModal(id);
            if (action === 'delete') openDeleteModal(id, btn.dataset.label);
        });
    });

    // Row click → view
    ui.tbody.querySelectorAll('tr[data-plan-id]').forEach(row => {
        row.addEventListener('click', () => openViewModal(+row.dataset.planId));
    });

    ui.recordCount.textContent = `${plans.length} of ${state.total} plans`;
}

function _buildRow(p, num) {
    const bType = BTYPE_META[p.beneficiary_type] || BTYPE_META.street;
    const initials = (p.beneficiary_name || p.street_name || '?')
        .split(' ').slice(0, 2).map(w => w[0]).join('').toUpperCase();

    // Beneficiary label
    const bLabel = p.beneficiary_name
        ? `<div class="wap-beneficiary-name">${esc(p.beneficiary_name)}</div>
           <div class="wap-beneficiary-type">${esc(p.street_name)} · ${bType.label}</div>`
        : `<div class="wap-beneficiary-name">${esc(p.street_name)}</div>
           <div class="wap-beneficiary-type">${esc(p.barangay)} · ${bType.label}</div>`;

    // Needs tags (show max 2 + count)
    const needs   = Array.isArray(p.needs) ? p.needs : [];
    const shown   = needs.slice(0, 2);
    const extra   = needs.length - shown.length;
    const needsHtml = shown.map(n => {
        const m = NEED_META[n] || { label: n, icon: 'fa-tag', cls: '' };
        return `<span class="wap-need-tag ${m.cls}"><i class="fa-solid ${m.icon}" style="font-size:9px"></i>${m.label}</span>`;
    }).join('') + (extra > 0 ? `<span class="wap-more-tag">+${extra}</span>` : '');

    // Priority
    const pm    = PRIORITY_META[p.priority] || PRIORITY_META.Medium;
    const pHtml = `<span class="wap-priority ${pm.cls}"><i class="fa-solid ${pm.icon}"></i>${p.priority || 'Medium'}</span>`;

    // Status
    const sm    = STATUS_META[p.status] || STATUS_META.Planned;
    const sHtml = `<span class="wap-status ${sm.cls}"><i class="fa-solid ${sm.icon}"></i>${p.status}</span>`;

    // Assigned
    const assigned = p.assigned_name
        ? `<div class="user-cell"><div class="user-avatar">${p.assigned_name.split(' ').map(w=>w[0]).join('').slice(0,2).toUpperCase()}</div><span>${esc(p.assigned_name)}</span></div>`
        : `<span style="color:var(--slate-mid);font-size:12px">—</span>`;

    // Date
    const today    = new Date().toDateString();
    const planned  = p.planned_date ? _fmtDate(p.planned_date) : '—';
    const target   = p.target_date  ? p.target_date : null;
    const isOver   = target && new Date(target) < new Date() && p.status !== 'Completed';
    const dateHtml = `<div class="wap-date-range">
        <span class="date-start">${planned}</span>
        ${target ? `<span class="${isOver ? 'date-overdue' : 'date-end'}">→ ${_fmtDate(target)}${isOver ? ' ⚠' : ''}</span>` : ''}
    </div>`;

    const displayLabel = p.beneficiary_name || p.street_name;

    return `
    <tr data-plan-id="${p.plan_id}">
        <td class="rank-cell">${num}</td>
        <td>
            <div class="wap-beneficiary-cell">
                <div>${bLabel}</div>
            </div>
        </td>
        <td style="font-size:13px;font-weight:500;color:var(--text-dark)">${esc(p.assistance_type)}</td>
        <td><div class="wap-needs-wrap">${needsHtml || '<span style="color:var(--slate-mid);font-size:12px">—</span>'}</div></td>
        <td>${pHtml}</td>
        <td>${sHtml}</td>
        <td>${assigned}</td>
        <td class="date-cell">${dateHtml}</td>
        <td>
            <div class="wap-row-actions">
                <button class="wap-icon-btn" data-action="view" data-id="${p.plan_id}" title="View Details">
                    <i class="fa-solid fa-eye"></i>
                </button>
                <button class="wap-icon-btn" data-action="edit" data-id="${p.plan_id}" title="Edit Plan">
                    <i class="fa-solid fa-pen"></i>
                </button>
                <button class="wap-icon-btn danger" data-action="delete" data-id="${p.plan_id}"
                        data-label="${esc(displayLabel)}" title="Delete Plan">
                    <i class="fa-solid fa-trash"></i>
                </button>
            </div>
        </td>
    </tr>`;
}

/* ══════════════════════════════════════════════════════
   RENDER — PAGINATION
════════════════════════════════════════════════════════ */
function _renderPagination(total, lastPage) {
    const from = total === 0 ? 0 : (state.page - 1) * PER_PAGE + 1;
    const to   = Math.min(state.page * PER_PAGE, total);
    ui.paginationInfo.innerHTML = `Showing <strong>${from}–${to}</strong> of <strong>${total}</strong> plans`;

    const pages = [];
    pages.push(`<button class="page-btn" id="pgPrev" ${state.page === 1 ? 'disabled' : ''}>
                    <i class="fa-solid fa-chevron-left"></i>
                </button>`);

    const range = _pageRange(state.page, lastPage);
    range.forEach(p => {
        if (p === '…') {
            pages.push(`<span class="page-btn" style="cursor:default;pointer-events:none">…</span>`);
        } else {
            pages.push(`<button class="page-btn ${p === state.page ? 'active' : ''}" data-pg="${p}">${p}</button>`);
        }
    });

    pages.push(`<button class="page-btn" id="pgNext" ${state.page === lastPage ? 'disabled' : ''}>
                    <i class="fa-solid fa-chevron-right"></i>
                </button>`);

    ui.pagination.innerHTML = pages.join('');

    ui.pagination.querySelector('#pgPrev')?.addEventListener('click', () => { state.page--; loadPlans(); });
    ui.pagination.querySelector('#pgNext')?.addEventListener('click', () => { state.page++; loadPlans(); });
    ui.pagination.querySelectorAll('[data-pg]').forEach(btn => {
        btn.addEventListener('click', () => { state.page = +btn.dataset.pg; loadPlans(); });
    });
}

function _pageRange(cur, last) {
    if (last <= 7) return Array.from({ length: last }, (_, i) => i + 1);
    if (cur <= 4)  return [1, 2, 3, 4, 5, '…', last];
    if (cur >= last - 3) return [1, '…', last-4, last-3, last-2, last-1, last];
    return [1, '…', cur-1, cur, cur+1, '…', last];
}

/* ══════════════════════════════════════════════════════
   MODALS — FORM (CREATE / EDIT)
════════════════════════════════════════════════════════ */
function openCreateModal() {
    state.editingId = null;
    _resetForm();
    ui.modalEyebrow.textContent  = 'New Plan';
    ui.modalTitle.textContent    = 'Create Welfare Action Plan';
    ui.btnSubmitLabel.textContent = 'Save Plan';
    _addStep(); // start with 1 empty step
    _openModal(ui.formModal);
}

async function openEditModal(id) {
    state.editingId = id;
    _resetForm();
    ui.modalEyebrow.textContent  = `Edit Plan #${id}`;
    ui.modalTitle.textContent    = 'Edit Welfare Action Plan';
    ui.btnSubmitLabel.textContent = 'Update Plan';
    ui.btnSubmit.disabled = true;
    ui.btnSubmit.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin"></i> Loading…';

    const json = await fetchSinglePlan(id);
    ui.btnSubmit.disabled = false;
    ui.btnSubmit.innerHTML = '<i class="fa-solid fa-floppy-disk"></i> <span id="btnSubmitLabel">Update Plan</span>';

    if (!json.success) { toast('Failed to load plan.', 'error'); return; }
    _fillForm(json.data);
    _openModal(ui.formModal);
}

function _fillForm(p) {
    ui.formPlanId.value         = p.plan_id;
    ui.formStreetId.value       = p.street_id    || '';
    ui.formBType.value          = p.beneficiary_type || 'street';
    ui.formBName.value          = p.beneficiary_name || '';
    ui.formAssType.value        = p.assistance_type  || '';
    ui.formEventId.value        = p.event_id     || '';
    ui.formPriority.value       = p.priority     || 'Medium';
    ui.formStatus.value         = p.status       || 'Planned';
    _toggleRiskAfter(); // must run after status is set
    ui.formRiskBefore.value     = p.risk_level_before || '';
    ui.formRiskAfter.value      = p.risk_level_after  || '';
    ui.formPlannedDate.value    = p.planned_date || '';
    ui.formTargetDate.value     = p.target_date  || '';
    ui.formDesc.value           = p.description  || '';
    ui.formRemarks.value        = p.remarks      || '';
    ui.formAssignedTo.value     = p.assigned_to  || '';

    // Needs checkboxes
    const needs = Array.isArray(p.needs) ? p.needs : [];
    ui.formModal.querySelectorAll('[name="needs[]"]').forEach(cb => {
        cb.checked = needs.includes(cb.value);
    });

    // Steps
    state.stepCount = 0;
    ui.stepsContainer.innerHTML = '';
    const steps = Array.isArray(p.steps) ? p.steps : [];
    if (steps.length === 0) {
        _addStep();
    } else {
        steps.forEach(s => _addStep(s));
    }
}

function _resetForm() {
    ui.form.reset();
    ui.formPlanId.value = '';
    state.stepCount = 0;
    ui.stepsContainer.innerHTML = '';
    _toggleRiskAfter(); // reset to disabled state
}

function _addStep(value = '') {
    state.stepCount++;
    const num  = state.stepCount;
    const item = document.createElement('div');
    item.className = 'wap-step-item';
    item.dataset.stepNum = num;
    item.innerHTML = `
        <div class="wap-step-num">${num}</div>
        <input type="text" class="wap-step-input" placeholder="Describe action step ${num}…" value="${esc(value)}">
        <button type="button" class="wap-step-del" title="Remove step"><i class="fa-solid fa-xmark"></i></button>`;

    item.querySelector('.wap-step-del').addEventListener('click', () => {
        item.remove();
        _renumberSteps();
    });

    ui.stepsContainer.appendChild(item);
}

function _renumberSteps() {
    ui.stepsContainer.querySelectorAll('.wap-step-item').forEach((el, i) => {
        el.querySelector('.wap-step-num').textContent = i + 1;
    });
    state.stepCount = ui.stepsContainer.querySelectorAll('.wap-step-item').length;
}

function _buildPayload() {
    const steps = Array.from(ui.stepsContainer.querySelectorAll('.wap-step-input'))
        .map(i => i.value.trim()).filter(Boolean);

    const needs = Array.from(ui.formModal.querySelectorAll('[name="needs[]"]:checked'))
        .map(cb => cb.value);

    return {
        street_id:        ui.formStreetId.value,
        event_id:         ui.formEventId.value,
        beneficiary_type: ui.formBType.value,
        beneficiary_name: ui.formBName.value.trim(),
        assistance_type:  ui.formAssType.value,
        priority:         ui.formPriority.value,
        status:           ui.formStatus.value,
        risk_level_before:ui.formRiskBefore.value,
        risk_level_after: ui.formStatus.value === 'Completed' ? ui.formRiskAfter.value : '',
        planned_date:     ui.formPlannedDate.value,
        target_date:      ui.formTargetDate.value,
        description:      ui.formDesc.value.trim(),
        remarks:          ui.formRemarks.value.trim(),
        assigned_to:      ui.formAssignedTo.value,
        needs,
        steps,
    };
}

/* ══════════════════════════════════════════════════════
   MODALS — VIEW DETAILS
════════════════════════════════════════════════════════ */
async function openViewModal(id) {
    _openModal(ui.viewModal);
    ui.viewModalTitle.textContent = `Loading…`;
    ui.viewModalBody.innerHTML    = `<div style="text-align:center;padding:32px;color:var(--slate-mid)"><i class="fa-solid fa-circle-notch fa-spin"></i></div>`;

    const json = await fetchSinglePlan(id);
    if (!json.success) {
        ui.viewModalBody.innerHTML = `<p style="color:#dc2626">${json.message}</p>`;
        return;
    }

    const p = json.data;
    state.viewingPlan = p;
    ui.viewModalTitle.textContent = `Action Plan #${p.plan_id}`;
    ui.btnEditFromView.dataset.id = p.plan_id;

    const pm    = PRIORITY_META[p.priority] || PRIORITY_META.Medium;
    const sm    = STATUS_META[p.status]     || STATUS_META.Planned;
    const needs = Array.isArray(p.needs) ? p.needs : [];
    const steps = Array.isArray(p.steps) ? p.steps : [];

    const needsHtml = needs.length
        ? needs.map(n => {
            const m = NEED_META[n] || { label: n, icon: 'fa-tag', cls: '' };
            return `<span class="wap-need-tag ${m.cls}"><i class="fa-solid ${m.icon}" style="font-size:9px"></i>${m.label}</span>`;
          }).join('')
        : '—';

    const stepsHtml = steps.length
        ? steps.map((s, i) => `
            <div class="wap-view-step">
                <div class="wap-view-step-num">${i+1}</div>
                <div class="wap-view-step-text">${esc(s)}</div>
            </div>`).join('')
        : '<span style="color:var(--slate-mid);font-size:13px">No steps defined.</span>';

    const riskBadge = r => r
        ? `<span class="risk-pill risk-${r.toLowerCase()}">${r}</span>`
        : '—';

    ui.viewModalBody.innerHTML = `
        <div class="wap-view-field">
            <span class="wap-view-label">Street / Location</span>
            <span class="wap-view-value">${esc(p.street_name)} — ${esc(p.barangay)}</span>
        </div>
        <div class="wap-view-field">
            <span class="wap-view-label">Beneficiary</span>
            <span class="wap-view-value">${p.beneficiary_name ? esc(p.beneficiary_name) : `${esc(p.street_name)} (${(BTYPE_META[p.beneficiary_type]||{}).label||'Street'})`}</span>
        </div>
        <div class="wap-view-field">
            <span class="wap-view-label">Assistance Type</span>
            <span class="wap-view-value">${esc(p.assistance_type)}</span>
        </div>
        <div class="wap-view-field">
            <span class="wap-view-label">Priority</span>
            <span class="wap-view-value"><span class="wap-priority ${pm.cls}"><i class="fa-solid ${pm.icon}"></i>${p.priority||'Medium'}</span></span>
        </div>
        <div class="wap-view-field">
            <span class="wap-view-label">Status</span>
            <span class="wap-view-value"><span class="wap-status ${sm.cls}"><i class="fa-solid ${sm.icon}"></i>${p.status}</span></span>
        </div>
        <div class="wap-view-field">
            <span class="wap-view-label">Assigned To</span>
            <span class="wap-view-value">${p.assigned_name ? esc(p.assigned_name) : '— Unassigned'}</span>
        </div>
        <div class="wap-view-field">
            <span class="wap-view-label">Planned Date</span>
            <span class="wap-view-value">${p.planned_date ? _fmtDate(p.planned_date) : '—'}</span>
        </div>
        <div class="wap-view-field">
            <span class="wap-view-label">Target Completion</span>
            <span class="wap-view-value">${p.target_date ? _fmtDate(p.target_date) : '—'}</span>
        </div>
        <div class="wap-view-field">
            <span class="wap-view-label">Risk Level Before</span>
            <span class="wap-view-value">${riskBadge(p.risk_level_before)}</span>
        </div>
        <div class="wap-view-field">
            <span class="wap-view-label">Risk Level After</span>
            <span class="wap-view-value">${riskBadge(p.risk_level_after)}</span>
        </div>
        <div class="wap-view-field full">
            <span class="wap-view-label">Identified Needs</span>
            <div class="wap-needs-wrap" style="margin-top:4px">${needsHtml}</div>
        </div>
        <div class="wap-view-field full">
            <span class="wap-view-label">Description</span>
            <span class="wap-view-value">${p.description ? esc(p.description) : '—'}</span>
        </div>
        <div class="wap-view-field full">
            <span class="wap-view-label">Action Steps</span>
            <div class="wap-view-steps">${stepsHtml}</div>
        </div>
        <div class="wap-view-field full">
            <span class="wap-view-label">Remarks / Notes</span>
            <span class="wap-view-value">${p.remarks || '—'}</span>
        </div>
        <div class="wap-view-field">
            <span class="wap-view-label">Created At</span>
            <span class="wap-view-value" style="font-size:12px;color:var(--text-muted)">${_fmtDateTime(p.created_at)}</span>
        </div>
        <div class="wap-view-field">
            <span class="wap-view-label">Last Updated</span>
            <span class="wap-view-value" style="font-size:12px;color:var(--text-muted)">${_fmtDateTime(p.updated_at)}</span>
        </div>`;
}

/* ══════════════════════════════════════════════════════
   MODALS — DELETE
════════════════════════════════════════════════════════ */
function openDeleteModal(id, label) {
    state.deletingId = id;
    ui.deleteTarget.textContent = label || `Plan #${id}`;
    _openModal(ui.deleteModal);
}

/* ══════════════════════════════════════════════════════
   EVENT BINDING
════════════════════════════════════════════════════════ */
function bindEvents() {
    // Status → enable/disable Risk Level After
    ui.formStatus.addEventListener('change', _toggleRiskAfter);

    // Toolbar
    $('btnCreatePlan').addEventListener('click', openCreateModal);

    let searchTimer;
    ui.search.addEventListener('input', () => {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => {
            state.filters.search = ui.search.value.trim();
            state.page = 1;
            loadPlans();
        }, 350);
    });

    ui.filterStatus.addEventListener('change', () => {
        state.filters.status = ui.filterStatus.value;
        state.page = 1; loadPlans();
    });
    ui.filterPriority.addEventListener('change', () => {
        state.filters.priority = ui.filterPriority.value;
        state.page = 1; loadPlans();
    });
    ui.filterType.addEventListener('change', () => {
        state.filters.type = ui.filterType.value;
        state.page = 1; loadPlans();
    });

    // Form modal
    ui.btnAddStep.addEventListener('click', () => _addStep());

    ui.btnSubmit.addEventListener('click', async () => {
        const payload = _buildPayload();

        // Basic validation
        if (!payload.street_id) { _flashError(ui.formStreetId, 'Please select a street.'); return; }
        if (!payload.assistance_type) { _flashError(ui.formAssType, 'Please select assistance type.'); return; }
        if (!payload.planned_date) { _flashError(ui.formPlannedDate, 'Please set a planned date.'); return; }

        ui.btnSubmit.disabled = true;
        ui.btnSubmit.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin"></i> Saving…';

        const json = await savePlan(payload);

        ui.btnSubmit.disabled = false;
        ui.btnSubmit.innerHTML = `<i class="fa-solid fa-floppy-disk"></i> <span>${state.editingId ? 'Update' : 'Save'} Plan</span>`;

        if (json.success) {
            toast(json.message, 'success');

            // ✅ Add this
            const isEdit = !!state.editingId;
            const label  = payload.beneficiary_name || `Plan #${state.editingId ?? 'new'}`;
            logActivity(
                isEdit
                    ? `Updated welfare action plan "${label}"`
                    : `Created new welfare action plan "${label}"`,
                'Welfare'
            );

            _closeModal(ui.formModal);
            state.page = 1;
            loadPlans();
        } else {
            toast(json.message || 'An error occurred.', 'error');
        }
    });

    // Form close
    $('btnCloseFormModal').addEventListener('click', () => _closeModal(ui.formModal));
    $('btnCancelForm').addEventListener('click',      () => _closeModal(ui.formModal));

    // View modal close
    $('btnCloseViewModal').addEventListener('click',       () => _closeModal(ui.viewModal));
    $('btnCloseViewModalBottom').addEventListener('click', () => _closeModal(ui.viewModal));
    ui.btnEditFromView.addEventListener('click', () => {
        const id = +ui.btnEditFromView.dataset.id;
        _closeModal(ui.viewModal);
        setTimeout(() => openEditModal(id), 160);
    });

    // Delete modal
    $('btnCloseDeleteModal').addEventListener('click', () => _closeModal(ui.deleteModal));
    $('btnCancelDelete').addEventListener('click',     () => _closeModal(ui.deleteModal));
    ui.btnConfirmDel.addEventListener('click', async () => {
        if (!state.deletingId) return;

        ui.btnConfirmDel.disabled = true;
        ui.btnConfirmDel.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin"></i> Deleting…';

        const json = await deletePlan(state.deletingId);

        ui.btnConfirmDel.disabled = false;
        ui.btnConfirmDel.innerHTML = '<i class="fa-solid fa-trash"></i> Delete Plan';

        if (json.success) {
            toast(json.message, 'success');
            // ✅ Add this
            logActivity(`Deleted welfare action plan "${state.deletingId}"`, 'Welfare');
            _closeModal(ui.deleteModal);
            state.page = 1;
            loadPlans();
        } else {
            toast(json.message || 'Delete failed.', 'error');
        }
        state.deletingId = null;
    });

    // Close modals on backdrop click
    [ui.formModal, ui.viewModal, ui.deleteModal].forEach(modal => {
        modal.addEventListener('click', e => {
            if (e.target === modal) _closeModal(modal);
        });
    });

    // ESC key
    document.addEventListener('keydown', e => {
        if (e.key === 'Escape') {
            [ui.formModal, ui.viewModal, ui.deleteModal].forEach(m => {
                if (m.classList.contains('open')) _closeModal(m);
            });
        }
    });
}

/* ══════════════════════════════════════════════════════
   RISK AFTER TOGGLE — only enabled when status = Completed
════════════════════════════════════════════════════════ */
function _toggleRiskAfter() {
    const isCompleted = ui.formStatus.value === 'Completed';
    ui.formRiskAfter.disabled = !isCompleted;
    ui.formRiskAfter.style.opacity     = isCompleted ? '1'         : '0.45';
    ui.formRiskAfter.style.cursor      = isCompleted ? 'pointer'   : 'not-allowed';
    ui.formRiskAfter.style.background  = isCompleted ? ''          : 'var(--bg-subtle)';

    const hint = $('riskAfterHint');
    if (hint) {
        hint.textContent = isCompleted ? '' : '— available when Completed';
        hint.style.color = isCompleted ? 'var(--navy)' : 'var(--slate-mid)';
    }
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
    ui.tbody.innerHTML = `<tr><td colspan="9" class="tbl-loading"><i class="fa-solid fa-circle-notch fa-spin"></i>&nbsp; Loading plans…</td></tr>`;
}

function _setTableError(msg) {
    ui.tbody.innerHTML = `<tr><td colspan="9" class="tbl-loading" style="color:#dc2626">${msg}</td></tr>`;
}

/* ══════════════════════════════════════════════════════
   TOAST
════════════════════════════════════════════════════════ */
function toast(message, type = 'info') {
    const icons = { success: 'fa-circle-check', error: 'fa-circle-xmark', info: 'fa-circle-info' };
    const el = document.createElement('div');
    el.className = `wap-toast ${type}`;
    el.innerHTML = `<i class="fa-solid ${icons[type] || icons.info}"></i><span>${esc(message)}</span>`;
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
function esc(str) {
    if (!str) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function _fmtDate(dateStr) {
    if (!dateStr) return '—';
    const d = new Date(dateStr + 'T00:00:00');
    return d.toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' });
}

function _fmtDateTime(dtStr) {
    if (!dtStr) return '—';
    const d = new Date(dtStr);
    return d.toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' })
        + ' ' + d.toLocaleTimeString('en-PH', { hour: '2-digit', minute: '2-digit' });
}

function _populateSelect(selectEl, items, valKey, labelFn) {
    // Save the placeholder text from the first option before clearing
    const placeholderText = selectEl.options[0] ? selectEl.options[0].textContent : '';
    const placeholderVal  = selectEl.options[0] ? selectEl.options[0].value       : '';

    selectEl.innerHTML = '';

    // Re-create placeholder option fresh (avoids detached-node issues)
    const placeholder = document.createElement('option');
    placeholder.value       = placeholderVal;
    placeholder.textContent = placeholderText;
    selectEl.appendChild(placeholder);

    items.forEach(item => {
        const opt = document.createElement('option');
        opt.value       = item[valKey];
        opt.textContent = labelFn(item);
        selectEl.appendChild(opt);
    });
}

function _flashError(el, msg) {
    el.classList.add('error');
    el.focus();
    toast(msg, 'error');
    el.addEventListener('input', () => el.classList.remove('error'), { once: true });
}