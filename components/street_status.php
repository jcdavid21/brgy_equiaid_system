<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$page_title = 'Street Status — Barangay EQUIAID';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Current flood risk levels and welfare needs for all monitored streets in Barangay Bagong Silang, Caloocan City.">
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

    <!-- Leaflet.js -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin=""/>
</head>
<body>

<?php include 'navbar.php'; ?>

<!-- ═══════════════════════════════════════════════════════
     ACTIVE TYPHOON ALERT BANNER
     Hidden by default — shown by loadSummary() when active
════════════════════════════════════════════════════════ -->
<div id="ss-alert-banner" class="typhoon-banner" role="alert" aria-live="polite" hidden>
    <div class="container">
        <div class="typhoon-banner-inner">
            <span class="typhoon-banner-pill">
                <i class="fa-solid fa-hurricane" aria-hidden="true"></i>
                ACTIVE
            </span>
            <span class="typhoon-banner-text">
                <strong id="ss-alert-name"></strong>
                <span id="ss-alert-local"></span>
                &mdash; Category <span id="ss-alert-cat"></span>
                &bull; <span id="ss-alert-kph"></span> km/h sustained winds
            </span>
            <a href="map.php" class="typhoon-banner-link">
                View Live Map <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
            </a>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════
     PAGE HEADER
════════════════════════════════════════════════════════ -->
<header class="ss-header">
    <div class="container">

        <div class="ss-header-inner">
            <div class="ss-header-text">
                <p class="section-label">
                    <i class="fa-solid fa-road" aria-hidden="true"></i>
                    Real-time Monitoring
                </p>
                <h1 class="section-title ss-page-title">Street Status</h1>
                <p class="section-desc">
                    Current flood risk levels and welfare needs for all monitored
                    streets in Barangay Bagong Silang, Caloocan City.
                </p>
            </div>

            <div class="ss-header-meta">
                <div class="ss-last-updated-wrap">
                    <i class="fa-regular fa-clock" aria-hidden="true"></i>
                    Last updated
                    <strong id="ss-last-updated" class="sk-loading sk-inline">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</strong>
                </div>
                <a href="report.php" class="btn btn-primary">
                    <i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i>
                    Report Incident
                </a>
            </div>
        </div>

        <!-- KPI summary strip -->
        <div class="ss-summary-strip" id="ss-kpi-strip" aria-label="Risk level summary">

            <?php
            $kpis = [
                'red'    => ['Critical', 'fa-circle-xmark'],
                'orange' => ['High Risk','fa-triangle-exclamation'],
                'yellow' => ['Moderate', 'fa-circle-minus'],
                'green'  => ['Safe',     'fa-circle-check'],
            ];
            foreach ($kpis as $lvl => [$lbl, $ico]):
            ?>
            <div class="ss-kpi ss-kpi--<?= $lvl ?>">
                <div class="ss-kpi-top">
                    <i class="fa-solid <?= $ico ?>" aria-hidden="true"></i>
                    <span class="ss-kpi-badge"><?= $lbl ?></span>
                </div>
                <div class="ss-kpi-value" id="ss-kpi-count-<?= $lvl ?>" aria-live="polite">—</div>
                <div class="ss-kpi-sub">
                    <span id="ss-kpi-pct-<?= $lvl ?>">— of streets</span>
                    <div class="ss-kpi-bar" aria-hidden="true">
                        <div class="ss-kpi-bar-fill" id="ss-kpi-bar-<?= $lvl ?>" style="width:0%"></div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>

            <div class="ss-kpi ss-kpi--total">
                <div class="ss-kpi-top">
                    <i class="fa-solid fa-map-location-dot" aria-hidden="true"></i>
                    <span class="ss-kpi-badge">Total</span>
                </div>
                <div class="ss-kpi-value" id="ss-kpi-count-total" aria-live="polite">—</div>
                <div class="ss-kpi-sub">Streets monitored</div>
            </div>

        </div><!-- /#ss-kpi-strip -->
    </div>
</header>

<!-- ═══════════════════════════════════════════════════════
     FILTER / SEARCH TOOLBAR
