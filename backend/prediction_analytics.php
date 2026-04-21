<?php

declare(strict_types=1);

/* ── CORS (allow same-origin admin pages) ──────────────── */
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

/* ── Load DB connection ────────────────────────────────── */
require_once __DIR__ . '/db.php';   // provides $pdo

/* ── Helpers: send JSON and exit ───────────────────────── */
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
$action = trim($_GET['action'] ?? $_POST['action'] ?? '');

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    error_respond('Unauthorized. Admin access required.', 401);
}


/* ══════════════════════════════════════════════════════════
   PYTHON RESOLVER
   Finds the best available Python binary automatically.

   Priority order:
     1. venv/bin/python3  — project-local virtual environment
        (works for any user who cloned the project and ran
         `python3 -m venv venv` — no hardcoded paths needed)
     2. venv/Scripts/python.exe — same venv on Windows/XAMPP
     3. Common system locations (Homebrew arm64/intel, etc.)
     4. Whatever $PATH gives us as a last resort

   __DIR__ is backend/, so the project root is one level up.
══════════════════════════════════════════════════════════ */
function resolve_python(): string
{
    $projectRoot = dirname(__DIR__);

    // Candidates in priority order (cross-platform)
    $candidates = [
        // Project-local venv — Unix/macOS (highest priority)
        $projectRoot . '/venv/bin/python3',
        $projectRoot . '/venv/bin/python',
        // Project-local venv — Windows
        $projectRoot . '/venv/Scripts/python.exe',
        $projectRoot . '/venv/Scripts/python3.exe',
        // Homebrew arm64 macOS (Apple Silicon)
        '/opt/homebrew/bin/python3',
        // Homebrew intel macOS
        '/usr/local/bin/python3',
    ];

    foreach ($candidates as $bin) {
        if (is_executable($bin)) {
            return $bin;
        }
    }

    // Fallback: ask $PATH (Unix)
    exec('which python3 2>/dev/null', $out);
    if (!empty($out[0]) && is_executable(trim($out[0]))) {
        return trim($out[0]);
    }
    exec('which python 2>/dev/null', $out2);
    if (!empty($out2[0]) && is_executable(trim($out2[0]))) {
        return trim($out2[0]);
    }

    // Fallback: ask $PATH (Windows)
    foreach (['python3', 'python'] as $cmd) {
        exec("where $cmd 2>NUL", $w);
        if (!empty($w[0])) return trim($w[0]);
    }

    return 'python3'; // absolute last resort — will surface a clear error if missing
}


