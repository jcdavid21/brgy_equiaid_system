<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$page_title = 'Announcements — Barangay EQUIAID';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Official announcements, advisories, and updates from Barangay Bagong Silang EQUIAID.">
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
    <link rel="stylesheet" href="../styles/announcements.css">
</head>
<body>

<?php include 'navbar.php'; ?>

<!-- ═══════════════════════════════════════════════════
     PAGE HEADER
════════════════════════════════════════════════════ -->
<header class="an-header">
    <div class="container">
        <div class="an-header-inner">
            <div class="an-header-text">
                <p class="section-label">
                    <i class="fa-solid fa-bullhorn"></i>
                    Official Updates
                </p>
                <h1 class="section-title an-page-title">Announcements</h1>
                <p class="section-desc">
                    Advisories, evacuation orders, relief updates, and official
                    notices from Barangay Bagong Silang.
                </p>
            </div>
            <div class="an-header-meta">
                <div class="an-last-updated-wrap">
                    <i class="fa-regular fa-clock"></i>
                    Last posted
                    <strong id="an-last-updated" class="sk-loading sk-inline">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</strong>
                </div>
                <a href="report.php" class="btn btn-primary">
                    <i class="fa-solid fa-circle-exclamation"></i>
                    Report Incident
                </a>
            </div>
        </div>

        <!-- Category filter pills -->
        <div class="an-pill-strip" id="anPillStrip" role="group" aria-label="Filter by category">
            <button class="an-pill active" data-cat="all">
                All <span class="an-pill-count" id="anCountAll">—</span>
            </button>
            <button class="an-pill an-pill--alert" data-cat="Alert">
                <i class="fa-solid fa-triangle-exclamation"></i> Alert
                <span class="an-pill-count" id="anCountAlert">—</span>
            </button>
            <button class="an-pill an-pill--evacuation" data-cat="Evacuation">
                <i class="fa-solid fa-person-walking-arrow-right"></i> Evacuation
                <span class="an-pill-count" id="anCountEvacuation">—</span>
            </button>
            <button class="an-pill an-pill--relief" data-cat="Relief">
                <i class="fa-solid fa-hand-holding-heart"></i> Relief
                <span class="an-pill-count" id="anCountRelief">—</span>
            </button>
            <button class="an-pill an-pill--weather" data-cat="Weather">
                <i class="fa-solid fa-cloud-bolt"></i> Weather
                <span class="an-pill-count" id="anCountWeather">—</span>
            </button>
            <button class="an-pill an-pill--general" data-cat="General">
                <i class="fa-solid fa-circle-info"></i> General
                <span class="an-pill-count" id="anCountGeneral">—</span>
            </button>
        </div>
    </div>
</header>

<!-- ═══════════════════════════════════════════════════
     TOOLBAR
════════════════════════════════════════════════════ -->
<div class="an-toolbar" id="anToolbar">
    <div class="container">
        <div class="an-toolbar-inner">
            <div class="an-search-wrap">
                <i class="fa-solid fa-magnifying-glass an-search-icon"></i>
                <input
                    type="search"
                    id="anSearch"
                    class="an-search-input"
                    placeholder="Search announcements…"
                    autocomplete="off"
                    aria-label="Search announcements"
                >
            </div>

            <select class="an-select" id="anPriorityFilter" aria-label="Filter by priority">
                <option value="all">All Priority</option>
                <option value="Critical">Critical</option>
                <option value="High">High</option>
                <option value="Normal">Normal</option>
            </select>

            <select class="an-select" id="anSortFilter" aria-label="Sort announcements">
                <option value="newest">Newest First</option>
                <option value="oldest">Oldest First</option>
                <option value="priority">By Priority</option>
            </select>

            <div class="an-toolbar-spacer"></div>

            <span class="an-results-label" id="anResultsLabel" hidden>
                <strong id="anVisibleCount">0</strong> announcements
            </span>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════
     CONTENT — PINNED + LIST
