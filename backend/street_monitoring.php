<?php

set_exception_handler(function (Throwable $e) {
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    exit();
});

set_error_handler(function (int $errno, string $errstr, string $errfile, int $errline) {
    throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
});

if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json; charset=utf-8');

/* ── Auth ────────────────────────────────────────────── */
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
    exit();
}

/* ── DB connection ───────────────────────────────────── */
$db_paths = require_once __DIR__ . '/db.php';

/* Fallback: build PDO from constants/env */
if (!isset($pdo) || !($pdo instanceof PDO)) {
    $host   = defined('DB_HOST') ? DB_HOST : (getenv('DB_HOST') ?: 'localhost');
    $dbname = defined('DB_NAME') ? DB_NAME : (getenv('DB_NAME') ?: 'brgy_equiaid');
    $user   = defined('DB_USER') ? DB_USER : (getenv('DB_USER') ?: 'root');
    $pass   = defined('DB_PASS') ? DB_PASS : (getenv('DB_PASS') ?: '');
    try {
        $pdo = new PDO("mysql:host={$host};dbname={$dbname};charset=utf8mb4", $user, $pass);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'DB connection failed: ' . $e->getMessage()]);
        exit();
    }
}

$pdo->setAttribute(PDO::ATTR_ERRMODE,            PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'kpis':              echo json_encode(getKpis($pdo));            break;
        case 'risk_distribution': echo json_encode(getRiskDist($pdo));        break;
        case 'map_streets':       echo json_encode(getMapStreets($pdo));      break;
        case 'streets':           echo json_encode(getStreets($pdo));         break;
        case 'images':            echo json_encode(getImages($pdo));          break;
        case 'demographics':      echo json_encode(getDemographics($pdo));    break;
        case 'evac_centers':      echo json_encode(getEvacCenters($pdo));     break;
        case 'typhoon_events':    echo json_encode(getTyphoonEvents($pdo));   break;
        case 'add_demographics':    echo json_encode(addDemographics($pdo));    break;
        case 'update_demographics': echo json_encode(updateDemographics($pdo)); break;
        case 'delete_demographics': echo json_encode(deleteDemographics($pdo)); break;
        case 'add_street':          echo json_encode(addStreet($pdo));          break;
        case 'update_street':       echo json_encode(updateStreet($pdo));       break;
        case 'delete_street':       echo json_encode(deleteStreet($pdo));       break;
        case 'upload_image':        echo json_encode(uploadImage($pdo));        break;
        case '':
            echo json_encode(['ok' => false, 'error' => 'No action specified.']);
            break;
        default:
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => "Unknown action: {$action}"]);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'DB error: ' . $e->getMessage()]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}

/* ══════════════════════════════════════════════════════
   REUSABLE SUBQUERIES
   Column names verified against brgy_equiaid.sql
══════════════════════════════════════════════════════ */

/**
 * Latest prediction per street from prediction_results.
 * Columns used: street_id, risk_level, needs_welfare,
 *   vulnerability_score, welfare_priority,
 *   image_contribution_pct, demographic_contribution_pct,
 *   trigger_type, predicted_at
 */
function latestPredSQL(): string {
    return "
        SELECT pr2.street_id,
               pr2.risk_level,
               pr2.needs_welfare,
               pr2.vulnerability_score,
               pr2.welfare_priority,
               pr2.image_contribution_pct,
               pr2.demographic_contribution_pct,
               pr2.trigger_type,
               pr2.predicted_at
        FROM   prediction_results pr2
        INNER  JOIN (
            SELECT street_id, MAX(predicted_at) AS mx
            FROM   prediction_results
            GROUP  BY street_id
        ) t ON pr2.street_id = t.street_id AND pr2.predicted_at = t.mx
    ";
}

/**
 * Latest demographic survey per street from demographic_indicators.
 * Columns used: street_id, fourps_households, poverty_rate_pct,
 *   avg_monthly_income, pwd_count, senior_count, pregnant_count,
 *   child_count, informal_settlers_pct, concrete_houses_pct,
 *   light_materials_pct, drainage_type, road_surface, street_width_m,
 *   elevation_m, dist_to_waterway_m, flood_frequency, avg_flood_height_m,
 *   data_source, survey_date, encoded_by
 */
