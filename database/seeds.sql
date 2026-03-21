-- ============================================================
-- TaskOrbit — Seeds de ejemplo
-- Ejecutar DESPUÉS de schema.sql
-- Contraseñas en texto plano (se hashean manualmente):
--   admin123   → bcrypt
--   user123    → bcrypt
--   god123     → bcrypt
-- Para regenerar hashes usa: php -r "echo password_hash('contraseña', PASSWORD_BCRYPT, ['cost'=>12]);"
-- ============================================================

-- ---- Roles ----
INSERT INTO roles (nombre) VALUES
  ('GOD'),
  ('ADMIN'),
  ('USER')
ON CONFLICT (nombre) DO NOTHING;

-- ---- Usuarios ----
-- Contraseñas de ejemplo (generadas con pgcrypto bcrypt):
--   god   → god123
--   admin → admin123
--   maria → user123
--   juan  → user123
-- Para cambiarlas usa: UPDATE usuarios SET password_hash = crypt('nueva', gen_salt('bf',12)) WHERE username = 'x';
INSERT INTO usuarios (nombre_completo, username, telefono, password_hash, activo) VALUES
  ('Super Administrador', 'god',   '5500000001', crypt('god123',   gen_salt('bf', 10)), TRUE),
  ('Carlos Administrador','admin', '5500000002', crypt('admin123', gen_salt('bf', 10)), TRUE),
  ('María Trabajadora',   'maria', '5500000003', crypt('user123',  gen_salt('bf', 10)), TRUE),
  ('Juan Operador',       'juan',  '5500000004', crypt('user123',  gen_salt('bf', 10)), TRUE)
ON CONFLICT (username) DO NOTHING;

-- ---- Asignar roles ----
INSERT INTO usuarios_roles (usuario_id, rol_id)
SELECT u.id, r.id
FROM usuarios u, roles r
WHERE (u.username = 'god'   AND r.nombre = 'GOD')
   OR (u.username = 'admin' AND r.nombre = 'ADMIN')
   OR (u.username = 'maria' AND r.nombre = 'USER')
   OR (u.username = 'juan'  AND r.nombre = 'USER')
ON CONFLICT (usuario_id) DO NOTHING;

-- ---- Proyectos de ejemplo ----
INSERT INTO proyectos (nombre, descripcion, prioridad, estado, fecha_inicio, fecha_fin, usuario_asignado_id, created_by)
SELECT
  'Rediseño Portal Web',
  'Modernización del portal de clientes con nueva interfaz responsive.',
  'alta',
  'haciendo',
  CURRENT_DATE - INTERVAL '15 days',
  CURRENT_DATE + INTERVAL '30 days',
  u_maria.id,
  u_admin.id
FROM
  (SELECT id FROM usuarios WHERE username = 'admin') AS u_admin,
  (SELECT id FROM usuarios WHERE username = 'maria') AS u_maria
WHERE NOT EXISTS (SELECT 1 FROM proyectos WHERE nombre = 'Rediseño Portal Web');

INSERT INTO proyectos (nombre, descripcion, prioridad, estado, fecha_inicio, fecha_fin, usuario_asignado_id, created_by)
SELECT
  'Migración Base de Datos',
  'Migración de MySQL a PostgreSQL con preservación de datos históricos.',
  'critica',
  'por_hacer',
  CURRENT_DATE + INTERVAL '5 days',
  CURRENT_DATE + INTERVAL '60 days',
  u_juan.id,
  u_admin.id
FROM
  (SELECT id FROM usuarios WHERE username = 'admin') AS u_admin,
  (SELECT id FROM usuarios WHERE username = 'juan')  AS u_juan
WHERE NOT EXISTS (SELECT 1 FROM proyectos WHERE nombre = 'Migración Base de Datos');

INSERT INTO proyectos (nombre, descripcion, prioridad, estado, fecha_inicio, fecha_fin, usuario_asignado_id, created_by)
SELECT
  'App Móvil v2.0',
  'Segunda versión de la aplicación móvil con mejoras de rendimiento.',
  'media',
  'haciendo',
  CURRENT_DATE - INTERVAL '30 days',
  CURRENT_DATE + INTERVAL '45 days',
  u_maria.id,
  u_admin.id
FROM
  (SELECT id FROM usuarios WHERE username = 'admin') AS u_admin,
  (SELECT id FROM usuarios WHERE username = 'maria') AS u_maria
