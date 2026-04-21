/**
 * js/auth.js — Barangay EQUIAID
 * Handles: tab switching, password visibility toggle,
 *          password strength meter, login & signup AJAX,
 *          inline field validation, and redirect on success.
 */

'use strict';

/* ─────────────────────────────────────────────────────────────
   1.  ELEMENT REFERENCES
───────────────────────────────────────────────────────────── */
const tabLogin  = document.getElementById('tab-login');
const tabSignup = document.getElementById('tab-signup');
const panelLogin  = document.getElementById('form-login');
const panelSignup = document.getElementById('form-signup');

const loginForm  = document.getElementById('form-login-el');
const signupForm = document.getElementById('form-signup-el');

const alertLogin  = document.getElementById('alert-login');
const alertSignup = document.getElementById('alert-signup');

// Panel-side copy that updates when switching tabs
const panelTitle = document.getElementById('panel-title');
const panelDesc  = document.getElementById('panel-desc');

const PANEL_COPY = {
  login: {
    title: 'Protecting <em>Barangay Bagong Silang</em> Together',
    desc : 'Sign in to access real-time street vulnerability data, disaster monitoring, and social welfare resource information for your community.',
  },
  signup: {
    title: 'Join the <em>EQUIAID</em> Community',
    desc : 'Create a resident account to report street incidents, receive disaster alerts, and access DSWD assistance information.',
  },
};


/* ─────────────────────────────────────────────────────────────
   2.  TAB SWITCHING
───────────────────────────────────────────────────────────── */
function activateTab(tab) {
  const isLogin = tab === 'login';

  tabLogin.classList.toggle('active', isLogin);
  tabSignup.classList.toggle('active', !isLogin);
  tabLogin.setAttribute('aria-selected',  isLogin);
  tabSignup.setAttribute('aria-selected', !isLogin);

  panelLogin.classList.toggle('active',  isLogin);
  panelSignup.classList.toggle('active', !isLogin);

  // Swap left-panel copy
  if (panelTitle) panelTitle.innerHTML = PANEL_COPY[tab].title;
  if (panelDesc)  panelDesc.textContent = PANEL_COPY[tab].desc;

  hideAlert(isLogin ? alertLogin : alertSignup);
}

tabLogin.addEventListener('click',  () => activateTab('login'));
tabSignup.addEventListener('click', () => activateTab('signup'));

// "#signup" / "#login" anchor links inside the forms
document.getElementById('link-to-signup')?.addEventListener('click', e => {
  e.preventDefault();
  activateTab('signup');
  panelSignup.querySelector('input')?.focus();
});
document.getElementById('link-to-login')?.addEventListener('click', e => {
  e.preventDefault();
  activateTab('login');
  panelLogin.querySelector('input')?.focus();
});


/* ─────────────────────────────────────────────────────────────
   3.  PASSWORD VISIBILITY TOGGLE
───────────────────────────────────────────────────────────── */
document.querySelectorAll('.toggle-pw').forEach(toggle => {
  function handleToggle() {
    // The password input is the preceding sibling with class field-input
    const wrap  = toggle.closest('.field-input-wrap');
    const input = wrap?.querySelector('input.field-input');
    if (!input) return;

    const show = input.type === 'password';
    input.type = show ? 'text' : 'password';

    toggle.classList.toggle('fa-eye',      !show);
    toggle.classList.toggle('fa-eye-slash', show);
    toggle.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
  }

  toggle.addEventListener('click', handleToggle);

  // Keyboard accessibility — Space / Enter activates the toggle
  toggle.addEventListener('keydown', e => {
    if (e.key === ' ' || e.key === 'Enter') {
      e.preventDefault();
      handleToggle();
    }
  });
});


/* ─────────────────────────────────────────────────────────────
   4.  PASSWORD STRENGTH METER  (signup only)
───────────────────────────────────────────────────────────── */
const suPassword    = document.getElementById('su-password');
const strengthFill  = document.getElementById('pw-strength-fill');
const strengthLabel = document.getElementById('pw-strength-label');

const STRENGTH_LEVELS = [
  { label: 'Too short',  color: '#e53e3e', width: '15%'  },
  { label: 'Weak',       color: '#e53e3e', width: '25%'  },
  { label: 'Fair',       color: '#ed8936', width: '50%'  },
  { label: 'Good',       color: '#3182ce', width: '75%'  },
  { label: 'Strong',     color: '#38a169', width: '100%' },
];

