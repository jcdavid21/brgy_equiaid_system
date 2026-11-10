<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/../backend/db.php';
if (!$pdo) { fwrite(STDERR, "Database connection unavailable.\n"); exit(1); }
$pdo->exec((string)file_get_contents(__DIR__ . '/20260911_add_pagasa_typhoon_imports.sql'));
echo "PAGASA source import schema is ready. Existing events and impact records were preserved.\n";
