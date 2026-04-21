<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$page_title = 'Assistance — Barangay EQUIAID';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;1,9..40,300&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer">
    <link rel="stylesheet" href="../styles/global.css">
    <link rel="stylesheet" href="../styles/navbar.css">
    <link rel="stylesheet" href="../styles/footer.css">
    <link rel="stylesheet" href="../styles/assistance.css">
    <link rel="stylesheet" href="../styles/street_status.css">
</head>
<body>
<?php include 'navbar.php'; ?>

<header class="as-header">
    <div class="container">
        <div class="as-header-inner">
            <div class="as-header-text">
                <p class="section-label"><i class="fa-solid fa-hand-holding-heart"></i> Welfare &amp; Relief</p>
                <h1 class="section-title as-page-title">Assistance</h1>
                <p class="section-desc">Resource inventory, distribution logs, welfare action plans, and evacuation center status for Barangay Bagong Silang.</p>
            </div>
            <div class="as-header-meta">
                <div class="as-last-updated-wrap">
                    <i class="fa-regular fa-clock"></i>
                    Last updated <strong id="as-last-updated" class="sk-loading sk-inline">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</strong>
                </div>
                <a href="report.php" class="btn btn-primary"><i class="fa-solid fa-circle-exclamation"></i> Report Incident</a>
            </div>
        </div>

        <div class="as-kpi-strip">
            <div class="as-kpi as-kpi--plans">
                <div class="as-kpi-icon"><i class="fa-solid fa-clipboard-list"></i></div>
                <div class="as-kpi-body"><span class="as-kpi-val sk-loading" id="as-kpi-plans">—</span><span class="as-kpi-lbl">Active Plans</span></div>
            </div>
            <div class="as-kpi as-kpi--distributed">
                <div class="as-kpi-icon"><i class="fa-solid fa-boxes-stacked"></i></div>
                <div class="as-kpi-body"><span class="as-kpi-val sk-loading" id="as-kpi-distributed">—</span><span class="as-kpi-lbl">Items Distributed</span></div>
            </div>
            <div class="as-kpi as-kpi--families">
                <div class="as-kpi-icon"><i class="fa-solid fa-house-chimney-user"></i></div>
                <div class="as-kpi-body"><span class="as-kpi-val sk-loading" id="as-kpi-families">—</span><span class="as-kpi-lbl">Families Served</span></div>
            </div>
            <div class="as-kpi as-kpi--budget">
                <div class="as-kpi-icon"><i class="fa-solid fa-peso-sign"></i></div>
                <div class="as-kpi-body"><span class="as-kpi-val sk-loading" id="as-kpi-budget">—</span><span class="as-kpi-lbl">Total Disbursed</span></div>
            </div>
            <div class="as-kpi as-kpi--evac">
                <div class="as-kpi-icon"><i class="fa-solid fa-house-medical"></i></div>
                <div class="as-kpi-body"><span class="as-kpi-val sk-loading" id="as-kpi-evac">—</span><span class="as-kpi-lbl">Evac Occupancy</span></div>
            </div>
        </div>
    </div>
</header>

