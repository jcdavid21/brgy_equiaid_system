<?php
/** Shared normalized tagging helpers and administrator catalog API. */

const TAG_OBJECT_TYPES = [
    'resident' => ['table' => 'users', 'id' => 'id', 'label' => 'name', 'where' => "role = 'resident'"],
    'street' => ['table' => 'streets', 'id' => 'street_id', 'label' => 'street_name', 'where' => '1=1'],
    'resident_report' => ['table' => 'resident_reports', 'id' => 'report_id', 'label' => 'report_type', 'where' => '1=1'],
    'welfare_action_plan' => ['table' => 'welfare_action_plans', 'id' => 'plan_id', 'label' => 'assistance_type', 'where' => '1=1'],
];

function tag_slug(string $name): string { return strtolower(trim((string)preg_replace('/[^a-z0-9]+/i', '-', $name), '-')); }
function tag_valid_color(string $color): bool { return (bool)preg_match('/^#[0-9a-f]{6}$/i', $color); }

function tag_normalize(array $tags): array {
    $result = [];
    foreach ($tags as $tag) {
        $name = is_array($tag) ? ($tag['name'] ?? '') : $tag;
        if (!is_string($name)) throw new InvalidArgumentException('Every tag must be text.');
        $name = trim((string)preg_replace('/\s+/', ' ', strip_tags($name)));
        $slug = tag_slug($name);
        if ($name === '' || mb_strlen($name) > 80 || $slug === '') throw new InvalidArgumentException('Tags must be 1–80 characters and contain letters or numbers.');
        $result[$slug] = $name;
        if (count($result) > 12) throw new InvalidArgumentException('A record may have at most 12 tags.');
    }
    return $result;
}

function tag_assert_object(string $type): void {
    if (!isset(TAG_OBJECT_TYPES[$type])) throw new InvalidArgumentException('Unsupported record type.');
}

