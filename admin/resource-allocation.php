<?php
$current_page = 'resource-allocation';
$page_title   = 'Resource Allocation — Barangay EQUIAID';

if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
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

    <!-- Base styles (shared tokens + layout) -->
    <link rel="stylesheet" href="../styles/admin_sidebar.css">
    <link rel="stylesheet" href="../styles/admin_dashboard.css">

    <!-- Page-specific styles -->
    <link rel="stylesheet" href="../styles/admin-resource-allocation.css">
</head>
<body>

<div class="admin-shell">
    <?php include 'sidebar.php'; ?>

    <main class="main-content" id="mainContent">
        <div class="dashboard-container">

            <!-- ══════════════════════════════════════════
                 PAGE HEADER
            ══════════════════════════════════════════ -->
            <div class="dash-header">
                <div class="dash-header-left">
                    <div class="dash-breadcrumb">
                        <i class="fa-solid fa-boxes-stacked"></i>
                        <span>Resource Allocation</span>
                    </div>
                    <h1 class="dash-title">Resource Allocation</h1>
                    <p class="dash-desc">
                        Manage welfare resource inventory, dispatch supplies to affected streets,
                        and track all distribution activity for Barangay Bagong Silang.
                    </p>
                </div>
                <div class="dash-header-right">
                    <div class="live-pill">
                        <span class="live-dot"></span>
                        Live Inventory
                    </div>
                    <button class="btn-ra-primary" id="raAddResourceBtn">
                        <i class="fa-solid fa-plus"></i> Add Resource
                    </button>
                </div>
            </div>

            <!-- ══════════════════════════════════════════
                 LOW STOCK ALERT BANNER
            ══════════════════════════════════════════ -->
            <div class="ra-alert-banner hidden" id="raAlertBanner">
                <i class="fa-solid fa-triangle-exclamation"></i>
                <span id="raAlertBannerText">Some resources are running low. </span>
                <a onclick="document.getElementById('raInvSection').scrollIntoView({behavior:'smooth'})">
                    View Inventory
                </a>
            </div>

            <!-- ══════════════════════════════════════════
                 SECTION 1 — KPI SUMMARY STRIP
            ══════════════════════════════════════════ -->
            <div class="ra-section-label">
                <i class="fa-solid fa-circle-dot"></i> Inventory Overview
            </div>

            <div class="ra-kpi-strip">

                <div class="kpi-card ra-kpi-sm">
                    <div class="kpi-icon-wrap" style="background:#0f1f3d;">
                        <i class="fa-solid fa-boxes-stacked" style="color:#fff;font-size:14px;"></i>
                    </div>
                    <div class="kpi-body">
                        <span class="kpi-label">Resource Types</span>
                        <span class="kpi-number sk-inline" id="raKpiTypes">—</span>
                        <span class="kpi-sub">types tracked</span>
                    </div>
                </div>

                <div class="kpi-card ra-kpi-sm">
                    <div class="kpi-icon-wrap" style="background:#15803d;">
                        <i class="fa-solid fa-warehouse" style="color:#fff;font-size:14px;"></i>
                    </div>
                    <div class="kpi-body">
                        <span class="kpi-label">Total Available</span>
                        <span class="kpi-number sk-inline" id="raKpiAvailable" style="color:#15803d;">—</span>
                        <span class="kpi-sub">units in stock</span>
                    </div>
                </div>

                <div class="kpi-card ra-kpi-sm">
                    <div class="kpi-icon-wrap" style="background:#0284c7;">
                        <i class="fa-solid fa-truck-fast" style="color:#fff;font-size:14px;"></i>
                    </div>
                    <div class="kpi-body">
                        <span class="kpi-label">Total Distributed</span>
                        <span class="kpi-number sk-inline" id="raKpiDistributed" style="color:#0284c7;">—</span>
                        <span class="kpi-sub">units dispatched</span>
                    </div>
                </div>

                <div class="kpi-card ra-kpi-sm">
                    <div class="kpi-icon-wrap" style="background:#7c3aed;">
                        <i class="fa-solid fa-peso-sign" style="color:#fff;font-size:14px;"></i>
                    </div>
                    <div class="kpi-body">
                        <span class="kpi-label">Stock Value</span>
                        <span class="kpi-number sk-inline" id="raKpiValue" style="color:#7c3aed;font-size:18px;">—</span>
                        <span class="kpi-sub">estimated value</span>
                    </div>
                </div>

                <div class="kpi-card ra-kpi-sm">
                    <div class="kpi-icon-wrap" style="background:#d97706;">
                        <i class="fa-solid fa-file-invoice-dollar" style="color:#fff;font-size:14px;"></i>
                    </div>
                    <div class="kpi-body">
                        <span class="kpi-label">Total Dist. Cost</span>
                        <span class="kpi-number sk-inline" id="raKpiDistCost" style="color:#d97706;font-size:18px;">—</span>
                        <span class="kpi-sub">all time</span>
                    </div>
                </div>

                <div class="kpi-card ra-kpi-sm">
                    <div class="kpi-icon-wrap" style="background:#059669;">
                        <i class="fa-solid fa-calendar-day" style="color:#fff;font-size:14px;"></i>
                    </div>
                    <div class="kpi-body">
                        <span class="kpi-label">Dispatches (Month)</span>
                        <span class="kpi-number sk-inline" id="raKpiDistMonth" style="color:#059669;">—</span>
                        <span class="kpi-sub">this month</span>
                    </div>
                </div>

            </div>

            <!-- ══════════════════════════════════════════
                 SECTION 2 — INVENTORY + DISPATCH (two col)
            ══════════════════════════════════════════ -->
            <div class="ra-two-col" id="raDispatchSection">

                <!-- ── LEFT: Inventory Table ─────────────── -->
                <div id="raInvSection">
                    <div class="ra-section-label">
                        <i class="fa-solid fa-warehouse"></i> Resource Inventory
                        <span class="ra-section-badge" id="raInvCount">—</span>
                    </div>

                    <!-- Toolbar -->
                    <div class="ra-toolbar">
                        <div class="ra-search-wrap">
                            <i class="fa-solid fa-magnifying-glass"></i>
                            <input type="text"
                                   id="raSearchInput"
                                   class="ra-input ra-search-input"
                                   placeholder="Search resources…"
                                   autocomplete="off">
                        </div>
                        <select id="raCatFilter" class="ra-select" style="width:auto;min-width:140px;flex-shrink:0;">
                            <option value="">All Categories</option>
                            <option value="Food">Food</option>
                            <option value="Medical">Medical</option>
                            <option value="Water">Water</option>
                            <option value="Shelter">Shelter</option>
                            <option value="Transport">Transport</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>

                    <!-- Inventory Table Card -->
                    <div class="dash-card" style="padding:0;overflow:hidden;">
                        <div class="table-wrap">
                            <table class="dashboard-table">
                                <thead>
                                    <tr>
                                        <th>Resource</th>
                                        <th>Category</th>
                                        <th>Unit</th>
                                        <th style="min-width:140px;">Available</th>
                                        <th>Distributed</th>
                                        <th>Unit Cost</th>
                                        <th>Status</th>
                                        <th style="text-align:right;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="raInvTbody">
                                    <tr><td colspan="8" class="ra-tbl-empty sk-loading" style="height:120px;"></td></tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="ra-table-footer">
                            <span class="ra-table-info" id="raInvCount2"></span>
                            <div class="ra-pagination" id="raInvPagination"></div>
                        </div>
                    </div>
                </div>

                <!-- ── RIGHT: Dispatch Panel ──────────────── -->
                <div>
                    <div class="ra-section-label">
                        <i class="fa-solid fa-truck-fast"></i> Dispatch Resource
                    </div>

                    <div class="dash-card ra-dispatch-card">
                        <form id="raDispatchForm" autocomplete="off">

                            <div class="ra-dispatch-field">
                                <label class="ra-dispatch-label" for="raDispResource">Resource *</label>
                                <select id="raDispResource" class="ra-select" required>
                                    <option value="">— Loading resources… —</option>
                                </select>
                                <span class="ra-dispatch-hint" id="raDispHint"></span>
                            </div>

                            <div class="ra-dispatch-field">
                                <label class="ra-dispatch-label" for="raDispStreet">Destination Street *</label>
                                <select id="raDispStreet" class="ra-select" required>
                                    <option value="">— Select Street —</option>
                                </select>
                            </div>

                            <div class="ra-dispatch-field">
                                <label class="ra-dispatch-label" for="raDispEvent">Linked Event (optional)</label>
                                <select id="raDispEvent" class="ra-select">
                                    <option value="">— None —</option>
                                </select>
                            </div>

                            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                                <div class="ra-dispatch-field">
                                    <label class="ra-dispatch-label" for="raDispQty">Quantity *</label>
                                    <input type="number" id="raDispQty" class="ra-input"
                                           min="1" placeholder="0" required>
                                </div>
                                <div class="ra-dispatch-field">
                                    <label class="ra-dispatch-label" for="raDispRecipients">Recipients (HH)</label>
                                    <input type="number" id="raDispRecipients" class="ra-input"
                                           min="0" placeholder="0">
                                </div>
                            </div>

                            <div class="ra-dispatch-field">
                                <label class="ra-dispatch-label" for="raDispDate">Date &amp; Time</label>
                                <input type="datetime-local" id="raDispDate" class="ra-input">
                            </div>

                            <div class="ra-dispatch-field">
                                <label class="ra-dispatch-label" for="raDispNotes">Notes</label>
                                <textarea id="raDispNotes" class="ra-textarea ra-input"
                                          placeholder="Optional remarks…" rows="2"></textarea>
                            </div>

                            <!-- Cost preview -->
                            <div class="ra-dispatch-total">
                                <span class="ra-dispatch-total-label"><i class="fa-solid fa-peso-sign" style="font-size:11px;"></i> Estimated Cost</span>
                                <span class="ra-dispatch-total-val" id="raDispTotal">—</span>
                            </div>

                            <button type="submit" class="btn-ra-primary" id="raDispatchSubmit" style="width:100%;justify-content:center;padding:10px;">
                                <i class="fa-solid fa-truck-fast"></i> Dispatch
                            </button>

                        </form>
                    </div>
                </div>

            </div><!-- /.ra-two-col -->

            <!-- ══════════════════════════════════════════
                 SECTION 3 — DISTRIBUTION LOG
            ══════════════════════════════════════════ -->
            <div class="ra-section-label" style="margin-top:8px;">
                <i class="fa-solid fa-clipboard-list"></i> Distribution Log
                <span class="ra-section-badge" id="raLogCount">—</span>
            </div>

            <!-- Log filters -->
            <div class="ra-dist-filters">
                <select id="raLogResFilter" class="ra-select ra-filter-select">
                    <option value="">All Resources</option>
                </select>
                <select id="raLogEvFilter" class="ra-select ra-filter-select">
                    <option value="">All Events</option>
                </select>
            </div>

            <div class="dash-card" style="padding:0;overflow:hidden;">
                <div class="table-wrap">
                    <table class="dashboard-table">
                        <thead>
                            <tr>
                                <th style="min-width:130px;">Date &amp; Time</th>
                                <th>Resource</th>
                                <th>Street</th>
                                <th>Event</th>
                                <th>Qty</th>
                                <th>Total Cost</th>
                                <th>Distributed By</th>
                                <th style="text-align:right;">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="raLogTbody">
                            <tr><td colspan="8" class="ra-tbl-empty sk-loading" style="height:100px;"></td></tr>
                        </tbody>
                    </table>
                </div>
                <div class="ra-table-footer">
                    <span class="ra-table-info" id="raLogCount2"></span>
                    <div class="ra-pagination" id="raLogPagination"></div>
                </div>
            </div>

        </div><!-- /.dashboard-container -->
    </main>
