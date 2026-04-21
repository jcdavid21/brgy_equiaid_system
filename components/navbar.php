<?php



$is_logged_in = !empty($_SESSION['user_id']);
$user_name    = htmlspecialchars($_SESSION['user_name'] ?? 'Resident', ENT_QUOTES);
$user_role    = $_SESSION['user_role'] ?? 'resident';

// Two-char initials
$user_initials = '';
if ($is_logged_in) {
    $parts         = explode(' ', trim($user_name));
    $user_initials = mb_strtoupper(mb_substr($parts[0], 0, 1));
    if (count($parts) > 1) {
        $user_initials .= mb_strtoupper(mb_substr(end($parts), 0, 1));
    }
}

$role_labels = [
    'superadmin'   => 'Super Admin',
    'admin'        => 'Admin',
    'staff'        => 'Barangay Staff',
    'dswd_officer' => 'DSWD Officer',
    'labeler'      => 'Labeler',
    'resident'     => 'Resident',
];
$role_label = $role_labels[$user_role] ?? 'Resident';
$is_staff   = in_array($user_role, ['admin','staff','superadmin','dswd_officer','labeler']);
?>

<header id="navbar">
    <div class="nav-inner container">

        <!-- ── Brand ──────────────────────────────────── -->
        <a href="index.php" class="nav-brand" aria-label="Barangay EQUIAID — Home">
            <div class="nav-logo-mark" aria-hidden="true">EQ</div>
            <div>
                <div class="nav-brand-sub">Barangay</div>
                <div class="nav-brand-name">EQUIAID</div>
            </div>
        </a>

        <!-- ── Desktop links ──────────────────────────── -->
        <nav class="nav-links" aria-label="Main navigation">
            <a href="index.php">Home</a>
            <a href="street_status.php">Street Status</a>
            <a href="disaster_map.php">Disaster Map</a>
            <a href="assistance.php">Assistance</a>
            <a href="report.php">Report</a>
            <a href="announcements.php">Announcements</a>

<?php if ($is_logged_in): ?>
            <!-- ── LOGGED-IN: Profile avatar + dropdown ── -->
            <div class="nav-profile" id="nav-profile">

                <button class="nav-avatar-btn"
                        id="nav-avatar-btn"
                        aria-haspopup="true"
                        aria-expanded="false"
                        aria-controls="nav-profile-dropdown"
                        aria-label="Open profile menu">
                    <div class="nav-avatar" aria-hidden="true"><?= $user_initials ?></div>
                    <i class="fa-solid fa-chevron-down nav-avatar-caret" aria-hidden="true"></i>
                </button>

                <div class="nav-profile-dropdown"
                     id="nav-profile-dropdown"
                     role="menu"
                     hidden>

                    <div class="npd-header">
                        <div class="npd-avatar-lg" aria-hidden="true"><?= $user_initials ?></div>
                        <div class="npd-info">
                            <div class="npd-name"><?= $user_name ?></div>
                            <span class="npd-role-badge"><?= $role_label ?></span>
                        </div>
                    </div>

                    <div class="npd-divider" role="separator"></div>

                    <a href="my_profile.php"    class="npd-item" role="menuitem">
                        <i class="fa-solid fa-user" aria-hidden="true"></i>My Profile
                    </a>
                    <a href="my_reports.php" class="npd-item" role="menuitem">
                        <i class="fa-solid fa-flag" aria-hidden="true"></i>My Reports
                    </a>

                    <?php if ($is_staff): ?>
                    <div class="npd-divider" role="separator"></div>
                    <a href="../admin/dashboard.php" class="npd-item npd-item--admin" role="menuitem">
                        <i class="fa-solid fa-gauge-high" aria-hidden="true"></i>Admin Panel
                    </a>
                    <?php endif; ?>

                    <div class="npd-divider" role="separator"></div>

                    <a href="logout.php"
                       class="npd-item npd-item--danger" role="menuitem">
                        <i class="fa-solid fa-right-from-bracket" aria-hidden="true"></i>Log Out
                    </a>

                </div><!-- /#nav-profile-dropdown -->
            </div><!-- /.nav-profile -->

<?php else: ?>
            <!-- ── LOGGED-OUT: Log In button ─────────── -->
            <a href="login.php" class="btn-nav-login">
                <i class="fa-solid fa-right-to-bracket" aria-hidden="true"></i>
                Log In
            </a>

<?php endif; ?>
        </nav><!-- /.nav-links -->

        <!-- ── Hamburger ──────────────────────────────── -->
        <button class="hamburger" id="hamburger"
                aria-controls="mobile-menu"
                aria-expanded="false"
                aria-label="Toggle navigation menu">
            <span></span>
            <span></span>
            <span></span>
        </button>

    </div><!-- /.nav-inner -->

    <!-- ── Mobile menu ────────────────────────────────── -->
    <nav class="mobile-menu" id="mobile-menu" aria-label="Mobile navigation">
        <a href="index.php">
            <i class="fa-solid fa-house" aria-hidden="true"></i>Home
        </a>
        <a href="street_status.php">
            <i class="fa-solid fa-road" aria-hidden="true"></i>Street Status
        </a>
        <a href="disaster_map.php">
            <i class="fa-solid fa-map-location-dot" aria-hidden="true"></i>Disaster Map
        </a>
        <a href="assistance.php">
            <i class="fa-solid fa-hand-holding-heart" aria-hidden="true"></i>Assistance
        </a>
        <a href="report.php">
            <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>Report Incident
        </a>
        <a href="announcements.php">
            <i class="fa-solid fa-bullhorn" aria-hidden="true"></i>Announcements
        </a>

<?php if ($is_logged_in): ?>
        <!-- Mobile: user identity row -->
        <div class="mobile-user-row">
            <div class="mobile-avatar" aria-hidden="true"><?= $user_initials ?></div>
            <div>
                <div class="mobile-user-name"><?= $user_name ?></div>
                <div class="mobile-user-role"><?= $role_label ?></div>
            </div>
        </div>
        <?php if ($is_staff): ?>
        <a href="../admin/dashboard.php">
            <i class="fa-solid fa-gauge-high" aria-hidden="true"></i>Admin Panel
        </a>
        <?php endif; ?>
        <a href="logout.php" class="mobile-logout-link">
            <i class="fa-solid fa-right-from-bracket" aria-hidden="true"></i>Log Out
        </a>
<?php else: ?>
        <a href="login.php" class="btn-nav-login">
            <i class="fa-solid fa-right-to-bracket" aria-hidden="true"></i>Log In
        </a>
<?php endif; ?>

    </nav><!-- /#mobile-menu -->

</header>