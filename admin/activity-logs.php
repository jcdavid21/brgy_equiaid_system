<?php
/**
 * activity-logs.php
 * Barangay EQUIAID — Admin: Activity Logs
 */

$current_page = 'activity-logs';
$page_title   = 'Activity Logs — Barangay EQUIAID';

if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['user_role']) || !in_array($_SESSION['user_role'], ['admin', 'superadmin'])) {
    header("Location: ../index.php");
    exit();
}

$is_superadmin = ($_SESSION['user_role'] === 'superadmin');
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
    <link rel="stylesheet" href="../styles/admin-activity-logs.css">
    <link rel="stylesheet" href="../styles/admin-resident-reports.css">
</head>
<body>

<div class="admin-shell">

    <!-- ══ SIDEBAR ══════════════════════════════════════════ -->
    <?php include 'sidebar.php'; ?>

    <!-- ══ MAIN CONTENT ═════════════════════════════════════ -->
    <main class="main-content" id="mainContent">
        <div class="dashboard-container">

            <!-- ── PAGE HEADER ──────────────────────────────── -->
            <div class="dash-header">
                <div class="dash-header-left">
                    <div class="dash-breadcrumb">
                        <i class="fa-solid fa-scroll"></i>
                        <span>Activity Logs</span>
                    </div>
                    <h1 class="dash-title">Activity Logs</h1>
                    <p class="dash-desc">
                        Full audit trail of all system actions performed by administrators and staff.
                        Monitor user activity, track changes, and investigate events.
                    </p>
                </div>
                <div class="dash-header-right">
                    <button class="btn-rr-export" id="btnExportCsv">
                        <i class="fa-solid fa-file-csv"></i>
                        Export CSV
                    </button>
                </div>
            </div>

            <!-- ── KPI STRIP ────────────────────────────────── -->
            <div class="al-kpi-grid">
                <div class="kpi-card">
                    <div class="kpi-icon-wrap neutral"><i class="fa-solid fa-list-check"></i></div>
                    <div class="kpi-body">
                        <span class="kpi-label">Total Logs</span>
                        <span class="kpi-number sk-inline" id="kpiTotal">—</span>
                        <span class="kpi-sub">All time</span>
                    </div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-icon-wrap mid"><i class="fa-solid fa-calendar-day"></i></div>
                    <div class="kpi-body">
                        <span class="kpi-label">Today</span>
                        <span class="kpi-number sk-inline" id="kpiToday">—</span>
                        <span class="kpi-sub">Actions today</span>
                    </div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-icon-wrap dark"><i class="fa-solid fa-clock-rotate-left"></i></div>
                    <div class="kpi-body">
                        <span class="kpi-label">Last 7 Days</span>
                        <span class="kpi-number sk-inline" id="kpiWeek">—</span>
                        <span class="kpi-sub">Recent activity</span>
                    </div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-icon-wrap neutral"><i class="fa-solid fa-users"></i></div>
                    <div class="kpi-body">
                        <span class="kpi-label">Active Users</span>
                        <span class="kpi-number sk-inline" id="kpiUsers">—</span>
                        <span class="kpi-sub">Users w/ activity</span>
                    </div>
                </div>
            </div>

            <!-- ── LOGS TABLE ────────────────────────────────── -->
            <div class="dash-card al-table-card">
                <div class="card-head">
                    <div>
                        <div class="card-label">Audit Trail</div>
                        <div class="card-title">System Activity Logs</div>
                    </div>
                </div>

                <!-- Toolbar -->
                <div class="rr-toolbar">
                    <!-- Search -->
                    <div class="rr-search-wrap">
                        <i class="fa-solid fa-magnifying-glass"></i>
                        <input type="text" class="rr-search" id="alSearch"
                               placeholder="Search action, user, module…">
                    </div>

                    <!-- User filter (populated by JS) -->
                    <select class="rr-filter-select" id="filterUser">
                        <option value="">All Users</option>
                    </select>

                    <!-- Module filter (populated by JS) -->
                    <select class="rr-filter-select" id="filterModule">
                        <option value="">All Modules</option>
                    </select>

                    <!-- Date range -->
                    <input type="date" class="rr-filter-select" id="filterDateFrom"
                           title="From date" aria-label="From date">
                    <input type="date" class="rr-filter-select" id="filterDateTo"
                           title="To date" aria-label="To date">

                    <!-- Sort direction toggle -->
                    <button class="btn-rr-export" id="btnToggleSort" title="Toggle sort direction">
                        <i class="fa-solid fa-arrow-down-wide-short" id="sortIcon"></i>
                        <span id="sortLabel">Newest</span>
                    </button>

                    <div class="rr-toolbar-spacer"></div>
                    <span id="alRecordCount" class="table-info"></span>
                </div>

                <!-- Table -->
                <div class="table-wrap">
                    <table class="dashboard-table" id="alTable">
                        <thead>
                            <tr>
                                <th class="al-col-num">#</th>
                                <th class="al-col-user">User / Staff</th>
                                <th class="al-col-module">Module</th>
                                <th class="al-col-action">Action Performed</th>
                                <th class="al-col-ip">IP Address</th>
                                <th class="al-col-date">Date &amp; Time</th>
                                <?php if ($is_superadmin): ?>
                                <th class="al-col-actions">Actions</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody id="alTableBody">
                            <tr>
                                <td colspan="<?= $is_superadmin ? 7 : 6 ?>" class="tbl-loading">
                                    <i class="fa-solid fa-circle-notch fa-spin"></i>&nbsp; Loading logs…
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div class="table-footer">
                    <span id="alPaginationInfo" class="table-info"></span>
                    <div class="table-pagination" id="alPagination"></div>
                </div>

            </div><!-- /.dash-card -->
        </div><!-- /.dashboard-container -->
    </main>
