<?php

$current_page = 'typhoon-impact';
$page_title   = 'Typhoon Impact — Barangay EQUIAID';

if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}
if (empty($_SESSION['typhoon_csrf'])) $_SESSION['typhoon_csrf'] = bin2hex(random_bytes(32));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="typhoon-csrf" content="<?= htmlspecialchars($_SESSION['typhoon_csrf'], ENT_QUOTES) ?>">
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
    <link rel="stylesheet" href="../styles/admin-typhoon-impact.css">
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
                        <i class="fa-solid fa-hurricane"></i>
                        <span>Typhoon Impact</span>
                    </div>
                    <h1 class="dash-title">Typhoon Impact Map</h1>
                    <p class="dash-desc">
                        Visualize affected streets, flood levels, and damage assessments
                        for each typhoon event in Barangay Bagong Silang.
                    </p>
                </div>
                <div class="dash-header-right">
                    <div class="ti-admin-actions" style="display:flex;gap:8px;align-items:center;margin-right:12px;">
                        <button class="btn-dash-outline" id="btnAddEvent" title="Import an official PAGASA report">
                            <i class="fa-solid fa-file-arrow-up"></i> Import PAGASA Data
                        </button>

                        <button class="btn-dash-outline" id="btnDemoImpactImport" title="Import street impact assessments from CSV">
                            <i class="fa-solid fa-file-csv"></i> Import Impact CSV
                        </button>

                        <button class="btn-dash-outline" id="btnPagasaHistory" title="View immutable PAGASA source revisions">
                            <i class="fa-solid fa-clock-rotate-left"></i> Revisions
                        </button>

                    </div>
                    <div class="ti-event-selector-wrap">
                        <label class="ti-event-label" for="tiEventSelect">
                            <i class="fa-solid fa-hurricane"></i> Select Typhoon Event
                        </label>
                        <select class="ti-event-select" id="tiEventSelect">
                            <option value="">Loading events…</option>
                        </select>
                    </div>
                    <div class="live-pill" id="tiStatusPill" style="display:none;">
                        <span class="live-dot"></span>
                        <span id="tiStatusText">Active</span>
                    </div>
                </div>
            </div>

            <!-- ── TYPHOON INFO BANNER ─────────────────── -->
            <div class="ti-event-banner" id="tiEventBanner" style="display:none;">
                <div class="ti-banner-icon">
                    <i class="fa-solid fa-hurricane"></i>
                </div>
                <div class="ti-banner-body">
                    <div class="ti-banner-name" id="tiBannerName">—</div>
                    <div class="ti-banner-meta" id="tiBannerMeta">—</div>
                </div>
                <div class="ti-banner-stats" id="tiBannerStats"></div>
            </div>
            <div id="tiImpactDataNotice" class="ti-import-notice" style="display:none;margin-bottom:16px;" role="status"></div>

            <!-- ── KPI STRIP ───────────────────────────── -->
            <div class="overview-grid ti-kpi-grid" id="tiKpiGrid">
                <div class="kpi-card">
                    <div class="kpi-icon-wrap" style="background:#b91c1c;">
                        <i class="fa-solid fa-road" style="color:#fff;font-size:14px;"></i>
                    </div>
                    <div class="kpi-body">
                        <span class="kpi-label">Impacted Streets</span>
                        <span class="kpi-number" id="kpi-ti-streets">0</span>
                        <span class="kpi-sub">Streets with recorded impact</span>
                    </div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-icon-wrap" style="background:#0369a1;">
                        <i class="fa-solid fa-house-flood-water" style="color:#fff;font-size:13px;"></i>
                    </div>
                    <div class="kpi-body">
                        <span class="kpi-label">Flooded Streets</span>
                        <span class="kpi-number" id="kpi-ti-flooded" style="color:#0369a1;">0</span>
                        <span class="kpi-sub">Including severe floods</span>
                    </div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-icon-wrap" style="background:#7c3aed;">
                        <i class="fa-solid fa-house-chimney-crack" style="color:#fff;font-size:13px;"></i>
                    </div>
                    <div class="kpi-body">
                        <span class="kpi-label">Damaged Streets</span>
                        <span class="kpi-number" id="kpi-ti-damaged" style="color:#7c3aed;">0</span>
                        <span class="kpi-sub">Moderate to severe damage</span>
                    </div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-icon-wrap" style="background:#d97706;">
                        <i class="fa-solid fa-people-group" style="color:#fff;font-size:13px;"></i>
                    </div>
                    <div class="kpi-body">
                        <span class="kpi-label">Affected Households</span>
                        <span class="kpi-number" id="kpi-ti-households" style="color:#d97706;">0</span>
                        <span class="kpi-sub">Households impacted</span>
                    </div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-icon-wrap" style="background:#dc2626;">
                        <i class="fa-solid fa-person-circle-exclamation" style="color:#fff;font-size:13px;"></i>
                    </div>
                    <div class="kpi-body">
                        <span class="kpi-label">Affected Persons</span>
                        <span class="kpi-number" id="kpi-ti-persons" style="color:#dc2626;">0</span>
                        <span class="kpi-sub">Total persons affected</span>
                    </div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-icon-wrap" style="background:#b45309;">
                        <i class="fa-solid fa-road-barrier" style="color:#fff;font-size:13px;"></i>
                    </div>
                    <div class="kpi-body">
                        <span class="kpi-label">Inaccessible Roads</span>
                        <span class="kpi-number" id="kpi-ti-roads" style="color:#b45309;">0</span>
                        <span class="kpi-sub">Roads blocked / flooded</span>
                    </div>
                </div>
            </div>

            <!-- ── MAP + IMPACT BREAKDOWN ──────────────── -->
            <div class="mid-row">

                <!-- MAP CARD -->
                <div class="dash-card map-card" style="flex:1.5;">
                    <div class="card-head">
                        <div>
                            <div class="card-label">Interactive Map</div>
                            <h2 class="card-title">Affected Streets Map</h2>
                        </div>
                        <div class="sm-map-controls">
                            <button class="ti-layer-btn active" id="btnLayerImpact">
                                <i class="fa-solid fa-layer-group"></i> Impact
                            </button>
                            <button class="ti-layer-btn" id="btnLayerRisk">
                                <i class="fa-solid fa-triangle-exclamation"></i> Risk
                            </button>
                            <button class="ti-layer-btn" id="btnLayerWelfare">
                                <i class="fa-solid fa-hand-holding-heart"></i> Welfare
                            </button>
                        </div>
                    </div>
                    <div class="dashboard-map-wrap" style="margin:0 -28px;">
                        <div id="ti-map" style="height:460px;width:100%;z-index:1;"></div>
                        <div class="map-loading-overlay" id="tiMapOverlay">
                            <div class="map-spinner"></div>
                            <span>Loading map…</span>
                        </div>
                    </div>
                    <div class="map-legend-row" id="tiMapLegend">
                        <!-- Legend populated by JS based on active layer -->
                    </div>
                </div>

                <!-- IMPACT BREAKDOWN -->
                <div class="dash-card" style="display:flex;flex-direction:column;min-width:260px;">
                    <div class="card-head">
                        <div>
                            <div class="card-label">Breakdown</div>
                            <h2 class="card-title">Impact Levels</h2>
                        </div>
                    </div>

                    <!-- Impact distribution bars -->
                    <div class="risk-chart-wrap" id="tiImpactBars">
                        <div class="sk-bar-row"></div>
                        <div class="sk-bar-row"></div>
                        <div class="sk-bar-row"></div>
                        <div class="sk-bar-row"></div>
                    </div>

                    <div style="margin-top:16px;padding-top:16px;border-top:1px solid var(--border-light);">
                        <div class="card-label" style="margin-bottom:12px;">Risk Level of Affected Streets</div>
                        <div id="tiRiskBreakdownBars" class="risk-chart-wrap"></div>
                    </div>

                    <!-- Welfare status summary -->
                    <div style="margin-top:16px;padding-top:16px;border-top:1px solid var(--border-light);">
                        <div class="card-label" style="margin-bottom:12px;">Response Status</div>
                        <div id="tiWelfareStatus" class="ti-status-pills"></div>
                    </div>

                    <!-- Budget summary -->
                    <div style="margin-top:16px;padding-top:16px;border-top:1px solid var(--border-light);margin-top:auto;">
                        <div class="card-label" style="margin-bottom:10px;">Budget Overview</div>
                        <div class="ti-budget-row">
                            <div class="ti-budget-item">
                                <span class="ti-budget-label">Recommended</span>
                                <span class="ti-budget-val" id="tiBudgetRec">₱0</span>
                            </div>
                            <div class="ti-budget-item">
                                <span class="ti-budget-label">Approved</span>
                                <span class="ti-budget-val" id="tiBudgetApproved">₱0</span>
                            </div>
                            <div class="ti-budget-item">
                                <span class="ti-budget-label">Spent</span>
                                <span class="ti-budget-val" id="tiBudgetSpent">₱0</span>
                            </div>
                        </div>
                    </div>
                </div>

            </div><!-- /.mid-row -->

            <!-- ── IMPACT TABLE ────────────────────────── -->
            <div class="dash-card">
                <div class="card-head">
                    <div>
                        <div class="card-label">Street-Level Records</div>
                        <h2 class="card-title">Typhoon Impact by Street</h2>
                    </div>
                    <div class="sm-head-actions">
                        <button class="btn-dash-outline" id="tiExportBtn">
                            <i class="fa-solid fa-file-export"></i> Export CSV
                        </button>
                    </div>
                </div>

                <!-- FILTER BAR -->
                <div class="sm-filter-bar">
                    <div class="sm-search-wrap">
                        <i class="fa-solid fa-magnifying-glass sm-search-icon"></i>
                        <input type="text" class="sm-search-input" id="tiSearch"
                               placeholder="Search street name or zone…" autocomplete="off">
                        <button class="sm-search-clear" id="tiSearchClear" style="display:none;" aria-label="Clear">
                            <i class="fa-solid fa-xmark"></i>
                        </button>
                    </div>
                    <div class="sm-filter-chips">
                        <span class="sm-filter-label"><i class="fa-solid fa-layer-group"></i> Impact:</span>
                        <button class="sm-chip active" data-filter="impact" data-val="">All</button>
                        <button class="sm-chip" data-filter="impact" data-val="Severe">
                            <span class="sm-chip-dot" style="background:#dc2626;"></span>Severe
                        </button>
                        <button class="sm-chip" data-filter="impact" data-val="High">
                            <span class="sm-chip-dot" style="background:#d97706;"></span>High
                        </button>
                        <button class="sm-chip" data-filter="impact" data-val="Moderate">
                            <span class="sm-chip-dot" style="background:#ca8a04;"></span>Moderate
                        </button>
                        <button class="sm-chip" data-filter="impact" data-val="None">
                            <span class="sm-chip-dot" style="background:#16a34a;"></span>None
                        </button>
                    </div>
                    <div class="sm-filter-chips">
                        <span class="sm-filter-label"><i class="fa-solid fa-hand-holding-heart"></i> Status:</span>
                        <button class="sm-chip active" data-filter="status" data-val="">All</button>
                        <button class="sm-chip" data-filter="status" data-val="Needs Help">
                            <span class="sm-chip-dot" style="background:#dc2626;"></span>Needs Help
                        </button>
                        <button class="sm-chip" data-filter="status" data-val="Ongoing Assistance">
                            <span class="sm-chip-dot" style="background:#d97706;"></span>Ongoing
                        </button>
                        <button class="sm-chip" data-filter="status" data-val="Resolved">
                            <span class="sm-chip-dot" style="background:#16a34a;"></span>Resolved
                        </button>
                    </div>
                    <div class="sm-filter-chips">
                        <span class="sm-filter-label"><i class="fa-solid fa-map-pin"></i> Zone:</span>
                        <select class="sm-select-inline" id="tiZoneFilter">
                            <option value="">All Zones</option>
                        </select>
                    </div>
                </div>

                <div class="table-wrap" style="margin-top:16px;">
                    <table class="dashboard-table">
                        <thead>
                            <tr>
                                <th style="width:40px;">#</th>
                                <th>Street / Zone</th>
                                <th>Impact Level</th>
                                <th>Flood Status</th>
                                <th>Damage</th>
                                <th>Flood Height</th>
                                <th>Affected HH</th>
                                <th>Affected Persons</th>
                                <th>Road Access</th>
                                <th>Response Status</th>
                                <th style="width:100px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="tiTableTbody">
                            <tr><td colspan="11" class="tbl-loading">
                                <span class="sk-inline">Select a typhoon event to load data…</span>
                            </td></tr>
                        </tbody>
                    </table>
                </div>
                <div class="table-footer" id="tiTableFooter" style="display:none;">
                    <span class="table-info" id="tiTableInfo"></span>
                    <div class="pagination" id="tiTablePagination"></div>
                </div>
            </div>

        </div><!-- /.dashboard-container -->
    </main>
