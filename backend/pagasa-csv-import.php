<?php
declare(strict_types=1);

require_once __DIR__ . '/pagasa-import-helpers.php';

function pagasaCsvCatalogPath(): string
{
    return __DIR__ . '/data/pagasa-actual.csv';
}

/** Read RFC 4180 CSV; row numbers in errors include the header. */
function pagasaReadCsv(string $content, int $maxRows = 100): array
{
    if ($content === '' || strlen($content) > 2 * 1024 * 1024 || strpos($content, "\0") !== false) {
        throw new InvalidArgumentException('Choose a non-empty CSV file no larger than 2 MB.');
    }
    if (preg_match('//u', $content) !== 1) {
        throw new InvalidArgumentException('The CSV must use UTF-8 encoding.');
    }
    $stream = fopen('php://temp', 'r+');
    fwrite($stream, preg_replace('/^\xEF\xBB\xBF/', '', $content));
    rewind($stream);
    $headers = fgetcsv($stream, 0, ',', '"', '');
    if (!$headers || count($headers) !== count(array_unique($headers)) || in_array('', $headers, true)) {
        fclose($stream);
        throw new InvalidArgumentException('The CSV header is missing or contains duplicate column names.');
    }
    $rows = [];
    $line = 1;
    while (($values = fgetcsv($stream, 0, ',', '"', '')) !== false) {
        $line++;
        if ($values === [null]) continue;
        if (count($values) !== count($headers)) {
            fclose($stream);
            throw new InvalidArgumentException("CSV row {$line} has an incorrect number of columns.");
        }
        $rows[] = ['line' => $line, 'data' => array_combine($headers, array_map('trim', $values))];
        if (count($rows) > $maxRows) {
            fclose($stream);
            throw new InvalidArgumentException('Import at most ' . $maxRows . ' records at a time.');
        }
    }
    fclose($stream);
    if (!$rows) throw new InvalidArgumentException('The CSV has no cyclone records.');
    return $rows;
}

/** Only accept facts identical to the reviewed server-side catalogue, never trust labels/hashes alone. */
function pagasaValidateTrustedCsv(string $content): array
{
    $catalog = pagasaReadCsv((string)file_get_contents(pagasaCsvCatalogPath()));
    $trusted = [];
    foreach ($catalog as $row) {
        $data = $row['data'];
        $trusted[$data['report_year'] . ':' . $data['pagasa_name']] = $data;
    }
    $expectedHeaders = array_keys($catalog[0]['data']);
    sort($expectedHeaders);
    $seen = [];
    $reports = [];
    foreach (pagasaReadCsv($content) as $row) {
        $data = $row['data'];
        $headers = array_keys($data);
        sort($headers);
        if ($headers !== $expectedHeaders) {
            throw new InvalidArgumentException('The columns do not match the reviewed PAGASA CSV. Use the provided dataset with its original columns.');
        }
        $key = $data['report_year'] . ':' . $data['pagasa_name'];
        if (isset($seen[$key])) throw new InvalidArgumentException("Row {$row['line']}: duplicate cyclone {$key}.");
        $seen[$key] = true;
        $reference = $trusted[$key] ?? null;
        $matches = $reference !== null;
        if ($reference) foreach ($reference as $column => $value) {
            if ($data[$column] !== $value) $matches = false;
        }
        if (!$matches) {
            throw new InvalidArgumentException("Row {$row['line']}: {$key} differs from the reviewed source dataset. Edited, synthetic, or unrecognized records cannot be imported.");
        }
        $report = validatePagasaImportPayload($reference);
        $report['source_record'] = $reference;
        $reports[] = $report;
    }
    return $reports;
}

function pagasaReportForPdfHash(string $hash): array
{
    foreach (pagasaReadCsv((string)file_get_contents(pagasaCsvCatalogPath())) as $row) {
        if (hash_equals($row['data']['file_sha256'], $hash)) {
            $report = validatePagasaImportPayload($row['data']);
            $report['source_record'] = $row['data'];
            return $report;
        }
    }
    throw new InvalidArgumentException('This PDF is not in the reviewed PAGASA catalogue. Use the provided CSV dataset or an original PDF referenced by that dataset.');
}

function pagasaCsvDecision(PDO $pdo, array $report): array
{
    $stmt = $pdo->prepare('SELECT import_id, event_id, report_revision, file_sha256 FROM pagasa_typhoon_imports WHERE report_year = ? AND pagasa_name = ? ORDER BY report_revision DESC LIMIT 1');
    $stmt->execute([$report['report_year'], $report['pagasa_name']]);
    $previous = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    if ($previous && (int)$previous['report_revision'] >= $report['report_revision']) {
        if ((int)$previous['report_revision'] === $report['report_revision'] && !hash_equals($previous['file_sha256'], $report['source_record']['file_sha256'])) {
            throw new InvalidArgumentException($report['pagasa_name'] . ': the stored revision has a different source hash. Resolve the source conflict before importing.');
        }
        return ['action' => 'skip', 'previous' => $previous];
    }
    return ['action' => $previous ? 'revise' : 'create', 'previous' => $previous];
}

