<?php

$current_page = 'welfare-action';
$page_title   = 'Welfare Action Plan — Barangay EQUIAID';

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
    <link rel="stylesheet" href="../styles/admin-welfare-action.css">
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
                        <i class="fa-solid fa-file-shield"></i>
                        <span>Welfare Action Plan</span>
                    </div>
                    <h1 class="dash-title">Welfare Action Plan</h1>
                    <p class="dash-desc">
                        Plan, track, and manage welfare programs and interventions for identified
                        individuals, families, and streets in need.
                    </p>
                </div>
                <div class="dash-header-right">
                    <button class="btn-wap-create" id="btnCreatePlan">
                        <i class="fa-solid fa-plus"></i>
                        New Action Plan
                    </button>
                </div>
            </div>

            <!-- ── KPI STRIP ───────────────────────────── -->
            <div class="wap-kpi-grid" id="wapKpiGrid">
                <div class="kpi-card">
                    <div class="kpi-icon-wrap neutral"><i class="fa-solid fa-clipboard-list"></i></div>
                    <div class="kpi-body">
                        <span class="kpi-label">Total Plans</span>
                        <span class="kpi-number sk-inline" id="kpiTotal">—</span>
                        <span class="kpi-sub">All action plans</span>
                    </div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-icon-wrap dark"><i class="fa-solid fa-hourglass-half"></i></div>
                    <div class="kpi-body">
                        <span class="kpi-label">Planned</span>
                        <span class="kpi-number sk-inline" id="kpiPlanned">—</span>
                        <span class="kpi-sub">Awaiting start</span>
                    </div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-icon-wrap mid"><i class="fa-solid fa-spinner"></i></div>
                    <div class="kpi-body">
                        <span class="kpi-label">Ongoing</span>
                        <span class="kpi-number sk-inline" id="kpiOngoing">—</span>
                        <span class="kpi-sub">In progress</span>
                    </div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-icon-wrap neutral"><i class="fa-solid fa-circle-check"></i></div>
                    <div class="kpi-body">
                        <span class="kpi-label">Completed</span>
                        <span class="kpi-number sk-inline" id="kpiCompleted">—</span>
                        <span class="kpi-sub">Successfully done</span>
                    </div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-icon-wrap neutral"><i class="fa-solid fa-triangle-exclamation"></i></div>
                    <div class="kpi-body">
                        <span class="kpi-label">High Priority</span>
                        <span class="kpi-number sk-inline" id="kpiHighPriority">—</span>
                        <span class="kpi-sub">Urgent cases</span>
                    </div>
                </div>
            </div>

            <!-- ── PLANS TABLE ──────────────────────────── -->
            <div class="dash-card wap-table-card">
                <div class="card-head">
                    <div>
                        <div class="card-label">Management</div>
                        <div class="card-title">Action Plans</div>
                    </div>
                </div>

                <!-- Toolbar -->
                <div class="wap-toolbar">
                    <div class="wap-search-wrap">
                        <i class="fa-solid fa-magnifying-glass"></i>
                        <input type="text" class="wap-search" id="wapSearch" placeholder="Search plans, streets, types…">
                    </div>
                    <select class="wap-filter-select" id="filterStatus">
                        <option value="">All Status</option>
                        <option value="Planned">Planned</option>
                        <option value="Ongoing">Ongoing</option>
                        <option value="Completed">Completed</option>
                        <option value="Cancelled">Cancelled</option>
                    </select>
                    <select class="wap-filter-select" id="filterPriority">
                        <option value="">All Priority</option>
                        <option value="High">High</option>
                        <option value="Medium">Medium</option>
                        <option value="Low">Low</option>
                    </select>
                    <select class="wap-filter-select" id="filterType">
                        <option value="">All Types</option>
                        <option value="Food Distribution">Food Distribution</option>
                        <option value="Medical Assistance">Medical Assistance</option>
                        <option value="Financial Aid">Financial Aid</option>
                        <option value="Shelter Repair">Shelter Repair</option>
                        <option value="Water Supply">Water Supply</option>
                        <option value="Livelihood Support">Livelihood Support</option>
                        <option value="Psychosocial Support">Psychosocial Support</option>
                        <option value="Other">Other</option>
                    </select>
                    <div class="wap-toolbar-spacer"></div>
                    <span id="wapRecordCount" class="table-info"></span>
                </div>

                <!-- Table -->
                <div class="table-wrap">
                    <table class="dashboard-table" id="wapTable">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Beneficiary / Street</th>
                                <th>Assistance Type</th>
                                <th>Needs</th>
                                <th>Priority</th>
                                <th>Status</th>
                                <th>Assigned To</th>
                                <th>Planned Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="wapTableBody">
                            <tr>
                                <td colspan="9" class="tbl-loading">
                                    <i class="fa-solid fa-circle-notch fa-spin"></i>&nbsp; Loading plans…
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Footer / Pagination -->
                <div class="table-footer">
                    <span class="table-info" id="wapPaginationInfo">Showing — plans</span>
                    <div class="pagination" id="wapPagination"></div>
                </div>
            </div>

        </div><!-- /.dashboard-container -->
    </main>

