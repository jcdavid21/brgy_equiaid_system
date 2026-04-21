'use strict';

/* ══════════════════════════════════════════════════════
   CONFIG
════════════════════════════════════════════════════════ */
const API_URL  = '../backend/admin-user-management.php';
const PER_PAGE = 15;

const CURRENT_ROLE = window.CURRENT_USER_ROLE || 'admin';
const CURRENT_ID   = window.CURRENT_USER_ID   || 0;
const IS_SUPER     = CURRENT_ROLE === 'superadmin';

/* ══════════════════════════════════════════════════════
   STATE
════════════════════════════════════════════════════════ */
const state = {
    users:      [],
    kpi:        {},
    page:       1,
    lastPage:   1,
    total:      0,
    editingId:  null,
    deletingId: null,
    filters: {
        search:    '',
        role:      '',
        is_active: '',
    },
};

/* ══════════════════════════════════════════════════════
   METADATA
════════════════════════════════════════════════════════ */
const ROLE_META = {
    superadmin:   { label: 'Superadmin',   icon: 'fa-star',             cls: 'superadmin', avatarCls: 'superadmin' },
    admin:        { label: 'Admin',         icon: 'fa-user-shield',      cls: 'admin',      avatarCls: 'admin'      },
    staff:        { label: 'Staff',         icon: 'fa-id-badge',         cls: 'staff',      avatarCls: 'staff'      },
    dswd_officer: { label: 'DSWD Officer',  icon: 'fa-hand-holding-heart',cls: 'dswd',      avatarCls: 'dswd'       },
    labeler:      { label: 'Labeler',       icon: 'fa-tags',             cls: 'labeler',    avatarCls: 'labeler'    },
    resident:     { label: 'Resident',      icon: 'fa-house-user',       cls: 'resident',   avatarCls: 'resident'   },
};

/* ══════════════════════════════════════════════════════
   DOM REFERENCES
════════════════════════════════════════════════════════ */
const $id = id => document.getElementById(id);

const ui = {
    tbody:          $id('umTableBody'),
    kpiTotal:       $id('kpiTotal'),
    kpiActive:      $id('kpiActive'),
    kpiInactive:    $id('kpiInactive'),
    kpiAdmins:      $id('kpiAdmins'),
    kpiRecent:      $id('kpiRecent'),
    paginationInfo: $id('umPaginationInfo'),
    pagination:     $id('umPagination'),
    recordCount:    $id('umRecordCount'),
    search:         $id('umSearch'),
    filterRole:     $id('filterRole'),
    filterStatus:   $id('filterStatus'),

    // Form modal
    formModal:      $id('umFormModal'),
    form:           $id('umForm'),
    formUserId:     $id('formUserId'),
    formName:       $id('formName'),
    formEmail:      $id('formEmail'),
    formPhone:      $id('formPhone'),
    formRole:       $id('formRole'),
    formIsActive:   $id('formIsActive'),
    formPassword:   $id('formPassword'),
    modalEyebrow:   $id('umModalEyebrow'),
    modalTitle:     $id('umModalTitle'),
    btnSubmitLabel: $id('btnSubmitLabel'),
    btnSubmit:      $id('btnSubmitForm'),
    btnTogglePwd:   $id('btnTogglePwd'),
    passwordHint:   $id('passwordHint'),

    // View modal
    viewModal:      $id('umViewModal'),
    viewTitle:      $id('umViewTitle'),
    viewBody:       $id('umViewBody'),
    btnEditFromView:$id('btnEditFromView'),

    // Delete modal
    deleteModal:    $id('umDeleteModal'),
    deleteTarget:   $id('deleteTargetLabel'),
    btnConfirmDel:  $id('btnConfirmDelete'),

    toastCont:      $id('umToastContainer'),
};

/* ══════════════════════════════════════════════════════
   INIT
════════════════════════════════════════════════════════ */
document.addEventListener('DOMContentLoaded', () => {
    loadUsers();
    bindEvents();
});

