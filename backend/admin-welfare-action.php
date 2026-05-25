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

_ensure_welfare_action_plan_columns($pdo);

// Helper: send JSON response
function respond(bool $ok, $data = null, string $msg = '', int $code = 200): void {
    http_response_code($code);
    echo json_encode([
        'success' => $ok,
        'message' => $msg,
        'data'    => $data,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit();
}

// Helper: sanitize text input
function clean(string $val): string {
    return htmlspecialchars(strip_tags(trim($val)), ENT_QUOTES, 'UTF-8');
}

// ── Parse body for PUT requests ───────────────────────
$method = $_SERVER['REQUEST_METHOD'];
$input  = [];
if (in_array($method, ['POST', 'PUT'])) {
    $raw = file_get_contents('php://input');
    $input = json_decode($raw, true) ?? [];
    // Also accept form-encoded
    if (empty($input) && $method === 'POST') {
        $input = $_POST;
    }
}

// ── Route ─────────────────────────────────────────────
$id     = isset($_GET['id'])   ? (int)$_GET['id']   : 0;
$meta   = isset($_GET['meta']) ? (int)$_GET['meta']  : 0;

try {
    // ── GET: meta data (streets, users, events) ──────
    if ($method === 'GET' && $meta) {
        $streets = $pdo->query(
            "SELECT street_id, street_name, barangay, needs_welfare, current_risk_level
             FROM streets WHERE is_active = 1 ORDER BY street_name"
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

    // ── GET: single plan ─────────────────────────────
    if ($method === 'GET' && $id > 0) {
        $stmt = $pdo->prepare(
            "SELECT w.*,
                    w.priority,
                    w.needs_json,
                    w.steps_json,
                    w.beneficiary_type,
                    w.beneficiary_name,
                    w.target_date,
                    w.remarks,
                    s.street_name, s.barangay, s.needs_welfare,
                    u.name AS assigned_name, u.role AS assigned_role,
                    c.name AS creator_name,
                    e.event_name
             FROM welfare_action_plans w
             LEFT JOIN streets s ON s.street_id = w.street_id
             LEFT JOIN users   u ON u.id = w.assigned_to
             LEFT JOIN users   c ON c.id = w.created_by
             LEFT JOIN typhoon_events e ON e.event_id = w.event_id
             WHERE w.plan_id = ?"
        );
        $stmt->execute([$id]);
        $plan = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$plan) {
            respond(false, null, 'Plan not found.', 404);
        }

        // Decode extended JSON fields stored in description
        $plan = _decode_extended_fields($plan);

        respond(true, $plan);
    }

    // ── GET: list all plans ──────────────────────────
    if ($method === 'GET') {
        // KPI counts
        $kpi = $pdo->query(
            "SELECT
                COUNT(*) AS total,
                SUM(status = 'Planned')   AS planned,
                SUM(status = 'Ongoing')   AS ongoing,
                SUM(status = 'Completed') AS completed,
                SUM(status = 'Cancelled') AS cancelled
             FROM welfare_action_plans"
        )->fetch(PDO::FETCH_ASSOC);

        // Filters
        $where   = ['1=1'];
        $params  = [];

        if (!empty($_GET['status'])) {
            $where[] = 'w.status = ?';
            $params[] = $_GET['status'];
        }
        if (!empty($_GET['priority'])) {
            $where[] = 'w.priority = ?';
            $params[] = $_GET['priority'];
        }
        if (!empty($_GET['assistance_type'])) {
            $where[] = 'w.assistance_type = ?';
            $params[] = $_GET['assistance_type'];
        }
        if (!empty($_GET['search'])) {
            $like    = '%' . $_GET['search'] . '%';
            $where[] = '(s.street_name LIKE ? OR w.assistance_type LIKE ? OR u.name LIKE ?)';
            $params  = array_merge($params, [$like, $like, $like]);
        }

        $whereStr = implode(' AND ', $where);

        $page  = max(1, (int)($_GET['page'] ?? 1));
        $limit = 15;
        $offset = ($page - 1) * $limit;

        // Total count
        $countSql = "SELECT COUNT(*) FROM welfare_action_plans w
                     LEFT JOIN streets s ON s.street_id = w.street_id
                     LEFT JOIN users   u ON u.id = w.assigned_to
                     WHERE $whereStr";
        $countStmt = $pdo->prepare($countSql);
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        // Main query
        $sql = "SELECT
                    w.plan_id, w.street_id, w.event_id,
                    w.assistance_type, w.description,
                    w.status, w.planned_date, w.started_at, w.completed_at,
                    w.assigned_to, w.created_by, w.created_at, w.updated_at,
                    w.risk_level_before, w.risk_level_after,
                    w.vuln_score_before, w.vuln_score_after,
                    w.priority, w.needs_json, w.steps_json,
                    w.beneficiary_type, w.beneficiary_name,
                    w.target_date, w.remarks,
                    s.street_name, s.barangay, s.needs_welfare,
                    u.name AS assigned_name
                FROM welfare_action_plans w
                LEFT JOIN streets s ON s.street_id = w.street_id
                LEFT JOIN users   u ON u.id = w.assigned_to
                WHERE $whereStr
                ORDER BY
                    FIELD(w.status, 'Ongoing','Planned','Completed','Cancelled'),
                    w.planned_date ASC
                LIMIT $limit OFFSET $offset";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $plans = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Decode extended JSON for each plan
        $plans = array_map('_decode_extended_fields', $plans);

        respond(true, [
            'plans'      => $plans,
            'kpi'        => $kpi,
            'total'      => $total,
            'page'       => $page,
            'limit'      => $limit,
            'last_page'  => (int)ceil($total / $limit),
        ]);
    }

    // ── POST: create plan ────────────────────────────
    if ($method === 'POST') {
        $required = ['street_id', 'assistance_type', 'planned_date'];
        foreach ($required as $f) {
            if (empty($input[$f])) {
                respond(false, null, "Field '$f' is required.", 422);
            }
        }

        $description = clean($input['description'] ?? '');
        $priority = in_array($input['priority'] ?? '', ['High', 'Medium', 'Low'], true)
            ? $input['priority'] : 'Medium';
        $beneficiaryType = in_array($input['beneficiary_type'] ?? '', ['street', 'family', 'individual'], true)
            ? $input['beneficiary_type'] : 'street';
        $needsJson = json_encode(array_values($input['needs'] ?? []), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $stepsJson = json_encode(array_values($input['steps'] ?? []), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $stmt = $pdo->prepare(
            "INSERT INTO welfare_action_plans
                (street_id, event_id, assistance_type, description, status,
                 risk_level_before, planned_date, assigned_to, created_by,
                 priority, needs_json, steps_json, beneficiary_type, beneficiary_name,
                 target_date, remarks)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );

        $stmt->execute([
            (int) $input['street_id'],
            !empty($input['event_id']) ? (int)$input['event_id'] : null,
            clean($input['assistance_type']),
            $description,
            in_array($input['status'] ?? '', ['Planned','Ongoing','Completed','Cancelled'])
                ? $input['status'] : 'Planned',
            in_array($input['risk_level_before'] ?? '', ['RED','ORANGE','YELLOW','GREEN'])
                ? $input['risk_level_before'] : null,
            $input['planned_date'],
            !empty($input['assigned_to']) ? (int)$input['assigned_to'] : null,
            $_SESSION['user_id'] ?? null,
            $priority,
            $needsJson,
            $stepsJson,
            $beneficiaryType,
            clean($input['beneficiary_name'] ?? ''),
            !empty($input['target_date']) ? $input['target_date'] : null,
            clean($input['remarks'] ?? ''),
        ]);

        $newId = (int)$pdo->lastInsertId();

        // Auto-set started_at if Ongoing
        if (($input['status'] ?? 'Planned') === 'Ongoing') {
            $pdo->prepare("UPDATE welfare_action_plans SET started_at = NOW() WHERE plan_id = ?")
                ->execute([$newId]);
        }

        respond(true, ['plan_id' => $newId], 'Action plan created successfully.');
    }

    // ── PUT: update plan ─────────────────────────────
    if ($method === 'PUT' && $id > 0) {
        // Verify exists
        $exists = $pdo->prepare("SELECT plan_id FROM welfare_action_plans WHERE plan_id = ?");
        $exists->execute([$id]);
        if (!$exists->fetch()) {
            respond(false, null, 'Plan not found.', 404);
        }

        $description  = clean($input['description'] ?? '');
        $priority     = in_array($input['priority'] ?? '', ['High', 'Medium', 'Low'], true)
            ? $input['priority'] : 'Medium';
        $beneficiaryType = in_array($input['beneficiary_type'] ?? '', ['street', 'family', 'individual'], true)
            ? $input['beneficiary_type'] : 'street';
        $needsJson    = json_encode(array_values($input['needs'] ?? []), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $stepsJson    = json_encode(array_values($input['steps'] ?? []), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $newStatus    = in_array($input['status'] ?? '', ['Planned','Ongoing','Completed','Cancelled'])
                        ? $input['status'] : 'Planned';

        // Handle timestamps
        $startedAt   = null;
        $completedAt = null;

        if ($newStatus === 'Ongoing') {
            // Only set started_at if not already set
            $cur = $pdo->prepare("SELECT started_at FROM welfare_action_plans WHERE plan_id = ?");
            $cur->execute([$id]);
            $row = $cur->fetch(PDO::FETCH_ASSOC);
            $startedAt = $row['started_at'] ?? date('Y-m-d H:i:s');
        }
        if ($newStatus === 'Completed') {
            $completedAt = date('Y-m-d H:i:s');
        }

        $stmt = $pdo->prepare(
            "UPDATE welfare_action_plans SET
                street_id        = ?,
                event_id         = ?,
                assistance_type  = ?,
                description      = ?,
                status           = ?,
                risk_level_before= ?,
                risk_level_after = ?,
                planned_date     = ?,
                started_at       = COALESCE(?, started_at),
                completed_at     = COALESCE(?, completed_at),
                     assigned_to      = ?,
                     priority         = ?,
                     needs_json       = ?,
                     steps_json       = ?,
                     beneficiary_type = ?,
                     beneficiary_name = ?,
                     target_date      = ?,
                     remarks          = ?
             WHERE plan_id = ?"
        );

        $stmt->execute([
            (int) $input['street_id'],
            !empty($input['event_id']) ? (int)$input['event_id'] : null,
            clean($input['assistance_type']),
            $description,
            $newStatus,
            in_array($input['risk_level_before'] ?? '', ['RED','ORANGE','YELLOW','GREEN'])
                ? $input['risk_level_before'] : null,
            in_array($input['risk_level_after'] ?? '', ['RED','ORANGE','YELLOW','GREEN'])
                ? $input['risk_level_after'] : null,
            $input['planned_date'],
            $startedAt,
            $completedAt,
            !empty($input['assigned_to']) ? (int)$input['assigned_to'] : null,
            $priority,
            $needsJson,
            $stepsJson,
            $beneficiaryType,
            clean($input['beneficiary_name'] ?? ''),
            !empty($input['target_date']) ? $input['target_date'] : null,
            clean($input['remarks'] ?? ''),
            $id,
        ]);

        respond(true, ['plan_id' => $id], 'Action plan updated successfully.');
    }

    // ── DELETE ───────────────────────────────────────
    if ($method === 'DELETE' && $id > 0) {
        $stmt = $pdo->prepare("DELETE FROM welfare_action_plans WHERE plan_id = ?");
        $stmt->execute([$id]);

        if ($stmt->rowCount() === 0) {
            respond(false, null, 'Plan not found.', 404);
        }

        respond(true, null, 'Action plan deleted successfully.');
    }

    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);

} catch (PDOException $e) {
    error_log('[WelfareActionAPI] DB Error: ' . $e->getMessage());
    respond(false, null, 'A database error occurred. Please try again.', 500);
} catch (Throwable $e) {
    error_log('[WelfareActionAPI] Error: ' . $e->getMessage());
    respond(false, null, 'An unexpected error occurred.', 500);
}

/* ══════════════════════════════════════════════════════
   HELPERS — Extended fields stored in dedicated columns
   Older rows may still use the legacy `__EXT__:{…}||description` format,
   so decoding keeps backward compatibility.
════════════════════════════════════════════════════════ */

function _ensure_welfare_action_plan_columns(PDO $pdo): void {
    $columns = [
        'priority'         => "ALTER TABLE welfare_action_plans ADD COLUMN priority enum('High','Medium','Low') NOT NULL DEFAULT 'Medium' AFTER vuln_score_after",
        'needs_json'       => "ALTER TABLE welfare_action_plans ADD COLUMN needs_json text DEFAULT NULL AFTER priority",
        'steps_json'       => "ALTER TABLE welfare_action_plans ADD COLUMN steps_json text DEFAULT NULL AFTER needs_json",
        'beneficiary_type' => "ALTER TABLE welfare_action_plans ADD COLUMN beneficiary_type enum('street','family','individual') NOT NULL DEFAULT 'street' AFTER steps_json",
        'beneficiary_name' => "ALTER TABLE welfare_action_plans ADD COLUMN beneficiary_name varchar(255) DEFAULT NULL AFTER beneficiary_type",
        'target_date'      => "ALTER TABLE welfare_action_plans ADD COLUMN target_date date DEFAULT NULL AFTER beneficiary_name",
        'remarks'          => "ALTER TABLE welfare_action_plans ADD COLUMN remarks text DEFAULT NULL AFTER target_date",
    ];

    $stmt = $pdo->prepare(
        "SELECT COLUMN_NAME
           FROM INFORMATION_SCHEMA.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = 'welfare_action_plans'"
    );
    $stmt->execute();
    $existing = array_fill_keys($stmt->fetchAll(PDO::FETCH_COLUMN), true);

    foreach ($columns as $name => $sql) {
        if (!isset($existing[$name])) {
            $pdo->exec($sql);
        }
    }
}

function _decode_extended_fields(array $plan): array {
    $desc = $plan['description'] ?? '';
    if (str_starts_with($desc, '__EXT__:')) {
        $parts = explode('||', substr($desc, 8), 2);
        $ext   = json_decode($parts[0] ?? '{}', true) ?? [];
        $plan['priority']         = $ext['priority']         ?? ($plan['priority'] ?? 'Medium');
        $plan['needs']            = $ext['needs']            ?? (json_decode($plan['needs_json'] ?? '[]', true) ?? []);
        $plan['steps']            = $ext['steps']            ?? (json_decode($plan['steps_json'] ?? '[]', true) ?? []);
        $plan['beneficiary_type'] = $ext['beneficiary_type'] ?? ($plan['beneficiary_type'] ?? 'street');
        $plan['beneficiary_name'] = $ext['beneficiary_name'] ?? ($plan['beneficiary_name'] ?? '');
        $plan['target_date']      = $ext['target_date']      ?? ($plan['target_date'] ?? '');
        $plan['remarks']          = $ext['remarks']          ?? ($plan['remarks'] ?? '');
        $plan['description']      = $parts[1] ?? '';
    } else {
        $plan['priority']         = $plan['priority']         ?? 'Medium';
        $plan['needs']            = isset($plan['needs_json']) ? (json_decode($plan['needs_json'], true) ?? []) : [];
        $plan['steps']            = isset($plan['steps_json']) ? (json_decode($plan['steps_json'], true) ?? []) : [];
        $plan['beneficiary_type'] = $plan['beneficiary_type'] ?? 'street';
        $plan['beneficiary_name'] = $plan['beneficiary_name'] ?? '';
        $plan['target_date']      = $plan['target_date']      ?? '';
        $plan['remarks']          = $plan['remarks']          ?? '';
    }

    unset($plan['needs_json'], $plan['steps_json']);
    return $plan;
}