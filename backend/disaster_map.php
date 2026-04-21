<?php
/**
 * backend/disaster_map.php — Barangay EQUIAID Disaster Map API
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

// ── Auth guard ────────────────────────────────────────────
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

        // ── SUMMARY KPIs ─────────────────────────────────────
        case 'summary':
            // Street risk counts
            $riskRow = $pdo->query("
                SELECT
                    COUNT(*)                                          AS total_streets,
                    SUM(current_risk_level = 'RED')                  AS streets_red,
                    SUM(current_risk_level = 'ORANGE')               AS streets_orange,
                    SUM(current_risk_level = 'YELLOW')               AS streets_yellow,
                    SUM(current_risk_level = 'GREEN')                AS streets_green,
                    MAX(last_predicted_at)                           AS last_updated
                FROM streets
                WHERE is_active = 1
            ")->fetch(PDO::FETCH_ASSOC);

            // Open resident reports (Pending + In Progress)
            $openReports = (int) $pdo->query("
                SELECT COUNT(*) FROM resident_reports
                WHERE status IN ('Pending','In Progress')
            ")->fetchColumn();

            // Active evac centers
            $evacCount = (int) $pdo->query("
                SELECT COUNT(*) FROM evacuation_centers WHERE is_active = 1
            ")->fetchColumn();

            // Affected persons & households from latest typhoon impacts
            $impactRow = $pdo->query("
                SELECT
                    COALESCE(SUM(tsi.affected_persons), 0)    AS affected_persons,
                    COALESCE(SUM(tsi.affected_households), 0) AS affected_households
                FROM typhoon_street_impacts tsi
                INNER JOIN typhoon_events te ON te.event_id = tsi.event_id
                WHERE te.status = 'Active'
            ")->fetch(PDO::FETCH_ASSOC);

            // Active welfare plans
            $activeWelfare = (int) $pdo->query("
                SELECT COUNT(*) FROM welfare_action_plans
                WHERE status IN ('Planned','Ongoing')
            ")->fetchColumn();

            // Active typhoon event
            $event = $pdo->query("
                SELECT event_id, event_name, local_name, category, wind_speed_kph
                FROM typhoon_events
                WHERE status = 'Active'
                ORDER BY created_at DESC
                LIMIT 1
            ")->fetch(PDO::FETCH_ASSOC);

            echo json_encode([
                'ok'                 => true,
                'total_streets'      => (int) ($riskRow['total_streets']   ?? 0),
                'streets_red'        => (int) ($riskRow['streets_red']     ?? 0),
                'streets_orange'     => (int) ($riskRow['streets_orange']  ?? 0),
                'streets_yellow'     => (int) ($riskRow['streets_yellow']  ?? 0),
                'streets_green'      => (int) ($riskRow['streets_green']   ?? 0),
                'last_updated'       => $riskRow['last_updated'] ?? null,
                'open_reports'       => $openReports,
                'evac_centers'       => $evacCount,
                'affected_persons'   => (int) ($impactRow['affected_persons']    ?? 0),
                'affected_households'=> (int) ($impactRow['affected_households'] ?? 0),
                'active_welfare'     => $activeWelfare,
                'active_event'       => $event ?: null,
            ]);
            break;

        // ── STREETS ──────────────────────────────────────────
        case 'streets':
            $rows = $pdo->query("
                SELECT
                    s.street_id,
                    s.zone_id,
                    s.street_name,
                    s.barangay,
                    s.city,
                    s.latitude,
                    s.longitude,
                    s.current_risk_level,
                    s.current_vuln_score,
                    s.needs_welfare,
                    s.last_predicted_at,
                    s.total_population,
                    s.total_households
                FROM streets s
                WHERE s.is_active = 1
                ORDER BY
                    FIELD(s.current_risk_level,'RED','ORANGE','YELLOW','GREEN') ASC,
                    s.street_name ASC
            ")->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode(['ok' => true, 'streets' => $rows]);
            break;

        // ── EVACUATION CENTERS ───────────────────────────────
        case 'evac_centers':
            $rows = $pdo->query("
                SELECT
                    ec.center_id,
                    ec.zone_id,
                    ec.center_name,
                    ec.address,
                    ec.latitude,
                    ec.longitude,
                    ec.capacity,
                    ec.current_occupancy,
                    ec.contact_person,
                    ec.contact_number,
                    ec.is_active
                FROM evacuation_centers ec
                WHERE ec.is_active = 1
                ORDER BY ec.center_name ASC
            ")->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode(['ok' => true, 'centers' => $rows]);
            break;

        // ── RESIDENT REPORTS ─────────────────────────────────
        case 'reports':
            $rows = $pdo->query("
                SELECT
                    rr.report_id,
                    rr.user_id,
                    rr.street_id,
                    rr.event_id,
                    rr.report_type,
                    rr.severity,
                    rr.description,
                    rr.image_path,
                    rr.latitude,
                    rr.longitude,
                    rr.status,
                    rr.created_at,
                    s.street_name,
                    s.zone_id
                FROM resident_reports rr
                LEFT JOIN streets s ON s.street_id = rr.street_id
                ORDER BY rr.created_at DESC
                LIMIT 50
            ")->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode(['ok' => true, 'reports' => $rows]);
            break;

        // ── WELFARE PLANS ────────────────────────────────────
        case 'welfare':
            $rows = $pdo->query("
                SELECT
                    wap.plan_id,
                    wap.street_id,
                    wap.event_id,
                    wap.assistance_type,
                    wap.description,
                    wap.status,
                    wap.planned_date,
                    wap.started_at,
                    wap.completed_at,
                    s.street_name,
                    s.latitude,
                    s.longitude
                FROM welfare_action_plans wap
                LEFT JOIN streets s ON s.street_id = wap.street_id
                WHERE wap.status IN ('Planned', 'Ongoing')
                ORDER BY
                    FIELD(wap.status, 'Ongoing', 'Planned') ASC,
                    wap.planned_date ASC
            ")->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode(['ok' => true, 'welfare' => $rows]);
            break;

        default:
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Unknown action: ' . $action]);
    }

} catch (PDOException $e) {
    http_response_code(500);
    error_log('[EQUIAID DISASTER MAP] ' . $e->getMessage());
    echo json_encode(['ok' => false, 'error' => 'Database error']);
} catch (Throwable $e) {
    http_response_code(500);
    error_log('[EQUIAID DISASTER MAP] ' . $e->getMessage());
    echo json_encode(['ok' => false, 'error' => 'Server error']);
}