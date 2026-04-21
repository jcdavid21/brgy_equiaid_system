<?php

if(session_status() === PHP_SESSION_NONE) {
    session_start();
}
$page_title = 'Home — Barangay EQUIAID';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Barangay EQUIAID: Predictive disaster monitoring and social welfare resource allocation for Barangay Bagong Silang, Caloocan City.">
    <title><?= htmlspecialchars($page_title) ?></title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;1,9..40,300&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"
          integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA=="
          crossorigin="anonymous" referrerpolicy="no-referrer">
    <!-- Leaflet.js map -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css">

    <link rel="stylesheet" href="../styles/global.css">
    <link rel="stylesheet" href="../styles/navbar.css">
    <link rel="stylesheet" href="../styles/footer.css">
    <link rel="stylesheet" href="../styles/home.css">


</head>
<body>

<?php include 'navbar.php'; ?>

<div id="alert-banner" class="alert-banner" role="alert" hidden>
    <div class="container">
        <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
        <span>
            <strong id="alert-typhoon-name"></strong>
            is currently active &mdash; Category <span id="alert-typhoon-cat"></span>,
            <span id="alert-typhoon-kph"></span> kph.
            Residents in RED-rated streets should proceed to the nearest evacuation center.
        </span>
        <a href="disaster_map.php">View Map</a>
    </div>
</div>

<main id="main-content">

<!-- ══════════════════════════════════════════════════════
     SECTION 1: HERO — KPI values injected by loadKPI()
════════════════════════════════════════════════════════ -->
<section id="hero" aria-labelledby="hero-title">
    <div class="container">
        <div class="hero-grid">

            <div class="hero-content">
                <div class="hero-eyebrow" aria-hidden="true">
                    <span class="hero-eyebrow-dot"></span>
                    <span>Live System &mdash; Bagong Silang</span>
                </div>

                <h1 class="hero-title" id="hero-title">
                    Barangay EQUIAID<br>
                    <em>Disaster Monitoring</em><br>
                    &amp; Social Welfare System
                </h1>

                <p class="hero-desc">
                    A decision support platform that identifies vulnerable streets,
                    monitors disaster impacts, and supports efficient social welfare
                    distribution within Barangay Bagong Silang.
                </p>

                <div class="hero-actions">
                    <a href="street_status.php" class="btn btn-primary">
                        <i class="fa-solid fa-road" aria-hidden="true"></i>
                        View Street Status
                    </a>
                    <a href="disaster_map.php" class="btn btn-outline">
                        <i class="fa-solid fa-map-location-dot" aria-hidden="true"></i>
                        View Disaster Map
                    </a>
                </div>

                <!-- KPI strip — skeleton until JS fills -->
                <div id="kpi-strip" class="hero-kpi-strip" aria-label="Live system statistics">
                    <div class="kpi-item">
                        <div class="kpi-value sk-box" id="kpi-total"
                             style="width:42px;height:32px;">&nbsp;</div>
                        <div class="kpi-label">Total Streets</div>
                    </div>
                    <div class="kpi-divider" aria-hidden="true"></div>
                    <div class="kpi-item">
                        <div class="kpi-value kpi-red sk-box" id="kpi-critical"
                             style="width:28px;height:32px;">&nbsp;</div>
                        <div class="kpi-label">Critical (RED)</div>
                    </div>
                    <div class="kpi-divider" aria-hidden="true"></div>
                    <div class="kpi-item">
                        <div class="kpi-value kpi-orange sk-box" id="kpi-high"
                             style="width:28px;height:32px;">&nbsp;</div>
                        <div class="kpi-label">High Risk (ORANGE)</div>
                    </div>
                    <div class="kpi-divider" aria-hidden="true"></div>
                    <div class="kpi-item">
                        <div class="kpi-value sk-box" id="kpi-pct"
                             style="width:52px;height:32px;">&nbsp;</div>
                        <div class="kpi-label">Streets Affected</div>
                    </div>
                    <div class="kpi-divider" aria-hidden="true"></div>
                    <div class="kpi-item">
                        <div class="kpi-value sk-box" id="kpi-population"
                             style="width:60px;height:32px;">&nbsp;</div>
                        <div class="kpi-label">Total Population</div>
                    </div>
                </div>
            </div>

            <div>
                <div class="image-placeholder hero-image-placeholder"
                     role="img" aria-label="Hero banner image placeholder">
                    <img src="../assets/social-team.avif" alt="">
                </div>
            </div>
        </div>
    </div>
