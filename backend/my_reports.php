<?php
/**
 * backend/my_reports.php — Barangay EQUIAID My Reports API
 * Returns only reports belonging to the logged-in user ($_SESSION['user_id'])
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

// ── Auth guard ────────────────────────────────────────
if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Not authenticated']);
    exit;
}

$user_id = (int) $_SESSION['user_id'];

require_once __DIR__ . '/db.php';

if (!$pdo) {
    http_response_code(503);
    echo json_encode(['ok' => false, 'error' => 'Database unavailable']);
    exit;
}

$action = trim($_GET['action'] ?? '');

try {
    switch ($action) {

        // ── MY REPORTS LIST + SUMMARY ─────────────────────
        case 'my_reports':
            // All reports for this user, newest first
            $stmt = $pdo->prepare("
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
                    rr.verified_by,
                    rr.verified_at,
                    rr.resolution_notes,
                    rr.created_at,
                    rr.updated_at,
                    s.street_name,
                    s.barangay,
                    s.city,
                    s.zone_id,
                    te.event_name
                FROM resident_reports rr
                LEFT JOIN streets s       ON s.street_id   = rr.street_id
                LEFT JOIN typhoon_events te ON te.event_id = rr.event_id
                WHERE rr.user_id = :user_id
                ORDER BY rr.created_at DESC
            ");
            $stmt->execute([':user_id' => $user_id]);
            $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Build summary counts
            $summary = [
                'total'       => count($reports),
                'pending'     => 0,
                'in_progress' => 0,
                'resolved'    => 0,
                'verified'    => 0,
                'dismissed'   => 0,
            ];

            foreach ($reports as $r) {
                switch ($r['status']) {
                    case 'Pending':     $summary['pending']++;     break;
                    case 'In Progress': $summary['in_progress']++; break;
                    case 'Resolved':    $summary['resolved']++;    break;
                    case 'Verified':    $summary['verified']++;    break;
                    case 'Dismissed':   $summary['dismissed']++;   break;
                }
            }

            echo json_encode([
                'ok'      => true,
                'reports' => $reports,
                'summary' => $summary,
            ]);
            break;

        // ── SINGLE REPORT DETAIL ──────────────────────────
        case 'report_detail':
            $report_id = (int) ($_GET['id'] ?? 0);
            if ($report_id <= 0) {
                http_response_code(400);
                echo json_encode(['ok' => false, 'error' => 'Invalid report ID']);
                exit;
            }

            // Fetch report — enforce user ownership
            $stmt = $pdo->prepare("
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
                    rr.verified_by,
                    rr.verified_at,
                    rr.resolution_notes,
                    rr.created_at,
                    rr.updated_at,
                    s.street_name,
                    s.barangay,
                    s.city,
                    s.zone_id,
                    te.event_name
                FROM resident_reports rr
                LEFT JOIN streets s        ON s.street_id   = rr.street_id
                LEFT JOIN typhoon_events te ON te.event_id  = rr.event_id
                WHERE rr.report_id = :id
                  AND rr.user_id   = :user_id
                LIMIT 1
            ");
            $stmt->execute([':id' => $report_id, ':user_id' => $user_id]);
            $report = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$report) {
                http_response_code(404);
                echo json_encode(['ok' => false, 'error' => 'Report not found']);
                exit;
            }

            // Fetch verifier name if verified
            $verifier = null;
            if ($report['verified_by']) {
                $vstmt = $pdo->prepare("
                    SELECT id, name, role FROM users WHERE id = :id LIMIT 1
                ");
                $vstmt->execute([':id' => $report['verified_by']]);
                $verifier = $vstmt->fetch(PDO::FETCH_ASSOC);
            }

            echo json_encode([
                'ok'       => true,
                'report'   => $report,
                'verifier' => $verifier,
            ]);
            break;

        default:
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Unknown action: ' . htmlspecialchars($action)]);
    }

} catch (PDOException $e) {
    http_response_code(500);
    error_log('[EQUIAID MY_REPORTS] ' . $e->getMessage());
    echo json_encode(['ok' => false, 'error' => 'Database error']);
} catch (Throwable $e) {
    http_response_code(500);
    error_log('[EQUIAID MY_REPORTS] ' . $e->getMessage());
    echo json_encode(['ok' => false, 'error' => 'Server error']);
}