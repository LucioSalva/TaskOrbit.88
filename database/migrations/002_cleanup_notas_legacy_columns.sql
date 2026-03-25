-- Fix QA-007: Limpiar columnas legacy de la tabla notas
-- Estas columnas nunca son leídas ni escritas por el código de aplicación.
-- La información equivalente existe en user_id, created_at, updated_at.
-- Fecha: 2026-03-25
-- NOTA: Ejecutar en ventana de mantenimiento. Hacer backup antes.

ALTER TABLE notas DROP COLUMN IF EXISTS usuario_id;
ALTER TABLE notas DROP COLUMN IF EXISTS actividad_id;
ALTER TABLE notas DROP COLUMN IF EXISTS fecha_creacion;
ALTER TABLE notas DROP COLUMN IF EXISTS fecha_actualizacion;
