<?php

$current_page = 'street-monitoring';
$page_title   = 'Street Monitoring — Barangay EQUIAID';

if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title) ?></title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;1,400&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"
          integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA=="
          crossorigin="anonymous" referrerpolicy="no-referrer">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css">
    <link rel="stylesheet" href="../styles/admin_sidebar.css">
    <link rel="stylesheet" href="../styles/admin_dashboard.css">
    <link rel="stylesheet" href="../styles/street_monitoring.css">
</head>
<body>

<div class="admin-shell">
    <?php include 'sidebar.php'; ?>

    <main class="main-content" id="mainContent">
        <div class="dashboard-container">

            <!-- ── PAGE HEADER ─────────────────────────── -->
            <div class="dash-header">
                <div class="dash-header-left">
                    <div class="dash-breadcrumb">
                        <i class="fa-solid fa-road"></i>
                        <span>Street Monitoring</span>
                    </div>
                    <h1 class="dash-title">Street Monitoring</h1>
                    <p class="dash-desc">
                        Real-time vulnerability tracking, risk classification, and demographic
                        indicators for all monitored streets in Barangay Bagong Silang.
                    </p>
                </div>
                <div class="dash-header-right">
                    <div class="dash-meta">
                        <span class="dash-meta-label">Last updated</span>
                        <span class="dash-meta-value" id="lastUpdated">—</span>
                    </div>
                    <div class="live-pill">
                        <span class="live-dot"></span>
                        <span>Live</span>
                    </div>
                </div>
            </div>

            <!-- ── KPI STRIP ───────────────────────────── -->
            <div class="overview-grid">
                <div class="kpi-card">
                    <div class="kpi-icon-wrap neutral"><i class="fa-solid fa-road"></i></div>
                    <div class="kpi-body">
                        <span class="kpi-label">Total Streets</span>
                        <span class="kpi-number sk-inline" id="kpi-sm-total">—</span>
                        <span class="kpi-sub">All monitored streets</span>
                    </div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-icon-wrap" style="background:#b91c1c;"><i class="fa-solid fa-circle-exclamation" style="color:#fff;font-size:14px;"></i></div>
                    <div class="kpi-body">
                        <span class="kpi-label">Critical (RED)</span>
                        <span class="kpi-number sk-inline" id="kpi-sm-red" style="color:#b91c1c;">—</span>
                        <span class="kpi-sub">Highest risk streets</span>
                    </div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-icon-wrap" style="background:#d97706;"><i class="fa-solid fa-triangle-exclamation" style="color:#fff;font-size:14px;"></i></div>
                    <div class="kpi-body">
                        <span class="kpi-label">High Risk (ORANGE)</span>
                        <span class="kpi-number sk-inline" id="kpi-sm-orange" style="color:#d97706;">—</span>
                        <span class="kpi-sub">Needs attention</span>
                    </div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-icon-wrap" style="background:#ca8a04;"><i class="fa-solid fa-circle-half-stroke" style="color:#fff;font-size:14px;"></i></div>
                    <div class="kpi-body">
                        <span class="kpi-label">Moderate (YELLOW)</span>
                        <span class="kpi-number sk-inline" id="kpi-sm-yellow" style="color:#ca8a04;">—</span>
                        <span class="kpi-sub">Under observation</span>
                    </div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-icon-wrap" style="background:#16a34a;"><i class="fa-solid fa-circle-check" style="color:#fff;font-size:14px;"></i></div>
                    <div class="kpi-body">
                        <span class="kpi-label">Safe (GREEN)</span>
                        <span class="kpi-number sk-inline" id="kpi-sm-green" style="color:#16a34a;">—</span>
                        <span class="kpi-sub">Low risk streets</span>
                    </div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-icon-wrap dark"><i class="fa-solid fa-hand-holding-heart"></i></div>
                    <div class="kpi-body">
                        <span class="kpi-label">Need Welfare</span>
                        <span class="kpi-number sk-inline" id="kpi-sm-welfare">—</span>
                        <span class="kpi-sub">Requiring assistance</span>
                    </div>
                </div>
            </div>

            <!-- ── MAP + RISK BARS ─────────────────────── -->
            <div class="mid-row">

                <!-- MAP CARD -->
                <div class="dash-card map-card">
                    <div class="card-head">
                        <div>
                            <div class="card-label">Live Map View</div>
                            <h2 class="card-title">Street Risk Map</h2>
                        </div>
                        <div class="sm-map-controls">
                            <button class="btn-dash-outline sm-map-toggle active" id="btnLayerRisk">
                                <i class="fa-solid fa-layer-group"></i> Risk
                            </button>
                            <button class="btn-dash-outline sm-map-toggle" id="btnLayerWelfare">
                                <i class="fa-solid fa-hand-holding-heart"></i> Welfare
                            </button>
                        </div>
                    </div>
                    <div class="dashboard-map-wrap" style="margin:0 -28px;">
                        <div id="sm-map" style="height:420px;width:100%;z-index:1;"></div>
                        <div class="map-loading-overlay" id="smMapOverlay">
                            <div class="map-spinner"></div>
                            <span>Loading map…</span>
                        </div>
                    </div>
                    <div class="map-legend-row">
                        <div class="map-legend-item"><span class="map-legend-dot" style="background:#b91c1c;"></span> Critical (RED) <span class="map-legend-count" id="leg-sm-red">—</span></div>
                        <div class="map-legend-item"><span class="map-legend-dot" style="background:#d97706;"></span> High Risk (ORANGE) <span class="map-legend-count" id="leg-sm-orange">—</span></div>
                        <div class="map-legend-item"><span class="map-legend-dot" style="background:#ca8a04;"></span> Moderate (YELLOW) <span class="map-legend-count" id="leg-sm-yellow">—</span></div>
                        <div class="map-legend-item"><span class="map-legend-dot" style="background:#16a34a;"></span> Safe (GREEN) <span class="map-legend-count" id="leg-sm-green">—</span></div>
                        <div class="map-legend-item"><span class="map-legend-dot" style="background:#1d4ed8;border-radius:3px;"></span> Evac Center <span class="map-legend-count" id="leg-sm-evac">—</span></div>
                    </div>
                </div>

                <!-- RISK BARS + WELFARE -->
                <div class="dash-card" style="display:flex;flex-direction:column;">
                    <div class="card-head">
                        <div>
                            <div class="card-label">Classification</div>
                            <h2 class="card-title">Risk Distribution</h2>
                        </div>
                    </div>
                    <div class="risk-chart-wrap" id="smRiskChart">
                        <div class="sk-bar-row"></div>
                        <div class="sk-bar-row"></div>
                        <div class="sk-bar-row"></div>
                        <div class="sk-bar-row"></div>
                    </div>
                    <div class="risk-summary-row" id="smRiskSummary">
                        <div class="risk-summary-item"><span class="rs-num rs-red sk-inline">&nbsp;</span><span class="rs-label">Critical</span></div>
                        <div class="risk-summary-item"><span class="rs-num rs-orange sk-inline">&nbsp;</span><span class="rs-label">High Risk</span></div>
                        <div class="risk-summary-item"><span class="rs-num rs-yellow sk-inline">&nbsp;</span><span class="rs-label">Moderate</span></div>
                        <div class="risk-summary-item"><span class="rs-num rs-green sk-inline">&nbsp;</span><span class="rs-label">Safe</span></div>
                    </div>
                    <div style="margin-top:20px;padding-top:18px;border-top:1px solid var(--border-light);">
                        <div class="card-label" style="margin-bottom:12px;">Welfare Needs</div>
                        <div id="smWelfareBars">
                            <div class="sk-bar-row"></div>
                            <div class="sk-bar-row"></div>
                            <div class="sk-bar-row"></div>
                        </div>
                    </div>
                </div>

            </div><!-- /.mid-row -->

            <!-- ── STREET RECORDS TABLE ────────────────── -->
            <div class="dash-card">
                <div class="card-head">
                    <div>
                        <div class="card-label">All Streets</div>
                        <h2 class="card-title">Street Records</h2>
                    </div>
                    <div class="sm-head-actions">
                        <button class="btn-dash-outline" id="smExportBtn">
                            <i class="fa-solid fa-file-export"></i> Export CSV
                        </button>
                        <button class="btn-sm-primary" id="smAddStreetBtn">
                            <i class="fa-solid fa-plus"></i> Add Street
                        </button>
                    </div>
                </div>

                <!-- FILTER BAR -->
                <div class="sm-filter-bar">
                    <div class="sm-search-wrap">
                        <i class="fa-solid fa-magnifying-glass sm-search-icon"></i>
                        <input type="text" class="sm-search-input" id="smSearch"
                               placeholder="Search street name or zone…" autocomplete="off">
                        <button class="sm-search-clear" id="smSearchClear" style="display:none;" aria-label="Clear">
                            <i class="fa-solid fa-xmark"></i>
                        </button>
                    </div>
                    <div class="sm-filter-chips">
                        <button class="sm-chip active" data-filter="risk" data-val="">All Risk</button>
                        <button class="sm-chip" data-filter="risk" data-val="RED"><span class="sm-chip-dot" style="background:#b91c1c;"></span>RED</button>
                        <button class="sm-chip" data-filter="risk" data-val="ORANGE"><span class="sm-chip-dot" style="background:#d97706;"></span>ORANGE</button>
                        <button class="sm-chip" data-filter="risk" data-val="YELLOW"><span class="sm-chip-dot" style="background:#ca8a04;"></span>YELLOW</button>
                        <button class="sm-chip" data-filter="risk" data-val="GREEN"><span class="sm-chip-dot" style="background:#16a34a;"></span>GREEN</button>
                    </div>
                    <div class="sm-filter-chips">
                        <button class="sm-chip active" data-filter="welfare" data-val="">All Welfare</button>
                        <button class="sm-chip" data-filter="welfare" data-val="Yes">Needs Welfare</button>
                        <button class="sm-chip" data-filter="welfare" data-val="Moderate">Moderate</button>
                        <button class="sm-chip" data-filter="welfare" data-val="No">No Welfare</button>
                    </div>
                    <div class="sm-filter-chips">
                        <select class="sm-select-inline" id="smZoneFilter"><option value="">All Zones</option></select>
                        <select class="sm-select-inline" id="smSortBy">
                            <option value="score_desc">Score ↓</option>
                            <option value="score_asc">Score ↑</option>
                            <option value="name_asc">Name A–Z</option>
                            <option value="name_desc">Name Z–A</option>
                        </select>
                    </div>
                </div>

                <div class="table-wrap" style="margin-top:16px;">
                    <table class="dashboard-table">
                        <thead>
                            <tr>
                                <th style="width:40px;">#</th>
                                <th>Street Name</th>
                                <th>Zone</th>
                                <th>Vuln. Score</th>
                                <th>Risk Level</th>
                                <th>Needs Welfare</th>
                                <th>Flood Freq.</th>
                                <th>PWD / Senior</th>
                                <th>4Ps HH</th>
                                <th>Last Predicted</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="smTableTbody">
                            <tr><td colspan="11" class="tbl-loading"><span class="sk-inline">Loading street records…</span></td></tr>
                        </tbody>
                    </table>
                </div>
                <div class="table-footer" id="smTableFooter" style="display:none;">
                    <span class="table-info" id="smTableInfo"></span>
                    <div class="pagination" id="smTablePagination"></div>
                </div>
            </div>

            <!-- ── RECENT IMAGES CARD ──────────────────── -->
            <div class="dash-card">
                <div class="card-head">
                    <div>
                        <div class="card-label">Street Documentation</div>
                        <h2 class="card-title">Recent Uploaded Images</h2>
                    </div>
                    <select class="sm-select-inline" id="smImgStreetFilter">
                        <option value="">All Streets</option>
                    </select>
                </div>
                <div class="sm-image-grid" id="smImageGrid">
                    <div class="sm-img-skeleton"></div>
                    <div class="sm-img-skeleton"></div>
                    <div class="sm-img-skeleton"></div>
                    <div class="sm-img-skeleton"></div>
                    <div class="sm-img-skeleton"></div>
                    <div class="sm-img-skeleton"></div>
                </div>
            </div>

            <!-- ── DEMOGRAPHICS TABLE ──────────────────── -->
            <div class="dash-card">
                <div class="card-head">
                    <div>
                        <div class="card-label">CBMS / DSWD Survey Data</div>
                        <h2 class="card-title">Demographic Indicators</h2>
                    </div>
                    <button class="btn-sm-primary" id="smAddDemoBtn">
                        <i class="fa-solid fa-plus"></i> Add Demographics
                    </button>
                </div>
                <div class="table-wrap">
                    <table class="dashboard-table">
                        <thead>
                            <tr>
                                <th>Street</th>
                                <th>Survey Date</th>
                                <th>4Ps HH</th>
                                <th>Poverty Rate</th>
                                <th>PWD</th>
                                <th>Senior</th>
                                <th>Pregnant</th>
                                <th>Informal Settlers</th>
                                <th>Flood Freq.</th>
                                <th>Avg Flood Ht.</th>
                                <th>Drainage</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="smDemoTbody">
                            <tr><td colspan="12" class="tbl-loading"><span class="sk-inline">Loading demographic data…</span></td></tr>
                        </tbody>
                    </table>
                </div>
                <div class="table-footer" id="smDemoFooter" style="display:none;">
                    <span class="table-info" id="smDemoInfo"></span>
                    <div class="pagination" id="smDemoPagination"></div>
                </div>
            </div>

        </div><!-- /.dashboard-container -->
    </main>