════════════════════════════════════════════════════════ -->
<div class="ss-toolbar" id="ssToolbar" role="search">
    <div class="container">
        <div class="ss-toolbar-inner">

            <div class="ss-search-wrap">
                <i class="fa-solid fa-magnifying-glass ss-search-icon" aria-hidden="true"></i>
                <input
                    type="search"
                    id="streetSearch"
                    class="ss-search-input"
                    placeholder="Search street name…"
                    aria-label="Search streets"
                    autocomplete="off"
                >
            </div>

            <div class="ss-filter-group" role="group" aria-label="Filter by risk level">
                <button class="ss-filter-pill active" data-filter="all">
                    All <span class="ss-filter-count">—</span>
                </button>
                <button class="ss-filter-pill ss-filter-pill--red" data-filter="red">
                    Critical <span class="ss-filter-count">—</span>
                </button>
                <button class="ss-filter-pill ss-filter-pill--orange" data-filter="orange">
                    High <span class="ss-filter-count">—</span>
                </button>
                <button class="ss-filter-pill ss-filter-pill--yellow" data-filter="yellow">
                    Moderate <span class="ss-filter-count">—</span>
                </button>
                <button class="ss-filter-pill ss-filter-pill--green" data-filter="green">
                    Safe <span class="ss-filter-count">—</span>
                </button>
            </div>

            <!-- Zone options injected by loadZones() -->
            <div class="ss-zone-wrap">
                <label for="zoneFilter" class="sr-only">Filter by Zone</label>
                <select id="zoneFilter" class="ss-select" aria-label="Filter by zone">
                    <option value="">All Zones</option>
                </select>
            </div>

            <div class="ss-sort-wrap">
                <label for="streetSort" class="sr-only">Sort streets by</label>
                <select id="streetSort" class="ss-select" aria-label="Sort streets">
                    <option value="risk">Sort: Risk Level</option>
                    <option value="score_desc">Sort: Vuln. Score ↓</option>
                    <option value="score_asc">Sort: Vuln. Score ↑</option>
                    <option value="name">Sort: Street Name</option>
                </select>
            </div>

            <div class="ss-view-toggle" role="group" aria-label="View mode">
                <button class="ss-view-btn active" data-view="grid" aria-label="Grid view" title="Grid view">
                    <i class="fa-solid fa-grip" aria-hidden="true"></i>
                </button>
                <button class="ss-view-btn" data-view="list" aria-label="List view" title="List view">
                    <i class="fa-solid fa-list" aria-hidden="true"></i>
                </button>
            </div>

        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════
     STREETS GRID
     Skeleton shown on load; cards injected by loadStreets()
════════════════════════════════════════════════════════ -->
<section class="ss-content" aria-label="Street status listing">
    <div class="container">

        <div class="ss-skeleton-grid" id="ss-skeleton" aria-hidden="true">
            <?php for ($i = 0; $i < 6; $i++): ?>
            <div class="ss-card-skeleton sk-loading">
                <div class="sk-line sk-line--short"></div>
                <div class="sk-line sk-line--title"></div>
                <div class="sk-line sk-line--mid"></div>
                <div class="sk-line sk-line--mid"></div>
                <div class="sk-line sk-line--short"></div>
            </div>
            <?php endfor; ?>
        </div>

        <div class="ss-empty" id="ssEmpty" hidden aria-live="polite">
            <i class="fa-solid fa-road" aria-hidden="true"></i>
            <p>No streets match your filters.</p>
            <button class="btn btn-outline" id="ssClearFilters">Clear Filters</button>
        </div>

        <div class="ss-results-bar" id="ssResultsBar" aria-live="polite" hidden>
            Showing <strong id="ssVisibleCount">0</strong> streets
        </div>

        <div class="ss-grid" id="ssGrid" aria-live="polite"></div>

        <!-- Pagination — injected by renderPagination() -->
        <div id="ssPagination" class="ss-pagination" hidden aria-label="Page navigation"></div>

    </div>
</section>

<!-- ═══════════════════════════════════════════════════════
     STREET DETAIL MODAL
