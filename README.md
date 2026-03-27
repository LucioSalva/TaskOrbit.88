# TaskOrbit

Task and project management system for small teams. Centralizes projects, tasks, subtasks, notes, and team collaboration in a single PHP MVC application.

---

## Tech Stack

| Layer        | Technology                          |
|--------------|-------------------------------------|
| Backend      | PHP 8.1+, custom MVC                |
| Frontend     | Bootstrap 5.3, vanilla JS, HTML5    |
| Database     | PostgreSQL 14+                      |
| Architecture | MVC (no framework)                  |
| UI           | Mobile-first, dark/light mode       |

---

## Requirements

- PHP 8.1 or higher
- PostgreSQL 14 or higher
- Laragon (recommended) or any web server with PHP + PostgreSQL support
- PDO + PDO_PGSQL extension enabled

---

## Local Setup (Laragon)

1. Clone or copy the project into `C:\laragon\www\taskorbit`

2. Create the PostgreSQL database:

   ```sql
   CREATE DATABASE "TaskOrbit";
   ```

3. Run the SQL schema:

   ```
   psql -U postgres -d TaskOrbit -f database/schema.sql
   ```

   If seed data is available:

   ```
   psql -U postgres -d TaskOrbit -f database/seed.sql
   ```

4. Copy and configure the environment file:

   ```
   cp .env.example .env
   ```

   Edit `.env` with your values (see section below).

5. Access the app at:

   ```
   http://localhost/taskorbit/public
   ```

---

## Environment Variables

Create a `.env` file in the project root with the following keys:

```env
APP_NAME=TaskOrbit
APP_URL=http://localhost/taskorbit/public
APP_DEBUG=false
APP_TIMEZONE=America/Mexico_City

DB_HOST=localhost
DB_PORT=5432
DB_NAME=TaskOrbit
DB_USER=postgres
DB_PASSWORD=your_password

SESSION_NAME=taskorbit_session
SESSION_LIFETIME=7200
```

Set `APP_DEBUG=true` only in development. Never enable in production.

---

## Folder Structure

```
taskorbit/
├── public/
│   ├── index.php               # Entry point: session, CSRF, router bootstrap
│   └── assets/
│       ├── css/app.css         # Custom styles
│       └── js/app.js           # Dark mode toggle, notifications, delete modal
├── routes/
│   └── web.php                 # All route definitions
├── app/
│   ├── Core/
│   │   ├── Router.php          # URL routing
│   │   ├── Controller.php      # Base controller
│   │   ├── Database.php        # PDO singleton (PostgreSQL)
│   │   └── View.php            # Template renderer
│   ├── Controllers/            # AuthController, DashboardController, ProyectosController, ...
│   ├── Models/                 # Proyecto, Tarea, Subtarea, Nota, Usuario, Notificacion
│   ├── Services/
│   │   ├── NotificacionService.php # In-app notification dispatch
│   │   ├── NotificacionTemplates.php # Notification message templates
│   │   ├── EstadoService.php   # State propagation logic
│   │   └── SemaforoService.php # Traffic light / risk indicator
│   ├── Helpers/
│   │   ├── CSRF.php            # CSRF token generation and verification
│   │   ├── Validator.php       # Input validation helpers
│   │   └── DateHelper.php      # Date formatting and estimation
│   ├── Middleware/
│   │   ├── AuthMiddleware.php  # Session authentication check
│   │   └── RoleMiddleware.php  # Role-based access control
│   └── Views/                  # PHP view templates per module
├── database/                   # SQL schema and migration files
├── storage/
│   └── logs/                   # Application logs (excluded from git)
├── .env                        # Local environment config (excluded from git)
└── .gitignore
```

---

## User Roles

| Role  | Access                                                        |
|-------|---------------------------------------------------------------|
| GOD   | Full system access, manages all users and all data            |
| ADMIN | Creates and manages projects/tasks, manages non-GOD users     |
| USER  | Views and updates only what is assigned to them               |

---

## Key Features

- Project, task, and subtask management with soft-delete
- Role-based access control on every route
- In-app notifications
- Contextual notes (project, task, subtask, personal)
- Productivity dashboard with Chart.js charts
- Dark/light mode toggle (persisted in localStorage)
- Full audit log on all write operations
- CSRF protection on all POST forms
- Rate limiting on login (5 attempts per 15 minutes per IP)
- Content-Security-Policy header
