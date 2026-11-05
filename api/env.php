<?php
// api/env.php — load .env into environment variables

function loadEnv($path) {
    if (!is_readable($path)) return;

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        // Skip comments and blank lines
        if ($line === '' || $line[0] === '#') continue;

        // Split on first '='
        if (strpos($line, '=') === false) continue;
        list($key, $value) = explode('=', $line, 2);

        $key   = trim($key);
        $value = trim($value);

        // Strip surrounding quotes
        if (strlen($value) >= 2) {
            $first = $value[0];
            $last  = substr($value, -1);
            if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                $value = substr($value, 1, -1);
            }
        }

        // Do NOT overwrite variables that already exist (real env vars take priority)
        if (getenv($key) === false && !isset($_ENV[$key]) && !isset($_SERVER[$key])) {
            putenv("$key=$value");
            $_ENV[$key]    = $value;
            $_SERVER[$key] = $value;
        }
    }
}

// Load .env from project root (adjust the path if needed)
loadEnv(__DIR__ . '/../.env');