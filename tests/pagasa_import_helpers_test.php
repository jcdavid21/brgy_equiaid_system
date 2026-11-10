<?php
declare(strict_types=1);

require __DIR__ . '/../backend/pagasa-import-helpers.php';

function pagasaCheck(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$maymay = validatePagasaImportPayload([
    'pagasa_name' => ' Maymay ',
    'international_name' => 'Kujira',
    'international_id' => '2614',
    'classification' => 'Tropical Storm',
    'report_year' => 2026,
    'report_revision' => 0,
    'report_published_at' => '2026-08-31',
    'started_at_utc' => '2026-08-04T00:00:00Z',
    'ended_at_utc' => '2026-08-06T12:00:00Z',
    'peak_intensity_at_utc' => '2026-08-05T00:00:00Z',
    'peak_wind_kph' => 85,
    'peak_wind_knots' => 45,
    'minimum_pressure_hpa' => 994,
    'developed_inside_par' => true,
    'exited_par' => false,
    'landfall_location' => 'Santa Catalina, Ilocos Sur',
    'landfall_at_utc' => '2026-08-05T19:30:00Z',
    'highest_tcws' => 2,
    'bulletins_issued' => 17,
    'source_url' => 'https://pubfiles.pagasa.dost.gov.ph/report.pdf',
]);

pagasaCheck($maymay['pagasa_name'] === 'MAYMAY', 'PAGASA name normalization failed.');
pagasaCheck($maymay['peak_wind_kph'] === 85.0, 'Peak wind validation failed.');
pagasaCheck($maymay['started_at_utc'] === '2026-08-04 00:00:00', 'UTC normalization failed.');
pagasaCheck(pagasaEventName($maymay) === 'Tropical Storm MAYMAY', 'Event name projection failed.');

try {
    validatePagasaImportPayload(array_merge($maymay, ['classification' => 'Category 9']));
    pagasaCheck(false, 'Invalid classification was accepted.');
} catch (InvalidArgumentException $expected) {
}

try {
    validatePagasaImportPayload(array_merge($maymay, ['minimum_pressure_hpa' => 200]));
    pagasaCheck(false, 'Invalid pressure was accepted.');
} catch (InvalidArgumentException $expected) {
}

echo "PAGASA import helper tests passed.\n";
