-- Fix QA-011: Agregar columna en_riesgo a proyectos, tareas y actualizar vw_proyectos
-- La columna es escrita por EstadoService pero no estaba declarada en el schema base.
-- Fecha: 2026-03-25

-- 1. Agregar en_riesgo a proyectos (si no existe)
ALTER TABLE proyectos ADD COLUMN IF NOT EXISTS en_riesgo BOOLEAN NOT NULL DEFAULT FALSE;

-- 2. Agregar en_riesgo a tareas (si no existe — EstadoService también la escribe)
ALTER TABLE tareas ADD COLUMN IF NOT EXISTS en_riesgo BOOLEAN NOT NULL DEFAULT FALSE;

-- 3. Actualizar vw_proyectos para exponer en_riesgo
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
  p.en_riesgo,
  p.usuario_asignado_id,
  u.nombre_completo  AS usuario_asignado_nombre,
  p.created_by,
  p.created_at,
  p.updated_at
FROM proyectos p
LEFT JOIN usuarios u ON u.id = p.usuario_asignado_id
WHERE p.deleted_at IS NULL;

COMMENT ON VIEW vw_proyectos IS 'Proyectos activos con nombre del usuario asignado y flag en_riesgo';
