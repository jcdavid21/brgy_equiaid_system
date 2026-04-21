<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['user_id'])){
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Not authenticated']);
    header('Location: ./login.php?session_expired=1');
}

$page_title = 'Disaster Map — Barangay EQUIAID';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Live disaster map showing flood-affected streets, evacuation centers, resident reports, and welfare deployments in Barangay Bagong Silang, Caloocan City.">
    <title><?= htmlspecialchars($page_title) ?></title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;1,9..40,300&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"
          integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA=="
          crossorigin="anonymous" referrerpolicy="no-referrer">

    <link rel="stylesheet" href="../styles/global.css">
    <link rel="stylesheet" href="../styles/navbar.css">
    <link rel="stylesheet" href="../styles/footer.css">
    <link rel="stylesheet" href="../styles/street_status.css">
    <link rel="stylesheet" href="../styles/disaster_map.css">

    <!-- Leaflet -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin=""/>
</head>
<body>

<?php include 'navbar.php'; ?>

<!-- ═══════════════════════════════════════════════════════
     TYPHOON ALERT BANNER
════════════════════════════════════════════════════════ -->
<div id="dm-alert-banner" class="typhoon-banner" role="alert" aria-live="polite" hidden>
    <div class="container">
        <div class="typhoon-banner-inner">
            <span class="typhoon-banner-pill">
                <i class="fa-solid fa-hurricane" aria-hidden="true"></i>
                ACTIVE
            </span>
            <span class="typhoon-banner-text">
                <strong id="dm-alert-name"></strong>
                <span id="dm-alert-local"></span>
                &mdash; Category <span id="dm-alert-cat"></span>
                &bull; <span id="dm-alert-kph"></span> km/h sustained winds
            </span>
            <a href="street_status.php" class="typhoon-banner-link">
                Street Status <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
            </a>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════
     PAGE HEADER
════════════════════════════════════════════════════════ -->
<header class="dm-header">
    <div class="container">
        <div class="dm-header-inner">
            <div class="dm-header-text">
                <p class="section-label">
                    <i class="fa-solid fa-map-location-dot" aria-hidden="true"></i>
                    Live Situational Awareness
                </p>
                <h1 class="section-title dm-page-title">Disaster Map</h1>
                <p class="section-desc">
                    Real-time flood extents, evacuation centers, resident reports,
                    and welfare deployments across Barangay Bagong Silang.
                </p>
            </div>
            <div class="dm-header-meta">
                <div class="dm-last-updated-wrap">
                    <i class="fa-regular fa-clock" aria-hidden="true"></i>
                    Last updated
                    <strong id="dm-last-updated" class="sk-loading sk-inline">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</strong>
                </div>
                <div class="dm-header-actions">
                    <a href="report.php" class="btn btn-primary">
                        <i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i>
                        Report Incident
                    </a>
                    <a href="street_status.php" class="btn btn-outline">
                        <i class="fa-solid fa-road" aria-hidden="true"></i>
                        Street Status
                    </a>
                </div>
            </div>
        </div>

        <!-- KPI strip -->
        <div class="dm-kpi-strip" id="dm-kpi-strip">
            <div class="dm-kpi dm-kpi--red">
                <div class="dm-kpi-icon"><i class="fa-solid fa-circle-xmark"></i></div>
                <div class="dm-kpi-body">
                    <span class="dm-kpi-val sk-loading" id="dm-kpi-red">—</span>
                    <span class="dm-kpi-lbl">Critical Streets</span>
                </div>
            </div>
            <div class="dm-kpi dm-kpi--orange">
                <div class="dm-kpi-icon"><i class="fa-solid fa-triangle-exclamation"></i></div>
                <div class="dm-kpi-body">
                    <span class="dm-kpi-val sk-loading" id="dm-kpi-orange">—</span>
                    <span class="dm-kpi-lbl">High Risk</span>
                </div>
            </div>
            <div class="dm-kpi dm-kpi--reports">
                <div class="dm-kpi-icon"><i class="fa-solid fa-flag"></i></div>
                <div class="dm-kpi-body">
                    <span class="dm-kpi-val sk-loading" id="dm-kpi-reports">—</span>
                    <span class="dm-kpi-lbl">Open Reports</span>
                </div>
            </div>
            <div class="dm-kpi dm-kpi--evac">
                <div class="dm-kpi-icon"><i class="fa-solid fa-house-medical"></i></div>
                <div class="dm-kpi-body">
                    <span class="dm-kpi-val sk-loading" id="dm-kpi-evac">—</span>
                    <span class="dm-kpi-lbl">Evac Centers</span>
                </div>
            </div>
            <div class="dm-kpi dm-kpi--affected">
                <div class="dm-kpi-icon"><i class="fa-solid fa-people-group"></i></div>
                <div class="dm-kpi-body">
                    <span class="dm-kpi-val sk-loading" id="dm-kpi-affected">—</span>
                    <span class="dm-kpi-lbl">Affected Persons</span>
                </div>
            </div>
        </div>
    </div>