</div><!-- /.admin-shell -->

<!-- ══ IMPORT PAGASA REPORT MODAL ════════════════════════ -->
<div class="modal-backdrop" id="tiAddEventModal" role="dialog" aria-modal="true">
    <div class="modal-box" style="max-width:720px;">
        <div class="modal-header">
            <div class="modal-title-group">
                <span class="modal-eyebrow" id="tiImportEyebrow">Official source import</span>
                <h3 class="modal-title" id="tiImportTitle">Import PAGASA Data</h3>
            </div>
            <button class="modal-close" id="tiAddEventModalClose" aria-label="Close">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <div class="modal-body" style="padding:24px;">
            <div id="tiAddEventMsg" style="display:none;margin-bottom:12px;padding:10px 14px;border-radius:6px;font-size:13px;"></div>
            <div class="ti-import-notice">
                <i class="fa-solid fa-shield-halved"></i>
                <div id="tiImportNotice"><strong>Read-only source data</strong><br>CSV records must match the reviewed PAGASA dataset. Importing weather details does not create street-impact assessments.</div>
            </div>
            <div style="margin-top:16px;">
                <label class="ti-form-label" for="pagasaImportFormat">Import format</label>
                <select id="pagasaImportFormat" class="ti-form-input">
                    <option value="csv">CSV — bulk typhoon records</option>
                    <option value="pdf">PDF — one reviewed PAGASA report</option>
                    <option value="impact">Street Impact CSV — street assessments</option>
                </select>
            </div>
            <div id="pagasaCsvFields" style="margin-top:16px;">
                <label class="ti-form-label" id="tiCsvFileLabel" for="pagasaCsvFile">PAGASA dataset (CSV) *</label>
                <input type="file" id="pagasaCsvFile" class="ti-form-input" accept=".csv,text/csv">
                <small class="ti-form-help" id="tiCsvHelp">Maximum 2 MB. Upload the full dataset or a subset with the same columns and unchanged values.</small>
                <a class="ti-sample-download" id="tiCsvDownload" href="../backend/admin-typhoon-impact.php?action=download_pagasa_csv_sample"><i class="fa-solid fa-download"></i> Download sample CSV format</a>
                <small class="ti-form-help" id="tiCsvDownloadHelp">Column headers and one example record only, for reference.</small>
            </div>
            <div id="pagasaPdfFields" style="display:none;margin-top:16px;">
                <label class="ti-form-label" for="pagasaPdfFile">Official PAGASA Preliminary Report (PDF) *</label>
                <input type="file" id="pagasaPdfFile" class="ti-form-input" accept="application/pdf,.pdf">
                <small class="ti-form-help">Maximum 15 MB. Use an original PDF referenced by the provided CSV dataset. The uploaded file must match its reviewed source hash.</small>
            </div>
            <div id="pagasaSourceFields" style="display:none;margin-top:14px;">
                <label class="ti-form-label" for="pagasaSourceUrl">PAGASA source URL</label>
                <input type="url" id="pagasaSourceUrl" class="ti-form-input" placeholder="https://pubfiles.pagasa.dost.gov.ph/...pdf">
                <small class="ti-form-help">Only official pagasa.dost.gov.ph links are accepted.</small>
            </div>
            <div id="pagasaImportProgress" class="ti-import-progress" style="display:none;">
                <i class="fa-solid fa-circle-notch fa-spin"></i> Validating source data…
            </div>
            <div id="pagasaCsvPreview" class="ti-csv-preview" style="display:none;" aria-live="polite"></div>
            <div id="pagasaImportPreview" class="ti-import-preview" style="display:none;">
                <div class="ti-import-preview-head">
                    <div>
                        <span class="modal-eyebrow">Extracted preview</span>
                        <h4 id="pagasaPreviewTitle">—</h4>
                    </div>
                    <span class="ti-source-badge"><i class="fa-solid fa-lock"></i> Read only</span>
                </div>
                <div class="ti-import-preview-grid" id="pagasaPreviewGrid"></div>
            </div>
            <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:20px;">
                <button class="btn-dash-outline" id="tiAddEventCancel">Cancel</button>
                <button class="btn-dash-primary" id="tiAnalyzePagasaReport">
                    <i class="fa-solid fa-magnifying-glass"></i> Preview CSV
                </button>
                <button class="btn-dash-primary" id="tiAddEventSubmit" style="display:none;">
                    <i class="fa-solid fa-file-import"></i> Confirm Import
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ══ PAGASA REVISION HISTORY MODAL ════════════════════ -->
<div class="modal-backdrop" id="tiPagasaHistoryModal" role="dialog" aria-modal="true">
    <div class="modal-box" style="max-width:760px;">
        <div class="modal-header">
            <div class="modal-title-group">
                <span class="modal-eyebrow">Immutable audit trail</span>
                <h3 class="modal-title">PAGASA Report Revisions</h3>
            </div>
            <button class="modal-close" id="tiPagasaHistoryClose" aria-label="Close">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <div class="modal-body" style="padding:24px;max-height:70vh;overflow:auto;">
            <div id="tiPagasaHistoryBody" class="ti-revision-list">Select an imported typhoon event.</div>
            <div style="display:flex;justify-content:flex-end;margin-top:20px;">
                <button class="btn-dash-outline" id="tiPagasaHistoryDone">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- ══ STREET IMPACT DETAIL MODAL ══════════════════════ -->
<div class="modal-backdrop" id="tiDetailModal" role="dialog" aria-modal="true">
    <div class="modal-box" style="max-width:680px;">
        <div class="modal-header">
            <div class="modal-title-group">
                <span class="modal-eyebrow" id="tiModalEyebrow">Street Impact Details</span>
                <h3 class="modal-title" id="tiModalTitle">—</h3>
            </div>
            <button class="modal-close" id="tiModalClose" aria-label="Close">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <div class="modal-body" id="tiModalBody" style="max-height:70vh;overflow-y:auto;"></div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"
        crossorigin="anonymous" referrerpolicy="no-referrer"></script>
<script src="../js/admin-typhoon-impact.js"></script>
</body>
</html>
