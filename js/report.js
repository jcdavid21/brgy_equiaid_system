/**
 * report.js — Barangay EQUIAID Incident Report Page
 * Connects to: Flask API (localhost:5001) + backend/report.php
 */

'use strict';

// ── Config ────────────────────────────────────────────────
const FLASK_API = 'http://localhost:5001';
const BACKEND_API = '../backend/report.php';
const MAX_DESC_LEN = 500;

// ── Submission Guard Thresholds ───────────────────────────
const MIN_CONFIDENCE_FLOOD   = 0.50;   // < 50 % confidence → not considered flood
const MIN_FLOOD_PCT          = 15;     // < 15 % area covered → not significant
const MIN_DESC_LEN_REQUIRED  = 0;      // no minimum description length required
const MAX_REPORTS_PER_SESSION = 5;     // spam-guard: max reports in one session

// ── Session report counter (in-memory anti-spam) ──────────
let sessionReportCount = 0;

// ── Validation state ──────────────────────────────────────
const validationIssues = {
    lowConfidence:  false,
    outOfBounds:    false,
    noLocation:     false,
    spamGuard:      false,
    duplicateFlag:  false,
};

// ── Safe fetch ────────────────────────────────────────────
async function safeFetch(url, options = {}, timeoutMs = 120000) {
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
    aiSummary.innerHTML = '<i class="fa-solid fa-circle-xmark" style="margin-right:6px;"></i>' + msg;
    cardResult.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

// ── Warning panel ─────────────────────────────────────────
const warningPanel = (() => {
    let el = document.getElementById('submission-warnings');
    if (!el) {
        el = document.createElement('div');
        el.id = 'submission-warnings';
        el.style.cssText = `
            display:none; margin:12px 0; padding:14px 16px;
            background:#fff7ed; border:1.5px solid #f97316;
            border-radius:10px; color:#7c2d12; font-size:0.875rem; line-height:1.6;
        `;
        // Insert before the submit button
        btnSubmit && btnSubmit.parentElement && btnSubmit.parentElement.insertBefore(el, btnSubmit);
    }
    return el;
})();

function updateWarningPanel() {
    const isFloodType = typeSelect.value === 'Flood';

    if (isFloodType && !predictionDone) {
        warningPanel.style.display = 'block';
        const notAnalyzedMsg = selectedFile
            ? '<li style="margin-bottom:6px;"><i class="fa-solid fa-magnifying-glass" style="color:#ea580c;margin-right:7px;"></i><strong>Photo not yet analyzed</strong> — You have uploaded a photo. Please click <em>Analyze</em> to run the AI flood check before submitting.</li>'
            : '<li style="margin-bottom:6px;"><i class="fa-solid fa-image" style="color:#ea580c;margin-right:7px;"></i><strong>No photo analyzed</strong> — Please upload a photo and click <em>Analyze</em> before submitting a Flood report.</li>';
        warningPanel.innerHTML =
            '<strong style="color:#c2410c;font-size:0.9rem;"><i class="fa-solid fa-circle-xmark" style="margin-right:6px;"></i>Cannot Submit Yet</strong>' +
            '<ul style="margin:8px 0 0 0;padding-left:18px;">' + notAnalyzedMsg + '</ul>';
        btnSubmit.disabled = true;
        btnSubmit.title    = selectedFile ? 'Click Analyze first' : 'Upload and analyze a photo first';
        return;
    }

    if (validationIssues.lowConfidence) {
        warningPanel.style.display = 'block';
        warningPanel.innerHTML =
            '<strong style="color:#c2410c;font-size:0.9rem;"><i class="fa-solid fa-circle-xmark" style="margin-right:6px;"></i>Cannot Submit — Please resolve the following:</strong>' +
            '<ul style="margin:8px 0 0 0;padding-left:18px;">' +
            '<li style="margin-bottom:6px;"><i class="fa-solid fa-microchip-ai" style="color:#ea580c;margin-right:7px;"></i><strong>Low AI confidence</strong> — The AI model\'s flood detection confidence is below 50%. This photo cannot be classified as a confirmed flood. Please upload a clearer photo showing visible floodwater.</li>' +
            '</ul>';
        btnSubmit.disabled = true;
        btnSubmit.title    = 'Fix the issues above before submitting';
    } else {
        warningPanel.style.display = 'none';
        btnSubmit.disabled = false;
        btnSubmit.title    = '';
    }
}

function hasBlockingIssues() {
    return validationIssues.lowConfidence;
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
    updateWarningPanel();
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
    unlockSeverity();
    updateWarningPanel();
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

        predictionDone = true;
        renderResults(aiFloodResult);
        autoFillForm(aiFloodResult);

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

    // ── Confidence & flood validity check ─────────────────
    const confPct    = flood.confidence * 100;
    const isLowConf  = flood.confidence < MIN_CONFIDENCE_FLOOD;
    const isFloodSig = !flood.false_positive && flood.flood_pct >= MIN_FLOOD_PCT;

    validationIssues.lowConfidence = (typeSelect.value === 'Flood') && isLowConf;
    updateWarningPanel();

    // ── Flood panel ───────────────────────────────────────
    floodSevBanner.className = `severity-banner ${floodSeverityClass(flood.severity)}`;
    floodSevLabel.textContent = `Flood Level: ${flood.severity}`;
    floodPct.textContent  = `${flood.flood_pct}%`;
    floodConf.textContent = `${confPct.toFixed(1)}%`;
    floodOverlay.src  = `data:image/png;base64,${flood.overlay_b64}`;
    yoloNanoImg.src   = `data:image/png;base64,${flood.yolo_nano_b64}`;
    yoloSmallImg.src  = `data:image/png;base64,${flood.yolo_small_b64}`;

    // ── AI summary banner ─────────────────────────────────
    const isFlood = flood.flood_pct > 15 && !flood.false_positive;

    // Find detection-view panels (Fast + Detailed) to hide when not a flood
    const riItems = document.querySelectorAll('.ri-item');
    const detectionPanels = Array.from(riItems).filter(el => {
        const label = el.querySelector('.ri-label');
        return label && (label.textContent.includes('Detection'));
    });

    if (flood.false_positive) {
        // Override displayed stats so users don't see confusing % numbers
        floodPct.textContent  = '0%';
        floodConf.textContent = '—';
        floodSevBanner.className = `severity-banner sev-none`;
        floodSevLabel.textContent = 'Flood Level: None Detected';

        // Hide the YOLO detection panels — boxes on clothing/objects are misleading
        detectionPanels.forEach(p => { p.style.display = 'none'; });

        aiSummary.className  = 'ai-summary ai-summary--info';
        aiSummary.innerHTML  =
            `<i class="fa-solid fa-circle-info" style="margin-right:7px;"></i>` +
            `The AI did <strong>not detect flooding</strong> in this photo. ` +
            `Blue or dark areas were found (e.g. clothing, objects, sky) but are not consistent ` +
            `with actual floodwater. Please confirm the details below before submitting.`;
    } else if (isLowConf && isFloodSig) {
        aiSummary.className  = 'ai-summary ai-summary--warn';
        aiSummary.innerHTML  =
            `<i class="fa-solid fa-triangle-exclamation" style="margin-right:7px;"></i>` +
            `The AI detected ${flood.flood_pct}% flood coverage but confidence is only ` +
            `${confPct.toFixed(1)}% (below the 50% threshold). This will NOT be classified as ` +
            `a flood report. Please upload a clearer photo with visible floodwater, or change ` +
            `the report type to another category.`;
    } else if (isFlood) {
        detectionPanels.forEach(p => { p.style.display = ''; });
        aiSummary.className  = 'ai-summary';
        aiSummary.innerHTML  =
            `<i class="fa-solid fa-triangle-exclamation" style="margin-right:7px;"></i>` +
            `The AI detected ${flood.severity} flooding covering about ${flood.flood_pct}% of your photo ` +
            `(confidence: ${confPct.toFixed(1)}%). ` +
            `Please review the details below before submitting.`;
    } else {
        detectionPanels.forEach(p => { p.style.display = ''; });
        aiSummary.className  = 'ai-summary ai-summary--info';
        aiSummary.innerHTML  =
            `<i class="fa-solid fa-circle-check" style="margin-right:7px;"></i>` +
            `The AI did not detect significant flooding in this photo. ` +
            `Please review and adjust the details if needed before submitting.`;
    }

    aiSummary.style.background = '';
    cardResult.hidden = false;
    cardResult.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

// ── Auto-fill form from AI results ────────────────────────
function autoFillForm(flood) {
    const isLowConf  = flood.confidence < MIN_CONFIDENCE_FLOOD;
    const isFlood    = !flood.false_positive && flood.flood_pct > MIN_FLOOD_PCT;
    const isConfirmedFlood = isFlood && !isLowConf;

    if (isConfirmedFlood) {
        typeSelect.value = 'Flood';
        const sevMap = { None: 'Low', Low: 'Low', Moderate: 'Moderate', High: 'Moderate', Severe: 'Severe' };
        lockSeverity(sevMap[flood.severity] || 'Moderate');
    } else if (isFlood && isLowConf) {
        // Detected something but confidence too low — don't auto-set Flood
        typeSelect.value = 'Other';
        lockSeverity('Low');
    } else if (flood.false_positive) {
        lockSeverity('Low');   // non-flood image — lock at Low, user shouldn't inflate this
    } else {
        lockSeverity('Low');   // no significant flood detected
    }
    // Re-validate after auto-fill
    validateDescription();
    validationIssues.lowConfidence = (typeSelect.value === 'Flood') && isLowConf;
    updateWarningPanel();
}

// ── Description validation ────────────────────────────────
function validateDescription() {
    descCount.textContent = Math.min(fDesc.value.length, MAX_DESC_LEN);
}

// ── Re-validate lowConfidence when type changes ───────────
typeSelect.addEventListener('change', () => {
    if (aiFloodResult) {
        const isLowConf = aiFloodResult.confidence < MIN_CONFIDENCE_FLOOD;
        const isFlood   = !aiFloodResult.false_positive && aiFloodResult.flood_pct >= MIN_FLOOD_PCT;
        validationIssues.lowConfidence = (typeSelect.value === 'Flood') && isLowConf;
        updateWarningPanel();
    }
});

// ── Severity picker ───────────────────────────────────────
// ── Severity lock (set by AI; user cannot override after analysis) ───────
let severityLockedByAI = false;

severityPicker.addEventListener('click', e => {
    if (severityLockedByAI) return;   // ignore clicks when AI has locked it
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

function lockSeverity(val) {
    setSeverity(val);
    severityLockedByAI = true;
    severityPicker.style.opacity    = '0.65';
    severityPicker.style.cursor     = 'not-allowed';
    severityPicker.title = 'Severity is set by the AI analysis and cannot be changed manually.';
    document.querySelectorAll('.sp-btn').forEach(b => {
        b.style.pointerEvents = 'none';
    });

    // Show a small lock notice if not already there
    let notice = document.getElementById('sev-lock-notice');
    if (!notice) {
        notice = document.createElement('p');
        notice.id = 'sev-lock-notice';
        notice.style.cssText =
            'margin:4px 0 0 0; font-size:0.78rem; color:#6b7280; display:flex; align-items:center; gap:5px;';
        severityPicker.parentElement.appendChild(notice);
    }
    notice.innerHTML =
        '<i class="fa-solid fa-lock" style="color:#9ca3af;"></i>' +
        ' Severity is determined by the AI result and cannot be changed manually.';
    notice.hidden = false;
}

function unlockSeverity() {
    severityLockedByAI = false;
    severityPicker.style.opacity     = '';
    severityPicker.style.cursor      = '';
    severityPicker.title             = '';
    document.querySelectorAll('.sp-btn').forEach(b => {
        b.style.pointerEvents = '';
    });
    const notice = document.getElementById('sev-lock-notice');
    if (notice) notice.hidden = true;
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

// ── Geolocation (auto on page load) ──────────────────────
async function captureLocation() {
    if (!navigator.geolocation) {
        setGeoState('err', 'Geolocation not supported by this browser.');
        return;
    }
    setGeoState('detecting');
    fLat.value = '';
    fLng.value = '';

    const GEO_MAX_RETRIES = 3;
    const GEO_RETRY_DELAY = 1500; // ms between retries

    const onSuccess = async pos => {
        const lat = pos.coords.latitude.toFixed(6);
        const lng = pos.coords.longitude.toFixed(6);
        fLat.value = lat;
        fLng.value = lng;

        validationIssues.noLocation  = false;
        validationIssues.outOfBounds = false;
        updateWarningPanel();

        setGeoState('ok');
        geoCoords.textContent  = `${lat}, ${lng}`;
        geoAddress.textContent = 'Looking up address…';
        const addr = await reverseGeocode(lat, lng);
        geoAddress.textContent = addr || `${lat}, ${lng}`;
    };

    const tryGeo = (attempt, highAccuracy) => {
        navigator.geolocation.getCurrentPosition(
            onSuccess,
            err => {
                // kCLErrorLocationUnknown = POSITION_UNAVAILABLE (code 2)
                // Retry up to GEO_MAX_RETRIES times, then fall back to low accuracy
                if (err.code === 2) {
                    if (attempt < GEO_MAX_RETRIES) {
                        setTimeout(() => tryGeo(attempt + 1, highAccuracy), GEO_RETRY_DELAY);
                        return;
                    }
                    // After high-accuracy retries exhausted, try once with low accuracy
                    if (highAccuracy) {
                        setTimeout(() => tryGeo(0, false), GEO_RETRY_DELAY);
                        return;
                    }
                    // All retries exhausted for POSITION_UNAVAILABLE — use Bagong Silang default
                    const DEFAULT_LAT = 14.7097;
                    const DEFAULT_LNG = 121.0450;
                    fLat.value = DEFAULT_LAT.toFixed(6);
                    fLng.value = DEFAULT_LNG.toFixed(6);
                    validationIssues.noLocation  = false;
                    validationIssues.outOfBounds = false;
                    updateWarningPanel();
                    setGeoState('ok');
                    geoCoords.textContent  = `${fLat.value}, ${fLng.value} (default)`;
                    geoAddress.textContent = 'Bagong Silang, North Caloocan (default location)';
                    return;
                }

                const msgs = {
                    1: 'Permission denied — please allow location access in your browser.',
                    2: 'Position unavailable. Please tap Retry or enter your street manually.',
                    3: 'Request timed out. Please try again.',
                };
                validationIssues.noLocation  = true;
                validationIssues.outOfBounds = false;
                updateWarningPanel();
                setGeoState('err', msgs[err.code] || 'Could not get location.');
            },
            highAccuracy
                ? { enableHighAccuracy: true,  timeout: 10000, maximumAge: 30000 }
                : { enableHighAccuracy: false, timeout: 12000, maximumAge: 60000 }
        );
    };

    tryGeo(0, true);
}

btnGeoRetry.addEventListener('click', captureLocation);

// ── Character counter ─────────────────────────────────────
fDesc.addEventListener('input', () => {
    const len = fDesc.value.length;
    descCount.textContent = Math.min(len, MAX_DESC_LEN);
    if (len > MAX_DESC_LEN) fDesc.value = fDesc.value.slice(0, MAX_DESC_LEN);
    validateDescription();
});

// ── Submit report ─────────────────────────────────────────
btnSubmit.addEventListener('click', async () => {
    // ── Re-run all validations before submit ──────────────
    validateDescription();

    // Re-check location
    const latVal = parseFloat(fLat.value);
    const lngVal = parseFloat(fLng.value);
    if (!fLat.value || !fLng.value || isNaN(latVal) || isNaN(lngVal)) {
        validationIssues.noLocation  = true;
        validationIssues.outOfBounds = false;
    } else {
        validationIssues.noLocation  = false;
        validationIssues.outOfBounds = false;
    }

    // Re-check spam guard
    validationIssues.spamGuard = sessionReportCount >= MAX_REPORTS_PER_SESSION;

    updateWarningPanel();

    if (hasBlockingIssues()) return;

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
            fd.append('ai_flood_severity',    aiFloodResult.severity);
            fd.append('ai_flood_pct',         aiFloodResult.flood_pct);
            fd.append('ai_flood_confidence',  aiFloodResult.confidence);
            fd.append('ai_false_positive',    aiFloodResult.false_positive ? '1' : '0');
        }

        // Pass geofence validation result so PHP can double-check
        fd.append('geo_validated', (!validationIssues.outOfBounds && !validationIssues.noLocation) ? '1' : '0');

        const data = await safeFetch(BACKEND_API, { method: 'POST', body: fd });
        if (!data.ok) throw new Error(data.error);

        sessionReportCount++;
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
    // Reset validation flags (keep location flags — captureLocation will update them)
    validationIssues.lowConfidence = false;
    validationIssues.spamGuard     = sessionReportCount >= MAX_REPORTS_PER_SESSION;
    validationIssues.duplicateFlag = false;
    updateWarningPanel();
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
    updateWarningPanel();
});