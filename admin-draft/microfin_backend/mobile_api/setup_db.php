<?php
require_once __DIR__ . '/../config/db.php';

$sql = "
CREATE TABLE IF NOT EXISTS tenants (
    tenant_id VARCHAR(50) PRIMARY KEY,
    tenant_name VARCHAR(100) NOT NULL,
    tenant_slug VARCHAR(50) UNIQUE
);

CREATE TABLE IF NOT EXISTS user_roles (
    role_id INT PRIMARY KEY AUTO_INCREMENT,
    tenant_id VARCHAR(50) NULL,
    role_name VARCHAR(50) NOT NULL,
    role_description TEXT,
    is_system_role BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (tenant_id) REFERENCES tenants(tenant_id) ON DELETE CASCADE,
    UNIQUE KEY uq_tenant_role (tenant_id, role_name)
);

CREATE TABLE IF NOT EXISTS users (
    user_id INT PRIMARY KEY AUTO_INCREMENT,
    tenant_id VARCHAR(50) NULL,
    username VARCHAR(50) NOT NULL,
    email VARCHAR(100) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role_id INT NOT NULL,
    user_type ENUM('Employee', 'Client', 'Super Admin', 'App User') NOT NULL,
    status ENUM('Active', 'Inactive', 'Suspended', 'Locked') DEFAULT 'Active',
    email_verified BOOLEAN DEFAULT FALSE,
    verification_token VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(tenant_id),
    FOREIGN KEY (role_id) REFERENCES user_roles(role_id)
);

CREATE TABLE IF NOT EXISTS clients (
    client_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    tenant_id VARCHAR(50) NOT NULL,
    client_code VARCHAR(20),
    first_name VARCHAR(50) NOT NULL,
    middle_name VARCHAR(50),
    last_name VARCHAR(50) NOT NULL,
    suffix VARCHAR(10),
    date_of_birth DATE NOT NULL,
    contact_number VARCHAR(20) NOT NULL,
    email_address VARCHAR(100),
    client_status ENUM('Active', 'Inactive', 'Blacklisted', 'Deceased') DEFAULT 'Active',
    registration_date DATE NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (tenant_id) REFERENCES tenants(tenant_id)
);

CREATE TABLE IF NOT EXISTS email_delivery_logs (
    email_log_id BIGINT PRIMARY KEY AUTO_INCREMENT,
    tenant_id VARCHAR(50) NULL,
    user_id INT NULL,
    email_type VARCHAR(50) NOT NULL,
    recipient_email VARCHAR(255) NOT NULL,
    recipient_name VARCHAR(255) NULL,
    subject VARCHAR(255) NOT NULL,
    provider VARCHAR(50) NOT NULL DEFAULT 'brevo',
    provider_message_id VARCHAR(255) NULL,
    status ENUM('sent', 'failed') NOT NULL,
    error_message TEXT NULL,
    request_payload LONGTEXT NULL,
    response_payload LONGTEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT IGNORE INTO tenants (tenant_id, tenant_name, tenant_slug) VALUES ('fundline', 'Fundline', 'fundline');
INSERT IGNORE INTO user_roles (role_id, tenant_id, role_name) VALUES (3, 'fundline', 'Client');
";

if ($conn->multi_query($sql)) {
    do {
        if ($res = $conn->store_result()) {
            $res->free();
        }
    } while ($conn->more_results() && $conn->next_result());
    echo "Base tables created successfully for Registration!\n";
} else {
    echo "Error inserting tables: " . $conn->error;
}
?>
