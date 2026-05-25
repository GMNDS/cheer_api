<?php

use Cheer\Core\Env;

return [
    'name' => Env::get('APP_NAME', 'Cheer API'),
    'env' => Env::get('APP_ENV', 'local'),
    'debug' => Env::get('APP_DEBUG', false),
    'url' => Env::get('APP_URL', 'http://localhost:8000'),
];