function scorePassword(pw) {
  if (!pw || pw.length < 8) return 0;
  let score = 1;
  if (pw.length >= 12)          score++;
  if (/[A-Z]/.test(pw))         score++;
  if (/[0-9]/.test(pw))         score++;
  if (/[^A-Za-z0-9]/.test(pw))  score++;
  return Math.min(score, 4);
}

suPassword?.addEventListener('input', () => {
  const pw    = suPassword.value;
  const score = scorePassword(pw);
  const level = pw.length === 0 ? null : STRENGTH_LEVELS[score];

  if (!level) {
    strengthFill.style.width       = '0';
    strengthLabel.textContent      = '';
  } else {
    strengthFill.style.width            = level.width;
    strengthFill.style.backgroundColor  = level.color;
    strengthLabel.textContent           = level.label;
    strengthLabel.style.color           = level.color;
  }
});


/* ─────────────────────────────────────────────────────────────
   5.  ALERT HELPERS
───────────────────────────────────────────────────────────── */
function showAlert(el, message, type = 'error') {
  el.querySelector('.alert-text').textContent = message;
  el.className = `auth-alert auth-alert--${type}`;
  el.style.display = 'flex';
  el.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

function hideAlert(el) {
  el.style.display = 'none';
}


/* ─────────────────────────────────────────────────────────────
   6.  FIELD-LEVEL VALIDATION HELPERS
───────────────────────────────────────────────────────────── */
function setFieldError(input, message) {
  const group = input.closest('.field-group');
  if (!group) return;

  input.classList.add('field-input--error');
  input.setAttribute('aria-invalid', 'true');

  const errEl = group.querySelector('.field-error');
  if (errEl) {
    // Keep the icon, append text node
    const icon = errEl.querySelector('i');
    errEl.textContent = '';
    if (icon) errEl.appendChild(icon);
    errEl.append(` ${message}`);
    errEl.style.display = 'flex';
  }
}

function clearFieldError(input) {
  const group = input.closest('.field-group');
  if (!group) return;

  input.classList.remove('field-input--error');
  input.removeAttribute('aria-invalid');

  const errEl = group.querySelector('.field-error');
  if (errEl) errEl.style.display = 'none';
}

// Clear on user interaction
document.querySelectorAll('.field-input').forEach(input => {
  input.addEventListener('input', () => clearFieldError(input));
});


/* ─────────────────────────────────────────────────────────────
   7.  LOADING STATE HELPERS
───────────────────────────────────────────────────────────── */
function setLoading(form, loading) {
  const btn     = form.querySelector('.btn-auth');
  const spinner = btn?.querySelector('.btn-spinner');
  const label   = btn?.querySelector('.btn-label');

  if (!btn) return;
  btn.disabled = loading;
  if (spinner) spinner.style.display = loading ? 'inline-block' : 'none';
  if (label)   label.style.opacity   = loading ? '0.5' : '1';
}


/* ─────────────────────────────────────────────────────────────
   8.  LOGIN FORM SUBMISSION
───────────────────────────────────────────────────────────── */
loginForm?.addEventListener('submit', async (e) => {
  e.preventDefault();
  hideAlert(alertLogin);

  const emailInput = document.getElementById('login-email');
  const pwInput    = document.getElementById('login-password');
  const remember   = document.getElementById('remember');

  const email    = emailInput.value.trim();
  const password = pwInput.value;

  // ── Client-side validation ──────────────────────────────
  let valid = true;

  if (!email) {
    setFieldError(emailInput, 'Email address is required.');
    valid = false;
  } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
    setFieldError(emailInput, 'Please enter a valid email address.');
    valid = false;
  }

  if (!password) {
    setFieldError(pwInput, 'Password is required.');
    valid = false;
  }

  if (!valid) return;

  // ── Send to backend ─────────────────────────────────────
  setLoading(loginForm, true);

  try {
    const body = new URLSearchParams({
      action  : 'login',
      email,
      password,
      remember: remember?.checked ? '1' : '0',
    });

    const res  = await fetch('../backend/auth.php', {
      method : 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body,
    });

    const data = await res.json();

    if (data.ok) {
      showAlert(alertLogin, data.message || 'Login successful! Redirecting…', 'success');
      setTimeout(() => {
        window.location.href = data.redirect ?? 'index.php';
      }, 800);
    } else {
      showAlert(alertLogin, data.message || 'Invalid email or password.');
    }

  } catch (err) {
    console.error('[auth] login error:', err);
    showAlert(alertLogin, 'A network error occurred. Please try again.');
  } finally {
    setLoading(loginForm, false);
  }
});


