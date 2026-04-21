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

// ════════════════════════════════════════════════════════
// GLOBAL HELPER — derive impact level from flood + damage
// ════════════════════════════════════════════════════════
function deriveImpactLevel(?string $flood, ?string $damage): string
{
    if ($flood === 'Severely Flooded' || $damage === 'Severely Damaged') return 'Severe';
    if ($flood === 'Flooded'          || $damage === 'Moderate Damage')  return 'High';
    if ($damage === 'Minor Damage')                                       return 'Moderate';
    if ($flood !== null && $flood !== 'None')                            return 'Moderate';
    return 'None';
}

switch ($action) {

    // ════════════════════════════════════════════════════════
    // events — list all typhoon events (for dropdown selector)
    // ════════════════════════════════════════════════════════
    case 'events':
        $rows = $pdo->query("
            SELECT
                event_id,
                event_name,
                local_name,
                category,
                landfall_date,
                date_started,
                date_ended,
                wind_speed_kph,
                status,
                notes
            FROM typhoon_events
            ORDER BY date_started DESC
        ")->fetchAll(PDO::FETCH_ASSOC);

        $events = array_map(function (array $r): array {
            return [
                'event_id'       => (int)   $r['event_id'],
                'event_name'     => $r['event_name'],
                'local_name'     => $r['local_name'],
                'category'       => $r['category'] ? (int)$r['category'] : null,
                'landfall_date'  => $r['landfall_date'],
                'date_started'   => $r['date_started'],
                'date_ended'     => $r['date_ended'],
                'wind_speed_kph' => $r['wind_speed_kph'] ? (float)$r['wind_speed_kph'] : null,
                'status'         => $r['status'],
                'notes'          => $r['notes'],
            ];
        }, $rows);

        respond(['ok' => true, 'events' => $events]);

    // ════════════════════════════════════════════════════════
    // summary — KPI strip for a given event
    // ════════════════════════════════════════════════════════
    case 'summary':
        $eventId = filter_input(INPUT_GET, 'event_id', FILTER_VALIDATE_INT);
        if (!$eventId || $eventId <= 0) {
            error_respond('Invalid event ID.', 400);
        }

        // Event info
        $stmt = $pdo->prepare("
            SELECT event_id, event_name, local_name, category,
                   landfall_date, date_started, date_ended,
                   wind_speed_kph, status, notes
            FROM typhoon_events WHERE event_id = :id LIMIT 1
        ");
        $stmt->execute([':id' => $eventId]);
        $event = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$event) { error_respond('Event not found.', 404); }

        // Impact aggregates
        $stmt = $pdo->prepare("
            SELECT
                COUNT(*)                                                    AS impacted_streets,
                SUM(tsi.affected_households)                                AS total_households,
                SUM(tsi.affected_persons)                                   AS total_persons,
                SUM(tsi.flood_status IN ('Flooded','Severely Flooded'))     AS flooded_streets,
                SUM(tsi.flood_status = 'Severely Flooded')                  AS severe_flood_streets,
                SUM(tsi.damage_status IN ('Moderate Damage','Severely Damaged')) AS damaged_streets,
                SUM(tsi.road_accessible = 0)                                AS inaccessible_streets
            FROM typhoon_street_impacts tsi
            WHERE tsi.event_id = :id
        ");
        $stmt->execute([':id' => $eventId]);
        $agg = $stmt->fetch(PDO::FETCH_ASSOC);

        // Risk-level breakdown for impacted streets
        $stmt = $pdo->prepare("
            SELECT
                s.current_risk_level AS risk_level,
                COUNT(*) AS cnt
            FROM typhoon_street_impacts tsi
            JOIN streets s ON s.street_id = tsi.street_id
            WHERE tsi.event_id = :id
            GROUP BY s.current_risk_level
        ");
        $stmt->execute([':id' => $eventId]);
        $riskRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $riskBreakdown = ['RED' => 0, 'ORANGE' => 0, 'YELLOW' => 0, 'GREEN' => 0];
        foreach ($riskRows as $r) {
            if (isset($riskBreakdown[$r['risk_level']])) {
                $riskBreakdown[$r['risk_level']] = (int)$r['cnt'];
            }
        }

        // Budget
        $stmt = $pdo->prepare("
            SELECT
                SUM(recommended_budget) AS total_recommended,
                SUM(approved_budget)    AS total_approved,
                SUM(actual_spent)       AS total_spent
            FROM budget_allocations
            WHERE event_id = :id
        ");
        $stmt->execute([':id' => $eventId]);
        $budget = $stmt->fetch(PDO::FETCH_ASSOC);

        respond([
            'ok'    => true,
            'event' => $event,
            'summary' => [
                'impacted_streets'     => (int)($agg['impacted_streets']    ?? 0),
                'total_households'     => (int)($agg['total_households']     ?? 0),
                'total_persons'        => (int)($agg['total_persons']        ?? 0),
                'flooded_streets'      => (int)($agg['flooded_streets']      ?? 0),
                'severe_flood_streets' => (int)($agg['severe_flood_streets'] ?? 0),
                'damaged_streets'      => (int)($agg['damaged_streets']      ?? 0),
                'inaccessible_streets' => (int)($agg['inaccessible_streets'] ?? 0),
                'risk_breakdown'       => $riskBreakdown,
                'budget' => [
                    'recommended' => $budget['total_recommended'] ? (float)$budget['total_recommended'] : 0,
                    'approved'    => $budget['total_approved']    ? (float)$budget['total_approved']    : 0,
                    'spent'       => $budget['total_spent']       ? (float)$budget['total_spent']       : 0,
                ],
            ],
        ]);

    // ════════════════════════════════════════════════════════
    // map_data — all streets with their impact for the event
    // ════════════════════════════════════════════════════════
    case 'map_data':
        $eventId = filter_input(INPUT_GET, 'event_id', FILTER_VALIDATE_INT);
        if (!$eventId || $eventId <= 0) {
            error_respond('Invalid event ID.', 400);
        }

        // All active streets + their impact data for this event (LEFT JOIN so unaffected streets show too)
        $stmt = $pdo->prepare("
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
                tsi.impact_id,
                tsi.flood_status,
                tsi.damage_status,
                tsi.flood_height_m,
                tsi.road_accessible,
                tsi.affected_households,
                tsi.affected_persons,
                tsi.report_source,
                tsi.notes              AS impact_notes,
                tsi.date_recorded,
                wap.status             AS welfare_status,
                wap.assistance_type
            FROM streets s
            JOIN zones z ON z.zone_id = s.zone_id
            LEFT JOIN typhoon_street_impacts tsi
              ON tsi.street_id = s.street_id AND tsi.event_id = :event_id
            LEFT JOIN welfare_action_plans wap
              ON wap.street_id = s.street_id AND wap.event_id = :event_id2
              AND wap.plan_id = (
                  SELECT plan_id FROM welfare_action_plans
                  WHERE street_id = s.street_id AND event_id = :event_id3
                  ORDER BY created_at DESC LIMIT 1
              )
            WHERE s.is_active = 1
            ORDER BY
                FIELD(s.current_risk_level,'RED','ORANGE','YELLOW','GREEN'),
                s.current_vuln_score DESC
        ");
        $stmt->execute([
            ':event_id'  => $eventId,
            ':event_id2' => $eventId,
            ':event_id3' => $eventId,
        ]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $streets = array_map(function (array $r): array {
            $impactLevel = deriveImpactLevel($r['flood_status'], $r['damage_status']);
            $isAffected  = $r['impact_id'] !== null;

            // Welfare status mapping
            $welfareStatusMap = [
                'Planned'   => 'Pending',
                'Ongoing'   => 'Ongoing Assistance',
                'Completed' => 'Resolved',
                'Cancelled' => 'Resolved',
            ];
            $displayWelfare = $isAffected
                ? ($welfareStatusMap[$r['welfare_status'] ?? ''] ?? 'Needs Help')
                : 'No Impact';

            return [
                'street_id'          => (int)   $r['street_id'],
                'street_name'        => $r['street_name'],
                'zone_name'          => $r['zone_name'],
                'latitude'           => (float) $r['latitude'],
                'longitude'          => (float) $r['longitude'],
                'risk_level'         => $r['risk_level'] ?? 'GREEN',
                'vuln_score'         => (float)($r['vuln_score'] ?? 0),
                'needs_welfare'      => $r['needs_welfare'] ?? 'No',
                'total_population'   => (int)   $r['total_population'],
                'total_households'   => (int)   $r['total_households'],
                'is_affected'        => $isAffected,
                'impact_level'       => $impactLevel,
                'flood_status'       => $r['flood_status'] ?? 'None',
                'damage_status'      => $r['damage_status'] ?? 'None',
                'flood_height_m'     => $r['flood_height_m'] ? (float)$r['flood_height_m'] : null,
                'road_accessible'    => isset($r['road_accessible']) ? (bool)(int)$r['road_accessible'] : true,
                'affected_households'=> (int)($r['affected_households'] ?? 0),
                'affected_persons'   => (int)($r['affected_persons']   ?? 0),
                'report_source'      => $r['report_source'] ?? null,
                'impact_notes'       => $r['impact_notes']  ?? null,
                'date_recorded'      => $r['date_recorded'] ?? null,
                'welfare_status'     => $displayWelfare,
                'assistance_type'    => $r['assistance_type'] ?? null,
            ];
        }, $rows);

        // Evacuation centers
        $evacs = $pdo->query("
            SELECT
                ec.center_id, ec.center_name, ec.address,
                ec.latitude, ec.longitude,
                ec.capacity, ec.current_occupancy,
                ec.contact_person, ec.contact_number,
                z.zone_name
            FROM evacuation_centers ec
            LEFT JOIN zones z ON z.zone_id = ec.zone_id
            WHERE ec.is_active = 1
        ")->fetchAll(PDO::FETCH_ASSOC);

        $mapEvacs = array_map(function (array $e): array {
            return [
                'center_id'         => (int)   $e['center_id'],
                'center_name'       => $e['center_name'],
                'address'           => $e['address'],
                'zone_name'         => $e['zone_name'],
                'latitude'          => (float) $e['latitude'],
                'longitude'         => (float) $e['longitude'],
                'capacity'          => (int)   $e['capacity'],
                'current_occupancy' => (int)   $e['current_occupancy'],
                'contact_person'    => $e['contact_person'],
                'contact_number'    => $e['contact_number'],
            ];
        }, $evacs);

        respond(['ok' => true, 'streets' => $streets, 'evac' => $mapEvacs]);

    // ════════════════════════════════════════════════════════
    // street_impact — full detail for a single street in an event
    // ════════════════════════════════════════════════════════
    case 'street_impact':
        $eventId  = filter_input(INPUT_GET, 'event_id',  FILTER_VALIDATE_INT);
        $streetId = filter_input(INPUT_GET, 'street_id', FILTER_VALIDATE_INT);
        if (!$eventId || !$streetId) {
            error_respond('Invalid parameters.', 400);
        }

        // Street core info
        $stmt = $pdo->prepare("
            SELECT s.*, z.zone_name
            FROM streets s
            JOIN zones z ON z.zone_id = s.zone_id
            WHERE s.street_id = :sid AND s.is_active = 1 LIMIT 1
        ");
        $stmt->execute([':sid' => $streetId]);
        $street = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$street) { error_respond('Street not found.', 404); }

        // Impact record
        $stmt = $pdo->prepare("
            SELECT tsi.*, u.name AS recorded_by_name
            FROM typhoon_street_impacts tsi
            LEFT JOIN users u ON u.id = tsi.recorded_by
            WHERE tsi.event_id = :eid AND tsi.street_id = :sid
            LIMIT 1
        ");
        $stmt->execute([':eid' => $eventId, ':sid' => $streetId]);
        $impact = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

        // Welfare plans
        $stmt = $pdo->prepare("
            SELECT wap.*, u.name AS assigned_name
            FROM welfare_action_plans wap
            LEFT JOIN users u ON u.id = wap.assigned_to
            WHERE wap.event_id = :eid AND wap.street_id = :sid
            ORDER BY wap.created_at DESC
        ");
        $stmt->execute([':eid' => $eventId, ':sid' => $streetId]);
        $plans = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Resource distributions
        $stmt = $pdo->prepare("
            SELECT rd.qty_distributed, rd.recipient_count, rd.distributed_at,
                   rd.total_cost, r.resource_name, r.category, r.unit
            FROM resource_distributions rd
            JOIN resources r ON r.resource_id = rd.resource_id
            WHERE rd.event_id = :eid AND rd.street_id = :sid
            ORDER BY rd.distributed_at DESC
        ");
        $stmt->execute([':eid' => $eventId, ':sid' => $streetId]);
        $resources = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Resident reports
        $stmt = $pdo->prepare("
            SELECT report_type, severity, description, status, created_at
            FROM resident_reports
            WHERE event_id = :eid AND street_id = :sid
            ORDER BY created_at DESC LIMIT 5
        ");
        $stmt->execute([':eid' => $eventId, ':sid' => $streetId]);
        $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Demographics
        $stmt = $pdo->prepare("
            SELECT *
            FROM demographic_indicators
            WHERE street_id = :sid
            ORDER BY survey_date DESC LIMIT 1
        ");
        $stmt->execute([':sid' => $streetId]);
        $demo = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

        $impactLevel = 'None';
        if ($impact) {
            $impactLevel = deriveImpactLevel($impact['flood_status'], $impact['damage_status']);
        }

        respond([
            'ok'          => true,
            'street'      => $street,
            'impact'      => $impact ? array_merge($impact, [
                'flood_height_m'      => $impact['flood_height_m'] ? (float)$impact['flood_height_m'] : null,
                'road_accessible'     => (bool)(int)$impact['road_accessible'],
                'affected_households' => (int)$impact['affected_households'],
                'affected_persons'    => (int)$impact['affected_persons'],
                'impact_level'        => $impactLevel,
            ]) : null,
            'plans'     => $plans,
            'resources' => array_map(function ($r) {
                return array_merge($r, [
                    'qty_distributed' => (int)$r['qty_distributed'],
                    'recipient_count' => (int)$r['recipient_count'],
                    'total_cost'      => $r['total_cost'] ? (float)$r['total_cost'] : null,
                ]);
            }, $resources),
            'reports'   => $reports,
            'demo'      => $demo,
        ]);

    // ════════════════════════════════════════════════════════
    // impact_list — table of all street impacts for an event
    // ════════════════════════════════════════════════════════
    case 'impact_list':
        $eventId = filter_input(INPUT_GET, 'event_id', FILTER_VALIDATE_INT);
        if (!$eventId || $eventId <= 0) {
            error_respond('Invalid event ID.', 400);
        }

        $stmt = $pdo->prepare("
            SELECT
                s.street_id,
                s.street_name,
                z.zone_name,
                s.current_risk_level   AS risk_level,
                s.current_vuln_score   AS vuln_score,
                s.needs_welfare,
                tsi.flood_status,
                tsi.damage_status,
                tsi.flood_height_m,
                tsi.road_accessible,
                tsi.affected_households,
                tsi.affected_persons,
                tsi.notes              AS impact_notes,
                tsi.date_recorded,
                wap.status             AS welfare_plan_status
            FROM typhoon_street_impacts tsi
            JOIN streets s ON s.street_id = tsi.street_id
            JOIN zones   z ON z.zone_id   = s.zone_id
            LEFT JOIN welfare_action_plans wap
              ON wap.street_id = tsi.street_id AND wap.event_id = tsi.event_id
              AND wap.plan_id = (
                  SELECT plan_id FROM welfare_action_plans
                  WHERE  street_id = tsi.street_id AND event_id = tsi.event_id
                  ORDER  BY created_at DESC LIMIT 1
              )
            WHERE tsi.event_id = :id
            ORDER BY
                FIELD(tsi.flood_status,'Severely Flooded','Flooded','None'),
                s.current_vuln_score DESC
        ");
        $stmt->execute([':id' => $eventId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $list = array_map(function (array $r): array {
            $impactLevel = deriveImpactLevel($r['flood_status'], $r['damage_status']);
            return [
                'street_id'           => (int)   $r['street_id'],
                'street_name'         => $r['street_name'],
                'zone_name'           => $r['zone_name'],
                'risk_level'          => $r['risk_level'] ?? 'GREEN',
                'vuln_score'          => (float)($r['vuln_score'] ?? 0),
                'needs_welfare'       => $r['needs_welfare'] ?? 'No',
                'flood_status'        => $r['flood_status'],
                'damage_status'       => $r['damage_status'],
                'impact_level'        => $impactLevel,
                'flood_height_m'      => $r['flood_height_m'] ? (float)$r['flood_height_m'] : null,
                'road_accessible'     => isset($r['road_accessible']) ? (bool)(int)$r['road_accessible'] : true,
                'affected_households' => (int)($r['affected_households'] ?? 0),
                'affected_persons'    => (int)($r['affected_persons']   ?? 0),
                'impact_notes'        => $r['impact_notes'] ?? null,
                'date_recorded'       => $r['date_recorded'] ?? null,
                'welfare_status'      => $r['welfare_plan_status'] ?? null,
            ];
        }, $rows);

        respond(['ok' => true, 'impacts' => $list]);

    // ════════════════════════════════════════════════════════
    default:
        error_respond('Unknown action: ' . htmlspecialchars($action), 400);
}