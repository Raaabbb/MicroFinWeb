<?php

if (!function_exists('mf_env_first')) {
    function mf_env_first(array $keys): ?string
    {
        foreach ($keys as $key) {
            $value = getenv($key);
            if ($value !== false && trim((string) $value) !== '') {
                return (string) $value;
            }
            if (isset($_ENV[$key]) && trim((string) $_ENV[$key]) !== '') {
                return (string) $_ENV[$key];
            }
            if (isset($_SERVER[$key]) && trim((string) $_SERVER[$key]) !== '') {
                return (string) $_SERVER[$key];
            }
        }

        return null;
    }
}

if (!function_exists('mf_load_local_db_config')) {
    function mf_load_local_db_config(): array
    {
        static $config = null;
        if ($config !== null) {
            return $config;
        }

        $config = [];
        $configPath = __DIR__ . DIRECTORY_SEPARATOR . 'local_db_config.php';
        if (!is_file($configPath)) {
            return $config;
        }

        $loadedConfig = require $configPath;
        if (is_array($loadedConfig)) {
            $config = $loadedConfig;
        }

        return $config;
    }
}

if (!function_exists('mf_local_db_config_first')) {
    function mf_local_db_config_first(array $keys): ?string
    {
        $config = mf_load_local_db_config();

        foreach ($keys as $key) {
            if (!array_key_exists($key, $config)) {
                continue;
            }

            $value = trim((string) $config[$key]);
            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }
}

if (!function_exists('mf_runtime_config_first')) {
    function mf_runtime_config_first(array $keys): ?string
    {
        $envValue = mf_env_first($keys);
        if ($envValue !== null) {
            return $envValue;
        }

        return mf_local_db_config_first($keys);
    }
}

if (!function_exists('mf_is_railway_runtime')) {
    function mf_is_railway_runtime(): bool
    {
        foreach ([
            'RAILWAY_ENVIRONMENT',
            'RAILWAY_ENVIRONMENT_NAME',
            'RAILWAY_PROJECT_NAME',
            'RAILWAY_SERVICE_NAME',
            'RAILWAY_PROJECT_ID',
            'RAILWAY_SERVICE_ID',
            'RAILWAY_PUBLIC_DOMAIN',
            'RAILWAY_PRIVATE_DOMAIN',
            'RAILWAY_STATIC_URL',
        ] as $key) {
            $value = mf_env_first([$key]);
            if ($value !== null) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('mf_runtime_db_mode')) {
    function mf_runtime_db_mode(): string
    {
        $mode = strtolower(trim((string) (mf_runtime_config_first(['MF_DB_MODE']) ?? 'auto')));

        if (in_array($mode, ['local', 'auto', 'remote'], true)) {
            return $mode;
        }

        if (in_array($mode, ['public', 'remote-public', 'railway-public'], true)) {
            return 'remote';
        }

        return 'auto';
    }
}

if (!function_exists('mf_database_target_from_url')) {
    function mf_database_target_from_url(string $databaseUrl): ?array
    {
        $parts = parse_url($databaseUrl);
        if ($parts === false) {
            return null;
        }

        $target = [];

        if (!empty($parts['host'])) {
            $target['host'] = (string) $parts['host'];
        }
        if (!empty($parts['port'])) {
            $target['port'] = (int) $parts['port'];
        }
        if (array_key_exists('user', $parts)) {
            $target['user'] = urldecode((string) $parts['user']);
        }
        if (array_key_exists('pass', $parts)) {
            $target['pass'] = urldecode((string) $parts['pass']);
        }
        if (!empty($parts['path'])) {
            $target['db'] = ltrim((string) $parts['path'], '/');
        }

        return $target;
    }
}

if (!function_exists('mf_resolve_public_db_target')) {
    function mf_resolve_public_db_target(): ?array
    {
        $target = [];

        $publicDatabaseUrl = mf_runtime_config_first([
            'MYSQL_PUBLIC_URL',
            'PUBLIC_DATABASE_URL',
            'PUBLIC_MYSQL_URL',
            'REMOTE_DATABASE_URL',
        ]);
        if ($publicDatabaseUrl !== null) {
            $parsedTarget = mf_database_target_from_url($publicDatabaseUrl);
            if ($parsedTarget !== null) {
                $target = array_merge($target, $parsedTarget);
            }
        }

        $overrides = [
            'host' => mf_runtime_config_first(['PUBLIC_DB_HOST', 'PUBLIC_MYSQL_HOST', 'REMOTE_DB_HOST']),
            'port' => mf_runtime_config_first(['PUBLIC_DB_PORT', 'PUBLIC_MYSQL_PORT', 'REMOTE_DB_PORT']),
            'db' => mf_runtime_config_first(['PUBLIC_DB_NAME', 'PUBLIC_MYSQL_DATABASE', 'REMOTE_DB_NAME']),
            'user' => mf_runtime_config_first(['PUBLIC_DB_USER', 'PUBLIC_MYSQL_USER', 'REMOTE_DB_USER']),
            'pass' => mf_runtime_config_first(['PUBLIC_DB_PASSWORD', 'PUBLIC_MYSQL_PASSWORD', 'REMOTE_DB_PASSWORD']),
        ];

        foreach ($overrides as $key => $value) {
            if ($value === null) {
                continue;
            }

            $target[$key] = $key === 'port' ? (int) $value : $value;
        }

        if (empty($target['host']) || empty($target['db']) || !isset($target['user'])) {
            return null;
        }

        if (!isset($target['port']) || (int) $target['port'] <= 0) {
            $target['port'] = 3306;
        }
        if (!array_key_exists('pass', $target)) {
            $target['pass'] = '';
        }

        return $target;
    }
}

if (!function_exists('mf_resolve_db_targets')) {
    function mf_resolve_db_targets(): array
    {
        $isRailway = mf_is_railway_runtime();
        $dbMode = mf_runtime_db_mode();
        
        $targets = [];

        // 1. Resolve Local Target (Priority 1 if not explicitly forced to remote)
        $baseLocalTarget = [
            'host' => mf_runtime_config_first(['LOCAL_DB_HOST']) ?? 'localhost',
            'port' => (int) (mf_runtime_config_first(['LOCAL_DB_PORT']) ?? 3306),
            'db' => mf_runtime_config_first(['LOCAL_DB_NAME']) ?? 'microfin_db',
            'user' => mf_runtime_config_first(['LOCAL_DB_USER']) ?? 'root',
        ];
        $explicitLocalPassword = mf_runtime_config_first(['LOCAL_DB_PASSWORD']);
        $passwordCandidates = $explicitLocalPassword !== null
            ? [$explicitLocalPassword]
            : ['1234', ''];

        $localTargets = [];
        foreach ($passwordCandidates as $passwordCandidate) {
            $localTargets[] = array_merge($baseLocalTarget, [
                'pass' => $passwordCandidate,
            ]);
        }

        // 2. Resolve Remote/Railway Target (Priority 2 or 1 depending on runtime)
        $remoteTarget = null;
        if ($isRailway) {
            $remoteTarget = [
                'host' => '',
                'port' => 3306,
                'db'   => '',
                'user' => '',
                'pass' => '',
            ];

            // AGGRESSIVE RESOLUTION: Check all possible host sources
            $hostCandidates = [
                mf_env_first(['MYSQLHOST', 'DB_HOST']),
                mf_env_first(['RAILWAY_PRIVATE_DOMAIN']),
                'mysql.railway.internal' // Final internal fallback
            ];
            
            foreach ($hostCandidates as $h) {
                if ($h !== null && trim($h) !== '') {
                    $remoteTarget['host'] = $h;
                    break;
                }
            }

            $databaseUrl = mf_env_first(['DATABASE_URL', 'MYSQL_URL', 'MYSQL_PRIVATE_URL', 'MYSQL_PUBLIC_URL']);
            if ($databaseUrl !== null) {
                $parsedTarget = mf_database_target_from_url($databaseUrl);
                if ($parsedTarget !== null) {
                    // If URL provides a host, use it
                    $remoteTarget = array_merge($remoteTarget, $parsedTarget);
                }
            }

            $envOverrides = [
                'port' => mf_env_first(['MYSQLPORT', 'DB_PORT']),
                'db'   => mf_env_first(['MYSQLDATABASE', 'DB_NAME']),
                'user' => mf_env_first(['MYSQLUSER', 'DB_USER']),
                'pass' => mf_env_first(['MYSQLPASSWORD', 'MYSQL_ROOT_PASSWORD', 'DB_PASSWORD']),
            ];

            foreach ($envOverrides as $key => $value) {
                if ($value !== null && trim((string)$value) !== '') {
                    $remoteTarget[$key] = $key === 'port' ? (int) $value : $value;
                }
            }

            // EXTRA FORCE: If we have a password but it's not in the target yet, put it there
            if (empty($remoteTarget['pass'])) {
                $remoteTarget['pass'] = mf_env_first(['MYSQLPASSWORD', 'MYSQL_ROOT_PASSWORD']);
            }
            
            // Add public proxy fallback if available
            $publicUrl = mf_env_first(['MYSQL_PUBLIC_URL']);
            if ($publicUrl !== null) {
                $publicTarget = mf_database_target_from_url($publicUrl);
                if ($publicTarget !== null) {
                    $targets = [$remoteTarget, $publicTarget];
                } else {
                    $targets = [$remoteTarget];
                }
            } else {
                $targets = [$remoteTarget];
            }
        } else {
            $remoteTarget = mf_resolve_public_db_target();
        }

        // Build final targets array based on user preference
        if ($remoteTarget !== null && ($dbMode === 'remote' || $isRailway)) {
            // Priority to remote if explicitly requested OR if we are in the cloud (Railway)
            $targets = [$remoteTarget];
            if ($dbMode === 'auto') {
                $targets = array_merge($targets, $localTargets);
            }
        } else {
            // Default (Local Development): Local targets first, then remote fallback
            $targets = $localTargets;
            if ($remoteTarget !== null) {
                $targets[] = $remoteTarget;
            }
        }

        return [
            'mode' => $isRailway ? 'railway' : $dbMode,
            'targets' => $targets,
        ];
    }
}