</div><!-- /.admin-shell -->

<!-- ══ STREET DETAIL MODAL ══════════════════════════════ -->
<div class="modal-backdrop" id="streetModal" role="dialog" aria-modal="true">
    <div class="modal-box" style="max-width:640px;">
        <div class="modal-header">
            <div class="modal-title-group">
                <span class="modal-eyebrow" id="streetModalEyebrow">Street Details</span>
                <h3 class="modal-title" id="streetModalTitle">—</h3>
            </div>
            <button class="modal-close" id="streetModalClose" aria-label="Close">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <div class="modal-body" id="streetModalBody"></div>
        <div class="modal-footer">
            <button class="btn-sm-primary" id="streetModalEdit">
                <i class="fa-solid fa-pen"></i> Edit Street
            </button>
        </div>
    </div>
</div>

<!-- ══ IMAGE LIGHTBOX ════════════════════════════════════ -->
<div class="modal-backdrop" id="imgLightbox" role="dialog" aria-modal="true">
    <div class="sm-lightbox-box">
        <button class="modal-close" id="imgLightboxClose"
                style="position:absolute;top:12px;right:12px;z-index:2;" aria-label="Close">
            <i class="fa-solid fa-xmark"></i>
        </button>
        <img id="imgLightboxSrc" src="" alt="Street image preview">
        <div class="sm-lightbox-meta" id="imgLightboxMeta"></div>
    </div>
