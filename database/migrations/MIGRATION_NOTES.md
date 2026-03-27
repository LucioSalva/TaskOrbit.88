# TaskOrbit -- Notas de Migraciones

## Estructura de migraciones

- **000_create_login_attempts.sql** — Tabla para rate limiting de login (basado en DB)
- **001_fix_vw_proyectos_en_riesgo.sql** — Correccion de vista vw_proyectos con campo en_riesgo
- **002_cleanup_notas_legacy_columns.sql** — Limpieza de columnas legacy en notas
- **003 a 006** — No existen. Fueron saltados durante desarrollo. Esto es intencional y seguro.
- **007_notas_inteligentes.sql** — Agrega is_pinned, amplia titulo a 200 chars, user_id nullable
- **008_evidencias.sql** — Tabla de evidencias para archivos adjuntos
- **009_remove_whatsapp_channel.sql** — Elimina canal 'whatsapp' (y 'push') del CHECK constraint de la tabla notifications. Elimina filas con channel no soportado. Deja solo 'in_app' y 'email'.

## Fuente autoritativa

El archivo `database/schema.sql` es la fuente autoritativa del esquema completo.
Ejecutar `schema.sql` en una DB vacia debe crear todas las tablas, vistas, indices y triggers
necesarios para el funcionamiento del sistema sin necesidad de ejecutar migraciones.

Las migraciones son incrementales y se usan unicamente para actualizar bases de datos existentes.
