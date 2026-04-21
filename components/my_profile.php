<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile — Barangay EQUIAID</title>

    <!-- Fonts & Icons -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <!-- Global styles (assumed present on all pages) -->
    <link rel="stylesheet" href="../styles/global.css">
    <link rel="stylesheet" href="../styles/navbar.css">
    <link rel="stylesheet" href="../styles/footer.css">

    <!-- Page styles -->
    <link rel="stylesheet" href="../styles/my_profile.css">
</head>
<body>

<!-- ── NAVBAR (shared include) ─────────────────────────── -->
<?php include __DIR__ . '/navbar.php'; ?>

<!-- ── TOAST CONTAINER ─────────────────────────────────── -->
<div class="mp-toast-wrap" id="mpToastWrap" aria-live="polite" aria-atomic="false"></div>

<!-- ══════════════════════════════════════════════════════
     PAGE HEADER
══════════════════════════════════════════════════════════ -->
<header class="mp-header">
    <div class="container">

        <div class="mp-header-inner">
            <div>
                <h1 class="mp-page-title h3">My Profile</h1>
                <p class="mp-page-sub">Manage your personal information and account security.</p>
            </div>
            <div style="flex-shrink:0;padding-top:4px;">
                <!-- breadcrumb hint -->
                <span style="font-size:12.5px;color:var(--slate-light);">
                    <a href="dashboard.php" style="color:var(--slate-mid);text-decoration:none;">Dashboard</a>
                    &nbsp;/&nbsp; My Profile
                </span>
            </div>
        </div>

        <!-- AVATAR HERO -->
        <div class="mp-hero">
            <!-- Avatar circle -->
            <div class="mp-avatar-wrap">
                <div class="mp-avatar" id="mpAvatar">
                    <!-- Initials or img injected by JS -->
                    <span class="sk-loading" style="width:100%;height:100%;border-radius:50%;"></span>
                </div>
            </div>

            <!-- Name + badges + stats -->
            <div class="mp-hero-info">
                <div class="mp-hero-name" id="mpHeroName">
                    <span class="sk-loading" style="display:inline-block;width:200px;height:26px;"></span>
                </div>

                <div class="mp-hero-meta">
                    <span class="mp-role-badge sk-loading" id="mpRoleBadge" style="min-width:70px;height:22px;"></span>
                    <span class="mp-status-badge sk-loading" id="mpStatusBadge" style="min-width:60px;height:22px;"></span>
                </div>

                <div class="mp-stat-strip" style="margin-top:16px;">
                    <div class="mp-stat">
                        <div class="mp-stat-val sk-loading" id="mpStatReports" style="width:24px;height:20px;">—</div>
                        <div class="mp-stat-lbl">Reports Filed</div>
                    </div>
                    <div class="mp-stat">
                        <div class="mp-stat-val" id="mpStatSince" style="font-size:13px;color:var(--slate-mid);">—</div>
                        <div class="mp-stat-lbl">Member Since</div>
                    </div>
                    <div class="mp-stat">
                        <div class="mp-stat-val" id="mpStatLastLogin" style="font-size:13px;color:var(--slate-mid);">—</div>
                        <div class="mp-stat-lbl">Last Login</div>
                    </div>
                </div>
            </div>
        </div>

    </div><!-- /container -->
</header>

<!-- ══════════════════════════════════════════════════════
     CONTENT
