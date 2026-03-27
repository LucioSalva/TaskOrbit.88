# ESPECIFICACION FUNCIONAL OFICIAL — TaskOrbit

> Este documento es la referencia funcional base del proyecto.
> Cualquier analisis, correccion, implementacion o documentacion debe respetar esta definicion.
> Ultima actualizacion: 2026-03-21

---

## 1. Resumen General

**Nombre del sistema:** TaskOrbit

**Tipo de sistema:**
- Gestion de proyectos
- Gestion de tareas
- Colaboracion de equipo
- Seguimiento operativo diario

**Objetivo:** Centralizar en un solo lugar procesos que normalmente estan dispersos entre hojas de calculo, correos y mensajeria.

**Stack tecnologico obligatorio:**
| Capa | Tecnologia |
|------|-----------|
| Backend | PHP 8+ |
| Frontend | HTML5, Bootstrap 5, SCSS/CSS, JavaScript |
| Base de datos | PostgreSQL |
| Arquitectura | MVC personalizado en PHP |
| Enfoque UI | Mobile-first |

**Restricciones absolutas:**
- No Angular
- No TypeScript
- No SPA (Single Page Application)
- No componentes Angular

---

## 2. Funcionalidades Principales

### 2.1 Autenticacion (Login)

Autenticacion basada en PHP sin correo electronico.

**Campos requeridos:**
- Nombre completo
- Usuario (username)
- Contrasena
- Numero telefonico

**Requerimientos adicionales:**
- Validacion o verificacion por telefono
- Autenticacion basada en sesion PHP
- Control de acceso por roles

---

### 2.2 Dashboard

Panel principal con resumen del sistema.

**Contenido requerido:**
- Resumen general del sistema (contadores)
- Indicadores de productividad
- Grafica de barras de productividad

**Dimensiones de productividad:**
- Por usuario
- Por proyecto
- Por tarea

---

### 2.3 Gestion de Proyectos

**Campos minimos por proyecto:**
- Nombre
- Descripcion
- Tiempo o duracion estimada
- Fechas (inicio y fin)
- Estatus

**Funcionalidad dentro de cada proyecto:**
- Crear tareas
- Seguimiento del avance
- Colaboracion con el equipo

---

### 2.4 Gestion de Tareas

**Campos minimos por tarea:**
- Titulo
- Descripcion
- Usuario asignado
- Estatus
- Fechas (inicio y fin)
- Prioridad
- Relacion con proyecto

**Requerimientos adicionales:**
- Al asignar una tarea debe generarse una notificacion in-app al responsable

---

### 2.5 Estados Rapidos

Botones de accion rapida para actualizar el estado de una tarea o usuario:

- **Aceptada** — el usuario acepta la tarea
- **Terminada** — la tarea fue completada
- **Ocupado** — el usuario esta ocupado con esta tarea

---

### 2.6 Subtareas

Cada tarea puede contener subtareas.

**Campos por subtarea:**
- Titulo
- Descripcion
- Usuario asignado
- Estatus

**Estados posibles:**
- Por hacer
- Haciendo
- Terminada

---

### 2.7 Notas Informativas

Notas contextualizadas asignables a diferentes niveles:

| Nivel | Descripcion |
|-------|-------------|
| Proyecto | Nota relacionada con un proyecto especifico |
| Tarea | Nota relacionada con una tarea especifica |
| Subtarea | Nota relacionada con una subtarea especifica |
| Personal | Nota personal del usuario |

---

### 2.8 Colaboracion Visual

La interfaz debe sentirse como una herramienta:
- Visual y clara
- Colaborativa
- Orientada al seguimiento diario
- Util para coordinar al equipo
- No un CRUD plano sin contexto

---

## 3. Modulos o Paginas Minimas

| Modulo | Descripcion |
|--------|-------------|
| Login | Autenticacion de usuarios |
| Dashboard | Panel principal con indicadores |
| Usuarios | Gestion de usuarios del sistema |
| Proyectos | Listado y gestion de proyectos |
| Detalle de Proyecto | Vista con tareas, notas y avance |
| Tareas | Listado y gestion de tareas por proyecto |
| Detalle de Tarea | Vista con subtareas, notas y estado |
| Subtareas | Gestion de subtareas por tarea |
| Notas | Centro de notas por nivel |
| Notificaciones | Alertas y mensajes del sistema |
| Perfil / Configuracion | (Opcional) Configuracion del usuario |

