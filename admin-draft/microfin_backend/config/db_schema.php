<?php

function mf_db_schema_note(array &$summary, string $bucket, string $message): void
{
    if (!isset($summary[$bucket])) {
        $summary[$bucket] = [];
    }

    $summary[$bucket][] = $message;
}

function mf_db_schema_safe_noop(\PDOException $error, array $safeNeedles): bool
{
    $message = $error->getMessage();

    foreach ($safeNeedles as $needle) {
        if (stripos($message, $needle) !== false) {
            return true;
        }
    }

    return false;
}

function mf_db_schema_exec(PDO $pdo, string $sql, string $label, array &$summary, array $safeNeedles = []): bool
{
    try {
        $pdo->exec($sql);
        mf_db_schema_note($summary, 'applied', $label);
        return true;
    } catch (\PDOException $error) {
        if (mf_db_schema_safe_noop($error, $safeNeedles)) {
            mf_db_schema_note($summary, 'skipped', $label);
            return false;
        }

        mf_db_schema_note($summary, 'warnings', $label . ': ' . $error->getMessage());
        return false;
    }
}

function mf_apply_db_schema_bootstrap(PDO $pdo): array
{
    $summary = [
        'applied' => [],
        'skipped' => [],
        'warnings' => [],
    ];

    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS tenant_website_content (
                tenant_id VARCHAR(50) PRIMARY KEY,
                layout_template ENUM('template1', 'template2', 'template3') DEFAULT 'template1',
                hero_title VARCHAR(255) NULL,
                hero_subtitle VARCHAR(255) NULL,
                hero_description TEXT NULL,
                hero_cta_text VARCHAR(100) DEFAULT 'Learn More',
                hero_cta_url VARCHAR(255) DEFAULT '#about',
                hero_image_path VARCHAR(500) NULL,
                about_heading VARCHAR(255) DEFAULT 'About Us',
                about_body TEXT NULL,
                about_image_path VARCHAR(500) NULL,
                services_heading VARCHAR(255) DEFAULT 'Our Services',
                services_json LONGTEXT NULL,
                contact_address TEXT NULL,
                contact_phone VARCHAR(100) NULL,
                contact_email VARCHAR(255) NULL,
                contact_hours VARCHAR(255) NULL,
                custom_css LONGTEXT NULL,
                meta_description VARCHAR(255) NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                CONSTRAINT fk_tenant_website_content_tenant
                    FOREIGN KEY (tenant_id) REFERENCES tenants(tenant_id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        mf_db_schema_note($summary, 'applied', 'Ensured tenant_website_content exists');

        $websiteContentColumnMigrations = [
            'tenant_website_content.hero_badge_text' => "ALTER TABLE tenant_website_content ADD COLUMN hero_badge_text VARCHAR(255) NULL AFTER hero_image_path",
            'tenant_website_content.stats_json' => "ALTER TABLE tenant_website_content ADD COLUMN stats_json LONGTEXT NULL AFTER services_json",
            'tenant_website_content.stats_heading' => "ALTER TABLE tenant_website_content ADD COLUMN stats_heading VARCHAR(255) NULL AFTER stats_json",
            'tenant_website_content.stats_subheading' => "ALTER TABLE tenant_website_content ADD COLUMN stats_subheading VARCHAR(255) NULL AFTER stats_heading",
            'tenant_website_content.stats_image_path' => "ALTER TABLE tenant_website_content ADD COLUMN stats_image_path VARCHAR(500) NULL AFTER stats_subheading",
            'tenant_website_content.footer_description' => "ALTER TABLE tenant_website_content ADD COLUMN footer_description TEXT NULL AFTER contact_hours",
            'tenant_website_content.website_data' => "ALTER TABLE tenant_website_content ADD COLUMN website_data JSON NULL COMMENT 'Stores hero, about, services, toggles, section_styles, and arrays' AFTER layout_template",
        ];

        foreach ($websiteContentColumnMigrations as $label => $sql) {
            mf_db_schema_exec($pdo, $sql, $label, $summary, [
                'Duplicate column name',
                'already exists',
                'Invalid default value',
            ]);
        }
    } catch (\PDOException $error) {
        mf_db_schema_note($summary, 'warnings', 'tenant_website_content bootstrap: ' . $error->getMessage());
    }

    mf_db_schema_exec(
        $pdo,
        "ALTER TABLE tenant_website_content MODIFY COLUMN layout_template VARCHAR(50) DEFAULT 'template1.php'",
        'tenant_website_content.layout_template varchar migration',
        $summary,
        [
            'Unknown table',
        ]
    );

    try {
        $columnAdded = mf_db_schema_exec(
            $pdo,
            "ALTER TABLE tenants ADD COLUMN setup_current_step INT DEFAULT 0 COMMENT 'Onboarding step: 0=password_reset, 1=billing, 2=branding, 3=website, 4=done'",
            'tenants.setup_current_step',
            $summary,
            [
                'Duplicate column name',
                'already exists',
            ]
        );

        if ($columnAdded) {
            $pdo->exec("
                UPDATE tenants t SET setup_current_step =
                    CASE
                        WHEN t.setup_completed = TRUE THEN 4
                        WHEN EXISTS (SELECT 1 FROM tenant_website_content w WHERE w.tenant_id = t.tenant_id) THEN 3
                        WHEN EXISTS (SELECT 1 FROM tenant_branding br WHERE br.tenant_id = t.tenant_id) THEN 2
                        WHEN EXISTS (SELECT 1 FROM tenant_billing_payment_methods b WHERE b.tenant_id = t.tenant_id) THEN 1
                        ELSE 0
                    END
            ");
            mf_db_schema_note($summary, 'applied', 'tenants.setup_current_step backfill');
        } else {
            mf_db_schema_note($summary, 'skipped', 'tenants.setup_current_step backfill');
        }
    } catch (\PDOException $error) {
        mf_db_schema_note($summary, 'warnings', 'tenants.setup_current_step backfill: ' . $error->getMessage());
    }

    mf_db_schema_exec(
        $pdo,
        "ALTER TABLE tenant_branding ADD COLUMN theme_border_color VARCHAR(10) DEFAULT '#e2e8f0' COMMENT 'Card border/divider color'",
        'tenant_branding.theme_border_color',
        $summary,
        [
            'Duplicate column name',
            'already exists',
        ]
    );
    mf_db_schema_exec(
        $pdo,
        "ALTER TABLE tenant_branding ADD COLUMN card_border_width TINYINT DEFAULT 1 COMMENT 'Card border width in px (0-3)'",
        'tenant_branding.card_border_width',
        $summary,
        [
            'Duplicate column name',
            'already exists',
        ]
    );
    mf_db_schema_exec(
        $pdo,
        "ALTER TABLE tenant_branding ADD COLUMN card_shadow VARCHAR(10) DEFAULT 'sm' COMMENT 'Card shadow: none, sm, md, lg'",
        'tenant_branding.card_shadow',
        $summary,
        [
            'Duplicate column name',
            'already exists',
        ]
    );

    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS mobile_install_attributions (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                tracking_token VARCHAR(64) NOT NULL UNIQUE,
                tenant_id VARCHAR(50) NOT NULL,
                tenant_slug VARCHAR(100) NOT NULL,
                ip_address VARCHAR(45) NOT NULL,
                user_agent_hash VARCHAR(64) NOT NULL,
                user_agent TEXT NULL,
                platform_hint VARCHAR(32) NOT NULL DEFAULT 'unknown',
                referer_url VARCHAR(500) NULL,
                claimed_at DATETIME NULL,
                claimed_ip_address VARCHAR(45) NULL,
                claimed_platform_hint VARCHAR(32) NULL,
                claimed_user_agent TEXT NULL,
                last_seen_at DATETIME NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                expires_at DATETIME NOT NULL,
                INDEX idx_mobile_install_lookup (ip_address, platform_hint, claimed_at, expires_at, created_at),
                INDEX idx_mobile_install_tenant (tenant_id, created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        mf_db_schema_note($summary, 'applied', 'Ensured mobile_install_attributions exists');
    } catch (\PDOException $error) {
        mf_db_schema_note($summary, 'warnings', 'mobile_install_attributions bootstrap: ' . $error->getMessage());
    }

    mf_db_schema_exec(
        $pdo,
        "ALTER TABLE mobile_install_attributions CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci",
        'mobile_install_attributions charset alignment',
        $summary,
        [
            'Unknown table',
        ]
    );

    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS user_sessions (
                session_id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                tenant_id VARCHAR(50) NULL,
                session_token VARCHAR(255) NOT NULL,
                ip_address VARCHAR(45) NULL,
                user_agent TEXT NULL,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                last_activity_at DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
                expires_at DATETIME NOT NULL,
                UNIQUE KEY uq_user_sessions_token (session_token),
                KEY idx_user_sessions_user (user_id),
                KEY idx_user_sessions_tenant (tenant_id),
                KEY idx_user_sessions_expires (expires_at),
                CONSTRAINT fk_user_sessions_user
                    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
                CONSTRAINT fk_user_sessions_tenant
                    FOREIGN KEY (tenant_id) REFERENCES tenants(tenant_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        mf_db_schema_note($summary, 'applied', 'Ensured user_sessions exists');
    } catch (\PDOException $error) {
        mf_db_schema_note($summary, 'warnings', 'user_sessions bootstrap: ' . $error->getMessage());
    }

    $userSessionMigrations = [
        'user_sessions.tenant_id nullable' => "ALTER TABLE user_sessions MODIFY COLUMN tenant_id VARCHAR(50) NULL",
        'user_sessions.user_agent' => "ALTER TABLE user_sessions ADD COLUMN user_agent TEXT NULL AFTER ip_address",
        'user_sessions.last_activity_at' => "ALTER TABLE user_sessions ADD COLUMN last_activity_at DATETIME NULL DEFAULT CURRENT_TIMESTAMP AFTER created_at",
    ];

    foreach ($userSessionMigrations as $label => $sql) {
        mf_db_schema_exec($pdo, $sql, $label, $summary, [
            'Duplicate column name',
            'already exists',
            'Invalid default value',
            'Unknown table',
        ]);
    }

    return $summary;
}