</div>

<!-- ══ ADD / EDIT STREET MODAL ══════════════════════════ -->
<div class="modal-backdrop" id="editStreetModal" role="dialog" aria-modal="true">
    <div class="modal-box" style="max-width:480px;">
        <div class="modal-header">
            <div class="modal-title-group">
                <span class="modal-eyebrow" id="editStreetEyebrow">Street Record</span>
                <h3 class="modal-title" id="editStreetTitle">Add New Street</h3>
            </div>
            <button class="modal-close" id="editStreetClose" aria-label="Close">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <div class="modal-body">
            <form id="editStreetForm" novalidate>
                <input type="hidden" id="esStreetId">
                <div class="modal-field-grid">
                    <div class="modal-field full">
                        <label class="modal-field-label" for="esStreetName">Street Name</label>
                        <input type="text" class="sm-input" id="esStreetName" placeholder="e.g. Rizal Avenue" required>
                    </div>
                    <div class="modal-field">
                        <label class="modal-field-label" for="esZone">Zone</label>
                        <select class="sm-select" id="esZone" required>
                            <option value="">Select zone…</option>
                        </select>
                    </div>
                    <div class="modal-field">
                        <label class="modal-field-label" for="esStatus">Status</label>
                        <select class="sm-select" id="esStatus">
                            <option value="Active">Active</option>
                            <option value="Inactive">Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="sm-form-actions">
                    <button type="button" class="btn-dash-outline" id="esCancel">Cancel</button>
                    <button type="submit" class="btn-sm-primary">
                        <i class="fa-solid fa-floppy-disk"></i> Save Street
                    </button>
                </div>
                <div class="sm-upload-feedback" id="esFeedback" style="display:none;margin-top:10px;"></div>
            </form>
        </div>
    </div>
