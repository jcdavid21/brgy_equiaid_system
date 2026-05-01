<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['user_id'])) {
    header('Location: login.php?redirect=report.php');
    exit;
}


$user_name = htmlspecialchars($_SESSION['user_name'] ?? 'Resident', ENT_QUOTES);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Report Incident — Barangay EQUIAID</title>

    <!-- Fonts & Icons -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;1,9..40,300&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <!-- Site styles -->
    <link rel="stylesheet" href="../styles/global.css">
    <link rel="stylesheet" href="../styles/navbar.css">
    <link rel="stylesheet" href="../styles/report.css">
</head>

<body>

    <?php include 'navbar.php'; ?>

    <!-- ══════════════════════════════════════════════════════
     PAGE HEADER
══════════════════════════════════════════════════════ -->
    <section id="report-hero">
        <div class="container">
            <div class="rh-inner">
                <div>
                    <div class="section-label">Community Reporting</div>
                    <h1 class="section-title">Report an Incident</h1>
                    <p class="section-desc">
                        Upload a photo of flood water in your area. Our AI will analyze
                        the image and classify the flood severity — helping Barangay staff respond faster.
                    </p>
                </div>
                <div class="rh-badges">
                    <div class="rh-badge">
                        <i class="fa-solid fa-water"></i>
                        <span>Flood Detection</span>
                    </div>
                    <div class="rh-badge">
                        <i class="fa-solid fa-brain"></i>
                        <span>AI-Powered</span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ══════════════════════════════════════════════════════
     MAIN CONTENT
