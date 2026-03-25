-- ============================================================
-- Migration 007: Notas Inteligentes / Bitácora
-- Run once against the TaskOrbit database.
-- ============================================================

-- 1. Make user_id nullable (system-generated notes have no user actor)
ALTER TABLE notas ALTER COLUMN user_id DROP NOT NULL;

-- 2. Widen titulo to 200 chars
ALTER TABLE notas ALTER COLUMN titulo TYPE VARCHAR(200);

-- 3. Add is_pinned flag (ADMIN/GOD can pin important notes)
ALTER TABLE notas ADD COLUMN IF NOT EXISTS is_pinned BOOLEAN NOT NULL DEFAULT FALSE;

-- 4. Add index for pinned notes (pinned first in ORDER BY)
CREATE INDEX IF NOT EXISTS idx_notas_pinned ON notas(scope, referencia_id, is_pinned DESC, created_at DESC) WHERE deleted_at IS NULL;
