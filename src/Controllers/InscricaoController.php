<?php

namespace Cheer\Controllers;

use Cheer\Core\Auth;
use Cheer\Core\Request;
use Cheer\Core\Response;
use Cheer\Repositories\EventoRepository;
use Cheer\Repositories\InscricaoRepository;
use Cheer\Repositories\LogRepository;
use OpenApi\Attributes as OA;
use Throwable;

final class InscricaoController
{
    public function __construct(
        private readonly ?object $inscricaoRepository = null,
        private readonly ?object $logRepository = null,
        private readonly ?object $eventoRepository = null,
    ) {
    }

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
            $this->inscricaoRepository()->create((int) $auth['profile']['id'], $eventoId);
            $this->logRepository()->create(
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
                $this->logRepository()->create(
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
            'data' => $this->inscricaoRepository()->listByVoluntario((int) $auth['profile']['id']),
        ]);
    }

    #[OA\Get(
        path: '/api/eventos/{id}/inscritos',
        summary: 'Listar inscritos de um evento da instituicao autenticada',
        security: [['cookieAuth' => []]],
        tags: ['Inscricoes'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Inscritos do evento', content: new OA\JsonContent(ref: '#/components/schemas/InscritosEventoResponse')),
            new OA\Response(response: 401, description: 'Nao autenticado', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 403, description: 'Permissao negada', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function inscritos(Request $request, string $id): Response
    {
        $auth = $this->requireInstituicao($request);

        if ($auth instanceof Response) {
            return $auth;
        }

        return Response::json([
            'status' => 'success',
            'data' => $this->inscricaoRepository()->listByEventoForInstituicao((int) $id, (int) $auth['profile']['id']),
        ]);
    }

    #[OA\Patch(
        path: '/api/eventos/{id}/inscritos/{voluntario_id}/status',
        summary: 'Atualizar status de inscrito em evento',
        security: [['cookieAuth' => []]],
        tags: ['Inscricoes'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'voluntario_id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Status atualizado', content: new OA\JsonContent(ref: '#/components/schemas/InscricaoCreatedResponse')),
            new OA\Response(response: 401, description: 'Nao autenticado', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 403, description: 'Permissao negada', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 404, description: 'Inscricao nao encontrada', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 422, description: 'Payload invalido', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')),
        ]
    )]
    public function updateStatus(Request $request, string $id, string $voluntarioId): Response
    {
        $auth = $this->requireInstituicao($request);

        if ($auth instanceof Response) {
            return $auth;
        }

        $status = (string) $request->input('status', '');
        $allowedStatuses = ['pendente', 'aprovado', 'rejeitado'];

        if (!in_array($status, $allowedStatuses, true)) {
            return Response::json([
                'status' => 'error',
                'message' => 'Payload invalido.',
                'fields' => ['status'],
            ], 422);
        }

        $updated = $this->inscricaoRepository()->updateStatusForInstituicao((int) $id, (int) $voluntarioId, (int) $auth['profile']['id'], $status);

        if (!$updated) {
            return Response::json([
                'status' => 'error',
                'message' => 'Inscricao nao encontrada.',
            ], 404);
        }

        $this->logRepository()->create(
            'STATUS_INSCRICAO_ATUALIZADO',
            "Status do voluntario {$voluntarioId} no evento {$id} alterado para {$status}.",
            'info',
            $request,
            (int) $auth['profile']['id'],
            'instituicao'
        );

        return Response::json([
            'status' => 'success',
            'data' => ['status' => $status],
        ]);
    }

    /** @return array{profile: array<string, mixed>}|Response */
    private function requireVoluntario(Request $request): array|Response
    {
        try {
            $profile = Auth::profile();

            if (($profile['tipo'] ?? null) !== 'voluntario') {
                $this->logRepository()->create(
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

    private function inscricaoRepository(): object
    {
        return $this->inscricaoRepository ?? new InscricaoRepository();
    }

    private function eventoRepository(): EventoRepository
    {
        return $this->eventoRepository ?? new EventoRepository();
    }

    private function logRepository(): object
    {
        return $this->logRepository ?? new LogRepository();
    }
}