try {
    switch ($action) {

        /* ════════════════════════════════════════════════
           prediction_summary
           Returns all streets with demographics, welfare
           breakdown, zone totals, and trend snapshots.
        ════════════════════════════════════════════════ */
        case 'prediction_summary':

            $streets = $pdo->query(
                "SELECT
                     s.street_id,
                     s.street_name,
                     z.zone_name,
                     s.current_risk_level              AS risk_level,
                     s.current_vuln_score              AS vuln_score,
                     s.needs_welfare,
                     s.last_predicted_at               AS predicted_at,
                     COALESCE(s.total_population,  0)  AS total_population,
                     COALESCE(s.total_households,  0)  AS total_households,
                     COALESCE(d.flood_frequency,   0)  AS flood_frequency,
                     d.poverty_rate_pct,
                     COALESCE(d.pwd_count,         0)  AS pwd_count,
                     COALESCE(d.senior_count,      0)  AS senior_count,
                     COALESCE(d.fourps_households, 0)  AS fourps_households,
                     d.avg_flood_height_m,
                     d.drainage_type
                 FROM streets s
                 LEFT JOIN zones z ON z.zone_id = s.zone_id
                 LEFT JOIN (
                     SELECT street_id, MAX(demo_id) AS lid
                     FROM   demographic_indicators
                     GROUP  BY street_id
                 ) ld ON ld.street_id = s.street_id
                 LEFT JOIN demographic_indicators d ON d.demo_id = ld.lid
                 WHERE  s.is_active = 1
                 ORDER  BY COALESCE(s.current_vuln_score, 0) DESC"
            )->fetchAll();

            $rank = 1;
            foreach ($streets as &$s) {
                $s['welfare_priority'] = ($s['needs_welfare'] === 'Yes') ? $rank++ : null;
            }
            unset($s);

            $welfare  = ['Yes' => 0, 'Moderate' => 0, 'No' => 0];
            $totalPop = 0;
            $scoreSum = 0.0;
            $scoreCnt = 0;

            foreach ($streets as $s) {
                $w = $s['needs_welfare'] ?? 'No';
                if (array_key_exists($w, $welfare)) $welfare[$w]++;
                if ($w === 'Yes') $totalPop += (int) $s['total_population'];
                if ($s['vuln_score'] !== null) {
                    $scoreSum += (float) $s['vuln_score'];
                    $scoreCnt++;
                }
            }

            $zones = $pdo->query(
                "SELECT
                     z.zone_name,
                     SUM(s.needs_welfare = 'Yes')      AS needs_welfare,
                     SUM(s.needs_welfare = 'Moderate') AS moderate_welfare,
                     SUM(s.needs_welfare = 'No')       AS no_welfare
                 FROM   streets s
                 JOIN   zones z ON z.zone_id = s.zone_id
                 WHERE  s.is_active = 1
                 GROUP  BY z.zone_id, z.zone_name
                 ORDER  BY z.zone_name"
            )->fetchAll();

            $snapshots = $pdo->query(
                "SELECT snapshot_date, pct_needs_welfare
                 FROM   analytics_snapshots
                 ORDER  BY snapshot_date ASC
                 LIMIT  30"
            )->fetchAll();

            $lastRun = $pdo->query(
                "SELECT MAX(last_predicted_at)
                 FROM   streets
                 WHERE  last_predicted_at IS NOT NULL"
            )->fetchColumn();

            respond([
                'ok'   => true,
                'data' => [
                    'streets'          => $streets,
                    'welfare'          => $welfare,
                    'zones'            => $zones,
                    'snapshots'        => $snapshots,
                    'avg_score'        => $scoreCnt ? round($scoreSum / $scoreCnt, 2) : 0,
                    'affected_persons' => $totalPop,
                    'last_run'         => $lastRun
                        ? date('M j, Y g:i A', strtotime($lastRun))
                        : 'Never — click Run Prediction',
                ],
            ]);


        /* ════════════════════════════════════════════════
           run_prediction
           Scores every active street with the heuristic
           formula (or Python API if available), then
           saves a daily analytics snapshot.
        ════════════════════════════════════════════════ */
        case 'run_prediction':

            $streets = $pdo->query(
                "SELECT
                     s.street_id,
                     COALESCE(d.flood_frequency,       0)       AS flood_frequency,
                     COALESCE(d.avg_flood_height_m,    0)       AS avg_flood_height_m,
                     COALESCE(d.poverty_rate_pct,      0)       AS poverty_rate_pct,
                     COALESCE(d.informal_settlers_pct, 0)       AS informal_settlers_pct,
                     COALESCE(d.fourps_households,     0)       AS fourps_households,
                     COALESCE(d.pwd_count,             0)       AS pwd_count,
                     COALESCE(d.senior_count,          0)       AS senior_count,
                     COALESCE(d.drainage_type,         'None')  AS drainage_type
                 FROM streets s
                 LEFT JOIN (
                     SELECT street_id, MAX(demo_id) AS lid
                     FROM   demographic_indicators
                     GROUP  BY street_id
                 ) ld ON ld.street_id = s.street_id
                 LEFT JOIN demographic_indicators d ON d.demo_id = ld.lid
                 WHERE s.is_active = 1"
            )->fetchAll();

            $pythonUsed = false;
            $ctx = stream_context_create([
                'http' => [
                    'method'        => 'POST',
                    'header'        => "Content-Type: application/json\r\n",
                    'content'       => json_encode(['streets' => $streets]),
                    'timeout'       => 4,
                    'ignore_errors' => true,
                ],
            ]);
            $pyResp = @file_get_contents('http://localhost:5001/predict/welfare_batch', false, $ctx);

            if ($pyResp) {
                $pyData = json_decode($pyResp, true);
                if (!empty($pyData['ok']) && !empty($pyData['results'])) {
                    $pythonUsed = true;
                    foreach ($pyData['results'] as $r) {
                        updateStreet(
                            $pdo,
                            (int)   $r['street_id'],
                            (float) $r['vuln_score'],
                                    $r['risk_level'],
                                    $r['needs_welfare']
                        );
                    }
                }
            }

            if (!$pythonUsed) {
                foreach ($streets as $s) {
                    $score   = computeScore($s);
                    $risk    = scoreToRisk($score);
                    $welfare = scoreToWelfare($score, $s);
                    updateStreet($pdo, (int) $s['street_id'], $score, $risk, $welfare);
                }
            }

            saveSnapshot($pdo);

            respond([
                'ok'             => true,
                'python_used'    => $pythonUsed,
                'streets_scored' => count($streets),
            ]);


        /* ════════════════════════════════════════════════
           start_training
           Spawns predict_api.py --train in background.
           Returns job_id used for polling.
        ════════════════════════════════════════════════ */
        case 'start_training':

            $epochs    = max(1,    (int)   ($_POST['epochs']      ?? 20));
            $lr        = max(1e-5, (float) ($_POST['lr']          ?? 0.001));
            $batch     = (int)             ($_POST['batch_size']  ?? 32);
            $modelType = preg_replace('/[^a-z_]/', '', $_POST['model_type']  ?? 'rf');
            $valSplit  = max(0.05, min(0.5, (float) ($_POST['valid_split'] ?? 0.2)));
            $dataSource= in_array($_POST['data_source'] ?? 'db', ['db', 'upload'])
                         ? $_POST['data_source'] : 'db';

            // Temp directory for logs / pid / csv
            $tmpDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'equiaid_train';
            if (!is_dir($tmpDir)) @mkdir($tmpDir, 0755, true);

            $jobId   = 'train_' . uniqid('', true);
            $logFile = $tmpDir . DIRECTORY_SEPARATOR . $jobId . '.log';
            $pidFile = $tmpDir . DIRECTORY_SEPARATOR . $jobId . '.pid';
            $csvPath = $tmpDir . DIRECTORY_SEPARATOR . $jobId . '_data.csv';

            // Export DB to CSV for training
            if ($dataSource === 'db') {
                $rows = $pdo->query(
                    "SELECT
                         s.street_id,
                         COALESCE(d.flood_frequency,       0) AS flood_frequency,
                         COALESCE(d.poverty_rate_pct,      0) AS poverty_rate_pct,
                         COALESCE(d.pwd_count,             0) AS pwd_count,
                         COALESCE(d.senior_count,          0) AS senior_count,
                         COALESCE(d.fourps_households,     0) AS fourps_households,
                         COALESCE(d.informal_settlers_pct, 0) AS informal_settlers_pct,
                         COALESCE(d.avg_flood_height_m,    0) AS avg_flood_height_m,
                         CASE COALESCE(d.drainage_type, 'None')
                             WHEN 'None'             THEN 0
                             WHEN 'Open Canal'       THEN 1
                             WHEN 'Closed Drainage'  THEN 2
                             WHEN 'Underground'      THEN 3
                             ELSE 0 END                       AS drainage_score,
                         COALESCE(s.current_vuln_score, 0)   AS vuln_score,
                         CASE COALESCE(s.needs_welfare, 'No')
                             WHEN 'Yes'      THEN 2
                             WHEN 'Moderate' THEN 1
                             ELSE 0 END                       AS welfare_label
                     FROM streets s
                     LEFT JOIN (
                         SELECT street_id, MAX(demo_id) AS lid
                         FROM   demographic_indicators
                         GROUP  BY street_id
                     ) ld ON ld.street_id = s.street_id
                     LEFT JOIN demographic_indicators d ON d.demo_id = ld.lid
                     WHERE s.is_active = 1"
                )->fetchAll();

                $fp = fopen($csvPath, 'w');
                if (!empty($rows)) {
                    fputcsv($fp, array_keys($rows[0]));
                    foreach ($rows as $row) fputcsv($fp, $row);
                }
                fclose($fp);

            } elseif (
                isset($_FILES['dataset']) &&
                $_FILES['dataset']['error'] === UPLOAD_ERR_OK
            ) {
                $ext     = pathinfo($_FILES['dataset']['name'], PATHINFO_EXTENSION);
                $csvPath = $tmpDir . DIRECTORY_SEPARATOR . $jobId . '_upload.' . $ext;
                move_uploaded_file($_FILES['dataset']['tmp_name'], $csvPath);
            }

            // ── Locate predict_api.py ────────────────────────────
            // Searches relative to this file so the project works
            // from any folder without hardcoded paths.
            $pyScript = null;
            $scriptSearchPaths = [
                __DIR__ . '/../predict_api.py',    // standard: backend/ is one level below root
                __DIR__ . '/predict_api.py',        // flat layout: both files in same folder
                __DIR__ . '/../../predict_api.py',  // backend is two levels deep
            ];
            foreach ($scriptSearchPaths as $candidate) {
                if (file_exists($candidate)) {
                    $pyScript = realpath($candidate);
                    break;
                }
            }

            if (!$pyScript) {
                respond(['ok' => false, 'error' => 'predict_api.py not found — browser demo will run instead']);
            }

            // ── Resolve Python binary ────────────────────────────
            // Automatically picks up the project venv if it exists,
            // so any user who ran `python3 -m venv venv` gets the
            // correct isolated Python with no config needed.
            $python    = resolve_python();
            $isWindows = DIRECTORY_SEPARATOR === '\\';

            if ($isWindows) {
                $cmd = sprintf(
                    'start /B %s %s --train --dataset %s --epochs %d --lr %s --batch %d --model %s --val-split %s --job-id %s > %s 2>&1',
                    escapeshellarg($python),
                    escapeshellarg($pyScript),
                    escapeshellarg($csvPath),
                    $epochs,
                    escapeshellarg((string) $lr),
                    $batch,
                    escapeshellarg($modelType),
                    escapeshellarg((string) $valSplit),
                    escapeshellarg($jobId),
                    escapeshellarg($logFile)
                );
                @exec($cmd);
                @file_put_contents($pidFile, '0');
            } else {
                $cmd = sprintf(
                    'nohup %s %s --train --dataset %s --epochs %d --lr %s --batch %d --model %s --val-split %s --job-id %s > %s 2>&1 & echo $! > %s',
                    escapeshellcmd($python),
                    escapeshellarg($pyScript),
                    escapeshellarg($csvPath),
                    $epochs,
                    escapeshellarg((string) $lr),
                    $batch,
                    escapeshellarg($modelType),
                    escapeshellarg((string) $valSplit),
                    escapeshellarg($jobId),
                    escapeshellarg($logFile),
                    escapeshellarg($pidFile)
                );
                @exec($cmd);
            }

            if (!isset($_SESSION['train_jobs'])) $_SESSION['train_jobs'] = [];
            $_SESSION['train_jobs'][$jobId] = [
                'log_file'   => $logFile,
                'pid_file'   => $pidFile,
                'started_at' => time(),
                'epochs'     => $epochs,
                'state'      => 'running',
                'last_line'  => 0,
            ];

            respond(['ok' => true, 'job_id' => $jobId]);


        /* ════════════════════════════════════════════════
           training_status
           Polls the log file for new lines and returns
           parsed epoch / metric data to the frontend.
        ════════════════════════════════════════════════ */
        case 'training_status':

            $jobId = trim($_GET['job_id'] ?? '');

            if (!$jobId || empty($_SESSION['train_jobs'][$jobId])) {
                error_respond('Unknown job ID.', 400);
            }

            $job    = &$_SESSION['train_jobs'][$jobId];
            $status = [
                'state'    => $job['state'] ?? 'running',
                'epoch'    => null,
                'loss'     => null,
                'acc'      => null,
                'val_loss' => null,
                'val_acc'  => null,
                'logs'     => [],
            ];

            $logFile = $job['log_file'] ?? '';
            if (!file_exists($logFile)) {
                respond(['ok' => true, 'status' => $status]);
            }

            $lines    = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
            $newLines = array_slice($lines, (int) ($job['last_line'] ?? 0));
            $job['last_line'] = count($lines);

            foreach ($newLines as $line) {
                $line = trim($line);
                if (!$line) continue;

                $type = 'info';
                if (preg_match('/^\[EPOCH\]/i',  $line)) $type = 'epoch';
                if (preg_match('/^\[METRIC\]/i', $line)) $type = 'metric';
                if (preg_match('/^\[WARN\]/i',   $line)) $type = 'warn';
                if (preg_match('/^\[SYSTEM\]/i', $line)) $type = 'system';
                if (preg_match('/^\[ERROR\]/i',  $line)) {
                    $type = 'error';
                    $job['state'] = $status['state'] = 'error';
                }
                if (preg_match('/^\[DONE\]/i',   $line)) {
                    $type = 'success';
                    $job['state'] = $status['state'] = 'done';
                }

                $status['logs'][] = ['type' => $type, 'msg' => $line];

                if (preg_match('/epoch[=\/\s:]+(\d+)/i',             $line, $m)) $status['epoch']    = (int)   $m[1];
                if (preg_match('/\bloss[=:\s]+([\d.]+)/i',            $line, $m)) $status['loss']     = (float) $m[1];
                if (preg_match('/\bacc(?:uracy)?[=:\s]+([\d.]+)/i',  $line, $m)) $status['acc']      = (float) $m[1];
                if (preg_match('/val_loss[=:\s]+([\d.]+)/i',          $line, $m)) $status['val_loss'] = (float) $m[1];
                if (preg_match('/val_acc(?:uracy)?[=:\s]+([\d.]+)/i', $line, $m)) $status['val_acc']  = (float) $m[1];
            }

            // Check if process died without emitting [DONE]
            if ($job['state'] === 'running') {
                $pidFile = $job['pid_file'] ?? '';
                if (file_exists($pidFile) && function_exists('posix_kill')) {
                    $pid = (int) trim(file_get_contents($pidFile));
                    if ($pid > 0 && !posix_kill($pid, 0)) {
                        $all = file_get_contents($logFile) ?: '';
                        $job['state'] = $status['state'] =
                            stripos($all, '[ERROR]') !== false ? 'error' : 'done';
                    }
                }
            }

            respond(['ok' => true, 'status' => $status]);


        /* ════════════════════════════════════════════════
           save_training_result
           Optionally persists final metrics to
           model_registry after demo training completes.
        ════════════════════════════════════════════════ */
        case 'save_training_result':

            $body = json_decode(file_get_contents('php://input'), true) ?? [];

            try {
                $cols = array_column(
                    $pdo->query("SHOW COLUMNS FROM model_registry")->fetchAll(),
                    'Field'
                );
                if (in_array('model_name', $cols, true)) {
                    $stmt = $pdo->prepare(
                        "INSERT INTO model_registry
                             (model_name, model_type, description, is_active, created_at)
                         VALUES (?, ?, 'Trained via Prediction & Analytics panel', 0, NOW())"
                    );
                    $stmt->execute([
                        'WelfareClassifier_' . date('Ymd_His'),
                        $body['model_type'] ?? 'rf',
                    ]);
                }
            } catch (Throwable $e) {
                error_log('[prediction_analytics.php] save_training_result: ' . $e->getMessage());
            }

            respond(['ok' => true]);


        /* ════════════════════════════════════════════════
           Unknown action
        ════════════════════════════════════════════════ */
        default:
            error_respond(
                $action
                    ? "Unknown action: \"{$action}\". Valid: prediction_summary, run_prediction, start_training, training_status, save_training_result."
                    : 'Missing required parameter: action.',
                400
            );
    }

} catch (PDOException $e) {
    error_log('[prediction_analytics.php] PDOException: ' . $e->getMessage());
    error_respond('A database error occurred. Please try again.', 500);
} catch (Throwable $e) {
    error_log('[prediction_analytics.php] Error: ' . $e->getMessage());
    error_respond('An unexpected error occurred.', 500);
}