/* ══════════════════════════════════════════════════════
   API CALLS
════════════════════════════════════════════════════════ */
async function loadUsers() {
    _setTableLoading();

    const params = new URLSearchParams({
        page:      state.page,
        role:      state.filters.role,
        is_active: state.filters.is_active,
        search:    state.filters.search,
    });

    try {
        const res  = await fetch(`${API_URL}?${params}`);
        const json = await res.json();
        if (!json.success) { _setTableError(json.message); return; }

        const { users, kpi, total, last_page } = json.data;

        state.users    = users;
        state.kpi      = kpi;
        state.total    = total;
        state.lastPage = last_page;

        _renderKpi(kpi);
        _renderTable(users);
        _renderPagination(total, last_page);
    } catch (err) {
        console.error('loadUsers:', err);
        _setTableError('Failed to load users.');
    }
}

async function saveUser(payload) {
    const isEdit = !!state.editingId;
    const url    = isEdit ? `${API_URL}?id=${state.editingId}` : API_URL;
    const method = isEdit ? 'PUT' : 'POST';

    const res = await fetch(url, {
        method,
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
    });
    return res.json();
}

async function deleteUser(id) {
    const res = await fetch(`${API_URL}?id=${id}`, { method: 'DELETE' });
    return res.json();
}

async function toggleActive(id) {
    const res = await fetch(`${API_URL}?id=${id}&action=toggle`, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({}),
    });
    return res.json();
}

async function fetchSingleUser(id) {
    const res = await fetch(`${API_URL}?id=${id}`);
    return res.json();
}

