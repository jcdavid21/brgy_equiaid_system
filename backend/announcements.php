<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

// ── No auth guard — announcements are public ──────────

require_once __DIR__ . '/db.php';

if (!$pdo) {
    http_response_code(503);
    echo json_encode(['ok' => false, 'error' => 'Database unavailable']);
    exit;
}

$action = trim($_GET['action'] ?? '');

try {
    switch ($action) {

        // ── ALL ANNOUNCEMENTS ──────────────────────────
        case 'announcements':

            // Check if table exists — gracefully return empty if migration not run
            $tableExists = $pdo->query("
                SELECT COUNT(*) FROM information_schema.tables
                WHERE table_schema = DATABASE()
                AND table_name = 'announcements'
            ")->fetchColumn();

            if (!$tableExists) {
                // Return empty with helpful message so page still renders
                echo json_encode([
                    'ok'            => true,
                    'announcements' => [],
                    'active_event'  => null,
                    'last_updated'  => null,
                    '_note'         => 'Run the SQL migration in announcements.php to create the announcements table.',
                ]);
                break;
            }

            $rows = $pdo->query("
                SELECT
                    a.announcement_id,
                    a.event_id,
                    a.category,
                    a.priority,
                    a.title,
                    a.body,
                    a.is_pinned,
                    a.created_at,
                    a.updated_at,
                    u.name   AS author_name,
                    te.event_name
                FROM announcements a
                LEFT JOIN users         u  ON u.id          = a.author_id
                LEFT JOIN typhoon_events te ON te.event_id  = a.event_id
                WHERE a.is_active = 1
                ORDER BY
                    a.is_pinned DESC,
                    FIELD(a.priority,'Critical','High','Normal') ASC,
                    a.created_at DESC
            ")->fetchAll(PDO::FETCH_ASSOC);

            // Cast types
            foreach ($rows as &$r) {
                $r['announcement_id'] = (int) $r['announcement_id'];
                $r['is_pinned']       = (bool) $r['is_pinned'];
            }
            unset($r);

            // Active typhoon event
            $event = $pdo->query("
                SELECT event_id, event_name, local_name, category, wind_speed_kph, status
                FROM typhoon_events
                WHERE status = 'Active'
                ORDER BY created_at DESC
                LIMIT 1
            ")->fetch(PDO::FETCH_ASSOC);

            // Last updated
            $lastUpdated = $rows[0]['created_at'] ?? null;

            echo json_encode([
                'ok'            => true,
                'announcements' => $rows,
                'active_event'  => $event ?: null,
                'last_updated'  => $lastUpdated,
            ]);
            break;

        // ── SINGLE ANNOUNCEMENT ────────────────────────
        case 'detail':
            $id = (int) ($_GET['id'] ?? 0);
            if ($id <= 0) {
                http_response_code(400);
                echo json_encode(['ok' => false, 'error' => 'Invalid ID']);
                exit;
            }

            $stmt = $pdo->prepare("
                SELECT
                    a.*,
                    u.name   AS author_name,
                    te.event_name
                FROM announcements a
                LEFT JOIN users         u  ON u.id         = a.author_id
                LEFT JOIN typhoon_events te ON te.event_id = a.event_id
                WHERE a.announcement_id = :id AND a.is_active = 1
                LIMIT 1
            ");
            $stmt->execute([':id' => $id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$row) {
                http_response_code(404);
                echo json_encode(['ok' => false, 'error' => 'Not found']);
                exit;
            }

            echo json_encode(['ok' => true, 'announcement' => $row]);
            break;

        default:
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Unknown action']);
    }

} catch (PDOException $e) {
    http_response_code(500);
    error_log('[EQUIAID ANNOUNCEMENTS] ' . $e->getMessage());
    echo json_encode(['ok' => false, 'error' => 'Database error']);
} catch (Throwable $e) {
    http_response_code(500);
    error_log('[EQUIAID ANNOUNCEMENTS] ' . $e->getMessage());
    echo json_encode(['ok' => false, 'error' => 'Server error']);
}