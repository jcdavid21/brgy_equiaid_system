<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/../backend/pagasa-csv-import.php';

function csvAssert(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
}
function csvReject(callable $call, string $label): void {
    try { $call(); } catch (InvalidArgumentException $e) { return; }
    throw new RuntimeException('Accepted invalid CSV: ' . $label);
}
$csv = file_get_contents(pagasaCsvCatalogPath());
$reports = pagasaValidateTrustedCsv($csv);
csvAssert(count($reports) === 50, 'Expected 50 actual cyclone records.');
csvAssert(count(pagasaValidateTrustedCsv("\xEF\xBB\xBF" . $csv)) === 50, 'UTF-8 BOM rejected.');
$lines = explode("\r\n", trim($csv));
csvAssert(count(pagasaValidateTrustedCsv($lines[0] . "\n" . $lines[1] . "\n")) === 1, 'Single-record subset rejected.');
csvReject(fn() => pagasaValidateTrustedCsv(str_replace(',140,75,965,', ',999,75,965,', $csv)), 'forged wind');
csvReject(fn() => pagasaValidateTrustedCsv(str_replace('ACTUAL_SOURCE_EXTRACT', 'SYNTHETIC', $csv)), 'synthetic data');
csvReject(fn() => pagasaValidateTrustedCsv(str_replace('https://pubfiles.pagasa.dost.gov.ph/', 'https://example.com/', $csv)), 'forged URL');
csvReject(fn() => pagasaValidateTrustedCsv($csv . $lines[1] . "\r\n"), 'duplicate cyclone');
csvReject(fn() => pagasaValidateTrustedCsv("pagasa_name,pagasa_name\nA,A\n"), 'duplicate header');
csvReject(fn() => pagasaValidateTrustedCsv("pagasa_name\nAGHON\n"), 'missing columns');
csvReject(fn() => pagasaReadCsv("a,b\n1,2,3\n"), 'ragged row');
csvReject(fn() => pagasaReadCsv("a,b\n"), 'header only');
csvReject(fn() => pagasaReadCsv(str_repeat('x', 2 * 1024 * 1024 + 1)), 'oversize');
csvReject(fn() => pagasaReadCsv("a,b\n\xFF,1\n"), 'invalid UTF-8');
csvReject(fn() => pagasaReportForPdfHash(str_repeat('0', 64)), 'unreviewed PDF');
csvAssert(pagasaReportForPdfHash($reports[0]['source_record']['file_sha256'])['pagasa_name'] === 'AGHON', 'PDF hash did not resolve trusted facts.');
csvAssert($reports[1]['peak_intensity_at_utc'] === null, 'Source inconsistency was filled with an invented timestamp.');
echo "CSV validation passed: 50 real records, BOM/subsets, tampering, duplicates, file limits, and PDF hash binding.\n";

if (!in_array('--database', $argv, true)) exit;
require __DIR__ . '/../backend/db.php';
if (!$pdo) throw new RuntimeException('Database integration testing requires MySQL.');
$productionCounts = [];
foreach (['typhoon_events', 'typhoon_street_impacts', 'pagasa_typhoon_imports'] as $table) {
    $productionCounts[$table] = $pdo->query("SELECT COUNT(*) FROM `{$table}`")->fetchColumn();
}
$testDb = 'equiaid_csv_test_' . bin2hex(random_bytes(6));
$pdo->exec("CREATE DATABASE `{$testDb}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
try {
    foreach (['users', 'typhoon_events', 'typhoon_street_impacts'] as $table) {
        $pdo->exec("CREATE TABLE `{$testDb}`.`{$table}` LIKE `" . DB_NAME . "`.`{$table}`");
    }
    $pdo->exec("USE `{$testDb}`");
    $pdo->exec(file_get_contents(__DIR__ . '/../database/20260911_add_pagasa_typhoon_imports.sql'));
    $pdo->exec("INSERT INTO typhoon_events (event_id,event_name,local_name,date_started,status) VALUES (123,'Legacy Aghon','AGHON','2024-05-23','Monitoring')");
    $pdo->exec('INSERT INTO typhoon_street_impacts (event_id,street_id) VALUES (123,1)');
    $preview = pagasaCsvPreview($pdo, $reports);
    csvAssert($preview['counts']['create'] === 50, 'Initial preview count wrong.');
    $result = pagasaCommitCsv($pdo, $reports, 'uploads/pagasa/csv/test.csv', hash('sha256', $csv), null);
    csvAssert($result === ['created'=>50,'revised'=>0,'skipped'=>0], 'First import failed.');
    csvAssert((int)$pdo->query('SELECT COUNT(*) FROM typhoon_events')->fetchColumn() === 50, 'Legacy event was duplicated.');
    csvAssert((int)$pdo->query('SELECT event_id FROM pagasa_typhoon_imports WHERE pagasa_name="AGHON"')->fetchColumn() === 123, 'Legacy event ID changed.');
    csvAssert((int)$pdo->query('SELECT COUNT(*) FROM typhoon_street_impacts WHERE event_id=123')->fetchColumn() === 1, 'Existing impact link lost.');
    csvAssert((int)$pdo->query("SELECT COUNT(*) FROM typhoon_events WHERE status='Passed'")->fetchColumn() === 50, 'Historical status was not derived.');
    $again = pagasaCommitCsv($pdo, $reports, 'uploads/pagasa/csv/test.csv', hash('sha256', $csv), null);
    csvAssert($again['skipped'] === 50 && $again['created'] === 0, 'Duplicate import was not idempotent.');
    $pdo->exec("UPDATE pagasa_typhoon_imports SET report_revision=1,file_sha256=REPEAT('a',64) WHERE pagasa_name='AGHON'");
    $bad = $reports[0];
    $bad['pagasa_name'] = 'ROLLBACK_TEST';
    $bad['ended_at_utc'] = null;
    try {
        pagasaCommitCsv($pdo, [$reports[0],$bad], 'uploads/pagasa/csv/test.csv', hash('sha256', $csv), null);
        throw new RuntimeException('Invalid batch unexpectedly succeeded.');
    } catch (InvalidArgumentException $expected) {}
    csvAssert((int)$pdo->query('SELECT COUNT(*) FROM pagasa_typhoon_imports')->fetchColumn() === 50, 'Partial batch committed.');
    csvAssert((int)$pdo->query("SELECT report_revision FROM pagasa_typhoon_imports WHERE pagasa_name='AGHON'")->fetchColumn() === 1, 'Failed batch modified previous revision.');
    $revision = pagasaCommitCsv($pdo, [$reports[0]], 'uploads/pagasa/csv/test.csv', hash('sha256', $csv), null);
    csvAssert($revision['revised'] === 1, 'New revision did not supersede older revision.');
    csvAssert((int)$pdo->query("SELECT COUNT(*) FROM pagasa_typhoon_imports WHERE pagasa_name='AGHON' AND is_latest=1")->fetchColumn() === 1, 'Multiple latest revisions.');
    csvAssert((int)$pdo->query('SELECT COUNT(*) FROM typhoon_events')->fetchColumn() === 50, 'Revision duplicated events.');
    echo "Database integration passed: 50-row import, legacy relationships, duplicates, revision history, and rollback.\n";
} finally {
    $pdo->exec('USE `' . DB_NAME . '`');
    $pdo->exec("DROP DATABASE `{$testDb}`");
}
foreach ($productionCounts as $table => $count) {
    csvAssert($count === $pdo->query("SELECT COUNT(*) FROM `{$table}`")->fetchColumn(), 'Production data changed during tests.');
}
echo "Temporary test database removed; application records unchanged.\n";
