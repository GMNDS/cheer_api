<?php

namespace Cheer\Controllers;

use Cheer\Core\Database;
use Cheer\Core\Response;
use OpenApi\Attributes as OA;
use Throwable;

final class HealthController
{
    #[OA\Get(
        path: '/health',
        summary: 'Status da API',
        tags: ['Health'],
        responses: [
            new OA\Response(response: 200, description: 'API ativa'),
        ]
    )]
    public function index(): Response
    {
        return Response::json([
            'status' => 'success',
            'message' => 'Cheer API running',
        ]);
    }

    #[OA\Get(
        path: '/health/database',
        summary: 'Status da conexao com banco',
        tags: ['Health'],
        responses: [
            new OA\Response(response: 200, description: 'Banco conectado'),
            new OA\Response(response: 500, description: 'Erro de conexao', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function database(): Response
    {
        try {
            Database::connection()->query('SELECT 1');

            return Response::json([
                'status' => 'success',
                'message' => 'Database connection ok',
            ]);
        } catch (Throwable) {
            return Response::json([
                'status' => 'error',
                'message' => 'Database connection failed',
            ], 500);
        }
    }
}
