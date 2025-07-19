CREATE TABLE tenants (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name          VARCHAR(120) NOT NULL,
    slug          VARCHAR(80)  NOT NULL UNIQUE,
    logo_path     VARCHAR(255),
    color_primary CHAR(7),
    color_accent  CHAR(7)
);

CREATE TABLE users (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id  INT UNSIGNED NOT NULL,
    email      VARCHAR(190)  NOT NULL,
    password   CHAR(60)      NOT NULL,
    role       ENUM('admin','user') DEFAULT 'user',
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    UNIQUE KEY uniq_email_tenant (email, tenant_id)
);

CREATE TABLE files (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id  INT UNSIGNED NOT NULL,
    drive_id   VARCHAR(128),      -- Google/Dropbox identifier
    filename   VARCHAR(255) NOT NULL,
    mime_type  VARCHAR(120),
    size       INT UNSIGNED,
    tags       TEXT,
    description TEXT,
    downloads  INT UNSIGNED DEFAULT 0,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
);