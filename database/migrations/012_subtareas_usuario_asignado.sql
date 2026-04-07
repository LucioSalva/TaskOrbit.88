-- ============================================================
-- Migration 012: subtareas.usuario_asignado_id
--
-- Adds an OPTIONAL per-subtask assignee column to `subtareas`.
-- When NULL, the subtask still inherits the responsible user from
-- the parent task (and from the project, transitively) via the
-- existing COALESCE pattern in vw_tareas / EstadoService /
-- bin/scheduler.php — full backwards compatibility.
--
-- When set, it overrides the inherited responsible.
--
-- Idempotent: safe to run multiple times.
-- ============================================================

-- 1) Column ----------------------------------------------------------------
ALTER TABLE subtareas
  ADD COLUMN IF NOT EXISTS usuario_asignado_id INT
    DEFAULT NULL
    REFERENCES usuarios(id) ON DELETE SET NULL;

COMMENT ON COLUMN subtareas.usuario_asignado_id IS
  'Responsable explícito de la subtarea. Si NULL, hereda de tarea/proyecto.';

-- 2) Partial index -----------------------------------------------------------
CREATE INDEX IF NOT EXISTS idx_subtareas_usuario
  ON subtareas(usuario_asignado_id)
  WHERE deleted_at IS NULL;

-- 3) View update -------------------------------------------------------------
-- Postgres CREATE OR REPLACE VIEW only allows APPENDING columns at the end,
-- not reordering. The live vw_subtareas exposes:
--   id, tarea_id, nombre, descripcion, prioridad, estado, completada,
--   fecha_inicio, fecha_fin, estimacion_minutos, created_by, created_at,
--   updated_at
-- We APPEND usuario_asignado_id and usuario_asignado_nombre at the end so
-- existing column order is preserved (no consumer breakage).
--
-- IMPORTANT: do NOT cast prioridad/estado to enum types — the live DB
-- stores them as varchar and the application reads them as plain strings.
CREATE OR REPLACE VIEW vw_subtareas AS
SELECT
  s.id,
  s.tarea_id,
  s.nombre,
  s.descripcion,
  s.prioridad,
  s.estado,
  CASE
    WHEN s.estado::text = 'terminada'::text THEN TRUE
    ELSE FALSE
  END                          AS completada,
  s.fecha_inicio,
  s.fecha_fin,
  s.estimacion_minutos,
  s.created_by,
  s.created_at,
  s.updated_at,
  s.usuario_asignado_id,
  u.nombre_completo            AS usuario_asignado_nombre
FROM subtareas s
LEFT JOIN usuarios u ON u.id = s.usuario_asignado_id
WHERE s.deleted_at IS NULL;

COMMENT ON VIEW vw_subtareas IS
  'Subtareas activas con responsable explícito (NULL = hereda de la tarea/proyecto)';
