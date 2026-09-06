<?php
/** Minimal, dependency-free .env reader for local EQUIAID configuration. */
function env_value(string $key, ?string $default = null): ?string {
    static $values = null;
    if ($values === null) {
        $values = [];
        $path = dirname(__DIR__) . '/.env';
        if (is_file($path) && is_readable($path)) {
            $parsed = parse_ini_file($path, false, INI_SCANNER_RAW);
            if (is_array($parsed)) $values = $parsed;
        }
    }
    $serverValue = getenv($key);
    if ($serverValue !== false && $serverValue !== '') return $serverValue;
    $value = $values[$key] ?? $default;
    return is_string($value) ? trim($value, " \t\n\r\0\x0B\"'") : $default;
}