<main class="as-main">
    <div class="container">
        <div class="as-layout">

            <div class="as-col-main">

                <!-- WELFARE ACTION PLANS -->
                <section class="as-section" aria-labelledby="as-plans-title">
                    <div class="as-section-head">
                        <div>
                            <h2 class="as-section-title" id="as-plans-title">
                                <i class="fa-solid fa-clipboard-list"></i> Welfare Action Plans
                            </h2>
                            <p class="as-section-desc">Active and recent assistance deployments per street.</p>
                        </div>
                        <div class="as-section-controls">
                            <select class="as-select" id="planStatusFilter">
                                <option value="all">All Status</option>
                                <option value="Ongoing">Ongoing</option>
                                <option value="Planned">Planned</option>
                                <option value="Completed">Completed</option>
                                <option value="Cancelled">Cancelled</option>
                            </select>
                            <select class="as-select" id="planTypeFilter">
                                <option value="all">All Types</option>
                                <option value="Food Distribution">Food</option>
                                <option value="Medical Assistance">Medical</option>
                                <option value="Water Supply">Water</option>
                                <option value="Shelter Repair">Shelter</option>
                            </select>
                        </div>
                    </div>

                    <div class="as-results-bar" id="asResultsBar" hidden>
                        Showing <strong id="asVisibleCount">0</strong> of <strong id="asTotalCount">0</strong> plans
                    </div>

                    <!-- Skeletons -->
                    <div class="as-plans-list" id="asPlansGridSkeleton">
                        <?php for ($i = 0; $i < 4; $i++): ?>
                        <div class="as-plan-card as-plan-card--skeleton">
                            <div class="as-plan-body">
                                <div class="sk-loading" style="height:11px;width:38%;border-radius:4px;margin-bottom:10px"></div>
                                <div class="sk-loading" style="height:20px;width:58%;border-radius:4px;margin-bottom:10px"></div>
                                <div class="sk-loading" style="height:13px;width:88%;border-radius:4px;margin-bottom:6px"></div>
                                <div class="sk-loading" style="height:13px;width:68%;border-radius:4px"></div>
                            </div>
                            <div class="as-plan-side">
                                <div class="sk-loading" style="height:24px;width:80px;border-radius:100px;margin-bottom:8px"></div>
                                <div class="sk-loading" style="height:11px;width:72px;border-radius:4px"></div>
                            </div>
                        </div>
                        <?php endfor; ?>
                    </div>

                    <div class="as-plans-list" id="asPlansGrid" hidden></div>

                    <div class="as-empty" id="asPlansEmpty" hidden>
                        <i class="fa-solid fa-clipboard-list"></i>
                        <p>No plans match the selected filters.</p>
                        <button class="btn btn-outline" id="asClearFilters">Clear Filters</button>
                    </div>

                    <!-- Pagination — reuses ss-pagination classes from street_status.css -->
                    <div id="asPagination" class="ss-pagination" hidden aria-label="Page navigation"></div>
                </section>

                <!-- DISTRIBUTION LOG -->
                <section class="as-section" aria-labelledby="as-dist-title">
                    <div class="as-section-head">
                        <div>
                            <h2 class="as-section-title" id="as-dist-title">
                                <i class="fa-solid fa-truck-ramp-box"></i> Distribution Log
                            </h2>
                            <p class="as-section-desc">Record of all resources delivered to affected streets.</p>
                        </div>
                    </div>
                    <div class="as-table-wrap">
                        <table class="as-table">
                            <thead>
                                <tr>
                                    <th>Street</th>
                                    <th>Resource</th>
                                    <th>Qty</th>
                                    <th>Households</th>
                                    <th>Total Cost</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody id="asDistBody">
                                <?php for ($i = 0; $i < 5; $i++): ?>
                                <tr><td colspan="6" style="padding:14px 16px"><div class="sk-loading" style="height:14px;border-radius:4px"></div></td></tr>
                                <?php endfor; ?>
                            </tbody>
                        </table>
                    </div>
                </section>

            </div><!-- /.as-col-main -->

            <aside class="as-col-side">

                <!-- RESOURCE INVENTORY -->
                <div class="as-sidebar-card">
                    <div class="as-sc-head">
                        <div class="as-sc-icon"><i class="fa-solid fa-warehouse"></i></div>
                        <div>
                            <h3 class="as-sc-title">Resource Inventory</h3>
                            <p class="as-sc-desc">Current stock levels</p>
                        </div>
                    </div>
                    <div class="as-inventory-list" id="asInventoryList">
                        <?php for ($i = 0; $i < 5; $i++): ?>
                        <div class="as-inv-item">
                            <div class="as-inv-head">
                                <div class="sk-loading" style="height:13px;width:55%;border-radius:4px"></div>
                                <div class="sk-loading" style="height:20px;width:55px;border-radius:4px"></div>
                            </div>
                            <div class="sk-loading" style="height:5px;width:100%;border-radius:3px;margin-top:8px"></div>
                        </div>
                        <?php endfor; ?>
                    </div>
                </div>

                <!-- EVACUATION CENTERS -->
                <div class="as-sidebar-card">
                    <div class="as-sc-head">
                        <div class="as-sc-icon as-sc-icon--evac"><i class="fa-solid fa-house-medical"></i></div>
                        <div>
                            <h3 class="as-sc-title">Evacuation Centers</h3>
                            <p class="as-sc-desc">Current occupancy status</p>
                        </div>
                    </div>
                    <div class="as-evac-list" id="asEvacList">
                        <?php for ($i = 0; $i < 3; $i++): ?>
                        <div class="as-evac-item">
                            <div class="sk-loading" style="height:14px;width:65%;border-radius:4px;margin-bottom:8px"></div>
                            <div class="sk-loading" style="height:5px;width:100%;border-radius:3px"></div>
                        </div>
                        <?php endfor; ?>
                    </div>
                </div>

                <!-- BREAKDOWN -->
                <div class="as-sidebar-card">
                    <div class="as-sc-head">
                        <div class="as-sc-icon as-sc-icon--chart"><i class="fa-solid fa-chart-bar"></i></div>
                        <div>
                            <h3 class="as-sc-title">By Assistance Type</h3>
                            <p class="as-sc-desc">Plans per category</p>
                        </div>
                    </div>
                    <div class="as-breakdown-list" id="asBreakdownList">
                        <?php for ($i = 0; $i < 4; $i++): ?>
                        <div class="as-breakdown-item">
                            <div class="as-breakdown-head">
                                <div class="sk-loading" style="height:13px;width:60%;border-radius:4px"></div>
                                <div class="sk-loading" style="height:13px;width:20px;border-radius:4px"></div>
                            </div>
                            <div class="sk-loading" style="height:4px;width:100%;border-radius:2px;margin-top:6px"></div>
                        </div>
                        <?php endfor; ?>
                    </div>
                </div>

                <!-- EMERGENCY CONTACTS -->
                <div class="as-sidebar-card as-sidebar-card--dark">
                    <div class="as-sc-head">
                        <div class="as-sc-icon as-sc-icon--emergency"><i class="fa-solid fa-phone-volume"></i></div>
                        <div>
                            <h3 class="as-sc-title" style="color:#fff">Emergency Contacts</h3>
                            <p class="as-sc-desc" style="color:rgba(255,255,255,.5)">Hotlines &amp; responders</p>
                        </div>
                    </div>
                    <div class="as-em-list">
                        <a href="tel:911" class="as-em-item">
                            <span class="as-em-label"><i class="fa-solid fa-shield-halved"></i> Emergency Hotline</span>
                            <span class="as-em-number">911</span>
                        </a>
                        <a href="tel:117" class="as-em-item">
                            <span class="as-em-label"><i class="fa-solid fa-person-drowning"></i> NDRRMC</span>
                            <span class="as-em-number">117</span>
                        </a>
                        <a href="tel:+63287119406" class="as-em-item">
                            <span class="as-em-label"><i class="fa-solid fa-building-columns"></i> DSWD Hotline</span>
                            <span class="as-em-number">(02) 8711-9406</span>
                        </a>
                        <a href="tel:+6328888" class="as-em-item">
                            <span class="as-em-label"><i class="fa-solid fa-city"></i> Caloocan CDRRMO</span>
                            <span class="as-em-number">(02) 8888-8888</span>
                        </a>
                    </div>
                </div>

            </aside>
        </div>
    </div>
</main>

<?php include 'footer.php'; ?>
<script src="../js/navbar.js"></script>
<script src="../js/assistance.js"></script>
</body>
</html>