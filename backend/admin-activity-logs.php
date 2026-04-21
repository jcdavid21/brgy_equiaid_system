<?php

// ── Session / Auth ─────────────────────────────────────────
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['user_role']) || !in_array($_SESSION['user_role'], ['admin', 'superadmin'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/db.php'; // provides $pdo

// ── Ensure activity_logs table exists ──────────────────────
$pdo->exec("
    CREATE TABLE IF NOT EXISTS `activity_logs` (
        `id`         INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
        `user_id`    INT(10) UNSIGNED DEFAULT NULL COMMENT 'FK → users.id; NULL for system actions',
        `action`     VARCHAR(512)     NOT NULL   COMMENT 'Human-readable description of the action performed',
        `module`     VARCHAR(80)      DEFAULT NULL COMMENT 'Which module triggered this log (e.g. Reports, Users)',
        `ip_address` VARCHAR(45)      DEFAULT NULL COMMENT 'IPv4 or IPv6 of the requesting client',
        `created_at` DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_al_user`    (`user_id`),
        KEY `idx_al_module`  (`module`),
        KEY `idx_al_created` (`created_at`),
        CONSTRAINT `fk_al_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
      COMMENT='System-wide activity audit trail — every meaningful admin/staff action'
");

// ── Helper: send JSON response ─────────────────────────────
function respond(bool $ok, $data = null, string $msg = '', int $code = 200): void {
    http_response_code($code);
    echo json_encode([
        'success' => $ok,
        'message' => $msg,
        'data'    => $data,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit();
}

// ── Helper: sanitize text input ────────────────────────────
function clean(string $val): string {
    return htmlspecialchars(strip_tags(trim($val)), ENT_QUOTES, 'UTF-8');
}

// ── Helper: get client IP ─────────────────────────────────
function clientIp(): string {
    foreach (['HTTP_X_FORWARDED_FOR', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'] as $key) {
        if (!empty($_SERVER[$key])) {
            // X-Forwarded-For may be comma-separated; take the first
            return trim(explode(',', $_SERVER[$key])[0]);
        }
    }
    return 'unknown';
}

// ── Parse body ────────────────────────────────────────────
$method = $_SERVER['REQUEST_METHOD'];
$input  = [];
if (in_array($method, ['POST', 'PUT'])) {
    $raw   = file_get_contents('php://input');
    $input = json_decode($raw, true) ?? [];
    if (empty($input) && $method === 'POST') {
        $input = $_POST;
    }
}

$id   = isset($_GET['id'])   ? (int)$_GET['id']  : 0;
$meta = isset($_GET['meta']) ? (int)$_GET['meta'] : 0;

try {

    // ── GET: meta (user list for filter dropdown) ──────────
    if ($method === 'GET' && $meta) {
        $users = $pdo->query(
            "SELECT DISTINCT u.id, u.name, u.role
             FROM users u
             INNER JOIN activity_logs al ON al.user_id = u.id
             WHERE u.is_active = 1
             ORDER BY u.name"
        )->fetchAll(PDO::FETCH_ASSOC);

        // Also grab distinct modules for the module filter
        $modules = $pdo->query(
            "SELECT DISTINCT module FROM activity_logs WHERE module IS NOT NULL ORDER BY module"
        )->fetchAll(PDO::FETCH_COLUMN);

        respond(true, compact('users', 'modules'));
    }

    // ── GET: list logs (with pagination, search, filters) ──
    if ($method === 'GET') {
        // ── Filters ─────────────────────────────────────
        $where  = ['1=1'];
        $params = [];

        if (!empty($_GET['user_id'])) {
            $where[]  = 'al.user_id = ?';
            $params[] = (int)$_GET['user_id'];
        }
        if (!empty($_GET['module'])) {
            $where[]  = 'al.module = ?';
            $params[] = $_GET['module'];
        }
        if (!empty($_GET['date_from'])) {
            $where[]  = 'DATE(al.created_at) >= ?';
            $params[] = $_GET['date_from'];
        }
        if (!empty($_GET['date_to'])) {
            $where[]  = 'DATE(al.created_at) <= ?';
            $params[] = $_GET['date_to'];
        }
        if (!empty($_GET['search'])) {
            $like     = '%' . $_GET['search'] . '%';
            $where[]  = '(al.action LIKE ? OR u.name LIKE ? OR al.module LIKE ? OR al.ip_address LIKE ?)';
            $params   = array_merge($params, [$like, $like, $like, $like]);
        }

        $whereStr = implode(' AND ', $where);

        // ── Sorting ───────────────────────────────────
        $sortAllowed = ['created_at', 'action', 'module'];
        $sortCol     = in_array($_GET['sort'] ?? '', $sortAllowed) ? $_GET['sort'] : 'created_at';
        $sortDir     = ($_GET['dir'] ?? 'desc') === 'asc' ? 'ASC' : 'DESC';

        // ── Pagination ────────────────────────────────
        $page   = max(1, (int)($_GET['page'] ?? 1));
        $limit  = 20;
        $offset = ($page - 1) * $limit;

        // ── KPI counts (unfiltered totals) ────────────
        $kpi = $pdo->query(
            "SELECT
                COUNT(*)                                                       AS total,
                COUNT(DISTINCT user_id)                                        AS unique_users,
                SUM(DATE(created_at) = CURDATE())                              AS today,
                SUM(created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY))            AS last_7_days
             FROM activity_logs"
        )->fetch(PDO::FETCH_ASSOC);

        // ── Total matching rows ───────────────────────
        $countSql  = "SELECT COUNT(*)
                      FROM activity_logs al
                      LEFT JOIN users u ON u.id = al.user_id
                      WHERE $whereStr";
        $countStmt = $pdo->prepare($countSql);
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        // ── Main query ────────────────────────────────
        $sql = "SELECT
                    al.id,
                    al.user_id,
                    al.action,
                    al.module,
                    al.ip_address,
                    al.created_at,
                    u.name AS user_name,
                    u.role AS user_role
                FROM activity_logs al
                LEFT JOIN users u ON u.id = al.user_id
                WHERE $whereStr
                ORDER BY al.$sortCol $sortDir
                LIMIT $limit OFFSET $offset";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

        respond(true, [
            'logs'      => $logs,
            'kpi'       => $kpi,
            'total'     => $total,
            'page'      => $page,
            'limit'     => $limit,
            'last_page' => (int)ceil($total / $limit),
        ]);
    }

    // ── POST: insert a new log entry ───────────────────────
    if ($method === 'POST') {
        $action  = clean($input['action'] ?? '');
        $module  = isset($input['module']) ? clean($input['module']) : null;
        $userId  = isset($input['user_id']) ? (int)$input['user_id'] : ($_SESSION['user_id'] ?? null);
        $ip      = clientIp();

        if (empty($action)) {
            respond(false, null, 'action is required.', 422);
        }

        $stmt = $pdo->prepare(
            "INSERT INTO activity_logs (user_id, action, module, ip_address)
             VALUES (?, ?, ?, ?)"
        );
        $stmt->execute([$userId ?: null, $action, $module ?: null, $ip]);
        $newId = (int)$pdo->lastInsertId();

        respond(true, ['id' => $newId], 'Log entry created.');
    }

    // ── DELETE: remove a single log (superadmin only) ──────
    if ($method === 'DELETE' && $id > 0) {
        if ($_SESSION['user_role'] !== 'superadmin') {
            respond(false, null, 'Only superadmins may delete log entries.', 403);
        }

        $stmt = $pdo->prepare("DELETE FROM activity_logs WHERE id = ?");
        $stmt->execute([$id]);

        if ($stmt->rowCount() === 0) respond(false, null, 'Log entry not found.', 404);

        respond(true, null, 'Log entry deleted.');
    }

    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);

} catch (PDOException $e) {
    error_log('[ActivityLogsAPI] DB Error: ' . $e->getMessage());
    respond(false, null, 'A database error occurred. Please try again.', 500);
} catch (Throwable $e) {
    error_log('[ActivityLogsAPI] Error: ' . $e->getMessage());
    respond(false, null, 'An unexpected error occurred.', 500);
}