---

## 4. Flujo Funcional Esperado

```
1. El usuario inicia sesion
2. Entra al Dashboard
3. Visualiza productividad y resumen general
4. Entra al modulo de Proyectos
5. Crea o consulta un proyecto
6. Dentro del proyecto, crea Tareas
7. Dentro de la tarea, crea Subtareas
8. Asigna responsables a tareas o subtareas
9. El responsable recibe notificacion in-app
10. El responsable cambia el estatus con botones rapidos
11. Se agregan notas en proyecto, tarea o subtarea
12. El Dashboard refleja el avance acumulado
```

---

## 5. Roles del Sistema

| Rol | Permisos |
|-----|----------|
| GOD | Acceso total al sistema |
| ADMIN | Puede crear proyectos, tareas y administrar usuarios asignados |
| USER | Solo puede ver y editar lo que tiene asignado |

---

## 6. Estructura de Base de Datos

### Tablas principales

| Tabla | Descripcion |
|-------|-------------|
| `usuarios` | Datos de usuarios (sin email) |
| `roles` | GOD, ADMIN, USER |
| `usuarios_roles` | Asignacion de rol por usuario |
| `proyectos` | Proyectos con prioridad, estado y fechas |
| `tareas` | Tareas vinculadas a proyectos |
| `subtareas` | Subtareas vinculadas a tareas |
| `notas` | Notas por scope (proyecto, tarea, subtarea, personal) |
| `notifications` | Notificaciones internas in-app |
| `audit_logs` | Registro de acciones del sistema |

### Estados de proyectos y tareas
```
por_hacer | haciendo | terminada | enterado | ocupado | aceptada
```

### Prioridades
```
baja | media | alta | critica
```

---

## 7. Notificaciones In-App

**Canal:** Notificaciones internas en la aplicacion.

**Eventos que generan notificacion:**
- Asignacion de tarea a usuario
- Cambio de estado de tarea
- Asignacion de proyecto a usuario

---

## 8. Seguridad

| Mecanismo | Descripcion |
|-----------|-------------|
| Autenticacion | Sesiones PHP con nombre de sesion personalizado |
| Contrasenas | Bcrypt con costo 12 |
| CSRF | Token por sesion validado con `hash_equals()` |
| SQL | Prepared statements con PDO (PostgreSQL) |
| Control de acceso | Middleware por rol en cada ruta |

---

## 9. Estado de Implementacion

> Ultima revision: 2026-03-20

### Ya implementado

- [x] Autenticacion completa (login, logout, sesion, roles)
- [x] Dashboard con metricas de productividad y filtros
- [x] Gestion de proyectos (CRUD, soft-delete, acceso por rol)
- [x] Gestion de tareas (CRUD, estados rapidos, herencia de asignacion)
- [x] Gestion de subtareas (crear, editar, cambiar estado, eliminar)
- [x] Notas por nivel (proyecto, tarea, subtarea, personal)
- [x] Notificaciones in-app (no leidas, marcar como leidas)
- [x] Notificaciones in-app funcionales con deduplicacion
- [x] Audit log de todas las acciones
- [x] CSRF en todos los formularios (incluido logout)
- [x] Rate limiting en login (5 intentos / 15 minutos por IP)
- [x] Content-Security-Policy header implementado
- [x] Filtros de proyectos aplicados server-side (SQL)
- [x] Control de acceso por roles (GOD/ADMIN/USER)
- [x] Soft-delete con cascada (proyectos > tareas > subtareas > notas)
- [x] Calculo de minutos estimados por fechas
- [x] Interfaz Bootstrap 5 mobile-first con sidebar responsivo
- [x] Gestion de usuarios (GOD puede crear, editar, activar/desactivar)

### Parcialmente implementado

