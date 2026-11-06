<?php
declare(strict_types=1);

/**
 * Loads database settings from environment variables, or from an optional
 * src/config.local.php (git-ignored) for setups like XAMPP/Apache where shell
 * variables are not passed to PHP. No credentials are hard-coded here.
 */
function app_config(): array
{
    static $config = null;
    if ($config !== null) {
        return $config;
    }

    $local = [];
    $localFile = __DIR__ . '/config.local.php';
    if (is_file($localFile)) {
        $local = require $localFile;
    }

    $config = [
        'db_host' => getenv('LAB_DB_HOST') ?: ($local['db_host'] ?? '127.0.0.1'),
        'db_name' => getenv('LAB_DB_NAME') ?: ($local['db_name'] ?? 'notes_lab'),
        'db_user' => getenv('LAB_DB_USER') ?: ($local['db_user'] ?? ''),
        'db_pass' => getenv('LAB_DB_PASSWORD') ?: ($local['db_pass'] ?? ''),
    ];

    if ($config['db_user'] === '' || $config['db_user'] === 'root') {
        throw new RuntimeException('Database user is not configured (set LAB_DB_USER; root is not allowed).');
    }

    return $config;
}
