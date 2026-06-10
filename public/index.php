<?php

use Cheer\Core\Request;
use Cheer\Core\Router;
use Cheer\Core\Session;
use Cheer\Core\Config;
use Cheer\Core\Cors;
use Dotenv\Dotenv;

$rootPath = dirname(__DIR__);

require_once $rootPath . "/vendor/autoload.php";

if (is_file($rootPath . '/.env')) {
    Dotenv::createImmutable($rootPath)->safeLoad();
}

$allowCredentials = (bool) Config::get('cors.allow_credentials', true);
$allowedOrigin = Cors::allowedOrigin(
    $_SERVER['HTTP_ORIGIN'] ?? null,
    (string) Config::get('cors.allowed_origin', 'http://localhost:5173'),
    $allowCredentials
);

if ($allowedOrigin !== null) {
    header("Access-Control-Allow-Origin: {$allowedOrigin}");
    header('Vary: Origin');
}

if ($allowCredentials) {
    header('Access-Control-Allow-Credentials: true');
}

header('Access-Control-Allow-Methods: ' . Config::get('cors.allowed_methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS'));
header('Access-Control-Allow-Headers: ' . Config::get('cors.allowed_headers', 'Content-Type, X-Requested-With, Authorization'));

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

Session::start();

$router = new Router();

require $rootPath . '/routes/api.php';

$router->dispatch(Request::capture())->send();
