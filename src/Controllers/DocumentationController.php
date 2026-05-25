<?php

namespace Cheer\Controllers;

use Cheer\Core\Response;
use OpenApi\Attributes as OA;
use OpenApi\Generator;

final class DocumentationController
{
    #[OA\Get(
        path: '/openapi.json',
        summary: 'Especificacao OpenAPI',
        tags: ['Docs'],
        responses: [
            new OA\Response(response: 200, description: 'Documento OpenAPI em JSON'),
        ]
    )]
    public function openapi(): Response
    {
        $openapi = (new Generator())->generate([dirname(__DIR__)]);

        return Response::content(
            $openapi->toJson(),
            'application/json; charset=utf-8'
        );
    }

    #[OA\Get(
        path: '/docs',
        summary: 'Documentacao Scalar',
        tags: ['Docs'],
        responses: [
            new OA\Response(response: 200, description: 'Interface Scalar'),
        ]
    )]
    public function scalar(): Response
    {
        return Response::content(<<<'HTML'
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Cheer API Docs</title>
  <style>
    body { margin: 0; }
  </style>
</head>
<body>
  <script
    id="api-reference"
    data-url="/openapi.json"
    data-theme="default"
    data-layout="modern"
  ></script>
  <script src="https://cdn.jsdelivr.net/npm/@scalar/api-reference"></script>
</body>
</html>
HTML, 'text/html; charset=utf-8');
    }
}
