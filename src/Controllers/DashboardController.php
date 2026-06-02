<?php

namespace Cheer\Controllers;

use Cheer\Core\Auth;
use Cheer\Core\Request;
use Cheer\Core\Response;
use Cheer\Repositories\DashboardRepository;
use Cheer\Repositories\LogRepository;
use OpenApi\Attributes as OA;
use Throwable;

final class DashboardController
{
    public function __construct(
        private readonly ?object $dashboardRepository = null,
        private readonly ?object $logRepository = null,
    ) {
    }

    #[OA\Get(
        path: '/api/dashboard/instituicao',
        summary: 'Dashboard da instituicao autenticada',
        security: [['cookieAuth' => []]],
        tags: ['Dashboard'],
        responses: [
            new OA\Response(response: 200, description: 'Dados agregados do dashboard', content: new OA\JsonContent(ref: '#/components/schemas/DashboardInstituicaoResponse')),
            new OA\Response(response: 401, description: 'Nao autenticado', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 403, description: 'Permissao negada', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function instituicao(Request $request): Response
    {
        $auth = $this->requireInstituicao($request);

        if ($auth instanceof Response) {
            return $auth;
        }

        return Response::json([
            'status' => 'success',
            'data' => $this->dashboardRepository()->institutionDashboard((int) $auth['profile']['id']),
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

    private function dashboardRepository(): object
    {
        return $this->dashboardRepository ?? new DashboardRepository();
    }

    private function logRepository(): object
    {
        return $this->logRepository ?? new LogRepository();
    }
}
