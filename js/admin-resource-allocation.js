/**
 * resource_allocation.js — Barangay EQUIAID Resource Allocation Page
 * Handles: KPI loading, inventory CRUD, dispatch form, distribution log.
 */

'use strict';

/* ═══ CONFIG ══════════════════════════════════════════════════════════ */
const RA_API = '../backend/admin-resource-allocation.php';

/* ═══ STATE ═══════════════════════════════════════════════════════════ */
const State = {
  /* inventory */
  invPage: 1, invPerPage: 15, invTotal: 0, invSearch: '', invCategory: '',
  /* distribution log */
  logPage: 1, logPerPage: 15, logTotal: 0, logResourceId: 0, logEventId: 0,
  /* lookup caches */
  streets: [], events: [], resources: [],
  /* edit context */
  editId: null,
};

/* ═══ DOM REFS ════════════════════════════════════════════════════════ */
const $ = (sel, ctx = document) => ctx.querySelector(sel);
const $$ = (sel, ctx = document) => [...ctx.querySelectorAll(sel)];

/* ═══ INIT ════════════════════════════════════════════════════════════ */
document.addEventListener('DOMContentLoaded', () => {
  loadOverview();
  loadInventory();
  loadDistributionLog();
  loadStreets();
  loadEvents();
  bindToolbar();
  bindDispatchForm();
  bindModals();
});

/* ═══ TOAST ═══════════════════════════════════════════════════════════ */
function showToast(msg, type = 'success') {
  let t = $('#raToast');
  if (!t) {
    t = document.createElement('div');
    t.id = 'raToast';
    t.className = 'ra-toast';
    t.innerHTML = '<i></i><span></span>';
    document.body.appendChild(t);
  }
  t.className = `ra-toast ${type}`;
  const icons = { success: 'fa-solid fa-circle-check', error: 'fa-solid fa-circle-xmark', info: 'fa-solid fa-circle-info' };
  t.querySelector('i').className = icons[type] || icons.info;
  t.querySelector('span').textContent = msg;
  t.classList.add('show');
  clearTimeout(t._timer);
  t._timer = setTimeout(() => t.classList.remove('show'), 3500);
}

/* ═══ API HELPERS ════════════════════════════════════════════════════ */
async function apiGet(action, params = {}) {
  const url = new URL(RA_API, location.href);
  url.searchParams.set('action', action);
  for (const [k, v] of Object.entries(params)) url.searchParams.set(k, v);
  const res = await fetch(url.toString());
  if (!res.ok) throw new Error(`HTTP ${res.status}`);
  return res.json();
}

async function apiPost(action, body = {}) {
  const url = new URL(RA_API, location.href);
  url.searchParams.set('action', action);
  const res = await fetch(url.toString(), {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(body),
  });
  return res.json();
}

/* ═══ FORMATTERS ═════════════════════════════════════════════════════ */
function fmtNum(n) {
  return Number(n).toLocaleString('en-PH');
}

