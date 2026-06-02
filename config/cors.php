<?php

use Cheer\Core\Env;

return [
    'allowed_origin' => Env::get('CORS_ALLOWED_ORIGIN', 'http://localhost:5173'),
    'allowed_methods' => Env::get('CORS_ALLOWED_METHODS', 'GET, POST, PUT, PATCH, DELETE, OPTIONS'),
    'allowed_headers' => Env::get('CORS_ALLOWED_HEADERS', 'Content-Type, X-Requested-With'),
    'allow_credentials' => Env::get('CORS_ALLOW_CREDENTIALS', true),
];
