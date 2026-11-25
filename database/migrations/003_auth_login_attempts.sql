CREATE TABLE IF NOT EXISTS auth_login_attempts (
    identifier_hash CHAR(64) NOT NULL,
    failures SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    window_started_at DATETIME NOT NULL,
    blocked_until DATETIME NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (identifier_hash),
    KEY idx_auth_login_blocked_until (blocked_until),
    CONSTRAINT chk_auth_login_failures CHECK (failures <= 5)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
