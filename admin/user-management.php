<?php
/**
 * user-management.php
 * Barangay EQUIAID — Admin: User Management
 */

$current_page = 'user-management';
$page_title   = 'User Management — Barangay EQUIAID';

if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['user_role']) || !in_array($_SESSION['user_role'], ['admin', 'superadmin'])) {
    header("Location: ../index.php");
    exit();
}

$isSuperAdmin = $_SESSION['user_role'] === 'superadmin';
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
    <link rel="stylesheet" href="../styles/admin-user-management.css">
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
                        <i class="fa-solid fa-users-gear"></i>
                        <span>User Management</span>
                    </div>
                    <h1 class="dash-title">User Management</h1>
                    <p class="dash-desc">
                        Create, update, and manage system accounts. Control roles, access levels,
                        and account status for all EQUIAID personnel and residents.
                    </p>
                </div>
                <div class="dash-header-right">
                    <button class="btn-um-create" id="btnCreateUser">
                        <i class="fa-solid fa-user-plus"></i>
                        New User
                    </button>
                </div>
            </div>

            <!-- ── KPI STRIP ───────────────────────────── -->
            <div class="um-kpi-grid">
                <div class="kpi-card">
                    <div class="kpi-icon-wrap neutral"><i class="fa-solid fa-users"></i></div>
                    <div class="kpi-body">
                        <span class="kpi-label">Total Users</span>
                        <span class="kpi-number sk-inline" id="kpiTotal">—</span>
                        <span class="kpi-sub">All accounts</span>
                    </div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-icon-wrap dark"><i class="fa-solid fa-circle-check"></i></div>
                    <div class="kpi-body">
                        <span class="kpi-label">Active</span>
                        <span class="kpi-number sk-inline" id="kpiActive">—</span>
                        <span class="kpi-sub">Enabled accounts</span>
                    </div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-icon-wrap mid"><i class="fa-solid fa-user-slash"></i></div>
                    <div class="kpi-body">
                        <span class="kpi-label">Inactive</span>
                        <span class="kpi-number sk-inline" id="kpiInactive">—</span>
                        <span class="kpi-sub">Disabled accounts</span>
                    </div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-icon-wrap neutral"><i class="fa-solid fa-user-shield"></i></div>
                    <div class="kpi-body">
                        <span class="kpi-label">Admins</span>
                        <span class="kpi-number sk-inline" id="kpiAdmins">—</span>
                        <span class="kpi-sub">Admin &amp; superadmin</span>
                    </div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-icon-wrap neutral"><i class="fa-solid fa-clock-rotate-left"></i></div>
                    <div class="kpi-body">
                        <span class="kpi-label">Recent Logins</span>
                        <span class="kpi-number sk-inline" id="kpiRecent">—</span>
                        <span class="kpi-sub">Active last 30 days</span>
                    </div>
                </div>
            </div>

            <!-- ── USERS TABLE ──────────────────────────── -->
            <div class="dash-card um-table-card">
                <div class="card-head">
                    <div>
                        <div class="card-label">Management</div>
                        <div class="card-title">System Accounts</div>
                    </div>
                </div>

                <!-- Toolbar -->
                <div class="um-toolbar">
                    <div class="um-search-wrap">
                        <i class="fa-solid fa-magnifying-glass"></i>
                        <input type="text" class="um-search" id="umSearch"
                               placeholder="Search name, email, phone…">
                    </div>
                    <select class="um-filter-select" id="filterRole">
                        <option value="">All Roles</option>
                        <option value="superadmin">Superadmin</option>
                        <option value="admin">Admin</option>
                        <option value="staff">Staff</option>
                        <option value="dswd_officer">DSWD Officer</option>
                        <option value="labeler">Labeler</option>
                        <option value="resident">Resident</option>
                    </select>
                    <select class="um-filter-select" id="filterStatus">
                        <option value="">All Status</option>
                        <option value="1">Active</option>
                        <option value="0">Inactive</option>
                    </select>
                    <div class="um-toolbar-spacer"></div>
                    <span id="umRecordCount" class="table-info"></span>
                </div>

                <!-- Table -->
                <div class="table-wrap">
                    <table class="dashboard-table" id="umTable">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>User</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th>Phone</th>
                                <th>Last Login</th>
                                <th>Member Since</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="umTableBody">
                            <tr>
                                <td colspan="8" class="tbl-loading">
                                    <i class="fa-solid fa-circle-notch fa-spin"></i>&nbsp; Loading users…
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div class="table-footer">
                    <span id="umPaginationInfo" class="table-info"></span>
                    <div class="table-pagination" id="umPagination"></div>
                </div>

            </div><!-- /.dash-card -->
        </div><!-- /.dashboard-container -->
    </main>
</div><!-- /.admin-shell -->


<!-- ══════════════════════════════════════════════════════
     MODAL — CREATE / EDIT USER
