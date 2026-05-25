<?php

namespace Cheer\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Info(
    version: '0.1.0',
    title: 'Cheer API',
    description: 'BFF da aplicacao Cheer integrado com Authentik e banco local.'
)]
#[OA\Server(
    url: 'http://localhost:8000',
    description: 'Servidor local'
)]
#[OA\SecurityScheme(
    securityScheme: 'cookieAuth',
    type: 'apiKey',
    description: 'Sessao HttpOnly criada pelo BFF apos login no Authentik.',
    name: 'cheer_session',
    in: 'cookie'
)]
final class OpenApiInfo
{
}
