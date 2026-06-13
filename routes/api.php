<?php

use Cheer\Controllers\AuthController;
use Cheer\Controllers\DashboardController;
use Cheer\Controllers\DocumentationController;
use Cheer\Controllers\EventoController;
use Cheer\Controllers\HealthController;
use Cheer\Controllers\InscricaoController;
use Cheer\Controllers\LogController;
use Cheer\Controllers\RegistrationController;
use Cheer\Core\Router;

/** @var Router $router */

$health = new HealthController();
$auth = new AuthController();
$dashboard = new DashboardController();
$eventos = new EventoController();
$inscricoes = new InscricaoController();
$logs = new LogController();
$registration = new RegistrationController();
$docs = new DocumentationController();

$router->get('/', [$health, 'index']);
$router->get('/docs', [$docs, 'scalar']);
$router->get('/api/docs', [$docs, 'scalar']);
$router->get('/openapi.json', [$docs, 'openapi']);
$router->get('/api/openapi.json', [$docs, 'openapi']);

$router->get('/health', [$health, 'index']);
$router->get('/health/database', [$health, 'database']);

$router->get('/auth/config', [$auth, 'config']);
$router->get('/auth/login', [$auth, 'login']);
$router->get('/auth/callback', [$auth, 'callback']);
$router->get('/auth/mobile/login', [$auth, 'mobileLogin']);
$router->get('/auth/mobile/callback', [$auth, 'mobileCallback']);
$router->get('/auth/mobile/logout', [$auth, 'mobileLogout']);
$router->post('/auth/mobile/logout', [$auth, 'mobileLogoutUrl']);
$router->get('/auth/mobile/logout/callback', [$auth, 'mobileLogoutCallback']);
$router->post('/auth/mobile/exchange', [$auth, 'mobileExchange']);
$router->get('/auth/logout', [$auth, 'logout']);
$router->post('/auth/logout', [$auth, 'logout']);
$router->post('/auth/register-voluntario', [$registration, 'registerVoluntario']);
$router->post('/auth/register-instituicao', [$registration, 'registerInstituicao']);
$router->get('/me', [$auth, 'me']);

$router->get('/eventos', [$eventos, 'index']);
$router->post('/eventos', [$eventos, 'store']);
$router->get('/eventos/{id}', [$eventos, 'show']);
$router->put('/eventos/{id}', [$eventos, 'update']);
$router->delete('/eventos/{id}', [$eventos, 'destroy']);
$router->get('/meus-eventos', [$eventos, 'meusEventos']);
$router->post('/eventos/inscrever', [$inscricoes, 'store']);
$router->get('/eventos/{id}/inscritos', [$inscricoes, 'inscritos']);
$router->patch('/eventos/{id}/inscritos/{voluntario_id}/status', [$inscricoes, 'updateStatus']);
$router->get('/minhas-inscricoes', [$inscricoes, 'minhasInscricoes']);
$router->get('/dashboard/instituicao', [$dashboard, 'instituicao']);
$router->get('/logs', [$logs, 'index']);

$router->get('/api/me', [$auth, 'me']);
$router->get('/api/auth/config', [$auth, 'config']);
$router->get('/api/auth/login', [$auth, 'login']);
$router->get('/api/auth/callback', [$auth, 'callback']);
$router->get('/api/auth/mobile/login', [$auth, 'mobileLogin']);
$router->get('/api/auth/mobile/callback', [$auth, 'mobileCallback']);
$router->get('/api/auth/mobile/logout', [$auth, 'mobileLogout']);
$router->post('/api/auth/mobile/logout', [$auth, 'mobileLogoutUrl']);
$router->get('/api/auth/mobile/logout/callback', [$auth, 'mobileLogoutCallback']);
$router->post('/api/auth/mobile/exchange', [$auth, 'mobileExchange']);
$router->get('/api/auth/logout', [$auth, 'logout']);
$router->post('/api/auth/logout', [$auth, 'logout']);
$router->post('/api/auth/register-voluntario', [$registration, 'registerVoluntario']);
$router->post('/api/auth/register-instituicao', [$registration, 'registerInstituicao']);
$router->get('/api/eventos', [$eventos, 'index']);
$router->post('/api/eventos', [$eventos, 'store']);
$router->get('/api/eventos/{id}', [$eventos, 'show']);
$router->put('/api/eventos/{id}', [$eventos, 'update']);
$router->delete('/api/eventos/{id}', [$eventos, 'destroy']);
$router->get('/api/meus-eventos', [$eventos, 'meusEventos']);
$router->post('/api/eventos/inscrever', [$inscricoes, 'store']);
$router->get('/api/eventos/{id}/inscritos', [$inscricoes, 'inscritos']);
$router->patch('/api/eventos/{id}/inscritos/{voluntario_id}/status', [$inscricoes, 'updateStatus']);
$router->get('/api/minhas-inscricoes', [$inscricoes, 'minhasInscricoes']);
$router->get('/api/dashboard/instituicao', [$dashboard, 'instituicao']);
$router->get('/api/logs', [$logs, 'index']);
