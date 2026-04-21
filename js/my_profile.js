/**
 * my_profile.js — Barangay EQUIAID My Profile Page
 * Connects to: backend/my_profile.php
 */
'use strict';

const MP_API = '../backend/my_profile.php';

// ── Fetch helper ──────────────────────────────────────────
async function mpFetch(action, params = '') {
    const url = `${MP_API}?action=${action}${params ? '&' + params : ''}`;
    const res = await fetch(url);
    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    const data = await res.json();
    if (!data.ok) throw new Error(data.error || 'API error');
    return data;
}

async function mpPost(action, formData) {
    formData.append('action', action);
    const res = await fetch(MP_API, { method: 'POST', body: formData });
    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    const data = await res.json();
    if (!data.ok) throw new Error(data.error || 'API error');
    return data;
}

// ── HTML escape ───────────────────────────────────────────
function esc(str) {
    if (str == null) return '';
    return String(str)
        .replace(/&/g, '&amp;').replace(/</g, '&lt;')
        .replace(/>/g, '&gt;').replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

// ── Date formatters ───────────────────────────────────────
function fmtDate(ts) {
    if (!ts) return '—';
    return new Date(ts.replace(' ', 'T')).toLocaleString('en-PH', {
        month: 'short', day: 'numeric', year: 'numeric',
        hour: 'numeric', minute: '2-digit',
    });
}

function fmtDateShort(ts) {
    if (!ts) return '—';
    return new Date(ts.replace(' ', 'T')).toLocaleString('en-PH', {
        month: 'long', day: 'numeric', year: 'numeric',
    });
}

// ── Role display ──────────────────────────────────────────
const ROLE_LABELS = {
    superadmin:   'Super Admin',
    admin:        'Admin',
    staff:        'Staff',
    dswd_officer: 'DSWD Officer',
    labeler:      'Labeler',
    resident:     'Resident',
};

function roleLabel(role) {
    return ROLE_LABELS[role] || role;
}

// ── Initials from name ────────────────────────────────────
function getInitials(name) {
    if (!name) return '?';
    const parts = name.trim().split(/\s+/);
    if (parts.length === 1) return parts[0][0].toUpperCase();
    return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
}

// ═══════════════════════════════════════════════════════════
//  TOAST SYSTEM
// ═══════════════════════════════════════════════════════════
let _toastId = 0;

function showToast(msg, type = 'success', duration = 4000) {
    const wrap = document.getElementById('mpToastWrap');
    if (!wrap) return;

    const ICONS = { success: 'fa-circle-check', error: 'fa-circle-xmark', info: 'fa-circle-info' };
    const id = ++_toastId;

    const toast = document.createElement('div');
    toast.className = `mp-toast mp-toast--${type}`;
    toast.setAttribute('role', 'alert');
    toast.dataset.id = id;
    toast.innerHTML = `
        <i class="fa-solid ${ICONS[type] || ICONS.info} mp-toast-icon"></i>
        <span class="mp-toast-msg">${esc(msg)}</span>
    `;
    wrap.appendChild(toast);

    setTimeout(() => {
        toast.classList.add('is-exiting');
        toast.addEventListener('animationend', () => toast.remove(), { once: true });
    }, duration);
}

// ═══════════════════════════════════════════════════════════
//  LOAD PROFILE
// ═══════════════════════════════════════════════════════════
let _userData = null;

async function loadProfile() {
    try {
        const data = await mpFetch('get_profile');
        _userData = data.user;
        renderProfile(data.user, data.total_reports);
    } catch (err) {
        console.error('[MyProfile]', err.message);
        showToast('Could not load profile. Please refresh.', 'error', 6000);
    }
}

function renderProfile(u, totalReports) {
    // ── Avatar ─────────────────────────────────────────────
    const avatarEl = document.getElementById('mpAvatar');
    if (avatarEl) {
        avatarEl.innerHTML = `<span>${esc(getInitials(u.name))}</span>`;
    }

    // ── Hero ───────────────────────────────────────────────
    const heroName = document.getElementById('mpHeroName');
    if (heroName) heroName.textContent = u.name;

    const roleBadge = document.getElementById('mpRoleBadge');
    if (roleBadge) {
        roleBadge.className = `mp-role-badge mp-role-badge--${esc(u.role)}`;
        roleBadge.innerHTML = `<i class="fa-solid fa-shield-halved"></i> ${esc(roleLabel(u.role))}`;
    }

    const statusBadge = document.getElementById('mpStatusBadge');
    if (statusBadge) {
        const active = u.is_active == 1;
        statusBadge.className = `mp-status-badge mp-status-badge--${active ? 'active' : 'inactive'}`;
        statusBadge.innerHTML = `<span class="mp-status-dot"></span> ${active ? 'Active' : 'Inactive'}`;
    }

    // ── Stats ──────────────────────────────────────────────
    const rEl = document.getElementById('mpStatReports');
    if (rEl) { rEl.textContent = totalReports; rEl.classList.remove('sk-loading'); }

    const sEl = document.getElementById('mpStatSince');
    if (sEl) sEl.textContent = fmtDateShort(u.created_at);

    const llEl = document.getElementById('mpStatLastLogin');
    if (llEl) llEl.textContent = u.last_login_at ? fmtDate(u.last_login_at) : 'Never';

    // ── Form fields ────────────────────────────────────────
    setVal('mpName',  u.name);
    setVal('mpEmail', u.email);
    setVal('mpPhone', u.phone_number || '');

    // ── Right-column detail rows ───────────────────────────
    setText('mpDetailId',        '#' + u.id);
    setHtml('mpDetailRole',      `<span class="mp-role-badge mp-role-badge--${esc(u.role)}">${esc(roleLabel(u.role))}</span>`);
    const activeDetail = u.is_active == 1;
    setHtml('mpDetailStatus',    `<span class="mp-status-badge mp-status-badge--${activeDetail ? 'active' : 'inactive'}"><span class="mp-status-dot"></span> ${activeDetail ? 'Active' : 'Inactive'}</span>`);
    setText('mpDetailEmail',     u.email);
    setText('mpDetailPhone',     u.phone_number || '—');
    setText('mpDetailJoined',    fmtDate(u.created_at));
    setText('mpDetailUpdated',   fmtDate(u.updated_at));
    setText('mpDetailLastLogin', u.last_login_at ? fmtDate(u.last_login_at) : 'Never');
}

function setVal(id, val) {
    const el = document.getElementById(id);
    if (el) el.value = val;
}

function setText(id, val) {
    const el = document.getElementById(id);
    if (el) el.textContent = val;
}

function setHtml(id, html) {
    const el = document.getElementById(id);
    if (el) el.innerHTML = html;
}

// ═══════════════════════════════════════════════════════════
//  EDIT MODE TOGGLE
// ═══════════════════════════════════════════════════════════
let _editing = false;
let _savedName  = '';
let _savedPhone = '';

function enterEditMode() {
    _editing = true;
    _savedName  = document.getElementById('mpName').value;
    _savedPhone = document.getElementById('mpPhone').value;

    document.getElementById('mpName').disabled  = false;
    document.getElementById('mpPhone').disabled = false;
    document.getElementById('mpName').focus();

    document.getElementById('mpFormActions').style.display = 'flex';
    document.getElementById('mpEditBtn').style.display = 'none';
}

function exitEditMode(restore = false) {
    _editing = false;

    document.getElementById('mpName').disabled  = true;
    document.getElementById('mpPhone').disabled = true;

    if (restore) {
        document.getElementById('mpName').value  = _savedName;
        document.getElementById('mpPhone').value = _savedPhone;
    }

    document.getElementById('mpFormActions').style.display = 'none';
    document.getElementById('mpEditBtn').style.display     = '';
    clearFieldError('mpNameErr');
    clearFieldError('mpPhoneErr');
}

document.getElementById('mpEditBtn')?.addEventListener('click', enterEditMode);
document.getElementById('mpCancelBtn')?.addEventListener('click', () => exitEditMode(true));

// ═══════════════════════════════════════════════════════════
//  PROFILE FORM SUBMIT
// ═══════════════════════════════════════════════════════════
document.getElementById('mpProfileForm')?.addEventListener('submit', async function (e) {
    e.preventDefault();

    const name  = document.getElementById('mpName').value.trim();
    const phone = document.getElementById('mpPhone').value.trim();

    // Client-side validation
    let valid = true;
    clearFieldError('mpNameErr');
    clearFieldError('mpPhoneErr');

    if (!name) {
        showFieldError('mpNameErr', 'Full name is required.');
        valid = false;
    } else if (name.length > 120) {
        showFieldError('mpNameErr', 'Name must be 120 characters or fewer.');
        valid = false;
    }

    if (phone && !/^(\+639|09)\d{9}$/.test(phone)) {
        showFieldError('mpPhoneErr', 'Must be a valid PH number (09xxxxxxxxx or +639xxxxxxxxx).');
        valid = false;
    }

    if (!valid) return;

    // Submit
    const saveBtn = document.getElementById('mpSaveBtn');
    setLoading(saveBtn, true, 'Saving…');

    try {
        const fd = new FormData();
        fd.append('name',  name);
        fd.append('phone', phone);

        const data = await mpPost('update_profile', fd);
        showToast(data.message || 'Profile updated.', 'success');

        // Refresh local state
        if (_userData) {
            _userData.name         = name;
            _userData.phone_number = phone || null;
        }

        // Update avatar initials in real time
        const avatarEl = document.getElementById('mpAvatar');
        if (avatarEl) avatarEl.innerHTML = `<span>${esc(getInitials(name))}</span>`;

        const heroName = document.getElementById('mpHeroName');
        if (heroName) heroName.textContent = name;

        setText('mpDetailPhone', phone || '—');

        exitEditMode(false);

    } catch (err) {
        showToast(err.message, 'error');
    } finally {
        setLoading(saveBtn, false, '<i class="fa-solid fa-floppy-disk"></i> Save Changes');
    }
});

// ═══════════════════════════════════════════════════════════
//  PASSWORD FORM
// ═══════════════════════════════════════════════════════════

// Toggle collapse
const mpPwWrap      = document.getElementById('mpPwFormWrap');
const mpPwChevron   = document.getElementById('mpPwChevron');
let _pwOpen = false;

document.getElementById('mpPwToggleBtn')?.addEventListener('click', () => {
    _pwOpen = !_pwOpen;
    mpPwWrap.classList.toggle('is-open', _pwOpen);
    mpPwChevron.className = _pwOpen
        ? 'fa-solid fa-chevron-up'
        : 'fa-solid fa-chevron-down';
});

document.getElementById('mpPwCancelBtn')?.addEventListener('click', () => {
    _pwOpen = false;
    mpPwWrap.classList.remove('is-open');
    mpPwChevron.className = 'fa-solid fa-chevron-down';
    document.getElementById('mpPwForm').reset();
    ['mpCurrentPwErr','mpNewPwErr','mpConfirmPwErr'].forEach(clearFieldError);
    resetPwStrength();
});

// Password strength meter
document.getElementById('mpNewPw')?.addEventListener('input', function () {
    const pw = this.value;
    const barsWrap = document.getElementById('mpPwStrengthBars');
    const label    = document.getElementById('mpPwStrengthLabel');

    if (!pw) { resetPwStrength(); return; }

    barsWrap.hidden = false;

    const score = calcPwStrength(pw);
    const bars  = [
        document.getElementById('mpPwBar1'),
        document.getElementById('mpPwBar2'),
        document.getElementById('mpPwBar3'),
        document.getElementById('mpPwBar4'),
    ];

    const LEVELS = ['', 'weak', 'fair', 'good', 'strong'];
    const LABELS = ['', 'Weak', 'Fair', 'Good', 'Strong'];

    bars.forEach((b, i) => {
        b.className = 'mp-pw-bar' + (i < score ? ` is-filled-${LEVELS[score]}` : '');
    });

    label.className = `mp-pw-strength-label ${LEVELS[score]}`;
    label.textContent = pw ? LABELS[score] : '';
});

function calcPwStrength(pw) {
    let score = 0;
    if (pw.length >= 8)  score++;
    if (pw.length >= 12) score++;
    if (/[A-Z]/.test(pw) && /[a-z]/.test(pw)) score++;
    if (/\d/.test(pw) && /[^a-zA-Z0-9]/.test(pw)) score++;
    return Math.max(1, Math.min(score, 4));
}

function resetPwStrength() {
    const barsWrap = document.getElementById('mpPwStrengthBars');
    if (barsWrap) barsWrap.hidden = true;
    const label = document.getElementById('mpPwStrengthLabel');
    if (label) { label.className = 'mp-pw-strength-label'; label.textContent = ''; }
    ['mpPwBar1','mpPwBar2','mpPwBar3','mpPwBar4'].forEach(id => {
        const b = document.getElementById(id);
        if (b) b.className = 'mp-pw-bar';
    });
}

// Password form submit
document.getElementById('mpPwForm')?.addEventListener('submit', async function (e) {
    e.preventDefault();

    const currentPw = document.getElementById('mpCurrentPw').value;
    const newPw     = document.getElementById('mpNewPw').value;
    const confirmPw = document.getElementById('mpConfirmPw').value;

    ['mpCurrentPwErr','mpNewPwErr','mpConfirmPwErr'].forEach(clearFieldError);

    let valid = true;

    if (!currentPw) {
        showFieldError('mpCurrentPwErr', 'Please enter your current password.'); valid = false;
    }
    if (!newPw || newPw.length < 8) {
        showFieldError('mpNewPwErr', 'New password must be at least 8 characters.'); valid = false;
    }
    if (newPw && newPw !== confirmPw) {
        showFieldError('mpConfirmPwErr', 'Passwords do not match.'); valid = false;
    }
    if (newPw && newPw === currentPw) {
        showFieldError('mpNewPwErr', 'New password must differ from the current one.'); valid = false;
    }

    if (!valid) return;

    const saveBtn = document.getElementById('mpPwSaveBtn');
    setLoading(saveBtn, true, 'Updating…', true);

    try {
        const fd = new FormData();
        fd.append('current_password', currentPw);
        fd.append('new_password',     newPw);
        fd.append('confirm_password', confirmPw);

        const data = await mpPost('change_password', fd);
        showToast(data.message || 'Password changed successfully.', 'success');

        this.reset();
        resetPwStrength();

        // Collapse form
        _pwOpen = false;
        mpPwWrap.classList.remove('is-open');
        mpPwChevron.className = 'fa-solid fa-chevron-down';

    } catch (err) {
        showToast(err.message, 'error');
    } finally {
        setLoading(saveBtn, false, '<i class="fa-solid fa-shield-halved"></i> Update Password', true);
    }
});

// ═══════════════════════════════════════════════════════════
//  PASSWORD VISIBILITY TOGGLES
// ═══════════════════════════════════════════════════════════
document.querySelectorAll('.mp-pw-toggle').forEach(btn => {
    btn.addEventListener('click', function () {
        const targetId = this.dataset.target;
        const input = document.getElementById(targetId);
        if (!input) return;

        const isPassword = input.type === 'password';
        input.type = isPassword ? 'text' : 'password';

        const icon = this.querySelector('i');
        if (icon) icon.className = isPassword ? 'fa-regular fa-eye-slash' : 'fa-regular fa-eye';
    });
});

// ═══════════════════════════════════════════════════════════
//  HELPERS: field errors + loading state
// ═══════════════════════════════════════════════════════════
function showFieldError(errId, msg) {
    const wrap = document.getElementById(errId);
    if (!wrap) return;
    wrap.hidden = false;
    const span = wrap.querySelector('span');
    if (span) span.textContent = msg;
    // Highlight input: find closest mp-field > mp-input
    const field = wrap.closest('.mp-field');
    if (field) {
        const input = field.querySelector('.mp-input');
        if (input) input.style.borderColor = '#dc2626';
    }
}

function clearFieldError(errId) {
    const wrap = document.getElementById(errId);
    if (!wrap) return;
    wrap.hidden = true;
    const span = wrap.querySelector('span');
    if (span) span.textContent = '';
    const field = wrap.closest?.('.mp-field');
    if (field) {
        const input = field?.querySelector('.mp-input');
        if (input) input.style.borderColor = '';
    }
}

function setLoading(btn, loading, label, isDanger = false) {
    if (!btn) return;
    btn.disabled = loading;
    if (loading) {
        btn.classList.add('mp-btn--loading');
        btn.innerHTML = `<span class="mp-btn-spinner"></span> ${label}`;
    } else {
        btn.classList.remove('mp-btn--loading');
        btn.innerHTML = label;
    }
}

// ═══════════════════════════════════════════════════════════
//  INIT
// ═══════════════════════════════════════════════════════════
document.addEventListener('DOMContentLoaded', loadProfile);