</div><!-- /.admin-shell -->


<!-- ══════════════════════════════════════════════════════════
     MODAL — VIEW LOG DETAILS
══════════════════════════════════════════════════════════ -->
<div class="modal-backdrop" id="alViewModal" role="dialog" aria-modal="true" aria-labelledby="alViewModalTitle">
    <div class="modal-box al-modal-box">

        <div class="modal-header">
            <div class="modal-title-group">
                <span class="modal-eyebrow">Log Entry</span>
                <h2 class="modal-title" id="alViewModalTitle">Activity Detail</h2>
            </div>
            <button class="modal-close" id="btnCloseViewModal" aria-label="Close modal">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <div class="modal-body">
            <div id="alViewModalBody"><!-- Populated by JS --></div>
        </div>

        <div class="rr-modal-footer">
            <button class="btn-rr-cancel" id="btnCloseViewModalBottom">Close</button>
        </div>

    </div>
</div>


<!-- ══════════════════════════════════════════════════════════
     MODAL — DELETE CONFIRMATION  (superadmin only)
══════════════════════════════════════════════════════════ -->
<?php if ($is_superadmin): ?>
<div class="modal-backdrop" id="alDeleteModal" role="dialog" aria-modal="true">
    <div class="modal-box" style="max-width: 420px;">

        <div class="modal-header">
            <div class="modal-title-group">
                <span class="modal-eyebrow">Confirmation Required</span>
                <h2 class="modal-title">Delete Log Entry</h2>
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
                <div class="rr-confirm-title">Delete this log entry?</div>
                <p class="rr-confirm-desc">
                    You are about to permanently delete log entry
                    <span class="rr-confirm-target" id="deleteTargetLabel">#—</span>.
                    This action cannot be undone and may affect audit integrity.
                </p>
            </div>
        </div>

        <div class="rr-modal-footer">
            <button class="btn-rr-cancel" id="btnCancelDelete">Cancel</button>
            <button class="btn-rr-delete" id="btnConfirmDelete">
                <i class="fa-solid fa-trash"></i> Delete Entry
            </button>
        </div>

    </div>
</div>
<?php endif; ?>


<!-- ══ TOAST CONTAINER ══════════════════════════════════════ -->
<div class="rr-toast-container" id="alToastContainer"></div>


<!-- ══ SCRIPTS ══════════════════════════════════════════════ -->
<script>
    /* Expose PHP session flags to JS without a separate AJAX call */
    window.AL_CONFIG = {
        apiUrl:        '../backend/admin-activity-logs.php',
        isSuperadmin:  <?= $is_superadmin ? 'true' : 'false' ?>,
        colSpan:       <?= $is_superadmin ? 7 : 6 ?>,
    };
</script>
<script src="../js/admin-activity-logs.js"></script>

</body>
</html>