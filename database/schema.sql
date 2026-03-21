-- ============================================================
-- TaskOrbit — PostgreSQL Schema
-- Motor: PostgreSQL 14+
-- Charset: UTF8
-- ============================================================

-- Extensiones
CREATE EXTENSION IF NOT EXISTS pgcrypto;

-- ============================================================
-- ENUM types
-- ============================================================
DO $$ BEGIN
  CREATE TYPE estado_tipo AS ENUM (
    'por_hacer','haciendo','terminada','enterado','ocupado','aceptada'
  );
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;

DO $$ BEGIN
  CREATE TYPE prioridad_tipo AS ENUM ('baja','media','alta','critica');
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;

-- ============================================================
-- TABLAS
-- ============================================================

-- roles
CREATE TABLE IF NOT EXISTS roles (
  id     SERIAL PRIMARY KEY,
  nombre VARCHAR(20) NOT NULL UNIQUE
);
COMMENT ON TABLE roles IS 'Roles del sistema: GOD, ADMIN, USER';

-- usuarios
CREATE TABLE IF NOT EXISTS usuarios (
  id              SERIAL PRIMARY KEY,
  nombre_completo VARCHAR(120) NOT NULL,
  username        VARCHAR(60)  NOT NULL UNIQUE,
  telefono        VARCHAR(20)  DEFAULT NULL,
  password_hash   VARCHAR(255) NOT NULL,
  activo          BOOLEAN      NOT NULL DEFAULT TRUE,
  created_at      TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
  updated_at      TIMESTAMPTZ  NOT NULL DEFAULT NOW()
);
COMMENT ON TABLE usuarios IS 'Usuarios del sistema';

-- usuarios_roles (relación N-1 en la práctica, cada usuario tiene un rol)
CREATE TABLE IF NOT EXISTS usuarios_roles (
  id          SERIAL PRIMARY KEY,
  usuario_id  INT NOT NULL REFERENCES usuarios(id) ON DELETE CASCADE,
  rol_id      INT NOT NULL REFERENCES roles(id)    ON DELETE RESTRICT,
  UNIQUE (usuario_id)
);
COMMENT ON TABLE usuarios_roles IS 'Asignación de rol a cada usuario (uno a uno)';

-- proyectos
CREATE TABLE IF NOT EXISTS proyectos (
  id                   SERIAL         PRIMARY KEY,
  nombre               VARCHAR(120)   NOT NULL,
  descripcion          TEXT           DEFAULT NULL,
  prioridad            prioridad_tipo NOT NULL DEFAULT 'media',
  estado               estado_tipo    NOT NULL DEFAULT 'por_hacer',
  fecha_inicio         DATE           DEFAULT NULL,
  fecha_fin            DATE           DEFAULT NULL,
  estimacion_minutos   INT            DEFAULT NULL,
  usuario_asignado_id  INT            DEFAULT NULL REFERENCES usuarios(id) ON DELETE SET NULL,
  created_by           INT            NOT NULL     REFERENCES usuarios(id) ON DELETE RESTRICT,
  deleted_at           TIMESTAMPTZ    DEFAULT NULL,
  created_at           TIMESTAMPTZ    NOT NULL DEFAULT NOW(),
  updated_at           TIMESTAMPTZ    NOT NULL DEFAULT NOW()
);
COMMENT ON TABLE proyectos IS 'Proyectos del sistema (soft-delete con deleted_at)';

