<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/db.php'; // provides $pdo

/* ── Helpers ──────────────────────────────────────────── */
function respond(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function error_respond(string $message, int $status = 400): void
{
    respond(['ok' => false, 'error' => $message], $status);
}

/* ── Auth guard ───────────────────────────────────────── */
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    error_respond('Unauthorized. Admin access required.', 401);
}

if (!$pdo) {
    error_respond('Database connection unavailable.', 503);
}

$method = $_SERVER['REQUEST_METHOD'];
$action = trim($_GET['action'] ?? '');

/* ── Input helpers ────────────────────────────────────── */
function body(): array
{
    $raw = file_get_contents('php://input');
    return json_decode($raw ?: '{}', true) ?? [];
}

function gp(string $key, mixed $default = null): mixed
{
    return $_GET[$key] ?? $default;
}

function bp(array $data, string $key, mixed $default = null): mixed
{
    return $data[$key] ?? $default;
}

/* ═══════════════════════════════════════════════════════
   GET ROUTES
═══════════════════════════════════════════════════════ */
if ($method === 'GET') {
    switch ($action) {

        /* ── Overview KPIs ──────────────────────────── */
        case 'overview':
            $total = (int) $pdo->query(
                "SELECT COUNT(*) FROM resources"
            )->fetchColumn();

            $total_available = (int) $pdo->query(
                "SELECT COALESCE(SUM(qty_available), 0) FROM resources"
            )->fetchColumn();

            $total_distributed = (int) $pdo->query(
                "SELECT COALESCE(SUM(qty_distributed), 0) FROM resources"
            )->fetchColumn();

            $total_reserved = (int) $pdo->query(
                "SELECT COALESCE(SUM(qty_reserved), 0) FROM resources"
            )->fetchColumn();

            // Budget value of available stock
            $total_stock_value = (float) $pdo->query(
                "SELECT COALESCE(SUM(unit_cost * qty_available), 0) FROM resources WHERE unit_cost IS NOT NULL"
            )->fetchColumn();

            // Total cost of all distributions
            $total_dist_cost = (float) $pdo->query(
                "SELECT COALESCE(SUM(total_cost), 0) FROM resource_distributions"
            )->fetchColumn();

            // Low stock count (qty_available < restock_threshold)
            $low_stock = (int) $pdo->query(
                "SELECT COUNT(*) FROM resources WHERE qty_available < restock_threshold AND qty_available > 0"
            )->fetchColumn();

            // Critical (zero)
            $zero_stock = (int) $pdo->query(
                "SELECT COUNT(*) FROM resources WHERE qty_available = 0"
            )->fetchColumn();

            // Distribution count this month
            $dist_this_month = (int) $pdo->query(
                "SELECT COUNT(*) FROM resource_distributions
                 WHERE YEAR(distributed_at) = YEAR(NOW())
                   AND MONTH(distributed_at) = MONTH(NOW())"
            )->fetchColumn();

            respond([
                'ok'                => true,
                'total_types'       => $total,
                'total_available'   => $total_available,
                'total_distributed' => $total_distributed,
                'total_reserved'    => $total_reserved,
                'total_stock_value' => $total_stock_value,
                'total_dist_cost'   => $total_dist_cost,
                'low_stock'         => $low_stock,
                'zero_stock'        => $zero_stock,
                'dist_this_month'   => $dist_this_month,
            ]);

        /* ── Resource inventory list ─────────────────── */
        case 'list_resources':
            $page     = max(1, (int) gp('page', 1));
            $per_page = min(50, max(5, (int) gp('per_page', 20)));
            $search   = trim((string) gp('search', ''));
            $category = trim((string) gp('category', ''));
            $offset   = ($page - 1) * $per_page;

            $where = ['1=1'];
            $params = [];

            if ($search !== '') {
                $where[]  = "(resource_name LIKE :search OR supplier LIKE :search2)";
                $params[':search']  = "%$search%";
                $params[':search2'] = "%$search%";
            }
            if ($category !== '') {
                $where[]  = "category = :category";
                $params[':category'] = $category;
            }

            $whereStr = implode(' AND ', $where);

            $total_count = (int) $pdo->prepare(
                "SELECT COUNT(*) FROM resources WHERE $whereStr"
            )->execute($params) ?
                $pdo->prepare("SELECT COUNT(*) FROM resources WHERE $whereStr")->execute($params) :
                0;

            // Re-execute for count cleanly
            $countStmt = $pdo->prepare("SELECT COUNT(*) FROM resources WHERE $whereStr");
            $countStmt->execute($params);
            $total_count = (int) $countStmt->fetchColumn();

            $stmt = $pdo->prepare(
                "SELECT resource_id, resource_name, category, unit, unit_cost,
                        qty_available, qty_reserved, qty_distributed,
                        restock_threshold, supplier, notes, created_at, updated_at
                 FROM resources
                 WHERE $whereStr
                 ORDER BY category ASC, resource_name ASC
                 LIMIT :limit OFFSET :offset"
            );
            foreach ($params as $k => $v) $stmt->bindValue($k, $v);
            $stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            respond([
                'ok'          => true,
                'data'        => $rows,
                'total'       => $total_count,
                'page'        => $page,
                'per_page'    => $per_page,
                'total_pages' => (int) ceil($total_count / $per_page),
            ]);

        /* ── Distribution log ────────────────────────── */
        case 'list_distributions':
            $page      = max(1, (int) gp('page', 1));
            $per_page  = min(50, max(5, (int) gp('per_page', 15)));
            $offset    = ($page - 1) * $per_page;
            $resource  = (int) gp('resource_id', 0);
            $event_id  = (int) gp('event_id', 0);

            $where  = ['1=1'];
            $params = [];

            if ($resource > 0) {
                $where[]          = "rd.resource_id = :rid";
                $params[':rid']   = $resource;
            }
            if ($event_id > 0) {
                $where[]          = "rd.event_id = :eid";
                $params[':eid']   = $event_id;
            }

            $whereStr = implode(' AND ', $where);

            $countStmt = $pdo->prepare(
                "SELECT COUNT(*) FROM resource_distributions rd WHERE $whereStr"
            );
            $countStmt->execute($params);
            $total_count = (int) $countStmt->fetchColumn();

            $stmt = $pdo->prepare(
                "SELECT rd.dist_id, rd.resource_id, r.resource_name, r.category, r.unit,
                        rd.street_id, s.street_name,
                        rd.event_id, te.event_name,
                        rd.qty_distributed, rd.unit_cost_at_time, rd.total_cost,
                        rd.distributed_by, u.name AS distributed_by_name,
                        rd.distributed_at, rd.recipient_count, rd.notes
                 FROM resource_distributions rd
                 LEFT JOIN resources r ON r.resource_id = rd.resource_id
                 LEFT JOIN streets s ON s.street_id = rd.street_id
                 LEFT JOIN typhoon_events te ON te.event_id = rd.event_id
                 LEFT JOIN users u ON u.id = rd.distributed_by
                 WHERE $whereStr
                 ORDER BY rd.distributed_at DESC
                 LIMIT :limit OFFSET :offset"
            );
            foreach ($params as $k => $v) $stmt->bindValue($k, $v);
            $stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            respond([
                'ok'          => true,
                'data'        => $rows,
                'total'       => $total_count,
                'page'        => $page,
                'per_page'    => $per_page,
                'total_pages' => (int) ceil($total_count / $per_page),
            ]);

        /* ── Single resource ─────────────────────────── */
        case 'get_resource':
            $id = (int) gp('id', 0);
            if ($id <= 0) error_respond('Missing resource id.');
            $stmt = $pdo->prepare("SELECT * FROM resources WHERE resource_id = :id");
            $stmt->execute([':id' => $id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) error_respond('Resource not found.', 404);
            respond(['ok' => true, 'data' => $row]);

        /* ── Streets list (for dispatch form) ────────── */
        case 'get_streets':
            $rows = $pdo->query(
                "SELECT street_id, street_name, zone_id, current_risk_level, total_households
                 FROM streets WHERE is_active = 1 ORDER BY street_name ASC"
            )->fetchAll(PDO::FETCH_ASSOC);
            respond(['ok' => true, 'data' => $rows]);

        /* ── Events list ─────────────────────────────── */
        case 'get_events':
            $rows = $pdo->query(
                "SELECT event_id, event_name, status FROM typhoon_events ORDER BY event_id DESC"
            )->fetchAll(PDO::FETCH_ASSOC);
            respond(['ok' => true, 'data' => $rows]);

        default:
            error_respond('Unknown action.', 404);
    }
}

/* ═══════════════════════════════════════════════════════
   POST ROUTES
═══════════════════════════════════════════════════════ */
if ($method === 'POST') {
    $data = body();

    switch ($action) {

        /* ── Create resource ─────────────────────────── */
        case 'create_resource':
            $name  = trim((string) bp($data, 'resource_name', ''));
            $cat   = trim((string) bp($data, 'category', 'Other'));
            $unit  = trim((string) bp($data, 'unit', 'pcs'));
            $cost  = bp($data, 'unit_cost', null);
            $avail = (int) bp($data, 'qty_available', 0);
            $rsv   = (int) bp($data, 'qty_reserved', 0);
            $dist  = (int) bp($data, 'qty_distributed', 0);
            $thresh= (int) bp($data, 'restock_threshold', 50);
            $supp  = trim((string) bp($data, 'supplier', '')) ?: null;
            $notes = trim((string) bp($data, 'notes', '')) ?: null;

            if ($name === '') error_respond('Resource name is required.');
            $allowed_cats = ['Food','Medical','Water','Shelter','Transport','Other'];
            if (!in_array($cat, $allowed_cats, true)) error_respond('Invalid category.');

            // Check unique name
            $chk = $pdo->prepare("SELECT COUNT(*) FROM resources WHERE resource_name = :n");
            $chk->execute([':n' => $name]);
            if ((int) $chk->fetchColumn() > 0) error_respond('A resource with that name already exists.');

            $stmt = $pdo->prepare(
                "INSERT INTO resources
                 (resource_name, category, unit, unit_cost, qty_available, qty_reserved,
                  qty_distributed, restock_threshold, supplier, notes)
                 VALUES (:name, :cat, :unit, :cost, :avail, :rsv, :dist, :thresh, :supp, :notes)"
            );
            $stmt->execute([
                ':name'   => $name,
                ':cat'    => $cat,
                ':unit'   => $unit,
                ':cost'   => ($cost !== null && $cost !== '') ? (float) $cost : null,
                ':avail'  => $avail,
                ':rsv'    => $rsv,
                ':dist'   => $dist,
                ':thresh' => $thresh,
                ':supp'   => $supp,
                ':notes'  => $notes,
            ]);
            $new_id = (int) $pdo->lastInsertId();
            respond(['ok' => true, 'resource_id' => $new_id, 'message' => 'Resource created successfully.']);

        /* ── Update resource ─────────────────────────── */
        case 'update_resource':
            $id    = (int) bp($data, 'resource_id', 0);
            if ($id <= 0) error_respond('Missing resource_id.');

            $name  = trim((string) bp($data, 'resource_name', ''));
            $cat   = trim((string) bp($data, 'category', 'Other'));
            $unit  = trim((string) bp($data, 'unit', 'pcs'));
            $cost  = bp($data, 'unit_cost', null);
            $avail = (int) bp($data, 'qty_available', 0);
            $rsv   = (int) bp($data, 'qty_reserved', 0);
            $dist  = (int) bp($data, 'qty_distributed', 0);
            $thresh= (int) bp($data, 'restock_threshold', 50);
            $supp  = trim((string) bp($data, 'supplier', '')) ?: null;
            $notes = trim((string) bp($data, 'notes', '')) ?: null;

            if ($name === '') error_respond('Resource name is required.');

            // Unique name check (excluding self)
            $chk = $pdo->prepare("SELECT COUNT(*) FROM resources WHERE resource_name = :n AND resource_id != :id");
            $chk->execute([':n' => $name, ':id' => $id]);
            if ((int) $chk->fetchColumn() > 0) error_respond('Another resource with that name already exists.');

            $stmt = $pdo->prepare(
                "UPDATE resources SET
                   resource_name     = :name,
                   category          = :cat,
                   unit              = :unit,
                   unit_cost         = :cost,
                   qty_available     = :avail,
                   qty_reserved      = :rsv,
                   qty_distributed   = :dist,
                   restock_threshold = :thresh,
                   supplier          = :supp,
                   notes             = :notes
                 WHERE resource_id = :id"
            );
            $stmt->execute([
                ':name'   => $name,
                ':cat'    => $cat,
                ':unit'   => $unit,
                ':cost'   => ($cost !== null && $cost !== '') ? (float) $cost : null,
                ':avail'  => $avail,
                ':rsv'    => $rsv,
                ':dist'   => $dist,
                ':thresh' => $thresh,
                ':supp'   => $supp,
                ':notes'  => $notes,
                ':id'     => $id,
            ]);
            respond(['ok' => true, 'message' => 'Resource updated successfully.']);

        /* ── Delete resource ─────────────────────────── */
        case 'delete_resource':
            $id = (int) bp($data, 'resource_id', 0);
            if ($id <= 0) error_respond('Missing resource_id.');

            // Check if has distributions
            $chk = $pdo->prepare("SELECT COUNT(*) FROM resource_distributions WHERE resource_id = :id");
            $chk->execute([':id' => $id]);
            if ((int) $chk->fetchColumn() > 0) {
                error_respond('Cannot delete: this resource has distribution records. Remove distributions first.');
            }

            $stmt = $pdo->prepare("DELETE FROM resources WHERE resource_id = :id");
            $stmt->execute([':id' => $id]);
            respond(['ok' => true, 'message' => 'Resource deleted.']);

        /* ── Dispatch resource ───────────────────────── */
        case 'dispatch_resource':
            $res_id   = (int) bp($data, 'resource_id', 0);
            $street_id= (int) bp($data, 'street_id', 0);
            $event_id = bp($data, 'event_id', null);
            if ($event_id !== null) $event_id = (int) $event_id ?: null;
            $qty      = (int) bp($data, 'qty_distributed', 0);
            $recips   = (int) bp($data, 'recipient_count', 0);
            $notes    = trim((string) bp($data, 'notes', '')) ?: null;
            $dist_at  = trim((string) bp($data, 'distributed_at', '')) ?: date('Y-m-d H:i:s');

            if ($res_id <= 0)    error_respond('Select a resource.');
            if ($street_id <= 0) error_respond('Select a destination street.');
            if ($qty <= 0)       error_respond('Quantity must be greater than 0.');

            // Fetch resource
            $res = $pdo->prepare("SELECT qty_available, unit_cost FROM resources WHERE resource_id = :id FOR UPDATE");
            $pdo->beginTransaction();
            $res->execute([':id' => $res_id]);
            $row = $res->fetch(PDO::FETCH_ASSOC);

            if (!$row) {
                $pdo->rollBack();
                error_respond('Resource not found.', 404);
            }

            $available = (int) $row['qty_available'];
            if ($qty > $available) {
                $pdo->rollBack();
                error_respond("Insufficient stock. Only $available units available.");
            }

            $unit_cost = $row['unit_cost'];

            // Insert distribution
            $ins = $pdo->prepare(
                "INSERT INTO resource_distributions
                 (street_id, resource_id, event_id, qty_distributed, unit_cost_at_time,
                  distributed_by, distributed_at, recipient_count, notes)
                 VALUES (:sid, :rid, :eid, :qty, :cost, :by, :at, :rc, :notes)"
            );
            $ins->execute([
                ':sid'   => $street_id,
                ':rid'   => $res_id,
                ':eid'   => $event_id,
                ':qty'   => $qty,
                ':cost'  => $unit_cost,
                ':by'    => $_SESSION['user_id'] ?? $_SESSION['id'] ?? null,
                ':at'    => $dist_at,
                ':rc'    => $recips,
                ':notes' => $notes,
            ]);

            // Deduct qty_available, add to qty_distributed
            $upd = $pdo->prepare(
                "UPDATE resources SET
                   qty_available   = qty_available   - :qty,
                   qty_distributed = qty_distributed + :qty2
                 WHERE resource_id = :id"
            );
            $upd->execute([':qty' => $qty, ':qty2' => $qty, ':id' => $res_id]);
            $pdo->commit();

            respond(['ok' => true, 'message' => 'Dispatch recorded successfully.']);

        /* ── Delete distribution ─────────────────────── */
        case 'delete_distribution':
            $dist_id = (int) bp($data, 'dist_id', 0);
            if ($dist_id <= 0) error_respond('Missing dist_id.');

            $pdo->beginTransaction();

            // Get distribution record
            $sel = $pdo->prepare(
                "SELECT resource_id, qty_distributed FROM resource_distributions WHERE dist_id = :id"
            );
            $sel->execute([':id' => $dist_id]);
            $dist_row = $sel->fetch(PDO::FETCH_ASSOC);

            if (!$dist_row) {
                $pdo->rollBack();
                error_respond('Distribution record not found.', 404);
            }

            // Restore qty_available
            $upd = $pdo->prepare(
                "UPDATE resources SET
                   qty_available   = qty_available   + :qty,
                   qty_distributed = GREATEST(0, qty_distributed - :qty2)
                 WHERE resource_id = :rid"
            );
            $upd->execute([
                ':qty'  => (int) $dist_row['qty_distributed'],
                ':qty2' => (int) $dist_row['qty_distributed'],
                ':rid'  => (int) $dist_row['resource_id'],
            ]);

            // Delete distribution record
            $del = $pdo->prepare("DELETE FROM resource_distributions WHERE dist_id = :id");
            $del->execute([':id' => $dist_id]);
            $pdo->commit();

            respond(['ok' => true, 'message' => 'Distribution record deleted and stock restored.']);

        default:
            error_respond('Unknown action.', 404);
    }
}

error_respond('Method not allowed.', 405);