═══════════════════════════════════════════════════════ -->
<div class="modal-backdrop" id="umFormModal" role="dialog" aria-modal="true">
    <div class="modal-box um-modal-box">

        <div class="modal-header">
            <div class="modal-title-group">
                <span class="modal-eyebrow" id="umModalEyebrow">New Account</span>
                <h2 class="modal-title" id="umModalTitle">Create User</h2>
            </div>
            <button class="modal-close" id="btnCloseFormModal" aria-label="Close">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <div class="modal-body">
            <form id="umForm" novalidate>
                <input type="hidden" id="formUserId">

                <div class="um-form-grid">

                    <!-- Section: Identity -->
                    <div class="um-form-section">Account Identity</div>

                    <div class="um-form-field">
                        <label class="um-form-label" for="formName">
                            Full Name <span class="required">*</span>
                        </label>
                        <input type="text" class="um-form-control" id="formName"
                               name="name" placeholder="e.g. Maria Santos" autocomplete="off">
                    </div>

                    <div class="um-form-field">
                        <label class="um-form-label" for="formEmail">
                            Email Address <span class="required">*</span>
                        </label>
                        <input type="email" class="um-form-control" id="formEmail"
                               name="email" placeholder="e.g. maria@equiaid.gov.ph" autocomplete="off">
                    </div>

                    <div class="um-form-field">
                        <label class="um-form-label" for="formPhone">Phone Number</label>
                        <input type="text" class="um-form-control" id="formPhone"
                               name="phone_number" placeholder="e.g. 09171234567">
                    </div>

                    <div class="um-form-field">
                        <label class="um-form-label" for="formRole">
                            Role <span class="required">*</span>
                        </label>
                        <select class="um-form-control" id="formRole" name="role">
                            <option value="resident">Resident</option>
                            <option value="labeler">Labeler</option>
                            <option value="staff">Staff</option>
                            <option value="dswd_officer">DSWD Officer</option>
                            <?php if ($isSuperAdmin): ?>
                            <option value="admin">Admin</option>
                            <option value="superadmin">Superadmin</option>
                            <?php endif; ?>
                        </select>
                    </div>

                    <div class="um-form-field">
                        <label class="um-form-label" for="formIsActive">Account Status</label>
                        <select class="um-form-control" id="formIsActive" name="is_active">
                            <option value="1">Active</option>
                            <option value="0">Inactive</option>
                        </select>
                    </div>

                    <!-- Section: Password -->
                    <div class="um-form-section" id="passwordSection">Password</div>

                    <div class="um-form-field full" id="passwordFieldWrap">
                        <label class="um-form-label" for="formPassword">
                            Password
                            <span id="passwordHint" style="font-weight:400;text-transform:none;letter-spacing:0;font-size:10px;color:var(--slate-mid);margin-left:4px">— required for new users; leave blank to keep current</span>
                        </label>
                        <div class="um-password-wrap">
                            <input type="password" class="um-form-control" id="formPassword"
                                   name="password" placeholder="Min. 8 characters" autocomplete="new-password">
                            <button type="button" class="um-pwd-toggle" id="btnTogglePwd" tabindex="-1">
                                <i class="fa-solid fa-eye"></i>
                            </button>
                        </div>
                        <span class="um-form-hint">Passwords are stored as bcrypt hashes.</span>
                    </div>

                </div><!-- /.um-form-grid -->
            </form>
        </div><!-- /.modal-body -->

        <div class="um-modal-footer">
            <button class="btn-um-cancel" id="btnCancelForm">Cancel</button>
            <button class="btn-um-submit" id="btnSubmitForm">
                <i class="fa-solid fa-floppy-disk"></i>
                <span id="btnSubmitLabel">Create User</span>
            </button>
        </div>

    </div>
</div>


<!-- ══════════════════════════════════════════════════════
     MODAL — VIEW USER DETAILS
═══════════════════════════════════════════════════════ -->
<div class="modal-backdrop" id="umViewModal" role="dialog" aria-modal="true">
    <div class="modal-box um-modal-box">

        <div class="modal-header">
            <div class="modal-title-group">
                <span class="modal-eyebrow">Account Details</span>
                <h2 class="modal-title" id="umViewTitle">User Profile</h2>
            </div>
            <button class="modal-close" id="btnCloseViewModal" aria-label="Close">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <div class="modal-body">
            <div id="umViewBody">
                <!-- Populated by JS -->
            </div>
        </div>

        <div class="um-modal-footer">
            <button class="btn-um-cancel" id="btnCloseViewModalBottom">Close</button>
            <button class="btn-um-submit" id="btnEditFromView">
                <i class="fa-solid fa-pen"></i> Edit User
            </button>
        </div>

    </div>
</div>


<!-- ══════════════════════════════════════════════════════
     MODAL — DELETE / REMOVE CONFIRMATION
═══════════════════════════════════════════════════════ -->
<div class="modal-backdrop" id="umDeleteModal" role="dialog" aria-modal="true">
    <div class="modal-box" style="max-width: 420px;">

        <div class="modal-header">
            <div class="modal-title-group">
                <span class="modal-eyebrow">Confirmation Required</span>
                <h2 class="modal-title">Remove User</h2>
            </div>
            <button class="modal-close" id="btnCloseDeleteModal">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <div class="modal-body">
            <div class="um-confirm-body">
                <div class="um-confirm-icon">
                    <i class="fa-solid fa-user-xmark"></i>
                </div>
                <div class="um-confirm-title">Remove this user?</div>
                <p class="um-confirm-desc">
                    <span class="um-confirm-target" id="deleteTargetLabel">This user</span>'s
                    account will be anonymised and deactivated. Their submitted reports and
                    linked records will be preserved. This action cannot be undone.
                </p>
            </div>
        </div>

        <div class="um-modal-footer">
            <button class="btn-um-cancel" id="btnCancelDelete">Cancel</button>
            <button class="btn-um-delete" id="btnConfirmDelete">
                <i class="fa-solid fa-user-xmark"></i> Remove User
            </button>
        </div>

    </div>
</div>


<!-- ══ TOAST CONTAINER ════════════════════════════════ -->
<div class="um-toast-container" id="umToastContainer"></div>

<!-- ══ Pass session role to JS ════════════════════════ -->
<script>
    window.CURRENT_USER_ROLE = <?= json_encode($_SESSION['user_role'] ?? 'admin') ?>;
    window.CURRENT_USER_ID   = <?= json_encode((int)($_SESSION['user_id'] ?? 0)) ?>;
</script>

<!-- ══ SCRIPTS ════════════════════════════════════════ -->
<script src="../js/admin-user-management.js"></script>

</body>
</html>