-- tareas
CREATE TABLE IF NOT EXISTS tareas (
  id                   SERIAL         PRIMARY KEY,
  proyecto_id          INT            NOT NULL REFERENCES proyectos(id) ON DELETE CASCADE,
  nombre               VARCHAR(200)   NOT NULL,
  descripcion          TEXT           DEFAULT NULL,
  prioridad            prioridad_tipo NOT NULL DEFAULT 'media',
  estado               estado_tipo    NOT NULL DEFAULT 'por_hacer',
  fecha_inicio         DATE           DEFAULT NULL,
  fecha_fin            DATE           DEFAULT NULL,
  estimacion_minutos   INT            DEFAULT NULL,
  usuario_asignado_id  INT            DEFAULT NULL REFERENCES usuarios(id) ON DELETE SET NULL,
  created_by           INT            NOT NULL     REFERENCES usuarios(id) ON DELETE RESTRICT,
  deleted_at           TIMESTAMPTZ    DEFAULT NULL,
  created_at           TIMESTAMPTZ    NOT NULL DEFAULT NOW(),
  updated_at           TIMESTAMPTZ    NOT NULL DEFAULT NOW()
);
COMMENT ON TABLE tareas IS 'Tareas vinculadas a proyectos (soft-delete con deleted_at)';

-- subtareas
CREATE TABLE IF NOT EXISTS subtareas (
  id          SERIAL         PRIMARY KEY,
  tarea_id    INT            NOT NULL REFERENCES tareas(id) ON DELETE CASCADE,
  nombre      VARCHAR(200)   NOT NULL,
  descripcion TEXT           DEFAULT NULL,
  prioridad   prioridad_tipo NOT NULL DEFAULT 'media',
  estado      estado_tipo    NOT NULL DEFAULT 'por_hacer',
  fecha_inicio DATE          DEFAULT NULL,
  fecha_fin    DATE          DEFAULT NULL,
  created_by  INT            NOT NULL REFERENCES usuarios(id) ON DELETE RESTRICT,
  deleted_at  TIMESTAMPTZ    DEFAULT NULL,
  created_at  TIMESTAMPTZ    NOT NULL DEFAULT NOW(),
  updated_at  TIMESTAMPTZ    NOT NULL DEFAULT NOW()
);
COMMENT ON TABLE subtareas IS 'Subtareas vinculadas a tareas (soft-delete con deleted_at)';

-- notas
CREATE TABLE IF NOT EXISTS notas (
  id           SERIAL       PRIMARY KEY,
  scope        VARCHAR(20)  NOT NULL CHECK (scope IN ('personal','proyecto','tarea','subtarea')),
  referencia_id INT         DEFAULT NULL,
  user_id      INT          NOT NULL REFERENCES usuarios(id) ON DELETE CASCADE,
  titulo       VARCHAR(160) DEFAULT NULL,
  contenido    TEXT         NOT NULL,
  tipo         VARCHAR(30)  DEFAULT 'personal',
  deleted_at   TIMESTAMPTZ  DEFAULT NULL,
  created_at   TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
  updated_at   TIMESTAMPTZ  NOT NULL DEFAULT NOW()
);
COMMENT ON TABLE notas IS 'Notas personales y de proyecto/tarea/subtarea';

