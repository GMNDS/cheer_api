<?php

namespace Cheer\Controllers;

use Cheer\Core\Auth;
use Cheer\Core\Request;
use Cheer\Core\Response;
use Cheer\Repositories\InscricaoRepository;
use Cheer\Repositories\LogRepository;
use OpenApi\Attributes as OA;
use Throwable;

final class InscricaoController
{
    #[OA\Post(
        path: '/api/eventos/inscrever',
        summary: 'Inscrever voluntario em evento',
        security: [['cookieAuth' => []]],
        tags: ['Inscricoes'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/CreateInscricaoRequest')),
        responses: [
            new OA\Response(response: 201, description: 'Inscricao criada', content: new OA\JsonContent(ref: '#/components/schemas/InscricaoCreatedResponse')),
            new OA\Response(response: 401, description: 'Nao autenticado', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 403, description: 'Permissao negada', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 422, description: 'Payload invalido', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')),
            new OA\Response(response: 500, description: 'Erro interno', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function store(Request $request): Response
    {
        $auth = $this->requireVoluntario($request);

        if ($auth instanceof Response) {
            return $auth;
        }

        $eventoId = (int) $request->input('id_evento', $request->input('evento_id', 0));

        if ($eventoId <= 0) {
            return Response::json([
                'status' => 'error',
                'message' => 'Missing required field: id_evento.',
            ], 422);
        }

        try {
            (new InscricaoRepository())->create((int) $auth['profile']['id'], $eventoId);
            (new LogRepository())->create(
                'INSCRICAO_EVENTO',
                "Inscricao no evento {$eventoId}.",
                'info',
                $request,
                (int) $auth['profile']['id'],
                'voluntario'
            );

            return Response::json([
                'status' => 'success',
                'data' => ['status' => 'pendente'],
            ], 201);
        } catch (Throwable $exception) {
            try {
                (new LogRepository())->create(
                    'ERRO_INSCRICAO_EVENTO',
                    $exception->getMessage(),
                    'error',
                    $request,
                    (int) $auth['profile']['id'],
                    'voluntario'
                );
            } catch (Throwable) {
            }

            return Response::json([
                'status' => 'error',
                'message' => $exception->getMessage(),
            ], 500);
        }
    }

    #[OA\Get(
        path: '/api/minhas-inscricoes',
        summary: 'Listar inscricoes do voluntario autenticado',
        security: [['cookieAuth' => []]],
        tags: ['Inscricoes'],
        responses: [
            new OA\Response(response: 200, description: 'Inscricoes do voluntario', content: new OA\JsonContent(ref: '#/components/schemas/InscricoesResponse')),
            new OA\Response(response: 401, description: 'Nao autenticado', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 403, description: 'Permissao negada', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function minhasInscricoes(Request $request): Response
    {
        $auth = $this->requireVoluntario($request);

        if ($auth instanceof Response) {
            return $auth;
        }

        return Response::json([
            'status' => 'success',
            'data' => (new InscricaoRepository())->listByVoluntario((int) $auth['profile']['id']),
        ]);
    }

    /** @return array{profile: array<string, mixed>}|Response */
    private function requireVoluntario(Request $request): array|Response
    {
        try {
            $profile = Auth::profile();

            if (($profile['tipo'] ?? null) !== 'voluntario') {
                (new LogRepository())->create(
                    'PERMISSAO_NEGADA',
                    'Expected voluntario.',
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
}
