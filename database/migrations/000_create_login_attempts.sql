-- Migración 000: Crear tabla login_attempts
-- Requerida por LoginRateLimiter para control de intentos fallidos
-- Ejecutar: psql -U postgres -d TaskOrbit -f database/migrations/000_create_login_attempts.sql

CREATE TABLE IF NOT EXISTS login_attempts (
    id           SERIAL PRIMARY KEY,
    identifier   VARCHAR(150) NOT NULL,
    ip_address   VARCHAR(45)  NOT NULL,
    success      BOOLEAN      NOT NULL DEFAULT FALSE,
    attempted_at TIMESTAMPTZ  NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_login_attempts_identifier
    ON login_attempts (identifier, attempted_at);

CREATE INDEX IF NOT EXISTS idx_login_attempts_ip
    ON login_attempts (ip_address, attempted_at);
