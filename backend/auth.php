<?php


header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

// ── Only allow POST ───────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'Method not allowed.']);
    exit;
}

$action = trim($_POST['action'] ?? '');
if ($action === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Missing action parameter.']);
    exit;
}

require_once __DIR__ . '/db.php'; // provides $pdo

if (!$pdo) {
    http_response_code(503);
    echo json_encode(['ok' => false, 'message' => 'Database unavailable. Please try again later.']);
    exit;
}

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

try {

    // ════════════════════════════════════════════════════
    // LOGIN
    // ════════════════════════════════════════════════════
    if ($action === 'login') {

        $email    = trim($_POST['email']    ?? '');
        $password = trim($_POST['password'] ?? '');

        // ── Basic validation ──────────────────────────
        if ($email === '' || $password === '') {
            echo json_encode(['ok' => false, 'message' => 'Email and password are required.']);
            exit;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['ok' => false, 'message' => 'Please enter a valid email address.']);
            exit;
        }

        // ── Fetch user ────────────────────────────────
        $stmt = $pdo->prepare("
            SELECT id, name, email, password, role, is_active
            FROM users
            WHERE email = ?
            LIMIT 1
        ");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        // ── Verify password ───────────────────────────
        if (!$user || !password_verify($password, $user['password'])) {
            // Generic message prevents user enumeration
            echo json_encode(['ok' => false, 'message' => 'Invalid email or password.']);
            exit;
        }

        if (!(bool)$user['is_active']) {
            echo json_encode(['ok' => false, 'message' => 'Your account has been deactivated. Please contact the barangay office.']);
            exit;
        }

        // ── Session ───────────────────────────────────
        session_regenerate_id(true);
        $_SESSION['user_id']   = $user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['user_email']= $user['email'];

        // Update last login timestamp
        $pdo->prepare("UPDATE users SET last_login_at = NOW() WHERE id = ?")
            ->execute([$user['id']]);

        // ── Redirect based on role ────────────────────
        $redirect = match($user['role']) {
            'superadmin', 'admin'  => '../admin/dashboard.php',
            'staff'                => '../admin/dashboard.php',
            'dswd_officer'         => '../admin/dashboard.php',
            'labeler'              => '../admin/dashboard.php',
            default                => '../components/index.php',
        };

        echo json_encode([
            'ok'       => true,
            'message'  => 'Welcome back, ' . htmlspecialchars($user['name']) . '!',
            'redirect' => $redirect,
            'role'     => $user['role'],
        ]);
        exit;
    }

    // ════════════════════════════════════════════════════
    // SIGNUP
    // ════════════════════════════════════════════════════
    if ($action === 'signup') {

        $name     = trim($_POST['name']         ?? '');
        $email    = trim($_POST['email']        ?? '');
        $phone    = trim($_POST['phone_number'] ?? '');
        $password = trim($_POST['password']     ?? '');
        $confirm  = trim($_POST['confirm_pw']   ?? '');

        // ── Validation ────────────────────────────────
        $errors = [];

        if ($name === '') {
            $errors[] = 'Full name is required.';
        } elseif (strlen($name) < 3) {
            $errors[] = 'Full name must be at least 3 characters.';
        } elseif (strlen($name) > 120) {
            $errors[] = 'Full name must not exceed 120 characters.';
        }

        if ($email === '') {
            $errors[] = 'Email address is required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please enter a valid email address.';
        }

        if ($phone !== '' && !preg_match('/^(09|\+639)\d{9}$/', $phone)) {
            $errors[] = 'Phone number must be a valid PH mobile number (e.g. 09171234567).';
        }

        if ($password === '') {
            $errors[] = 'Password is required.';
        } elseif (strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters.';
        } elseif (!preg_match('/[A-Z]/', $password)) {
            $errors[] = 'Password must contain at least one uppercase letter.';
        } elseif (!preg_match('/[0-9]/', $password)) {
            $errors[] = 'Password must contain at least one number.';
        }

        if ($password !== $confirm) {
            $errors[] = 'Passwords do not match.';
        }

        if (!empty($errors)) {
            echo json_encode(['ok' => false, 'message' => implode(' ', $errors)]);
            exit;
        }

        // ── Check email already exists ────────────────
        $check = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
        $check->execute([$email]);

        if ($check->fetch()) {
            echo json_encode(['ok' => false, 'message' => 'An account with this email already exists. Please log in instead.']);
            exit;
        }

        // ── Insert new resident ───────────────────────
        $hashed = password_hash($password, PASSWORD_BCRYPT);

        $insert = $pdo->prepare("
            INSERT INTO users (name, email, password, role, phone_number, is_active)
            VALUES (?, ?, ?, 'resident', ?, 1)
        ");
        $insert->execute([
            $name,
            $email,
            $hashed,
            $phone !== '' ? $phone : null,
        ]);

        $newId = (int) $pdo->lastInsertId();

        // Auto-login after signup
        session_regenerate_id(true);
        $_SESSION['user_id']    = $newId;
        $_SESSION['user_name']  = $name;
        $_SESSION['user_role']  = 'resident';
        $_SESSION['user_email'] = $email;

        echo json_encode([
            'ok'       => true,
            'message'  => 'Account created successfully! Welcome, ' . htmlspecialchars($name) . '.',
            'redirect' => '../components/index.php',
        ]);
        exit;
    }

    // ════════════════════════════════════════════════════
    // LOGOUT
    // ════════════════════════════════════════════════════
    if ($action === 'logout') {
        // Clear session data
        $_SESSION = [];

        // Expire the session cookie immediately
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(), '', time() - 42000,
                $params['path'], $params['domain'],
                $params['secure'], $params['httponly']
            );
        }

        session_destroy();

        echo json_encode([
            'ok'       => true,
            'message'  => 'You have been logged out.',
            'redirect' => '../components/login.php',
        ]);
        exit;
    }

    // ── Unknown action ────────────────────────────────
    http_response_code(404);
    echo json_encode(['ok' => false, 'message' => 'Unknown action.']);

} catch (PDOException $e) {
    error_log('[AUTH] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'A server error occurred. Please try again.']);
} catch (Throwable $e) {
    error_log('[AUTH] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'An unexpected error occurred.']);
}