<?php

$_ENV['DB_CONNECTION'] = 'sqlite';
$_ENV['DB_DATABASE'] = ':memory:';
$_ENV['APP_DEBUG'] = 'true';
$_ENV['AUTHENTIK_API_BASE_URL'] = '';
$_ENV['AUTHENTIK_API_TOKEN'] = '';

putenv('DB_CONNECTION=sqlite');
putenv('DB_DATABASE=:memory:');
putenv('APP_DEBUG=true');
putenv('AUTHENTIK_API_BASE_URL=');
putenv('AUTHENTIK_API_TOKEN=');

require dirname(__DIR__) . '/vendor/autoload.php';