function tag_details(PDO $pdo, string $type, int $recordId): array {
    tag_assert_object($type);
    $stmt = $pdo->prepare('SELECT t.tag_id, t.name, t.slug, t.color, t.is_predefined FROM record_tags rt JOIN tags t ON t.tag_id = rt.tag_id WHERE rt.object_type = ? AND rt.record_id = ? ORDER BY t.name');
    $stmt->execute([$type, $recordId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function tag_list(PDO $pdo, string $type, int $recordId): array { return array_column(tag_details($pdo, $type, $recordId), 'name'); }

function tag_report_details(PDO $pdo, int $reportId): array {
    $stmt = $pdo->prepare("SELECT DISTINCT t.tag_id,t.name,t.slug,t.color,t.is_predefined,
            CASE WHEN direct_rt.tag_id IS NOT NULL THEN 'report' ELSE 'street' END AS source
        FROM resident_reports r
        JOIN record_tags rt ON (rt.object_type='resident_report' AND rt.record_id=r.report_id)
          OR (rt.object_type='street' AND rt.record_id=r.street_id)
        JOIN tags t ON t.tag_id=rt.tag_id
        LEFT JOIN record_tags direct_rt ON direct_rt.object_type='resident_report'
          AND direct_rt.record_id=r.report_id AND direct_rt.tag_id=t.tag_id
        WHERE r.report_id=? ORDER BY t.name");
    $stmt->execute([$reportId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function tag_audit(PDO $pdo, ?int $userId, string $action): void {
    $stmt = $pdo->prepare('INSERT INTO activity_logs (user_id, action, module, ip_address) VALUES (?, ?, ?, ?)');
    $stmt->execute([$userId, $action, 'Tags', substr($_SERVER['REMOTE_ADDR'] ?? 'unknown', 0, 45)]);
}

function tag_sync(PDO $pdo, string $type, int $recordId, array $tags, ?int $userId): void {
    tag_assert_object($type);
    if ($recordId < 1) throw new InvalidArgumentException('Invalid record ID.');
    $wanted = tag_normalize($tags);
    $existing = tag_details($pdo, $type, $recordId);
    $existingBySlug = array_column($existing, null, 'slug');
    $ownsTx = !$pdo->inTransaction();
    if ($ownsTx) $pdo->beginTransaction();
    try {
        $upsert = $pdo->prepare("INSERT INTO tags (name, slug, color, created_by) VALUES (?, ?, '#17684e', ?) ON DUPLICATE KEY UPDATE tag_id = LAST_INSERT_ID(tag_id)");
        $link = $pdo->prepare('INSERT IGNORE INTO record_tags (object_type, record_id, tag_id, assigned_by) VALUES (?, ?, ?, ?)');
        $wantedIds = [];
        foreach ($wanted as $slug => $name) {
            $upsert->execute([$name, $slug, $userId]);
            $tagId = (int)$pdo->lastInsertId();
            $wantedIds[] = $tagId;
            $link->execute([$type, $recordId, $tagId, $userId]);
            if (!isset($existingBySlug[$slug])) tag_audit($pdo, $userId, "Assigned tag '{$name}' to {$type} #{$recordId}");
        }
        $delete = $pdo->prepare('DELETE FROM record_tags WHERE object_type = ? AND record_id = ? AND tag_id = ?');
        foreach ($existing as $old) {
            if (!in_array((int)$old['tag_id'], $wantedIds, true)) {
                $delete->execute([$type, $recordId, $old['tag_id']]);
                tag_audit($pdo, $userId, "Removed tag '{$old['name']}' from {$type} #{$recordId}");
            }
        }
        if ($ownsTx) $pdo->commit();
    } catch (Throwable $e) {
        if ($ownsTx && $pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

function tag_api_response(bool $success, $data = null, string $message = '', int $status = 200): void {
    http_response_code($status);
    echo json_encode(compact('success', 'message', 'data'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') !== __FILE__) return;
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
if (!isset($_SESSION['user_role']) || !in_array($_SESSION['user_role'], ['admin', 'superadmin'], true)) tag_api_response(false, null, 'Unauthorized', 401);
require __DIR__ . '/db.php';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$input = json_decode((string)file_get_contents('php://input'), true) ?: [];
$id = max(0, (int)($_GET['id'] ?? 0));

try {
    if ($method === 'GET' && isset($_GET['records'])) {
        $type = (string)($_GET['object_type'] ?? ''); tag_assert_object($type); $cfg = TAG_OBJECT_TYPES[$type];
        $records = $pdo->query("SELECT {$cfg['id']} AS record_id, {$cfg['label']} AS record_label FROM {$cfg['table']} WHERE {$cfg['where']} ORDER BY {$cfg['label']} LIMIT 500")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($records as &$record) $record['tags'] = tag_details($pdo, $type, (int)$record['record_id']);
        tag_api_response(true, ['records' => $records]);
    }
    if ($method === 'GET' && isset($_GET['recent'])) {
        $limit = min(100, max(1, (int)($_GET['limit'] ?? 25)));
        $rows = $pdo->query("SELECT rt.object_type,rt.record_id,rt.assigned_at,t.name,t.color,u.name assigned_by_name,
            CASE rt.object_type WHEN 'resident' THEN resident.name WHEN 'street' THEN s.street_name
              WHEN 'resident_report' THEN CONCAT(rr.report_type,' report')
              WHEN 'welfare_action_plan' THEN wap.assistance_type END record_label
            FROM record_tags rt JOIN tags t ON t.tag_id=rt.tag_id LEFT JOIN users u ON u.id=rt.assigned_by
            LEFT JOIN users resident ON rt.object_type='resident' AND resident.id=rt.record_id
            LEFT JOIN streets s ON rt.object_type='street' AND s.street_id=rt.record_id
            LEFT JOIN resident_reports rr ON rt.object_type='resident_report' AND rr.report_id=rt.record_id
            LEFT JOIN welfare_action_plans wap ON rt.object_type='welfare_action_plan' AND wap.plan_id=rt.record_id
            ORDER BY rt.assigned_at DESC LIMIT {$limit}")->fetchAll(PDO::FETCH_ASSOC);
        tag_api_response(true, ['assignments' => $rows]);
    }
    if ($method === 'GET') {
        $tags = $pdo->query('SELECT t.tag_id, t.name, t.slug, t.color, t.is_predefined, COUNT(rt.tag_id) usage_count FROM tags t LEFT JOIN record_tags rt ON rt.tag_id=t.tag_id GROUP BY t.tag_id ORDER BY t.is_predefined DESC,t.name')->fetchAll(PDO::FETCH_ASSOC);
        tag_api_response(true, ['tags' => $tags, 'object_types' => array_keys(TAG_OBJECT_TYPES)]);
    }
    if ($method === 'PUT' && isset($_GET['assign'])) {
        tag_sync($pdo, (string)($input['object_type'] ?? ''), (int)($input['record_id'] ?? 0), is_array($input['tags'] ?? null) ? $input['tags'] : [], $_SESSION['user_id'] ?? null);
        tag_api_response(true, null, 'Record tags updated.');
    }
    if ($method === 'POST' || ($method === 'PUT' && $id > 0)) {
        $name = trim((string)($input['name'] ?? '')); $color = strtolower((string)($input['color'] ?? '#17684e'));
        if ($name === '' || mb_strlen($name) > 80 || tag_slug($name) === '') tag_api_response(false, null, 'Name must be 1–80 characters.', 422);
        if (!tag_valid_color($color)) tag_api_response(false, null, 'Color must be a six-digit hex value.', 422);
        if ($method === 'POST') {
            $stmt=$pdo->prepare('INSERT INTO tags(name,slug,color,created_by) VALUES(?,?,?,?)'); $stmt->execute([$name,tag_slug($name),$color,$_SESSION['user_id']??null]); $id=(int)$pdo->lastInsertId();
            tag_audit($pdo,$_SESSION['user_id']??null,"Created custom tag '{$name}'");
        } else {
            $stmt=$pdo->prepare('SELECT name FROM tags WHERE tag_id=?'); $stmt->execute([$id]); $old=$stmt->fetchColumn(); if (!$old) tag_api_response(false,null,'Tag not found.',404);
            $pdo->prepare('UPDATE tags SET name=?,slug=?,color=? WHERE tag_id=?')->execute([$name,tag_slug($name),$color,$id]); tag_audit($pdo,$_SESSION['user_id']??null,"Updated tag '{$old}' to '{$name}'");
        }
        tag_api_response(true,['tag_id'=>$id],$method==='POST'?'Tag created.':'Tag updated.');
    }
    if ($method === 'DELETE' && $id > 0) {
        $stmt=$pdo->prepare('SELECT name,is_predefined FROM tags WHERE tag_id=?'); $stmt->execute([$id]); $tag=$stmt->fetch(); if(!$tag) tag_api_response(false,null,'Tag not found.',404);
        if((int)$tag['is_predefined']) tag_api_response(false,null,'Predefined tags cannot be deleted.',403);
        $links=$pdo->prepare('SELECT object_type,record_id FROM record_tags WHERE tag_id=?'); $links->execute([$id]);
        $pdo->beginTransaction();
        try {
            foreach($links->fetchAll(PDO::FETCH_ASSOC) as $link) tag_audit($pdo,$_SESSION['user_id']??null,"Removed tag '{$tag['name']}' from {$link['object_type']} #{$link['record_id']} during tag deletion");
            $pdo->prepare('DELETE FROM tags WHERE tag_id=?')->execute([$id]);
            tag_audit($pdo,$_SESSION['user_id']??null,"Deleted custom tag '{$tag['name']}'");
            $pdo->commit();
        } catch(Throwable $e) { if($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
        tag_api_response(true,null,'Tag deleted.');
    }
    tag_api_response(false,null,'Method not allowed.',405);
} catch (InvalidArgumentException $e) { tag_api_response(false,null,$e->getMessage(),422);
} catch (PDOException $e) { error_log('[TagsAPI] '.$e->getMessage()); tag_api_response(false,null,$e->getCode()==='23000'?'A tag with that name already exists.':'A database error occurred.',$e->getCode()==='23000'?409:500);
} catch (Throwable $e) { error_log('[TagsAPI] '.$e->getMessage()); tag_api_response(false,null,'An unexpected error occurred.',500); }
