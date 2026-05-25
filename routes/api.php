<?php

use Cheer\Controllers\AuthController;
use Cheer\Controllers\EventoController;
use Cheer\Controllers\HealthController;
use Cheer\Controllers\InscricaoController;
use Cheer\Controllers\RegistrationController;
use Cheer\Core\Router;

/** @var Router $router */

$health = new HealthController();
$auth = new AuthController();
$eventos = new EventoController();
$inscricoes = new InscricaoController();
$registration = new RegistrationController();

$router->get('/', [$health, 'index']);
$router->get('/health', [$health, 'index']);
$router->get('/health/database', [$health, 'database']);
$router->get('/auth/config', [$auth, 'config']);
$router->get('/auth/login', [$auth, 'login']);
$router->get('/auth/callback', [$auth, 'callback']);
$router->get('/auth/logout', [$auth, 'logout']);
$router->post('/auth/logout', [$auth, 'logout']);
$router->post('/auth/register-voluntario', [$registration, 'registerVoluntario']);
$router->post('/auth/register-instituicao', [$registration, 'registerInstituicao']);
$router->get('/me', [$auth, 'me']);
$router->get('/eventos', [$eventos, 'index']);
$router->post('/eventos', [$eventos, 'store']);
$router->get('/meus-eventos', [$eventos, 'meusEventos']);
$router->post('/eventos/inscrever', [$inscricoes, 'store']);
$router->get('/minhas-inscricoes', [$inscricoes, 'minhasInscricoes']);

$router->get('/api/me', [$auth, 'me']);
$router->get('/api/auth/config', [$auth, 'config']);
$router->get('/api/auth/login', [$auth, 'login']);
$router->get('/api/auth/callback', [$auth, 'callback']);
$router->get('/api/auth/logout', [$auth, 'logout']);
$router->post('/api/auth/logout', [$auth, 'logout']);
$router->post('/api/auth/register-voluntario', [$registration, 'registerVoluntario']);
$router->post('/api/auth/register-instituicao', [$registration, 'registerInstituicao']);
$router->get('/api/eventos', [$eventos, 'index']);
$router->post('/api/eventos', [$eventos, 'store']);
$router->get('/api/meus-eventos', [$eventos, 'meusEventos']);
$router->post('/api/eventos/inscrever', [$inscricoes, 'store']);
$router->get('/api/minhas-inscricoes', [$inscricoes, 'minhasInscricoes']);
