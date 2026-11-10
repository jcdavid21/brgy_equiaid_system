<?php
declare(strict_types=1);

function pagasaAllowedClassifications(): array
{
    return [
        'Tropical Depression',
        'Tropical Storm',
        'Severe Tropical Storm',
        'Typhoon',
        'Super Typhoon',
    ];
}

function pagasaNormalizeName($value): string
{
    $value = strtoupper(trim((string)$value));
    $value = preg_replace('/\s+/', ' ', $value) ?? '';
    return preg_replace('/[^A-Z0-9 .\-]/', '', $value) ?? '';
}

function pagasaNormalizeDateTime($value, bool $required = false): ?string
{
    $value = trim((string)$value);
    if ($value === '') {
        if ($required) {
            throw new InvalidArgumentException('The report is missing a required UTC date/time.');
        }
        return null;
    }

    $date = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:s\Z', $value, new DateTimeZone('UTC'));
    $errors = DateTimeImmutable::getLastErrors();
    if (!$date || (is_array($errors) && ($errors['warning_count'] || $errors['error_count']))) {
        throw new InvalidArgumentException('The report contains an invalid UTC date/time.');
    }

    return $date->format('Y-m-d H:i:s');
}

function pagasaNullableNumber(array $data, string $key, float $min, float $max): ?float
{
    if (!array_key_exists($key, $data) || $data[$key] === '' || $data[$key] === null) {
        return null;
    }
    if (!is_numeric($data[$key])) {
        throw new InvalidArgumentException("Invalid numeric value for {$key}.");
    }
    $value = (float)$data[$key];
    if ($value < $min || $value > $max) {
        throw new InvalidArgumentException("{$key} is outside the accepted range.");
    }
    return $value;
}

function validatePagasaImportPayload(array $data): array
{
    $pagasaName = pagasaNormalizeName($data['pagasa_name'] ?? '');
    if ($pagasaName === '') {
        throw new InvalidArgumentException('PAGASA name could not be read from the report.');
    }

    $classification = trim((string)($data['classification'] ?? ''));
    if (!in_array($classification, pagasaAllowedClassifications(), true)) {
        throw new InvalidArgumentException('The tropical cyclone classification is missing or invalid.');
    }

    $reportYear = filter_var($data['report_year'] ?? null, FILTER_VALIDATE_INT);
    $currentYear = (int)date('Y') + 1;
    if (!$reportYear || $reportYear < 1948 || $reportYear > $currentYear) {
        throw new InvalidArgumentException('The report year is invalid.');
    }

    $revision = filter_var($data['report_revision'] ?? 0, FILTER_VALIDATE_INT);
    if ($revision === false || $revision < 0 || $revision > 99) {
        throw new InvalidArgumentException('The report revision is invalid.');
    }

    $startedAt = pagasaNormalizeDateTime($data['started_at_utc'] ?? '', true);
    $endedAt = pagasaNormalizeDateTime($data['ended_at_utc'] ?? '');
    $peakAt = pagasaNormalizeDateTime($data['peak_intensity_at_utc'] ?? '');
    $landfallAt = pagasaNormalizeDateTime($data['landfall_at_utc'] ?? '');
    if ($endedAt !== null && $endedAt < $startedAt) {
        throw new InvalidArgumentException('The report end date is earlier than its start date.');
    }

    $published = trim((string)($data['report_published_at'] ?? '')) ?: null;
    $lastRevised = trim((string)($data['report_last_revised_at'] ?? '')) ?: null;
    foreach ([$published, $lastRevised] as $dateValue) {
        if ($dateValue !== null && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateValue)) {
            throw new InvalidArgumentException('The publication or revision date is invalid.');
        }
    }

    $highestTcws = pagasaNullableNumber($data, 'highest_tcws', 1, 5);
    $bulletins = pagasaNullableNumber($data, 'bulletins_issued', 0, 999);

    return [
        'pagasa_name'              => $pagasaName,
        'international_name'       => pagasaNormalizeName($data['international_name'] ?? '') ?: null,
        'international_id'         => preg_replace('/[^A-Z0-9\-]/i', '', trim((string)($data['international_id'] ?? ''))) ?: null,
        'classification'           => $classification,
        'report_year'              => (int)$reportYear,
        'report_revision'          => (int)$revision,
        'report_published_at'      => $published,
        'report_last_revised_at'   => $lastRevised,
        'started_at_utc'           => $startedAt,
        'ended_at_utc'             => $endedAt,
        'peak_intensity_at_utc'    => $peakAt,
        'peak_wind_kph'            => pagasaNullableNumber($data, 'peak_wind_kph', 0, 500),
        'peak_wind_knots'          => pagasaNullableNumber($data, 'peak_wind_knots', 0, 300),
        'minimum_pressure_hpa'     => pagasaNullableNumber($data, 'minimum_pressure_hpa', 800, 1100),
        'developed_inside_par'     => !empty($data['developed_inside_par']) ? 1 : 0,
        'exited_par'               => !empty($data['exited_par']) ? 1 : 0,
        'landfall_location'        => trim((string)($data['landfall_location'] ?? '')) ?: null,
        'landfall_at_utc'          => $landfallAt,
        'highest_tcws'             => $highestTcws === null ? null : (int)$highestTcws,
        'bulletins_issued'         => $bulletins === null ? null : (int)$bulletins,
        'source_url'               => filter_var(trim((string)($data['source_url'] ?? '')), FILTER_VALIDATE_URL) ?: null,
    ];
}

function pagasaEventName(array $report): string
{
    return $report['classification'] . ' ' . $report['pagasa_name'];
}
