<?php

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

// ── Only allow GET requests ───────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$action = trim($_GET['action'] ?? '');
if ($action === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required parameter: action']);
    exit;
}

// ── DB connection ─────────────────────────────────────────
require_once __DIR__ . '/db.php';   // provides $pdo

if (!$pdo) {
    http_response_code(503);
    echo json_encode(['error' => 'Database unavailable. Check db.php configuration.']);
    exit;
}

// ── Route to handler ─────────────────────────────────────
try {
    switch ($action) {

        // ──────────────────────────────────────────────────
        // KPI: hero strip + active typhoon alert banner
        // Tables: streets, typhoon_events
        // ──────────────────────────────────────────────────
        case 'kpi':
            $stats = $pdo->query("
                SELECT
                    COUNT(*)                                                AS total_streets,
                    COALESCE(SUM(current_risk_level IN ('RED','ORANGE')),0) AS affected_streets,
                    COALESCE(SUM(current_risk_level = 'RED'),   0)          AS critical_streets,
                    COALESCE(SUM(current_risk_level = 'ORANGE'),0)          AS high_streets,
                    COALESCE(SUM(current_risk_level = 'YELLOW'),0)          AS moderate_streets,
                    COALESCE(SUM(current_risk_level = 'GREEN'), 0)          AS safe_streets,
                    COALESCE(SUM(total_population), 0)                      AS total_population,
                    ROUND(
                        COALESCE(SUM(current_risk_level IN ('RED','ORANGE')),0)
                        / COUNT(*) * 100, 1
                    )                                                       AS pct_affected
                FROM streets
                WHERE is_active = 1
            ")->fetch();

            $typhoon = $pdo->query("
                SELECT event_name, status, date_started, category, wind_speed_kph
                FROM typhoon_events
                ORDER BY FIELD(status,'Active','Monitoring','Passed'), date_started DESC
                LIMIT 1
            ")->fetch();

            echo json_encode([
                'ok'      => true,
                'stats'   => $stats,
                'typhoon' => $typhoon,
            ]);
            break;

        // ──────────────────────────────────────────────────
        // RISK COUNTS: street count per level (map legend)
        // Tables: streets
        // ──────────────────────────────────────────────────
        case 'risk_counts':
            $counts = ['RED' => 0, 'ORANGE' => 0, 'YELLOW' => 0, 'GREEN' => 0];
            $rows = $pdo->query("
                SELECT current_risk_level AS level, COUNT(*) AS cnt
                FROM streets
                WHERE is_active = 1 AND current_risk_level IS NOT NULL
                GROUP BY current_risk_level
            ")->fetchAll();

            foreach ($rows as $row) {
                $counts[$row['level']] = (int) $row['cnt'];
            }

            echo json_encode(['ok' => true, 'counts' => $counts]);
            break;

        // ──────────────────────────────────────────────────
        // RISK EXAMPLES: highest-scoring street per tier
        // Tables: streets, zones
        // ──────────────────────────────────────────────────
        case 'risk_examples':
            $rows = $pdo->query("
                SELECT s.street_name,
                       s.current_risk_level   AS risk_level,
                       s.current_vuln_score   AS vuln_score,
                       s.needs_welfare,
                       z.zone_name
                FROM streets s
                JOIN zones z ON s.zone_id = z.zone_id
                WHERE s.is_active = 1
                  AND s.current_risk_level IS NOT NULL
                ORDER BY s.current_vuln_score DESC
            ")->fetchAll();

            // One per risk level
            $examples = [];
            foreach ($rows as $row) {
                $lvl = $row['risk_level'];
                if (!isset($examples[$lvl])) {
                    $examples[$lvl] = $row;
                }
            }

            echo json_encode(['ok' => true, 'examples' => $examples]);
            break;

        // ──────────────────────────────────────────────────
        // RESOURCES: live welfare inventory with % distributed
        // Tables: resources
        // ──────────────────────────────────────────────────
        case 'resources':
            $rows = $pdo->query("
                SELECT
                    resource_name,
                    category,
                    unit,
                    qty_available,
                    qty_distributed,
                    GREATEST(qty_available - qty_distributed, 0)    AS qty_remaining,
                    CASE
                        WHEN (qty_available + qty_distributed) = 0 THEN 0
                        ELSE ROUND(
                            qty_distributed / (qty_available + qty_distributed) * 100, 0
                        )
                    END AS pct_distributed
                FROM resources
                ORDER BY FIELD(category,'Food','Medical','Water','Shelter','Transport','Other')
                LIMIT 4
            ")->fetchAll();

            echo json_encode(['ok' => true, 'resources' => $rows]);
            break;

        // ──────────────────────────────────────────────────
        // ANNOUNCEMENTS: typhoon events + welfare plans merged
        // Tables: typhoon_events, welfare_action_plans, streets, zones
        // ──────────────────────────────────────────────────
        case 'announcements':
            $items = [];

            // Typhoon events
            $typhoons = $pdo->query("
                SELECT
                    te.event_name,
                    te.status,
                    te.category,
                    te.wind_speed_kph,
                    te.date_started,
                    (
                        SELECT COUNT(*)
                        FROM typhoon_street_impacts tsi
                        WHERE tsi.event_id = te.event_id
                    ) AS impacted_streets
                FROM typhoon_events te
                ORDER BY te.date_started DESC
                LIMIT 2
            ")->fetchAll();

            foreach ($typhoons as $t) {
                $items[] = [
                    'tag'      => 'Disaster Alert',
                    'tag_icon' => 'fa-solid fa-bell',
                    'title'    => $t['event_name'] . ': Monitoring Affected Streets',
                    'preview'  => 'Category ' . ($t['category'] ?? '—')
                                  . ' typhoon — ' . ($t['wind_speed_kph'] ?? '—') . ' kph winds. '
                                  . $t['impacted_streets'] . ' street'
                                  . ($t['impacted_streets'] != 1 ? 's' : '')
                                  . ' recorded as impacted.',
                    'date'     => $t['date_started'],
                    'day'      => date('d', strtotime($t['date_started'])),
                    'month'    => date('M', strtotime($t['date_started'])),
                ];
            }

            // Welfare action plans
            $plans = $pdo->query("
                SELECT
                    wap.assistance_type,
                    wap.description,
                    wap.status,
                    s.street_name,
                    z.zone_name,
                    COALESCE(wap.started_at, wap.planned_date, wap.created_at) AS plan_date
                FROM welfare_action_plans wap
                JOIN streets s ON wap.street_id = s.street_id
                JOIN zones   z ON s.zone_id = z.zone_id
                ORDER BY wap.created_at DESC
                LIMIT 2
            ")->fetchAll();

            foreach ($plans as $p) {
                $dateStr = $p['plan_date'] ?? date('Y-m-d');
                $items[] = [
                    'tag'      => 'Welfare Action',
                    'tag_icon' => 'fa-solid fa-hand-holding-heart',
                    'title'    => $p['assistance_type'] . ' — ' . $p['street_name'],
                    'preview'  => $p['description']
                                  ?? $p['assistance_type'] . ' provided to residents of '
                                     . $p['street_name'] . ' (' . $p['zone_name'] . ')'
                                     . '. Status: ' . $p['status'] . '.',
                    'date'     => $dateStr,
                    'day'      => date('d', strtotime($dateStr)),
                    'month'    => date('M', strtotime($dateStr)),
                ];
            }

            // Sort newest first, return top 3
            usort($items, fn($a, $b) =>
                strtotime($b['date']) <=> strtotime($a['date'])
            );

            echo json_encode([
                'ok'            => true,
                'announcements' => array_slice($items, 0, 3),
            ]);
            break;

        // ──────────────────────────────────────────────────
        // PENDING REPORTS: count for report section badge
        // Tables: resident_reports
        // ──────────────────────────────────────────────────
        case 'pending_reports':
            $count = (int) $pdo->query("
                SELECT COUNT(*)
                FROM resident_reports
                WHERE status = 'Pending'
            ")->fetchColumn();

            echo json_encode(['ok' => true, 'count' => $count]);
            break;

        // ──────────────────────────────────────────────────
        // MAP DATA: streets + evacuation centers for Leaflet
        // Tables: streets, zones, evacuation_centers,
        //         typhoon_street_impacts, typhoon_events
        // ──────────────────────────────────────────────────
        case 'map_data':
            // All active streets with risk level + zone
            $streets = $pdo->query("
                SELECT
                    s.street_id,
                    s.street_name,
                    s.latitude,
                    s.longitude,
                    s.current_risk_level    AS risk_level,
                    s.current_vuln_score    AS vuln_score,
                    s.needs_welfare,
                    s.total_population,
                    s.total_households,
                    z.zone_name
                FROM streets s
                JOIN zones z ON s.zone_id = z.zone_id
                WHERE s.is_active = 1
                  AND s.latitude  IS NOT NULL
                  AND s.longitude IS NOT NULL
                ORDER BY s.current_vuln_score DESC
            ")->fetchAll();

            // Active evacuation centers
            $evac = $pdo->query("
                SELECT
                    ec.center_id,
                    ec.center_name,
                    ec.address,
                    ec.latitude,
                    ec.longitude,
                    ec.capacity,
                    ec.current_occupancy,
                    COALESCE(z.zone_name, 'Unknown Zone') AS zone_name
                FROM evacuation_centers ec
                LEFT JOIN zones z ON ec.zone_id = z.zone_id
                WHERE ec.is_active = 1
                  AND ec.latitude  IS NOT NULL
                  AND ec.longitude IS NOT NULL
            ")->fetchAll();

            // Latest typhoon impacts (for popup detail)
            $impacts = $pdo->query("
                SELECT
                    tsi.street_id,
                    te.event_name           AS typhoon_name,
                    tsi.flood_status,
                    tsi.damage_status,
                    tsi.flood_height_m,
                    tsi.affected_households
                FROM typhoon_street_impacts tsi
                JOIN typhoon_events te ON tsi.event_id = te.event_id
                WHERE te.event_id = (
                    SELECT event_id FROM typhoon_events
                    ORDER BY date_started DESC LIMIT 1
                )
            ")->fetchAll();

            // Index impacts by street_id for easy lookup
            $impacts_by_street = [];
            foreach ($impacts as $imp) {
                $impacts_by_street[$imp['street_id']] = $imp;
            }

            // Merge impact data into street rows
            foreach ($streets as &$st) {
                $sid = $st['street_id'];
                $st['typhoon_impact'] = $impacts_by_street[$sid] ?? null;
            }
            unset($st);

            echo json_encode([
                'ok'      => true,
                'streets' => $streets,
                'evac'    => $evac,
            ]);
            break;

        // ──────────────────────────────────────────────────
        // Unknown action
        // ──────────────────────────────────────────────────
        default:
            http_response_code(404);
            echo json_encode([
                'error'            => 'Unknown action: ' . $action,
                'available_actions'=> [
                    'kpi', 'risk_counts', 'risk_examples',
                    'resources', 'announcements', 'pending_reports',
                    'map_data',
                ],
            ]);
    }

} catch (PDOException $e) {
    http_response_code(500);
    error_log('[EQUIAID API] ' . $e->getMessage());
    echo json_encode(['error' => 'Database query failed.']);
} catch (Throwable $e) {
    http_response_code(500);
    error_log('[EQUIAID API] ' . $e->getMessage());
    echo json_encode(['error' => 'Unexpected server error.']);
}