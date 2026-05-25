<?php

use Cheer\Core\Env;

return [
    'name' => Env::get('SESSION_NAME', 'cheer_session'),
    'lifetime' => (int) Env::get('SESSION_LIFETIME', 7200),
    'path' => Env::get('SESSION_PATH', '/'),
    'domain' => Env::get('SESSION_DOMAIN', ''),
    'secure' => Env::get('SESSION_SECURE', false),
    'http_only' => Env::get('SESSION_HTTP_ONLY', true),
    'same_site' => Env::get('SESSION_SAME_SITE', 'Lax'),
];
