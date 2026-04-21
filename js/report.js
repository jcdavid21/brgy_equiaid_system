/**
 * report.js — Barangay EQUIAID Incident Report Page
 * Connects to: Flask API (localhost:5001) + backend/report.php
 */

'use strict';

// ── Config ────────────────────────────────────────────────
const FLASK_API = 'http://localhost:5001';
const BACKEND_API = '../backend/report.php';
const MAX_DESC_LEN = 500;

// ── Safe fetch ────────────────────────────────────────────
async function safeFetch(url, options = {}, timeoutMs = 30000) {
    const ctrl = new AbortController();
    const timer = setTimeout(() => ctrl.abort(), timeoutMs);
    options.signal = ctrl.signal;

    let res;
    try {
        res = await fetch(url, options);
    } catch (err) {
        clearTimeout(timer);
        if (err.name === 'AbortError')
            throw new Error(`Request timed out after ${timeoutMs / 1000}s`);
        throw new Error(`Network error: ${err.message}`);
    }
    clearTimeout(timer);

    const text = await res.text();
    try {
        return JSON.parse(text);
    } catch (_) {
        const title = text.match(/<title>(.*?)<\/title>/i)?.[1] || '';
        throw new Error(
            `Server returned non-JSON (HTTP ${res.status}${title ? ' — ' + title : ''}). ` +
            `Check that ${url} exists and session is active.`
        );
    }
}

// ── DOM refs ──────────────────────────────────────────────
const imageInput = document.getElementById('image-input');
const uploadZone = document.getElementById('upload-zone');
const uzIdle = document.getElementById('uz-idle');
const uzPreview = document.getElementById('uz-preview');
const previewImg = document.getElementById('preview-img');
const uzFilename = document.getElementById('uz-filename');
const uzBrowse = document.getElementById('uz-browse');
const uzRemove = document.getElementById('uz-remove');
const btnAnalyze = document.getElementById('btn-analyze');
const cardResult = document.getElementById('card-result');
const loadingOverlay = document.getElementById('loading-overlay');
const loText = document.getElementById('lo-text');
const btnSubmit = document.getElementById('btn-submit');
const btnReset = document.getElementById('btn-reset');
const fLat        = document.getElementById('f-lat');
const fLng        = document.getElementById('f-lng');
const geoStatus   = document.getElementById('geo-status');
const geoAddress  = document.getElementById('geo-address');
const geoCoords   = document.getElementById('geo-coords');
const btnGeoRetry = document.getElementById('btn-geo-retry');
const fDesc = document.getElementById('f-desc');
const descCount = document.getElementById('desc-count');
const severityPicker = document.getElementById('severity-picker');
const fSeverity = document.getElementById('f-severity');
const modalSuccess = document.getElementById('modal-success');
const modalReportId = document.getElementById('modal-report-id');
const modalClose = document.getElementById('modal-close');
const streetSelect = document.getElementById('f-street');
const typeSelect = document.getElementById('f-type');
const aiSummary = document.getElementById('ai-summary');

// Flood result elements
const floodSevBanner = document.getElementById('flood-severity-banner');
const floodSevLabel = document.getElementById('flood-severity-label');
const floodPct = document.getElementById('flood-pct');
const floodConf = document.getElementById('flood-conf');
const floodOverlay = document.getElementById('flood-overlay-img');
const yoloNanoImg = document.getElementById('yolo-nano-img');
const yoloSmallImg = document.getElementById('yolo-small-img');


// ── State ─────────────────────────────────────────────────
let selectedFile = null;
let predictionDone = false;
let aiFloodResult = null;

// ── Helpers ───────────────────────────────────────────────
function showLoading(msg = 'Processing…') {
    loText.textContent = msg;
    loadingOverlay.hidden = false;
}

function hideLoading() {
    loadingOverlay.hidden = true;
}

