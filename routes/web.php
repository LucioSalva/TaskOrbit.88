<?php
use App\Middleware\AuthMiddleware;

$auth = [AuthMiddleware::class];

// Public routes
$router->get('/', function() {
    $base = rtrim(getenv('APP_URL') ?: '', '/');
    header('Location: ' . $base . '/login');
    exit;
});
$router->get('/login', 'AuthController@showLogin');
$router->post('/login', 'AuthController@login');
$router->post('/logout', 'AuthController@logout');
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
    $r->post('/notas/{id}/eliminar', 'NotasController@destroy');

    // Usuarios (GOD only)
    $r->get('/admin/usuarios', 'UsuariosController@index');
    $r->post('/admin/usuarios', 'UsuariosController@store');
    $r->post('/admin/usuarios/{id}/editar', 'UsuariosController@update');
    $r->post('/admin/usuarios/{id}/estado', 'UsuariosController@toggleEstado');
    $r->post('/admin/usuarios/{id}/eliminar', 'UsuariosController@destroy');

    // Notificaciones (AJAX)
    $r->get('/notificaciones', 'NotificacionesController@index');
    $r->post('/notificaciones/{id}/leer', 'NotificacionesController@markRead');
    $r->post('/notificaciones/leer-todas', 'NotificacionesController@markAllRead');
});