════════════════════════════════════════════════════ -->
<main class="an-main">
    <div class="container">
        <div class="an-layout">

            <!-- ── LEFT: ANNOUNCEMENTS LIST ───────────────── -->
            <div class="an-col-main">

                <!-- Pinned banner (shown if a pinned item exists) -->
                <div id="anPinnedWrap" hidden></div>

                <!-- Skeletons -->
                <div class="an-list" id="anSkeletons">
                    <?php for ($i = 0; $i < 5; $i++): ?>
                    <div class="an-card an-card--skeleton">
                        <div class="an-card-aside">
                            <div class="sk-loading" style="width:44px;height:44px;border-radius:10px"></div>
                        </div>
                        <div class="an-card-body">
                            <div class="sk-loading" style="height:10px;width:30%;border-radius:4px;margin-bottom:10px"></div>
                            <div class="sk-loading" style="height:20px;width:68%;border-radius:4px;margin-bottom:8px"></div>
                            <div class="sk-loading" style="height:13px;width:92%;border-radius:4px;margin-bottom:5px"></div>
                            <div class="sk-loading" style="height:13px;width:72%;border-radius:4px"></div>
                        </div>
                        <div class="an-card-right">
                            <div class="sk-loading" style="height:22px;width:68px;border-radius:100px"></div>
                            <div class="sk-loading" style="height:11px;width:56px;border-radius:4px;margin-top:6px"></div>
                        </div>
                    </div>
                    <?php endfor; ?>
                </div>

                <!-- Actual list -->
                <div class="an-list" id="anList" hidden></div>

                <!-- Empty state -->
                <div class="an-empty" id="anEmpty" hidden>
                    <div class="an-empty-icon">
                        <i class="fa-solid fa-bullhorn"></i>
                    </div>
                    <h3>No announcements found</h3>
                    <p id="anEmptyMsg">There are no announcements matching your filters.</p>
                    <button class="btn btn-outline" id="anClearFilters">Clear Filters</button>
                </div>

                <!-- Pagination -->
                <div id="anPagination" class="an-pagination" hidden aria-label="Page navigation"></div>

            </div><!-- /.an-col-main -->

            <!-- ── RIGHT: SIDEBAR ─────────────────────────── -->
            <aside class="an-col-side">

                <!-- Active typhoon event -->
                <div class="an-sidebar-card an-sidebar-card--event" id="anEventCard" hidden>
                    <div class="an-event-label">
                        <i class="fa-solid fa-hurricane"></i> Active Event
                    </div>
                    <div class="an-event-name" id="anEventName">—</div>
                    <div class="an-event-meta" id="anEventMeta">—</div>
                    <a href="street_status.php" class="an-event-link">
                        View Street Status <i class="fa-solid fa-arrow-right"></i>
                    </a>
                </div>

                <!-- Category breakdown -->
                <div class="an-sidebar-card">
                    <div class="an-sc-head">
                        <div class="an-sc-icon">
                            <i class="fa-solid fa-chart-bar"></i>
                        </div>
                        <div>
                            <h3 class="an-sc-title">By Category</h3>
                            <p class="an-sc-desc">Announcement breakdown</p>
                        </div>
                    </div>
                    <div class="an-breakdown" id="anBreakdown">
                        <?php for ($i = 0; $i < 4; $i++): ?>
                        <div class="an-bdwn-item">
                            <div class="an-bdwn-head">
                                <div class="sk-loading" style="height:13px;width:60%;border-radius:4px"></div>
                                <div class="sk-loading" style="height:13px;width:20px;border-radius:4px"></div>
                            </div>
                            <div class="sk-loading" style="height:4px;width:100%;border-radius:2px;margin-top:6px"></div>
                        </div>
                        <?php endfor; ?>
                    </div>
                </div>

                <!-- Quick links -->
                <div class="an-sidebar-card">
                    <div class="an-sc-head">
                        <div class="an-sc-icon an-sc-icon--links">
                            <i class="fa-solid fa-link"></i>
                        </div>
                        <div>
                            <h3 class="an-sc-title">Quick Links</h3>
                            <p class="an-sc-desc">Related pages</p>
                        </div>
                    </div>
                    <div class="an-links-list">
                        <a href="street_status.php" class="an-link-item">
                            <i class="fa-solid fa-road"></i>
                            <span>Street Status</span>
                            <i class="fa-solid fa-chevron-right an-link-arrow"></i>
                        </a>
                        <a href="disaster_map.php" class="an-link-item">
                            <i class="fa-solid fa-map-location-dot"></i>
                            <span>Disaster Map</span>
                            <i class="fa-solid fa-chevron-right an-link-arrow"></i>
                        </a>
                        <a href="assistance.php" class="an-link-item">
                            <i class="fa-solid fa-hand-holding-heart"></i>
                            <span>Assistance</span>
                            <i class="fa-solid fa-chevron-right an-link-arrow"></i>
                        </a>
                        <a href="report.php" class="an-link-item">
                            <i class="fa-solid fa-flag"></i>
                            <span>Report Incident</span>
                            <i class="fa-solid fa-chevron-right an-link-arrow"></i>
                        </a>
                    </div>
                </div>

                <!-- Emergency contacts -->
                <div class="an-sidebar-card an-sidebar-card--dark">
                    <div class="an-sc-head">
                        <div class="an-sc-icon an-sc-icon--emergency">
                            <i class="fa-solid fa-phone-volume"></i>
                        </div>
                        <div>
                            <h3 class="an-sc-title" style="color:#fff">Emergency Contacts</h3>
                            <p class="an-sc-desc" style="color:rgba(255,255,255,.5)">Hotlines &amp; responders</p>
                        </div>
                    </div>
                    <div class="an-em-list">
                        <a href="tel:911" class="an-em-item">
                            <span class="an-em-label"><i class="fa-solid fa-shield-halved"></i> Emergency</span>
                            <span class="an-em-number">911</span>
                        </a>
                        <a href="tel:117" class="an-em-item">
                            <span class="an-em-label"><i class="fa-solid fa-person-drowning"></i> NDRRMC</span>
                            <span class="an-em-number">117</span>
                        </a>
                        <a href="tel:+63287119406" class="an-em-item">
                            <span class="an-em-label"><i class="fa-solid fa-building-columns"></i> DSWD</span>
                            <span class="an-em-number">(02) 8711-9406</span>
                        </a>
                        <a href="tel:1550" class="an-em-item">
                            <span class="an-em-label"><i class="fa-solid fa-cloud-bolt"></i> PAGASA</span>
                            <span class="an-em-number">1550</span>
                        </a>
                    </div>
                </div>

            </aside><!-- /.an-col-side -->

        </div><!-- /.an-layout -->
    </div><!-- /.container -->
</main>

<!-- ═══════════════════════════════════════════════════
     ANNOUNCEMENT DETAIL MODAL
════════════════════════════════════════════════════ -->
<div class="an-modal-overlay" id="anModal" role="dialog" aria-modal="true" aria-labelledby="anModalTitle" hidden>
    <div class="an-modal-panel">

        <div class="an-modal-header" id="anModalHeader">
            <div class="an-modal-category-bar" id="anModalCategoryBar"></div>
            <button class="an-modal-close" id="anModalClose" aria-label="Close">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <div class="an-modal-body">
            <h2 class="an-modal-title" id="anModalTitle">—</h2>
            <div class="an-modal-meta" id="anModalMeta"></div>
            <div class="an-modal-content" id="anModalContent">—</div>
            <div class="an-modal-footer-meta" id="anModalFooterMeta"></div>
        </div>

        <div class="an-modal-footer">
            <button class="btn btn-outline an-modal-close-btn" id="anModalCloseBtn"
                    style="font-size:13px;padding:9px 18px">
                Close
            </button>
        </div>

    </div>
</div>

<?php include 'footer.php'; ?>

<script src="../js/navbar.js"></script>
<script src="../js/announcements.js"></script>

</body>
</html>