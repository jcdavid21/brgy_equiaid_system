<?php


$current_page = $current_page ?? 'dashboard';

$nav_items = [
    ['id' => 'dashboard',          'label' => 'Dashboard',               'icon' => 'fa-solid fa-gauge-high',           'href' => 'dashboard.php'],
    ['id' => 'street-monitoring',  'label' => 'Street Monitoring',       'icon' => 'fa-solid fa-road',                 'href' => 'street_monitoring.php'],
    ['id' => 'prediction-analytics',         'label' => 'Prediction & Analytics',  'icon' => 'fa-solid fa-chart-line',           'href' => 'prediction_analytics.php'],
    ['id' => 'resource-allocation','label' => 'Resource Allocation',     'icon' => 'fa-solid fa-boxes-stacked',        'href' => 'resource-allocation.php'],
    ['id' => 'welfare-action',     'label' => 'Welfare Action Plan',     'icon' => 'fa-solid fa-file-shield',          'href' => 'welfare-action.php'],
    ['id' => 'typhoon-impact',     'label' => 'Typhoon Impact',          'icon' => 'fa-solid fa-wind',                 'href' => 'typhoon-impact.php'],
    ['id' => 'resident-reports',   'label' => 'Resident Reports',        'icon' => 'fa-solid fa-flag',                 'href' => 'resident-reports.php'],
    ['id' => 'activity-logs',      'label' => 'Activity Logs',           'icon' => 'fa-solid fa-clipboard-list',       'href' => 'activity-logs.php'],
    ['id' => 'user-management',    'label' => 'User Management',         'icon' => 'fa-solid fa-users-gear',           'href' => 'user-management.php'],
];
?>

<aside class="sidebar" id="adminSidebar" role="navigation" aria-label="Admin Navigation">

    <!-- ── Toggle Button ─────────────────────────────── -->
    <button class="toggle-btn" id="sidebarToggle" aria-label="Toggle sidebar" aria-expanded="true" title="Toggle sidebar">
        <i class="fa-solid fa-bars" aria-hidden="true"></i>
    </button>

    <!-- ── Branding Header ───────────────────────────── -->
    <div class="sidebar-header">
        <div class="logo-wrap">
            <div class="brand-text">
                <span class="brand-name">EQUIAID</span>
                <span class="brand-sub">Admin Panel</span>
            </div>
        </div>
    </div>

    <!-- ── Divider ────────────────────────────────────── -->
    <div class="sidebar-divider"></div>

    <!-- ── Navigation ────────────────────────────────── -->
    <nav class="sidebar-nav" aria-label="Main navigation">
        <ul class="nav-list" role="list">
            <?php foreach ($nav_items as $item): ?>
            <li class="nav-item" role="listitem">
                <a  href="<?= htmlspecialchars($item['href']) ?>"
                    class="nav-link <?= ($current_page === $item['id']) ? 'active' : '' ?>"
                    <?= ($current_page === $item['id']) ? 'aria-current="page"' : '' ?>
                    title="<?= htmlspecialchars($item['label']) ?>">
                    <span class="nav-icon" aria-hidden="true">
                        <i class="<?= htmlspecialchars($item['icon']) ?>"></i>
                    </span>
                    <span class="nav-label"><?= htmlspecialchars($item['label']) ?></span>
                    <?php if ($current_page === $item['id']): ?>
                    <span class="active-indicator" aria-hidden="true"></span>
                    <?php endif; ?>
                </a>
            </li>
            <?php endforeach; ?>
        </ul>
    </nav>

    <!-- ── Bottom Spacer + Logout ─────────────────────── -->
    <div class="sidebar-footer">
        <div class="sidebar-divider"></div>
        <a href="../components/logout.php" class="nav-link logout-link" title="Logout">
            <span class="nav-icon" aria-hidden="true">
                <i class="fa-solid fa-arrow-right-from-bracket"></i>
            </span>
            <span class="nav-label">Logout</span>
        </a>
    </div>

</aside>

<script src="../js/admin_sidebar.js"></script>