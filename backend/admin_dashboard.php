<?php


declare(strict_types=1);

/* ── CORS (allow same-origin admin pages) ──────────────── */
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

/* ── Load DB connection ────────────────────────────────── */
require_once __DIR__ . '/db.php';   // provides $pdo

/* ── Helper: send JSON and exit ───────────────────────── */
function respond(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function error_respond(string $message, int $status = 500): void
{
    respond(['ok' => false, 'error' => $message], $status);
}

/* ── Guard: DB must be available ───────────────────────── */
if (!$pdo) {
    error_respond('Database connection unavailable.', 503);
}

/* ── Route to action ────────────────────────────────────── */
$action = trim($_GET['action'] ?? '');

// check if session started, if not start it
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    error_respond('Unauthorized. Admin access required.', 401);
}


try {
    switch ($action) {

        case 'overview':
            // Total streets
            $total = (int) $pdo->query(
                "SELECT COUNT(*) FROM streets WHERE is_active = 1"
            )->fetchColumn();

            // Affected (RED + ORANGE)
            $affected = (int) $pdo->query(
                "SELECT COUNT(*) FROM streets
                 WHERE is_active = 1
                   AND current_risk_level IN ('RED','ORANGE')"
            )->fetchColumn();

            // Need welfare (Yes only)
            $need_welfare = (int) $pdo->query(
                "SELECT COUNT(*) FROM streets
                 WHERE is_active = 1
                   AND needs_welfare = 'Yes'"
            )->fetchColumn();

            // Available resource types (distinct categories with qty_available > 0)
            $resources = (int) $pdo->query(
                "SELECT COUNT(*) FROM resources
                 WHERE qty_available > 0"
            )->fetchColumn();

            // Active resident reports (Pending + Verified — not Resolved)
            $reports = (int) $pdo->query(
                "SELECT COUNT(*) FROM resident_reports
                 WHERE status IN ('Pending','Verified')"
            )->fetchColumn();

            // Active evacuation centres
            $evac_centers = (int) $pdo->query(
                "SELECT COUNT(*) FROM evacuation_centers
                 WHERE is_active = 1"
            )->fetchColumn();

            respond([
                'ok'   => true,
                'data' => [
                    'total_streets'    => $total,
                    'affected_streets' => $affected,
                    'need_welfare'     => $need_welfare,
                    'resources'        => $resources,
                    'active_reports'   => $reports,
                    'evac_centers'     => $evac_centers,
                ],
            ]);

        /* ════════════════════════════════════════════════
           risk_dist — count per risk level
        ════════════════════════════════════════════════ */
        case 'risk_dist':
            $rows = $pdo->query(
                "SELECT current_risk_level AS level, COUNT(*) AS cnt
                 FROM streets
                 WHERE is_active = 1
                   AND current_risk_level IS NOT NULL
                 GROUP BY current_risk_level"
            )->fetchAll();

            $dist = ['RED' => 0, 'ORANGE' => 0, 'YELLOW' => 0, 'GREEN' => 0];
            foreach ($rows as $r) {
                if (isset($dist[$r['level']])) {
                    $dist[$r['level']] = (int) $r['cnt'];
                }
            }

            respond(['ok' => true, 'data' => $dist]);

        /* ════════════════════════════════════════════════
           ai_summary — prediction results + model confidence
        ════════════════════════════════════════════════ */
        case 'ai_summary':
            // Streets analysed (images processed)
            $analyzed = (int) $pdo->query(
                "SELECT COUNT(DISTINCT street_id)
                 FROM uploaded_images
                 WHERE is_processed = 1 AND is_active = 1"
            )->fetchColumn();

            // Risk breakdown from latest ground-truth labels
            $risk_rows = $pdo->query(
                "SELECT risk_level, COUNT(*) AS cnt
                 FROM ground_truth_labels
                 GROUP BY risk_level"
            )->fetchAll();

            $high = $moderate = $safe = 0;
            foreach ($risk_rows as $r) {
                if ($r['risk_level'] === 'RED')    $high     += (int) $r['cnt'];
                if ($r['risk_level'] === 'ORANGE') $high     += (int) $r['cnt'];
                if ($r['risk_level'] === 'YELLOW') $moderate += (int) $r['cnt'];
                if ($r['risk_level'] === 'GREEN')  $safe     += (int) $r['cnt'];
            }

            // Avg model confidence from analytics_snapshots (latest snapshot)
            $confidence = $pdo->query(
                "SELECT avg_model_confidence
                 FROM analytics_snapshots
                 WHERE avg_model_confidence IS NOT NULL
                 ORDER BY snapshot_date DESC
                 LIMIT 1"
            )->fetchColumn();

            respond([
                'ok'   => true,
                'data' => [
                    'analyzed'       => $analyzed,
                    'high_risk'      => $high,
                    'moderate'       => $moderate,
                    'safe'           => $safe,
                    'avg_confidence' => $confidence
                        ? round((float) $confidence * 100, 2)
                        : null,
                ],
            ]);

        /* ════════════════════════════════════════════════
           map_data — all streets + evacuation centres
           (same shape as home.js loadMap)
        ════════════════════════════════════════════════ */
        case 'map_data':
            // Streets with latest typhoon impact joined
            $streets = $pdo->query(
                "SELECT
                     s.street_id,
                     s.street_name,
                     z.zone_name,
                     s.latitude,
                     s.longitude,
                     s.current_risk_level   AS risk_level,
                     s.current_vuln_score   AS vuln_score,
                     s.total_population,
                     s.total_households,
                     s.needs_welfare,
                     -- Most recent typhoon impact for this street
                     tsi.flood_status,
                     tsi.damage_status,
                     tsi.flood_height_m,
                     tsi.affected_households AS impact_households,
                     te.event_name           AS typhoon_name
                 FROM streets s
                 JOIN zones z ON z.zone_id = s.zone_id
                 LEFT JOIN typhoon_street_impacts tsi
                   ON tsi.impact_id = (
                       SELECT impact_id
                       FROM typhoon_street_impacts
                       WHERE street_id = s.street_id
                       ORDER BY date_recorded DESC
                       LIMIT 1
                   )
                 LEFT JOIN typhoon_events te ON te.event_id = tsi.event_id
                 WHERE s.is_active = 1
                 ORDER BY s.current_vuln_score DESC"
            )->fetchAll();

            // Shape data to match home.js popup builder
            $shaped_streets = array_map(function ($row) {
                $impact = null;
                if ($row['flood_status'] || $row['damage_status']) {
                    $impact = [
                        'typhoon_name'        => $row['typhoon_name'],
                        'flood_status'        => $row['flood_status'],
                        'damage_status'       => $row['damage_status'],
                        'flood_height_m'      => $row['flood_height_m'],
                        'affected_households' => $row['impact_households'],
                    ];
                }
                return [
                    'street_name'       => $row['street_name'],
                    'zone_name'         => $row['zone_name'],
                    'latitude'          => (float) $row['latitude'],
                    'longitude'         => (float) $row['longitude'],
                    'risk_level'        => $row['risk_level'] ?? 'GREEN',
                    'vuln_score'        => (float) ($row['vuln_score'] ?? 0),
                    'total_population'  => (int)   $row['total_population'],
                    'total_households'  => (int)   $row['total_households'],
                    'needs_welfare'     => $row['needs_welfare'] ?? 'No',
                    'typhoon_impact'    => $impact,
                ];
            }, $streets);

            // Evacuation centres
            $evacs = $pdo->query(
                "SELECT
                     ec.center_name,
                     z.zone_name,
                     ec.latitude,
                     ec.longitude,
                     ec.capacity,
                     ec.current_occupancy,
                     ec.address
                 FROM evacuation_centers ec
                 LEFT JOIN zones z ON z.zone_id = ec.zone_id
                 WHERE ec.is_active = 1"
            )->fetchAll();

            $shaped_evacs = array_map(function ($e) {
                return [
                    'center_name'       => $e['center_name'],
                    'zone_name'         => $e['zone_name'],
                    'latitude'          => (float) $e['latitude'],
                    'longitude'         => (float) $e['longitude'],
                    'capacity'          => (int)   $e['capacity'],
                    'current_occupancy' => (int)   $e['current_occupancy'],
                    'address'           => $e['address'],
                ];
            }, $evacs);

            respond([
                'ok'      => true,
                'streets' => $shaped_streets,
                'evacs'   => $shaped_evacs,
            ]);

        /* ════════════════════════════════════════════════
           top_streets — most vulnerable streets
        ════════════════════════════════════════════════ */
        case 'top_streets':
            $limit = max(1, min(20, (int) ($_GET['limit'] ?? 10)));

            $stmt = $pdo->prepare(
                "SELECT
                     s.street_name                       AS name,
                     z.zone_name                         AS zone,
                     s.current_vuln_score                AS score,
                     s.current_risk_level                AS level
                 FROM streets s
                 JOIN zones z ON z.zone_id = s.zone_id
                 WHERE s.is_active = 1
                   AND s.current_vuln_score IS NOT NULL
                 ORDER BY s.current_vuln_score DESC
                 LIMIT :lim"
            );
            $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
            $stmt->execute();
            $rows = $stmt->fetchAll();

            $ranked = array_map(function ($row, $i) {
                return [
                    'rank'  => $i + 1,
                    'name'  => $row['name'],
                    'zone'  => $row['zone'],
                    'score' => (float) $row['score'],
                    'level' => $row['level'] ?? 'GREEN',
                ];
            }, $rows, array_keys($rows));

            respond(['ok' => true, 'data' => $ranked]);

        /* ════════════════════════════════════════════════
           recent_reports — latest resident reports
        ════════════════════════════════════════════════ */
        case 'recent_reports':
            $limit = max(1, min(20, (int) ($_GET['limit'] ?? 5)));

            $stmt = $pdo->prepare(
                "SELECT
                     rr.report_id,
                     u.name                                    AS resident,
                     s.street_name                             AS street,
                     rr.report_type                            AS type,
                     DATE_FORMAT(rr.created_at, '%b %d, %Y')  AS date_submitted,
                     rr.status
                 FROM resident_reports rr
                 JOIN users   u ON u.id        = rr.user_id
                 JOIN streets s ON s.street_id = rr.street_id
                 ORDER BY rr.created_at DESC
                 LIMIT :lim"
            );
            $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
            $stmt->execute();

            respond(['ok' => true, 'data' => $stmt->fetchAll()]);

        /* ════════════════════════════════════════════════
           welfare_actions — recent welfare action plans
        ════════════════════════════════════════════════ */
        case 'welfare_actions':
            $limit = max(1, min(20, (int) ($_GET['limit'] ?? 6)));

            $stmt = $pdo->prepare(
                "SELECT
                     s.street_name                              AS street,
                     wap.assistance_type                        AS type,
                     wap.status,
                     DATE_FORMAT(
                         COALESCE(wap.completed_at, wap.started_at, wap.planned_date),
                         '%b %d, %Y'
                     )                                          AS date_provided,
                     -- Resource qty: sum distributions for this street/plan
                     CONCAT(
                         COALESCE(
                             (SELECT SUM(rd.qty_distributed)
                              FROM resource_distributions rd
                              WHERE rd.street_id = wap.street_id
                                AND rd.event_id  = wap.event_id),
                             0
                         ),
                         ' units'
                     )                                          AS qty
                 FROM welfare_action_plans wap
                 JOIN streets s ON s.street_id = wap.street_id
                 ORDER BY wap.created_at DESC
                 LIMIT :lim"
            );
            $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
            $stmt->execute();

            respond(['ok' => true, 'data' => $stmt->fetchAll()]);

        /* ════════════════════════════════════════════════
           disasters — typhoon street impacts
        ════════════════════════════════════════════════ */
        case 'disasters':
            $limit = max(1, min(20, (int) ($_GET['limit'] ?? 6)));

            $stmt = $pdo->prepare(
                "SELECT
                     s.street_name                             AS street,
                     CASE
                         WHEN tsi.flood_status   IN ('Flooded','Severely Flooded') THEN 'Flood'
                         WHEN tsi.damage_status  IN ('Moderate Damage','Severely Damaged') THEN 'Infrastructure Damage'
                         ELSE 'Road Blockage'
                     END                                       AS type,
                     CASE
                         WHEN tsi.flood_status = 'Severely Flooded'
                              OR tsi.damage_status = 'Severely Damaged' THEN 'Severe'
                         WHEN tsi.flood_status = 'Flooded'
                              OR tsi.damage_status = 'Moderate Damage'  THEN 'Moderate'
                         ELSE 'Minor'
                     END                                       AS severity,
                     DATE_FORMAT(tsi.date_recorded, '%b %d, %Y') AS date_recorded
                 FROM typhoon_street_impacts tsi
                 JOIN streets s ON s.street_id = tsi.street_id
                 ORDER BY tsi.date_recorded DESC
                 LIMIT :lim"
            );
            $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
            $stmt->execute();

            respond(['ok' => true, 'data' => $stmt->fetchAll()]);

        /* ════════════════════════════════════════════════
           impact_summary — before/after welfare impact
           from analytics_snapshots
        ════════════════════════════════════════════════ */
        case 'impact_summary':
            // Latest two snapshots to compare before/after
            $rows = $pdo->query(
                "SELECT
                     snap_id,
                     snapshot_date,
                     pct_affected,
                     pct_needs_welfare,
                     pct_improved,
                     total_streets,
                     streets_red,
                     streets_orange,
                     streets_yellow,
                     streets_green
                 FROM analytics_snapshots
                 ORDER BY snapshot_date DESC
                 LIMIT 2"
            )->fetchAll();

            if (empty($rows)) {
                respond(['ok' => true, 'data' => ['before' => null, 'after' => null]]);
            }

            // First row = most recent ("after"), second = prior ("before")
            // If only one snapshot exists, use pct_improved field as delta proxy
            $latest = $rows[0];
            $prior  = $rows[1] ?? null;

            respond([
                'ok'   => true,
                'data' => [
                    'after'   => (float) $latest['pct_affected'],
                    'before'  => $prior
                        ? (float) $prior['pct_affected']
                        : (float) ($latest['pct_affected'] + ($latest['pct_improved'] ?? 0)),
                    'improved' => $latest['pct_improved']
                        ? (float) $latest['pct_improved']
                        : null,
                ],
            ]);

        /* ════════════════════════════════════════════════
           Unknown action
        ════════════════════════════════════════════════ */
        default:
            error_respond(
                $action
                    ? "Unknown action: \"$action\". Valid actions: overview, risk_dist, ai_summary, map_data, top_streets, recent_reports, welfare_actions, disasters, impact_summary."
                    : 'Missing required parameter: action.',
                400
            );
    }

} catch (PDOException $e) {
    error_log('[admin_dashboard.php] PDOException: ' . $e->getMessage());
    error_respond('A database error occurred. Please try again.', 500);
} catch (Throwable $e) {
    error_log('[admin_dashboard.php] Error: ' . $e->getMessage());
    error_respond('An unexpected error occurred.', 500);
}