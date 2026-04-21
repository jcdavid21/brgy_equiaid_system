<?php
/**
 * backend/my_profile.php — Barangay EQUIAID My Profile API
 * Handles profile view, update, avatar upload, and password change
 * for the logged-in user ($_SESSION['user_id'])
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

// ── Auth guard ────────────────────────────────────────
if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Not authenticated']);
    exit;
}

$user_id = (int) $_SESSION['user_id'];

require_once __DIR__ . '/db.php';

if (!$pdo) {
    http_response_code(503);
    echo json_encode(['ok' => false, 'error' => 'Database unavailable']);
    exit;
}

$action = trim($_GET['action'] ?? $_POST['action'] ?? '');
$method = $_SERVER['REQUEST_METHOD'];

// ── Helper: validate phone (PH format, optional) ──────
function validatePhone(string $phone): bool {
    // Accepts blank, or Philippine numbers like 09xxxxxxxxx / +639xxxxxxxxx
    if ($phone === '') return true;
    return (bool) preg_match('/^(\+639|09)\d{9}$/', $phone);
}

try {
    switch ($action) {

        // ─────────────────────────────────────────────
        // GET profile data
        // ─────────────────────────────────────────────
        case 'get_profile':
            $stmt = $pdo->prepare("
                SELECT id, name, email, phone_number, role, is_active,
                       last_login_at, created_at, updated_at
                FROM users
                WHERE id = :id
                LIMIT 1
            ");
            $stmt->execute([':id' => $user_id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                http_response_code(404);
                echo json_encode(['ok' => false, 'error' => 'User not found']);
                exit;
            }

            // Count total reports for context
            $rStmt = $pdo->prepare("SELECT COUNT(*) FROM resident_reports WHERE user_id = :id");
            $rStmt->execute([':id' => $user_id]);
            $totalReports = (int) $rStmt->fetchColumn();

            // Never expose password hash
            unset($user['password']);

            echo json_encode([
                'ok'            => true,
                'user'          => $user,
                'total_reports' => $totalReports,
            ]);
            break;

        // ─────────────────────────────────────────────
        // POST update basic info
        // ─────────────────────────────────────────────
        case 'update_profile':
            if ($method !== 'POST') {
                http_response_code(405);
                echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
                exit;
            }

            $name  = trim($_POST['name']  ?? '');
            $phone = trim($_POST['phone'] ?? '');

            // Validation
            $errors = [];
            if ($name === '') $errors[] = 'Full name is required.';
            if (mb_strlen($name) > 120) $errors[] = 'Name must be 120 characters or fewer.';
            if (!validatePhone($phone)) $errors[] = 'Phone must be a valid PH number (e.g. 09171234567).';

            if ($errors) {
                http_response_code(422);
                echo json_encode(['ok' => false, 'error' => implode(' ', $errors)]);
                exit;
            }

            $stmt = $pdo->prepare("
                UPDATE users
                SET name         = :name,
                    phone_number = :phone,
                    updated_at   = NOW()
                WHERE id = :id
            ");
            $stmt->execute([
                ':name'  => $name,
                ':phone' => $phone !== '' ? $phone : null,
                ':id'    => $user_id,
            ]);

            // Update session name so navbar stays in sync
            $_SESSION['user_name'] = $name;

            echo json_encode(['ok' => true, 'message' => 'Profile updated successfully.']);
            break;

        // ─────────────────────────────────────────────
        // POST change password
        // ─────────────────────────────────────────────
        case 'change_password':
            if ($method !== 'POST') {
                http_response_code(405);
                echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
                exit;
            }

            $currentPw  = $_POST['current_password']  ?? '';
            $newPw      = $_POST['new_password']       ?? '';
            $confirmPw  = $_POST['confirm_password']   ?? '';

            // Fetch current hash
            $stmt = $pdo->prepare("SELECT password FROM users WHERE id = :id LIMIT 1");
            $stmt->execute([':id' => $user_id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$row || !password_verify($currentPw, $row['password'])) {
                http_response_code(422);
                echo json_encode(['ok' => false, 'error' => 'Current password is incorrect.']);
                exit;
            }

            // Validate new password
            $errors = [];
            if (strlen($newPw) < 8) $errors[] = 'New password must be at least 8 characters.';
            if ($newPw !== $confirmPw) $errors[] = 'Passwords do not match.';
            if ($newPw === $currentPw) $errors[] = 'New password must differ from the current password.';

            if ($errors) {
                http_response_code(422);
                echo json_encode(['ok' => false, 'error' => implode(' ', $errors)]);
                exit;
            }

            $hash = password_hash($newPw, PASSWORD_BCRYPT);
            $stmt = $pdo->prepare("UPDATE users SET password = :pw, updated_at = NOW() WHERE id = :id");
            $stmt->execute([':pw' => $hash, ':id' => $user_id]);

            echo json_encode(['ok' => true, 'message' => 'Password changed successfully.']);
            break;


        default:
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Unknown action: ' . htmlspecialchars($action)]);
    }

} catch (PDOException $e) {
    http_response_code(500);
    error_log('[EQUIAID MY_PROFILE] ' . $e->getMessage());
    echo json_encode(['ok' => false, 'error' => 'Database error']);
} catch (Throwable $e) {
    http_response_code(500);
    error_log('[EQUIAID MY_PROFILE] ' . $e->getMessage());
    echo json_encode(['ok' => false, 'error' => 'Server error']);
}