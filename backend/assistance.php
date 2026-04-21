<?php
/**
 * backend/assistance.php — Barangay EQUIAID Assistance API
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

// ── Auth guard ────────────────────────────────────────
if (empty($_SESSION['user_id'])) {
    $isXhr = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) ||
             str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');
    if ($isXhr) {
        http_response_code(401);
        echo json_encode(['ok' => false, 'error' => 'Not authenticated']);
    } else {
        header('Location: ../components/login.php?session_expired=1');
    }
    exit;
}

require_once __DIR__ . '/db.php';

if (!$pdo) {
    http_response_code(503);
    echo json_encode(['ok' => false, 'error' => 'Database unavailable']);
    exit;
}

$action = trim($_GET['action'] ?? '');

try {
    switch ($action) {

        // ── SUMMARY KPIs ──────────────────────────────────
        case 'summary':
            // Active welfare plans (Planned + Ongoing)
            $activePlans = (int) $pdo->query("
                SELECT COUNT(*) FROM welfare_action_plans
                WHERE status IN ('Planned','Ongoing')
            ")->fetchColumn();

            // Total items distributed (sum of all qty_distributed in resource_distributions)
            $totalDist = (int) $pdo->query("
                SELECT COALESCE(SUM(qty_distributed), 0)
                FROM resource_distributions
            ")->fetchColumn();

            // Families served (sum of recipient_count)
            $familiesServed = (int) $pdo->query("
                SELECT COALESCE(SUM(recipient_count), 0)
                FROM resource_distributions
            ")->fetchColumn();

            // Total cost disbursed
            $totalDisbursed = $pdo->query("
                SELECT COALESCE(SUM(total_cost), 0)
                FROM resource_distributions
            ")->fetchColumn();

            // Overall evac occupancy %
            $evacRow = $pdo->query("
                SELECT
                    COALESCE(SUM(capacity), 0)          AS total_cap,
                    COALESCE(SUM(current_occupancy), 0) AS total_occ
                FROM evacuation_centers
                WHERE is_active = 1
            ")->fetch(PDO::FETCH_ASSOC);

            $evacPct = $evacRow['total_cap'] > 0
                ? round($evacRow['total_occ'] / $evacRow['total_cap'] * 100)
                : 0;

            // Last updated from welfare_action_plans
            $lastUpdated = $pdo->query("
                SELECT MAX(updated_at) FROM welfare_action_plans
            ")->fetchColumn();

            echo json_encode([
                'ok'               => true,
                'active_plans'     => $activePlans,
                'total_distributed'=> $totalDist,
                'families_served'  => $familiesServed,
                'total_disbursed'  => (float) $totalDisbursed,
                'evac_occupancy'   => $evacPct,
                'last_updated'     => $lastUpdated,
            ]);
            break;

        // ── WELFARE ACTION PLANS ──────────────────────────
        case 'plans':
            $rows = $pdo->query("
                SELECT
                    wap.plan_id,
                    wap.street_id,
                    wap.event_id,
                    wap.assistance_type,
                    wap.description,
                    wap.status,
                    wap.vuln_score_before,
                    wap.vuln_score_after,
                    wap.risk_level_before,
                    wap.risk_level_after,
                    wap.planned_date,
                    wap.started_at,
                    wap.completed_at,
                    wap.updated_at,
                    s.street_name,
                    s.zone_id
                FROM welfare_action_plans wap
                LEFT JOIN streets s ON s.street_id = wap.street_id
                ORDER BY
                    FIELD(wap.status,'Ongoing','Planned','Completed','Cancelled') ASC,
                    wap.planned_date DESC
            ")->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode(['ok' => true, 'plans' => $rows]);
            break;

        // ── RESOURCE DISTRIBUTIONS ────────────────────────
        case 'distributions':
            $rows = $pdo->query("
                SELECT
                    rd.dist_id,
                    rd.street_id,
                    rd.resource_id,
                    rd.event_id,
                    rd.qty_distributed,
                    rd.unit_cost_at_time,
                    rd.total_cost,
                    rd.distributed_at,
                    rd.recipient_count,
                    rd.notes,
                    s.street_name,
                    r.resource_name,
                    r.category,
                    r.unit
                FROM resource_distributions rd
                LEFT JOIN streets s   ON s.street_id   = rd.street_id
                LEFT JOIN resources r ON r.resource_id = rd.resource_id
                ORDER BY rd.distributed_at DESC
            ")->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode(['ok' => true, 'distributions' => $rows]);
            break;

        // ── RESOURCE INVENTORY ────────────────────────────
        case 'resources':
            $rows = $pdo->query("
                SELECT
                    resource_id,
                    resource_name,
                    category,
                    unit,
                    unit_cost,
                    qty_available,
                    qty_reserved,
                    qty_distributed,
                    restock_threshold,
                    supplier,
                    updated_at
                FROM resources
                ORDER BY category ASC, resource_name ASC
            ")->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode(['ok' => true, 'resources' => $rows]);
            break;

        // ── EVACUATION CENTERS ────────────────────────────
        case 'evac':
            $rows = $pdo->query("
                SELECT
                    center_id,
                    zone_id,
                    center_name,
                    address,
                    capacity,
                    current_occupancy,
                    contact_person,
                    contact_number,
                    is_active
                FROM evacuation_centers
                WHERE is_active = 1
                ORDER BY center_name ASC
            ")->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode(['ok' => true, 'centers' => $rows]);
            break;

        default:
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Unknown action: ' . $action]);
    }

} catch (PDOException $e) {
    http_response_code(500);
    error_log('[EQUIAID ASSISTANCE] ' . $e->getMessage());
    echo json_encode(['ok' => false, 'error' => 'Database error']);
} catch (Throwable $e) {
    http_response_code(500);
    error_log('[EQUIAID ASSISTANCE] ' . $e->getMessage());
    echo json_encode(['ok' => false, 'error' => 'Server error']);
}