WHERE NOT EXISTS (SELECT 1 FROM proyectos WHERE nombre = 'App Móvil v2.0');

-- ---- Tareas de ejemplo ----
-- Proyecto 1: Rediseño Portal Web
INSERT INTO tareas (proyecto_id, nombre, descripcion, prioridad, estado, fecha_inicio, fecha_fin, usuario_asignado_id, created_by)
SELECT
  p.id,
  'Diseño de wireframes',
  'Crear wireframes para todas las secciones del portal.',
  'alta',
  'terminada',
  CURRENT_DATE - INTERVAL '14 days',
  CURRENT_DATE - INTERVAL '7 days',
  u.id,
  a.id
FROM proyectos p,
  (SELECT id FROM usuarios WHERE username = 'maria') AS u,
  (SELECT id FROM usuarios WHERE username = 'admin') AS a
WHERE p.nombre = 'Rediseño Portal Web'
  AND NOT EXISTS (SELECT 1 FROM tareas WHERE nombre = 'Diseño de wireframes' AND proyecto_id = p.id);

INSERT INTO tareas (proyecto_id, nombre, descripcion, prioridad, estado, fecha_inicio, fecha_fin, usuario_asignado_id, created_by)
SELECT
  p.id,
  'Desarrollo frontend',
  'Implementar maquetas HTML/CSS/JS con Bootstrap 5.',
  'alta',
  'haciendo',
  CURRENT_DATE - INTERVAL '7 days',
  CURRENT_DATE + INTERVAL '14 days',
  u.id,
  a.id
FROM proyectos p,
  (SELECT id FROM usuarios WHERE username = 'maria') AS u,
  (SELECT id FROM usuarios WHERE username = 'admin') AS a
WHERE p.nombre = 'Rediseño Portal Web'
  AND NOT EXISTS (SELECT 1 FROM tareas WHERE nombre = 'Desarrollo frontend' AND proyecto_id = p.id);

INSERT INTO tareas (proyecto_id, nombre, descripcion, prioridad, estado, fecha_inicio, fecha_fin, usuario_asignado_id, created_by)
SELECT
  p.id,
  'Pruebas de usabilidad',
  'Realizar pruebas con usuarios reales y corregir hallazgos.',
  'media',
  'por_hacer',
  CURRENT_DATE + INTERVAL '15 days',
  CURRENT_DATE + INTERVAL '25 days',
  u.id,
  a.id
FROM proyectos p,
  (SELECT id FROM usuarios WHERE username = 'maria') AS u,
  (SELECT id FROM usuarios WHERE username = 'admin') AS a
WHERE p.nombre = 'Rediseño Portal Web'
  AND NOT EXISTS (SELECT 1 FROM tareas WHERE nombre = 'Pruebas de usabilidad' AND proyecto_id = p.id);

-- Proyecto 2: Migración Base de Datos
INSERT INTO tareas (proyecto_id, nombre, descripcion, prioridad, estado, fecha_inicio, fecha_fin, usuario_asignado_id, created_by)
SELECT
  p.id,
  'Análisis de esquema actual',
  'Documentar tablas, relaciones e índices del esquema MySQL.',
  'critica',
  'por_hacer',
  CURRENT_DATE + INTERVAL '5 days',
  CURRENT_DATE + INTERVAL '12 days',
  u.id,
  a.id
FROM proyectos p,
  (SELECT id FROM usuarios WHERE username = 'juan') AS u,
  (SELECT id FROM usuarios WHERE username = 'admin') AS a
WHERE p.nombre = 'Migración Base de Datos'
  AND NOT EXISTS (SELECT 1 FROM tareas WHERE nombre = 'Análisis de esquema actual' AND proyecto_id = p.id);

INSERT INTO tareas (proyecto_id, nombre, descripcion, prioridad, estado, fecha_inicio, fecha_fin, usuario_asignado_id, created_by)
SELECT
  p.id,
  'Creación de esquema PostgreSQL',
  'Convertir y crear el esquema en PostgreSQL con mejoras.',
  'critica',
  'por_hacer',
  CURRENT_DATE + INTERVAL '13 days',
  CURRENT_DATE + INTERVAL '25 days',
  u.id,
  a.id