-- notifications
CREATE TABLE IF NOT EXISTS notifications (
  id           SERIAL      PRIMARY KEY,
  user_id      INT         NOT NULL REFERENCES usuarios(id) ON DELETE CASCADE,
  type         VARCHAR(60) NOT NULL DEFAULT 'info',
  title        VARCHAR(200) NOT NULL,
  message      TEXT        NOT NULL,
  severity     VARCHAR(20) NOT NULL DEFAULT 'info' CHECK (severity IN ('info','success','warning','danger')),
  channel      VARCHAR(20) NOT NULL DEFAULT 'in_app' CHECK (channel IN ('in_app','whatsapp','email')),
  entity_type  VARCHAR(40) DEFAULT NULL,
  entity_id    INT         DEFAULT NULL,
  read         BOOLEAN     NOT NULL DEFAULT FALSE,
  delivered_at TIMESTAMPTZ DEFAULT NULL,
  status       VARCHAR(20) NOT NULL DEFAULT 'pending',
  created_at   TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
COMMENT ON TABLE notifications IS 'Notificaciones en la app y canales externos';

-- audit_logs
CREATE TABLE IF NOT EXISTS audit_logs (
  id          SERIAL      PRIMARY KEY,
  actor_id    INT         DEFAULT NULL REFERENCES usuarios(id) ON DELETE SET NULL,
  action      VARCHAR(60) NOT NULL,
  target_id   INT         DEFAULT NULL,
  details     JSONB       DEFAULT NULL,
  ip_address  VARCHAR(45) DEFAULT NULL,
  method      VARCHAR(10) DEFAULT NULL,
  endpoint    VARCHAR(300) DEFAULT NULL,
  created_at  TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
COMMENT ON TABLE audit_logs IS 'Registro de auditoría de acciones del sistema';

-- ============================================================
-- ÍNDICES
-- ============================================================
CREATE INDEX IF NOT EXISTS idx_proyectos_estado         ON proyectos(estado)            WHERE deleted_at IS NULL;
CREATE INDEX IF NOT EXISTS idx_proyectos_usuario        ON proyectos(usuario_asignado_id) WHERE deleted_at IS NULL;
CREATE INDEX IF NOT EXISTS idx_proyectos_created_by     ON proyectos(created_by)         WHERE deleted_at IS NULL;
CREATE INDEX IF NOT EXISTS idx_proyectos_deleted        ON proyectos(deleted_at);

CREATE INDEX IF NOT EXISTS idx_tareas_proyecto          ON tareas(proyecto_id)           WHERE deleted_at IS NULL;
CREATE INDEX IF NOT EXISTS idx_tareas_estado            ON tareas(estado)                WHERE deleted_at IS NULL;
CREATE INDEX IF NOT EXISTS idx_tareas_usuario           ON tareas(usuario_asignado_id)   WHERE deleted_at IS NULL;
CREATE INDEX IF NOT EXISTS idx_tareas_deleted           ON tareas(deleted_at);

CREATE INDEX IF NOT EXISTS idx_subtareas_tarea          ON subtareas(tarea_id)           WHERE deleted_at IS NULL;
CREATE INDEX IF NOT EXISTS idx_subtareas_estado         ON subtareas(estado)             WHERE deleted_at IS NULL;
CREATE INDEX IF NOT EXISTS idx_subtareas_deleted        ON subtareas(deleted_at);

CREATE INDEX IF NOT EXISTS idx_notas_scope_ref         ON notas(scope, referencia_id)   WHERE deleted_at IS NULL;
CREATE INDEX IF NOT EXISTS idx_notas_user              ON notas(user_id)                WHERE deleted_at IS NULL;

CREATE INDEX IF NOT EXISTS idx_notifications_user      ON notifications(user_id, read);
CREATE INDEX IF NOT EXISTS idx_audit_actor             ON audit_logs(actor_id, created_at DESC);

-- ============================================================
-- TRIGGER: actualizar updated_at automáticamente
-- ============================================================
CREATE OR REPLACE FUNCTION fn_set_updated_at()
RETURNS TRIGGER LANGUAGE plpgsql AS $$
BEGIN
  NEW.updated_at = NOW();
  RETURN NEW;
END;
$$;

DO $$ DECLARE t TEXT;
BEGIN
  FOREACH t IN ARRAY ARRAY['usuarios','proyectos','tareas','subtareas','notas']
  LOOP
    EXECUTE format(
      'CREATE TRIGGER trg_%I_updated_at
       BEFORE UPDATE ON %I
       FOR EACH ROW EXECUTE FUNCTION fn_set_updated_at()',
      t, t
    );
  END LOOP;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;

-- ============================================================
-- VISTAS
-- ============================================================

-- vw_login: usada por AuthController para autenticación
CREATE OR REPLACE VIEW vw_login AS
SELECT
  u.id,
  u.username,
  u.password_hash,
  u.nombre_completo,
  u.telefono,
  u.activo,
  r.nombre AS rol
FROM usuarios u
JOIN usuarios_roles ur ON ur.usuario_id = u.id
JOIN roles r           ON r.id = ur.rol_id;

COMMENT ON VIEW vw_login IS 'Vista para login: usuarios con su rol';

-- vw_usuarios_roles: usada por UsuariosController y listados
CREATE OR REPLACE VIEW vw_usuarios_roles AS
SELECT
  u.id,
  u.username,
  u.nombre_completo,
  u.telefono,
  u.activo,
  u.created_at,
  r.nombre AS rol
FROM usuarios u
JOIN usuarios_roles ur ON ur.usuario_id = u.id
JOIN roles r           ON r.id = ur.rol_id;

COMMENT ON VIEW vw_usuarios_roles IS 'Usuarios con su rol asignado';

-- vw_proyectos: usada en ProyectosController, DashboardController y NotasController
CREATE OR REPLACE VIEW vw_proyectos AS
SELECT
  p.id,
  p.nombre,
  p.descripcion,
  p.prioridad::TEXT  AS prioridad,
  p.estado::TEXT     AS estado,
  p.fecha_inicio,
  p.fecha_fin,
  p.estimacion_minutos,
  p.usuario_asignado_id,
  u.nombre_completo  AS usuario_asignado_nombre,
  p.created_by,
  p.created_at,
  p.updated_at
FROM proyectos p
LEFT JOIN usuarios u ON u.id = p.usuario_asignado_id
WHERE p.deleted_at IS NULL;

COMMENT ON VIEW vw_proyectos IS 'Proyectos activos con nombre del usuario asignado';

-- vw_tareas: usada en ProyectosController::show, DashboardController y NotasController
CREATE OR REPLACE VIEW vw_tareas AS
SELECT
  t.id,
  t.proyecto_id,
  t.nombre,
  t.descripcion,
  t.prioridad::TEXT  AS prioridad,
  t.estado::TEXT     AS estado,
  t.fecha_inicio,
  t.fecha_fin,
  t.estimacion_minutos,
  COALESCE(t.usuario_asignado_id, p.usuario_asignado_id) AS usuario_asignado_id,
  COALESCE(ut.nombre_completo, up.nombre_completo)       AS usuario_asignado_nombre,
  t.created_by,
  p.created_by       AS proyecto_created_by,
  t.created_at,
  t.updated_at
FROM tareas t
JOIN proyectos p        ON p.id = t.proyecto_id
LEFT JOIN usuarios ut   ON ut.id = t.usuario_asignado_id
LEFT JOIN usuarios up   ON up.id = p.usuario_asignado_id
WHERE t.deleted_at IS NULL
  AND p.deleted_at IS NULL;

COMMENT ON VIEW vw_tareas IS 'Tareas activas con usuario efectivo resuelto';

-- vw_subtareas: usada por SubtareasController y listados de tareas
CREATE OR REPLACE VIEW vw_subtareas AS
SELECT
  s.id,
  s.tarea_id,
  s.nombre,
  s.descripcion,
  s.prioridad::TEXT AS prioridad,
  s.estado::TEXT    AS estado,
  s.fecha_inicio,
  s.fecha_fin,
  s.created_by,
  s.created_at,
  s.updated_at
FROM subtareas s
WHERE s.deleted_at IS NULL;

COMMENT ON VIEW vw_subtareas IS 'Subtareas activas (sin eliminadas)';

-- ============================================================
-- TABLA: login_attempts (rate limiting basado en DB)
-- ============================================================
CREATE TABLE IF NOT EXISTS login_attempts (
    id           SERIAL       PRIMARY KEY,
    identifier   VARCHAR(255) NOT NULL,   -- username o email del intento
    ip_address   VARCHAR(45),             -- IPv4 / IPv6
    attempted_at TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
    success      BOOLEAN      NOT NULL DEFAULT FALSE
);

CREATE INDEX IF NOT EXISTS idx_login_attempts_identifier   ON login_attempts(identifier);
CREATE INDEX IF NOT EXISTS idx_login_attempts_attempted_at ON login_attempts(attempted_at);
CREATE INDEX IF NOT EXISTS idx_login_attempts_ip           ON login_attempts(ip_address);