══════════════════════════════════════════════════════════ -->
<main class="mp-content">
    <div class="container">
        <div class="mp-grid">

            <!-- ═══ LEFT COLUMN ═══════════════════════════════ -->
            <div style="display:flex;flex-direction:column;gap:24px;">

                <!-- BASIC INFO CARD -->
                <div class="mp-card" id="mpInfoCard">
                    <div class="mp-card-head">
                        <div class="mp-card-title">
                            <i class="fa-solid fa-user"></i>
                            Personal Information
                        </div>
                        <button class="mp-btn-icon-btn" id="mpEditBtn" type="button">
                            <i class="fa-solid fa-pen-to-square"></i> Edit
                        </button>
                    </div>

                    <div class="mp-card-body">
                        <form id="mpProfileForm" novalidate>

                            <!-- Full Name -->
                            <div class="mp-field">
                                <label class="mp-label" for="mpName">
                                    Full Name <span class="mp-required">*</span>
                                </label>
                                <input
                                    class="mp-input"
                                    type="text"
                                    id="mpName"
                                    name="name"
                                    placeholder="e.g. Juan dela Cruz"
                                    maxlength="120"
                                    disabled
                                    required
                                >
                                <span class="mp-field-error" id="mpNameErr" hidden>
                                    <i class="fa-solid fa-circle-exclamation"></i>
                                    <span></span>
                                </span>
                            </div>

                            <!-- Email (read-only) -->
                            <div class="mp-field">
                                <label class="mp-label" for="mpEmail">Email Address</label>
                                <input
                                    class="mp-input"
                                    type="email"
                                    id="mpEmail"
                                    name="email"
                                    readonly
                                    tabindex="-1"
                                >
                                <span class="mp-field-hint">Email address cannot be changed here. Contact the administrator for updates.</span>
                            </div>

                            <!-- Phone -->
                            <div class="mp-field">
                                <label class="mp-label" for="mpPhone">Contact Number</label>
                                <input
                                    class="mp-input"
                                    type="tel"
                                    id="mpPhone"
                                    name="phone"
                                    placeholder="e.g. 09171234567"
                                    maxlength="20"
                                    disabled
                                >
                                <span class="mp-field-hint">Philippine format: 09xxxxxxxxx or +639xxxxxxxxx</span>
                                <span class="mp-field-error" id="mpPhoneErr" hidden>
                                    <i class="fa-solid fa-circle-exclamation"></i>
                                    <span></span>
                                </span>
                            </div>

                            <!-- Actions (hidden until Edit mode) -->
                            <div class="mp-form-actions" id="mpFormActions" style="display:none;">
                                <button type="submit" class="mp-btn mp-btn--primary" id="mpSaveBtn">
                                    <i class="fa-solid fa-floppy-disk"></i> Save Changes
                                </button>
                                <button type="button" class="mp-btn mp-btn--secondary" id="mpCancelBtn">
                                    Cancel
                                </button>
                            </div>

                        </form><!-- /mpProfileForm -->
                    </div>
                </div><!-- /mp-card -->

                <!-- CHANGE PASSWORD CARD -->
                <div class="mp-card mp-danger-card" id="mpPwCard">
                    <div class="mp-card-head">
                        <div class="mp-card-title">
                            <i class="fa-solid fa-lock"></i>
                            Change Password
                        </div>
                        <button class="mp-btn-icon-btn" id="mpPwToggleBtn" type="button">
                            <i class="fa-solid fa-chevron-down" id="mpPwChevron"></i>
                        </button>
                    </div>

                    <!-- Collapsible form -->
                    <div id="mpPwFormWrap">
                        <div class="mp-card-body">
                            <form id="mpPwForm" novalidate>

                                <!-- Current Password -->
                                <div class="mp-field">
                                    <label class="mp-label" for="mpCurrentPw">
                                        Current Password <span class="mp-required">*</span>
                                    </label>
                                    <div class="mp-pw-wrap">
                                        <input class="mp-input" type="password" id="mpCurrentPw" name="current_password" autocomplete="current-password" required>
                                        <button type="button" class="mp-pw-toggle" data-target="mpCurrentPw" aria-label="Toggle visibility">
                                            <i class="fa-regular fa-eye"></i>
                                        </button>
                                    </div>
                                    <span class="mp-field-error" id="mpCurrentPwErr" hidden>
                                        <i class="fa-solid fa-circle-exclamation"></i>
                                        <span></span>
                                    </span>
                                </div>

                                <!-- New Password -->
                                <div class="mp-field">
                                    <label class="mp-label" for="mpNewPw">
                                        New Password <span class="mp-required">*</span>
                                    </label>
                                    <div class="mp-pw-wrap">
                                        <input class="mp-input" type="password" id="mpNewPw" name="new_password" autocomplete="new-password" required>
                                        <button type="button" class="mp-pw-toggle" data-target="mpNewPw" aria-label="Toggle visibility">
                                            <i class="fa-regular fa-eye"></i>
                                        </button>
                                    </div>
                                    <!-- Strength indicator -->
                                    <div class="mp-pw-strength" id="mpPwStrengthBars" hidden>
                                        <div class="mp-pw-bar" id="mpPwBar1"></div>
                                        <div class="mp-pw-bar" id="mpPwBar2"></div>
                                        <div class="mp-pw-bar" id="mpPwBar3"></div>
                                        <div class="mp-pw-bar" id="mpPwBar4"></div>
                                    </div>
                                    <div class="mp-pw-strength-label" id="mpPwStrengthLabel"></div>
                                    <span class="mp-field-hint">Minimum 8 characters.</span>
                                    <span class="mp-field-error" id="mpNewPwErr" hidden>
                                        <i class="fa-solid fa-circle-exclamation"></i>
                                        <span></span>
                                    </span>
                                </div>

                                <!-- Confirm Password -->
                                <div class="mp-field">
                                    <label class="mp-label" for="mpConfirmPw">
                                        Confirm New Password <span class="mp-required">*</span>
                                    </label>
                                    <div class="mp-pw-wrap">
                                        <input class="mp-input" type="password" id="mpConfirmPw" name="confirm_password" autocomplete="new-password" required>
                                        <button type="button" class="mp-pw-toggle" data-target="mpConfirmPw" aria-label="Toggle visibility">
                                            <i class="fa-regular fa-eye"></i>
                                        </button>
                                    </div>
                                    <span class="mp-field-error" id="mpConfirmPwErr" hidden>
                                        <i class="fa-solid fa-circle-exclamation"></i>
                                        <span></span>
                                    </span>
                                </div>

                                <div class="mp-form-actions">
                                    <button type="submit" class="mp-btn mp-btn--danger" id="mpPwSaveBtn">
                                        <i class="fa-solid fa-shield-halved"></i> Update Password
                                    </button>
                                    <button type="button" class="mp-btn mp-btn--secondary" id="mpPwCancelBtn">
                                        Cancel
                                    </button>
                                </div>

                            </form>
                        </div>
                    </div><!-- /#mpPwFormWrap -->
                </div><!-- /danger card -->

            </div><!-- /left column -->

            <!-- ═══ RIGHT COLUMN ══════════════════════════════ -->
            <div style="display:flex;flex-direction:column;gap:24px;">

                <!-- ACCOUNT DETAILS CARD -->
                <div class="mp-card">
                    <div class="mp-card-head">
                        <div class="mp-card-title">
                            <i class="fa-solid fa-id-badge"></i>
                            Account Details
                        </div>
                    </div>
                    <div class="mp-card-body" style="padding-bottom:16px;">

                        <div class="mp-info-row">
                            <div class="mp-info-lbl">User ID</div>
                            <div class="mp-info-val" id="mpDetailId">—</div>
                        </div>

                        <div class="mp-info-row">
                            <div class="mp-info-lbl">Role</div>
                            <div class="mp-info-val" id="mpDetailRole">—</div>
                        </div>

                        <div class="mp-info-row">
                            <div class="mp-info-lbl">Account Status</div>
                            <div class="mp-info-val" id="mpDetailStatus">—</div>
                        </div>

                        <div class="mp-info-row">
                            <div class="mp-info-lbl">Email Address</div>
                            <div class="mp-info-val" id="mpDetailEmail" style="word-break:break-all;">—</div>
                        </div>

                        <div class="mp-info-row">
                            <div class="mp-info-lbl">Phone Number</div>
                            <div class="mp-info-val" id="mpDetailPhone">—</div>
                        </div>

                        <div class="mp-info-row">
                            <div class="mp-info-lbl">Joined</div>
                            <div class="mp-info-val" id="mpDetailJoined">—</div>
                        </div>

                        <div class="mp-info-row">
                            <div class="mp-info-lbl">Last Updated</div>
                            <div class="mp-info-val" id="mpDetailUpdated">—</div>
                        </div>

                        <div class="mp-info-row">
                            <div class="mp-info-lbl">Last Login</div>
                            <div class="mp-info-val" id="mpDetailLastLogin">—</div>
                        </div>

                    </div>
                </div>

                <!-- QUICK LINKS CARD -->
                <div class="mp-card">
                    <div class="mp-card-head">
                        <div class="mp-card-title">
                            <i class="fa-solid fa-arrow-up-right-from-square"></i>
                            Quick Links
                        </div>
                    </div>
                    <div class="mp-card-body" style="padding:10px 8px;">
                        <a href="my_reports.php" class="npd-item" style="border-radius:var(--radius);">
                            <i class="fa-solid fa-file-lines"></i>
                            My Reports
                        </a>
                        <a href="report.php" class="npd-item" style="border-radius:var(--radius);">
                            <i class="fa-solid fa-plus"></i>
                            Submit a Report
                        </a>
                        <a href="announcements.php" class="npd-item" style="border-radius:var(--radius);">
                            <i class="fa-solid fa-bullhorn"></i>
                            Announcements
                        </a>
                        <div class="npd-divider" style="margin:4px 0;"></div>
                        <a href="../backend/logout.php" class="npd-item npd-item--danger" style="border-radius:var(--radius);">
                            <i class="fa-solid fa-right-from-bracket"></i>
                            Sign Out
                        </a>
                    </div>
                </div>

            </div><!-- /right column -->

        </div><!-- /mp-grid -->
    </div><!-- /container -->
</main>

<!-- ── FOOTER ───────────────────────────────────────────── -->
<?php include __DIR__ . '/footer.php'; ?>

<script src="../js/my_profile.js"></script>
</body>
</html>