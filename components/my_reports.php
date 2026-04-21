<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$page_title = 'My Reports — Barangay EQUIAID';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="View and track your submitted flood incident reports for Barangay Bagong Silang.">
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
    <link rel="stylesheet" href="../styles/my_reports.css">
</head>
<body>

<?php include 'navbar.php'; ?>

<!-- ═══════════════════════════════════════════════════
     PAGE HEADER
════════════════════════════════════════════════════ -->
<header class="mr-header">
    <div class="container">
        <div class="mr-header-inner">
            <div class="mr-header-text">
                <p class="section-label">
                    <i class="fa-solid fa-flag"></i>
                    Incident Tracking
                </p>
                <h1 class="section-title mr-page-title">My Reports</h1>
                <p class="section-desc">
                    Track the status of all flood incidents you've submitted
                    to Barangay Bagong Silang.
                </p>
            </div>
            <div class="mr-header-actions">
                <a href="report.php" class="btn btn-primary">
                    <i class="fa-solid fa-plus"></i>
                    New Report
                </a>
            </div>
        </div>

        <!-- Summary strip -->
        <div class="mr-summary-strip" id="mrSummaryStrip">
            <div class="mr-sum mr-sum--total">
                <span class="mr-sum-val sk-loading" id="mrSumTotal">—</span>
                <span class="mr-sum-lbl">Total Reports</span>
            </div>
            <div class="mr-sum mr-sum--pending">
                <span class="mr-sum-val sk-loading" id="mrSumPending">—</span>
                <span class="mr-sum-lbl">Pending</span>
            </div>
            <div class="mr-sum mr-sum--progress">
                <span class="mr-sum-val sk-loading" id="mrSumProgress">—</span>
                <span class="mr-sum-lbl">In Progress</span>
            </div>
            <div class="mr-sum mr-sum--resolved">
                <span class="mr-sum-val sk-loading" id="mrSumResolved">—</span>
                <span class="mr-sum-lbl">Resolved</span>
            </div>
            <div class="mr-sum mr-sum--verified">
                <span class="mr-sum-val sk-loading" id="mrSumVerified">—</span>
                <span class="mr-sum-lbl">Verified</span>
            </div>
        </div>
    </div>
</header>

<!-- ═══════════════════════════════════════════════════
     TOOLBAR
════════════════════════════════════════════════════ -->
<div class="mr-toolbar" id="mrToolbar">
    <div class="container">
        <div class="mr-toolbar-inner">

            <!-- Search -->
            <div class="mr-search-wrap">
                <i class="fa-solid fa-magnifying-glass mr-search-icon"></i>
                <input
                    type="search"
                    id="mrSearch"
                    class="mr-search-input"
                    placeholder="Search by street or description…"
                    autocomplete="off"
                    aria-label="Search reports"
                >
            </div>

            <!-- Status filter -->
            <select class="mr-select" id="mrStatusFilter" aria-label="Filter by status">
                <option value="all">All Status</option>
                <option value="Pending">Pending</option>
                <option value="Verified">Verified</option>
                <option value="In Progress">In Progress</option>
                <option value="Resolved">Resolved</option>
                <option value="Dismissed">Dismissed</option>
            </select>

            <!-- Severity filter -->
            <select class="mr-select" id="mrSevFilter" aria-label="Filter by severity">
                <option value="all">All Severity</option>
                <option value="Severe">Severe</option>
                <option value="Moderate">Moderate</option>
                <option value="Low">Low</option>
            </select>

            <div class="mr-toolbar-spacer"></div>

            <!-- Results count -->
            <span class="mr-results-label" id="mrResultsLabel" hidden>
                <strong id="mrVisibleCount">0</strong> reports
            </span>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════
     REPORTS LIST
════════════════════════════════════════════════════ -->
<section class="mr-content" aria-label="My reports">
    <div class="container">

        <!-- Skeletons -->
        <div class="mr-list" id="mrSkeletons">
            <?php for ($i = 0; $i < 4; $i++): ?>
            <div class="mr-card mr-card--skeleton">
                <div class="mr-card-aside">
                    <div class="sk-loading" style="width:52px;height:52px;border-radius:12px"></div>
                </div>
                <div class="mr-card-body">
                    <div class="sk-loading" style="height:11px;width:38%;border-radius:4px;margin-bottom:10px"></div>
                    <div class="sk-loading" style="height:20px;width:55%;border-radius:4px;margin-bottom:8px"></div>
                    <div class="sk-loading" style="height:13px;width:85%;border-radius:4px;margin-bottom:5px"></div>
                    <div class="sk-loading" style="height:13px;width:65%;border-radius:4px"></div>
                </div>
                <div class="mr-card-meta">
                    <div class="sk-loading" style="height:24px;width:80px;border-radius:100px;margin-bottom:8px"></div>
                    <div class="sk-loading" style="height:11px;width:70px;border-radius:4px"></div>
                </div>
            </div>
            <?php endfor; ?>
        </div>

        <!-- Real list -->
        <div class="mr-list" id="mrList" hidden></div>

        <!-- Empty state -->
        <div class="mr-empty" id="mrEmpty" hidden>
            <div class="mr-empty-icon">
                <i class="fa-solid fa-flag"></i>
            </div>
            <h3>No reports found</h3>
            <p id="mrEmptyMsg">You haven't submitted any reports yet.</p>
            <a href="report.php" class="btn btn-primary" id="mrEmptyAction">
                <i class="fa-solid fa-plus"></i> Submit Your First Report
            </a>
        </div>

        <!-- Pagination -->
        <div id="mrPagination" class="mr-pagination" hidden aria-label="Page navigation"></div>

    </div>
