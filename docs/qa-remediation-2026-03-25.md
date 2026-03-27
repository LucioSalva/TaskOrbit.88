# Reporte de Remediación QA — TaskOrbit
**Fecha:** 2026-03-25
**Ejecutado por:** Tech Lead Orchestrator (modo automático)
**Estado inicial:** No apto para producción
**Estado final:** Apto con observaciones

---

## Resumen Ejecutivo

Se ejecutó una remediación completa del reporte QA pre-producción de TaskOrbit. Los 18 hallazgos identificados (1 bloqueante resuelto anteriormente, 17 en esta ronda) fueron corregidos en código, validados y documentados.

**Riesgos eliminados:**
- Fallo silencioso total en AJAX tras expiración de CSRF
- Notificaciones no leídas visualmente incorrectas (boolean PostgreSQL)
- Acceso no autorizado a subtareas huérfanas
- Datos de auditor faltantes (teléfono en sesión)
- SQL injection estructural en MetricasService
- Hard delete de usuarios sin manejo de integridad referencial
- Archivos de evidencia huérfanos en disco por fallo de DB

---

## Matriz de Remediación

| ID QA | Severidad | Descripción | Archivos | Estado |
|-------|-----------|-------------|----------|--------|
| QA-001 | BLOQUEANTE | Flash messages renderizaban "Array" en dashboard | `app/Views/dashboard/index.php` | CORREGIDO (ronda anterior) |
| QA-008 | BLOQUEANTE | CSRF die() rompe AJAX silenciosamente | `app/Helpers/CSRF.php`, 4 archivos JS | CORREGIDO |
| QA-010 | CRITICO | Boolean PostgreSQL 'f' truthy en JS | `app/Models/Notificacion.php`, `app.js` | CORREGIDO |
| QA-014 | CRITICO | Subtareas huérfanas sin control de acceso | `app/Controllers/SubtareasController.php` | CORREGIDO |
| QA-012 | ALTO | Tarea::create() sin validar id > 0 | `app/Models/Tarea.php` | CORREGIDO |
| QA-018 | ALTO | descripcion sin maxLength en backend | `ProyectosController`, `TareasController`, `SubtareasController`, `NotasController` | CORREGIDO |
| QA-002 | ALTO | telefono no en sesión al login | `app/Controllers/AuthController.php`, `app/Models/Usuario.php` | CORREGIDO |
| QA-006 | ALTO | Vistas acceden a $_GET directamente | `app/Views/proyectos/index.php`, `ProyectosController.php` | CORREGIDO |
| QA-005 | ALTO | Línea muerta con interpolación PHP inválida | `app/Services/EstadoService.php` | CORREGIDO |
| RS-001 | ALTO | _method override permisivo | `app/Core/Router.php` | CORREGIDO |
| RS-002 | ALTO | Hard delete usuario sin captura FK | `app/Controllers/UsuariosController.php` | CORREGIDO |
| RS-003 | ALTO | SQL con constantes concatenadas | `app/Services/MetricasService.php` | CORREGIDO |
| QA-011 | MEDIO | vw_proyectos sin columna en_riesgo | `database/migrations/001_fix_vw_proyectos_en_riesgo.sql` | CORREGIDO |
| QA-013 | MEDIO | Comparaciones de fecha por string | `TareasController.php`, `SubtareasController.php` | CORREGIDO |
| QA-009 | MEDIO | Logout no elimina cookie de sesión | `app/Controllers/AuthController.php` | CORREGIDO |
| QA-007 | MEDIO | Columnas legacy en tabla notas | `database/migrations/002_cleanup_notas_legacy_columns.sql` | CORREGIDO |
| QA-015 | MEDIO | Archivo evidencia antes de registro DB | `app/Controllers/EvidenciasController.php` | CORREGIDO |
| QA-017 | MEDIO | showActionFeedback sin fallback DOM | `public/assets/js/acciones-rapidas.js` | CORREGIDO |

---

## Migraciones SQL

Las siguientes migraciones deben ejecutarse en la base de datos PostgreSQL **antes del primer despliegue**:

```bash
psql -U postgres -d TaskOrbit -f database/migrations/001_fix_vw_proyectos_en_riesgo.sql
psql -U postgres -d TaskOrbit -f database/migrations/002_cleanup_notas_legacy_columns.sql
```

**001_fix_vw_proyectos_en_riesgo.sql:**
- Añade columna `en_riesgo` a `proyectos` y `tareas` si no existe
- Recrea `vw_proyectos` incluyendo el campo `en_riesgo`

**002_cleanup_notas_legacy_columns.sql:**
- Elimina columnas `usuario_id`, `actividad_id`, `fecha_creacion`, `fecha_actualizacion` de la tabla `notas`
- Usa `IF EXISTS` — es idempotente y segura de ejecutar múltiples veces

---

## Riesgos Residuales

### LoginRateLimiter cleanup en cada login
El cleanup de intentos de login caducos ocurre en cada verificación. Bajo carga alta puede generar contención en la tabla `login_attempts`. Impacto bajo en escala actual; migrar a cron si el tráfico de login aumenta.

---

## Estado por Módulo (post-remediación)

| Módulo | Estado |
|--------|--------|
| Autenticación | OK |
| Dashboard | OK |
| Proyectos | OK |
| Tareas | OK |
| Subtareas | OK |
| Notas | OK |
| Notificaciones in-app | OK |
| Evidencias | OK |
| Gráficas | OK |
| Navegación | OK |

---

## Veredicto Final

**Apto con observaciones**

Las observaciones son:
1. Ejecutar las 2 migraciones SQL antes del primer despliegue en producción
2. LoginRateLimiter cleanup inline — monitorear bajo carga alta