</div><!-- /.admin-shell -->


<!-- ══════════════════════════════════════════════════════
     ADD / EDIT RESOURCE MODAL
══════════════════════════════════════════════════════ -->
<div class="modal-backdrop" id="raResourceModal" role="dialog" aria-modal="true" aria-labelledby="raModalTitle">
    <div class="modal-box" style="max-width:620px;">

        <!-- Header -->
        <div class="modal-header">
            <div class="modal-title-group">
                <span class="modal-eyebrow" id="raModalEyebrow">New Resource</span>
                <span class="modal-title"   id="raModalTitle">Add Resource</span>
            </div>
            <button class="modal-close" id="raModalClose" aria-label="Close modal">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <!-- Body -->
        <div class="modal-body">
            <form id="raResourceForm" autocomplete="off">

                <div class="ra-modal-grid">

                    <!-- Resource Name -->
                    <div class="ra-modal-field full">
                        <label class="ra-modal-label" for="raFldName">Resource Name *</label>
                        <input type="text" id="raFldName" class="ra-input"
                               placeholder="e.g. Food Packs" required maxlength="150">
                    </div>

                    <!-- Category -->
                    <div class="ra-modal-field">
                        <label class="ra-modal-label" for="raFldCategory">Category *</label>
                        <select id="raFldCategory" class="ra-select" required>
                            <option value="Food">Food</option>
                            <option value="Medical">Medical</option>
                            <option value="Water">Water</option>
                            <option value="Shelter">Shelter</option>
                            <option value="Transport">Transport</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>

                    <!-- Unit -->
                    <div class="ra-modal-field">
                        <label class="ra-modal-label" for="raFldUnit">Unit *</label>
                        <input type="text" id="raFldUnit" class="ra-input"
                               placeholder="e.g. pack, kit, liter" required maxlength="30" value="pcs">
                    </div>

                    <!-- Unit Cost -->
                    <div class="ra-modal-field">
                        <label class="ra-modal-label" for="raFldCost">Unit Cost (₱)</label>
                        <input type="number" id="raFldCost" class="ra-input"
                               placeholder="0.00" min="0" step="0.01">
                    </div>

                    <!-- Restock Threshold -->
                    <div class="ra-modal-field">
                        <label class="ra-modal-label" for="raFldThreshold">Restock Threshold</label>
                        <input type="number" id="raFldThreshold" class="ra-input"
                               placeholder="50" min="0" value="50">
                    </div>

                    <!-- Qty Available -->
                    <div class="ra-modal-field">
                        <label class="ra-modal-label" for="raFldAvailable">Qty Available</label>
                        <input type="number" id="raFldAvailable" class="ra-input"
                               placeholder="0" min="0" value="0">
                    </div>

                    <!-- Qty Reserved -->
                    <div class="ra-modal-field">
                        <label class="ra-modal-label" for="raFldReserved">Qty Reserved</label>
                        <input type="number" id="raFldReserved" class="ra-input"
                               placeholder="0" min="0" value="0">
                    </div>

                    <!-- Qty Distributed -->
                    <div class="ra-modal-field">
                        <label class="ra-modal-label" for="raFldDistributed">Qty Distributed</label>
                        <input type="number" id="raFldDistributed" class="ra-input"
                               placeholder="0" min="0" value="0">
                    </div>

                    <!-- Supplier -->
                    <div class="ra-modal-field full">
                        <label class="ra-modal-label" for="raFldSupplier">Supplier</label>
                        <input type="text" id="raFldSupplier" class="ra-input"
                               placeholder="Optional supplier name" maxlength="150">
                    </div>

                    <!-- Notes -->
                    <div class="ra-modal-field full">
                        <label class="ra-modal-label" for="raFldNotes">Notes</label>
                        <textarea id="raFldNotes" class="ra-input ra-textarea"
                                  placeholder="Optional notes…" rows="2"></textarea>
                    </div>

                </div>

            </form>
        </div>

        <!-- Footer -->
        <div class="ra-modal-footer">
            <span class="ra-modal-err" id="raModalError"></span>
            <button type="button" class="btn-ra-secondary" id="raModalCancelBtn">Cancel</button>
            <button type="submit" class="btn-ra-primary" id="raModalSaveBtn"
                    onclick="document.getElementById('raResourceForm').dispatchEvent(new Event('submit', {cancelable:true, bubbles:true}))">
                Add Resource
            </button>
        </div>

    </div>
</div>


<script src="../js/activity-logs.js"></script>
<script src="../js/admin-resource-allocation.js"></script>

</body>
</html>