<?php
$current_page = 'tags';
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_role']) || !in_array($_SESSION['user_role'], ['admin','superadmin'], true)) { header('Location: ../index.php'); exit; }
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Data Tags — Barangay EQUIAID</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="stylesheet" href="../styles/admin_sidebar.css"><link rel="stylesheet" href="../styles/admin_dashboard.css"><link rel="stylesheet" href="../styles/admin-tags.css"></head>
<body><div class="admin-shell"><?php include 'sidebar.php'; ?><main class="main-content"><div class="dashboard-container">
<div class="dash-header"><div><div class="dash-breadcrumb"><i class="fa-solid fa-tags"></i><span>Data Tags</span></div><h1 class="dash-title">Data Tags</h1><p class="dash-desc">Manage color-coded categories and assign them to residents, streets, incident/disaster reports, and assistance plans.</p></div></div>
<section class="dash-card tag-card"><div class="card-head"><div><div class="card-label">Catalog</div><h2 class="card-title">Tag Library</h2></div></div>
<form id="tagForm" class="tag-form"><input type="hidden" id="tagId"><input id="tagName" maxlength="80" required placeholder="Tag name"><input id="tagColor" type="color" value="#17684e" aria-label="Tag color"><button class="tag-btn" type="submit"><i class="fa-solid fa-plus"></i> Save Tag</button><button class="tag-btn secondary" type="button" id="tagCancel" hidden>Cancel</button></form>
<div id="tagCatalog" class="tag-catalog"></div></section>
<section class="dash-card tag-card"><div class="card-head"><div><div class="card-label">Assignments</div><h2 class="card-title">Record Tags</h2></div></div>
<div class="tag-toolbar"><select id="recordType"><option value="resident">Residents</option><option value="street">Streets</option><option value="resident_report">Incident / Disaster Reports</option><option value="welfare_action_plan">Assistance Plans</option></select><input id="recordSearch" type="search" placeholder="Search records or tags…"></div>
<div id="recordList" class="record-list"></div></section>
<section class="dash-card tag-card"><div class="card-head"><div><div class="card-label">History</div><h2 class="card-title">Recent Tag Assignments</h2></div></div>
<div class="table-wrap"><table class="dashboard-table tag-history"><thead><tr><th>Tag</th><th>Record Type</th><th>Record</th><th>Assigned By</th><th>Date</th></tr></thead><tbody id="recentTags"><tr><td colspan="5">Loading…</td></tr></tbody></table></div></section>
<div id="tagToast" class="tag-toast" role="status"></div></div></main></div><script src="../js/admin-tags.js"></script></body></html>