</header>

<!-- ═══════════════════════════════════════════════════════
     MAP TOOLBAR
════════════════════════════════════════════════════════ -->
<div class="dm-toolbar" id="dmToolbar">
    <div class="container">
        <div class="dm-toolbar-inner">

            <!-- Layer select dropdown -->
            <div class="dm-layer-select-wrap">
                <span class="dm-toolbar-label"><i class="fa-solid fa-layer-group"></i> Layers</span>
                <div class="dm-select-box" id="dmLayerSelectBox">
                    <div class="dm-select-trigger" id="dmLayerTrigger">
                        <span id="dmLayerTriggerText">All Layers</span>
                        <i class="fa-solid fa-chevron-down dm-select-chevron"></i>
                    </div>
                    <div class="dm-select-dropdown" id="dmLayerDropdown" hidden>
                        <label class="dm-select-option">
                            <input type="checkbox" data-layer="streets" checked>
                            <i class="fa-solid fa-road"></i>
                            <span>Streets</span>
                            <span class="dm-select-check"><i class="fa-solid fa-check"></i></span>
                        </label>
                        <label class="dm-select-option">
                            <input type="checkbox" data-layer="evac" checked>
                            <i class="fa-solid fa-house-medical"></i>
                            <span>Evacuation Centers</span>
                            <span class="dm-select-check"><i class="fa-solid fa-check"></i></span>
                        </label>
                        <label class="dm-select-option">
                            <input type="checkbox" data-layer="reports" checked>
                            <i class="fa-solid fa-flag"></i>
                            <span>Resident Reports</span>
                            <span class="dm-select-check"><i class="fa-solid fa-check"></i></span>
                        </label>
                        <label class="dm-select-option">
                            <input type="checkbox" data-layer="welfare" checked>
                            <i class="fa-solid fa-hand-holding-heart"></i>
                            <span>Welfare Deployments</span>
                            <span class="dm-select-check"><i class="fa-solid fa-check"></i></span>
                        </label>
                    </div>
                </div>
            </div>

            <div class="dm-toolbar-divider"></div>

            <!-- Risk filter -->
            <div class="dm-risk-group" role="group" aria-label="Filter by risk level">
                <span class="dm-toolbar-label">Risk</span>
                <button class="dm-risk-pill active" data-risk="all">All</button>
                <button class="dm-risk-pill dm-risk-pill--red" data-risk="RED">Critical</button>
                <button class="dm-risk-pill dm-risk-pill--orange" data-risk="ORANGE">High</button>
                <button class="dm-risk-pill dm-risk-pill--yellow" data-risk="YELLOW">Moderate</button>
                <button class="dm-risk-pill dm-risk-pill--green" data-risk="GREEN">Safe</button>
            </div>

            <div class="dm-toolbar-spacer"></div>

            <!-- Locate button -->
            <button id="dmLocateBtn" class="btn btn-outline dm-locate-btn">
                <i class="fa-solid fa-location-crosshairs"></i>
                My Location
            </button>
        </div>

        <!-- Weather layer row -->
        <div class="dm-toolbar-inner dm-weather-row">
            <span class="dm-toolbar-label">Weather</span>
            <button class="dm-weather-btn" data-weather="precipitation" title="Toggle precipitation overlay">
                <i class="fa-solid fa-cloud-rain"></i> Precipitation
            </button>
            <button class="dm-weather-btn" data-weather="wind" title="Toggle wind speed overlay">
                <i class="fa-solid fa-wind"></i> Wind
            </button>
            <button class="dm-weather-btn" data-weather="clouds" title="Toggle cloud cover overlay">
                <i class="fa-solid fa-cloud"></i> Clouds
            </button>
            <button class="dm-weather-btn" data-weather="temp" title="Toggle temperature overlay">
                <i class="fa-solid fa-temperature-half"></i> Temperature
            </button>
            <span class="dm-weather-note">Powered by OpenWeatherMap</span>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════
     MAIN MAP LAYOUT