/* ─────────────────────────────────────────────────────────────
   9.  SIGNUP FORM SUBMISSION
───────────────────────────────────────────────────────────── */
signupForm?.addEventListener('submit', async (e) => {
  e.preventDefault();
  hideAlert(alertSignup);

  const nameInput    = document.getElementById('su-name');
  const emailInput   = document.getElementById('su-email');
  const phoneInput   = document.getElementById('su-phone');
  const pwInput      = document.getElementById('su-password');
  const confirmInput = document.getElementById('su-confirm-pw');
  const termsCheck   = document.getElementById('terms');

  const name     = nameInput.value.trim();
  const email    = emailInput.value.trim();
  const phone    = phoneInput.value.trim();
  const password = pwInput.value;
  const confirm  = confirmInput.value;

  // ── Client-side validation ──────────────────────────────
  let valid = true;

  if (!name) {
    setFieldError(nameInput, 'Full name is required.');
    valid = false;
  } else if (name.length < 2) {
    setFieldError(nameInput, 'Name must be at least 2 characters.');
    valid = false;
  }

  if (!email) {
    setFieldError(emailInput, 'Email address is required.');
    valid = false;
  } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
    setFieldError(emailInput, 'Please enter a valid email address.');
    valid = false;
  }

  if (phone && !/^(09|\+639)\d{9}$/.test(phone)) {
    setFieldError(phoneInput, 'Enter a valid PH number (e.g. 09XXXXXXXXX).');
    valid = false;
  }

  if (!password) {
    setFieldError(pwInput, 'Password is required.');
    valid = false;
  } else if (password.length < 8) {
    setFieldError(pwInput, 'Password must be at least 8 characters.');
    valid = false;
  }

  if (!confirm) {
    setFieldError(confirmInput, 'Please confirm your password.');
    valid = false;
  } else if (password !== confirm) {
    setFieldError(confirmInput, 'Passwords do not match.');
    valid = false;
  }

  if (!termsCheck?.checked) {
    showAlert(alertSignup, 'You must agree to the Terms of Use to continue.');
    valid = false;
  }

  if (!valid) return;

  // ── Send to backend ─────────────────────────────────────
  setLoading(signupForm, true);

  try {
    const body = new URLSearchParams({
      action      : 'signup',
      name,
      email,
      phone_number: phone,
      password,
      confirm_pw  : confirm,
    });

    const res  = await fetch('../backend/auth.php', {
      method : 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body,
    });

    const data = await res.json();

    if (data.ok) {
      showAlert(alertSignup, data.message || 'Account created! Redirecting…', 'success');
      signupForm.reset();
      // Reset strength meter
      if (strengthFill)  strengthFill.style.width = '0';
      if (strengthLabel) strengthLabel.textContent = '';

      setTimeout(() => {
        if (data.redirect) {
          window.location.href = data.redirect;
        } else {
          activateTab('login');
          showAlert(alertLogin, 'Account created! Please sign in.', 'success');
        }
      }, 1200);

    } else {
      showAlert(alertSignup, data.message || 'Registration failed. Please try again.');

      // Highlight specific field errors returned by the server
      if (data.field === 'email') setFieldError(emailInput, data.message);
      if (data.field === 'name')  setFieldError(nameInput, data.message);
    }

  } catch (err) {
    console.error('[auth] signup error:', err);
    showAlert(alertSignup, 'A network error occurred. Please try again.');
  } finally {
    setLoading(signupForm, false);
  }
});


/* ─────────────────────────────────────────────────────────────
   10.  CONFIRM-PASSWORD LIVE CHECK
───────────────────────────────────────────────────────────── */
const confirmPwInput = document.getElementById('su-confirm-pw');
confirmPwInput?.addEventListener('input', () => {
  if (confirmPwInput.value && confirmPwInput.value !== suPassword?.value) {
    setFieldError(confirmPwInput, 'Passwords do not match.');
  } else {
    clearFieldError(confirmPwInput);
  }
});


/* ─────────────────────────────────────────────────────────────
   11.  HANDLE DEEP-LINK HASH  (#signup / #login)
───────────────────────────────────────────────────────────── */
(function handleHash() {
  const hash = window.location.hash.toLowerCase();
  if (hash === '#signup') activateTab('signup');
})();