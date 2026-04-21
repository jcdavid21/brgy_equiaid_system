<?php
/**
 * admin-user-management-action.php
 * Barangay EQUIAID — User Management API
 * Handles GET (list/single/meta), POST (create), PUT (update), DELETE (soft/hard)
 *
 * Auth: superadmin only for destructive actions; admin can view + edit non-admins
 */

// ── Session / Auth ────────────────────────────────────
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['user_role']) || !in_array($_SESSION['user_role'], ['admin', 'superadmin'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/db.php'; // provides $pdo

// ── Helper: JSON response ────────────────────────────
function respond(bool $ok, $data = null, string $msg = '', int $code = 200): void {
    http_response_code($code);
    echo json_encode([
        'success' => $ok,
        'message' => $msg,
        'data'    => $data,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit();
}

// ── Helper: sanitize input ────────────────────────────
function clean(string $val): string {
    return htmlspecialchars(strip_tags(trim($val)), ENT_QUOTES, 'UTF-8');
}

// ── Parse body ────────────────────────────────────────
$method = $_SERVER['REQUEST_METHOD'];
$input  = [];
if (in_array($method, ['POST', 'PUT'])) {
    $raw   = file_get_contents('php://input');
    $input = json_decode($raw, true) ?? [];
    if (empty($input) && $method === 'POST') {
        $input = $_POST;
    }
}

// ── Route params ──────────────────────────────────────
$id     = isset($_GET['id'])     ? (int)$_GET['id']   : 0;
$meta   = isset($_GET['meta'])   ? (int)$_GET['meta'] : 0;
$action = $_GET['action']        ?? '';

$currentUserId   = (int)($_SESSION['user_id']   ?? 0);
$currentUserRole = $_SESSION['user_role'] ?? 'admin';

// Valid roles enum (matches DB)
$VALID_ROLES = ['superadmin', 'admin', 'staff', 'dswd_officer', 'labeler', 'resident'];

try {

    // ── GET: meta / stats ─────────────────────────────
    if ($method === 'GET' && $meta) {
        $stats = $pdo->query(
            "SELECT
                COUNT(*)                                AS total,
                SUM(is_active = 1)                      AS active,
                SUM(is_active = 0)                      AS inactive,
                SUM(role = 'admin')                     AS admins,
                SUM(role = 'superadmin')                AS superadmins,
                SUM(role = 'staff')                     AS staff,
                SUM(role = 'dswd_officer')              AS dswd,
                SUM(role = 'resident')                  AS residents,
                SUM(last_login_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)) AS active_week
             FROM users"
        )->fetch(PDO::FETCH_ASSOC);

        respond(true, compact('stats'));
    }

    // ── GET: single user ──────────────────────────────
    if ($method === 'GET' && $id > 0) {
        $stmt = $pdo->prepare(
            "SELECT id, name, email, role, phone_number,
                    is_active, last_login_at, created_at, updated_at
             FROM users WHERE id = ?"
        );
        $stmt->execute([$id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) respond(false, null, 'User not found.', 404);

        respond(true, $user);
    }

    // ── GET: list users ───────────────────────────────
    if ($method === 'GET') {
        // KPI counts
        $kpi = $pdo->query(
            "SELECT
                COUNT(*)                                   AS total,
                SUM(is_active = 1)                         AS active,
                SUM(is_active = 0)                         AS inactive,
                SUM(role IN ('admin','superadmin'))         AS admins,
                SUM(last_login_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) AND is_active = 1) AS recent_logins
             FROM users"
        )->fetch(PDO::FETCH_ASSOC);

        // Filters
        $where  = ['1=1'];
        $params = [];

        if (!empty($_GET['role'])) {
            $where[]  = 'u.role = ?';
            $params[] = $_GET['role'];
        }
        if (isset($_GET['is_active']) && $_GET['is_active'] !== '') {
            $where[]  = 'u.is_active = ?';
            $params[] = (int)$_GET['is_active'];
        }
        if (!empty($_GET['search'])) {
            $like     = '%' . $_GET['search'] . '%';
            $where[]  = '(u.name LIKE ? OR u.email LIKE ? OR u.phone_number LIKE ?)';
            $params   = array_merge($params, [$like, $like, $like]);
        }

        $whereStr = implode(' AND ', $where);

        // Sort
        $sortAllowed = ['name', 'role', 'created_at', 'last_login_at', 'is_active'];
        $sortCol     = in_array($_GET['sort'] ?? '', $sortAllowed) ? $_GET['sort'] : 'created_at';
        $sortDir     = ($_GET['dir'] ?? 'desc') === 'asc' ? 'ASC' : 'DESC';

        // Pagination
        $page   = max(1, (int)($_GET['page'] ?? 1));
        $limit  = 15;
        $offset = ($page - 1) * $limit;

        // Count
        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM users u WHERE $whereStr");
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        // Main query
        $sql = "SELECT
                    u.id, u.name, u.email, u.role, u.phone_number,
                    u.is_active, u.last_login_at, u.created_at, u.updated_at
                FROM users u
                WHERE $whereStr
                ORDER BY
                    FIELD(u.role,'superadmin','admin','staff','dswd_officer','labeler','resident'),
                    u.$sortCol $sortDir
                LIMIT $limit OFFSET $offset";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Strip password hashes (none returned since we didn't select them, but safety net)
        foreach ($users as &$u) unset($u['password']);

        respond(true, [
            'users'     => $users,
            'kpi'       => $kpi,
            'total'     => $total,
            'page'      => $page,
            'limit'     => $limit,
            'last_page' => (int)ceil($total / $limit),
        ]);
    }

    // ── POST: create user ─────────────────────────────
    if ($method === 'POST') {
        // Only superadmin can create admin/superadmin
        $targetRole = $input['role'] ?? 'resident';
        if (in_array($targetRole, ['admin', 'superadmin']) && $currentUserRole !== 'superadmin') {
            respond(false, null, 'Only superadmins can create admin-level users.', 403);
        }

        // Validation
        $required = ['name', 'email', 'password', 'role'];
        foreach ($required as $f) {
            if (empty($input[$f])) {
                respond(false, null, "Field '$f' is required.", 422);
            }
        }

        if (!filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
            respond(false, null, 'Invalid email address.', 422);
        }

        if (!in_array($targetRole, $VALID_ROLES)) {
            respond(false, null, 'Invalid role specified.', 422);
        }

        if (strlen($input['password']) < 8) {
            respond(false, null, 'Password must be at least 8 characters.', 422);
        }

        // Check email uniqueness
        $dupCheck = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $dupCheck->execute([strtolower(trim($input['email']))]);
        if ($dupCheck->fetch()) {
            respond(false, null, 'An account with this email already exists.', 409);
        }

        $stmt = $pdo->prepare(
            "INSERT INTO users (name, email, password, role, phone_number, is_active)
             VALUES (?, ?, ?, ?, ?, ?)"
        );

        $stmt->execute([
            clean($input['name']),
            strtolower(trim($input['email'])),
            password_hash($input['password'], PASSWORD_BCRYPT),
            $targetRole,
            !empty($input['phone_number']) ? clean($input['phone_number']) : null,
            isset($input['is_active']) ? (int)(bool)$input['is_active'] : 1,
        ]);

        $newId = (int)$pdo->lastInsertId();
        respond(true, ['id' => $newId], 'User created successfully.');
    }

    // ── PUT: update user ──────────────────────────────
    if ($method === 'PUT' && $id > 0) {
        // Verify exists
        $existing = $pdo->prepare("SELECT id, role FROM users WHERE id = ?");
        $existing->execute([$id]);
        $existingUser = $existing->fetch(PDO::FETCH_ASSOC);
        if (!$existingUser) respond(false, null, 'User not found.', 404);

        // Prevent non-superadmin from editing admin/superadmin
        if (in_array($existingUser['role'], ['admin', 'superadmin']) && $currentUserRole !== 'superadmin') {
            respond(false, null, 'Insufficient permissions to edit this user.', 403);
        }

        // Prevent self-role demotion
        if ($id === $currentUserId && isset($input['role'])
            && !in_array($input['role'], ['admin', 'superadmin'])) {
            respond(false, null, 'You cannot demote your own account.', 403);
        }

        // Validation
        if (!empty($input['email']) && !filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
            respond(false, null, 'Invalid email address.', 422);
        }

        $targetRole = $input['role'] ?? $existingUser['role'];
        if (!in_array($targetRole, $VALID_ROLES)) {
            respond(false, null, 'Invalid role specified.', 422);
        }

        // Email uniqueness check (excluding self)
        if (!empty($input['email'])) {
            $dupCheck = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $dupCheck->execute([strtolower(trim($input['email'])), $id]);
            if ($dupCheck->fetch()) {
                respond(false, null, 'This email is already in use by another account.', 409);
            }
        }

        // Build update — only update password if provided
        if (!empty($input['password'])) {
            if (strlen($input['password']) < 8) {
                respond(false, null, 'Password must be at least 8 characters.', 422);
            }
            $stmt = $pdo->prepare(
                "UPDATE users SET name = ?, email = ?, password = ?, role = ?,
                                  phone_number = ?, is_active = ?
                 WHERE id = ?"
            );
            $stmt->execute([
                clean($input['name'] ?? ''),
                strtolower(trim($input['email'] ?? '')),
                password_hash($input['password'], PASSWORD_BCRYPT),
                $targetRole,
                !empty($input['phone_number']) ? clean($input['phone_number']) : null,
                (int)(bool)($input['is_active'] ?? 1),
                $id,
            ]);
        } else {
            $stmt = $pdo->prepare(
                "UPDATE users SET name = ?, email = ?, role = ?,
                                  phone_number = ?, is_active = ?
                 WHERE id = ?"
            );
            $stmt->execute([
                clean($input['name'] ?? ''),
                strtolower(trim($input['email'] ?? '')),
                $targetRole,
                !empty($input['phone_number']) ? clean($input['phone_number']) : null,
                (int)(bool)($input['is_active'] ?? 1),
                $id,
            ]);
        }

        respond(true, ['id' => $id], 'User updated successfully.');
    }

    // ── PUT: toggle active status (quick action) ──────
    if ($method === 'PUT' && $action === 'toggle' && $id > 0) {
        if ($id === $currentUserId) {
            respond(false, null, 'You cannot deactivate your own account.', 403);
        }

        $stmt = $pdo->prepare(
            "UPDATE users SET is_active = IF(is_active = 1, 0, 1) WHERE id = ?"
        );
        $stmt->execute([$id]);

        // Fetch new state
        $newState = $pdo->prepare("SELECT is_active FROM users WHERE id = ?");
        $newState->execute([$id]);
        $row = $newState->fetch(PDO::FETCH_ASSOC);

        respond(true, ['is_active' => (bool)$row['is_active']],
            $row['is_active'] ? 'User activated successfully.' : 'User deactivated successfully.');
    }

    // ── DELETE ────────────────────────────────────────
    if ($method === 'DELETE' && $id > 0) {
        if ($currentUserRole !== 'superadmin') {
            respond(false, null, 'Only superadmins can delete users.', 403);
        }
        if ($id === $currentUserId) {
            respond(false, null, 'You cannot delete your own account.', 403);
        }

        // Verify user exists
        $check = $pdo->prepare("SELECT id, role FROM users WHERE id = ?");
        $check->execute([$id]);
        $target = $check->fetch(PDO::FETCH_ASSOC);
        if (!$target) respond(false, null, 'User not found.', 404);

        // Soft-delete: anonymise PII and deactivate instead of hard delete
        // to preserve referential integrity (resident_reports, welfare_action_plans, etc.)
        $stmt = $pdo->prepare(
            "UPDATE users SET
                name         = '[Deleted User]',
                email        = CONCAT('deleted_', id, '@equiaid.invalid'),
                password     = '',
                phone_number = NULL,
                is_active    = 0
             WHERE id = ?"
        );
        $stmt->execute([$id]);

        respond(true, null, 'User removed successfully.');
    }

    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);

} catch (PDOException $e) {
    error_log('[UserMgmtAPI] DB Error: ' . $e->getMessage());
    respond(false, null, 'A database error occurred. Please try again.', 500);
} catch (Throwable $e) {
    error_log('[UserMgmtAPI] Error: ' . $e->getMessage());
    respond(false, null, 'An unexpected error occurred.', 500);
}