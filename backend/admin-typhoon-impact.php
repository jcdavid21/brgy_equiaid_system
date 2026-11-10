<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    http_response_code(401);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => 'Administrator access is required.']);
    exit;
}

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/db.php';   // provides $pdo
require_once __DIR__ . '/welfare-plan-display.php';
require_once __DIR__ . '/pagasa-import-helpers.php';
require_once __DIR__ . '/pagasa-csv-import.php';
require_once __DIR__ . '/demo-impact-import.php';
require_once __DIR__ . '/street-impact-csv-import.php';

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

$action = $_GET['action'] ?? $_POST['action'] ?? '';

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

    case 'download_demo_impacts':
    case 'download_impact_csv_sample':
        $catalog = fopen(streetImpactCsvPath(), 'rb');
        if (!$catalog) error_respond('The sample CSV is unavailable.', 500);
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="street_impact_sample_format.csv"');
        header('Cache-Control: private, no-store');
        $output = fopen('php://output', 'wb');
        for ($line = 0; $line < 2; $line++) {
            $row = fgetcsv($catalog, 0, ',', '"', '');
            if ($row === false) break;
            fputcsv($output, $row, ',', '"', '');
        }
        fclose($catalog);
        fclose($output);
        exit;

    case 'download_pagasa_csv': // Older links also receive the small format sample.
    case 'download_pagasa_csv_sample':
        $catalog = fopen(pagasaCsvCatalogPath(), 'rb');
        if (!$catalog) error_respond('The sample CSV is unavailable.', 500);
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="pagasa_sample_format.csv"');
        header('Cache-Control: private, no-store');
        $output = fopen('php://output', 'wb');
        for ($line = 0; $line < 2; $line++) {
            $row = fgetcsv($catalog, 0, ',', '"', '');
            if ($row === false) break;
            fputcsv($output, $row, ',', '"', '');
        }
        fclose($catalog);
        fclose($output);
        exit;

    case 'preview_pagasa_csv':
    case 'import_pagasa_csv':
    case 'preview_demo_impacts':
    case 'import_demo_impacts':
    case 'preview_street_impacts':
    case 'import_street_impacts':
        $isImpact = in_array($action, ['preview_street_impacts', 'import_street_impacts'], true);
        $isDemo = in_array($action, ['preview_demo_impacts', 'import_demo_impacts'], true);
        $pendingKey = $isImpact ? 'street_csv_preview' : ($isDemo ? 'demo_csv_preview' : 'pagasa_csv_preview');
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') error_respond('POST is required.', 405);
        if (!isset($_SESSION['typhoon_csrf']) || !hash_equals($_SESSION['typhoon_csrf'], (string)($_POST['csrf_token'] ?? ''))) {
            error_respond('Your session expired. Reload this page before importing.', 403);
        }
        try {
            if (in_array($action, ['preview_pagasa_csv', 'preview_demo_impacts', 'preview_street_impacts'], true)) {
                unset($_SESSION[$pendingKey]);
                $upload = $_FILES['report_csv'] ?? [];
                if (($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file((string)($upload['tmp_name'] ?? ''))) {
                    throw new InvalidArgumentException('Choose a CSV file to preview. The upload may have exceeded the server upload limit.');
                }
                if (!preg_match('/\.csv$/i', (string)$upload['name']) || $upload['size'] > 2 * 1024 * 1024) {
                    throw new InvalidArgumentException('Choose a .csv file no larger than 2 MB.');
                }
                $content = (string)file_get_contents($upload['tmp_name']);
                $reports = $isImpact ? validateStreetImpactCsv($pdo, $content) : ($isDemo ? validateDemoImpacts($pdo, $content) : pagasaValidateTrustedCsv($content));
                $preview = $isImpact ? streetImpactCsvPreview($pdo, $reports) : ($isDemo ? demoImpactPreview($pdo, $reports) : pagasaCsvPreview($pdo, $reports));
                $token = bin2hex(random_bytes(24));
                $_SESSION[$pendingKey] = ['token' => $token, 'csv' => $content, 'created_at' => time()];
                respond(['ok' => true, 'preview_id' => $token] + $preview);
            }
            $pending = $_SESSION[$pendingKey] ?? null;
            if (!$pending || time() - $pending['created_at'] > 900 || !hash_equals($pending['token'], (string)($_POST['preview_id'] ?? ''))) {
                error_respond('This preview expired or was already imported. Preview the CSV again.', 409);
            }
            $reports = $isImpact ? validateStreetImpactCsv($pdo, $pending['csv']) : ($isDemo ? validateDemoImpacts($pdo, $pending['csv']) : pagasaValidateTrustedCsv($pending['csv']));
            $hash = hash('sha256', $pending['csv']);
            $relative = 'uploads/pagasa/csv/' . $hash . '.csv';
            $directory = __DIR__ . '/../uploads/pagasa/csv';
            if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) {
                throw new RuntimeException('Could not prepare CSV storage.');
            }
            $path = __DIR__ . '/../' . $relative;
            // A repeated batch shares the same immutable, content-addressed file.
            if (is_file($path)) {
                if (!hash_equals($hash, hash_file('sha256', $path))) throw new RuntimeException('Stored CSV checksum mismatch.');
            } elseif (file_put_contents($path, $pending['csv'], LOCK_EX) !== strlen($pending['csv'])) {
                throw new RuntimeException('Could not save the CSV source.');
            }
            $userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
            $counts = $isImpact ? commitStreetImpactCsv($pdo, $reports, $userId) : ($isDemo ? commitDemoImpacts($pdo, $reports, $userId) : pagasaCommitCsv($pdo, $reports, $relative, $hash, $userId));
            unset($_SESSION[$pendingKey]);
            respond(['ok' => true, 'counts' => $counts, 'event_id' => $counts['event_id'] ?? null]);
        } catch (InvalidArgumentException $e) {
            error_respond($e->getMessage(), 422);
        } catch (Throwable $e) {
            error_log('PAGASA CSV import: ' . $e->getMessage());
            error_respond('The CSV could not be imported. No partial batch was saved. Check the import database migrations and server storage, then preview again.', 500);
        }

    // ════════════════════════════════════════════════════════
    // events — list all typhoon events (for dropdown selector)
    // ════════════════════════════════════════════════════════
    case 'events':
        $rows = $pdo->query("
            SELECT
                te.event_id,
                te.event_name,
                te.local_name,
                te.category,
                te.landfall_date,
                te.date_started,
                te.date_ended,
                te.wind_speed_kph,
                te.status,
                te.notes,
                EXISTS(SELECT 1 FROM typhoon_demo_imports di WHERE di.event_id=te.event_id) AS is_demo,
                pi.classification AS imported_classification,
                pi.report_revision,
                pi.data_status,
                pi.import_id AS pagasa_import_id
            FROM typhoon_events te
            LEFT JOIN pagasa_typhoon_imports pi
              ON pi.event_id = te.event_id AND pi.is_latest = 1
            ORDER BY te.date_started DESC
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
                'is_demo'        => (bool)$r['is_demo'],
                'imported_classification' => $r['imported_classification'],
                'report_revision' => isset($r['report_revision']) ? (int)$r['report_revision'] : null,
                'data_status' => $r['data_status'],
                'pagasa_import_id' => isset($r['pagasa_import_id']) ? (int)$r['pagasa_import_id'] : null,
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
            SELECT te.event_id, te.event_name, te.local_name, te.category,
                   te.landfall_date, te.date_started, te.date_ended,
                   te.wind_speed_kph, te.status, te.notes,
                   EXISTS(SELECT 1 FROM typhoon_demo_imports di WHERE di.event_id=te.event_id) AS is_demo,
                   pi.import_id AS pagasa_import_id,
                   pi.classification AS imported_classification,
                   pi.report_revision, pi.data_status, pi.source_agency,
                   pi.report_published_at, pi.report_last_revised_at
            FROM typhoon_events te
            LEFT JOIN pagasa_typhoon_imports pi
              ON pi.event_id = te.event_id AND pi.is_latest = 1
            WHERE te.event_id = :id LIMIT 1
        ");
        $stmt->execute([':id' => $eventId]);
        $event = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$event) { error_respond('Event not found.', 404); }

        // Impact aggregates
        $stmt = $pdo->prepare("
            SELECT
                SUM(tsi.flood_status <> 'None' OR tsi.damage_status <> 'None' OR tsi.affected_households > 0 OR tsi.affected_persons > 0 OR tsi.road_accessible = 0) AS impacted_streets,
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
        $plans = array_map('welfarePlanForDisplay', $stmt->fetchAll(PDO::FETCH_ASSOC));

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
    // streets_list — for the Log Impact street dropdown
    // ════════════════════════════════════════════════════════
    case 'streets_list':
        $rows = $pdo->query("
            SELECT s.street_id, s.street_name, z.zone_name
            FROM streets s
            JOIN zones z ON z.zone_id = s.zone_id
            WHERE s.is_active = 1
            ORDER BY z.zone_name, s.street_name
        ")->fetchAll(PDO::FETCH_ASSOC);
        respond(['ok' => true, 'streets' => $rows]);

    // ════════════════════════════════════════════════════════
    // pagasa_import_history — immutable source revisions
    // ════════════════════════════════════════════════════════
    case 'pagasa_import_history':
        $eventId = filter_input(INPUT_GET, 'event_id', FILTER_VALIDATE_INT);
        if (!$eventId || $eventId <= 0) {
            error_respond('Invalid event ID.', 400);
        }
        $stmt = $pdo->prepare("
            SELECT import_id, pagasa_name, international_name, international_id,
                   classification, report_year, report_revision, report_published_at,
                   report_last_revised_at, data_status, source_agency, source_type,
                   source_url, original_filename, file_sha256, is_latest, imported_at
            FROM pagasa_typhoon_imports
            WHERE event_id = :id
            ORDER BY report_revision DESC, imported_at DESC
        ");
        $stmt->execute([':id' => $eventId]);
        respond(['ok' => true, 'imports' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);

    // ════════════════════════════════════════════════════════
    // view_pagasa_report — authenticated access to source PDF
    // ════════════════════════════════════════════════════════
    case 'view_pagasa_report':
        $importId = filter_input(INPUT_GET, 'import_id', FILTER_VALIDATE_INT);
        if (!$importId || $importId <= 0) {
            error_respond('Invalid import ID.', 400);
        }
        $stmt = $pdo->prepare("SELECT stored_file_path, original_filename, source_type, source_url FROM pagasa_typhoon_imports WHERE import_id = :id LIMIT 1");
        $stmt->execute([':id' => $importId]);
        $stored = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$stored) {
            error_respond('Imported report not found.', 404);
        }
        if (strpos($stored['source_type'], '(verified CSV extract)') !== false) {
            $url = (string)$stored['source_url'];
            if (parse_url($url, PHP_URL_SCHEME) !== 'https' || parse_url($url, PHP_URL_HOST) !== 'pubfiles.pagasa.dost.gov.ph') {
                error_respond('The official source URL is unavailable.', 404);
            }
            header('Location: ' . $url, true, 302);
            exit;
        }
        $projectRoot = realpath(__DIR__ . '/..');
        $filePath = realpath(__DIR__ . '/../' . $stored['stored_file_path']);
        $allowedRoot = realpath(__DIR__ . '/../uploads/pagasa');
        if (!$projectRoot || !$filePath || !$allowedRoot || strpos($filePath, $allowedRoot . DIRECTORY_SEPARATOR) !== 0 || !is_file($filePath)) {
            error_respond('The stored source document is unavailable.', 404);
        }
        header_remove('Content-Type');
        header('Content-Type: application/pdf');
        header('Content-Length: ' . (string)filesize($filePath));
        header('Content-Disposition: inline; filename="' . str_replace(['"', "\r", "\n"], '', basename($stored['original_filename'])) . '"');
        readfile($filePath);
        exit;

    // ════════════════════════════════════════════════════════
    // import_pagasa_report — validate PDF and store projection
    // ════════════════════════════════════════════════════════
    case 'import_pagasa_report':
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            error_respond('POST is required.', 405);
        }
        if (!isset($_SESSION['typhoon_csrf']) || !hash_equals($_SESSION['typhoon_csrf'], (string)($_POST['csrf_token'] ?? ''))) {
            error_respond('Your session expired. Reload this page before importing.', 403);
        }
        if (!isset($_FILES['report_pdf']) || !is_array($_FILES['report_pdf'])) {
            error_respond('Select an official PAGASA PDF report.', 400);
        }
        $upload = $_FILES['report_pdf'];
        if (($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            error_respond('The PDF upload did not complete successfully.', 400);
        }
        if (($upload['size'] ?? 0) < 100 || $upload['size'] > 15 * 1024 * 1024) {
            error_respond('The PDF must be between 100 bytes and 15 MB.', 400);
        }
        $tmpPath = (string)$upload['tmp_name'];
        if (!is_uploaded_file($tmpPath)) {
            error_respond('Invalid uploaded file.', 400);
        }
        $fileInfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $fileInfo->file($tmpPath);
        $signature = file_get_contents($tmpPath, false, null, 0, 5);
        if ($mime !== 'application/pdf' || $signature !== '%PDF-') {
            error_respond('Only a valid PDF document can be imported.', 415);
        }

        $rawJson = (string)($_POST['parsed_data'] ?? '');
        $rawText = trim((string)($_POST['extracted_text'] ?? ''));
        $decoded = json_decode($rawJson, true);
        if (!is_array($decoded) || json_last_error() !== JSON_ERROR_NONE) {
            error_respond('The browser could not provide valid extracted report data.', 400);
        }
        if (strlen($rawText) < 200 || strlen($rawText) > 750000
            || stripos($rawText, 'PAGASA') === false
            || stripos($rawText, 'TROPICAL CYCLONE PRELIMINARY REPORT') === false) {
            error_respond('This does not appear to be an official PAGASA Tropical Cyclone Preliminary Report.', 422);
        }

        try {
            $report = validatePagasaImportPayload($decoded);
        } catch (InvalidArgumentException $e) {
            error_respond($e->getMessage(), 422);
        }
        $sourceHost = $report['source_url'] ? strtolower((string)parse_url($report['source_url'], PHP_URL_HOST)) : '';
        if ($sourceHost !== '' && $sourceHost !== 'www.pagasa.dost.gov.ph'
            && $sourceHost !== 'bagong.pagasa.dost.gov.ph'
            && $sourceHost !== 'pubfiles.pagasa.dost.gov.ph') {
            error_respond('The source URL must be hosted by PAGASA.', 422);
        }

        $fileHash = hash_file('sha256', $tmpPath);
        try {
            // Bind persisted facts to the actual uploaded bytes, not browser-supplied parsed_data.
            $report = pagasaReportForPdfHash($fileHash);
            $rawText = json_encode($report['source_record'], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        } catch (InvalidArgumentException $e) {
            error_respond($e->getMessage(), 422);
        }
        $stmt = $pdo->prepare("SELECT import_id FROM pagasa_typhoon_imports WHERE file_sha256 = :hash LIMIT 1");
        $stmt->execute([':hash' => $fileHash]);
        if ($stmt->fetchColumn()) {
            error_respond('This exact PAGASA report has already been imported.', 409);
        }

        $stmt = $pdo->prepare("
            SELECT import_id, event_id, report_revision
            FROM pagasa_typhoon_imports
            WHERE report_year = :year AND pagasa_name = :name AND is_latest = 1
            LIMIT 1
        ");
        $stmt->execute([':year' => $report['report_year'], ':name' => $report['pagasa_name']]);
        $previous = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        if ($previous && $report['report_revision'] <= (int)$previous['report_revision']) {
            error_respond('A report with the same or a newer revision is already stored.', 409);
        }

        $safeOriginal = preg_replace('/[^A-Za-z0-9._-]/', '_', basename((string)$upload['name'])) ?: 'pagasa-report.pdf';
        $relativeDir = 'uploads/pagasa/' . $report['report_year'];
        $absoluteDir = __DIR__ . '/../' . $relativeDir;
        if (!is_dir($absoluteDir) && !mkdir($absoluteDir, 0750, true) && !is_dir($absoluteDir)) {
            error_respond('Could not prepare secure report storage.', 500);
        }
        $relativePath = $relativeDir . '/' . strtolower($report['pagasa_name']) . '-r' . $report['report_revision'] . '-' . substr($fileHash, 0, 16) . '.pdf';
        $absolutePath = __DIR__ . '/../' . $relativePath;
        if (!move_uploaded_file($tmpPath, $absolutePath)) {
            error_respond('Could not store the uploaded report.', 500);
        }

        try {
            $pdo->beginTransaction();
            $eventId = $previous ? (int)$previous['event_id'] : 0;
            $eventName = pagasaEventName($report);
            $landfallDate = $report['landfall_at_utc'] ? substr($report['landfall_at_utc'], 0, 10) : null;
            $startDate = substr($report['started_at_utc'], 0, 10);
            $endDate = $report['ended_at_utc'] ? substr($report['ended_at_utc'], 0, 10) : null;
            $note = 'Imported from an official DOST-PAGASA Tropical Cyclone Preliminary Report. Values are read-only; see source revision history.';

            if ($eventId > 0) {
                $stmt = $pdo->prepare("
                    UPDATE typhoon_events
                    SET event_name = :event_name, local_name = :local_name,
                        landfall_date = :landfall_date, date_started = :date_started,
                        date_ended = :date_ended, wind_speed_kph = :wind,
                        status = 'Passed', notes = :notes
                    WHERE event_id = :event_id
                ");
                $stmt->execute([
                    ':event_name' => $eventName, ':local_name' => $report['pagasa_name'],
                    ':landfall_date' => $landfallDate, ':date_started' => $startDate,
                    ':date_ended' => $endDate, ':wind' => $report['peak_wind_kph'],
                    ':notes' => $note, ':event_id' => $eventId,
                ]);
                $pdo->prepare("UPDATE pagasa_typhoon_imports SET is_latest = 0 WHERE import_id = :id")
                    ->execute([':id' => (int)$previous['import_id']]);
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO typhoon_events
                        (event_name, local_name, category, landfall_date, date_started,
                         date_ended, wind_speed_kph, status, notes, created_by)
                    VALUES (:event_name, :local_name, NULL, :landfall_date, :date_started,
                            :date_ended, :wind, 'Passed', :notes, :created_by)
                ");
                $stmt->execute([
                    ':event_name' => $eventName, ':local_name' => $report['pagasa_name'],
                    ':landfall_date' => $landfallDate, ':date_started' => $startDate,
                    ':date_ended' => $endDate, ':wind' => $report['peak_wind_kph'],
                    ':notes' => $note, ':created_by' => $_SESSION['user_id'] ?? null,
                ]);
                $eventId = (int)$pdo->lastInsertId();
            }

            $columns = [
                'event_id', 'pagasa_name', 'international_name', 'international_id', 'classification',
                'report_year', 'report_revision', 'report_published_at', 'report_last_revised_at',
                'started_at_utc', 'ended_at_utc', 'peak_intensity_at_utc', 'peak_wind_kph',
                'peak_wind_knots', 'minimum_pressure_hpa', 'developed_inside_par', 'exited_par',
                'landfall_location', 'landfall_at_utc', 'highest_tcws', 'bulletins_issued',
                'data_status', 'source_agency', 'source_type', 'source_url', 'original_filename',
                'stored_file_path', 'file_sha256', 'raw_extracted_text', 'raw_parsed_json',
                'is_latest', 'supersedes_id', 'imported_by'
            ];
            $params = [
                $eventId, $report['pagasa_name'], $report['international_name'], $report['international_id'], $report['classification'],
                $report['report_year'], $report['report_revision'], $report['report_published_at'], $report['report_last_revised_at'],
                $report['started_at_utc'], $report['ended_at_utc'], $report['peak_intensity_at_utc'], $report['peak_wind_kph'],
                $report['peak_wind_knots'], $report['minimum_pressure_hpa'], $report['developed_inside_par'], $report['exited_par'],
                $report['landfall_location'], $report['landfall_at_utc'], $report['highest_tcws'], $report['bulletins_issued'],
                'Preliminary', 'DOST-PAGASA', 'Tropical Cyclone Preliminary Report', $report['source_url'], $safeOriginal,
                $relativePath, $fileHash, $rawText, json_encode($report, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                1, $previous ? (int)$previous['import_id'] : null, $_SESSION['user_id'] ?? null,
            ];
            $placeholders = implode(',', array_fill(0, count($columns), '?'));
            $stmt = $pdo->prepare('INSERT INTO pagasa_typhoon_imports (`' . implode('`,`', $columns) . '`) VALUES (' . $placeholders . ')');
            $stmt->execute($params);
            $importId = (int)$pdo->lastInsertId();
            $pdo->commit();

            try {
                $log = $pdo->prepare("INSERT INTO activity_logs (user_id, action, module, ip_address) VALUES (:uid, :action, 'Typhoon Impact', :ip)");
                $log->execute([
                    ':uid' => $_SESSION['user_id'] ?? null,
                    ':action' => 'Imported PAGASA report for ' . $eventName . ' (revision ' . $report['report_revision'] . ')',
                    ':ip' => $_SERVER['REMOTE_ADDR'] ?? null,
                ]);
            } catch (Throwable $ignored) {
                // Import success must not depend on the optional activity log table.
            }
            respond(['ok' => true, 'event_id' => $eventId, 'import_id' => $importId]);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            if (is_file($absolutePath)) unlink($absolutePath);
            error_log('PAGASA import failed: ' . $e->getMessage());
            error_respond('The report could not be saved. Verify that the PAGASA migration has been applied.', 500);
        }

    // ════════════════════════════════════════════════════════
    // add_event — disabled; official report import is required
    // ════════════════════════════════════════════════════════
    case 'add_event':
        error_respond('Manual typhoon creation is disabled. Import reviewed PAGASA CSV or PDF data instead.', 405);

    // ════════════════════════════════════════════════════════
    // set_event_status — manual status changes are disabled
    // ════════════════════════════════════════════════════════
    case 'set_event_status':
        error_respond('Typhoon status is read-only and is derived from the source report.', 405);

    // ════════════════════════════════════════════════════════
    // add_impact — manual impact entry is disabled
    // ════════════════════════════════════════════════════════
    case 'add_impact':
        error_respond('Manual impact entry is disabled. Verified local impact data must be imported through a supported source workflow.', 405);
    // ════════════════════════════════════════════════════════
    default:
        error_respond('Unknown action: ' . htmlspecialchars($action), 400);
}
