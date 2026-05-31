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
    public function __construct(
        private readonly ?object $inscricaoRepository = null,
        private readonly ?object $eventoRepository = null,
        private readonly ?object $logRepository = null,
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
        summary: 'Listar voluntarios inscritos em um evento',
        description: 'Apenas a instituição dona do evento pode acessar.',
        security: [['cookieAuth' => []]],
        tags: ['Inscricoes'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: 'ID do evento', schema: new OA\Schema(type: 'integer')),   
        ],
        responses: [ 
            new OA\Response(response: 200, description: 'Lista de inscritos'),
            new OA\Response(response: 401, description: 'Nao autenticado', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 403, description: 'Permissao negada', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 404, description: 'Evento nao encontrado', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),        
            ]
        )]
            public function inscritos(Request $request, int $id): Response
            {
                $auth = $this->requireInstituicao($request);

                if ($auth instanceof Response) {
                    return $auth;
                }

                $evento = $this->eventoRepository()->findById($id);

                if (!$evento){
                    return Response::json([
                        'status' => 'error',
                        'message' => 'Evento não encontrado.',
                    ], 404);
                }
                       if((int) $evento['id_instituicao'] !== (int) $auth['profile']['id']) {
                    return Response::json([
                        'status' => 'error',
                        'message' => 'Permissão negada.',
                    ], 403);
                }

             
                return Response::json([
                    'status' => 'success',
                    'data' => $this->inscricaoRepository()->listByEvento($id),
                ]);
            }


            #[OA\Patch(
                path: '/api/eventos/{id}/inscritos/{voluntario_id}/status',
                summary: 'Atualizar status de inscrição de um voluntário',
                description: 'Apenas a instituição dona do evento pode aprovar ou rejeitar inscrições. Status permitido: pendente, aprovado, rejeitado.',
                security: [['cookieAuth' => []]],
                tags: ['Inscricoes'],
                parameters: [
                    new OA\Parameter(name: 'id', in: 'path', required: true, description: 'ID do evento', schema: new OA\Schema(type: 'integer')),
                    new OA\Parameter(name: 'voluntario_id', in: 'path', required: true, description: 'ID do voluntário', schema: new OA\Schema(type: 'integer')),
                ],
                requestBody: new OA\RequestBody(
                    required: true,
                    content: new OA\JsonContent(
                        required: ['status'],
                        properties: [
                            new OA\Property(property: 'status', type: 'string', enum: ['pendente', 'aprovado', 'rejeitado'], example: 'aprovado'),
                        ]
                    )
                ),
                responses: [
                    new OA\Response(response: 200, description: 'Status atualizado'),
                    new OA\Response(response: 401, description: 'Nao autenticado', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
                    new OA\Response(response: 403, description: 'Permissao negada', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
                    new OA\Response(response: 404, description: 'Evento ou voluntário nao encontrado', content: new OA\JsonContent(ref: '#/components/schemas/ErrorReponse')),
                    new OA\Response(response: 422, description: 'Status invalido', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')),
                ]
            )]

            public function updateStatus(Request $request, int $id, int $voluntarioId): Response
            {
                $auth = $this->requireInstituicao($request);

                if ($auth instanceof Response) {
                    return $auth; 
                }

                $evento = $this->eventoRepository()->findById($id);

                if(!$evento){
                    return Response::json([
                        'status' => 'error',
                        'message' => 'Evento não encontrado.',
                    ], 404);
                }


                if ((int) $evento['id_instituicao'] !== (int) $auth['profile']['id']) {
                    return Response::json([
                        'status' => 'error',
                        'message' => 'Permission denied.',
                    ], 403);
                }

                $novoStatus = (string) $request->input('status', '');

                if (!$this->inscricaoRepository()->isAllowedStatus($novoStatus)) {
                    return Response::json([
                        'status'  => 'error',
                        'message' => 'Status invalido. Use: pendente, aprovado ou rejeitado.',
                        'fields'  => ['status'],
                    ], 422);
                }


                $inscricao = $this->inscricaoRepository()->findInscricao($voluntarioId, $id);

                if (!$inscricao) {
                    return Response::json([
                        'status' => 'error',
                        'message' => 'Inscricao nao encontrada.',
                    ] , 404);
                }

                $this->inscricaoRepository()->updateStatus($voluntarioId, $id, $novoStatus);

                $this->logRepository()->create(
                    'STATUS_INSCRICAO_ATUALIZADO',
                    "Inscricao do voluntario {$voluntarioId} no evento {$id} atualizada para '{$novoStatus}'.",
                    'info',
                    $request,
                    (int) $auth['profile']['id'],
                    'instituicao'
                );

                return Response::json([
                    'status' => 'success',
                    'message' => "Inscricao atualizada para '{$novoStatus}'.",
                    'data' => [
                        'id_evento'     => $id,
                        'id_voluntario' => $voluntarioId,
                        'status'        => $novoStatus,
                    ],
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