</div><!-- /.admin-shell -->

<!-- ══════════════════════════════════════════════════════
     MODAL — CREATE / EDIT PLAN
═══════════════════════════════════════════════════════ -->
<div class="modal-backdrop" id="wapFormModal" role="dialog" aria-modal="true" aria-labelledby="wapModalTitle">
    <div class="modal-box wap-modal-box">

        <!-- Header -->
        <div class="modal-header">
            <div class="modal-title-group">
                <span class="modal-eyebrow" id="wapModalEyebrow">New Plan</span>
                <h2 class="modal-title" id="wapModalTitle">Create Welfare Action Plan</h2>
            </div>
            <button class="modal-close" id="btnCloseFormModal" aria-label="Close modal">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <!-- Body -->
        <div class="modal-body">
            <form id="wapForm" novalidate>
                <input type="hidden" id="formPlanId" name="plan_id" value="">

                <div class="wap-form-grid">

                    <!-- Section: Beneficiary -->
                    <div class="wap-form-section">Beneficiary Information</div>

                    <div class="wap-form-field">
                        <label class="wap-form-label" for="formStreetId">
                            Street / Location <span class="required">*</span>
                        </label>
                        <select class="wap-form-control" id="formStreetId" name="street_id" required>
                            <option value="">— Select street —</option>
                        </select>
                    </div>

                    <div class="wap-form-field">
                        <label class="wap-form-label" for="formBeneficiaryType">Beneficiary Type</label>
                        <select class="wap-form-control" id="formBeneficiaryType" name="beneficiary_type">
                            <option value="street">Entire Street</option>
                            <option value="family">Family / Household</option>
                            <option value="individual">Individual</option>
                        </select>
                    </div>

                    <div class="wap-form-field full">
                        <label class="wap-form-label" for="formBeneficiaryName">
                            Beneficiary Name <span style="font-weight:400;text-transform:none;letter-spacing:0;">(if specific person or family)</span>
                        </label>
                        <input type="text" class="wap-form-control" id="formBeneficiaryName" name="beneficiary_name"
                               placeholder="e.g. Santos Family, Juan dela Cruz">
                    </div>

                    <!-- Section: Intervention -->
                    <div class="wap-form-section">Intervention Details</div>

                    <div class="wap-form-field">
                        <label class="wap-form-label" for="formAssistanceType">
                            Assistance Type <span class="required">*</span>
                        </label>
                        <select class="wap-form-control" id="formAssistanceType" name="assistance_type" required>
                            <option value="">— Select type —</option>
                            <option value="Food Distribution">Food Distribution</option>
                            <option value="Medical Assistance">Medical Assistance</option>
                            <option value="Financial Aid">Financial Aid</option>
                            <option value="Shelter Repair">Shelter Repair</option>
                            <option value="Water Supply">Water Supply</option>
                            <option value="Livelihood Support">Livelihood Support</option>
                            <option value="Psychosocial Support">Psychosocial Support</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>

                    <div class="wap-form-field">
                        <label class="wap-form-label" for="formEventId">Related Typhoon Event</label>
                        <select class="wap-form-control" id="formEventId" name="event_id">
                            <option value="">— None / General —</option>
                        </select>
                    </div>

                    <div class="wap-form-field full">
                        <label class="wap-form-label">Identified Needs</label>
                        <div class="wap-needs-checks" id="needsChecks">
                            <label class="wap-need-check-label">
                                <input type="checkbox" name="needs[]" value="food"> <i class="fa-solid fa-utensils" style="font-size:10px"></i> Food
                            </label>
                            <label class="wap-need-check-label">
                                <input type="checkbox" name="needs[]" value="medical"> <i class="fa-solid fa-briefcase-medical" style="font-size:10px"></i> Medical
                            </label>
                            <label class="wap-need-check-label">
                                <input type="checkbox" name="needs[]" value="financial"> <i class="fa-solid fa-peso-sign" style="font-size:10px"></i> Financial
                            </label>
                            <label class="wap-need-check-label">
                                <input type="checkbox" name="needs[]" value="shelter"> <i class="fa-solid fa-house" style="font-size:10px"></i> Shelter
                            </label>
                            <label class="wap-need-check-label">
                                <input type="checkbox" name="needs[]" value="water"> <i class="fa-solid fa-droplet" style="font-size:10px"></i> Water
                            </label>
                            <label class="wap-need-check-label">
                                <input type="checkbox" name="needs[]" value="livelihood"> <i class="fa-solid fa-briefcase" style="font-size:10px"></i> Livelihood
                            </label>
                        </div>
                    </div>

                    <div class="wap-form-field full">
                        <label class="wap-form-label" for="formDescription">Description</label>
                        <textarea class="wap-form-control" id="formDescription" name="description"
                                  placeholder="Describe the welfare plan and its objectives…"></textarea>
                    </div>

                    <!-- Action Steps -->
                    <div class="wap-form-field full">
                        <label class="wap-form-label">Action Steps / Interventions</label>
                        <div class="wap-steps-list" id="stepsContainer"></div>
                        <button type="button" class="btn-wap-add-step" id="btnAddStep">
                            <i class="fa-solid fa-plus"></i> Add Step
                        </button>
                    </div>

                    <!-- Section: Assignment & Status -->
                    <div class="wap-form-section">Assignment & Schedule</div>

                    <div class="wap-form-field">
                        <label class="wap-form-label" for="formAssignedTo">Assigned Personnel</label>
                        <select class="wap-form-control" id="formAssignedTo" name="assigned_to">
                            <option value="">— Unassigned —</option>
                        </select>
                    </div>

                    <div class="wap-form-field">
                        <label class="wap-form-label" for="formPriority">Priority Level</label>
                        <select class="wap-form-control" id="formPriority" name="priority">
                            <option value="Medium">Medium</option>
                            <option value="High">High</option>
                            <option value="Low">Low</option>
                        </select>
                    </div>

                    <div class="wap-form-field">
                        <label class="wap-form-label" for="formStatus">Status</label>
                        <select class="wap-form-control" id="formStatus" name="status">
                            <option value="Planned">Planned</option>
                            <option value="Ongoing">Ongoing</option>
                            <option value="Completed">Completed</option>
                            <option value="Cancelled">Cancelled</option>
                        </select>
                    </div>

                    <div class="wap-form-field">
                        <label class="wap-form-label" for="formRiskBefore">Risk Level (Before)</label>
                        <select class="wap-form-control" id="formRiskBefore" name="risk_level_before">
                            <option value="">— Unknown —</option>
                            <option value="RED">RED — Critical</option>
                            <option value="ORANGE">ORANGE — High</option>
                            <option value="YELLOW">YELLOW — Moderate</option>
                            <option value="GREEN">GREEN — Low</option>
                        </select>
                    </div>

                    <div class="wap-form-field">
                        <label class="wap-form-label" for="formRiskAfter">
                            Risk Level (After)
                            <span id="riskAfterHint" style="font-weight:400;text-transform:none;letter-spacing:0;color:var(--slate-mid);font-size:10px;margin-left:4px">— available when Completed</span>
                        </label>
                        <select class="wap-form-control" id="formRiskAfter" name="risk_level_after" disabled>
                            <option value="">— Unknown —</option>
                            <option value="RED">RED — Critical</option>
                            <option value="ORANGE">ORANGE — High</option>
                            <option value="YELLOW">YELLOW — Moderate</option>
                            <option value="GREEN">GREEN — Low</option>
                        </select>
                    </div>

                    <div class="wap-form-field">
                        <label class="wap-form-label" for="formPlannedDate">
                            Planned / Start Date <span class="required">*</span>
                        </label>
                        <input type="date" class="wap-form-control" id="formPlannedDate" name="planned_date" required>
                    </div>

                    <div class="wap-form-field">
                        <label class="wap-form-label" for="formTargetDate">Target Completion Date</label>
                        <input type="date" class="wap-form-control" id="formTargetDate" name="target_date">
                    </div>

                    <div class="wap-form-field full">
                        <label class="wap-form-label" for="formRemarks">Remarks / Notes</label>
                        <textarea class="wap-form-control" id="formRemarks" name="remarks"
                                  placeholder="Any additional notes, constraints, or observations…" rows="3"></textarea>
                    </div>

                </div><!-- /.wap-form-grid -->
            </form>
        </div><!-- /.modal-body -->

        <!-- Footer -->
        <div class="wap-modal-footer">
            <button class="btn-wap-cancel" id="btnCancelForm">Cancel</button>
            <button class="btn-wap-submit" id="btnSubmitForm">
                <i class="fa-solid fa-floppy-disk"></i>
                <span id="btnSubmitLabel">Save Plan</span>
            </button>
        </div>

    </div><!-- /.modal-box -->
