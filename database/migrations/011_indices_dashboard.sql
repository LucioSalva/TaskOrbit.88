-- ============================================================
-- Migration 011: Índices auxiliares para dashboard y métricas
-- Mejora consultas de DashboardController y MetricasService.
-- Run once against the TaskOrbit database.
-- ============================================================

-- Tareas: orden frecuente por fecha_fin para "vencen pronto"
CREATE INDEX IF NOT EXISTS idx_tareas_fecha_fin
  ON tareas(fecha_fin)
  WHERE deleted_at IS NULL;

-- Tareas: filtro por estado + usuario asignado (mis tareas pendientes)
CREATE INDEX IF NOT EXISTS idx_tareas_estado_usuario
  ON tareas(estado, usuario_asignado_id)
  WHERE deleted_at IS NULL;

-- Proyectos: filtro por en_riesgo + estado (semáforo dashboard)
CREATE INDEX IF NOT EXISTS idx_proyectos_riesgo_estado
  ON proyectos(en_riesgo, estado)
  WHERE deleted_at IS NULL;

-- Subtareas: filtro frecuente por estado para porcentaje completado
CREATE INDEX IF NOT EXISTS idx_subtareas_tarea_estado
  ON subtareas(tarea_id, estado)
  WHERE deleted_at IS NULL;

-- Notificaciones: índice para drop-down "no leídas"
CREATE INDEX IF NOT EXISTS idx_notifications_user_unread
  ON notifications(user_id, created_at DESC)
  WHERE read = FALSE;
