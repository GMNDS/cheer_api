<?php

namespace Cheer\Controllers;

use Cheer\Core\Auth;
use Cheer\Core\Request;
use Cheer\Core\Response;
use Cheer\Repositories\DashboardRepository;
use OpenApi\Attributes as OA;
use Throwable;

final class DashboardController
{
    public function __construct(private readonly ?object $dashboardRepository = null)
    {
    }

    #[OA\Get(
        path: '/api/dashboard/instituicao',
        summary: 'Dashboard da instituicao autenticada',
        security: [['cookieAuth' => []]],
        tags: ['Dashboard'],
        responses: [
            new OA\Response(response: 200, description: 'Dados agregados do dashboard', content: new OA\JsonContent(ref: '#/components/schemas/InstitutionDashboardResponse')),
            new OA\Response(response: 401, description: 'Nao autenticado', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 403, description: 'Permissao negada', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function instituicao(Request $request): Response
    {
        $auth = $this->requireInstituicao();

        if ($auth instanceof Response) {
            return $auth;
        }

        return Response::json([
            'status' => 'success',
            'data' => $this->dashboardRepository()->institutionDashboard((int) $auth['profile']['id']),
        ]);
    }

    /** @return array{profile: array<string, mixed>}|Response */
    private function requireInstituicao(): array|Response
    {
        try {
            $profile = Auth::profile();

            if (($profile['tipo'] ?? null) !== 'instituicao') {
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
}
