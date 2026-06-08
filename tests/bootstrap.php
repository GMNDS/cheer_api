<?php

$_ENV['DB_CONNECTION'] = 'sqlite';
$_ENV['DB_DATABASE'] = ':memory:';
$_ENV['APP_DEBUG'] = 'true';
$_ENV['AUTHENTIK_API_BASE_URL'] = '';
$_ENV['AUTHENTIK_API_TOKEN'] = '';
$_ENV['CORS_ALLOWED_ORIGIN'] = '*';
$_ENV['CORS_ALLOW_CREDENTIALS'] = 'false';

putenv('DB_CONNECTION=sqlite');
putenv('DB_DATABASE=:memory:');
putenv('APP_DEBUG=true');
putenv('AUTHENTIK_API_BASE_URL=');
putenv('AUTHENTIK_API_TOKEN=');
putenv('CORS_ALLOWED_ORIGIN=*');
putenv('CORS_ALLOW_CREDENTIALS=false');

require dirname(__DIR__) . '/vendor/autoload.php';