════════════════════════════════════════════════════════ -->
<div id="ssModal" class="sdm-overlay" role="dialog" aria-modal="true" aria-labelledby="sdmTitle" hidden>
    <div class="sdm-panel">

        <!-- Header -->
        <div class="sdm-header" id="sdmHeader">
            <div class="sdm-header-left">
                <span class="sdm-risk-badge" id="sdmRiskBadge">
                    <span class="sdm-risk-dot"></span>
                    <span id="sdmRiskLabel">—</span>
                </span>
                <span class="sdm-zone-pill" id="sdmZone">—</span>
            </div>
            <button class="sdm-close" id="sdmClose" aria-label="Close details">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <!-- Street name + score -->
        <div class="sdm-title-row">
            <div>
                <h2 class="sdm-title" id="sdmTitle">—</h2>
                <p class="sdm-subtitle" id="sdmSubtitle">—</p>
            </div>
            <div class="sdm-score-wrap">
                <div class="sdm-score-ring" id="sdmScoreRing">
                    <svg viewBox="0 0 36 36" aria-hidden="true">
                        <circle cx="18" cy="18" r="15.9" fill="none" stroke-width="3" class="sdm-score-track"/>
                        <circle cx="18" cy="18" r="15.9" fill="none" stroke-width="3"
                            class="sdm-score-fill" id="sdmScoreFill"
                            stroke-dasharray="0,100" stroke-dashoffset="25"
                            transform="rotate(-90 18 18)"/>
                    </svg>
                    <span class="sdm-score-num" id="sdmScoreNum">—</span>
                </div>
                <span class="sdm-score-label">Vuln. Score</span>
            </div>
        </div>

        <!-- Loading / Error state -->
        <div class="sdm-loading" id="sdmLoading" hidden>
            <i class="fa-solid fa-circle-notch fa-spin"></i>
            Loading details…
        </div>
        <div class="sdm-error" id="sdmError" hidden>
            <i class="fa-solid fa-triangle-exclamation"></i>
            <span id="sdmErrorMsg">Could not load street details.</span>
        </div>

        <!-- Tab nav -->
        <nav class="sdm-tabs" role="tablist" id="sdmTabs">
            <button class="sdm-tab active" role="tab" data-tab="overview"   aria-selected="true">Overview</button>
            <button class="sdm-tab"        role="tab" data-tab="impact"     aria-selected="false">Typhoon Impact</button>
            <button class="sdm-tab"        role="tab" data-tab="welfare"    aria-selected="false">Welfare</button>
            <button class="sdm-tab"        role="tab" data-tab="resources"  aria-selected="false">Resources</button>
            <button class="sdm-tab"        role="tab" data-tab="reports"    aria-selected="false">Reports</button>
        </nav>

        <!-- Tab panels -->
        <div class="sdm-body" id="sdmBody">

            <!-- OVERVIEW -->
            <div class="sdm-panel-content" id="sdmTab-overview">
                <div class="sdm-section-title">Population</div>
                <div class="sdm-stat-row">
                    <div class="sdm-stat"><span class="sdm-stat-val" id="sdmPop">—</span><span class="sdm-stat-lbl">Residents</span></div>
                    <div class="sdm-stat"><span class="sdm-stat-val" id="sdmHH">—</span><span class="sdm-stat-lbl">Households</span></div>
                    <div class="sdm-stat"><span class="sdm-stat-val" id="sdmPoverty">—</span><span class="sdm-stat-lbl">Poverty Rate</span></div>
                    <div class="sdm-stat"><span class="sdm-stat-val" id="sdmIncome">—</span><span class="sdm-stat-lbl">Avg. Income</span></div>
                </div>

                <div class="sdm-section-title">Vulnerable Groups</div>
                <div class="sdm-stat-row">
                    <div class="sdm-stat"><span class="sdm-stat-val" id="sdmPWD">—</span><span class="sdm-stat-lbl">PWD</span></div>
                    <div class="sdm-stat"><span class="sdm-stat-val" id="sdmSenior">—</span><span class="sdm-stat-lbl">Seniors</span></div>
                    <div class="sdm-stat"><span class="sdm-stat-val" id="sdmChild">—</span><span class="sdm-stat-lbl">Children &lt;5</span></div>
                    <div class="sdm-stat"><span class="sdm-stat-val" id="sdm4Ps">—</span><span class="sdm-stat-lbl">4Ps HH</span></div>
                </div>

                <div class="sdm-section-title">Environment</div>
                <div class="sdm-env-grid" id="sdmEnvGrid">—</div>

                <div class="sdm-section-title">AI Assessment</div>
                <div id="sdmAI">—</div>
            </div>

            <!-- TYPHOON IMPACT -->
            <div class="sdm-panel-content" id="sdmTab-impact" hidden>
                <div id="sdmImpactList">—</div>
            </div>

            <!-- WELFARE -->
            <div class="sdm-panel-content" id="sdmTab-welfare" hidden>
                <div id="sdmWelfareList">—</div>
            </div>

            <!-- RESOURCES -->
            <div class="sdm-panel-content" id="sdmTab-resources" hidden>
                <div id="sdmResourceList">—</div>
                <div class="sdm-section-title" style="margin-top:20px">Budget Allocations</div>
                <div id="sdmBudgetList">—</div>
            </div>

            <!-- REPORTS -->
            <div class="sdm-panel-content" id="sdmTab-reports" hidden>
                <div id="sdmReportList">—</div>
            </div>

        </div><!-- /.sdm-body -->

        <!-- Footer -->
        <div class="sdm-footer">
            <span class="sdm-footer-ts" id="sdmLastUpdated"></span>
            <a id="sdmReportLink" href="#" class="btn btn-outline" style="font-size:13px;padding:8px 18px">
                <i class="fa-solid fa-circle-exclamation"></i> Report Incident
            </a>
        </div>

    </div>