function pagasaCsvPreview(PDO $pdo, array $reports): array
{
    $counts = ['create' => 0, 'revise' => 0, 'skip' => 0];
    $rows = [];
    foreach ($reports as $report) {
        $decision = pagasaCsvDecision($pdo, $report);
        $counts[$decision['action']]++;
        $source = $report['source_record'];
        $rows[] = [
            'pagasa_name' => $report['pagasa_name'], 'report_year' => $report['report_year'],
            'classification' => $report['classification'], 'report_revision' => $report['report_revision'],
            'peak_wind_kph' => $report['peak_wind_kph'], 'action' => $decision['action'],
            'quality_notes' => $source['quality_notes'], 'source_url' => $report['source_url'],
        ];
    }
    return ['counts' => $counts, 'rows' => $rows];
}

/** One transaction for the complete batch; recheck duplicates inside the transaction. */
function pagasaCommitCsv(PDO $pdo, array $reports, string $storedPath, string $batchHash, ?int $userId): array
{
    $counts = ['created' => 0, 'revised' => 0, 'skipped' => 0];
    $pdo->beginTransaction();
    try {
        // Consistent lock order also protects event IDs and historical welfare/budget references.
        $pdo->query('SELECT event_id FROM typhoon_events ORDER BY event_id FOR UPDATE')->fetchAll();
        $pdo->query('SELECT import_id FROM pagasa_typhoon_imports ORDER BY import_id FOR UPDATE')->fetchAll();
        foreach ($reports as $report) {
            $decision = pagasaCsvDecision($pdo, $report);
            if ($decision['action'] === 'skip') { $counts['skipped']++; continue; }
            $previous = $decision['previous'];
            $source = $report['source_record'];
            $eventId = $previous ? (int)$previous['event_id'] : 0;
            // Reuse only an unambiguous legacy identity; do not guess by name alone.
            if (!$eventId) {
                $find = $pdo->prepare('SELECT event_id FROM typhoon_events WHERE UPPER(local_name) = ? AND YEAR(date_started) = ?');
                $find->execute([$report['pagasa_name'], $report['report_year']]);
                $legacy = $find->fetchAll(PDO::FETCH_COLUMN);
                if (count($legacy) > 1) throw new InvalidArgumentException('Multiple legacy events match ' . $report['pagasa_name'] . '. Resolve the duplicate events first.');
                if ($legacy) $eventId = (int)$legacy[0];
            }
            if (!$report['ended_at_utc']) throw new InvalidArgumentException('Historical CSV imports require a completed cyclone lifecycle.');
            $notes = 'Imported from the reviewed PAGASA CSV; no street impacts supplied. ' . $source['quality_notes'];
            $params = [pagasaEventName($report), $report['pagasa_name'], substr($report['started_at_utc'], 0, 10), substr($report['ended_at_utc'], 0, 10), $report['peak_wind_kph'], $notes];
            if ($eventId) {
                $params[] = $eventId;
                $pdo->prepare("UPDATE typhoon_events SET event_name=?, local_name=?, date_started=?, date_ended=?, wind_speed_kph=?, notes=?, category=NULL, status='Passed' WHERE event_id=?")->execute($params);
            } else {
                $params[] = $userId;
                $pdo->prepare("INSERT INTO typhoon_events (event_name,local_name,date_started,date_ended,wind_speed_kph,notes,created_by,status) VALUES (?,?,?,?,?,?,?,'Passed')")->execute($params);
                $eventId = (int)$pdo->lastInsertId();
            }
            if ($previous) $pdo->prepare('UPDATE pagasa_typhoon_imports SET is_latest=0 WHERE event_id=?')->execute([$eventId]);
            $values = [
                'event_id' => $eventId, 'pagasa_name' => $report['pagasa_name'],
                'international_name' => $report['international_name'], 'international_id' => $report['international_id'],
                'classification' => $report['classification'], 'report_year' => $report['report_year'],
                'report_revision' => $report['report_revision'], 'report_published_at' => $report['report_published_at'],
                'report_last_revised_at' => $report['report_last_revised_at'], 'started_at_utc' => $report['started_at_utc'],
                'ended_at_utc' => $report['ended_at_utc'], 'peak_intensity_at_utc' => $report['peak_intensity_at_utc'],
                'peak_wind_kph' => $report['peak_wind_kph'], 'peak_wind_knots' => $report['peak_wind_knots'],
                'minimum_pressure_hpa' => $report['minimum_pressure_hpa'], 'data_status' => 'Preliminary',
                'source_agency' => 'DOST-PAGASA', 'source_type' => 'Tropical Cyclone Preliminary Report (verified CSV extract)',
                'source_url' => $report['source_url'], 'original_filename' => $source['original_filename'],
                'stored_file_path' => $storedPath, 'file_sha256' => $source['file_sha256'],
                'raw_extracted_text' => json_encode($source, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                'raw_parsed_json' => json_encode(['report' => $source, 'csv_batch_sha256' => $batchHash], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                'is_latest' => 1, 'supersedes_id' => $previous ? (int)$previous['import_id'] : null, 'imported_by' => $userId,
            ];
            $columns = '`' . implode('`,`', array_keys($values)) . '`';
            $placeholders = implode(',', array_fill(0, count($values), '?'));
            $pdo->prepare("INSERT INTO pagasa_typhoon_imports ({$columns}) VALUES ({$placeholders})")->execute(array_values($values));
            $counts[$previous ? 'revised' : 'created']++;
        }
        $pdo->commit();
        return $counts;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}
