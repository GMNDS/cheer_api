<?php

use Cheer\Core\Env;

return [
    'provider_url' => Env::get('GEOCODING_PROVIDER_URL', ''),
    'user_agent' => Env::get('GEOCODING_USER_AGENT', (string) Env::get('APP_NAME', 'Cheer API')),
    'timeout' => (int) Env::get('GEOCODING_TIMEOUT', 10),
];