</div><!-- /.modal-backdrop -->


<!-- ══════════════════════════════════════════════════════
     MODAL — VIEW PLAN DETAILS
═══════════════════════════════════════════════════════ -->
<div class="modal-backdrop" id="wapViewModal" role="dialog" aria-modal="true">
    <div class="modal-box wap-modal-box">

        <div class="modal-header">
            <div class="modal-title-group">
                <span class="modal-eyebrow">Plan Details</span>
                <h2 class="modal-title" id="viewModalTitle">Action Plan</h2>
            </div>
            <button class="modal-close" id="btnCloseViewModal" aria-label="Close">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <div class="modal-body">
            <div class="wap-view-grid" id="viewModalBody">
                <!-- Populated by JS -->
            </div>
        </div>

        <div class="wap-modal-footer">
            <button class="btn-wap-cancel" id="btnCloseViewModalBottom">Close</button>
            <button class="btn-wap-submit" id="btnEditFromView">
                <i class="fa-solid fa-pen"></i> Edit Plan
            </button>
        </div>

    </div>
</div>


<!-- ══════════════════════════════════════════════════════
     MODAL — DELETE CONFIRMATION
═══════════════════════════════════════════════════════ -->
<div class="modal-backdrop" id="wapDeleteModal" role="dialog" aria-modal="true">
    <div class="modal-box" style="max-width:420px">

        <div class="modal-header">
            <div class="modal-title-group">
                <span class="modal-eyebrow">Confirmation Required</span>
                <h2 class="modal-title">Delete Plan</h2>
            </div>
            <button class="modal-close" id="btnCloseDeleteModal">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <div class="modal-body">
            <div class="wap-confirm-body">
                <div class="wap-confirm-icon">
                    <i class="fa-solid fa-trash"></i>
                </div>
                <div class="wap-confirm-title">Are you sure?</div>
                <p class="wap-confirm-desc">
                    You are about to permanently delete the plan for
                    <span class="wap-confirm-target" id="deleteTargetLabel">this beneficiary</span>.
                    This action cannot be undone.
                </p>
            </div>
        </div>

        <div class="wap-modal-footer">
            <button class="btn-wap-cancel" id="btnCancelDelete">Cancel</button>
            <button class="btn-wap-delete" id="btnConfirmDelete">
                <i class="fa-solid fa-trash"></i> Delete Plan
            </button>
        </div>

    </div>
</div>


<!-- ══ TOAST CONTAINER ════════════════════════════════ -->
<div class="wap-toast-container" id="wapToastContainer"></div>


<!-- ══ SCRIPTS ════════════════════════════════════════ -->
 <script src="../js/activity-logs.js"></script>
<script src="../js/admin-welfare-action.js"></script>

</body>
</html>