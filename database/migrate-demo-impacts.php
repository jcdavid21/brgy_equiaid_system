<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__.'/../backend/db.php';
if (!$pdo) exit(1);
$pdo->exec(file_get_contents(__DIR__.'/20261110_add_demo_impacts.sql'));
echo "Demo impact schema is ready. Existing event and impact data preserved.\n";