/* ══════════════════════════════════════════════════════════
   HELPER FUNCTIONS
══════════════════════════════════════════════════════════ */

function computeScore(array $s): float
{
    $drainPts = ['None' => 10, 'Open Canal' => 6, 'Closed Drainage' => 3, 'Underground' => 0];

    $score =
        min(25, (float) $s['flood_frequency']       * 5)    +
        min(15, (float) $s['avg_flood_height_m']    * 7.5)  +
        min(15, (float) $s['poverty_rate_pct']      * 0.18) +
        min(10, (float) $s['informal_settlers_pct'] * 0.12) +
        min(5,  (float) $s['fourps_households']     * 0.08) +
        min(10, (float) $s['pwd_count']             * 0.5)  +
        min(10, (float) $s['senior_count']          * 0.4)  +
        ($drainPts[$s['drainage_type']] ?? 5);

    return round(min(100.0, max(0.0, $score)), 2);
}

function scoreToRisk(float $score): string
{
    if ($score >= 75) return 'RED';
    if ($score >= 50) return 'ORANGE';
    if ($score >= 30) return 'YELLOW';
    return 'GREEN';
}

function scoreToWelfare(float $score, array $s): string
{
    $ff  = (float) $s['flood_frequency'];
    $pov = (float) $s['poverty_rate_pct'];

    if ($score >= 65 || ($ff >= 4 && $pov >= 50)) return 'Yes';
    if ($score >= 35 || ($ff >= 2 && $pov >= 30)) return 'Moderate';
    return 'No';
}