- [x] Grafica de barras en dashboard (implementada con Chart.js: productividad por proyecto, estado de tareas, productividad por usuario)
- [ ] Vista detalle de tarea con subtareas inline (estructura existe, vista no es completa)
- [ ] Perfil/configuracion del usuario (no existe pagina dedicada)
- [ ] Verificacion o validacion por telefono (campo existe, verificacion no implementada)

### No implementado

- [ ] Vista detalle de tarea independiente (`/tareas/{id}`)
- [ ] Kanban board (solo vista de lista)
- [ ] Vista de calendario
- [ ] Busqueda global en proyectos, tareas, notas
- [ ] Exportacion de reportes (PDF, Excel)
- [ ] Operaciones masivas (editar/eliminar multiples elementos)
- [ ] Registro publico de nuevos usuarios (actualmente solo por GOD)
- [x] Dark mode (toggle implementado via Bootstrap data-bs-theme + localStorage)
- [ ] Dependencias entre tareas (bloqueos, prerequisitos)
- [ ] Tareas recurrentes

---

## 10. Archivos Clave del Sistema

```
taskorbit-php/
├── public/
│   ├── index.php               # Punto de entrada, sesion, CSRF, router
│   └── assets/css/app.css      # Estilos personalizados
├── routes/
│   └── web.php                 # Definicion de todas las rutas
├── app/
│   ├── Core/
│   │   ├── Router.php          # Sistema de enrutamiento
│   │   ├── Controller.php      # Controlador base
│   │   ├── Database.php        # Singleton PDO PostgreSQL
│   │   └── View.php            # Motor de plantillas
│   ├── Controllers/            # 8 controladores (Auth, Dashboard, Proyectos...)
│   ├── Models/                 # 6 modelos (Usuario, Proyecto, Tarea...)
│   ├── Services/
│   │   ├── NotificacionService.php # Despacho de notificaciones in-app
│   │   ├── NotificacionTemplates.php # Plantillas de mensajes
│   │   ├── EstadoService.php   # Propagacion de estados
│   │   └── SemaforoService.php # Calculo de semaforo de riesgo
│   ├── Helpers/
│   │   ├── CSRF.php            # Proteccion CSRF
│   │   ├── Validator.php       # Validaciones de entrada
│   │   └── DateHelper.php      # Utilidades de fecha
│   ├── Middleware/
│   │   ├── AuthMiddleware.php  # Verificacion de sesion
│   │   └── RoleMiddleware.php  # Verificacion de rol
│   └── Views/                  # Todas las vistas PHP
├── config/
│   ├── app.php                 # Configuracion general
│   └── database.php            # Configuracion PostgreSQL
├── database/
│   ├── schema.sql              # Esquema completo de la BD
│   └── seeds.sql               # Datos de prueba
└── .env                        # Variables de entorno
```

---

## 11. Flujo Actual del Sistema (Implementado)

```
/login → AuthController::showLogin()
  POST /login → AuthController::login() → sesion + rol
    /dashboard → DashboardController::index()
      /proyectos → ProyectosController::index()
        /proyectos/{id} → ProyectosController::show()
          /proyectos/{id}/tareas → TareasController::index()
            POST /tareas/{id}/estado → TareasController::updateEstado()
            /tareas/{tareaId}/subtareas → SubtareasController (inline)
          /notas (por scope) → NotasController
      /admin/usuarios → UsuariosController::index() (GOD only)
      /notificaciones → NotificacionesController (AJAX)
```

---

## 12. Notas de Desarrollo

- La asignacion de tareas hereda el usuario asignado del proyecto si no se especifica uno
- El rol GOD tiene acceso a todos los proyectos; ADMIN ve los que creo; USER ve los asignados
- Los soft-deletes en cascada preservan la integridad historica
- Las notificaciones in-app son el unico canal de alertas; no hay canales externos configurados
- Todos los formularios tienen proteccion CSRF
- La base de datos usa vistas (`vw_login`, `vw_proyectos`, `vw_tareas`, etc.) para simplificar queries

---

*Este documento debe actualizarse cada vez que se implemente, modifique o elimine funcionalidad del sistema.*
