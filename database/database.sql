-- Initial PostgreSQL schema for Proyecto_Empresa (Laravel + Supabase).
-- Run once against an empty Supabase project's public schema.
-- The migrations table entries are included so `php artisan migrate` does not
-- try to recreate these tables after this script has been applied.

BEGIN;

CREATE TABLE users (
    id BIGSERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    email_verified_at TIMESTAMP(0) WITHOUT TIME ZONE NULL,
    password VARCHAR(255) NOT NULL,
    two_factor_secret TEXT NULL,
    two_factor_recovery_codes TEXT NULL,
    two_factor_confirmed_at TIMESTAMP(0) WITHOUT TIME ZONE NULL,
    remember_token VARCHAR(100) NULL,
    created_at TIMESTAMP(0) WITHOUT TIME ZONE NULL,
    updated_at TIMESTAMP(0) WITHOUT TIME ZONE NULL
);

CREATE TABLE password_reset_tokens (
    email VARCHAR(255) PRIMARY KEY,
    token VARCHAR(255) NOT NULL,
    created_at TIMESTAMP(0) WITHOUT TIME ZONE NULL
);

CREATE TABLE sessions (
    id VARCHAR(255) PRIMARY KEY,
    user_id BIGINT NULL,
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    payload TEXT NOT NULL,
    last_activity INTEGER NOT NULL
);

CREATE INDEX sessions_user_id_index ON sessions (user_id);
CREATE INDEX sessions_last_activity_index ON sessions (last_activity);

CREATE TABLE cache (
    key VARCHAR(255) PRIMARY KEY,
    value TEXT NOT NULL,
    expiration BIGINT NOT NULL
);

CREATE INDEX cache_expiration_index ON cache (expiration);

CREATE TABLE cache_locks (
    key VARCHAR(255) PRIMARY KEY,
    owner VARCHAR(255) NOT NULL,
    expiration BIGINT NOT NULL
);

CREATE INDEX cache_locks_expiration_index ON cache_locks (expiration);

CREATE TABLE jobs (
    id BIGSERIAL PRIMARY KEY,
    queue VARCHAR(255) NOT NULL,
    payload TEXT NOT NULL,
    attempts SMALLINT NOT NULL,
    reserved_at INTEGER NULL,
    available_at INTEGER NOT NULL,
    created_at INTEGER NOT NULL
);

CREATE INDEX jobs_queue_index ON jobs (queue);

CREATE TABLE job_batches (
    id VARCHAR(255) PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    total_jobs INTEGER NOT NULL,
    pending_jobs INTEGER NOT NULL,
    failed_jobs INTEGER NOT NULL,
    failed_job_ids TEXT NOT NULL,
    options TEXT NULL,
    cancelled_at INTEGER NULL,
    created_at INTEGER NOT NULL,
    finished_at INTEGER NULL
);

CREATE TABLE failed_jobs (
    id BIGSERIAL PRIMARY KEY,
    uuid VARCHAR(255) NOT NULL UNIQUE,
    connection VARCHAR(255) NOT NULL,
    queue VARCHAR(255) NOT NULL,
    payload TEXT NOT NULL,
    exception TEXT NOT NULL,
    failed_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX failed_jobs_connection_queue_failed_at_index
    ON failed_jobs (connection, queue, failed_at);

CREATE TABLE passkeys (
    id BIGSERIAL PRIMARY KEY,
    user_id BIGINT NOT NULL,
    name VARCHAR(255) NOT NULL,
    credential_id VARCHAR(255) NOT NULL UNIQUE,
    credential JSON NOT NULL,
    last_used_at TIMESTAMP(0) WITHOUT TIME ZONE NULL,
    created_at TIMESTAMP(0) WITHOUT TIME ZONE NULL,
    updated_at TIMESTAMP(0) WITHOUT TIME ZONE NULL,
    CONSTRAINT passkeys_user_id_foreign
        FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
);

CREATE INDEX passkeys_user_id_index ON passkeys (user_id);

CREATE TABLE company_activities (
    id BIGSERIAL PRIMARY KEY,
    user_id BIGINT NOT NULL,
    description VARCHAR(280) NOT NULL,
    created_at TIMESTAMP(0) WITHOUT TIME ZONE NULL,
    updated_at TIMESTAMP(0) WITHOUT TIME ZONE NULL,
    CONSTRAINT company_activities_user_id_foreign
        FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
);

CREATE INDEX company_activities_created_at_index ON company_activities (created_at);

CREATE TABLE clients (
    id BIGSERIAL PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    company VARCHAR(180) NULL,
    email VARCHAR(255) NULL UNIQUE,
    phone VARCHAR(30) NULL,
    address VARCHAR(255) NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    created_at TIMESTAMP(0) WITHOUT TIME ZONE NULL,
    updated_at TIMESTAMP(0) WITHOUT TIME ZONE NULL
);

CREATE INDEX clients_status_index ON clients (status);

CREATE TABLE projects (
    id BIGSERIAL PRIMARY KEY,
    client_id BIGINT NOT NULL,
    name VARCHAR(180) NOT NULL,
    description TEXT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'planning',
    budget NUMERIC(12, 2) NULL,
    start_date DATE NULL,
    end_date DATE NULL,
    created_at TIMESTAMP(0) WITHOUT TIME ZONE NULL,
    updated_at TIMESTAMP(0) WITHOUT TIME ZONE NULL,
    CONSTRAINT projects_client_id_foreign
        FOREIGN KEY (client_id) REFERENCES clients (id) ON DELETE RESTRICT
);

CREATE INDEX projects_status_index ON projects (status);
CREATE INDEX projects_client_id_status_index ON projects (client_id, status);

CREATE TABLE migrations (
    id SERIAL PRIMARY KEY,
    migration VARCHAR(255) NOT NULL,
    batch INTEGER NOT NULL
);

INSERT INTO migrations (migration, batch) VALUES
    ('0001_01_01_000000_create_users_table', 1),
    ('0001_01_01_000001_create_cache_table', 1),
    ('0001_01_01_000002_create_jobs_table', 1),
    ('2024_01_01_000000_create_passkeys_table', 1),
    ('2025_08_14_170933_add_two_factor_columns_to_users_table', 1),
    ('2026_10_01_000000_create_company_activities_table', 1),
    ('2026_10_02_000000_create_clients_table', 1),
    ('2026_10_02_000001_create_projects_table', 1);

COMMIT;
