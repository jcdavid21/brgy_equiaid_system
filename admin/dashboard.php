<?php

$current_page = 'dashboard';
$page_title   = 'Admin Dashboard — Barangay EQUIAID';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin' || $_SESSION["user_role"] == "superadmin") {
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

    <!-- Leaflet CSS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css">

    <!-- Admin styles -->
    <link rel="stylesheet" href="../styles/admin_sidebar.css">
    <link rel="stylesheet" href="../styles/admin_dashboard.css">
</head>
<body>

<div class="admin-shell">

    <!-- ══ SIDEBAR ══════════════════════════════════════ -->
    <?php include 'sidebar.php'; ?>

    <!-- ══ MAIN CONTENT ════════════════════════════════ -->
    <main class="main-content" id="mainContent">
        <div class="dashboard-container">

            <!-- SECTION 1: HEADER -->
            <div class="dash-header">
                <div class="dash-header-left">
                    <div class="dash-breadcrumb">
                        <i class="fa-solid fa-gauge-high"></i>
                        <span>Admin Dashboard</span>
                    </div>
                    <h1 class="dash-title">Dashboard</h1>
                    <p class="dash-desc">
                        Overview of barangay disaster monitoring, vulnerability prediction,
                        and welfare resource allocation.
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

            <!-- SECTION 2: KPI CARDS -->
            <div class="overview-grid" id="overviewGrid">
                <div class="kpi-card">
                    <div class="kpi-icon-wrap neutral"><i class="fa-solid fa-road"></i></div>
                    <div class="kpi-body">
                        <span class="kpi-label">Total Streets</span>
                        <span class="kpi-number sk-inline" id="kpi-total-streets">—</span>
                        <span class="kpi-sub">Monitored in the barangay</span>
                    </div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-icon-wrap dark"><i class="fa-solid fa-triangle-exclamation"></i></div>
                    <div class="kpi-body">
                        <span class="kpi-label">Affected Streets</span>
                        <span class="kpi-number sk-inline" id="kpi-affected">—</span>
                        <span class="kpi-sub">RED &amp; ORANGE risk levels</span>
                    </div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-icon-wrap mid"><i class="fa-solid fa-hand-holding-heart"></i></div>
                    <div class="kpi-body">
                        <span class="kpi-label">Need Welfare</span>
                        <span class="kpi-number sk-inline" id="kpi-welfare">—</span>
                        <span class="kpi-sub">Streets requiring assistance</span>
                    </div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-icon-wrap neutral"><i class="fa-solid fa-boxes-stacked"></i></div>
                    <div class="kpi-body">
                        <span class="kpi-label">Available Resources</span>
                        <span class="kpi-number sk-inline" id="kpi-resources">—</span>
                        <span class="kpi-sub">Active resource types</span>
                    </div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-icon-wrap mid"><i class="fa-solid fa-flag"></i></div>
                    <div class="kpi-body">
                        <span class="kpi-label">Resident Reports</span>
                        <span class="kpi-number sk-inline" id="kpi-reports">—</span>
                        <span class="kpi-sub">Pending &amp; verified reports</span>
                    </div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-icon-wrap neutral"><i class="fa-solid fa-building-columns"></i></div>
                    <div class="kpi-body">
                        <span class="kpi-label">Evacuation Centers</span>
                        <span class="kpi-number sk-inline" id="kpi-evac">—</span>
                        <span class="kpi-sub">Active and ready centers</span>
                    </div>
                </div>
            </div>

            <!-- SECTIONS 3 + 4: RISK DIST + AI SUMMARY -->
            <div class="mid-row">

                <!-- SECTION 3: Risk Distribution -->
                <div class="dash-card">
                    <div class="card-head">
                        <div>
                            <div class="card-label">Classification</div>
                            <h2 class="card-title">Street Risk Distribution</h2>
                        </div>
                    </div>
                    <div class="risk-chart-wrap" id="riskChartWrap">
                        <div class="sk-bar-row"></div>
                        <div class="sk-bar-row"></div>
                        <div class="sk-bar-row"></div>
                        <div class="sk-bar-row"></div>
                    </div>
                    <div class="risk-summary-row" id="riskSummaryRow">
                        <div class="risk-summary-item"><span class="rs-num sk-inline">&nbsp;</span><span class="rs-label">Critical</span></div>
                        <div class="risk-summary-item"><span class="rs-num sk-inline">&nbsp;</span><span class="rs-label">High Risk</span></div>
                        <div class="risk-summary-item"><span class="rs-num sk-inline">&nbsp;</span><span class="rs-label">Moderate</span></div>
                        <div class="risk-summary-item"><span class="rs-num sk-inline">&nbsp;</span><span class="rs-label">Safe</span></div>
                    </div>
                </div>

                <!-- SECTION 4: AI Prediction Summary -->
                <div class="dash-card ai-card">
                    <div class="card-head">
                        <div>
                            <div class="card-label">ResNet-50 Analysis</div>
                            <h2 class="card-title">AI Prediction Summary</h2>
                        </div>
                        <span class="ai-badge"><i class="fa-solid fa-microchip"></i> AI</span>
                    </div>
                    <div class="ai-stat-grid">
                        <div class="ai-stat-main">
                            <span class="ai-big-num sk-inline" id="ai-analyzed">—</span>
                            <span class="ai-big-label">Streets Analyzed</span>
                        </div>
                        <div class="ai-stat-sub">
                            <div class="ai-sub-row">
                                <span class="ai-sub-dot red"></span>
                                <span class="ai-sub-label">High Risk</span>
                                <span class="ai-sub-val sk-inline" id="ai-high">—</span>
                            </div>
                            <div class="ai-sub-row">
                                <span class="ai-sub-dot orange"></span>
                                <span class="ai-sub-label">Moderate Risk</span>
                                <span class="ai-sub-val sk-inline" id="ai-moderate">—</span>
                            </div>
                            <div class="ai-sub-row">
                                <span class="ai-sub-dot light"></span>
                                <span class="ai-sub-label">Safe</span>
                                <span class="ai-sub-val sk-inline" id="ai-safe">—</span>
                            </div>
                        </div>
                    </div>
                    <div class="ai-confidence">
                        <div class="ai-conf-label">
                            <span>Avg. Model Confidence</span>
                            <span class="ai-conf-pct" id="ai-conf-pct">—%</span>
                        </div>
                        <div class="conf-track">
                            <div class="conf-fill" id="confFill" style="--conf:0%"></div>
                        </div>
                    </div>
                    <div class="impact-block">
                        <div class="card-label" style="margin-bottom:12px;">Action Plan Impact</div>
                        <div class="impact-compare">
                            <div class="impact-item">
                                <span class="impact-label">Before Assistance</span>
                                <span class="impact-pct before sk-inline" id="impact-before">—%</span>
                                <div class="impact-track">
                                    <div class="impact-fill dark-fill" id="impactBarBefore" style="--w:0%"></div>
                                </div>
                                <span class="impact-desc">Streets affected</span>
                            </div>
                            <div class="impact-arrow"><i class="fa-solid fa-arrow-right"></i></div>
                            <div class="impact-item">
                                <span class="impact-label">After Assistance</span>
                                <span class="impact-pct after sk-inline" id="impact-after">—%</span>
                                <div class="impact-track">
                                    <div class="impact-fill light-fill" id="impactBarAfter" style="--w:0%"></div>
                                </div>
                                <span class="impact-desc">Streets affected</span>
                            </div>
                        </div>
                        <div class="impact-delta" id="impactDelta">
                            <i class="fa-solid fa-arrow-trend-down"></i>
                            <span id="impactDeltaText">Calculating&hellip;</span>
                        </div>
                    </div>
                </div>

            </div><!-- /.mid-row -->

            <!-- SECTION 5: MAP — full width -->
            <div class="dash-card map-card">
                <div class="card-head">
                    <div>
                        <div class="card-label">Affected Areas — Bagong Silang</div>
                        <h2 class="card-title">Live Map Preview</h2>
                    </div>
                    <a href="map-monitoring.php" class="btn-dash-outline">
                        <i class="fa-solid fa-expand"></i> Full Map
                    </a>
                </div>
                <div class="dashboard-map-wrap">
                    <div class="map-loading-overlay" id="mapLoadingOverlay">
                        <div class="map-spinner"></div>
                        <span>Loading map&hellip;</span>
                    </div>
                    <div id="dashboard-map"></div>
                </div>
                <div class="map-legend-row" id="mapLegendRow">
                    <div class="map-legend-item">
                        <span class="map-legend-dot" style="background:#b91c1c;"></span>
                        <span>Critical</span>
                        <span class="map-legend-count" id="leg-red">—</span>
                    </div>
                    <div class="map-legend-item">
                        <span class="map-legend-dot" style="background:#d97706;"></span>
                        <span>High Risk</span>
                        <span class="map-legend-count" id="leg-orange">—</span>
                    </div>
                    <div class="map-legend-item">
                        <span class="map-legend-dot" style="background:#ca8a04;"></span>
                        <span>Moderate</span>
                        <span class="map-legend-count" id="leg-yellow">—</span>
                    </div>
                    <div class="map-legend-item">
                        <span class="map-legend-dot" style="background:#16a34a;"></span>
                        <span>Safe</span>
                        <span class="map-legend-count" id="leg-green">—</span>
                    </div>
                    <div class="map-legend-item">
                        <span class="map-legend-dot" style="background:#1d4ed8;border-radius:3px;"></span>
                        <span>Evacuation</span>
                        <span class="map-legend-count" id="leg-evac">—</span>
                    </div>
                </div>
            </div>

            <!-- SECTION 6: MOST VULNERABLE STREETS -->
            <div class="dash-card">
                <div class="card-head">
                    <div>
                        <div class="card-label">Priority List</div>
                        <h2 class="card-title">Most Vulnerable Streets</h2>
                    </div>
                    <a href="street-monitoring.php" class="btn-dash-outline">View All</a>
                </div>
                <div class="table-wrap">
                    <table class="dashboard-table" id="vulnerableTable">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Street Name</th>
                                <th>Zone</th>
                                <th>Vuln. Score</th>
                                <th>Risk Level</th>
                            </tr>
                        </thead>
                        <tbody id="vulnerableTbody">
                            <tr><td colspan="5" class="tbl-loading">
                                <span class="sk-inline">Loading streets&hellip;</span>
                            </td></tr>
                        </tbody>
                    </table>
                </div>
                <div class="table-footer" id="vulnerableFooter" style="display:none;">
                    <span class="table-info" id="vulnerableInfo"></span>
                    <div class="pagination" id="vulnerablePagination"></div>
                </div>
            </div>

            <!-- SECTION 7: RECENT RESIDENT REPORTS -->
            <div class="dash-card">
                <div class="card-head">
                    <div>
                        <div class="card-label">Community Submissions</div>
                        <h2 class="card-title">Recent Resident Reports</h2>
                    </div>
                    <a href="resident-reports.php" class="btn-dash-outline">View All</a>
                </div>
                <div class="table-wrap">
                    <table class="dashboard-table">
                        <thead>
                            <tr>
                                <th>Resident</th>
                                <th>Street</th>
                                <th>Report Type</th>
                                <th>Date Submitted</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody id="reportsTbody">
                            <tr><td colspan="6" class="tbl-loading">
                                <span class="sk-inline">Loading reports&hellip;</span>
                            </td></tr>
                        </tbody>
                    </table>
                </div>
                <div class="table-footer" id="reportsFooter" style="display:none;">
                    <span class="table-info" id="reportsInfo"></span>
                    <div class="pagination" id="reportsPagination"></div>
                </div>
            </div>

            <!-- SECTIONS 8 + 9: WELFARE + DISASTERS -->
            <div class="two-col-row">

                <!-- SECTION 8: Recent Welfare Actions -->
                <div class="dash-card">
                    <div class="card-head">
                        <div>
                            <div class="card-label">DSWD / Social Welfare</div>
                            <h2 class="card-title">Recent Welfare Actions</h2>
                        </div>
                        <a href="welfare-action.php" class="btn-dash-outline">View All</a>
                    </div>
                    <div class="table-wrap">
                        <table class="dashboard-table">
                            <thead>
                                <tr>
                                    <th>Street</th>
                                    <th>Assistance</th>
                                    <th>Resources</th>
                                    <th>Date</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody id="welfareTbody">
                                <tr><td colspan="5" class="tbl-loading">
                                    <span class="sk-inline">Loading welfare actions&hellip;</span>
                                </td></tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="table-footer" id="welfareFooter" style="display:none;">
                        <span class="table-info" id="welfareInfo"></span>
                        <div class="pagination" id="welfarePagination"></div>
                    </div>
                </div>

                <!-- SECTION 9: Disaster Monitoring -->
                <div class="dash-card">
                    <div class="card-head">
                        <div>
                            <div class="card-label">Typhoon Impact Monitoring</div>
                            <h2 class="card-title">Disaster Monitoring</h2>
                        </div>
                        <a href="typhoon-impact.php" class="btn-dash-outline">View All</a>
                    </div>
                    <div class="table-wrap">
                        <table class="dashboard-table">
                            <thead>
                                <tr>
                                    <th>Street</th>
                                    <th>Disaster Type</th>
                                    <th>Severity</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody id="disastersTbody">
                                <tr><td colspan="4" class="tbl-loading">
                                    <span class="sk-inline">Loading disasters&hellip;</span>
                                </td></tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="table-footer" id="disastersFooter" style="display:none;">
                        <span class="table-info" id="disastersInfo"></span>
                        <div class="pagination" id="disastersPagination"></div>
                    </div>
                </div>

            </div><!-- /.two-col-row -->

        </div><!-- /.dashboard-container -->
    </main>

</div><!-- /.admin-shell -->

<!-- ═══ ROW DETAIL MODAL ══════════════════════════════ -->
<div class="modal-backdrop" id="rowModal" role="dialog" aria-modal="true" aria-labelledby="modalTitle">
    <div class="modal-box">
        <div class="modal-header">
            <div class="modal-title-group">
                <span class="modal-eyebrow" id="modalEyebrow">Details</span>
                <h3 class="modal-title" id="modalTitle">—</h3>
            </div>
            <button class="modal-close" id="modalClose" aria-label="Close modal">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <div class="modal-body" id="modalBody">
            <!-- Content injected by JS -->
        </div>
    </div>
</div>

<!-- Leaflet JS -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js"></script>
<script src="../js/admin_dashboard.js"></script>
</body>
</html>