</div>

<!-- ═══════════════════════════════════════════════════════
     LIVE MAP SECTION
════════════════════════════════════════════════════════ -->
<section class="ss-map-section" aria-label="Live street risk map">
    <div class="container">
        <div class="ss-map-header">
            <div>
                <p class="section-label">
                    <i class="fa-solid fa-map-location-dot" aria-hidden="true"></i>
                    Live Map
                </p>
                <h2 class="section-title" style="margin-bottom:0">Street Risk Map</h2>
            </div>
            <div class="ss-map-controls">
                <button id="ssLocateEvac" class="btn btn-outline ss-locate-btn" title="Find the nearest evacuation center from your location">
                    <i class="fa-solid fa-location-crosshairs"></i>
                    Nearest Evacuation Center
                </button>
                <button id="ssClearRoute" class="btn ss-clear-route-btn" hidden>
                    <i class="fa-solid fa-xmark"></i>
                    Clear Route
                </button>
            </div>
        </div>

        <!-- Map legend -->
        <div class="ss-map-legend-bar">
            <span class="ss-map-legend-item ss-map-legend-item--red">
                <span class="ss-map-legend-dot"></span>Critical
            </span>
            <span class="ss-map-legend-item ss-map-legend-item--orange">
                <span class="ss-map-legend-dot"></span>High Risk
            </span>
            <span class="ss-map-legend-item ss-map-legend-item--yellow">
                <span class="ss-map-legend-dot"></span>Moderate
            </span>
            <span class="ss-map-legend-item ss-map-legend-item--green">
                <span class="ss-map-legend-dot"></span>Safe
            </span>
            <span class="ss-map-legend-item ss-map-legend-item--evac">
                <span class="ss-map-legend-dot"></span>Evacuation Center
            </span>
            <span class="ss-map-legend-item ss-map-legend-item--user" id="ssUserLegend" hidden>
                <span class="ss-map-legend-dot"></span>Your Location
            </span>
        </div>

        <!-- Map container -->
        <div class="ss-map-wrap">
            <div id="ss-street-map" class="ss-map"></div>
            <div class="ss-map-spinner" id="ssMapSpinner" aria-label="Loading map">
                <i class="fa-solid fa-circle-notch fa-spin"></i>
                <span>Loading map…</span>
            </div>
            <!-- Route info panel -->
            <div class="ss-route-panel" id="ssRoutePanel" hidden>
                <div class="ss-route-panel-inner">
                    <div class="ss-route-dest" id="ssRouteDest">—</div>
                    <div class="ss-route-meta">
                        <span id="ssRouteDist"><i class="fa-solid fa-route"></i> —</span>
                        <span id="ssRouteTime"><i class="fa-regular fa-clock"></i> —</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ═══════════════════════════════════════════════════════
     RISK LEVEL LEGEND — static, no JS dependency
════════════════════════════════════════════════════════ -->
<aside class="ss-legend-section" aria-label="Risk level guide">
    <div class="container">
                    <div class="ss-legend-intro">
                <p class="section-label">Risk Guide</p>
                <p class="ss-legend-note">
                    Risk levels are assessed using AI-driven image analysis,
                    demographic indicators, and historical flood data.
                </p>
            </div>
        <div class="ss-legend-grid">



            <?php
            $legend = [
                'red'    => ['Critical',  'fa-circle-xmark',        'Severe flooding or major structural damage. Immediate evacuation required.'],
                'orange' => ['High Risk', 'fa-triangle-exclamation', 'Significant flooding or damage. Evacuation may be required.'],
                'yellow' => ['Moderate',  'fa-circle-minus',         'Minor flood risk or early warning. Monitor conditions closely.'],
                'green'  => ['Safe',      'fa-circle-check',         'No significant flooding or damage detected. Normal conditions.'],
            ];
            foreach ($legend as $lvl => [$lbl, $ico, $desc]):
            ?>
            <div class="ss-legend-item ss-legend-item--<?= $lvl ?>">
                <div class="ss-legend-icon">
                    <i class="fa-solid <?= $ico ?>" aria-hidden="true"></i>
                </div>
                <div>
                    <div class="ss-legend-title"><?= $lbl ?></div>
                    <div class="ss-legend-desc"><?= $desc ?></div>
                </div>
            </div>
            <?php endforeach; ?>

        </div>
    </div>
</aside>

<?php include 'footer.php'; ?>

<!-- Leaflet.js -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
<script src="../js/navbar.js"></script>
<!-- App JS -->
<script src="../js/street_status.js"></script>

</body>
</html>