function showError(msg) {
    cardResult.hidden = false;
    aiSummary.style.background = '#7f1d1d';
    aiSummary.textContent = '✖ ' + msg;
    cardResult.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

// Maps flood severity string → CSS class
function floodSeverityClass(sev) {
    const map = {
        'None': 'sev-none',
        'Low': 'sev-low',
        'Moderate': 'sev-moderate',
        'High': 'sev-high',
        'Severe': 'sev-severe',
    };
    return map[sev] || 'sev-none';
}

// ── Load streets dropdown ─────────────────────────────────
async function loadStreets() {
    streetSelect.innerHTML = '<option value="">Loading streets…</option>';
    try {
        const data = await safeFetch(`${BACKEND_API}?action=get_streets`);
        if (!data.ok) throw new Error(data.error);

        streetSelect.innerHTML = '<option value="">Select street…</option>';
        data.streets.forEach(s => {
            const opt = document.createElement('option');
            opt.value = s.street_id;
            opt.textContent = s.street_name;
            streetSelect.appendChild(opt);
        });
    } catch (e) {
        streetSelect.innerHTML = '<option value="">Could not load streets</option>';
        console.error('loadStreets:', e.message);
    }
}

// ── Upload zone ───────────────────────────────────────────
uzBrowse.addEventListener('click', () => imageInput.click());

uploadZone.addEventListener('click', e => {
    if (e.target === uploadZone || e.target.closest('#uz-idle')) {
        imageInput.click();
    }
});

uploadZone.addEventListener('dragover', e => {
    e.preventDefault();
    uploadZone.classList.add('drag-over');
});

uploadZone.addEventListener('dragleave', () => {
    uploadZone.classList.remove('drag-over');
});

uploadZone.addEventListener('drop', e => {
    e.preventDefault();
    uploadZone.classList.remove('drag-over');
    const file = e.dataTransfer.files[0];
    if (file) handleFile(file);
});

imageInput.addEventListener('change', () => {
    if (imageInput.files[0]) handleFile(imageInput.files[0]);
});

uzRemove.addEventListener('click', e => {
    e.stopPropagation();
    resetUpload();
});

function handleFile(file) {
    const allowed = ['image/jpeg', 'image/png', 'image/webp'];
    if (!allowed.includes(file.type)) {
        alert('Please upload a JPG, PNG, or WEBP image.');
        return;
    }
    if (file.size > 10 * 1024 * 1024) {
        alert('Image is too large. Maximum size is 10 MB.');
        return;
    }

    selectedFile = file;
    previewImg.src = URL.createObjectURL(file);
    uzFilename.textContent = file.name;
    uzIdle.hidden = true;
    uzPreview.hidden = false;
    btnAnalyze.disabled = false;

    // Reset previous results
    cardResult.hidden = true;
    predictionDone = false;
    aiFloodResult = null;
    aiSummary.textContent = '';
    aiSummary.style.background = '';
}

function resetUpload() {
    selectedFile = null;
    predictionDone = false;
    imageInput.value = '';
    previewImg.src = '';
    uzIdle.hidden = false;
    uzPreview.hidden = true;
    btnAnalyze.disabled = true;
    cardResult.hidden = true;
    aiSummary.textContent = '';
    aiSummary.style.background = '';
}

// ── Analyze button ────────────────────────────────────────
btnAnalyze.addEventListener('click', async () => {
    if (!selectedFile) return;

    btnAnalyze.disabled = true;
    showLoading('Analyzing photo for flood water…');

    try {
        try {
            await safeFetch(`${FLASK_API}/health`, {}, 5000);
        } catch (_) {
            throw new Error(
                'Cannot reach the AI server on port 5001.\n\n' +
                'Make sure you have started it:\n  python predict_api.py'
            );
        }

        const fdForm = new FormData();
        fdForm.append('image', selectedFile);

        aiFloodResult = await safeFetch(
            `${FLASK_API}/predict/flood`,
            { method: 'POST', body: fdForm },
            120000
        );
        if (!aiFloodResult.ok) throw new Error(aiFloodResult.error);

        renderResults(aiFloodResult);
        autoFillForm(aiFloodResult);
        predictionDone = true;

    } catch (err) {
        console.error('Prediction error:', err);
        showError(err.message);
    } finally {
        hideLoading();
        btnAnalyze.disabled = !selectedFile;
    }
});

// ── Render AI results ─────────────────────────────────────
function renderResults(flood) {

    // ── Flood panel ───────────────────────────────────────
    floodSevBanner.className = `severity-banner ${floodSeverityClass(flood.severity)}`;
    floodSevLabel.textContent = `Flood Level: ${flood.severity}`;
    floodPct.textContent  = `${flood.flood_pct}%`;
    floodConf.textContent = `${(flood.confidence * 100).toFixed(1)}%`;
    floodOverlay.src  = `data:image/png;base64,${flood.overlay_b64}`;
    yoloNanoImg.src   = `data:image/png;base64,${flood.yolo_nano_b64}`;
    yoloSmallImg.src  = `data:image/png;base64,${flood.yolo_small_b64}`;

    // ── AI summary banner ─────────────────────────────────
    const isFlood = flood.flood_pct > 15 && !flood.false_positive;

    if (flood.false_positive) {
        aiSummary.className   = 'ai-summary ai-summary--info';
        aiSummary.textContent =
            `ℹ️ The AI spotted blue areas in your photo but determined they are likely sky, ` +
            `haze, or other non-water surfaces — not actual flooding. ` +
            `Please confirm the details below before submitting.`;
    } else if (isFlood) {
        aiSummary.className   = 'ai-summary';
        aiSummary.textContent =
            `⚠ The AI detected ${flood.severity} flooding covering about ${flood.flood_pct}% of your photo. ` +
            `Please review the details below before submitting.`;
    } else {
        aiSummary.className   = 'ai-summary ai-summary--info';
        aiSummary.textContent =
            `✓ The AI did not detect significant flooding in this photo. ` +
            `Please review and adjust the details if needed before submitting.`;
    }

    aiSummary.style.background = '';
    cardResult.hidden = false;
    cardResult.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

// ── Auto-fill form from AI results ────────────────────────
function autoFillForm(flood) {
    const isFlood = !flood.false_positive && flood.flood_pct > 15;
    typeSelect.value = 'Flood';
    const sevMap = { None: 'Low', Low: 'Low', Moderate: 'Moderate', High: 'Moderate', Severe: 'Severe' };
    setSeverity(isFlood ? (sevMap[flood.severity] || 'Moderate') : 'Low');
}

// ── Severity picker ───────────────────────────────────────
severityPicker.addEventListener('click', e => {
    const btn = e.target.closest('.sp-btn');
    if (!btn) return;
    setSeverity(btn.dataset.val);
});

function setSeverity(val) {
    document.querySelectorAll('.sp-btn').forEach(b => {
        b.classList.toggle('active', b.dataset.val === val);
    });
    fSeverity.value = val;
}

// ── Geolocation (auto on page load) ──────────────────────
async function reverseGeocode(lat, lng) {
    try {
        const res = await fetch(
            `https://nominatim.openstreetmap.org/reverse?lat=${lat}&lon=${lng}&format=json&addressdetails=1`,
            { headers: { 'Accept-Language': 'en' }, signal: AbortSignal.timeout(8000) }
        );
        if (!res.ok) throw new Error();
        const data = await res.json();
        const a = data.address || {};
        const parts = [
            a.house_number,
            a.road || a.pedestrian || a.footway,
            a.suburb || a.village || a.neighbourhood || a.quarter,
            a.city || a.town || a.municipality,
            a.state,
        ].filter(Boolean);
        return parts.length ? parts.join(', ') : data.display_name;
    } catch (_) {
        return null;
    }
}

function setGeoState(state, msg) {
    btnGeoRetry.hidden = true;
    if (state === 'detecting') {
        geoStatus.innerHTML    = '<i class="fa-solid fa-spinner fa-spin"></i> Detecting your location…';
        geoAddress.textContent = '';
        geoCoords.textContent  = '';
    } else if (state === 'ok') {
        geoStatus.innerHTML = '<i class="fa-solid fa-circle-check" style="color:#16a34a"></i> Location captured';
    } else {
        geoStatus.innerHTML    = '<i class="fa-solid fa-triangle-exclamation" style="color:#b91c1c"></i> ' + msg;
        geoAddress.textContent = '';
        geoCoords.textContent  = '';
        btnGeoRetry.hidden     = false;
    }
}

async function captureLocation() {
    if (!navigator.geolocation) {
        setGeoState('err', 'Geolocation not supported by this browser.');
        return;
    }
    setGeoState('detecting');
    fLat.value = '';
    fLng.value = '';

    const onSuccess = async pos => {
        const lat = pos.coords.latitude.toFixed(6);
        const lng = pos.coords.longitude.toFixed(6);
        fLat.value = lat;
        fLng.value = lng;
        setGeoState('ok');
        geoCoords.textContent  = `${lat}, ${lng}`;
        geoAddress.textContent = 'Looking up address…';
        const addr = await reverseGeocode(lat, lng);
        geoAddress.textContent = addr || `${lat}, ${lng}`;
    };

    const onError = err => {
        // kCLErrorLocationUnknown = code 2 (POSITION_UNAVAILABLE)
        // Retry once with low accuracy before showing an error
        if (err.code === 2) {
            navigator.geolocation.getCurrentPosition(
                onSuccess,
                finalErr => {
                    const msgs = {
                        1: 'Permission denied — please allow location access in your browser.',
                        2: 'Position unavailable. Please tap Retry or enter your street manually.',
                        3: 'Request timed out. Please try again.',
                    };
                    setGeoState('err', msgs[finalErr.code] || 'Could not get location.');
                },
                { enableHighAccuracy: false, timeout: 10000, maximumAge: 60000 }
            );
            return;
        }
        const msgs = {
            1: 'Permission denied — please allow location access in your browser.',
            2: 'Position unavailable. Please tap Retry or enter your street manually.',
            3: 'Request timed out. Please try again.',
        };
        setGeoState('err', msgs[err.code] || 'Could not get location.');
    };

    navigator.geolocation.getCurrentPosition(
        onSuccess,
        onError,
        { enableHighAccuracy: true, timeout: 10000, maximumAge: 30000 }
    );
}

btnGeoRetry.addEventListener('click', captureLocation);

// ── Character counter ─────────────────────────────────────
fDesc.addEventListener('input', () => {
    const len = fDesc.value.length;
    descCount.textContent = Math.min(len, MAX_DESC_LEN);
    if (len > MAX_DESC_LEN) fDesc.value = fDesc.value.slice(0, MAX_DESC_LEN);
});

// ── Submit report ─────────────────────────────────────────
btnSubmit.addEventListener('click', async () => {
    if (!streetSelect.value) {
        streetSelect.focus();
        alert('Please select the street/location.');
        return;
    }

    showLoading('Sending your report…');

    try {
        const fd = new FormData();
        fd.append('action', 'submit_report');
        fd.append('report_type', typeSelect.value);
        fd.append('street_id', streetSelect.value);
        fd.append('severity', fSeverity.value);
        fd.append('description', fDesc.value);
        if (fLat.value) fd.append('latitude', fLat.value);
        if (fLng.value) fd.append('longitude', fLng.value);
        if (selectedFile) fd.append('image', selectedFile);

        // Include AI results if prediction was run
        if (aiFloodResult) {
            fd.append('ai_flood_severity', aiFloodResult.severity);
            fd.append('ai_flood_pct', aiFloodResult.flood_pct);
            fd.append('ai_flood_confidence', aiFloodResult.confidence);
        }

        const data = await safeFetch(BACKEND_API, { method: 'POST', body: fd });
        if (!data.ok) throw new Error(data.error);

        modalReportId.textContent = `#${data.report_id}`;
        modalSuccess.hidden = false;

    } catch (err) {
        console.error('Submit error:', err);
        alert('Failed to submit report:\n' + err.message);
    } finally {
        hideLoading();
    }
});

// ── Reset ─────────────────────────────────────────────────
btnReset.addEventListener('click', () => {
    resetUpload();
    typeSelect.value = 'Flood';
    streetSelect.value = '';
    fDesc.value = '';
    descCount.textContent = '0';
    fLat.value = fLng.value = '';
    captureLocation();
    setSeverity('Moderate');
});

// ── Modal close ───────────────────────────────────────────
modalClose.addEventListener('click', () => {
    modalSuccess.hidden = true;
    btnReset.click();
});

modalSuccess.addEventListener('click', e => {
    if (e.target === modalSuccess) {
        modalSuccess.hidden = true;
        btnReset.click();
    }
});

// ── Init ──────────────────────────────────────────────────
typeSelect.value = 'Flood';
document.addEventListener('DOMContentLoaded', () => {
    loadStreets();
    captureLocation();
});