══════════════════════════════════════════════════════ -->
    <section id="report-main">
        <div class="container">
            <div class="report-layout">

                <!-- ── LEFT: Upload & Form ──────────────────── -->
                <div class="report-form-col">

                    <!-- Step 1: Upload Image -->
                    <div class="report-card" id="card-upload">
                        <div class="card-step-label">Step 1</div>
                        <h2 class="card-title">Upload Photo</h2>
                        <p class="card-desc">Take or upload a clear photo of the incident area.</p>

                        <!-- Drop zone -->
                        <div class="upload-zone" id="upload-zone">
                            <input type="file" id="image-input" accept="image/jpeg,image/png,image/webp" hidden>
                            <div class="uz-idle" id="uz-idle">
                                <div class="uz-icon"><i class="fa-solid fa-cloud-arrow-up"></i></div>
                                <div class="uz-title">Drop photo here</div>
                                <div class="uz-sub">or <button type="button" class="uz-browse" id="uz-browse">browse files</button></div>
                                <div class="uz-hint">JPG · PNG · WEBP · max 10 MB</div>
                            </div>
                            <div class="uz-preview" id="uz-preview" hidden>
                                <img id="preview-img" src="" alt="Preview">
                                <button type="button" class="uz-remove" id="uz-remove" aria-label="Remove image">
                                    <i class="fa-solid fa-xmark"></i>
                                </button>
                                <div class="uz-filename" id="uz-filename"></div>
                            </div>
                        </div>

                        <!-- Analyze button -->
                        <button class="btn btn-primary btn-analyze" id="btn-analyze" disabled>
                            <i class="fa-solid fa-brain"></i>
                            Analyze with AI
                        </button>
                    </div>

                    <!-- Step 2: AI Results (hidden until analysis done) -->
                    <div class="report-card report-card--result" id="card-result" hidden>
                        <div class="card-step-label">Step 2 — AI Analysis</div>
                        <h2 class="card-title">Prediction Results</h2>
                        <!-- Flood tab -->
                        <div class="result-tab-pane active" id="tab-flood">
                            <div class="severity-banner" id="flood-severity-banner">
                                <div class="sb-label" id="flood-severity-label">—</div>
                                <div class="sb-meta">
                                    <span>Flood coverage: <strong id="flood-pct">—</strong></span>
                                    <span>Confidence: <strong id="flood-conf">—</strong></span>
                                </div>
                            </div>

                            <div class="result-images">
                                <div class="ri-item">
                                    <div class="ri-label">Water Coverage Map</div>
                                    <img id="flood-overlay-img" src="" alt="Water coverage map">
                                </div>
                                <div class="ri-item">
                                    <div class="ri-label">Fast Detection View</div>
                                    <img id="yolo-nano-img" src="" alt="Fast detection view">
                                </div>
                                <div class="ri-item">
                                    <div class="ri-label">Detailed Detection View</div>
                                    <img id="yolo-small-img" src="" alt="Detailed detection view">
                                </div>
                            </div>
                        </div>
                        <!-- AI summary badge -->
                        <div class="ai-summary" id="ai-summary"></div>
                    </div>

                    <!-- Step 3: Report Details -->
                    <div class="report-card" id="card-details">
                        <div class="card-step-label">Step 3</div>
                        <h2 class="card-title">Report Details</h2>
                        <p class="card-desc">Provide additional information to help responders.</p>

                        <div class="form-grid">

                            <div class="form-group form-group--full">
                                <label for="f-type">Incident Type <span class="req">*</span></label>
                                <div class="select-wrap">
                                    <!-- Replace the f-type <select> with this -->
                                    <input type="hidden" id="f-type" name="report_type" value="Flood">
                                    <div class="form-control-static">
                                        <i class="fa-solid fa-water"></i> Flood
                                    </div>
                                </div>
                            </div>

                            <div class="form-group form-group--full">
                                <label for="f-street">Street / Location <span class="req">*</span></label>
                                <div class="select-wrap">
                                    <select id="f-street" name="street_id" required>
                                        <option value="">Loading streets…</option>
                                    </select>
                                    <i class="fa-solid fa-chevron-down"></i>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="f-severity">Severity <span class="req">*</span></label>
                                <div class="severity-picker" id="severity-picker">
                                    <input type="hidden" id="f-severity" name="severity" value="Moderate">
                                    <button type="button" class="sp-btn" data-val="Low">
                                        <span class="sp-dot sp-dot--low"></span>Low
                                    </button>
                                    <button type="button" class="sp-btn active" data-val="Moderate">
                                        <span class="sp-dot sp-dot--moderate"></span>Moderate
                                    </button>
                                    <button type="button" class="sp-btn" data-val="Severe">
                                        <span class="sp-dot sp-dot--severe"></span>Severe
                                    </button>
                                </div>
                            </div>

                            <div class="form-group form-group--full">
                                <label>
                                    <i class="fa-solid fa-location-dot"></i>
                                    Your Location
                                </label>
                                <div class="geo-display" id="geo-display">
                                    <div class="geo-status" id="geo-status">
                                        <i class="fa-solid fa-spinner fa-spin"></i> Detecting your location&hellip;
                                    </div>
                                    <div class="geo-address" id="geo-address"></div>
                                    <div class="geo-coords" id="geo-coords"></div>
                                    <button type="button" class="btn-geo-retry" id="btn-geo-retry" hidden>
                                        <i class="fa-solid fa-rotate-right"></i> Try Again
                                    </button>
                                </div>
                                <input type="hidden" id="f-lat" name="latitude">
                                <input type="hidden" id="f-lng" name="longitude">
                            </div>

                            <div class="form-group form-group--full">
                                <label for="f-desc">Description</label>
                                <textarea id="f-desc" name="description"
                                    placeholder="Describe what you observed — flood depth, affected streets, water level, road conditions…"
                                    rows="4"></textarea>
                                <div class="char-count"><span id="desc-count">0</span> / 500</div>
                            </div>

                        </div><!-- /.form-grid -->

                        <div class="form-actions">
                            <button type="button" class="btn btn-primary btn-submit" id="btn-submit">
                                <i class="fa-solid fa-paper-plane"></i>
                                Submit Report
                            </button>
                            <button type="button" class="btn btn-outline btn-reset" id="btn-reset">
                                <i class="fa-solid fa-rotate-left"></i>
                                Start Over
                            </button>
                        </div>

                    </div><!-- /#card-details -->

                </div><!-- /.report-form-col -->

                <!-- ── RIGHT: Info sidebar ──────────────────── -->
                <aside class="report-sidebar">

                    <div class="sidebar-card">
                        <div class="sc-icon"><i class="fa-solid fa-circle-info"></i></div>
                        <h3>How It Works</h3>
                        <ol class="how-list">
                            <li>
                                <span class="hl-num">1</span>
                                <div>
                                    <strong>Upload a photo</strong>
                                    <p>Take or select a clear photo of the flooded area.</p>
                                </div>
                            </li>
                            <li>
                                <span class="hl-num">2</span>
                                <div>
                                    <strong>AI Analysis</strong>
                                    <p>Our models detect flood extent and measure how much of the area is affected.</p>
                                </div>
                            </li>
                            <li>
                                <span class="hl-num">3</span>
                                <div>
                                    <strong>Fill Details &amp; Submit</strong>
                                    <p>Confirm the location and severity, then submit. Barangay staff are notified instantly.</p>
                                </div>
                            </li>
                        </ol>
                    </div>

                    <div class="sidebar-card sidebar-card--severity">
                        <div class="sc-icon"><i class="fa-solid fa-gauge-high"></i></div>
                        <h3>Severity Guide</h3>
                        <div class="sev-guide">
                            <div class="sg-item">
                                <div class="sg-dot sg-dot--low"></div>
                                <div>
                                    <strong>Low</strong>
                                    <p>Minor flooding, water below ankle level</p>
                                </div>
                            </div>
                            <div class="sg-item">
                                <div class="sg-dot sg-dot--moderate"></div>
                                <div>
                                    <strong>Moderate</strong>
                                    <p>Knee- to waist-deep water, roads impassable</p>
                                </div>
                            </div>
                            <div class="sg-item">
                                <div class="sg-dot sg-dot--severe"></div>
                                <div>
                                    <strong>Severe</strong>
                                    <p>Life-threatening flood levels, homes inundated</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="sidebar-card sidebar-card--ai">
                        <div class="sc-icon sc-icon--ai"><i class="fa-solid fa-microchip"></i></div>
                        <h3>How the AI Works</h3>
                        <div class="model-list">
                            <div class="ml-item">
                                <span class="ml-tag">Object Detection</span>
                                <span>Spots flooded areas in your photo</span>
                            </div>
                            <div class="ml-item">
                                <span class="ml-tag">Coverage Analysis</span>
                                <span>Measures how much of the area is flooded</span>
                            </div>
                        </div>
                    </div>

                    <div class="sidebar-card sidebar-card--emergency">
                        <div class="sc-icon sc-icon--emergency"><i class="fa-solid fa-phone-volume"></i></div>
                        <h3>Emergency Contacts</h3>
                        <div class="em-list">
                            <a href="tel:911" class="em-item">
                                <strong>911</strong><span>National Emergency</span>
                            </a>
                            <a href="tel:8721-0307" class="em-item">
                                <strong>NDRRMC</strong><span>8721-0307</span>
                            </a>
                            <a href="#" class="em-item">
                                <strong>Barangay</strong><span>Hotline</span>
                            </a>
                        </div>
                    </div>

                </aside>

            </div><!-- /.report-layout -->
        </div>
    </section>

    <!-- ══════════════════════════════════════════════════════
     SUCCESS MODAL
══════════════════════════════════════════════════════ -->
    <div class="modal-overlay" id="modal-success" hidden>
        <div class="modal-box" role="dialog" aria-modal="true" aria-labelledby="modal-title">
            <div class="modal-icon"><i class="fa-solid fa-circle-check"></i></div>
            <h2 id="modal-title">Report Submitted!</h2>
            <p>Your incident report has been received. Barangay staff have been notified and will respond shortly.</p>
            <div class="modal-ref">Report ID: <strong id="modal-report-id">—</strong></div>
            <div class="modal-actions">
                <a href="my-reports.php" class="btn btn-primary">
                    <i class="fa-solid fa-list"></i> View My Reports
                </a>
                <button type="button" class="btn btn-outline" id="modal-close">
                    Submit Another
                </button>
            </div>
        </div>
    </div>

    <!-- Loading overlay -->
    <div class="loading-overlay" id="loading-overlay" hidden>
        <div class="lo-inner">
            <div class="lo-spinner"></div>
            <div class="lo-text" id="lo-text">Analyzing image…</div>
        </div>
    </div>

    <script src="../js/navbar.js"></script>
    <script src="../js/report.js" defer></script>
</body>

</html>