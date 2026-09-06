<?php
/**
 * resident-reports.php
 * Barangay EQUIAID — Admin: Resident Reports Management
 */

$current_page = 'resident-reports';
$page_title   = 'Resident Reports — Barangay EQUIAID';

if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['user_role']) || !in_array($_SESSION['user_role'], ['admin', 'superadmin'])) {
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

    <link rel="stylesheet" href="../styles/admin_sidebar.css">
    <link rel="stylesheet" href="../styles/admin_dashboard.css">
    <link rel="stylesheet" href="../styles/admin-resident-reports.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css">
</head>
<body>

<div class="admin-shell">

    <!-- ══ SIDEBAR ══════════════════════════════════════ -->
    <?php include 'sidebar.php'; ?>

    <!-- ══ MAIN CONTENT ════════════════════════════════ -->
    <main class="main-content" id="mainContent">
        <div class="dashboard-container">

            <!-- ── PAGE HEADER ─────────────────────────── -->
            <div class="dash-header">
                <div class="dash-header-left">
                    <div class="dash-breadcrumb">
                        <i class="fa-solid fa-flag"></i>
                        <span>Resident Reports</span>
                    </div>
                    <h1 class="dash-title">Resident Reports</h1>
                    <p class="dash-desc">
                        Review, verify, and manage community-submitted incident reports. Update statuses
                        and add resolution notes to track each report through to completion.
                    </p>
                </div>
                <div class="dash-header-right">
                    <button class="btn-rr-export" id="btnExportCsv">
                        <i class="fa-solid fa-file-csv"></i>
                        Export CSV
                    </button>
                </div>
            </div>

            <!-- ── KPI STRIP ───────────────────────────── -->
            <div class="rr-kpi-grid" id="rrKpiGrid">
                <div class="kpi-card">
                    <div class="kpi-icon-wrap neutral"><i class="fa-solid fa-inbox"></i></div>
                    <div class="kpi-body">
                        <span class="kpi-label">Total Reports</span>
                        <span class="kpi-number sk-inline" id="kpiTotal">—</span>
                        <span class="kpi-sub">All submissions</span>
                    </div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-icon-wrap mid"><i class="fa-solid fa-clock"></i></div>
                    <div class="kpi-body">
                        <span class="kpi-label">Pending</span>
                        <span class="kpi-number sk-inline" id="kpiPending">—</span>
                        <span class="kpi-sub">Awaiting review</span>
                    </div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-icon-wrap dark"><i class="fa-solid fa-spinner"></i></div>
                    <div class="kpi-body">
                        <span class="kpi-label">In Progress</span>
                        <span class="kpi-number sk-inline" id="kpiInProgress">—</span>
                        <span class="kpi-sub">Being handled</span>
                    </div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-icon-wrap neutral"><i class="fa-solid fa-circle-check"></i></div>
                    <div class="kpi-body">
                        <span class="kpi-label">Resolved</span>
                        <span class="kpi-number sk-inline" id="kpiResolved">—</span>
                        <span class="kpi-sub">Closed reports</span>
                    </div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-icon-wrap neutral"><i class="fa-solid fa-triangle-exclamation"></i></div>
                    <div class="kpi-body">
                        <span class="kpi-label">Severe</span>
                        <span class="kpi-number sk-inline" id="kpiSevere">—</span>
                        <span class="kpi-sub">Critical severity</span>
                    </div>
                </div>
            </div>

            <!-- ── REPORT HEATMAP ────────────────────────── -->
            <div class="dash-card map-card" style="margin-bottom:24px;">
                <div class="card-head">
                    <div>
                        <div class="card-label">Incident Overview</div>
                        <h2 class="card-title">Report Heatmap</h2>
                    </div>
                    <div class="sm-map-controls">
                        <button class="btn-dash-outline sm-map-toggle active" id="btnRrLayerAll">
                            <i class="fa-solid fa-layer-group"></i> All
                        </button>
                        <button class="btn-dash-outline sm-map-toggle" id="btnRrLayerSevere">
                            <i class="fa-solid fa-circle-exclamation"></i> Severe
                        </button>
                        <button class="btn-dash-outline sm-map-toggle" id="btnRrLayerType">
                            <i class="fa-solid fa-tags"></i> By Type
                        </button>
                    </div>
                </div>
                <div class="dashboard-map-wrap" style="margin:0 -28px;">
                    <div id="rr-heatmap" style="height:400px;width:100%;z-index:1;"></div>
                    <div class="map-loading-overlay" id="rrMapOverlay">
                        <div class="map-spinner"></div>
                        <span>Loading map…</span>
                    </div>
                </div>
                <div class="map-legend-row" id="rrMapLegend">
                    <div class="map-legend-item"><span class="map-legend-dot" style="background:#dc2626;"></span> Severe <span class="map-legend-count" id="leg-rr-severe">—</span></div>
                    <div class="map-legend-item"><span class="map-legend-dot" style="background:#d97706;"></span> Moderate <span class="map-legend-count" id="leg-rr-moderate">—</span></div>
                    <div class="map-legend-item"><span class="map-legend-dot" style="background:#16a34a;"></span> Low <span class="map-legend-count" id="leg-rr-low">—</span></div>
                    <div class="map-legend-item"><span class="map-legend-dot" style="background:#9ca3af;"></span> Pending <span class="map-legend-count" id="leg-rr-pending">—</span></div>
                </div>
            </div>

            <!-- ── REPORTS TABLE ────────────────────────── -->
            <div class="dash-card rr-table-card">
                <div class="card-head">
                    <div>
                        <div class="card-label">Management</div>
                        <div class="card-title">Incident Reports</div>
                    </div>
                </div>

                <!-- Toolbar -->
                <div class="rr-toolbar">
                    <div class="rr-search-wrap">
                        <i class="fa-solid fa-magnifying-glass"></i>
                        <input type="text" class="rr-search" id="rrSearch"
                               placeholder="Search reporter, street, description…">
                    </div>
                    <select class="rr-filter-select" id="filterStatus">
                        <option value="">All Status</option>
                        <option value="Pending">Pending</option>
                        <option value="Verified">Verified</option>
                        <option value="In Progress">In Progress</option>
                        <option value="Resolved">Resolved</option>
                        <option value="Dismissed">Dismissed</option>
                    </select>
                    <select class="rr-filter-select" id="filterSeverity">
                        <option value="">All Severity</option>
                        <option value="Severe">Severe</option>
                        <option value="Moderate">Moderate</option>
                        <option value="Low">Low</option>
                    </select>
                    <select class="rr-filter-select" id="filterType">
                        <option value="">All Types</option>
                        <option value="Flood">Flood</option>
                        <option value="Damage">Damage</option>
                        <option value="Blocked Road">Blocked Road</option>
                        <option value="Fire">Fire</option>
                        <option value="Medical Emergency">Medical Emergency</option>
                        <option value="Other">Other</option>
                    </select>
                    <select class="rr-filter-select" id="filterStreet">
                        <option value="">All Streets</option>
                        <!-- Populated by JS -->
                    </select>
                    <select class="rr-filter-select" id="filterTag">
                        <option value="">All Tags</option>
                    </select>
                    <div class="rr-toolbar-spacer"></div>
                    <span id="rrRecordCount" class="table-info"></span>
                </div>

                <!-- Table -->
                <div class="table-wrap">
                    <table class="dashboard-table" id="rrTable">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Reporter</th>
                                <th>Type / Location</th>
                                <th>Severity</th>
                                <th>Status</th>
                                <th>Reported</th>
                                <th>Verified By</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="rrTableBody">
                            <tr>
                                <td colspan="8" class="tbl-loading">
                                    <i class="fa-solid fa-circle-notch fa-spin"></i>&nbsp; Loading reports…
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div class="table-footer">
                    <span id="rrPaginationInfo" class="table-info"></span>
                    <div class="table-pagination" id="rrPagination"></div>
                </div>

            </div><!-- /.dash-card -->

            <!-- ── STREET DOCUMENTATION ─────────────────── -->
            <div class="dash-card">
                <div class="card-head">
                    <div>
                        <div class="card-label">Street Documentation</div>
                        <h2 class="card-title">Recent Uploaded Images</h2>
                    </div>
                    <select class="sm-select-inline" id="rrImgStreetFilter" style="color: white !important;">
                        <option value="">All Streets</option>
                    </select>
                </div>
                <div class="sm-image-grid" id="rrImageGrid">
                    <div class="sm-img-skeleton"></div>
                    <div class="sm-img-skeleton"></div>
                    <div class="sm-img-skeleton"></div>
                    <div class="sm-img-skeleton"></div>
                    <div class="sm-img-skeleton"></div>
                    <div class="sm-img-skeleton"></div>
                </div>
            </div>
        </div><!-- /.dashboard-container -->
    </main>
</div><!-- /.admin-shell -->


<!-- ══════════════════════════════════════════════════════
     MODAL — VIEW REPORT DETAILS
═══════════════════════════════════════════════════════ -->
<div class="modal-backdrop" id="rrViewModal" role="dialog" aria-modal="true">
    <div class="modal-box rr-modal-box">

        <div class="modal-header">
            <div class="modal-title-group">
                <span class="modal-eyebrow" id="viewModalEyebrow">Report Details</span>
                <h2 class="modal-title" id="viewModalTitle">Incident Report</h2>
            </div>
            <button class="modal-close" id="btnCloseViewModal" aria-label="Close">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <div class="modal-body">
            <div id="viewModalBody">
                <!-- Populated by JS -->
            </div>
        </div>

        <div class="rr-modal-footer">
            <button class="btn-rr-cancel" id="btnCloseViewModalBottom">Close</button>
            <button class="btn-rr-update" id="btnUpdateFromView">
                <i class="fa-solid fa-pen-to-square"></i> Update Status
            </button>
        </div>

    </div>
</div>


<!-- ══════════════════════════════════════════════════════
     MODAL — UPDATE STATUS
═══════════════════════════════════════════════════════ -->
<div class="modal-backdrop" id="rrUpdateModal" role="dialog" aria-modal="true">
    <div class="modal-box" style="max-width: 500px;">

        <div class="modal-header">
            <div class="modal-title-group">
                <span class="modal-eyebrow">Status Management</span>
                <h2 class="modal-title">Update Report</h2>
            </div>
            <button class="modal-close" id="btnCloseUpdateModal">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <div class="modal-body">
            <form id="rrUpdateForm" novalidate>
                <input type="hidden" id="updateReportId">

                <div class="rr-form-grid">
                    <div class="rr-form-field full">
                        <div class="rr-update-report-info" id="updateReportInfo"></div>
                    </div>

                    <div class="rr-form-field full">
                        <label class="rr-form-label" for="updateStatus">
                            New Status <span class="required">*</span>
                        </label>
                        <div class="rr-status-buttons" id="updateStatusBtns">
                            <button type="button" class="rr-status-choice" data-value="Pending">
                                <i class="fa-solid fa-clock"></i> Pending
                            </button>
                            <button type="button" class="rr-status-choice" data-value="Verified">
                                <i class="fa-solid fa-check-circle"></i> Verified
                            </button>
                            <button type="button" class="rr-status-choice" data-value="In Progress">
                                <i class="fa-solid fa-spinner"></i> In Progress
                            </button>
                            <button type="button" class="rr-status-choice" data-value="Resolved">
                                <i class="fa-solid fa-circle-check"></i> Resolved
                            </button>
                            <button type="button" class="rr-status-choice" data-value="Dismissed">
                                <i class="fa-solid fa-ban"></i> Dismissed
                            </button>
                        </div>
                        <input type="hidden" id="updateStatus">
                    </div>

                    <div class="rr-form-field full">
                        <label class="rr-form-label" for="updateResolutionNotes">
                            Resolution Notes
                            <span style="font-weight:400;text-transform:none;letter-spacing:0;font-size:10px;color:var(--slate-mid);margin-left:4px">— optional</span>
                        </label>
                        <textarea class="rr-form-control" id="updateResolutionNotes"
                                  placeholder="Describe actions taken, findings, or reasons for dismissal…"
                                  rows="4"></textarea>
                        <span class="rr-form-hint">Previous notes will be preserved if left blank.</span>
                    </div>

                    <div class="rr-form-field full">
                        <label class="rr-form-label" for="updateTags">Tags / Case Categories</label>
                        <input type="text" class="rr-form-control" id="updateTags"
                               placeholder="e.g. Evacuation Needed, High Priority, PWD">
                        <span class="rr-form-hint">Separate tags with commas. Saving replaces the report's current tags.</span>
                    </div>
                </div>
            </form>
        </div>

        <div class="rr-modal-footer">
            <button class="btn-rr-cancel" id="btnCancelUpdate">Cancel</button>
            <button class="btn-rr-submit" id="btnSubmitUpdate" disabled>
                <i class="fa-solid fa-floppy-disk"></i>
                <span id="btnUpdateLabel">Save Changes</span>
            </button>
        </div>

    </div>
</div>


<!-- ══════════════════════════════════════════════════════
     MODAL — DELETE CONFIRMATION
═══════════════════════════════════════════════════════ -->
<div class="modal-backdrop" id="rrDeleteModal" role="dialog" aria-modal="true">
    <div class="modal-box" style="max-width: 420px;">

        <div class="modal-header">
            <div class="modal-title-group">
                <span class="modal-eyebrow">Confirmation Required</span>
                <h2 class="modal-title">Delete Report</h2>
            </div>
            <button class="modal-close" id="btnCloseDeleteModal">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <div class="modal-body">
            <div class="rr-confirm-body">
                <div class="rr-confirm-icon">
                    <i class="fa-solid fa-trash"></i>
                </div>
                <div class="rr-confirm-title">Are you sure?</div>
                <p class="rr-confirm-desc">
                    You are about to permanently delete the report submitted by
                    <span class="rr-confirm-target" id="deleteTargetLabel">this resident</span>.
                    This action cannot be undone.
                </p>
            </div>
        </div>

        <div class="rr-modal-footer">
            <button class="btn-rr-cancel" id="btnCancelDelete">Cancel</button>
            <button class="btn-rr-delete" id="btnConfirmDelete">
                <i class="fa-solid fa-trash"></i> Delete Report
            </button>
        </div>

    </div>
</div>

<!-- ══ IMAGE LIGHTBOX ════════════════════════════════════ -->
<div class="modal-backdrop" id="rrImgLightbox" role="dialog" aria-modal="true">
    <div class="sm-lightbox-box">
        <button class="modal-close" id="rrImgLightboxClose"
                style="position:absolute;top:12px;right:12px;z-index:2;" aria-label="Close">
            <i class="fa-solid fa-xmark"></i>
        </button>
        <img id="rrImgLightboxSrc" src="" alt="Street image preview">
        <div class="sm-lightbox-meta" id="rrImgLightboxMeta"></div>
    </div>
</div>


<!-- ══ TOAST CONTAINER ════════════════════════════════ -->
<div class="rr-toast-container" id="rrToastContainer"></div>


<!-- ══ SCRIPTS ════════════════════════════════════════ -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js"></script>
<script src="../js/activity-logs.js"></script>
<script src="../js/admin-resident-reports.js"></script>

</body>
</html>