</section>


<!-- ══════════════════════════════════════════════════════
     SECTION 2: KEY FEATURES (static)
════════════════════════════════════════════════════════ -->
<section id="features" aria-labelledby="features-title">
    <div class="container">
        <div class="features-header">
            <div class="section-label">What We Offer</div>
            <h2 class="section-title" id="features-title">Built for Barangay Resilience</h2>
            <p class="section-desc">
                EQUIAID integrates AI-driven prediction, real-time monitoring,
                and welfare coordination into a single accessible platform.
            </p>
        </div>
        <div class="features-grid" role="list">
            <article class="feature-card" role="listitem">
                <span class="feature-num">01</span>
                <div class="feature-icon-box" aria-hidden="true">
                    <i class="fa-solid fa-road-circle-exclamation"></i>
                </div>
                <h3 class="feature-title">Street Vulnerability Monitoring</h3>
                <p class="feature-desc">
                    Residents can view the current risk level of every street in the barangay,
                    updated by ResNet-50 AI analysis and field reports.
                </p>
            </article>
            <article class="feature-card" role="listitem">
                <span class="feature-num">02</span>
                <div class="feature-icon-box" aria-hidden="true">
                    <i class="fa-solid fa-cloud-bolt"></i>
                </div>
                <h3 class="feature-title">Disaster Impact Tracking</h3>
                <p class="feature-desc">
                    Monitor areas affected during typhoons and flooding events
                    with categorized impact levels and recorded flood data.
                </p>
            </article>
            <article class="feature-card" role="listitem">
                <span class="feature-num">03</span>
                <div class="feature-icon-box" aria-hidden="true">
                    <i class="fa-solid fa-hand-holding-heart"></i>
                </div>
                <h3 class="feature-title">Social Welfare Assistance</h3>
                <p class="feature-desc">
                    View available DSWD resources, welfare support programs, and
                    distribution schedules for qualified beneficiaries.
                </p>
            </article>
            <article class="feature-card" role="listitem">
                <span class="feature-num">04</span>
                <div class="feature-icon-box" aria-hidden="true">
                    <i class="fa-solid fa-person-walking-arrow-right"></i>
                </div>
                <h3 class="feature-title">Evacuation Planning</h3>
                <p class="feature-desc">
                    Identify designated evacuation centers, mapped routes,
                    and community emergency response plans.
                </p>
            </article>
        </div>
    </div>
</section>


<!-- ══════════════════════════════════════════════════════
     SECTION 3: MAP PREVIEW
     Legend counts injected by loadRiskCounts()
     Affected count sentence injected by loadKPI()
