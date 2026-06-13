<?php

use Cheer\Core\Env;

$appUrl = rtrim((string) Env::get('APP_URL', 'http://localhost:8000'), '/');
$baseUrl = rtrim((string) Env::get('AUTHENTIK_BASE_URL', ''), '/');
$applicationSlug = trim((string) Env::get('AUTHENTIK_APPLICATION_SLUG', 'cheer-api'), '/');
$issuer = (string) Env::get(
    'AUTHENTIK_ISSUER',
    $baseUrl !== '' ? "{$baseUrl}/application/o/{$applicationSlug}/" : ''
);

return [
    'base_url' => $baseUrl,
    'api_base_url' => Env::get('AUTHENTIK_API_BASE_URL', $baseUrl !== '' ? "{$baseUrl}/api/v3" : ''),
    'api_token' => Env::get('AUTHENTIK_API_TOKEN', ''),
    'verify_ssl' => Env::get('AUTHENTIK_VERIFY_SSL', true),
    'ca_bundle' => Env::get('AUTHENTIK_CA_BUNDLE', ''),
    'application_slug' => $applicationSlug,
    'client_id' => Env::get('AUTHENTIK_CLIENT_ID', ''),
    'client_secret' => Env::get('AUTHENTIK_CLIENT_SECRET', ''),
    'redirect_uri' => Env::get('AUTHENTIK_REDIRECT_URI', 'http://localhost:8000/api/auth/callback'),
    'mobile_callback_uri' => Env::get('AUTHENTIK_MOBILE_CALLBACK_URI', 'http://localhost:8000/api/auth/mobile/callback'),
    'mobile_app_redirect_uris' => array_filter(array_map('trim', explode(',', (string) Env::get('AUTHENTIK_MOBILE_APP_REDIRECT_URIS', 'cheer://auth/callback')))),
    'mobile_logout_callback_uri' => Env::get('AUTHENTIK_MOBILE_LOGOUT_CALLBACK_URI', "{$appUrl}/api/auth/mobile/logout/callback"),
    'mobile_logout_redirect_uri' => Env::get('AUTHENTIK_MOBILE_LOGOUT_REDIRECT_URI', 'cheer://auth/logout'),
    'mobile_login_prompt' => Env::get('AUTHENTIK_MOBILE_LOGIN_PROMPT', 'login'),
    'mobile_code_ttl' => Env::get('AUTHENTIK_MOBILE_CODE_TTL', 300),
    'post_login_redirect_uri' => Env::get('AUTHENTIK_POST_LOGIN_REDIRECT_URI', 'http://localhost:5173'),
    'post_logout_redirect_uri' => Env::get('AUTHENTIK_POST_LOGOUT_REDIRECT_URI', 'http://localhost:5173'),
    'issuer' => $issuer,
    'jwks_url' => Env::get('AUTHENTIK_JWKS_URL', $issuer !== '' ? "{$issuer}jwks/" : ''),
    'authorization_url' => $baseUrl !== '' ? "{$baseUrl}/application/o/authorize/" : '',
    'token_url' => $baseUrl !== '' ? "{$baseUrl}/application/o/token/" : '',
    'end_session_url' => Env::get('AUTHENTIK_END_SESSION_URL', $baseUrl !== '' ? "{$baseUrl}/application/o/{$applicationSlug}/end-session/" : ''),
    'userinfo_url' => $baseUrl !== '' ? "{$baseUrl}/application/o/userinfo/" : '',
    'scopes' => explode(' ', (string) Env::get('AUTHENTIK_SCOPES', 'openid profile email')),
    'required_scopes' => array_filter(explode(' ', (string) Env::get('AUTHENTIK_REQUIRED_SCOPES', ''))),
    'user_path' => Env::get('AUTHENTIK_USER_PATH', 'users'),
    'local_user_identifier' => Env::get('AUTHENTIK_LOCAL_USER_IDENTIFIER', 'uid'),
    'voluntario_group_name' => Env::get('AUTHENTIK_VOLUNTARIO_GROUP_NAME', 'voluntario'),
    'instituicao_group_name' => Env::get('AUTHENTIK_INSTITUICAO_GROUP_NAME', 'instituicao'),
    'voluntario_group_uuid' => Env::get('AUTHENTIK_VOLUNTARIO_GROUP_UUID', ''),
    'instituicao_group_uuid' => Env::get('AUTHENTIK_INSTITUICAO_GROUP_UUID', ''),
];
