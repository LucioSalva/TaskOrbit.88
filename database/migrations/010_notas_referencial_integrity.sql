-- ============================================================
-- Migration 010: Notas referencial integrity
-- Endurece la asociación polimórfica de notas (scope+referencia_id),
-- preserva historial cuando se elimina un usuario, y restringe tipo/contenido.
-- Run once against the TaskOrbit database.
-- ============================================================

BEGIN;

-- ------------------------------------------------------------
-- 1. Limpieza preventiva: descartar notas huérfanas previas
--    (scope!=personal con referencia_id NULL o apuntando a entidad eliminada)
-- ------------------------------------------------------------
UPDATE notas
   SET deleted_at = NOW()
 WHERE deleted_at IS NULL
   AND scope <> 'personal'
   AND referencia_id IS NULL;

UPDATE notas n
   SET deleted_at = NOW()
 WHERE n.deleted_at IS NULL
   AND n.scope = 'proyecto'
   AND NOT EXISTS (
     SELECT 1 FROM proyectos p
      WHERE p.id = n.referencia_id AND p.deleted_at IS NULL
   );

UPDATE notas n
   SET deleted_at = NOW()
 WHERE n.deleted_at IS NULL
   AND n.scope = 'tarea'
   AND NOT EXISTS (
     SELECT 1 FROM tareas t
      WHERE t.id = n.referencia_id AND t.deleted_at IS NULL
   );

UPDATE notas n
   SET deleted_at = NOW()
 WHERE n.deleted_at IS NULL
   AND n.scope = 'subtarea'
   AND NOT EXISTS (
     SELECT 1 FROM subtareas s
      WHERE s.id = n.referencia_id AND s.deleted_at IS NULL
   );

-- ------------------------------------------------------------
-- 2. CHECK: scope=personal => referencia_id IS NULL
--    scope!=personal => referencia_id IS NOT NULL
-- ------------------------------------------------------------
ALTER TABLE notas
  DROP CONSTRAINT IF EXISTS chk_notas_scope_referencia;

ALTER TABLE notas
  ADD CONSTRAINT chk_notas_scope_referencia
  CHECK (
    (scope =  'personal' AND referencia_id IS NULL)
    OR
    (scope <> 'personal' AND referencia_id IS NOT NULL)
  );

-- ------------------------------------------------------------
-- 3. CHECK: tipo en whitelist conocida
-- ------------------------------------------------------------
UPDATE notas
   SET tipo = 'personal'
 WHERE tipo IS NULL
    OR tipo NOT IN ('personal','auto','actividad','sistema','manual');

ALTER TABLE notas
  DROP CONSTRAINT IF EXISTS chk_notas_tipo;

ALTER TABLE notas
  ADD CONSTRAINT chk_notas_tipo
  CHECK (tipo IN ('personal','auto','actividad','sistema','manual'));

-- ------------------------------------------------------------
-- 4. CHECK: contenido no vacío y máximo 5000 caracteres
-- ------------------------------------------------------------
ALTER TABLE notas
  DROP CONSTRAINT IF EXISTS chk_notas_contenido_len;

ALTER TABLE notas
  ADD CONSTRAINT chk_notas_contenido_len
  CHECK (char_length(btrim(contenido)) BETWEEN 1 AND 5000);

-- ------------------------------------------------------------
-- 5. user_id ON DELETE SET NULL en lugar de CASCADE.
--    Razón: si se desactiva o elimina al autor, conservamos
--    historial operativo (auditoría real).
-- ------------------------------------------------------------
ALTER TABLE notas
  DROP CONSTRAINT IF EXISTS notas_user_id_fkey;

ALTER TABLE notas
  ADD CONSTRAINT notas_user_id_fkey
  FOREIGN KEY (user_id)
  REFERENCES usuarios(id)
  ON DELETE SET NULL;

COMMIT;