</section>

<!-- ═══════════════════════════════════════════════════
     REPORT DETAIL MODAL
════════════════════════════════════════════════════ -->
<div class="mr-modal-overlay" id="mrModal" role="dialog" aria-modal="true" aria-labelledby="mrModalTitle" hidden>
    <div class="mr-modal-panel">

        <!-- Modal header -->
        <div class="mr-modal-header" id="mrModalHeader">
            <div class="mr-modal-header-left">
                <span class="mr-modal-type-icon" id="mrModalIcon">
                    <i class="fa-solid fa-water"></i>
                </span>
                <div>
                    <div class="mr-modal-type" id="mrModalType">—</div>
                    <div class="mr-modal-street" id="mrModalStreet">—</div>
                </div>
            </div>
            <button class="mr-modal-close" id="mrModalClose" aria-label="Close">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <!-- Status timeline -->
        <div class="mr-modal-status-bar" id="mrModalStatusBar">
            <div class="mr-status-step" data-step="Pending">
                <div class="mr-status-dot"></div>
                <span>Submitted</span>
            </div>
            <div class="mr-status-line"></div>
            <div class="mr-status-step" data-step="Verified">
                <div class="mr-status-dot"></div>
                <span>Verified</span>
            </div>
            <div class="mr-status-line"></div>
            <div class="mr-status-step" data-step="In Progress">
                <div class="mr-status-dot"></div>
                <span>In Progress</span>
            </div>
            <div class="mr-status-line"></div>
            <div class="mr-status-step" data-step="Resolved">
                <div class="mr-status-dot"></div>
                <span>Resolved</span>
            </div>
        </div>

        <!-- Modal body -->
        <div class="mr-modal-body" id="mrModalBody">

            <!-- Severity + date row -->
            <div class="mr-modal-meta-row" id="mrModalMetaRow"></div>

            <!-- Description -->
            <div class="mr-modal-section" id="mrModalDescSection">
                <div class="mr-modal-section-label">Description</div>
                <p class="mr-modal-desc" id="mrModalDesc">—</p>
            </div>

            <!-- Image (if any) -->
            <div class="mr-modal-section" id="mrModalImgSection" hidden>
                <div class="mr-modal-section-label">Photo</div>
                <img id="mrModalImg" src="" alt="Report photo" class="mr-modal-img">
            </div>

            <!-- Location -->
            <div class="mr-modal-section" id="mrModalLocSection">
                <div class="mr-modal-section-label">Location</div>
                <div class="mr-modal-loc" id="mrModalLoc">—</div>
            </div>

            <!-- Resolution notes (shown if Resolved/Dismissed) -->
            <div class="mr-modal-section mr-modal-section--resolution" id="mrModalResSection" hidden>
                <div class="mr-modal-section-label">
                    <i class="fa-solid fa-circle-check"></i> Resolution Notes
                </div>
                <p class="mr-modal-res-notes" id="mrModalResNotes">—</p>
            </div>

            <!-- Verified by -->
            <div class="mr-modal-section" id="mrModalVerSection" hidden>
                <div class="mr-modal-section-label">Verified by</div>
                <div class="mr-modal-ver" id="mrModalVer">—</div>
            </div>

        </div>

        <!-- Modal footer -->
        <div class="mr-modal-footer">
            <a href="report.php" class="btn btn-primary" style="font-size:13px;padding:9px 18px">
                <i class="fa-solid fa-plus"></i> New Report
            </a>
            <button class="btn btn-outline mr-modal-close-btn" id="mrModalCloseBtn" style="font-size:13px;padding:9px 18px">
                Close
            </button>
        </div>
    </div>
</div>

<!-- Loading overlay -->
<div class="mr-loading-overlay" id="mrLoadingOverlay" hidden>
    <div class="mr-loading-inner">
        <div class="mr-loading-spinner"></div>
        <span>Loading report…</span>
    </div>
</div>

<?php include 'footer.php'; ?>

<script src="../js/navbar.js"></script>
<script src="../js/my_reports.js"></script>

</body>
</html>