════════════════════════════════════════════════════════ -->
<section id="map-preview" aria-labelledby="map-preview-title">
    <div class="container">
        <div class="map-grid">
            <div class="map-content">
                <div class="section-label">Monitoring System</div>
                <h2 class="section-title" id="map-preview-title">Real-Time Disaster Map</h2>
                <p class="section-desc">
                    Streets across Barangay Bagong Silang are continuously categorized
                    into risk levels based on AI predictions and resident reports.
                    <span id="hero-affected-count"></span>
                </p>
                <div class="map-legend" role="list" aria-label="Risk level legend">
                    <div class="map-legend-item" role="listitem">
                        <span class="legend-dot critical"></span>
                        <span><strong>RED &mdash; Critical:</strong> Immediate evacuation required</span>
                        <span class="legend-count" id="legend-count-red" hidden></span>
                    </div>
                    <div class="map-legend-item" role="listitem">
                        <span class="legend-dot high"></span>
                        <span><strong>ORANGE &mdash; High Risk:</strong> Caution, possible flooding</span>
                        <span class="legend-count" id="legend-count-orange" hidden></span>
                    </div>
                    <div class="map-legend-item" role="listitem">
                        <span class="legend-dot moderate"></span>
                        <span><strong>YELLOW &mdash; Moderate:</strong> Monitor conditions closely</span>
                        <span class="legend-count" id="legend-count-yellow" hidden></span>
                    </div>
                    <div class="map-legend-item" role="listitem">
                        <span class="legend-dot safe"></span>
                        <span><strong>GREEN &mdash; Safe:</strong> No immediate threat detected</span>
                        <span class="legend-count" id="legend-count-green" hidden></span>
                    </div>
                </div>
                <a href="disaster_map.php" class="btn btn-primary"
                   style="background:#fff;color:var(--navy);border-color:#fff;">
                    <i class="fa-solid fa-map-location-dot" aria-hidden="true"></i>
                    View Full Map
                </a>
            </div>
            <div class="map-placeholder-wrap">
                <div class="map-grid-lines" aria-hidden="true"></div>
                <div id="home-map" aria-label="Interactive disaster map of Barangay Bagong Silang">
                    <!-- Leaflet map renders here — js/home.js calls loadMap() -->
                    <div class="map-loading-state">
                        <i class="fa-solid fa-map-location-dot"></i>
                        <span>Loading map&hellip;</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>


<!-- ══════════════════════════════════════════════════════
     SECTION 4: RISK LEVELS
     Example street lines injected by loadRiskExamples()
════════════════════════════════════════════════════════ -->
<section id="risk-levels" aria-labelledby="risk-levels-title">
    <div class="container">
        <div class="risk-header">
            <div class="section-label">Classification System</div>
            <h2 class="section-title" id="risk-levels-title">Street Risk Level Categories</h2>
            <p class="section-desc">
                Every street is assessed using four risk tiers derived from
                ResNet-50 AI image analysis and field condition reports.
            </p>
        </div>
        <div class="risk-grid" role="list">
            <article class="risk-card critical" role="listitem">
                <div class="risk-icon" aria-hidden="true"><i class="fa-solid fa-circle-exclamation"></i></div>
                <div class="risk-indicator">
                    <span class="risk-badge">RED</span>
                    <div class="risk-bar"></div>
                </div>
                <h3 class="risk-level-name">Critical</h3>
                <p class="risk-desc">Severe flooding or structural damage detected. Immediate evacuation required.</p>
                <div class="risk-example" id="risk-example-RED" hidden></div>
            </article>
            <article class="risk-card high" role="listitem">
                <div class="risk-icon" aria-hidden="true"><i class="fa-solid fa-triangle-exclamation"></i></div>
                <div class="risk-indicator">
                    <span class="risk-badge">ORANGE</span>
                    <div class="risk-bar"></div>
                </div>
                <h3 class="risk-level-name">High Risk</h3>
                <p class="risk-desc">Significant risk of flooding or damage. Prepare for possible evacuation.</p>
                <div class="risk-example" id="risk-example-ORANGE" hidden></div>
            </article>
            <article class="risk-card moderate" role="listitem">
                <div class="risk-icon" aria-hidden="true"><i class="fa-solid fa-circle-info"></i></div>
                <div class="risk-indicator">
                    <span class="risk-badge">YELLOW</span>
                    <div class="risk-bar"></div>
                </div>
                <h3 class="risk-level-name">Moderate Risk</h3>
                <p class="risk-desc">Elevated risk conditions. Monitor updates and be ready to act.</p>
                <div class="risk-example" id="risk-example-YELLOW" hidden></div>
            </article>
            <article class="risk-card safe" role="listitem">
                <div class="risk-icon" aria-hidden="true"><i class="fa-solid fa-circle-check"></i></div>
                <div class="risk-indicator">
                    <span class="risk-badge">GREEN</span>
                    <div class="risk-bar"></div>
                </div>
                <h3 class="risk-level-name">Safe</h3>
                <p class="risk-desc">No immediate threat detected. Normal conditions apply.</p>
                <div class="risk-example" id="risk-example-GREEN" hidden></div>
            </article>
        </div>
    </div>
