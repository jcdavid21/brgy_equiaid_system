<?php


if (session_status() === PHP_SESSION_NONE) session_start();
if (!empty($_SESSION['user_id'])) {
    $redirect = in_array($_SESSION['user_role'] ?? '', ['admin','staff','superadmin','dswd_officer','labeler'])
        ? '../admin/dashboard.php'
        : 'index.php';
    header('Location: ' . $redirect);
    exit;
}

$page_title = 'Login — Barangay EQUIAID';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Sign in or create an account — Barangay EQUIAID">
    <title><?= htmlspecialchars($page_title) ?></title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;1,9..40,300&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"
          integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA=="
          crossorigin="anonymous" referrerpolicy="no-referrer">

    <link rel="stylesheet" href="../styles/global.css">
    <link rel="stylesheet" href="../styles/navbar.css">
    <link rel="stylesheet" href="../styles/footer.css">
    <link rel="stylesheet" href="../styles/auth.css">
</head>
<body>

<?php include 'navbar.php'; ?>

<!-- ══════════════════════════════════════════════════════
     AUTH SECTION — sits between navbar and footer
════════════════════════════════════════════════════════ -->
<section id="auth-section" aria-label="Authentication">
    <div class="container">
        <div class="auth-page">

            <!-- ── LEFT: Navy info panel ─────────────────── -->
            <aside class="auth-panel">

                <div class="panel-brand-inline">
                    <div class="panel-logo-sm">EQ</div>
                    <div>
                        <div class="panel-brand-sub">Barangay</div>
                        <div class="panel-brand-name">EQUIAID</div>
                    </div>
                </div>

                <div class="panel-hero">
                    <div class="panel-tagline">DSWD Social Welfare Program</div>
                    <h2 class="panel-title" id="panel-title">
                        Protecting <em>Barangay Bagong Silang</em> Together
                    </h2>
                    <p class="panel-desc" id="panel-desc">
                        Sign in to access real-time street vulnerability data,
                        disaster monitoring, and social welfare resource information
                        for your community.
                    </p>

                    <div class="panel-stats">
                        <div class="panel-stat">
                            <div class="panel-stat-value">10</div>
                            <div class="panel-stat-label">Streets Monitored</div>
                        </div>
                        <div class="panel-stat">
                            <div class="panel-stat-value">4</div>
                            <div class="panel-stat-label">Zones Covered</div>
                        </div>
                        <div class="panel-stat">
                            <div class="panel-stat-value">3,615</div>
                            <div class="panel-stat-label">Total Population</div>
                        </div>
                    </div>
                </div>

                <div class="panel-features">
                    <div class="panel-feature-item">
                        <div class="panel-feature-icon">
                            <i class="fa-solid fa-road-circle-exclamation"></i>
                        </div>
                        <div>
                            <div class="panel-feature-title">Street Vulnerability</div>
                            <div class="panel-feature-desc">AI-powered risk scoring per street</div>
                        </div>
                    </div>
                    <div class="panel-feature-item">
                        <div class="panel-feature-icon">
                            <i class="fa-solid fa-cloud-bolt"></i>
                        </div>
                        <div>
                            <div class="panel-feature-title">Disaster Monitoring</div>
                            <div class="panel-feature-desc">Real-time typhoon impact tracking</div>
                        </div>
                    </div>
                    <div class="panel-feature-item">
                        <div class="panel-feature-icon">
                            <i class="fa-solid fa-hand-holding-heart"></i>
                        </div>
                        <div>
                            <div class="panel-feature-title">Welfare Resources</div>
                            <div class="panel-feature-desc">DSWD assistance availability</div>
                        </div>
                    </div>
                </div>

                <p class="panel-note">
                    <i class="fa-solid fa-shield-halved"></i>
                    Secured access &middot; Powered by ResNet-50 AI
                </p>

            </aside>

            <!-- ── RIGHT: Form panel ──────────────────────── -->
            <main class="auth-form-wrap" role="main">
                <div class="auth-form-inner">

                    <!-- Tab switcher -->
                    <div class="auth-tabs" role="tablist" aria-label="Authentication options">
                        <button class="auth-tab active" id="tab-login"
                                role="tab" aria-selected="true" aria-controls="form-login">
                            <i class="fa-solid fa-right-to-bracket"></i>
                            Sign In
                        </button>
                        <button class="auth-tab" id="tab-signup"
                                role="tab" aria-selected="false" aria-controls="form-signup">
                            <i class="fa-solid fa-user-plus"></i>
                            Create Account
                        </button>
                    </div>

                    <!-- ══════════════════════════════════════
                         LOGIN FORM
                    ══════════════════════════════════════ -->
                    <div id="form-login" class="auth-panel-form active"
                         role="tabpanel" aria-labelledby="tab-login">

                        <div class="auth-form-header">
                            <div class="auth-form-eyebrow">Welcome Back</div>
                            <h3 class="auth-form-title">Sign in to EQUIAID</h3>
                            <p class="auth-form-sub">Enter your credentials to access the system.</p>
                        </div>

                        <div class="auth-alert" id="alert-login" role="alert" style="display:none">
                            <i class="fa-solid fa-circle-exclamation"></i>
                            <span class="alert-text"></span>
                        </div>

                        <form class="auth-form" id="form-login-el" novalidate onsubmit="return false">

                            <div class="field-group">
                                <label class="field-label" for="login-email">
                                    Email Address <span>*</span>
                                </label>
                                <div class="field-input-wrap">
                                    <input type="email" id="login-email" name="email"
                                           class="field-input"
                                           placeholder="you@example.com"
                                           autocomplete="email" required>
                                    <i class="fa-solid fa-envelope"></i>
                                </div>
                            </div>

                            <div class="field-group">
                                <label class="field-label" for="login-password">
                                    Password <span>*</span>
                                </label>
                                <div class="field-input-wrap">
                                    <input type="password" id="login-password" name="password"
                                           class="field-input has-toggle"
                                           placeholder="Enter your password"
                                           autocomplete="current-password" required>
                                    <i class="fa-solid fa-lock"></i>
                                    <i class="fa-solid fa-eye toggle-pw"
                                       tabindex="0" role="button"
                                       aria-label="Toggle password visibility"></i>
                                </div>
                            </div>

                            <div class="auth-options">
                                <label class="auth-checkbox-label">
                                    <input type="checkbox" name="remember" id="remember">
                                    Remember me
                                </label>
                                <a href="forgot-password.php" class="auth-forgot">
                                    Forgot password?
                                </a>
                            </div>

                            <button type="submit" class="btn-auth">
                                <span class="btn-spinner" aria-hidden="true"></span>
                                <span class="btn-label">
                                    <i class="fa-solid fa-right-to-bracket"></i>
                                    Sign In
                                </span>
                            </button>

                        </form>

                        <div class="auth-divider">or</div>
                        <p class="auth-switch">
                            Don't have an account?
                            <a href="#signup" id="link-to-signup">Create one now</a>
                        </p>

                    </div><!-- #form-login -->


                    <!-- ══════════════════════════════════════
                         SIGNUP FORM
                    ══════════════════════════════════════ -->
                    <div id="form-signup" class="auth-panel-form"
                         role="tabpanel" aria-labelledby="tab-signup">

                        <div class="auth-form-header">
                            <div class="auth-form-eyebrow">New Account</div>
                            <h3 class="auth-form-title">Join EQUIAID</h3>
                            <p class="auth-form-sub">
                                Create a resident account to report incidents and receive alerts.
                            </p>
                        </div>

                        <div class="auth-alert" id="alert-signup" role="alert" style="display:none">
                            <i class="fa-solid fa-circle-exclamation"></i>
                            <span class="alert-text"></span>
                        </div>

                        <form class="auth-form" id="form-signup-el" novalidate onsubmit="return false">

                            <div class="field-group">
                                <label class="field-label" for="su-name">
                                    Full Name <span>*</span>
                                </label>
                                <div class="field-input-wrap">
                                    <input type="text" id="su-name" name="name"
                                           class="field-input"
                                           placeholder="Juan dela Cruz"
                                           autocomplete="name" required>
                                    <i class="fa-solid fa-user"></i>
                                </div>
                                <span class="field-error" style="display:none">
                                    <i class="fa-solid fa-circle-exclamation"></i>
                                </span>
                            </div>

                            <div class="field-row">
                                <div class="field-group">
                                    <label class="field-label" for="su-email">
                                        Email <span>*</span>
                                    </label>
                                    <div class="field-input-wrap">
                                        <input type="email" id="su-email" name="email"
                                               class="field-input"
                                               placeholder="you@example.com"
                                               autocomplete="email" required>
                                        <i class="fa-solid fa-envelope"></i>
                                    </div>
                                    <span class="field-error" style="display:none">
                                        <i class="fa-solid fa-circle-exclamation"></i>
                                    </span>
                                </div>

                                <div class="field-group">
                                    <label class="field-label" for="su-phone">
                                        Phone Number
                                    </label>
                                    <div class="field-input-wrap">
                                        <input type="tel" id="su-phone" name="phone_number"
                                               class="field-input"
                                               placeholder="09XXXXXXXXX"
                                               autocomplete="tel">
                                        <i class="fa-solid fa-phone"></i>
                                    </div>
                                    <span class="field-error" style="display:none">
                                        <i class="fa-solid fa-circle-exclamation"></i>
                                    </span>
                                </div>
                            </div>

                            <div class="field-group">
                                <label class="field-label" for="su-password">
                                    Password <span>*</span>
                                </label>
                                <div class="field-input-wrap">
                                    <input type="password" id="su-password" name="password"
                                           class="field-input has-toggle"
                                           placeholder="Min. 8 characters"
                                           autocomplete="new-password" required>
                                    <i class="fa-solid fa-lock"></i>
                                    <i class="fa-solid fa-eye toggle-pw"
                                       tabindex="0" role="button"
                                       aria-label="Toggle password visibility"></i>
                                </div>
                                <div class="pw-strength-wrap">
                                    <div class="pw-strength-track">
                                        <div class="pw-strength-fill" id="pw-strength-fill"></div>
                                    </div>
                                    <div class="pw-strength-label" id="pw-strength-label"></div>
                                </div>
                            </div>

                            <div class="field-group">
                                <label class="field-label" for="su-confirm-pw">
                                    Confirm Password <span>*</span>
                                </label>
                                <div class="field-input-wrap">
                                    <input type="password" id="su-confirm-pw" name="confirm_pw"
                                           class="field-input has-toggle"
                                           placeholder="Re-enter your password"
                                           autocomplete="new-password" required>
                                    <i class="fa-solid fa-lock"></i>
                                    <i class="fa-solid fa-eye toggle-pw"
                                       tabindex="0" role="button"
                                       aria-label="Toggle password visibility"></i>
                                </div>
                                <span class="field-error" style="display:none">
                                    <i class="fa-solid fa-circle-exclamation"></i>
                                </span>
                            </div>

                            <div class="field-group">
                                <label class="terms-label">
                                    <input type="checkbox" id="terms" name="terms" required>
                                    I agree to the
                                    <a href="terms.php" target="_blank">Terms of Use</a>
                                   
                                </label>

                            </div>

                            <button type="submit" class="btn-auth">
                                <span class="btn-spinner" aria-hidden="true"></span>
                                <span class="btn-label">
                                    <i class="fa-solid fa-user-plus"></i>
                                    Create Account
                                </span>
                            </button>

                        </form>

                        <div class="auth-divider">or</div>
                        <p class="auth-switch">
                            Already have an account?
                            <a href="#login" id="link-to-login">Sign in here</a>
                        </p>

                    </div><!-- #form-signup -->

                </div><!-- .auth-form-inner -->
            </main>

        </div><!-- .auth-page -->
    </div><!-- .container -->
</section>

<?php include 'footer.php'; ?>

<script src="../js/navbar.js" defer></script>
<script src="../js/footer.js" defer></script>
<script src="../js/auth.js"   defer></script>

</body>
</html>