════════════════════════════════════════════════════════ -->
<section class="dm-map-section" aria-label="Disaster map">
    <div class="dm-map-layout">

        <!-- ── MAP ─────────────────────────────────────────── -->
        <div class="dm-map-wrap" id="dmMapWrap">
            <div id="dm-map" class="dm-map"></div>

            <!-- Spinner -->
            <div class="dm-map-spinner" id="dmMapSpinner">
                <i class="fa-solid fa-circle-notch fa-spin"></i>
                <span>Loading map…</span>
            </div>

            <!-- Legend (floating bottom-left) -->
            <div class="dm-map-legend" id="dmLegend">
                <div class="dm-legend-title">Legend</div>
                <div class="dm-legend-items">
                    <span class="dm-legend-item">
                        <span class="dm-legend-dot dm-legend-dot--red"></span>Critical
                    </span>
                    <span class="dm-legend-item">
                        <span class="dm-legend-dot dm-legend-dot--orange"></span>High Risk
                    </span>
                    <span class="dm-legend-item">
                        <span class="dm-legend-dot dm-legend-dot--yellow"></span>Moderate
                    </span>
                    <span class="dm-legend-item">
                        <span class="dm-legend-dot dm-legend-dot--green"></span>Safe
                    </span>
                    <span class="dm-legend-item">
                        <span class="dm-legend-dot dm-legend-dot--evac"></span>Evac Center
                    </span>
                    <span class="dm-legend-item">
                        <span class="dm-legend-dot dm-legend-dot--report"></span>Report
                    </span>
                    <span class="dm-legend-item">
                        <span class="dm-legend-dot dm-legend-dot--welfare"></span>Welfare
                    </span>
                    <span class="dm-legend-item" id="dmUserLegend" hidden>
                        <span class="dm-legend-dot dm-legend-dot--user"></span>You
                    </span>
                </div>
            </div>
        </div>

        <!-- ── SIDE PANEL ──────────────────────────────────── -->
        <aside class="dm-panel" id="dmPanel" aria-label="Map details panel">

            <!-- Default state -->
            <div class="dm-panel-default" id="dmPanelDefault">
                <div class="dm-panel-empty-icon">
                    <i class="fa-solid fa-map-location-dot"></i>
                </div>
                <h3>Select a location</h3>
                <p>Click any street marker, evacuation center, or resident report on the map to view details.</p>

                <!-- Active event summary if available -->
                <div class="dm-panel-event" id="dmPanelEvent" hidden>
                    <div class="dm-panel-event-label">Active Event</div>
                    <div class="dm-panel-event-name" id="dmPanelEventName">—</div>
                    <div class="dm-panel-event-meta" id="dmPanelEventMeta">—</div>
                </div>

                <!-- Quick stats -->
                <div class="dm-quick-stats" id="dmQuickStats">
                    <div class="dm-qs-item">
                        <i class="fa-solid fa-people-group"></i>
                        <div>
                            <strong id="dmQsPersons">—</strong>
                            <span>Affected persons</span>
                        </div>
                    </div>
                    <div class="dm-qs-item">
                        <i class="fa-solid fa-house-crack"></i>
                        <div>
                            <strong id="dmQsHH">—</strong>
                            <span>Affected households</span>
                        </div>
                    </div>
                    <div class="dm-qs-item">
                        <i class="fa-solid fa-hand-holding-heart"></i>
                        <div>
                            <strong id="dmQsWelfare">—</strong>
                            <span>Welfare plans active</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Detail state (populated by JS on click) -->
            <div class="dm-panel-detail" id="dmPanelDetail" hidden>
                <button class="dm-panel-back" id="dmPanelBack">
                    <i class="fa-solid fa-arrow-left"></i> Back
                </button>
                <div id="dmPanelContent"></div>
            </div>

        </aside>
    </div>
</section>

<!-- ═══════════════════════════════════════════════════════
     RECENT REPORTS STRIP
════════════════════════════════════════════════════════ -->
<section class="dm-reports-section" aria-label="Recent reports">
    <div class="container">
        <div class="dm-section-head">
            <div>
                <p class="section-label">
                    <i class="fa-solid fa-flag"></i>
                    Community Reports
                </p>
                <h2 class="section-title" style="margin-bottom:0">Recent Flood Reports</h2>
            </div>
            <a href="report.php" class="btn btn-outline">
                <i class="fa-solid fa-plus"></i> Submit Report
            </a>
        </div>

        <div class="dm-reports-grid" id="dmReportsGrid">
            <!-- Skeleton -->
            <?php for ($i = 0; $i < 3; $i++): ?>
            <div class="dm-report-card dm-report-card--skeleton">
                <div class="sk-loading" style="height:14px;width:40%;border-radius:4px;margin-bottom:10px"></div>
                <div class="sk-loading" style="height:18px;width:70%;border-radius:4px;margin-bottom:8px"></div>
                <div class="sk-loading" style="height:13px;width:90%;border-radius:4px"></div>
            </div>
            <?php endfor; ?>
        </div>
    </div>
</section>

<?php include 'footer.php'; ?>

<!-- Leaflet -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
<script src="../js/navbar.js"></script>
<script src="../js/disaster_map.js"></script>

</body>
</html>