function fmtPeso(n) {
  if (n == null) return '—';
  return '₱' + Number(n).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function fmtDate(d) {
  if (!d) return '—';
  return new Date(d).toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' });
}

function fmtDateTime(d) {
  if (!d) return '—';
  return new Date(d).toLocaleString('en-PH', { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
}

function catBadge(cat) {
  const key = (cat || 'other').toLowerCase();
  return `<span class="ra-cat-badge ${key}">${cat || 'Other'}</span>`;
}

function stockBadge(avail, thresh) {
  if (avail === 0)        return `<span class="ra-stock-badge ra-stock-critical"><i class="fa-solid fa-circle-xmark"></i> Out of Stock</span>`;
  if (avail < thresh)     return `<span class="ra-stock-badge ra-stock-low"><i class="fa-solid fa-triangle-exclamation"></i> Low Stock</span>`;
  return `<span class="ra-stock-badge ra-stock-ok"><i class="fa-solid fa-circle-check"></i> OK</span>`;
}

function qtyBar(avail, dist, thresh) {
  const total = avail + dist;
  const pct   = total > 0 ? Math.round((avail / total) * 100) : 0;
  const cls   = avail === 0 ? 'critical' : (avail < thresh ? 'low' : '');
  return `
    <div class="ra-qty-bar-wrap">
      <div class="ra-qty-bar-track">
        <div class="ra-qty-bar-fill ${cls}" style="width:${pct}%"></div>
      </div>
      <span class="ra-qty-num">${fmtNum(avail)}</span>
    </div>`;
}

/* ═══ OVERVIEW KPIs ══════════════════════════════════════════════════ */
async function loadOverview() {
  try {
    const d = await apiGet('overview');
    if (!d.ok) return;

    setKpi('raKpiTypes',      d.total_types);
    setKpi('raKpiAvailable',  fmtNum(d.total_available));
    setKpi('raKpiDistributed',fmtNum(d.total_distributed));
    setKpi('raKpiValue',      fmtPeso(d.total_stock_value));
    setKpi('raKpiDistCost',   fmtPeso(d.total_dist_cost));
    setKpi('raKpiDistMonth',  d.dist_this_month);

    // Low stock alert banner
    const banner = $('#raAlertBanner');
    if (banner) {
      const issues = d.low_stock + d.zero_stock;
      if (issues > 0) {
        banner.classList.remove('hidden');
        $('#raAlertBannerText').textContent =
          `${issues} resource type${issues > 1 ? 's' : ''} ${issues > 1 ? 'are' : 'is'} running low or out of stock. `;
      } else {
        banner.classList.add('hidden');
      }
    }
  } catch (e) {
    console.error('Overview error:', e);
  }
}

function setKpi(id, val) {
  const el = document.getElementById(id);
  if (el) { el.textContent = val; el.classList.remove('sk-inline'); }
}

/* ═══ INVENTORY TABLE ════════════════════════════════════════════════ */
async function loadInventory() {
  const tbody = $('#raInvTbody');
  if (!tbody) return;
  tbody.innerHTML = `<tr><td colspan="8" class="ra-tbl-empty sk-loading" style="height:120px;"></td></tr>`;

  try {
    const d = await apiGet('list_resources', {
      page:     State.invPage,
      per_page: State.invPerPage,
      search:   State.invSearch,
      category: State.invCategory,
    });

    if (!d.ok) throw new Error(d.error || 'Error');

    State.invTotal = d.total;
    State.resources = d.data || [];

    if (!d.data || d.data.length === 0) {
      tbody.innerHTML = `<tr><td colspan="8" class="ra-tbl-empty"><i class="fa-solid fa-box-open" style="font-size:22px;display:block;margin-bottom:8px;opacity:.4;"></i>No resources found.</td></tr>`;
    } else {
      tbody.innerHTML = d.data.map(r => `
        <tr data-id="${r.resource_id}">
          <td><strong style="color:var(--text-dark);font-size:13px;">${escHtml(r.resource_name)}</strong>
              ${r.supplier ? `<div style="font-size:11px;color:var(--slate-light);margin-top:2px;">${escHtml(r.supplier)}</div>` : ''}
          </td>
          <td>${catBadge(r.category)}</td>
          <td style="font-size:12.5px;color:var(--text-mid);">${escHtml(r.unit)}</td>
          <td>${qtyBar(+r.qty_available, +r.qty_distributed, +r.restock_threshold)}</td>
          <td style="font-size:12.5px;color:var(--slate-mid);">${fmtNum(r.qty_distributed)}</td>
          <td style="font-size:12.5px;">${r.unit_cost != null ? fmtPeso(r.unit_cost) : '—'}</td>
          <td>${stockBadge(+r.qty_available, +r.restock_threshold)}</td>
          <td>
            <div style="display:flex;gap:5px;justify-content:flex-end;">
              <button class="tbl-action-btn ra-btn-dispatch" data-id="${r.resource_id}" title="Dispatch">
                <i class="fa-solid fa-truck-fast"></i>
              </button>
              <button class="tbl-action-btn ra-btn-edit" data-id="${r.resource_id}" title="Edit">
                <i class="fa-solid fa-pen-to-square"></i>
              </button>
              <button class="tbl-action-btn ra-btn-delete" data-id="${r.resource_id}" title="Delete" style="color:#dc2626;border-color:#fca5a5;">
                <i class="fa-solid fa-trash"></i>
              </button>
            </div>
          </td>
        </tr>
      `).join('');
    }

    renderInvPagination(d.total_pages, d.page);
    $('#raInvCount').textContent = `${fmtNum(d.total)} resource${d.total !== 1 ? 's' : ''}`;
    bindInventoryRowActions();
  } catch (e) {
    tbody.innerHTML = `<tr><td colspan="8" class="ra-tbl-empty" style="color:#dc2626;">${e.message}</td></tr>`;
  }
}

function renderInvPagination(totalPages, currentPage) {
  const wrap = $('#raInvPagination');
  if (!wrap) return;
  if (totalPages <= 1) { wrap.innerHTML = ''; return; }
  wrap.innerHTML = paginationHTML(totalPages, currentPage, 'invPage', 'loadInventory');
}

function paginationHTML(totalPages, currentPage, stateKey, loadFn) {
  let html = `<button class="ra-page-btn" onclick="changePage('${stateKey}','${loadFn}',-1)" ${currentPage <= 1 ? 'disabled' : ''}><i class="fa-solid fa-chevron-left" style="font-size:10px;"></i></button>`;
  const start = Math.max(1, currentPage - 2);
  const end   = Math.min(totalPages, currentPage + 2);
  if (start > 1) html += `<button class="ra-page-btn" onclick="jumpPage('${stateKey}','${loadFn}',1)">1</button>${start > 2 ? '<span style="color:var(--slate-light);font-size:12px;">…</span>' : ''}`;
  for (let i = start; i <= end; i++) {
    html += `<button class="ra-page-btn ${i === currentPage ? 'active' : ''}" onclick="jumpPage('${stateKey}','${loadFn}',${i})">${i}</button>`;
  }
  if (end < totalPages) html += `${end < totalPages - 1 ? '<span style="color:var(--slate-light);font-size:12px;">…</span>' : ''}<button class="ra-page-btn" onclick="jumpPage('${stateKey}','${loadFn}',${totalPages})">${totalPages}</button>`;
  html += `<button class="ra-page-btn" onclick="changePage('${stateKey}','${loadFn}',1)" ${currentPage >= totalPages ? 'disabled' : ''}><i class="fa-solid fa-chevron-right" style="font-size:10px;"></i></button>`;
  return html;
}

window.changePage = function(stateKey, loadFn, delta) {
  State[stateKey] = Math.max(1, State[stateKey] + delta);
  window[loadFn]();
};

window.jumpPage = function(stateKey, loadFn, page) {
  State[stateKey] = page;
  window[loadFn]();
};

/* ═══ INVENTORY ROW ACTIONS ══════════════════════════════════════════ */
function bindInventoryRowActions() {
  $$('.ra-btn-edit').forEach(btn => {
    btn.addEventListener('click', e => {
      e.stopPropagation();
      openEditModal(+btn.dataset.id);
    });
  });

  $$('.ra-btn-delete').forEach(btn => {
    btn.addEventListener('click', e => {
      e.stopPropagation();
      confirmDeleteResource(+btn.dataset.id);
    });
  });

  $$('.ra-btn-dispatch').forEach(btn => {
    btn.addEventListener('click', e => {
      e.stopPropagation();
      const id = +btn.dataset.id;
      const res = State.resources.find(r => +r.resource_id === id);
      if (res) prefillDispatch(res);
      scrollToDispatch();
    });
  });
}

/* ═══ TOOLBAR ════════════════════════════════════════════════════════ */
function bindToolbar() {
  const searchEl = $('#raSearchInput');
  if (searchEl) {
    let timer;
    searchEl.addEventListener('input', () => {
      clearTimeout(timer);
      timer = setTimeout(() => {
        State.invSearch = searchEl.value.trim();
        State.invPage = 1;
        loadInventory();
      }, 320);
    });
  }

  const catEl = $('#raCatFilter');
  if (catEl) {
    catEl.addEventListener('change', () => {
      State.invCategory = catEl.value;
      State.invPage = 1;
      loadInventory();
    });
  }

  const addBtn = $('#raAddResourceBtn');
  if (addBtn) addBtn.addEventListener('click', () => openAddModal());

  const logResEl = $('#raLogResFilter');
  if (logResEl) {
    logResEl.addEventListener('change', () => {
      State.logResourceId = +logResEl.value;
      State.logPage = 1;
      loadDistributionLog();
    });
  }

  const logEvEl = $('#raLogEvFilter');
  if (logEvEl) {
    logEvEl.addEventListener('change', () => {
      State.logEventId = +logEvEl.value;
      State.logPage = 1;
      loadDistributionLog();
    });
  }
}

/* ═══ ADD / EDIT MODAL ═══════════════════════════════════════════════ */
function bindModals() {
  const backdrop = $('#raResourceModal');
  if (!backdrop) return;

  $('#raModalClose')?.addEventListener('click', closeResourceModal);
  $('#raModalCancelBtn')?.addEventListener('click', closeResourceModal);
  backdrop.addEventListener('click', e => { if (e.target === backdrop) closeResourceModal(); });
  $('#raResourceForm')?.addEventListener('submit', handleResourceFormSubmit);
}

function openAddModal() {
  State.editId = null;
  resetResourceForm();
  $('#raModalEyebrow').textContent = 'New Resource';
  $('#raModalTitle').textContent   = 'Add Resource';
  $('#raModalSaveBtn').textContent = 'Add Resource';
  showModal('#raResourceModal');
}

async function openEditModal(id) {
  State.editId = id;
  resetResourceForm();
  $('#raModalEyebrow').textContent = 'Edit Resource';
  $('#raModalTitle').textContent   = 'Edit Resource';
  $('#raModalSaveBtn').textContent = 'Save Changes';

  try {
    const d = await apiGet('get_resource', { id });
    if (!d.ok) { showToast(d.error || 'Failed to load resource.', 'error'); return; }
    const r = d.data;
    fillForm({
      raFldName:     r.resource_name,
      raFldCategory: r.category,
      raFldUnit:     r.unit,
      raFldCost:     r.unit_cost ?? '',
      raFldAvailable:r.qty_available,
      raFldReserved: r.qty_reserved,
      raFldDistributed: r.qty_distributed,
      raFldThreshold:r.restock_threshold,
      raFldSupplier: r.supplier ?? '',
      raFldNotes:    r.notes ?? '',
    });
  } catch (e) {
    showToast('Error loading resource.', 'error');
  }
  showModal('#raResourceModal');
}

function resetResourceForm() {
  const form = $('#raResourceForm');
  if (form) form.reset();
  setModalError('');
}

function fillForm(vals) {
  for (const [id, val] of Object.entries(vals)) {
    const el = document.getElementById(id);
    if (el) el.value = val;
  }
}

async function handleResourceFormSubmit(e) {
  e.preventDefault();
  setModalError('');

  const payload = {
    resource_name:     $('#raFldName')?.value.trim(),
    category:          $('#raFldCategory')?.value,
    unit:              $('#raFldUnit')?.value.trim(),
    unit_cost:         $('#raFldCost')?.value !== '' ? $('#raFldCost')?.value : null,
    qty_available:     parseInt($('#raFldAvailable')?.value) || 0,
    qty_reserved:      parseInt($('#raFldReserved')?.value)  || 0,
    qty_distributed:   parseInt($('#raFldDistributed')?.value) || 0,
    restock_threshold: parseInt($('#raFldThreshold')?.value) || 50,
    supplier:          $('#raFldSupplier')?.value.trim() || null,
    notes:             $('#raFldNotes')?.value.trim()    || null,
  };

  if (!payload.resource_name) { setModalError('Resource name is required.'); return; }

  const saveBtn = $('#raModalSaveBtn');
  saveBtn.disabled = true;
  saveBtn.textContent = 'Saving…';

  try {
    const action = State.editId ? 'update_resource' : 'create_resource';
    if (State.editId) payload.resource_id = State.editId;
    const d = await apiPost(action, payload);

    if (!d.ok) { setModalError(d.error || 'Save failed.'); return; }

    showToast(d.message || 'Resource saved.', 'success');
    
    logActivity(
        State.editId
            ? `Updated resource "${payload.resource_name}"`
            : `Created new resource "${payload.resource_name}"`,
        'Resources'
    );
    closeResourceModal();
    loadInventory();
    loadOverview();
    populateLogResourceFilter();
  } catch (err) {
    setModalError('Request failed. Try again.');
  } finally {
    saveBtn.disabled = false;
    saveBtn.textContent = State.editId ? 'Save Changes' : 'Add Resource';
  }
}

function setModalError(msg) {
  const el = $('#raModalError');
  if (!el) return;
  el.textContent = msg;
  el.classList.toggle('visible', msg !== '');
}

function showModal(sel) {
  const el = $(sel);
  if (el) { el.classList.add('open'); document.body.style.overflow = 'hidden'; }
}

function closeResourceModal() {
  const el = $('#raResourceModal');
  if (el) { el.classList.remove('open'); document.body.style.overflow = ''; }
}

/* ═══ DELETE RESOURCE ════════════════════════════════════════════════ */
function confirmDeleteResource(id) {
  const res = State.resources.find(r => +r.resource_id === id);
  const name = res ? res.resource_name : `ID ${id}`;
  if (!confirm(`Delete "${name}"?\n\nThis cannot be undone. Resources with distribution records cannot be deleted.`)) return;
  deleteResource(id);
}

async function deleteResource(id) {
  try {
    const d = await apiPost('delete_resource', { resource_id: id });
    if (!d.ok) { showToast(d.error || 'Delete failed.', 'error'); return; }
    showToast(d.message || 'Resource deleted.', 'success');
    const res = State.resources.find(r => +r.resource_id === id);
    logActivity(`Deleted resource "${res?.resource_name ?? 'ID ' + id}"`, 'Resources');

    loadInventory();
    loadOverview();
  } catch (e) {
    showToast('Delete request failed.', 'error');
  }
}

/* ═══ DISPATCH FORM ══════════════════════════════════════════════════ */
async function loadStreets() {
  try {
    const d = await apiGet('get_streets');
    if (!d.ok) return;
    State.streets = d.data || [];
    const sel = $('#raDispStreet');
    if (!sel) return;
    sel.innerHTML = '<option value="">— Select Street —</option>' +
      State.streets.map(s =>
        `<option value="${s.street_id}">[${s.current_risk_level || '?'}] ${escHtml(s.street_name)}</option>`
      ).join('');
  } catch (e) { /* ignore */ }
}

async function loadEvents() {
  try {
    const d = await apiGet('get_events');
    if (!d.ok) return;
    State.events = d.data || [];

    const sels = ['#raDispEvent', '#raLogEvFilter'];
    sels.forEach(s => {
      const el = $(s);
      if (!el) return;
      const placeholders = {
        '#raDispEvent':    '<option value="">— None —</option>',
        '#raLogEvFilter':  '<option value="">All Events</option>',
      };
      el.innerHTML = (placeholders[s] || '<option value="">All</option>') +
        State.events.map(ev =>
          `<option value="${ev.event_id}">${escHtml(ev.event_name)} (${ev.status})</option>`
        ).join('');
    });
  } catch (e) { /* ignore */ }
}

function bindDispatchForm() {
  const form = $('#raDispatchForm');
  if (!form) return;
  form.addEventListener('submit', handleDispatch);

  // Live total cost preview
  ['#raDispResource', '#raDispQty'].forEach(sel => {
    $(sel)?.addEventListener('change', updateDispatchTotal);
    $(sel)?.addEventListener('input', updateDispatchTotal);
  });

  // Populate resource dropdown
  populateDispatchResourceSelect();
}

async function populateDispatchResourceSelect() {
  try {
    const d = await apiGet('list_resources', { per_page: 100 });
    if (!d.ok) return;
    State.resources = d.data || [];
    const sel = $('#raDispResource');
    if (!sel) return;
    sel.innerHTML = '<option value="">— Select Resource —</option>' +
      State.resources.map(r =>
        `<option value="${r.resource_id}" data-cost="${r.unit_cost ?? ''}" data-avail="${r.qty_available}" data-unit="${escHtml(r.unit)}">${escHtml(r.resource_name)} (${fmtNum(r.qty_available)} ${r.unit} available)</option>`
      ).join('');
  } catch (e) { /* ignore */ }
}

async function populateLogResourceFilter() {
  try {
    const d = await apiGet('list_resources', { per_page: 100 });
    if (!d.ok) return;
    const sel = $('#raLogResFilter');
    if (!sel) return;
    const cur = sel.value;
    sel.innerHTML = '<option value="">All Resources</option>' +
      (d.data || []).map(r =>
        `<option value="${r.resource_id}" ${+cur === +r.resource_id ? 'selected' : ''}>${escHtml(r.resource_name)}</option>`
      ).join('');
  } catch (e) { /* ignore */ }
}

function prefillDispatch(res) {
  const sel = $('#raDispResource');
  if (sel) sel.value = res.resource_id;
  updateDispatchTotal();
}

function updateDispatchTotal() {
  const resSel = $('#raDispResource');
  const qtyEl  = $('#raDispQty');
  const totalEl= $('#raDispTotal');
  const hintEl = $('#raDispHint');

  if (!resSel || !qtyEl || !totalEl) return;

  const opt   = resSel.selectedOptions[0];
  const cost  = parseFloat(opt?.dataset.cost) || 0;
  const avail = parseInt(opt?.dataset.avail)  || 0;
  const unit  = opt?.dataset.unit || 'units';
  const qty   = parseInt(qtyEl.value)         || 0;

  if (hintEl) hintEl.textContent = `Max available: ${fmtNum(avail)} ${unit}`;
  totalEl.textContent = cost > 0 ? fmtPeso(cost * qty) : '—';
}

async function handleDispatch(e) {
  e.preventDefault();

  const res_id    = parseInt($('#raDispResource')?.value) || 0;
  const street_id = parseInt($('#raDispStreet')?.value)   || 0;
  const event_id  = parseInt($('#raDispEvent')?.value)    || null;
  const qty       = parseInt($('#raDispQty')?.value)      || 0;
  const recips    = parseInt($('#raDispRecipients')?.value) || 0;
  const notes     = $('#raDispNotes')?.value.trim() || null;
  const dist_at   = $('#raDispDate')?.value
    ? new Date($('#raDispDate').value).toISOString().replace('T', ' ').slice(0, 19)
    : null;

  if (!res_id)    { showToast('Select a resource.', 'error'); return; }
  if (!street_id) { showToast('Select a destination street.', 'error'); return; }
  if (qty <= 0)   { showToast('Quantity must be > 0.', 'error'); return; }

  const btn = $('#raDispatchSubmit');
  btn.disabled = true;
  btn.textContent = 'Dispatching…';

  try {
    const d = await apiPost('dispatch_resource', {
      resource_id: res_id, street_id, event_id, qty_distributed: qty,
      recipient_count: recips, notes, distributed_at: dist_at,
    });

    if (!d.ok) { showToast(d.error || 'Dispatch failed.', 'error'); return; }

    showToast(d.message || 'Dispatched successfully.', 'success');
    
    const resOpt = $('#raDispResource')?.selectedOptions[0];
    const resName = resOpt?.textContent.split('(')[0].trim() || `Resource #${res_id}`;
    const streetOpt = $('#raDispStreet')?.selectedOptions[0];
    const streetName = streetOpt?.textContent.trim() || `Street #${street_id}`;
    logActivity(`Dispatched ${qty} unit(s) of "${resName}" to ${streetName}`, 'Resources');

    $('#raDispatchForm').reset();
    $('#raDispTotal').textContent = '—';
    $('#raDispHint').textContent  = '';

    loadInventory();
    loadOverview();
    loadDistributionLog();
    populateDispatchResourceSelect();
  } catch (err) {
    showToast('Request failed. Try again.', 'error');
  } finally {
    btn.disabled = false;
    btn.textContent = 'Dispatch';
  }
}

function scrollToDispatch() {
  $('#raDispatchSection')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

/* ═══ DISTRIBUTION LOG ═══════════════════════════════════════════════ */
async function loadDistributionLog() {
  const tbody = $('#raLogTbody');
  if (!tbody) return;
  tbody.innerHTML = `<tr><td colspan="8" class="ra-tbl-empty sk-loading" style="height:100px;"></td></tr>`;

  try {
    const params = {
      page:     State.logPage,
      per_page: State.logPerPage,
    };
    if (State.logResourceId > 0) params.resource_id = State.logResourceId;
    if (State.logEventId    > 0) params.event_id    = State.logEventId;

    const d = await apiGet('list_distributions', params);
    if (!d.ok) throw new Error(d.error || 'Error');

    State.logTotal = d.total;

    if (!d.data || d.data.length === 0) {
      tbody.innerHTML = `<tr><td colspan="8" class="ra-tbl-empty"><i class="fa-solid fa-inbox" style="font-size:22px;display:block;margin-bottom:8px;opacity:.4;"></i>No distribution records found.</td></tr>`;
    } else {
      tbody.innerHTML = d.data.map(r => `
        <tr data-dist="${r.dist_id}">
          <td style="font-size:12px;color:var(--slate-mid);">${fmtDateTime(r.distributed_at)}</td>
          <td>
            <strong style="font-size:13px;color:var(--text-dark);">${escHtml(r.resource_name || '—')}</strong>
            <div style="font-size:11px;color:var(--slate-light);">${catBadge(r.category)}</div>
          </td>
          <td style="font-size:12.5px;">${escHtml(r.street_name || '—')}</td>
          <td style="font-size:12.5px;">${r.event_name ? escHtml(r.event_name) : '<span style="color:var(--slate-light);">—</span>'}</td>
          <td style="font-size:13px;font-weight:600;color:var(--navy);">${fmtNum(r.qty_distributed)} <span style="font-size:11px;color:var(--slate-mid);">${escHtml(r.unit || '')}</span></td>
          <td style="font-size:12.5px;">${r.total_cost != null ? fmtPeso(r.total_cost) : '—'}</td>
          <td style="font-size:12px;color:var(--text-mid);">${r.distributed_by_name ? escHtml(r.distributed_by_name) : '—'}${r.recipient_count > 0 ? `<div style="font-size:11px;color:var(--slate-light);">${fmtNum(r.recipient_count)} recipients</div>` : ''}</td>
          <td>
            <button class="tbl-action-btn ra-btn-del-dist" data-id="${r.dist_id}" title="Delete Record" style="color:#dc2626;border-color:#fca5a5;">
              <i class="fa-solid fa-trash"></i>
            </button>
          </td>
        </tr>
      `).join('');
    }

    renderLogPagination(d.total_pages, d.page);
    $('#raLogCount').textContent = `${fmtNum(d.total)} record${d.total !== 1 ? 's' : ''}`;
    bindLogRowActions();
  } catch (e) {
    tbody.innerHTML = `<tr><td colspan="8" class="ra-tbl-empty" style="color:#dc2626;">${e.message}</td></tr>`;
  }
}

function renderLogPagination(totalPages, currentPage) {
  const wrap = $('#raLogPagination');
  if (!wrap) return;
  if (totalPages <= 1) { wrap.innerHTML = ''; return; }
  wrap.innerHTML = paginationHTML(totalPages, currentPage, 'logPage', 'loadDistributionLog');
}

window.loadDistributionLog = loadDistributionLog;

function bindLogRowActions() {
  $$('.ra-btn-del-dist').forEach(btn => {
    btn.addEventListener('click', e => {
      e.stopPropagation();
      confirmDeleteDist(+btn.dataset.id);
    });
  });
}

function confirmDeleteDist(distId) {
  if (!confirm(`Delete this distribution record?\n\nThe dispatched quantity will be restored to available stock.`)) return;
  deleteDistribution(distId);
}

async function deleteDistribution(distId) {
  try {
    const d = await apiPost('delete_distribution', { dist_id: distId });
    if (!d.ok) { showToast(d.error || 'Delete failed.', 'error'); return; }
    showToast(d.message || 'Record deleted, stock restored.', 'success');
    logActivity(`Deleted distribution record #${distId}`, 'Resources');
    loadDistributionLog();
    loadInventory();
    loadOverview();
    populateDispatchResourceSelect();
  } catch (e) {
    showToast('Delete request failed.', 'error');
  }
}

/* ═══ UTILS ══════════════════════════════════════════════════════════ */
function escHtml(str) {
  if (!str) return '';
  return String(str)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');
}

// Set default datetime in dispatch form
(function setDefaultDispatchDate() {
  const el = document.getElementById('raDispDate');
  if (el) {
    const now = new Date();
    now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
    el.value = now.toISOString().slice(0, 16);
  }
})();