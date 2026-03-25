# CHANGELOG — TaskOrbit

## [1.1.0] - 2026-03-25 — Remediación QA Pre-Producción

### Seguridad
- **CSRF.php**: `verifyRequest()` ahora detecta requests AJAX/JSON y responde con JSON estructurado (HTTP 419) en lugar de `die()` con texto plano. Las peticiones tradicionales reciben HTML controlado.
- **Router.php**: El override `_method` ahora acepta únicamente `PUT`, `PATCH` y `DELETE`. Valores arbitrarios son ignorados.
- **AuthController.php**: El método `logout()` ahora elimina explícitamente la cookie de sesión del cliente con `setcookie()` y limpia `$_SESSION = []` antes de `session_destroy()`.
- **SubtareasController.php**: Todas las operaciones sobre subtareas verifican que la tarea padre y el proyecto padre no estén eliminados (soft-delete) antes de proceder. Subtareas huérfanas son inaccesibles.

### Correcciones de Backend
- **Tarea::create()**: Agregada validación del ID retornado por `RETURNING id`. Si el INSERT no produce un ID válido, se hace rollback y se lanza excepción explícita (igual al patrón de `Proyecto::create()`).
- **AuthController.php (login)**: El campo `telefono` del usuario ahora se almacena en `$_SESSION['user']` para que los servicios de notificación y WhatsApp puedan usarlo correctamente.
- **ProyectosController, TareasController, SubtareasController, NotasController**: Agregada validación `maxLength` en campos descriptivos. Descripción máx. 2000 caracteres, contenido de notas máx. 5000 caracteres.
- **EstadoService.php**: Eliminada línea muerta con interpolación PHP inválida (`{-self::DIAS_ANTICIPACION_RIESGO}`). Solo permanece la expresión correcta.
- **MetricasService.php**: Las consultas que usaban `INTERVAL '" . CONSTANTE . " days'` (concatenación directa en SQL) ahora calculan la fecha en PHP y la pasan como parámetro de prepared statement.
- **UsuariosController.php**: El hard delete de usuarios ahora captura `PDOException` con código `23503` (FK violation) y muestra un mensaje amigable al usuario en lugar de una página de error.
- **EvidenciasController.php**: El orden de operaciones en el upload fue corregido: primero se inserta el registro en DB, luego se mueve el archivo a disco. Si el move falla, se hace rollback. Se elimina el riesgo de archivos huérfanos.
- **WhatsAppService.php**: `sendReal()` ahora implementa llamada cURL real a la API de Twilio. Valida credenciales antes del intento. Si las credenciales están incompletas, hace fallback a mock con log de advertencia explícito. Ya no era posible que `WHATSAPP_MODE=real` terminara en mock silenciosamente.

### Correcciones de Frontend
- **app.js, acciones-rapidas.js, notas-panel.js, evidencias.js**: Todos los `fetch()` ahora manejan `response.status === 419` mostrando alerta al usuario y recargando la página para obtener nuevo token CSRF.
- **app.js**: El campo `read` de notificaciones ahora se consume como `is_read` (entero 0/1) en lugar de la cadena `'t'`/`'f'` que devolvía PostgreSQL. Las notificaciones no leídas ahora se marcan visualmente de forma correcta.
- **acciones-rapidas.js**: `showActionFeedback()` ahora crea dinámicamente el contenedor `#toast-container` si no existe en el DOM, con fallback para cuando Bootstrap no está disponible.
- **proyectos/index.php**: Eliminado acceso directo a `$_GET` en la vista. Todos los filtros se leen desde la variable `$filtros` pasada por el controlador.

### Base de Datos
- **Migración 001**: `vw_proyectos` actualizada para exponer la columna `en_riesgo` de la tabla `proyectos`. Requerida por el semáforo de estado del sistema.
- **Migración 002**: Eliminación de columnas legacy de la tabla `notas` (`usuario_id`, `actividad_id`, `fecha_creacion`, `fecha_actualizacion`). Estas columnas nunca fueron leídas ni escritas por el código de aplicación.

### Configuración
- **.env**: Corregido `WHATSAPP_FROM` que tenía un carácter sobrante (`j`) al final del número de teléfono.

---

## [1.0.0] - 2026-01-01 — Versión inicial

- Creación e implementación de TaskOrbit
- Módulos: Proyectos, Tareas, Subtareas, Notas, Evidencias, Usuarios, Dashboard
- Autenticación con sesiones PHP
- Notificaciones in-app y WhatsApp (modo mock)
- Gráficas con Chart.js
