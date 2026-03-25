-- Tabla evidencias
CREATE TABLE IF NOT EXISTS evidencias (
  id               SERIAL PRIMARY KEY,
  tipo_entidad     VARCHAR(20) NOT NULL CHECK (tipo_entidad IN ('proyecto','tarea','subtarea')),
  entidad_id       INT NOT NULL,
  nombre_original  VARCHAR(255) NOT NULL,
  nombre_guardado  VARCHAR(255) NOT NULL,
  ruta_archivo     VARCHAR(500) NOT NULL,
  extension        VARCHAR(10) NOT NULL,
  mime_type        VARCHAR(100) NOT NULL,
  peso_bytes       INT NOT NULL,
  subido_por       INT NOT NULL REFERENCES usuarios(id) ON DELETE RESTRICT,
  created_at       TIMESTAMPTZ NOT NULL DEFAULT NOW(),
  deleted_at       TIMESTAMPTZ DEFAULT NULL
);

CREATE INDEX IF NOT EXISTS idx_evidencias_entidad ON evidencias(tipo_entidad, entidad_id) WHERE deleted_at IS NULL;
CREATE INDEX IF NOT EXISTS idx_evidencias_subido_por ON evidencias(subido_por) WHERE deleted_at IS NULL;
