<?php

function microfin_local_config(): array
{
    static $config = null;

    if ($config !== null) {
        return $config;
    }

    $localConfigPath = __DIR__ . '/local_config.php';
    if (is_file($localConfigPath)) {
        $loadedConfig = require $localConfigPath;
        $config = is_array($loadedConfig) ? $loadedConfig : [];
        return $config;
    }

    $config = [];
    return $config;
}

function microfin_env(string $key, ?string $default = null): ?string
{
    $value = getenv($key);
    if ($value !== false && $value !== '') {
        return $value;
    }

    if (isset($_ENV[$key]) && $_ENV[$key] !== '') {
        return (string) $_ENV[$key];
    }

    if (isset($_SERVER[$key]) && $_SERVER[$key] !== '') {
        return (string) $_SERVER[$key];
    }

    return $default;
}

function microfin_config(string $key, ?string $default = null): ?string
{
    $localConfig = microfin_local_config();
    if (array_key_exists($key, $localConfig) && $localConfig[$key] !== null && $localConfig[$key] !== '') {
        return (string) $localConfig[$key];
    }

    return microfin_env($key, $default);
}

function microfin_bool_config(string $key, bool $default = false): bool
{
    $rawValue = microfin_config($key, $default ? '1' : '0');
    return in_array(strtolower((string) $rawValue), ['1', 'true', 'yes', 'on', 'drop'], true);
}

function microfin_app_base_url(): string
{
    return rtrim((string) microfin_config('APP_BASE_URL', 'https://microfinwebb-production.up.railway.app'), '/');
}

function microfin_database_config(bool $includeDatabase = true): array
{
    $defaultDatabaseUrl = 'mysql://root:lcwnEQrUEDOrGugVxeAuwJHLKLSiHduh@maglev.proxy.rlwy.net:10111/railway';
    $databaseUrl = microfin_config('DATABASE_URL', $defaultDatabaseUrl);
    $parsedUrl = $databaseUrl ? parse_url($databaseUrl) : false;

    $databaseNameFromUrl = '';
    if (is_array($parsedUrl) && isset($parsedUrl['path'])) {
        $databaseNameFromUrl = ltrim((string) $parsedUrl['path'], '/');
    }

    $config = [
        'host' => microfin_config(
            'MYSQLHOST',
            is_array($parsedUrl) && isset($parsedUrl['host']) ? (string) $parsedUrl['host'] : 'maglev.proxy.rlwy.net'
        ),
        'port' => (int) microfin_config(
            'MYSQLPORT',
            is_array($parsedUrl) && isset($parsedUrl['port']) ? (string) $parsedUrl['port'] : '10111'
        ),
        'username' => microfin_config(
            'MYSQLUSER',
            is_array($parsedUrl) && isset($parsedUrl['user']) ? (string) $parsedUrl['user'] : 'root'
        ),
        'password' => microfin_config(
            'MYSQLPASSWORD',
            is_array($parsedUrl) && isset($parsedUrl['pass']) ? (string) $parsedUrl['pass'] : 'lcwnEQrUEDOrGugVxeAuwJHLKLSiHduh'
        ),
        'database' => microfin_config('MYSQLDATABASE', $databaseNameFromUrl !== '' ? $databaseNameFromUrl : 'railway'),
    ];

    if (!$includeDatabase) {
        unset($config['database']);
    }

    return $config;
}