</div>


<!-- ══ ADD / EDIT DEMOGRAPHICS MODAL ═══════════════════ -->
<div class="modal-backdrop" id="demoModal" role="dialog" aria-modal="true">
    <div class="modal-box" style="max-width:720px;">
        <div class="modal-header">
            <div class="modal-title-group">
                <span class="modal-eyebrow" id="demoModalEyebrow">Demographic Survey</span>
                <h3 class="modal-title" id="demoModalTitle">Add Demographic Indicators</h3>
            </div>
            <button class="modal-close" id="demoModalClose" aria-label="Close">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <div class="modal-body" style="max-height:70vh;overflow-y:auto;">
            <form id="demoForm" novalidate>
                <input type="hidden" id="ddDemoId">

                <!-- Section: Street & Survey -->
                <div class="dm-form-section-title">Street &amp; Survey Info</div>
                <div class="modal-field-grid">
                    <div class="modal-field full">
                        <label class="modal-field-label" for="ddStreet">Street <span class="dm-required">*</span></label>
                        <select class="sm-select" id="ddStreet" required>
                            <option value="">Select a street&hellip;</option>
                        </select>
                    </div>
                    <div class="modal-field">
                        <label class="modal-field-label" for="ddSurveyDate">Survey Date <span class="dm-required">*</span></label>
                        <input type="date" class="sm-input" id="ddSurveyDate" required>
                    </div>
                    <div class="modal-field">
                        <label class="modal-field-label" for="ddDataSource">Data Source</label>
                        <input type="text" class="sm-input" id="ddDataSource" placeholder="e.g. CBMS, DSWD survey">
                    </div>
                </div>

                <!-- Section: Household & Poverty -->
                <div class="dm-form-section-title">Household &amp; Poverty</div>
                <div class="modal-field-grid">
                    <div class="modal-field">
                        <label class="modal-field-label" for="dd4Ps">4Ps Households</label>
                        <input type="number" class="sm-input" id="dd4Ps" min="0" placeholder="0">
                    </div>
                    <div class="modal-field">
                        <label class="modal-field-label" for="ddPovertyRate">Poverty Rate (%)</label>
                        <input type="number" class="sm-input" id="ddPovertyRate" min="0" max="100" step="0.01" placeholder="0.00">
                    </div>
                    <div class="modal-field">
                        <label class="modal-field-label" for="ddAvgIncome">Avg Monthly Income (&#8369;)</label>
                        <input type="number" class="sm-input" id="ddAvgIncome" min="0" step="0.01" placeholder="0.00">
                    </div>
                    <div class="modal-field">
                        <label class="modal-field-label" for="ddInformalPct">Informal Settlers (%)</label>
                        <input type="number" class="sm-input" id="ddInformalPct" min="0" max="100" step="0.01" placeholder="0.00">
                    </div>
                </div>

                <!-- Section: Vulnerable Groups -->
                <div class="dm-form-section-title">Vulnerable Groups</div>
                <div class="modal-field-grid">
                    <div class="modal-field">
                        <label class="modal-field-label" for="ddPwd">PWD Count</label>
                        <input type="number" class="sm-input" id="ddPwd" min="0" placeholder="0">
                    </div>
                    <div class="modal-field">
                        <label class="modal-field-label" for="ddSenior">Senior Citizens (60+)</label>
                        <input type="number" class="sm-input" id="ddSenior" min="0" placeholder="0">
                    </div>
                    <div class="modal-field">
                        <label class="modal-field-label" for="ddPregnant">Pregnant / Lactating</label>
                        <input type="number" class="sm-input" id="ddPregnant" min="0" placeholder="0">
                    </div>
                    <div class="modal-field">
                        <label class="modal-field-label" for="ddChildren">Children Under 5</label>
                        <input type="number" class="sm-input" id="ddChildren" min="0" placeholder="0">
                    </div>
                </div>

                <!-- Section: Housing -->
                <div class="dm-form-section-title">Housing &amp; Infrastructure</div>
                <div class="modal-field-grid">
                    <div class="modal-field">
                        <label class="modal-field-label" for="ddConcrete">Concrete Houses (%)</label>
                        <input type="number" class="sm-input" id="ddConcrete" min="0" max="100" step="0.01" placeholder="0.00">
                    </div>
                    <div class="modal-field">
                        <label class="modal-field-label" for="ddLightMat">Light Materials (%)</label>
                        <input type="number" class="sm-input" id="ddLightMat" min="0" max="100" step="0.01" placeholder="0.00">
                    </div>
                    <div class="modal-field">
                        <label class="modal-field-label" for="ddDrainage">Drainage Type</label>
                        <select class="sm-select" id="ddDrainage">
                            <option value="None">None</option>
                            <option value="Open Canal">Open Canal</option>
                            <option value="Closed Drainage">Closed Drainage</option>
                            <option value="Underground">Underground</option>
                        </select>
                    </div>
                    <div class="modal-field">
                        <label class="modal-field-label" for="ddRoadSurface">Road Surface</label>
                        <select class="sm-select" id="ddRoadSurface">
                            <option value="Unpaved">Unpaved</option>
                            <option value="Gravel">Gravel</option>
                            <option value="Asphalt">Asphalt</option>
                            <option value="Concrete">Concrete</option>
                        </select>
                    </div>
                    <div class="modal-field">
                        <label class="modal-field-label" for="ddStreetWidth">Street Width (m)</label>
                        <input type="number" class="sm-input" id="ddStreetWidth" min="0" step="0.01" placeholder="0.00">
                    </div>
                </div>

                <!-- Section: Flood & Geography -->
                <div class="dm-form-section-title">Flood &amp; Geography</div>
                <div class="modal-field-grid">
                    <div class="modal-field">
                        <label class="modal-field-label" for="ddElevation">Elevation (m asl)</label>
                        <input type="number" class="sm-input" id="ddElevation" step="0.01" placeholder="0.00">
                    </div>
                    <div class="modal-field">
                        <label class="modal-field-label" for="ddDistWaterway">Dist. to Waterway (m)</label>
                        <input type="number" class="sm-input" id="ddDistWaterway" min="0" step="0.01" placeholder="0.00">
                    </div>
                    <div class="modal-field">
                        <label class="modal-field-label" for="ddFloodFreq">Flood Frequency (last 5 yrs)</label>
                        <input type="number" class="sm-input" id="ddFloodFreq" min="0" placeholder="0">
                    </div>
                    <div class="modal-field">
                        <label class="modal-field-label" for="ddFloodHeight">Avg Flood Height (m)</label>
                        <input type="number" class="sm-input" id="ddFloodHeight" min="0" step="0.01" placeholder="0.00">
                    </div>
                </div>

                <div class="sm-form-actions">
                    <button type="button" class="btn-dash-outline" id="ddCancel">Cancel</button>
                    <button type="submit" class="btn-sm-primary" id="ddSubmitBtn">
                        <i class="fa-solid fa-floppy-disk"></i> Save Demographics
                    </button>
                </div>
                <div class="sm-upload-feedback" id="ddFeedback" style="display:none;margin-top:10px;"></div>
            </form>
        </div>
    </div>
</div>

<!-- ══ CONFIRM DELETE MODAL ══════════════════════════════ -->
<div class="modal-backdrop" id="confirmModal" role="dialog" aria-modal="true" aria-labelledby="confirmModalTitle">
    <div class="modal-box" style="max-width:420px;">
        <div class="modal-header">
            <div class="modal-title-group">
                <span class="modal-eyebrow" id="confirmModalEyebrow">Confirm Action</span>
                <h3 class="modal-title" id="confirmModalTitle">Delete Record?</h3>
            </div>
            <button class="modal-close" id="confirmModalClose" aria-label="Close">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <div class="modal-body">
            <div class="confirm-icon-wrap">
                <i class="fa-solid fa-triangle-exclamation"></i>
            </div>
            <p class="confirm-message" id="confirmMessage">
                Are you sure you want to delete this record? This action cannot be undone.
            </p>
        </div>
        <div class="modal-footer" style="justify-content:flex-end;gap:10px;">
            <button class="btn-dash-outline" id="confirmCancelBtn">Cancel</button>
            <button class="btn-sm-danger" id="confirmOkBtn">
                <i class="fa-solid fa-trash"></i> Delete
            </button>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js"></script>
<script src="../js/activity-logs.js"></script>
<script src="../js/street_monitoring.js"></script>
</body>
</html>