FROM proyectos p,
  (SELECT id FROM usuarios WHERE username = 'juan') AS u,
  (SELECT id FROM usuarios WHERE username = 'admin') AS a
WHERE p.nombre = 'Migración Base de Datos'
  AND NOT EXISTS (SELECT 1 FROM tareas WHERE nombre = 'Creación de esquema PostgreSQL' AND proyecto_id = p.id);

-- ---- Subtareas de ejemplo ----
INSERT INTO subtareas (tarea_id, nombre, descripcion, prioridad, estado, created_by)
SELECT
  t.id,
  'Página principal',
  'Diseño del hero, carrusel y secciones de features.',
  'alta',
  'terminada',
  u.id
FROM tareas t,
  (SELECT id FROM usuarios WHERE username = 'maria') AS u
WHERE t.nombre = 'Diseño de wireframes'
  AND NOT EXISTS (SELECT 1 FROM subtareas WHERE nombre = 'Página principal' AND tarea_id = t.id);

INSERT INTO subtareas (tarea_id, nombre, descripcion, prioridad, estado, created_by)
SELECT
  t.id,
  'Sección de productos',
  'Grid de productos con filtros y ordenamiento.',
  'alta',
  'terminada',
  u.id
FROM tareas t,
  (SELECT id FROM usuarios WHERE username = 'maria') AS u
WHERE t.nombre = 'Diseño de wireframes'
  AND NOT EXISTS (SELECT 1 FROM subtareas WHERE nombre = 'Sección de productos' AND tarea_id = t.id);

INSERT INTO subtareas (tarea_id, nombre, descripcion, prioridad, estado, created_by)
SELECT
  t.id,
  'Menú de navegación responsive',
  'Implementar hamburger menu para móvil.',
  'alta',
  'haciendo',
  u.id
FROM tareas t,
  (SELECT id FROM usuarios WHERE username = 'maria') AS u
WHERE t.nombre = 'Desarrollo frontend'
  AND NOT EXISTS (SELECT 1 FROM subtareas WHERE nombre = 'Menú de navegación responsive' AND tarea_id = t.id);

INSERT INTO subtareas (tarea_id, nombre, descripcion, prioridad, estado, created_by)
SELECT
  t.id,
  'Formulario de contacto',
  'Validación JS + envío via PHP.',
  'media',
  'por_hacer',
  u.id
FROM tareas t,
  (SELECT id FROM usuarios WHERE username = 'maria') AS u
WHERE t.nombre = 'Desarrollo frontend'
  AND NOT EXISTS (SELECT 1 FROM subtareas WHERE nombre = 'Formulario de contacto' AND tarea_id = t.id);

-- ---- Notas de ejemplo ----
INSERT INTO notas (scope, referencia_id, user_id, titulo, contenido, tipo)
SELECT
  'proyecto',
  p.id,
  u.id,
  'Kickoff meeting',
  'Reunión de inicio realizada el lunes. El cliente aprobó los wireframes iniciales. Siguiente paso: revisión del diseño final.',
  'actividad'
FROM proyectos p,
  (SELECT id FROM usuarios WHERE username = 'admin') AS u
WHERE p.nombre = 'Rediseño Portal Web'
  AND NOT EXISTS (SELECT 1 FROM notas WHERE titulo = 'Kickoff meeting' AND scope = 'proyecto' AND referencia_id = p.id);

INSERT INTO notas (scope, referencia_id, user_id, titulo, contenido, tipo)
SELECT
  'personal',
  NULL,
  u.id,
  'Recordatorio',
  'Revisar documentación de PostgreSQL para tipos ENUM y particionamiento.',
  'personal'
FROM (SELECT id FROM usuarios WHERE username = 'admin') AS u
WHERE NOT EXISTS (
  SELECT 1 FROM notas WHERE titulo = 'Recordatorio' AND user_id = u.id AND scope = 'personal'
);

-- ---- Notificación de bienvenida ----
INSERT INTO notifications (user_id, type, title, message, severity, channel, read)
SELECT
  u.id,
  'bienvenida',
  '¡Bienvenido a TaskOrbit!',
  'Tu cuenta ha sido creada exitosamente. Explora los proyectos asignados.',
  'success',
  'in_app',
  FALSE
FROM usuarios u
WHERE NOT EXISTS (
  SELECT 1 FROM notifications WHERE user_id = u.id AND type = 'bienvenida'
);
