<?php


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

// ── Helper: send JSON response ──────────────────────────────
function respond(bool $ok, $data = null, string $msg = '', int $code = 200): void {
    http_response_code($code);
    echo json_encode([
        'success' => $ok,
        'message' => $msg,
        'data'    => $data,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit();
}

// ── Helper: sanitize text input ──────────────────────────────
function clean(string $val): string {
    return htmlspecialchars(strip_tags(trim($val)), ENT_QUOTES, 'UTF-8');
}

// ── Parse body for PUT requests ───────────────────────
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
$id   = isset($_GET['id'])   ? (int)$_GET['id']  : 0;
$meta = isset($_GET['meta']) ? (int)$_GET['meta'] : 0;

try {

    // ── GET: meta (streets, users, events) ────────────
    if ($method === 'GET' && $meta) {
        $streets = $pdo->query(
            "SELECT street_id, street_name, barangay FROM streets WHERE is_active = 1 ORDER BY street_name"
        )->fetchAll(PDO::FETCH_ASSOC);

        $users = $pdo->query(
            "SELECT id, name, role FROM users
             WHERE is_active = 1 AND role IN ('admin','staff','dswd_officer','superadmin')
             ORDER BY name"
        )->fetchAll(PDO::FETCH_ASSOC);

        $events = $pdo->query(
            "SELECT event_id, event_name, date_started AS event_date
             FROM typhoon_events ORDER BY date_started DESC LIMIT 20"
        )->fetchAll(PDO::FETCH_ASSOC);

        respond(true, compact('streets', 'users', 'events'));
    }

    // ── GET: single report ────────────────────────────
    if ($method === 'GET' && $id > 0) {
        $stmt = $pdo->prepare(
            "SELECT r.*,
                    s.street_name, s.barangay,
                    u.name  AS reporter_name, u.phone_number AS reporter_phone,
                    v.name  AS verifier_name,
                    e.event_name
             FROM resident_reports r
             LEFT JOIN streets s        ON s.street_id = r.street_id
             LEFT JOIN users   u        ON u.id = r.user_id
             LEFT JOIN users   v        ON v.id = r.verified_by
             LEFT JOIN typhoon_events e ON e.event_id = r.event_id
             WHERE r.report_id = ?"
        );
        $stmt->execute([$id]);
        $report = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$report) respond(false, null, 'Report not found.', 404);

        respond(true, $report);
    }

    // ── GET: list all reports ─────────────────────────
    if ($method === 'GET') {
        // KPI counts
        $kpi = $pdo->query(
            "SELECT
                COUNT(*)                              AS total,
                SUM(status = 'Pending')               AS pending,
                SUM(status = 'Verified')              AS verified,
                SUM(status = 'In Progress')           AS in_progress,
                SUM(status = 'Resolved')              AS resolved,
                SUM(status = 'Dismissed')             AS dismissed,
                SUM(severity = 'Severe')              AS severe
             FROM resident_reports"
        )->fetch(PDO::FETCH_ASSOC);

        // Filters
        $where  = ['1=1'];
        $params = [];

        if (!empty($_GET['status'])) {
            $where[]  = 'r.status = ?';
            $params[] = $_GET['status'];
        }
        if (!empty($_GET['severity'])) {
            $where[]  = 'r.severity = ?';
            $params[] = $_GET['severity'];
        }
        if (!empty($_GET['report_type'])) {
            $where[]  = 'r.report_type = ?';
            $params[] = $_GET['report_type'];
        }
        if (!empty($_GET['street_id'])) {
            $where[]  = 'r.street_id = ?';
            $params[] = (int)$_GET['street_id'];
        }
        if (!empty($_GET['search'])) {
            $like     = '%' . $_GET['search'] . '%';
            $where[]  = '(s.street_name LIKE ? OR u.name LIKE ? OR r.description LIKE ? OR r.report_type LIKE ?)';
            $params   = array_merge($params, [$like, $like, $like, $like]);
        }

        $whereStr = implode(' AND ', $where);

        // Sorting
        $sortAllowed = ['created_at', 'severity', 'status', 'report_type'];
        $sortCol     = in_array($_GET['sort'] ?? '', $sortAllowed) ? $_GET['sort'] : 'created_at';
        $sortDir     = ($_GET['dir'] ?? 'desc') === 'asc' ? 'ASC' : 'DESC';

        // Pagination
        $page   = max(1, (int)($_GET['page'] ?? 1));
        $limit  = 15;
        $offset = ($page - 1) * $limit;

        // Total count
        $countSql  = "SELECT COUNT(*) FROM resident_reports r
                      LEFT JOIN streets s ON s.street_id = r.street_id
                      LEFT JOIN users   u ON u.id = r.user_id
                      WHERE $whereStr";
        $countStmt = $pdo->prepare($countSql);
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        // Main query — status priority ordering first, then user sort
        $sql = "SELECT
                    r.report_id, r.user_id, r.street_id, r.event_id,
                    r.report_type, r.severity, r.description,
                    r.image_path, r.latitude, r.longitude,
                    r.status, r.verified_by, r.verified_at,
                    r.resolution_notes, r.created_at, r.updated_at,
                    s.street_name, s.barangay,
                    u.name AS reporter_name, u.phone_number AS reporter_phone,
                    v.name AS verifier_name
                FROM resident_reports r
                LEFT JOIN streets s ON s.street_id = r.street_id
                LEFT JOIN users   u ON u.id = r.user_id
                LEFT JOIN users   v ON v.id = r.verified_by
                WHERE $whereStr
                ORDER BY
                    FIELD(r.status,'Pending','In Progress','Verified','Resolved','Dismissed'),
                    r.$sortCol $sortDir
                LIMIT $limit OFFSET $offset";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);

        respond(true, [
            'reports'   => $reports,
            'kpi'       => $kpi,
            'total'     => $total,
            'page'      => $page,
            'limit'     => $limit,
            'last_page' => (int)ceil($total / $limit),
        ]);
    }

    // ── PUT: update status / resolution notes ─────────
    if ($method === 'PUT' && $id > 0) {
        // Verify exists
        $exists = $pdo->prepare("SELECT report_id, status FROM resident_reports WHERE report_id = ?");
        $exists->execute([$id]);
        $current = $exists->fetch(PDO::FETCH_ASSOC);
        if (!$current) respond(false, null, 'Report not found.', 404);

        $validStatuses = ['Pending', 'Verified', 'In Progress', 'Resolved', 'Dismissed'];
        $newStatus = in_array($input['status'] ?? '', $validStatuses)
            ? $input['status'] : $current['status'];

        $resolutionNotes = isset($input['resolution_notes'])
            ? clean($input['resolution_notes']) : null;

        // Set verified_at / verified_by when moving out of Pending
        $verifiedBy = null;
        $verifiedAt = null;
        if ($newStatus !== 'Pending' && $current['status'] === 'Pending') {
            $verifiedBy = $_SESSION['user_id'] ?? null;
            $verifiedAt = date('Y-m-d H:i:s');
        }

        // If already verified, keep existing verifier unless re-set
        if ($newStatus !== 'Pending' && $current['status'] !== 'Pending') {
            // Preserve existing verified_by / verified_at
            $stmt = $pdo->prepare(
                "UPDATE resident_reports SET
                    status           = ?,
                    resolution_notes = COALESCE(?, resolution_notes),
                    updated_at       = NOW()
                 WHERE report_id = ?"
            );
            $stmt->execute([$newStatus, $resolutionNotes ?: null, $id]);
        } else {
            $stmt = $pdo->prepare(
                "UPDATE resident_reports SET
                    status           = ?,
                    resolution_notes = COALESCE(?, resolution_notes),
                    verified_by      = COALESCE(?, verified_by),
                    verified_at      = COALESCE(?, verified_at),
                    updated_at       = NOW()
                 WHERE report_id = ?"
            );
            $stmt->execute([$newStatus, $resolutionNotes ?: null, $verifiedBy, $verifiedAt, $id]);
        }

        respond(true, ['report_id' => $id], 'Report status updated successfully.');
    }

    // ── DELETE ────────────────────────────────────────
    if ($method === 'DELETE' && $id > 0) {
        $stmt = $pdo->prepare("DELETE FROM resident_reports WHERE report_id = ?");
        $stmt->execute([$id]);

        if ($stmt->rowCount() === 0) respond(false, null, 'Report not found.', 404);

        respond(true, null, 'Report deleted successfully.');
    }

    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);

} catch (PDOException $e) {
    error_log('[ResidentReportsAPI] DB Error: ' . $e->getMessage());
    respond(false, null, 'A database error occurred. Please try again.', 500);
} catch (Throwable $e) {
    error_log('[ResidentReportsAPI] Error: ' . $e->getMessage());
    respond(false, null, 'An unexpected error occurred.', 500);
}