function latestDemoSQL(): string {
    return "
        SELECT di2.*
        FROM   demographic_indicators di2
        INNER  JOIN (
            SELECT street_id, MAX(survey_date) AS mx
            FROM   demographic_indicators
            GROUP  BY street_id
        ) t ON di2.street_id = t.street_id AND di2.survey_date = t.mx
    ";
}

/* ══════════════════════════════════════════════════════
   1. KPIs  — uses analytics_snapshots (pre-aggregated)
              or live aggregate from prediction_results
   Schema: analytics_snapshots(snap_id, snapshot_date,
     total_streets, streets_red, streets_orange,
     streets_yellow, streets_green, streets_needs_welfare)
══════════════════════════════════════════════════════ */
function getKpis(PDO $pdo): array {
    $snap = $pdo->query("
        SELECT total_streets,
               streets_red,
               streets_orange,
               streets_yellow,
               streets_green,
               streets_needs_welfare
        FROM   analytics_snapshots
        ORDER  BY snapshot_date DESC, snap_id DESC
        LIMIT  1
    ")->fetch();

    if ($snap) return ['ok' => true, 'data' => $snap];

    /* Live fallback */
    $rows = $pdo->query(
        "SELECT risk_level, needs_welfare FROM (" . latestPredSQL() . ") lp"
    )->fetchAll();

    $d = ['total_streets' => 0, 'streets_red' => 0, 'streets_orange' => 0,
          'streets_yellow' => 0, 'streets_green' => 0, 'streets_needs_welfare' => 0];

    foreach ($rows as $r) {
        $d['total_streets']++;
        switch (strtoupper($r['risk_level'] ?? '')) {
            case 'RED':    $d['streets_red']++;    break;
            case 'ORANGE': $d['streets_orange']++; break;
            case 'YELLOW': $d['streets_yellow']++; break;
            case 'GREEN':  $d['streets_green']++;  break;
        }
        if (($r['needs_welfare'] ?? '') === 'Yes') $d['streets_needs_welfare']++;
    }
    return ['ok' => true, 'data' => $d];
}

/* ══════════════════════════════════════════════════════
   2. Risk + Welfare Distribution
══════════════════════════════════════════════════════ */
function getRiskDist(PDO $pdo): array {
    $rows = $pdo->query(
        "SELECT risk_level, needs_welfare FROM (" . latestPredSQL() . ") lp"
    )->fetchAll();

    $risk    = ['RED' => 0, 'ORANGE' => 0, 'YELLOW' => 0, 'GREEN' => 0];
    $welfare = ['Yes' => 0, 'Moderate' => 0, 'No' => 0];

    foreach ($rows as $r) {
        $rl = strtoupper($r['risk_level'] ?? '');
        if (isset($risk[$rl]))    $risk[$rl]++;
        $wl = $r['needs_welfare'] ?? '';
        if (isset($welfare[$wl])) $welfare[$wl]++;
    }
    return ['ok' => true, 'data' => $risk, 'welfare' => $welfare];
}

/* ══════════════════════════════════════════════════════
   3. Map streets
   streets: street_id, street_name, zone_id, latitude*, longitude*
   (* latitude/longitude may not exist — fallback to NULL)
   zones: zone_id, zone_name
══════════════════════════════════════════════════════ */
function getMapStreets(PDO $pdo): array {
    /* Check if lat/lng columns exist on streets table */
    $cols      = $pdo->query("SHOW COLUMNS FROM streets")->fetchAll(PDO::FETCH_COLUMN);
    $geoSelect = (in_array('latitude', $cols) && in_array('longitude', $cols))
        ? 's.latitude, s.longitude,'
        : 'NULL AS latitude, NULL AS longitude,';

    $rows = $pdo->query("
        SELECT s.street_id,
               s.street_name,
               z.zone_name,
               {$geoSelect}
               lp.risk_level,
               lp.needs_welfare,
               lp.vulnerability_score AS vuln_score,
               di.flood_frequency
        FROM   streets s
        LEFT   JOIN zones z  ON s.zone_id   = z.zone_id
        LEFT   JOIN (" . latestPredSQL() . ") lp ON s.street_id = lp.street_id
        LEFT   JOIN (" . latestDemoSQL() . ") di ON s.street_id = di.street_id
        WHERE  s.is_active = 1
        ORDER  BY s.street_name
    ")->fetchAll();

    return ['ok' => true, 'data' => $rows];
}

/* ══════════════════════════════════════════════════════
   4. Full streets table  (main data grid)
   streets: street_id, street_name, zone_id, is_active
   zones:   zone_id, zone_name
   prediction_results: vulnerability_score, risk_level,
     needs_welfare, welfare_priority,
     image_contribution_pct, demographic_contribution_pct,
     trigger_type, predicted_at
   demographic_indicators: fourps_households, poverty_rate_pct,
     pwd_count, senior_count, pregnant_count,
     informal_settlers_pct, flood_frequency, avg_flood_height_m,
     drainage_type, road_surface, avg_monthly_income
══════════════════════════════════════════════════════ */
function getStreets(PDO $pdo): array {
    $limit = min(max((int)($_GET['limit'] ?? 500), 1), 1000);

    $stmt = $pdo->prepare("
        SELECT s.street_id,
               s.street_name,
               s.is_active,
               z.zone_id,
               z.zone_name,
               lp.risk_level,
               lp.needs_welfare,
               lp.vulnerability_score             AS vuln_score,
               lp.welfare_priority,
               lp.image_contribution_pct,
               lp.demographic_contribution_pct,
               lp.trigger_type,
               DATE_FORMAT(lp.predicted_at, '%b %d, %Y %H:%i') AS predicted_at,
               di.fourps_households,
               di.poverty_rate_pct,
               di.pwd_count,
               di.senior_count,
               di.pregnant_count,
               di.informal_settlers_pct,
               di.flood_frequency,
               di.avg_flood_height_m,
               di.drainage_type,
               di.road_surface,
               di.avg_monthly_income
        FROM   streets s
        LEFT   JOIN zones z  ON s.zone_id   = z.zone_id
        LEFT   JOIN (" . latestPredSQL() . ") lp ON s.street_id = lp.street_id
        LEFT   JOIN (" . latestDemoSQL() . ") di ON s.street_id = di.street_id
        WHERE  s.is_active = 1
        ORDER  BY COALESCE(lp.vulnerability_score, 0) DESC
        LIMIT  :lim
    ");
    $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return ['ok' => true, 'data' => $stmt->fetchAll()];
}

/* ══════════════════════════════════════════════════════
   5. Uploaded images
   uploaded_images: image_id, street_id, event_id,
     uploaded_by, original_filename, stored_filename,
     file_path, is_processed, is_active, uploaded_at
   image_analysis_queue: analysis_id, image_id, model_id,
     predicted_class, confidence_score, flood_severity,
     damage_severity, road_accessibility, status,
     analyzed_at, created_at
   NOTE: image_analysis_queue has NO generic "status Pending"
   row until analysis runs — use is_processed flag on
   uploaded_images instead for display status.
══════════════════════════════════════════════════════ */
function getImages(PDO $pdo): array {
    $limit = min(max((int)($_GET['limit'] ?? 48), 1), 200);

    $stmt = $pdo->prepare("
        SELECT ui.image_id,
               ui.street_id,
               ui.file_path,
               ui.original_filename,
               ui.is_processed,
               DATE_FORMAT(ui.uploaded_at, '%b %d, %Y') AS uploaded_at,
               s.street_name,
               iaq.status              AS analysis_status,
               iaq.predicted_class,
               iaq.confidence_score,
               iaq.flood_severity,
               iaq.damage_severity,
               iaq.road_accessibility
        FROM   uploaded_images ui
        LEFT   JOIN streets s
                   ON ui.street_id = s.street_id
        LEFT   JOIN image_analysis_queue iaq
                   ON ui.image_id = iaq.image_id
        WHERE  ui.is_active = 1
        ORDER  BY ui.uploaded_at DESC
        LIMIT  :lim
    ");
    $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll();

    /* Derive a friendly display status from is_processed + queue status */
    foreach ($rows as &$r) {
        if (!isset($r['analysis_status']) || $r['analysis_status'] === null) {
            $r['analysis_status'] = $r['is_processed'] ? 'Completed' : 'Queued';
        }
    }
    unset($r);

    return ['ok' => true, 'data' => $rows];
}

/* ══════════════════════════════════════════════════════
   6. Demographic indicators
   demographic_indicators: demo_id, street_id, survey_date,
     fourps_households, poverty_rate_pct, avg_monthly_income,
     pwd_count, senior_count, pregnant_count, child_count,
     informal_settlers_pct, concrete_houses_pct,
     light_materials_pct, drainage_type, road_surface,
     street_width_m, elevation_m, dist_to_waterway_m,
     flood_frequency, avg_flood_height_m, data_source,
     encoded_by, created_at
══════════════════════════════════════════════════════ */
function getDemographics(PDO $pdo): array {
    $limit = min(max((int)($_GET['limit'] ?? 200), 1), 500);

    $stmt = $pdo->prepare("
        SELECT di.demo_id,
               di.street_id,
               di.survey_date                                      AS survey_date_raw,
               DATE_FORMAT(di.survey_date, '%b %d, %Y') AS survey_date,
               di.fourps_households,
               di.poverty_rate_pct,
               di.avg_monthly_income,
               di.pwd_count,
               di.senior_count,
               di.pregnant_count,
               di.child_count,
               di.informal_settlers_pct,
               di.concrete_houses_pct,
               di.light_materials_pct,
               di.drainage_type,
               di.road_surface,
               di.street_width_m,
               di.elevation_m,
               di.dist_to_waterway_m,
               di.flood_frequency,
               di.avg_flood_height_m,
               di.data_source,
               s.street_name
        FROM   demographic_indicators di
        LEFT   JOIN streets s ON di.street_id = s.street_id
        ORDER  BY di.survey_date DESC, s.street_name
        LIMIT  :lim
    ");
    $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return ['ok' => true, 'data' => $stmt->fetchAll()];
}

/* ══════════════════════════════════════════════════════
   7b. Evacuation Centers — for map display
   evacuation_centers: center_id, zone_id, center_name,
     address, latitude, longitude, capacity,
     current_occupancy, contact_person, contact_number,
     is_active, created_at
   zones: zone_id, zone_name
══════════════════════════════════════════════════════ */
function getEvacCenters(PDO $pdo): array {
    $rows = $pdo->query("
        SELECT ec.center_id,
               ec.center_name,
               ec.address,
               ec.latitude,
               ec.longitude,
               ec.capacity,
               ec.current_occupancy,
               ec.contact_person,
               ec.contact_number,
               ec.is_active,
               z.zone_name
        FROM   evacuation_centers ec
        LEFT   JOIN zones z ON ec.zone_id = z.zone_id
        WHERE  ec.is_active = 1
        ORDER  BY ec.center_name
    ")->fetchAll();

    return ['ok' => true, 'data' => $rows];
}

/* ══════════════════════════════════════════════════════
   8. Typhoon events — for upload dropdown
   typhoon_events: event_id, event_name, local_name,
     category, date_started, date_ended, status,
     wind_speed_kph, landfall_date, created_at
   *** NO event_date column — use date_started ***
══════════════════════════════════════════════════════ */
function getTyphoonEvents(PDO $pdo): array {
    $rows = $pdo->query("
        SELECT event_id,
               event_name,
               local_name,
               category,
               status,
               DATE_FORMAT(date_started, '%b %d, %Y') AS date_started,
               DATE_FORMAT(date_ended,   '%b %d, %Y') AS date_ended,
               wind_speed_kph
        FROM   typhoon_events
        ORDER  BY date_started DESC
        LIMIT  30
    ")->fetchAll();

    return ['ok' => true, 'data' => $rows];
}

/* ══════════════════════════════════════════════════════
   POST: Add Street
   streets: street_name, zone_id, is_active
   (no 'notes' column in streets schema)
══════════════════════════════════════════════════════ */
function addStreet(PDO $pdo): array {
    $body   = json_decode(file_get_contents('php://input'), true) ?? [];
    $name   = trim($body['street_name'] ?? '');
    $zoneId = (int)($body['zone_id']    ?? 0);
    $active = isset($body['is_active']) ? (int)$body['is_active'] : 1;

    if (!$name)   return ['ok' => false, 'error' => 'street_name is required.'];
    if (!$zoneId) return ['ok' => false, 'error' => 'zone_id is required.'];

    $stmt = $pdo->prepare("
        INSERT INTO streets (street_name, zone_id, is_active)
        VALUES (:name, :zone, :active)
    ");
    $stmt->execute([':name' => $name, ':zone' => $zoneId, ':active' => $active]);

    return ['ok' => true, 'street_id' => (int)$pdo->lastInsertId()];
}

/* ══════════════════════════════════════════════════════
   POST: Update Street
══════════════════════════════════════════════════════ */
function updateStreet(PDO $pdo): array {
    $body     = json_decode(file_get_contents('php://input'), true) ?? [];
    $streetId = (int)($body['street_id']  ?? 0);
    $name     = trim($body['street_name'] ?? '');
    $zoneId   = (int)($body['zone_id']    ?? 0);
    $active   = isset($body['is_active']) ? (int)$body['is_active'] : 1;

    if (!$streetId) return ['ok' => false, 'error' => 'street_id is required.'];
    if (!$name)     return ['ok' => false, 'error' => 'street_name is required.'];
    if (!$zoneId)   return ['ok' => false, 'error' => 'zone_id is required.'];

    $stmt = $pdo->prepare("
        UPDATE streets
        SET    street_name = :name,
               zone_id     = :zone,
               is_active   = :active
        WHERE  street_id   = :id
    ");
    $stmt->execute([':name' => $name, ':zone' => $zoneId, ':active' => $active, ':id' => $streetId]);

    return ['ok' => true];
}

/* ══════════════════════════════════════════════════════
   POST: Upload Image
   uploaded_images required NOT NULL columns:
     street_id, uploaded_by, original_filename,
     stored_filename, file_path
   image_analysis_queue required NOT NULL columns:
     image_id, model_id, predicted_class,
     confidence_score, class_probabilities,
     flood_severity, damage_severity, road_accessibility
   → We CANNOT insert a "Queued" row without running the model.
     Instead we just mark uploaded_images.is_processed = 0
     and let the background job pick it up.
══════════════════════════════════════════════════════ */
function uploadImage(PDO $pdo): array {
    $streetId   = (int)($_POST['street_id']  ?? 0);
    $eventId    = (int)($_POST['event_id']   ?? 0) ?: null;
    $notes      = trim($_POST['description'] ?? '');
    $uploadedBy = (int)($_SESSION['user_id'] ?? 1);

    if (!$streetId) return ['ok' => false, 'error' => 'street_id is required.'];

    $file = $_FILES['image'] ?? null;
    if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
        $errMap = [
            UPLOAD_ERR_INI_SIZE   => 'File exceeds server upload_max_filesize.',
            UPLOAD_ERR_FORM_SIZE  => 'File exceeds MAX_FILE_SIZE.',
            UPLOAD_ERR_PARTIAL    => 'File only partially uploaded.',
            UPLOAD_ERR_NO_FILE    => 'No file received.',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temp folder.',
            UPLOAD_ERR_CANT_WRITE => 'Cannot write to disk.',
        ];
        $code = $file['error'] ?? UPLOAD_ERR_NO_FILE;
        return ['ok' => false, 'error' => $errMap[$code] ?? "Upload error code {$code}"];
    }

    /* MIME validation */
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $finfo   = new finfo(FILEINFO_MIME_TYPE);
    $mime    = $finfo->file($file['tmp_name']);
    if (!isset($allowed[$mime])) {
        return ['ok' => false, 'error' => 'Only JPG, PNG, and WEBP are allowed.'];
    }
    if ($file['size'] > 10 * 1024 * 1024) {
        return ['ok' => false, 'error' => 'Image must be under 10 MB.'];
    }

    /* Save to uploads/streets/YYYY/MM/ — matching the schema path pattern */
    $ext         = $allowed[$mime];
    $yearMonth   = date('Y/m');
    $storedName  = 'st' . $streetId . '_' . uniqid('', true) . '.' . $ext;
    $uploadDir   = __DIR__ . '/../uploads/streets/' . $yearMonth . '/';
    $relPath     = 'uploads/streets/' . $yearMonth . '/' . $storedName;

    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true)) {
        return ['ok' => false, 'error' => 'Cannot create upload directory.'];
    }
    if (!move_uploaded_file($file['tmp_name'], $uploadDir . $storedName)) {
        return ['ok' => false, 'error' => 'Failed to save file.'];
    }

    /* Insert — all NOT NULL columns provided */
    $stmt = $pdo->prepare("
        INSERT INTO uploaded_images
               (street_id, event_id, uploaded_by,
                original_filename, stored_filename, file_path,
                mime_type, is_processed, is_active, notes)
        VALUES (:street,   :event,    :by,
                :orig,     :stored,   :path,
                :mime,     0,         1,     :notes)
    ");
    $stmt->execute([
        ':street' => $streetId,
        ':event'  => $eventId,
        ':by'     => $uploadedBy,
        ':orig'   => $file['name'],
        ':stored' => $storedName,
        ':path'   => $relPath,
        ':mime'   => $mime,
        ':notes'  => $notes,
    ]);
    $imageId = (int)$pdo->lastInsertId();

    /*
     * We do NOT insert into image_analysis_queue here because it has
     * multiple NOT NULL columns (predicted_class, confidence_score,
     * class_probabilities, etc.) that only the ResNet-50 job can populate.
     * The background worker should poll uploaded_images WHERE is_processed=0.
     */

    return ['ok' => true, 'image_id' => $imageId,
            'message' => 'Image saved. Queued for ResNet-50 analysis.'];
}

/* ══════════════════════════════════════════════════════
   POST: Add Demographic Indicators
   demographic_indicators required NOT NULL: street_id, survey_date
   All other fields are optional.
══════════════════════════════════════════════════════ */
function addDemographics(PDO $pdo): array {
    $body      = json_decode(file_get_contents('php://input'), true) ?? [];
    $streetId  = (int)($body['street_id']  ?? 0);
    $surveyDate= trim($body['survey_date'] ?? '');

    if (!$streetId)   return ['ok' => false, 'error' => 'street_id is required.'];
    if (!$surveyDate) return ['ok' => false, 'error' => 'survey_date is required.'];

    /* Validate date format YYYY-MM-DD */
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $surveyDate)) {
        return ['ok' => false, 'error' => 'survey_date must be in YYYY-MM-DD format.'];
    }

    $encodedBy = (int)($_SESSION['user_id'] ?? 1);

    $stmt = $pdo->prepare("
        INSERT INTO demographic_indicators (
            street_id, survey_date,
            fourps_households, poverty_rate_pct, avg_monthly_income,
            pwd_count, senior_count, pregnant_count, child_count,
            informal_settlers_pct, concrete_houses_pct, light_materials_pct,
            drainage_type, road_surface, street_width_m,
            elevation_m, dist_to_waterway_m,
            flood_frequency, avg_flood_height_m,
            data_source, encoded_by
        ) VALUES (
            :street, :survey_date,
            :fourps, :poverty, :income,
            :pwd, :senior, :pregnant, :child,
            :informal, :concrete, :light,
            :drainage, :road, :width,
            :elevation, :dist_waterway,
            :flood_freq, :flood_height,
            :data_source, :encoded_by
        )
    ");

    $stmt->execute(demoParams($body, $streetId, $surveyDate, $encodedBy));
    return ['ok' => true, 'demo_id' => (int)$pdo->lastInsertId()];
}

/* ══════════════════════════════════════════════════════
   POST: Update Demographic Indicators
══════════════════════════════════════════════════════ */
function updateDemographics(PDO $pdo): array {
    $body      = json_decode(file_get_contents('php://input'), true) ?? [];
    $demoId    = (int)($body['demo_id']    ?? 0);
    $streetId  = (int)($body['street_id']  ?? 0);
    $surveyDate= trim($body['survey_date'] ?? '');

    if (!$demoId)     return ['ok' => false, 'error' => 'demo_id is required.'];
    if (!$streetId)   return ['ok' => false, 'error' => 'street_id is required.'];
    if (!$surveyDate) return ['ok' => false, 'error' => 'survey_date is required.'];

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $surveyDate)) {
        return ['ok' => false, 'error' => 'survey_date must be in YYYY-MM-DD format.'];
    }

    $encodedBy = (int)($_SESSION['user_id'] ?? 1);

    $stmt = $pdo->prepare("
        UPDATE demographic_indicators SET
            street_id            = :street,
            survey_date          = :survey_date,
            fourps_households    = :fourps,
            poverty_rate_pct     = :poverty,
            avg_monthly_income   = :income,
            pwd_count            = :pwd,
            senior_count         = :senior,
            pregnant_count       = :pregnant,
            child_count          = :child,
            informal_settlers_pct= :informal,
            concrete_houses_pct  = :concrete,
            light_materials_pct  = :light,
            drainage_type        = :drainage,
            road_surface         = :road,
            street_width_m       = :width,
            elevation_m          = :elevation,
            dist_to_waterway_m   = :dist_waterway,
            flood_frequency      = :flood_freq,
            avg_flood_height_m   = :flood_height,
            data_source          = :data_source,
            encoded_by           = :encoded_by
        WHERE demo_id = :demo_id
    ");

    $params = demoParams($body, $streetId, $surveyDate, $encodedBy);
    $params[':demo_id'] = $demoId;
    $stmt->execute($params);

    return ['ok' => true];
}

/* ══════════════════════════════════════════════════════
   POST: Delete Demographic Record
   Requires: demo_id
   Uses CASCADE safe DELETE — only removes the single row.
══════════════════════════════════════════════════════ */
function deleteDemographics(PDO $pdo): array {
    $body   = json_decode(file_get_contents('php://input'), true) ?? [];
    $demoId = (int)($body['demo_id'] ?? 0);

    if (!$demoId) return ['ok' => false, 'error' => 'demo_id is required.'];

    $stmt = $pdo->prepare("DELETE FROM demographic_indicators WHERE demo_id = :id");
    $stmt->execute([':id' => $demoId]);

    if ($stmt->rowCount() === 0) {
        return ['ok' => false, 'error' => 'Record not found or already deleted.'];
    }

    return ['ok' => true];
}

/* ══════════════════════════════════════════════════════
   POST: Delete Street (soft delete — sets is_active = 0)
   Hard-deleting a street would cascade-delete prediction
   results, demographics, images etc. Soft delete is safer.
   Requires: street_id
══════════════════════════════════════════════════════ */
function deleteStreet(PDO $pdo): array {
    $body     = json_decode(file_get_contents('php://input'), true) ?? [];
    $streetId = (int)($body['street_id'] ?? 0);

    if (!$streetId) return ['ok' => false, 'error' => 'street_id is required.'];

    $stmt = $pdo->prepare("UPDATE streets SET is_active = 0 WHERE street_id = :id");
    $stmt->execute([':id' => $streetId]);

    if ($stmt->rowCount() === 0) {
        return ['ok' => false, 'error' => 'Street not found or already inactive.'];
    }

    return ['ok' => true];
}

/* ── Shared parameter builder ───────────────────────── */
function demoParams(array $b, int $streetId, string $surveyDate, int $encodedBy): array {
    /* Helper: return null for empty string, else cast */
    $n = function($key, $cast = 'float') use ($b) {
        $v = $b[$key] ?? null;
        if ($v === null || $v === '' || $v === false) return null;
        return $cast === 'int' ? (int)$v : (float)$v;
    };
    $s = function($key) use ($b) {
        $v = trim($b[$key] ?? '');
        return $v === '' ? null : $v;
    };

    return [
        ':street'       => $streetId,
        ':survey_date'  => $surveyDate,
        ':fourps'       => $n('fourps_households',    'int'),
        ':poverty'      => $n('poverty_rate_pct'),
        ':income'       => $n('avg_monthly_income'),
        ':pwd'          => $n('pwd_count',            'int'),
        ':senior'       => $n('senior_count',         'int'),
        ':pregnant'     => $n('pregnant_count',       'int'),
        ':child'        => $n('child_count',          'int'),
        ':informal'     => $n('informal_settlers_pct'),
        ':concrete'     => $n('concrete_houses_pct'),
        ':light'        => $n('light_materials_pct'),
        ':drainage'     => $s('drainage_type'),
        ':road'         => $s('road_surface'),
        ':width'        => $n('street_width_m'),
        ':elevation'    => $n('elevation_m'),
        ':dist_waterway'=> $n('dist_to_waterway_m'),
        ':flood_freq'   => $n('flood_frequency',      'int'),
        ':flood_height' => $n('avg_flood_height_m'),
        ':data_source'  => $s('data_source'),
        ':encoded_by'   => $encodedBy,
    ];
}