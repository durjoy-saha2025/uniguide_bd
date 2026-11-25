-- UniGuide BD initial schema for MySQL 8.0+
-- This file creates structure only. It intentionally contains no demo records.

CREATE TABLE IF NOT EXISTS users (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(150) NOT NULL,
    email VARCHAR(254) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('student', 'admin', 'super_admin') NOT NULL DEFAULT 'student',
    account_status ENUM('active', 'suspended', 'deleted') NOT NULL DEFAULT 'active',
    email_verified_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_email (email),
    KEY idx_users_role_status (role, account_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sources (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    title VARCHAR(255) NOT NULL,
    source_type VARCHAR(80) NOT NULL,
    url VARCHAR(2048) NOT NULL,
    publisher VARCHAR(180) NULL,
    notes TEXT NULL,
    verification_status ENUM('pending', 'verified', 'rejected') NOT NULL DEFAULT 'pending',
    verified_at DATETIME NULL,
    created_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_sources_verification (verification_status),
    CONSTRAINT fk_sources_created_by FOREIGN KEY (created_by)
        REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS universities (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    slug VARCHAR(120) NOT NULL,
    name VARCHAR(200) NOT NULL,
    summary VARCHAR(500) NULL,
    description MEDIUMTEXT NULL,
    city VARCHAR(100) NULL,
    address VARCHAR(500) NULL,
    campus_type VARCHAR(80) NULL,
    website_url VARCHAR(2048) NULL,
    admissions_url VARCHAR(2048) NULL,
    logo_url VARCHAR(2048) NULL,
    cover_image_url VARCHAR(2048) NULL,
    source_id BIGINT UNSIGNED NULL,
    ugc_status ENUM('unknown', 'pending', 'verified', 'not_verified') NOT NULL DEFAULT 'unknown',
    verification_status ENUM('draft', 'pending', 'verified', 'rejected') NOT NULL DEFAULT 'draft',
    verified_at DATETIME NULL,
    status ENUM('draft', 'published', 'archived') NOT NULL DEFAULT 'draft',
    created_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_universities_slug (slug),
    KEY idx_universities_public (status, name),
    KEY idx_universities_city (city),
    CONSTRAINT fk_universities_created_by FOREIGN KEY (created_by)
        REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT fk_universities_source FOREIGN KEY (source_id)
        REFERENCES sources (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS programs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    university_id BIGINT UNSIGNED NOT NULL,
    slug VARCHAR(140) NOT NULL,
    name VARCHAR(200) NOT NULL,
    degree_level VARCHAR(100) NULL,
    field_of_study VARCHAR(120) NULL,
    duration_months SMALLINT UNSIGNED NULL,
    description TEXT NULL,
    verification_status ENUM('draft', 'pending', 'verified', 'rejected') NOT NULL DEFAULT 'draft',
    verified_at DATETIME NULL,
    status ENUM('draft', 'published', 'archived') NOT NULL DEFAULT 'draft',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_programs_university_slug (university_id, slug),
    KEY idx_programs_public (status, field_of_study),
    CONSTRAINT fk_programs_university FOREIGN KEY (university_id)
        REFERENCES universities (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS program_fees (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    program_id BIGINT UNSIGNED NOT NULL,
    source_id BIGINT UNSIGNED NULL,
    fee_type ENUM('tuition', 'admission', 'semester', 'lab', 'other') NOT NULL,
    label VARCHAR(180) NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    currency CHAR(3) NOT NULL DEFAULT 'BDT',
    effective_from DATE NULL,
    effective_until DATE NULL,
    verification_status ENUM('pending', 'verified', 'rejected') NOT NULL DEFAULT 'pending',
    verified_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_program_fees_program (program_id, verification_status),
    CONSTRAINT chk_program_fees_amount CHECK (amount >= 0),
    CONSTRAINT fk_program_fees_program FOREIGN KEY (program_id)
        REFERENCES programs (id) ON DELETE CASCADE,
    CONSTRAINT fk_program_fees_source FOREIGN KEY (source_id)
        REFERENCES sources (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admission_requirements (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    program_id BIGINT UNSIGNED NOT NULL,
    source_id BIGINT UNSIGNED NULL,
    minimum_ssc_gpa DECIMAL(3,2) NULL,
    minimum_hsc_gpa DECIMAL(3,2) NULL,
    minimum_combined_gpa DECIMAL(4,2) NULL,
    accepted_ssc_groups JSON NULL,
    accepted_hsc_groups JSON NULL,
    subject_prerequisites JSON NULL,
    admission_test_required BOOLEAN NOT NULL DEFAULT FALSE,
    additional_requirements TEXT NULL,
    verification_status ENUM('pending', 'verified', 'rejected') NOT NULL DEFAULT 'pending',
    verified_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_admission_requirements_program (program_id, verification_status),
    CONSTRAINT chk_minimum_ssc_gpa CHECK (minimum_ssc_gpa IS NULL OR minimum_ssc_gpa BETWEEN 0 AND 5),
    CONSTRAINT chk_minimum_hsc_gpa CHECK (minimum_hsc_gpa IS NULL OR minimum_hsc_gpa BETWEEN 0 AND 5),
    CONSTRAINT chk_minimum_combined_gpa CHECK (minimum_combined_gpa IS NULL OR minimum_combined_gpa BETWEEN 0 AND 10),
    CONSTRAINT fk_admission_requirements_program FOREIGN KEY (program_id)
        REFERENCES programs (id) ON DELETE CASCADE,
    CONSTRAINT fk_admission_requirements_source FOREIGN KEY (source_id)
        REFERENCES sources (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS scholarships (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    university_id BIGINT UNSIGNED NOT NULL,
    program_id BIGINT UNSIGNED NULL,
    source_id BIGINT UNSIGNED NULL,
    title VARCHAR(200) NOT NULL,
    description TEXT NULL,
    award_type ENUM('percentage', 'fixed_amount', 'other') NOT NULL,
    award_value DECIMAL(12,2) NULL,
    eligibility_rules JSON NULL,
    conditions TEXT NULL,
    verification_status ENUM('pending', 'verified', 'rejected') NOT NULL DEFAULT 'pending',
    verified_at DATETIME NULL,
    status ENUM('draft', 'published', 'archived') NOT NULL DEFAULT 'draft',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_scholarships_public (university_id, status, verification_status),
    CONSTRAINT chk_scholarship_award_value CHECK (award_value IS NULL OR award_value >= 0),
    CONSTRAINT fk_scholarships_university FOREIGN KEY (university_id)
        REFERENCES universities (id) ON DELETE CASCADE,
    CONSTRAINT fk_scholarships_program FOREIGN KEY (program_id)
        REFERENCES programs (id) ON DELETE SET NULL,
    CONSTRAINT fk_scholarships_source FOREIGN KEY (source_id)
        REFERENCES sources (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rankings (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    university_id BIGINT UNSIGNED NOT NULL,
    source_id BIGINT UNSIGNED NULL,
    publisher VARCHAR(180) NOT NULL,
    edition VARCHAR(100) NOT NULL,
    publication_date DATE NULL,
    rank_label VARCHAR(80) NULL,
    verification_status ENUM('pending', 'verified', 'rejected') NOT NULL DEFAULT 'pending',
    verified_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_rankings_edition (university_id, publisher, edition),
    KEY idx_rankings_publisher_edition (publisher, edition),
    CONSTRAINT fk_rankings_university FOREIGN KEY (university_id)
        REFERENCES universities (id) ON DELETE CASCADE,
    CONSTRAINT fk_rankings_source FOREIGN KEY (source_id)
        REFERENCES sources (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS news_updates (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    university_id BIGINT UNSIGNED NULL,
    source_id BIGINT UNSIGNED NULL,
    slug VARCHAR(180) NOT NULL,
    title VARCHAR(240) NOT NULL,
    category VARCHAR(100) NOT NULL,
    summary VARCHAR(600) NULL,
    body MEDIUMTEXT NULL,
    source_published_at DATETIME NULL,
    workflow_status ENUM('draft', 'review', 'approved', 'published', 'archived') NOT NULL DEFAULT 'draft',
    verification_status ENUM('pending', 'verified', 'rejected') NOT NULL DEFAULT 'pending',
    verified_at DATETIME NULL,
    created_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_news_updates_slug (slug),
    KEY idx_news_public (workflow_status, source_published_at),
    CONSTRAINT fk_news_university FOREIGN KEY (university_id)
        REFERENCES universities (id) ON DELETE SET NULL,
    CONSTRAINT fk_news_source FOREIGN KEY (source_id)
        REFERENCES sources (id) ON DELETE SET NULL,
    CONSTRAINT fk_news_created_by FOREIGN KEY (created_by)
        REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS student_guides (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    slug VARCHAR(180) NOT NULL,
    title VARCHAR(240) NOT NULL,
    category VARCHAR(100) NOT NULL,
    summary VARCHAR(600) NULL,
    body MEDIUMTEXT NOT NULL,
    seo_title VARCHAR(240) NULL,
    seo_description VARCHAR(500) NULL,
    status ENUM('draft', 'published', 'archived') NOT NULL DEFAULT 'draft',
    published_at DATETIME NULL,
    created_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_student_guides_slug (slug),
    KEY idx_student_guides_public (status, published_at),
    CONSTRAINT fk_student_guides_created_by FOREIGN KEY (created_by)
        REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS student_profiles (
    user_id BIGINT UNSIGNED NOT NULL,
    ssc_gpa DECIMAL(3,2) NULL,
    ssc_group VARCHAR(60) NULL,
    ssc_passing_year SMALLINT UNSIGNED NULL,
    hsc_gpa DECIMAL(3,2) NULL,
    hsc_group VARCHAR(60) NULL,
    hsc_passing_year SMALLINT UNSIGNED NULL,
    equivalent_qualification JSON NULL,
    preferred_subject VARCHAR(120) NULL,
    preferred_location VARCHAR(100) NULL,
    maximum_budget DECIMAL(12,2) NULL,
    scholarship_preference BOOLEAN NULL,
    campus_preference VARCHAR(80) NULL,
    admission_test_preference ENUM('unknown', 'required', 'preferred', 'not_required') NOT NULL DEFAULT 'unknown',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id),
    CONSTRAINT chk_student_ssc_gpa CHECK (ssc_gpa IS NULL OR ssc_gpa BETWEEN 0 AND 5),
    CONSTRAINT chk_student_hsc_gpa CHECK (hsc_gpa IS NULL OR hsc_gpa BETWEEN 0 AND 5),
    CONSTRAINT chk_student_budget CHECK (maximum_budget IS NULL OR maximum_budget >= 0),
    CONSTRAINT fk_student_profiles_user FOREIGN KEY (user_id)
        REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS password_reset_tokens (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    token_hash CHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    used_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_password_reset_token_hash (token_hash),
    KEY idx_password_reset_user_expiry (user_id, expires_at),
    CONSTRAINT fk_password_reset_user FOREIGN KEY (user_id)
        REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS saved_universities (
    user_id BIGINT UNSIGNED NOT NULL,
    university_id BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, university_id),
    KEY idx_saved_universities_university (university_id),
    CONSTRAINT fk_saved_universities_user FOREIGN KEY (user_id)
        REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_saved_universities_university FOREIGN KEY (university_id)
        REFERENCES universities (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS student_comparison_lists (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(120) NOT NULL DEFAULT 'My comparison',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_comparison_lists_user (user_id, updated_at),
    CONSTRAINT fk_comparison_lists_user FOREIGN KEY (user_id)
        REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS student_comparison_items (
    comparison_id BIGINT UNSIGNED NOT NULL,
    university_id BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (comparison_id, university_id),
    CONSTRAINT fk_comparison_items_list FOREIGN KEY (comparison_id)
        REFERENCES student_comparison_lists (id) ON DELETE CASCADE,
    CONSTRAINT fk_comparison_items_university FOREIGN KEY (university_id)
        REFERENCES universities (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admin_activity_logs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    actor_user_id BIGINT UNSIGNED NULL,
    action VARCHAR(100) NOT NULL,
    entity_type VARCHAR(100) NOT NULL,
    entity_id BIGINT UNSIGNED NULL,
    details JSON NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_admin_activity_actor (actor_user_id, created_at),
    KEY idx_admin_activity_entity (entity_type, entity_id, created_at),
    CONSTRAINT fk_admin_activity_actor FOREIGN KEY (actor_user_id)
        REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
