<?php
use App\Middleware\AuthMiddleware;
use App\Middleware\RoleMiddleware;

$auth    = [AuthMiddleware::class];
$godOnly = [AuthMiddleware::class, new RoleMiddleware(['GOD'])];

// Public routes
$router->get('/', function() {
    $base = rtrim(getenv('APP_URL') ?: '', '/');
    header('Location: ' . $base . '/login');
    exit;
});
$router->get('/login', 'AuthController@showLogin');
$router->post('/login', 'AuthController@login');
$router->post('/logout', 'AuthController@logout');
$router->get('/logout', 'AuthController@logoutGet');
$router->get('/acceso-denegado', 'AuthController@accessDenied');

// Protected routes
$router->group($auth, function ($r) {

    // Dashboard
    $r->get('/dashboard', 'DashboardController@index');

    // Proyectos
    $r->get('/proyectos', 'ProyectosController@index');
    $r->get('/proyectos/crear', 'ProyectosController@create');
    $r->post('/proyectos', 'ProyectosController@store');
    $r->get('/proyectos/{id}/editar', 'ProyectosController@edit');
    $r->post('/proyectos/{id}/editar', 'ProyectosController@update');
    $r->post('/proyectos/{id}/eliminar', 'ProyectosController@destroy');
    $r->get('/proyectos/{id}/eliminar-preview', 'ProyectosController@deletePreview');
    $r->post('/proyectos/{id}/estado', 'ProyectosController@updateEstado');
    $r->get('/proyectos/{id}', 'ProyectosController@show');

    // Tareas
    $r->get('/proyectos/{proyectoId}/tareas', 'TareasController@index');
    $r->get('/proyectos/{proyectoId}/tareas/crear', 'TareasController@create');
    $r->post('/proyectos/{proyectoId}/tareas', 'TareasController@store');
    $r->get('/tareas/{id}/editar', 'TareasController@edit');
    $r->post('/tareas/{id}/editar', 'TareasController@update');
    $r->post('/tareas/{id}/estado', 'TareasController@updateEstado');
    $r->post('/tareas/{id}/eliminar', 'TareasController@destroy');

    // Subtareas
    $r->post('/tareas/{tareaId}/subtareas', 'SubtareasController@store');
    $r->post('/subtareas/{id}/estado', 'SubtareasController@updateEstado');
    $r->post('/subtareas/{id}/editar', 'SubtareasController@update');
    $r->post('/subtareas/{id}/eliminar', 'SubtareasController@destroy');

    // Notas
    $r->get('/notas', 'NotasController@index');
    $r->post('/notas', 'NotasController@store');
    $r->post('/notas/{id}/editar', 'NotasController@update');
    $r->post('/notas/{id}/pin', 'NotasController@pin');
    $r->post('/notas/{id}/eliminar', 'NotasController@destroy');
    $r->get('/notas/entidad', 'NotasController@listByEntity');

    // Usuarios (GOD only) — doble capa: RoleMiddleware en ruta + requireRole en controlador
    $r->get('/admin/usuarios', 'UsuariosController@index', [new RoleMiddleware(['GOD'])]);
    $r->post('/admin/usuarios', 'UsuariosController@store', [new RoleMiddleware(['GOD'])]);
    $r->post('/admin/usuarios/{id}/editar', 'UsuariosController@update', [new RoleMiddleware(['GOD'])]);
    $r->post('/admin/usuarios/{id}/estado', 'UsuariosController@toggleEstado', [new RoleMiddleware(['GOD'])]);
    $r->post('/admin/usuarios/{id}/eliminar', 'UsuariosController@destroy', [new RoleMiddleware(['GOD'])]);

    // Evidencias
    $r->post('/evidencias/subir', 'EvidenciasController@upload');
    $r->get('/evidencias/entidad', 'EvidenciasController@listByEntidad');
    $r->get('/evidencias/{id}/descargar', 'EvidenciasController@download');
    $r->post('/evidencias/{id}/eliminar', 'EvidenciasController@destroy');

    // Notificaciones (AJAX)
    $r->get('/notificaciones', 'NotificacionesController@index');
    $r->post('/notificaciones/{id}/leer', 'NotificacionesController@markRead');
    $r->post('/notificaciones/leer-todas', 'NotificacionesController@markAllRead');
});