</section>


<!-- ══════════════════════════════════════════════════════
     SECTION 5: RESOURCES
     Cards injected by loadResources() into #resources-grid
════════════════════════════════════════════════════════ -->
<section id="assistance" aria-labelledby="assistance-title">
    <div class="container">
        <div class="assistance-header">
            <div>
                <div class="section-label">DSWD Social Welfare</div>
                <h2 class="section-title" id="assistance-title">Available Assistance</h2>
                <p class="section-desc">
                    Welfare resources are allocated based on street vulnerability scores
                    to ensure aid reaches the most affected residents first.
                </p>
            </div>
            <a href="assistance.php" class="btn btn-outline" style="flex-shrink:0;">
                <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                View All Resources
            </a>
        </div>
        <!-- Empty on load — JS fills with real cards -->
        <div id="resources-grid" class="assistance-grid" role="list"
             aria-label="Available welfare resources">
        </div>
    </div>
</section>


<!-- ══════════════════════════════════════════════════════
     SECTION 6: ANNOUNCEMENTS
     Items injected by loadAnnouncements() into #ann-list
════════════════════════════════════════════════════════ -->
<section id="announcements" aria-labelledby="announcements-title">
    <div class="container">
        <div class="ann-header">
            <div>
                <div class="section-label">Latest Updates</div>
                <h2 class="section-title" id="announcements-title">Announcements</h2>
            </div>
            <a href="announcements.php" class="btn btn-outline">
                <i class="fa-solid fa-bullhorn" aria-hidden="true"></i>
                View All
            </a>
        </div>
        <!-- Empty on load — JS fills with real rows -->
        <div id="ann-list" class="ann-list" role="list"
             aria-label="Latest announcements">
        </div>
    </div>
</section>


<!-- ══════════════════════════════════════════════════════
     SECTION 7: REPORT INCIDENT
     Pending badge injected by loadPendingReports()
════════════════════════════════════════════════════════ -->
<section id="report" aria-labelledby="report-title">
    <div class="container">
        <div class="report-grid">
            <div>
                <div class="image-placeholder report-image-placeholder"
                     role="img" aria-label="Report incident illustration placeholder">
                    <img src="../assets/disaster.avif" alt="">
                </div>
            </div>
            <div>
                <div class="section-label">Community Reporting</div>
                <h2 class="section-title" id="report-title">
                    Spotted a Problem?<br><em>Report It Now</em>
                </h2>

                <!-- Injected by loadPendingReports() if count > 0 -->
                <div id="pending-badge" class="pending-badge" hidden>
                    <i class="fa-solid fa-clock" aria-hidden="true"></i>
                    <span id="pending-count"></span>
                    pending report<span id="pending-plural"></span> awaiting review
                </div>

                <p class="section-desc">
                    Help keep your community safe by reporting floods, road damage,
                    or blocked streets. Your report goes directly to barangay staff
                    for immediate action.
                </p>
                <div class="report-steps" aria-label="How to submit a report">
                    <div class="report-step">
                        <div class="step-num" aria-hidden="true">1</div>
                        <div class="step-text">
                            <strong>Select the incident type</strong> — flood, damage, or blocked road.
                        </div>
                    </div>
                    <div class="report-step">
                        <div class="step-num" aria-hidden="true">2</div>
                        <div class="step-text">
                            <strong>Describe the situation</strong> and attach a photo if available.
                        </div>
                    </div>
                    <div class="report-step">
                        <div class="step-num" aria-hidden="true">3</div>
                        <div class="step-text">
                            <strong>Submit your report</strong> and track its status in real time.
                        </div>
                    </div>
                </div>
                <a href="report.php" class="btn btn-primary">
                    <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
                    Submit a Report
                </a>
            </div>
        </div>
    </div>
</section>

</main>

<?php include 'footer.php'; ?>

<script src="../js/navbar.js" defer></script>
<script src="../js/footer.js" defer></script>
<!-- Leaflet.js map library -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js"></script>
<!-- home.js fetches all live data from backend/home.php -->
<script src="../js/home.js" defer></script>

</body>
</html>