/* ══════════════════════════════════════════════════════
   RENDER — KPI
════════════════════════════════════════════════════════ */
function _renderKpi(kpi) {
    ui.kpiTotal.textContent    = kpi.total          ?? 0;
    ui.kpiActive.textContent   = kpi.active         ?? 0;
    ui.kpiInactive.textContent = kpi.inactive       ?? 0;
    ui.kpiAdmins.textContent   = kpi.admins         ?? 0;
    ui.kpiRecent.textContent   = kpi.recent_logins  ?? 0;

    [ui.kpiTotal, ui.kpiActive, ui.kpiInactive, ui.kpiAdmins, ui.kpiRecent]
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
function _renderTable(users) {
    if (!users.length) {
        ui.tbody.innerHTML = `
            <tr><td colspan="8">
                <div class="um-empty">
                    <div class="um-empty-icon"><i class="fa-solid fa-users-slash"></i></div>
                    <div class="um-empty-title">No users found</div>
                    <p class="um-empty-desc">No accounts match your current filters. Try adjusting the search or role filter.</p>
                </div>
            </td></tr>`;
        ui.recordCount.textContent = '0 users';
        return;
    }

    const offset = (state.page - 1) * PER_PAGE;

    const rows = users.map((u, idx) => {
        const roleMeta  = ROLE_META[u.role]     ?? ROLE_META['resident'];
        const initials  = _initials(u.name);
        const isMe      = u.id === CURRENT_ID;
        const isActive  = u.is_active == 1;
        const rowNum    = offset + idx + 1;

        // Permissions: admin cannot edit/delete other admins or superadmins
        const isLocked  = !IS_SUPER && (u.role === 'admin' || u.role === 'superadmin') && !isMe;

        const youBadge  = isMe
            ? `<span class="um-you-badge"><i class="fa-solid fa-user"></i> You</span>`
            : '';

        const toggleCls = isActive ? 'active' : 'inactive';
        const toggleIcon = isActive ? 'fa-toggle-on' : 'fa-toggle-off';
        const toggleTitle = isActive ? 'Click to deactivate' : 'Click to activate';
        const toggleLabel = isActive ? 'Active' : 'Inactive';

        const toggleDisabled = isMe ? 'disabled title="Cannot deactivate your own account"' : `onclick="quickToggle(${u.id})"`;

        const deleteBtn = IS_SUPER && !isMe
            ? `<button class="um-icon-btn danger" title="Remove user"
                        onclick="openDeleteModal(${u.id}, '${esc(u.name)}')">
                   <i class="fa-solid fa-user-xmark"></i>
               </button>`
            : '';

        return `
        <tr class="${isLocked ? 'um-row-locked' : ''}">
            <td style="font-size:12px;color:var(--slate-mid);font-weight:600">${rowNum}</td>
            <td>
                <div class="um-user-cell">
                    
                    <div>
                        <div class="um-user-name">${esc(u.name)}${youBadge}</div>
                        <div class="um-user-email">${esc(u.email)}</div>
                    </div>
                </div>
            </td>
            <td>
                <span class="um-role ${roleMeta.cls}">
                    <i class="fa-solid ${roleMeta.icon}"></i>
                    ${roleMeta.label}
                </span>
            </td>
            <td>
                <button class="um-status-toggle ${toggleCls}"
                        ${isMe ? 'disabled title="Cannot deactivate your own account"' : ''}
                        ${!isMe ? `onclick="quickToggle(${u.id})"` : ''}
                        title="${toggleTitle}">
                    <i class="fa-solid ${toggleIcon}"></i>
                    ${toggleLabel}
                </button>
            </td>
            <td>
                <span style="font-size:12.5px;color:var(--text-mid)">${esc(u.phone_number ?? '—')}</span>
            </td>
            <td>
                ${u.last_login_at
                    ? `<div class="um-date-main">${_fmtDate(u.last_login_at)}</div>
                       <div class="um-date-sub">${_timeAgo(u.last_login_at)}</div>`
                    : `<span class="um-never">Never logged in</span>`}
            </td>
            <td>
                <div class="um-date-main">${_fmtDate(u.created_at)}</div>
            </td>
            <td>
                <div class="um-row-actions">
                    <button class="um-icon-btn" title="View details"
                            onclick="openViewModal(${u.id})">
                        <i class="fa-solid fa-eye"></i>
                    </button>
                    ${!isLocked
                        ? `<button class="um-icon-btn" title="Edit user"
                                   onclick="openEditModal(${u.id})">
                               <i class="fa-solid fa-pen"></i>
                           </button>`
                        : ''}
                    ${deleteBtn}
                </div>
            </td>
        </tr>`;
    }).join('');

    ui.tbody.innerHTML = rows;

    const showing = Math.min(state.total, offset + users.length);
    ui.recordCount.textContent = `Showing ${offset + 1}–${showing} of ${state.total} users`;
}

/* ══════════════════════════════════════════════════════
   RENDER — PAGINATION
════════════════════════════════════════════════════════ */
function _renderPagination(total, lastPage) {
    const p = state.page;
    ui.paginationInfo.textContent = `Page ${p} of ${lastPage}`;

    if (lastPage <= 1) { ui.pagination.innerHTML = ''; return; }

    let html = '';
    html += `<button class="page-btn" ${p === 1 ? 'disabled' : ''} onclick="goPage(${p - 1})">
                <i class="fa-solid fa-chevron-left"></i>
             </button>`;

    _pageRange(p, lastPage).forEach(pg => {
        if (pg === '…') {
            html += `<span class="page-ellipsis">…</span>`;
        } else {
            html += `<button class="page-btn ${pg === p ? 'active' : ''}" onclick="goPage(${pg})">${pg}</button>`;
        }
    });

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
    loadUsers();
    window.scrollTo({ top: 0, behavior: 'smooth' });
};

/* ══════════════════════════════════════════════════════
   QUICK STATUS TOGGLE
════════════════════════════════════════════════════════ */
window.quickToggle = async function(id) {
    try {
        const json = await toggleActive(id);
        if (json.success) {
            toast(json.message, 'success');
            // Update local state without full reload
            const user = state.users.find(u => u.id === id);
            if (user) {
                user.is_active = json.data.is_active ? 1 : 0;
                _renderTable(state.users);
            } else {
                loadUsers();
            }
        } else {
            toast(json.message || 'Toggle failed.', 'error');
        }
    } catch (err) {
        console.error('quickToggle:', err);
        toast('An error occurred.', 'error');
    }
};

/* ══════════════════════════════════════════════════════
   MODAL — CREATE USER
════════════════════════════════════════════════════════ */
function openCreateModal() {
    state.editingId = null;
    ui.formUserId.value    = '';
    ui.formName.value      = '';
    ui.formEmail.value     = '';
    ui.formPhone.value     = '';
    ui.formRole.value      = 'resident';
    ui.formIsActive.value  = '1';
    ui.formPassword.value  = '';
    ui.formPassword.type   = 'password';
    ui.btnTogglePwd.querySelector('i').className = 'fa-solid fa-eye';

    // Password required hint for new user
    ui.passwordHint.textContent = '— required for new users';

    ui.modalEyebrow.textContent  = 'New Account';
    ui.modalTitle.textContent    = 'Create User';
    ui.btnSubmitLabel.textContent = 'Create User';

    // Clear any validation errors
    document.querySelectorAll('.um-form-control.error').forEach(el => el.classList.remove('error'));

    _openModal(ui.formModal);
    setTimeout(() => ui.formName.focus(), 180);
}

/* ══════════════════════════════════════════════════════
   MODAL — EDIT USER
════════════════════════════════════════════════════════ */
window.openEditModal = async function(id) {
    state.editingId = id;

    // Try local cache first
    let user = state.users.find(u => u.id === id);
    if (!user) {
        const json = await fetchSingleUser(id);
        if (!json.success) { toast(json.message, 'error'); return; }
        user = json.data;
    }

    ui.formUserId.value    = user.id;
    ui.formName.value      = user.name ?? '';
    ui.formEmail.value     = user.email ?? '';
    ui.formPhone.value     = user.phone_number ?? '';
    ui.formRole.value      = user.role ?? 'resident';
    ui.formIsActive.value  = user.is_active ? '1' : '0';
    ui.formPassword.value  = '';
    ui.formPassword.type   = 'password';
    ui.btnTogglePwd.querySelector('i').className = 'fa-solid fa-eye';

    ui.passwordHint.textContent = '— leave blank to keep current password';

    ui.modalEyebrow.textContent  = `Editing #${user.id}`;
    ui.modalTitle.textContent    = 'Edit User';
    ui.btnSubmitLabel.textContent = 'Save Changes';

    document.querySelectorAll('.um-form-control.error').forEach(el => el.classList.remove('error'));

    _openModal(ui.formModal);
    setTimeout(() => ui.formName.focus(), 180);
};

/* ══════════════════════════════════════════════════════
   MODAL — VIEW USER DETAILS
════════════════════════════════════════════════════════ */
window.openViewModal = async function(id) {
    _openModal(ui.viewModal);
    ui.viewBody.innerHTML = `<div style="text-align:center;padding:32px 0;color:var(--slate-mid)">
        <i class="fa-solid fa-circle-notch fa-spin fa-lg"></i>
    </div>`;

    try {
        const json = await fetchSingleUser(id);
        if (!json.success) { toast(json.message, 'error'); _closeModal(ui.viewModal); return; }

        const u        = json.data;
        const roleMeta = ROLE_META[u.role] ?? ROLE_META['resident'];
        const initials = _initials(u.name);
        const isMe     = u.id === CURRENT_ID;

        ui.viewTitle.textContent = u.name ?? 'User Profile';

        ui.viewBody.innerHTML = `
            <div class="um-view-profile">
                <div class="um-avatar um-view-avatar ${roleMeta.avatarCls}">${initials}</div>
                <div class="um-view-profile-info">
                    <strong>${esc(u.name)}${isMe ? ' <span class="um-you-badge"><i class="fa-solid fa-user"></i> You</span>' : ''}</strong>
                    <span>${esc(u.email)}</span>
                </div>
                <span class="um-role ${roleMeta.cls}" style="margin-left:auto">
                    <i class="fa-solid ${roleMeta.icon}"></i>${roleMeta.label}
                </span>
            </div>
            <div class="um-view-grid">
                <div class="um-view-field">
                    <div class="um-view-label">User ID</div>
                    <div class="um-view-value">#${u.id}</div>
                </div>
                <div class="um-view-field">
                    <div class="um-view-label">Account Status</div>
                    <div class="um-view-value">
                        ${u.is_active
                            ? '<span style="color:#15803d;font-weight:600"><i class="fa-solid fa-circle-check" style="margin-right:5px"></i>Active</span>'
                            : '<span style="color:#b91c1c;font-weight:600"><i class="fa-solid fa-circle-xmark" style="margin-right:5px"></i>Inactive</span>'}
                    </div>
                </div>
                <div class="um-view-field">
                    <div class="um-view-label">Phone Number</div>
                    <div class="um-view-value">${esc(u.phone_number ?? '—')}</div>
                </div>
                <div class="um-view-field">
                    <div class="um-view-label">Last Login</div>
                    <div class="um-view-value">
                        ${u.last_login_at
                            ? `${_fmtDateTime(u.last_login_at)}<br><span style="font-size:11.5px;color:var(--slate-mid)">${_timeAgo(u.last_login_at)}</span>`
                            : '<span style="color:var(--slate-mid);font-style:italic">Never logged in</span>'}
                    </div>
                </div>
                <div class="um-view-field">
                    <div class="um-view-label">Member Since</div>
                    <div class="um-view-value">${_fmtDate(u.created_at)}</div>
                </div>
                <div class="um-view-field">
                    <div class="um-view-label">Last Updated</div>
                    <div class="um-view-value">${_fmtDate(u.updated_at)}</div>
                </div>
            </div>`;

        ui.btnEditFromView.dataset.id = id;

        // Hide edit button if locked
        const isLocked = !IS_SUPER && (u.role === 'admin' || u.role === 'superadmin') && !isMe;
        ui.btnEditFromView.style.display = isLocked ? 'none' : '';

    } catch (err) {
        console.error('openViewModal:', err);
        toast('Failed to load user details.', 'error');
        _closeModal(ui.viewModal);
    }
};

/* ══════════════════════════════════════════════════════
   MODAL — DELETE
════════════════════════════════════════════════════════ */
window.openDeleteModal = function(id, name) {
    state.deletingId = id;
    ui.deleteTarget.textContent = name || 'This user';
    _openModal(ui.deleteModal);
};

/* ══════════════════════════════════════════════════════
   BIND EVENTS
════════════════════════════════════════════════════════ */
function bindEvents() {

    // ── Create button ────────────────────────────────────
    $id('btnCreateUser').addEventListener('click', openCreateModal);

    // ── Toolbar filters ──────────────────────────────────
    let searchTimer;
    ui.search.addEventListener('input', () => {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => {
            state.filters.search = ui.search.value.trim();
            state.page = 1;
            loadUsers();
        }, 350);
    });

    ui.filterRole.addEventListener('change', () => {
        state.filters.role = ui.filterRole.value;
        state.page = 1; loadUsers();
    });
    ui.filterStatus.addEventListener('change', () => {
        state.filters.is_active = ui.filterStatus.value;
        state.page = 1; loadUsers();
    });

    // ── Password toggle ──────────────────────────────────
    ui.btnTogglePwd.addEventListener('click', () => {
        const isText = ui.formPassword.type === 'text';
        ui.formPassword.type = isText ? 'password' : 'text';
        ui.btnTogglePwd.querySelector('i').className = isText
            ? 'fa-solid fa-eye'
            : 'fa-solid fa-eye-slash';
    });

    // ── Form submit ──────────────────────────────────────
    ui.btnSubmit.addEventListener('click', async () => {
        const payload = {
            name:         ui.formName.value.trim(),
            email:        ui.formEmail.value.trim(),
            phone_number: ui.formPhone.value.trim() || null,
            role:         ui.formRole.value,
            is_active:    ui.formIsActive.value === '1' ? 1 : 0,
        };

        // Include password only if provided
        const pwd = ui.formPassword.value;
        if (pwd) payload.password = pwd;

        // Client-side validation
        if (!payload.name) {
            _flashError(ui.formName, 'Full name is required.');
            return;
        }
        if (!payload.email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(payload.email)) {
            _flashError(ui.formEmail, 'A valid email address is required.');
            return;
        }
        if (!state.editingId && !pwd) {
            _flashError(ui.formPassword, 'Password is required for new users.');
            return;
        }
        if (pwd && pwd.length < 8) {
            _flashError(ui.formPassword, 'Password must be at least 8 characters.');
            return;
        }

        ui.btnSubmit.disabled  = true;
        ui.btnSubmit.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin"></i> Saving…';

        const json = await saveUser(payload);

        ui.btnSubmit.disabled  = false;
        ui.btnSubmit.innerHTML = `<i class="fa-solid fa-floppy-disk"></i> <span>${state.editingId ? 'Save Changes' : 'Create User'}</span>`;

        if (json.success) {
            toast(json.message, 'success');
            _closeModal(ui.formModal);
            state.page = 1;
            loadUsers();
        } else {
            toast(json.message || 'An error occurred.', 'error');
        }
    });

    // ── Form modal close ─────────────────────────────────
    $id('btnCloseFormModal').addEventListener('click', () => _closeModal(ui.formModal));
    $id('btnCancelForm').addEventListener('click',     () => _closeModal(ui.formModal));

    // ── View modal ───────────────────────────────────────
    $id('btnCloseViewModal').addEventListener('click',       () => _closeModal(ui.viewModal));
    $id('btnCloseViewModalBottom').addEventListener('click', () => _closeModal(ui.viewModal));
    ui.btnEditFromView.addEventListener('click', () => {
        const id = +ui.btnEditFromView.dataset.id;
        _closeModal(ui.viewModal);
        setTimeout(() => openEditModal(id), 160);
    });

    // ── Delete modal ─────────────────────────────────────
    $id('btnCloseDeleteModal').addEventListener('click', () => _closeModal(ui.deleteModal));
    $id('btnCancelDelete').addEventListener('click',     () => _closeModal(ui.deleteModal));

    ui.btnConfirmDel.addEventListener('click', async () => {
        if (!state.deletingId) return;

        ui.btnConfirmDel.disabled  = true;
        ui.btnConfirmDel.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin"></i> Removing…';

        const json = await deleteUser(state.deletingId);

        ui.btnConfirmDel.disabled  = false;
        ui.btnConfirmDel.innerHTML = '<i class="fa-solid fa-user-xmark"></i> Remove User';

        if (json.success) {
            toast(json.message, 'success');
            _closeModal(ui.deleteModal);
            state.page = 1;
            loadUsers();
        } else {
            toast(json.message || 'Removal failed.', 'error');
        }
        state.deletingId = null;
    });

    // ── Backdrop click to close ──────────────────────────
    [ui.formModal, ui.viewModal, ui.deleteModal].forEach(modal => {
        modal.addEventListener('click', e => {
            if (e.target === modal) _closeModal(modal);
        });
    });

    // ── ESC key ──────────────────────────────────────────
    document.addEventListener('keydown', e => {
        if (e.key === 'Escape') {
            [ui.formModal, ui.viewModal, ui.deleteModal].forEach(m => {
                if (m.classList.contains('open')) _closeModal(m);
            });
        }
    });

    // ── Enter in search ──────────────────────────────────
    ui.search.addEventListener('keydown', e => {
        if (e.key === 'Enter') {
            clearTimeout(undefined);
            state.filters.search = ui.search.value.trim();
            state.page = 1;
            loadUsers();
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
        <i class="fa-solid fa-circle-notch fa-spin"></i>&nbsp; Loading users…
    </td></tr>`;
}

function _setTableError(msg) {
    ui.tbody.innerHTML = `<tr><td colspan="8" class="tbl-loading" style="color:#dc2626">${msg}</td></tr>`;
}

/* ══════════════════════════════════════════════════════
   TOAST
════════════════════════════════════════════════════════ */
function toast(message, type = 'info') {
    const icons = { success: 'fa-circle-check', error: 'fa-circle-xmark', info: 'fa-circle-info', warning: 'fa-triangle-exclamation' };
    const el    = document.createElement('div');
    el.className = `um-toast ${type}`;
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
    const d = new Date(dateStr);
    return d.toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' });
}

function _fmtDateTime(dtStr) {
    if (!dtStr) return '—';
    const d = new Date(dtStr);
    return d.toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' })
         + ' '
         + d.toLocaleTimeString('en-PH', { hour: '2-digit', minute: '2-digit' });
}

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
    if (d < 30)  return `${Math.floor(d / 7)}w ago`;
    return _fmtDate(dateStr);
}

function _initials(name) {
    if (!name) return '?';
    const parts = name.trim().split(/\s+/);
    if (parts.length === 1) return parts[0][0].toUpperCase();
    return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
}

function _flashError(el, msg) {
    el.classList.add('error');
    el.focus();
    toast(msg, 'error');
    el.addEventListener('input', () => el.classList.remove('error'), { once: true });
}