function updateStreet(PDO $pdo, int $id, float $score, string $risk, string $welfare): void
{
    $pdo->prepare(
        "UPDATE streets
         SET    current_vuln_score = ?,
                current_risk_level = ?,
                needs_welfare      = ?,
                last_predicted_at  = NOW()
         WHERE  street_id = ?"
    )->execute([$score, $risk, $welfare, $id]);
}

function saveSnapshot(PDO $pdo): void
{
    $r = $pdo->query(
        "SELECT
             COUNT(*)                           AS total,
             SUM(current_risk_level = 'RED')    AS red,
             SUM(current_risk_level = 'ORANGE') AS orange,
             SUM(current_risk_level = 'YELLOW') AS yellow,
             SUM(current_risk_level = 'GREEN')  AS green,
             SUM(needs_welfare = 'Yes')         AS w_yes,
             SUM(needs_welfare = 'Moderate')    AS w_mod,
             SUM(needs_welfare = 'No')          AS w_no
         FROM streets
         WHERE is_active = 1"
    )->fetch();

    $total   = max(1, (int) $r['total']);
    $pctAff  = round(((int) $r['red'] + (int) $r['orange']) / $total * 100, 2);
    $pctWelf = round((int) $r['w_yes'] / $total * 100, 2);

    $pdo->prepare(
        "INSERT INTO analytics_snapshots
             (snapshot_date, snapshot_type, total_streets,
              streets_red, streets_orange, streets_yellow, streets_green,
              streets_needs_welfare, streets_moderate_welfare, streets_no_welfare,
              pct_affected, pct_needs_welfare)
         VALUES (CURDATE(), 'Daily', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
             total_streets            = VALUES(total_streets),
             streets_red              = VALUES(streets_red),
             streets_orange           = VALUES(streets_orange),
             streets_yellow           = VALUES(streets_yellow),
             streets_green            = VALUES(streets_green),
             streets_needs_welfare    = VALUES(streets_needs_welfare),
             streets_moderate_welfare = VALUES(streets_moderate_welfare),
             streets_no_welfare       = VALUES(streets_no_welfare),
             pct_affected             = VALUES(pct_affected),
             pct_needs_welfare        = VALUES(pct_needs_welfare)"
    )->execute([
        $r['total'], $r['red'], $r['orange'], $r['yellow'], $r['green'],
        $r['w_yes'], $r['w_mod'], $r['w_no'],
        $pctAff, $pctWelf,
    ]);
}