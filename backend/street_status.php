<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/db.php';   // provides $pdo

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

if (!$pdo) {
    error_respond('Database connection unavailable.', 503);
}

$action = $_GET['action'] ?? '';

switch ($action) {

    // ════════════════════════════════════════════════════════
    // summary — KPI strip counts + last-updated + typhoon banner
    // ════════════════════════════════════════════════════════
    case 'summary':
        $counts = $pdo->query("
            SELECT
                COUNT(*)                                       AS total_streets,
                SUM(current_risk_level = 'RED')                AS red,
                SUM(current_risk_level = 'ORANGE')             AS orange,
                SUM(current_risk_level = 'YELLOW')             AS yellow,
                SUM(current_risk_level = 'GREEN')              AS green,
                SUM(current_risk_level IN ('RED','ORANGE'))    AS affected,
                SUM(total_population)                          AS total_population,
                MAX(last_predicted_at)                         AS last_updated
            FROM streets
            WHERE is_active = 1
        ")->fetch(PDO::FETCH_ASSOC);

        $typhoon = $pdo->query("
            SELECT event_name, local_name, category, wind_speed_kph, status
            FROM   typhoon_events
            WHERE  status = 'Active'
            LIMIT  1
        ")->fetch(PDO::FETCH_ASSOC);

        respond([
            'ok'      => true,
            'summary' => [
                'total'        => (int) $counts['total_streets'],
                'red'          => (int) $counts['red'],
                'orange'       => (int) $counts['orange'],
                'yellow'       => (int) $counts['yellow'],
                'green'        => (int) $counts['green'],
                'affected'     => (int) $counts['affected'],
                'population'   => (int) $counts['total_population'],
                'last_updated' => $counts['last_updated'],
            ],
            'typhoon' => $typhoon ?: null,
        ]);

    // ════════════════════════════════════════════════════════
    // streets — card list
    // ════════════════════════════════════════════════════════
    case 'streets':
        $rows = $pdo->query("
            SELECT
                s.street_id,
                s.street_name,
                z.zone_name,
                s.current_risk_level   AS risk_level,
                s.current_vuln_score   AS vuln_score,
                s.needs_welfare,
                s.total_population,
                s.total_households,
                s.last_predicted_at,
                tsi.flood_status,
                tsi.damage_status,
                tsi.flood_height_m,
                tsi.road_accessible,
                tsi.affected_households AS impact_households,
                tsi.affected_persons    AS impact_persons,
                te.event_name           AS typhoon_name,
                te.status               AS typhoon_status,
                di.poverty_rate_pct,
                di.flood_frequency,
                di.drainage_type,
                di.road_surface,
                di.elevation_m,
                di.dist_to_waterway_m
            FROM streets s
            JOIN zones z ON z.zone_id = s.zone_id
            LEFT JOIN typhoon_street_impacts tsi
              ON tsi.impact_id = (
                  SELECT impact_id FROM typhoon_street_impacts
                  WHERE  street_id = s.street_id
                  ORDER  BY date_recorded DESC LIMIT 1
              )
            LEFT JOIN typhoon_events te ON te.event_id = tsi.event_id
            LEFT JOIN demographic_indicators di
              ON di.demo_id = (
                  SELECT demo_id FROM demographic_indicators
                  WHERE  street_id = s.street_id
                  ORDER  BY survey_date DESC LIMIT 1
              )
            WHERE s.is_active = 1
            ORDER BY
                FIELD(s.current_risk_level,'RED','ORANGE','YELLOW','GREEN'),
                s.current_vuln_score DESC
        ")->fetchAll(PDO::FETCH_ASSOC);

        $streets = array_map(function (array $r): array {
            $hasImpact = !empty($r['flood_status']) && $r['flood_status'] !== 'None';
            return [
                'street_id'        => (int)   $r['street_id'],
                'street_name'      => $r['street_name'],
                'zone_name'        => $r['zone_name'],
                'risk_level'       => $r['risk_level']  ?? 'GREEN',
                'vuln_score'       => (float) ($r['vuln_score'] ?? 0),
                'needs_welfare'    => $r['needs_welfare'] ?? 'No',
                'total_population' => (int)   $r['total_population'],
                'total_households' => (int)   $r['total_households'],
                'last_predicted_at'=> $r['last_predicted_at'],
                'road_accessible'  => isset($r['road_accessible']) ? (bool)$r['road_accessible'] : true,
                'typhoon_impact'   => $hasImpact ? [
                    'typhoon_name'        => $r['typhoon_name'],
                    'typhoon_status'      => $r['typhoon_status'],
                    'flood_status'        => $r['flood_status'],
                    'damage_status'       => $r['damage_status'],
                    'flood_height_m'      => $r['flood_height_m'] ? (float)$r['flood_height_m'] : null,
                    'road_accessible'     => isset($r['road_accessible']) ? (bool)$r['road_accessible'] : true,
                    'affected_households' => (int)$r['impact_households'],
                    'affected_persons'    => (int)$r['impact_persons'],
                ] : null,
                'env' => [
                    'poverty_rate_pct'   => $r['poverty_rate_pct']   ? (float)$r['poverty_rate_pct']   : null,
                    'flood_frequency'    => (int)($r['flood_frequency'] ?? 0),
                    'drainage_type'      => $r['drainage_type']   ?? null,
                    'road_surface'       => $r['road_surface']    ?? null,
                    'elevation_m'        => $r['elevation_m']     ? (float)$r['elevation_m']     : null,
                    'dist_to_waterway_m' => $r['dist_to_waterway_m'] ? (float)$r['dist_to_waterway_m'] : null,
                ],
            ];
        }, $rows);

        respond(['ok' => true, 'streets' => $streets]);

    // ════════════════════════════════════════════════════════
    // zones — dropdown options
    // ════════════════════════════════════════════════════════
    case 'zones':
        $zones = $pdo->query("
            SELECT DISTINCT z.zone_name
            FROM   zones z
            JOIN   streets s ON s.zone_id = z.zone_id
            WHERE  s.is_active = 1
            ORDER  BY z.zone_name
        ")->fetchAll(PDO::FETCH_COLUMN);

        respond(['ok' => true, 'zones' => $zones]);

    // ════════════════════════════════════════════════════════
    // street_detail — full data for a single street modal
    // ════════════════════════════════════════════════════════
    case 'street_detail':
        $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
        if (!$id || $id <= 0) {
            error_respond('Invalid street ID.', 400);
        }

        // ── Core street + zone ────────────────────────────
        $stmt = $pdo->prepare("
            SELECT
                s.street_id, s.street_name, s.barangay, s.city,
                s.latitude, s.longitude,
                s.current_risk_level   AS risk_level,
                s.current_vuln_score   AS vuln_score,
                s.needs_welfare,
                s.total_population,
                s.total_households,
                s.last_predicted_at,
                z.zone_name,
                z.description          AS zone_desc
            FROM streets s
            JOIN zones z ON z.zone_id = s.zone_id
            WHERE s.street_id = :id AND s.is_active = 1
            LIMIT 1
        ");
        $stmt->execute([':id' => $id]);
        $street = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$street) {
            error_respond('Street not found.', 404);
        }

        // ── Latest demographic indicators ─────────────────
        $stmt = $pdo->prepare("
            SELECT
                fourps_households,
                poverty_rate_pct,
                avg_monthly_income,
                pwd_count,
                senior_count,
                pregnant_count,
                child_count,
                informal_settlers_pct,
                drainage_type,
                road_surface,
                street_width_m,
                elevation_m,
                dist_to_waterway_m,
                flood_frequency,
                avg_flood_height_m,
                survey_date
            FROM demographic_indicators
            WHERE street_id = :id
            ORDER BY survey_date DESC
            LIMIT 1
        ");
        $stmt->execute([':id' => $id]);
        $demo = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        // ── All typhoon impacts (most recent first) ───────
        $stmt = $pdo->prepare("
            SELECT
                tsi.impact_id,
                tsi.flood_status,
                tsi.damage_status,
                tsi.flood_height_m,
                tsi.road_accessible,
                tsi.affected_households,
                tsi.affected_persons,
                tsi.report_source,
                tsi.date_recorded,
                te.event_name,
                te.local_name,
                te.category,
                te.status          AS typhoon_status,
                te.wind_speed_kph
            FROM typhoon_street_impacts tsi
            JOIN typhoon_events te ON te.event_id = tsi.event_id
            WHERE tsi.street_id = :id
            ORDER BY tsi.date_recorded DESC
            LIMIT 5
        ");
        $stmt->execute([':id' => $id]);
        $impacts = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // ── Welfare action plans ───────────────────────────
        $stmt = $pdo->prepare("
            SELECT
                wap.assistance_type,
                wap.description,
                wap.status,
                wap.planned_date,
                wap.started_at,
                wap.completed_at,
                wap.vuln_score_before,
                wap.vuln_score_after,
                wap.risk_level_before,
                wap.risk_level_after,
                te.event_name
            FROM welfare_action_plans wap
            LEFT JOIN typhoon_events te ON te.event_id = wap.event_id
            WHERE wap.street_id = :id
            ORDER BY wap.created_at DESC
            LIMIT 5
        ");
        $stmt->execute([':id' => $id]);
        $plans = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // ── Resource distributions ─────────────────────────
        $stmt = $pdo->prepare("
            SELECT
                rd.qty_distributed,
                rd.recipient_count,
                rd.distributed_at,
                r.resource_name,
                r.category,
                r.unit,
                rd.total_cost
            FROM resource_distributions rd
            JOIN resources r ON r.resource_id = rd.resource_id
            WHERE rd.street_id = :id
            ORDER BY rd.distributed_at DESC
            LIMIT 5
        ");
        $stmt->execute([':id' => $id]);
        $resources = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // ── Budget allocations ─────────────────────────────
        $stmt = $pdo->prepare("
            SELECT
                ba.risk_level,
                ba.vuln_score,
                ba.recommended_budget,
                ba.approved_budget,
                ba.actual_spent,
                ba.affected_households,
                ba.priority_score,
                ba.fiscal_period,
                te.event_name
            FROM budget_allocations ba
            LEFT JOIN typhoon_events te ON te.event_id = ba.event_id
            WHERE ba.street_id = :id
            ORDER BY ba.created_at DESC
            LIMIT 3
        ");
        $stmt->execute([':id' => $id]);
        $budgets = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // ── Resident reports ───────────────────────────────
        $stmt = $pdo->prepare("
            SELECT
                rr.report_type,
                rr.severity,
                rr.description,
                rr.status,
                rr.created_at
            FROM resident_reports rr
            WHERE rr.street_id = :id
            ORDER BY rr.created_at DESC
            LIMIT 5
        ");
        $stmt->execute([':id' => $id]);
        $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // ── ML street features (latest) ────────────────────
        $stmt = $pdo->prepare("
            SELECT
                image_flood_score,
                image_damage_score,
                image_safe_score,
                composite_risk_score,
                snapshot_date
            FROM street_features
            WHERE street_id = :id
            ORDER BY snapshot_date DESC
            LIMIT 1
        ");
        $stmt->execute([':id' => $id]);
        $features = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        // ── Cast types ─────────────────────────────────────
        $castImpacts = array_map(fn($i) => array_merge($i, [
            'flood_height_m'      => $i['flood_height_m']  ? (float)$i['flood_height_m']  : null,
            'road_accessible'     => (bool)$i['road_accessible'],
            'affected_households' => (int)$i['affected_households'],
            'affected_persons'    => (int)$i['affected_persons'],
            'wind_speed_kph'      => $i['wind_speed_kph'] ? (float)$i['wind_speed_kph'] : null,
        ]), $impacts);

        $castBudgets = array_map(fn($b) => array_merge($b, [
            'recommended_budget'  => (float)$b['recommended_budget'],
            'approved_budget'     => $b['approved_budget']  ? (float)$b['approved_budget']  : null,
            'actual_spent'        => (float)$b['actual_spent'],
            'affected_households' => (int)$b['affected_households'],
            'priority_score'      => $b['priority_score'] ? (float)$b['priority_score'] : null,
            'vuln_score'          => $b['vuln_score']      ? (float)$b['vuln_score']      : null,
        ]), $budgets);

        $castResources = array_map(fn($r) => array_merge($r, [
            'qty_distributed' => (int)$r['qty_distributed'],
            'recipient_count' => (int)$r['recipient_count'],
            'total_cost'      => $r['total_cost'] ? (float)$r['total_cost'] : null,
        ]), $resources);

        respond([
            'ok'      => true,
            'street'  => [
                'street_id'        => (int)   $street['street_id'],
                'street_name'      => $street['street_name'],
                'barangay'         => $street['barangay'],
                'city'             => $street['city'],
                'latitude'         => (float) $street['latitude'],
                'longitude'        => (float) $street['longitude'],
                'zone_name'        => $street['zone_name'],
                'zone_desc'        => $street['zone_desc'],
                'risk_level'       => $street['risk_level']   ?? 'GREEN',
                'vuln_score'       => (float) ($street['vuln_score'] ?? 0),
                'needs_welfare'    => $street['needs_welfare'] ?? 'No',
                'total_population' => (int)   $street['total_population'],
                'total_households' => (int)   $street['total_households'],
                'last_predicted_at'=> $street['last_predicted_at'],
            ],
            'demo'      => $demo,
            'impacts'   => $castImpacts,
            'plans'     => $plans,
            'resources' => $castResources,
            'budgets'   => $castBudgets,
            'reports'   => $reports,
            'features'  => $features ? [
                'image_flood_score'   => $features['image_flood_score']   ? (float)$features['image_flood_score']   : null,
                'image_damage_score'  => $features['image_damage_score']  ? (float)$features['image_damage_score']  : null,
                'image_safe_score'    => $features['image_safe_score']    ? (float)$features['image_safe_score']    : null,
                'composite_risk_score'=> $features['composite_risk_score']? (float)$features['composite_risk_score']: null,
                'snapshot_date'       => $features['snapshot_date'],
            ] : null,
        ]);

    // ════════════════════════════════════════════════════════
    // map_data — streets + evac centers for the map
    // ════════════════════════════════════════════════════════
    case 'map_data':
        $streets = $pdo->query("
            SELECT
                s.street_id,
                s.street_name,
                s.latitude,
                s.longitude,
                s.current_risk_level   AS risk_level,
                s.current_vuln_score   AS vuln_score,
                s.needs_welfare,
                s.total_population,
                s.total_households,
                z.zone_name,
                tsi.flood_status,
                tsi.damage_status,
                tsi.flood_height_m,
                tsi.affected_households AS impact_households,
                tsi.affected_persons    AS impact_persons,
                te.event_name           AS typhoon_name,
                te.status               AS typhoon_status
            FROM streets s
            JOIN zones z ON z.zone_id = s.zone_id
            LEFT JOIN typhoon_street_impacts tsi
              ON tsi.impact_id = (
                  SELECT impact_id FROM typhoon_street_impacts
                  WHERE  street_id = s.street_id
                  ORDER  BY date_recorded DESC LIMIT 1
              )
            LEFT JOIN typhoon_events te ON te.event_id = tsi.event_id
            WHERE s.is_active = 1
        ")->fetchAll(PDO::FETCH_ASSOC);

        $evacs = $pdo->query("
            SELECT
                ec.center_id,
                ec.center_name,
                ec.address,
                ec.latitude,
                ec.longitude,
                ec.capacity,
                ec.current_occupancy,
                ec.contact_person,
                ec.contact_number,
                z.zone_name
            FROM evacuation_centers ec
            LEFT JOIN zones z ON z.zone_id = ec.zone_id
            WHERE ec.is_active = 1
        ")->fetchAll(PDO::FETCH_ASSOC);

        $mapStreets = array_map(function($r) {
            $hasImpact = !empty($r['flood_status']) && $r['flood_status'] !== 'None';
            return [
                'street_id'        => (int)   $r['street_id'],
                'street_name'      => $r['street_name'],
                'zone_name'        => $r['zone_name'],
                'latitude'         => (float) $r['latitude'],
                'longitude'        => (float) $r['longitude'],
                'risk_level'       => $r['risk_level']  ?? 'GREEN',
                'vuln_score'       => (float) ($r['vuln_score'] ?? 0),
                'needs_welfare'    => $r['needs_welfare'] ?? 'No',
                'total_population' => (int)   $r['total_population'],
                'total_households' => (int)   $r['total_households'],
                'typhoon_impact'   => $hasImpact ? [
                    'typhoon_name'        => $r['typhoon_name'],
                    'typhoon_status'      => $r['typhoon_status'],
                    'flood_status'        => $r['flood_status'],
                    'damage_status'       => $r['damage_status'],
                    'flood_height_m'      => $r['flood_height_m'] ? (float)$r['flood_height_m'] : null,
                    'affected_households' => (int)$r['impact_households'],
                    'affected_persons'    => (int)$r['impact_persons'],
                ] : null,
            ];
        }, $streets);

        $mapEvacs = array_map(function($e) {
            return [
                'center_id'        => (int)   $e['center_id'],
                'center_name'      => $e['center_name'],
                'address'          => $e['address'],
                'zone_name'        => $e['zone_name'],
                'latitude'         => (float) $e['latitude'],
                'longitude'        => (float) $e['longitude'],
                'capacity'         => (int)   $e['capacity'],
                'current_occupancy'=> (int)   $e['current_occupancy'],
                'contact_person'   => $e['contact_person'],
                'contact_number'   => $e['contact_number'],
            ];
        }, $evacs);

        respond(['ok' => true, 'streets' => $mapStreets, 'evac' => $mapEvacs]);

    // ════════════════════════════════════════════════════════
    default:
        error_respond('Unknown action: ' . htmlspecialchars($action), 400);
}