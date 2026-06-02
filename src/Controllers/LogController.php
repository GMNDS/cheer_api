<?php

namespace Cheer\Controllers;

use Cheer\Core\Auth;
use Cheer\Core\Request;
use Cheer\Core\Response;
use Cheer\Repositories\LogRepository;
use OpenApi\Attributes as OA;
use Throwable;

final class LogController
{
    public function __construct(
        private readonly ?object $logRepository = null,
    ) {
    }

    #[OA\Get(
        path: '/api/logs',
        summary: 'Listar logs da instituicao autenticada',
        security: [['cookieAuth' => []]],
        tags: ['Logs'],
        parameters: [
            new OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', minimum: 1)),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 100)),
            new OA\Parameter(name: 'nivel', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'tipo_evento', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'origem', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'data_inicio', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date-time')),
            new OA\Parameter(name: 'data_fim', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date-time')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Logs da instituicao', content: new OA\JsonContent(ref: '#/components/schemas/LogsResponse')),
            new OA\Response(response: 401, description: 'Nao autenticado', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 403, description: 'Permissao negada', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function index(Request $request): Response
    {
        $auth = $this->requireInstituicao($request);

        if ($auth instanceof Response) {
            return $auth;
        }

        $filters = [
            'page' => $request->input('page', 1),
            'per_page' => $request->input('per_page', 20),
            'nivel' => $request->input('nivel'),
            'tipo_evento' => $request->input('tipo_evento'),
            'origem' => $request->input('origem'),
            'data_inicio' => $request->input('data_inicio'),
            'data_fim' => $request->input('data_fim'),
        ];

        return Response::json([
            'status' => 'success',
            'data' => $this->logRepository()->listByUsuario((int) $auth['profile']['id'], 'instituicao', $filters),
        ]);
    }

    /** @return array{profile: array<string, mixed>}|Response */
    private function requireInstituicao(Request $request): array|Response
    {
        try {
            $profile = Auth::profile();

            if (($profile['tipo'] ?? null) !== 'instituicao') {
                $this->logRepository()->create(
                    'PERMISSAO_NEGADA',
                    'Expected instituicao.',
                    'warning',
                    $request,
                    (int) ($profile['id'] ?? 0),
                    (string) ($profile['tipo'] ?? null)
                );

                return Response::json([
                    'status' => 'error',
                    'message' => 'Permission denied.',
                ], 403);
            }

            return ['profile' => $profile];
        } catch (Throwable $exception) {
            return Response::json([
                'status' => 'error',
                'message' => $exception->getMessage(),
            ], 401);
        }
    }

    private function logRepository(): object
    {
        return $this->logRepository ?? new LogRepository();
    }
}
