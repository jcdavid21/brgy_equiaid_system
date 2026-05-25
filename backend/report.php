<?php
/**
 * backend/report.php — Barangay EQUIAID Report API
 */

// session_start MUST be first — before any output or headers
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

$user_id = (int) $_SESSION['user_id'];

// ── DB ────────────────────────────────────────────────────
require_once __DIR__ . '/db.php';

if (!$pdo) {
    http_response_code(503);
    echo json_encode(['ok' => false, 'error' => 'Database unavailable']);
    exit;
}

$action = trim($_POST['action'] ?? $_GET['action'] ?? '');

try {
    switch ($action) {

        // ── GET STREETS ───────────────────────────────────
        case 'get_streets':
            $rows = $pdo->query("
                SELECT street_id, street_name, zone_id,
                       current_risk_level, latitude, longitude
                FROM streets
                WHERE is_active = 1
                ORDER BY street_name ASC
            ")->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode(['ok' => true, 'streets' => $rows]);
            break;

        // ── SUBMIT REPORT ─────────────────────────────────
        case 'submit_report':
            // Validate required fields
            $report_type = trim($_POST['report_type'] ?? '');
            $street_id   = (int) ($_POST['street_id'] ?? 0);
            $severity    = trim($_POST['severity'] ?? 'Moderate');
            $description = trim($_POST['description'] ?? '');
            $latitude    = $_POST['latitude']  ?? null;
            $longitude   = $_POST['longitude'] ?? null;

            $valid_types = ['Flood', 'Blocked Road', 'Fire', 'Medical Emergency', 'Other'];
            $valid_sev   = ['Low', 'Moderate', 'Severe'];

            if (!in_array($report_type, $valid_types)) {
                echo json_encode(['ok' => false, 'error' => 'Invalid report type']);
                exit;
            }
            if ($street_id <= 0) {
                echo json_encode(['ok' => false, 'error' => 'Street is required']);
                exit;
            }
            if (!in_array($severity, $valid_sev)) {
                $severity = 'Moderate';
            }

            // ── AI flood confidence guard (server-side) ───
            if ($report_type === 'Flood') {
                $ai_confidence  = isset($_POST['ai_flood_confidence'])
                    ? (float) $_POST['ai_flood_confidence'] : null;
                $ai_flood_pct   = isset($_POST['ai_flood_pct'])
                    ? (float) $_POST['ai_flood_pct'] : null;
                $ai_false_pos   = ($_POST['ai_false_positive'] ?? '0') === '1';

                // Only reject if an AI analysis was actually run and it came back low-confidence
                if ($ai_confidence !== null && $ai_flood_pct !== null) {
                    $isSignificantFlood = !$ai_false_pos && $ai_flood_pct >= 15.0;
                    if ($isSignificantFlood && $ai_confidence < 0.50) {
                        echo json_encode([
                            'ok'    => false,
                            'error' => 'The AI flood confidence is below 50% ('
                                . round($ai_confidence * 100, 1)
                                . '%). This photo cannot be classified as a confirmed flood report. '
                                . 'Please upload a clearer image or change the report type.',
                        ]);
                        exit;
                    }
                }
            }

            // ── Rate-limit: max 5 reports per user per hour ──
            $recent = $pdo->prepare("
                SELECT COUNT(*) FROM resident_reports
                WHERE user_id = :uid
                  AND created_at >= NOW() - INTERVAL 1 HOUR
            ");
            $recent->execute([':uid' => $user_id]);
            if ((int) $recent->fetchColumn() >= 5) {
                echo json_encode([
                    'ok'    => false,
                    'error' => 'You have submitted too many reports in the last hour. Please wait before filing another report.',
                ]);
                exit;
            }

            // ── Duplicate guard: same type + same street in last 10 min ──
            $dup = $pdo->prepare("
                SELECT COUNT(*) FROM resident_reports
                WHERE user_id    = :uid
                  AND street_id  = :sid
                  AND report_type = :rtype
                  AND created_at  >= NOW() - INTERVAL 10 MINUTE
            ");
            $dup->execute([':uid' => $user_id, ':sid' => $street_id, ':rtype' => $report_type]);
            if ((int) $dup->fetchColumn() > 0) {
                echo json_encode([
                    'ok'    => false,
                    'error' => 'A similar report for this street was already submitted in the last 10 minutes. Please check existing reports before submitting again.',
                ]);
                exit;
            }

            // ── Handle image upload ───────────────────────
            $image_path = null;
            if (!empty($_FILES['image']['tmp_name'])) {
                $file     = $_FILES['image'];
                $allowed  = ['image/jpeg', 'image/png', 'image/webp'];
                $finfo    = finfo_open(FILEINFO_MIME_TYPE);
                $mime     = finfo_file($finfo, $file['tmp_name']);
                finfo_close($finfo);

                if (!in_array($mime, $allowed)) {
                    echo json_encode(['ok' => false, 'error' => 'Invalid image type. JPG/PNG/WEBP only.']);
                    exit;
                }
                if ($file['size'] > 10 * 1024 * 1024) {
                    echo json_encode(['ok' => false, 'error' => 'Image too large (max 10 MB)']);
                    exit;
                }

                $ext       = pathinfo($file['name'], PATHINFO_EXTENSION) ?: 'jpg';
                $year      = date('Y');
                $month     = date('m');
                $dir       = __DIR__ . "/../uploads/reports/{$year}/{$month}/";
                if (!is_dir($dir)) {
                    mkdir($dir, 0755, true);
                }
                $filename  = 'rpt_' . $user_id . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . strtolower($ext);
                $dest      = $dir . $filename;

                if (!move_uploaded_file($file['tmp_name'], $dest)) {
                    echo json_encode(['ok' => false, 'error' => 'Failed to save image']);
                    exit;
                }

                $image_path = "uploads/reports/{$year}/{$month}/{$filename}";
            }

            // ── Insert into resident_reports ──────────────
            $stmt = $pdo->prepare("
                INSERT INTO resident_reports
                    (user_id, street_id, report_type, severity,
                     description, image_path, latitude, longitude,
                     status, created_at, updated_at)
                VALUES
                    (:user_id, :street_id, :report_type, :severity,
                     :description, :image_path, :latitude, :longitude,
                     'Pending', NOW(), NOW())
            ");

            $stmt->execute([
                ':user_id'     => $user_id,
                ':street_id'   => $street_id,
                ':report_type' => $report_type,
                ':severity'    => $severity,
                ':description' => $description ?: null,
                ':image_path'  => $image_path,
                ':latitude'    => is_numeric($latitude)  ? (float)$latitude  : null,
                ':longitude'   => is_numeric($longitude) ? (float)$longitude : null,
            ]);

            $report_id = (int) $pdo->lastInsertId();

            // ── Also insert into uploaded_images ──────────
            if ($image_path) {
                $stmt2 = $pdo->prepare("
                    INSERT INTO uploaded_images
                        (street_id, uploaded_by, original_filename,
                         stored_filename, file_path, mime_type,
                         capture_lat, capture_lng,
                         is_processed, uploaded_at)
                    VALUES
                        (:street_id, :user_id, :orig, :stored, :path,
                         :mime, :lat, :lng, 0, NOW())
                ");
                $stmt2->execute([
                    ':street_id' => $street_id,
                    ':user_id'   => $user_id,
                    ':orig'      => $_FILES['image']['name'] ?? $filename,
                    ':stored'    => $filename,
                    ':path'      => $image_path,
                    ':mime'      => $mime ?? 'image/jpeg',
                    ':lat'       => is_numeric($latitude)  ? (float)$latitude  : null,
                    ':lng'       => is_numeric($longitude) ? (float)$longitude : null,
                ]);
            }

            echo json_encode([
                'ok'        => true,
                'report_id' => $report_id,
                'message'   => 'Report submitted successfully',
            ]);
            break;

        default:
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Unknown action: ' . $action]);
    }

} catch (PDOException $e) {
    http_response_code(500);
    error_log('[EQUIAID REPORT] ' . $e->getMessage());
    echo json_encode(['ok' => false, 'error' => 'Database error']);
} catch (Throwable $e) {
    http_response_code(500);
    error_log('[EQUIAID REPORT] ' . $e->getMessage());
    echo json_encode(['ok' => false, 'error' => 'Server error']);
}