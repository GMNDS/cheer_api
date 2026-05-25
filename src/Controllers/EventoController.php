<?php

namespace Cheer\Controllers;

use Cheer\Core\Auth;
use Cheer\Core\Database;
use Cheer\Core\Request;
use Cheer\Core\Response;
use Cheer\Repositories\EnderecoRepository;
use Cheer\Repositories\EventoRepository;
use Cheer\Repositories\LogRepository;
use OpenApi\Attributes as OA;
use Throwable;

final class EventoController
{
    #[OA\Get(
        path: '/api/eventos',
        summary: 'Listar eventos disponiveis',
        tags: ['Eventos'],
        responses: [
            new OA\Response(response: 200, description: 'Eventos disponiveis', content: new OA\JsonContent(ref: '#/components/schemas/EventosResponse')),
            new OA\Response(response: 500, description: 'Erro interno', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function index(): Response
    {
        return Response::json([
            'status' => 'success',
            'data' => (new EventoRepository())->listAvailable(),
        ]);
    }

    #[OA\Post(
        path: '/api/eventos',
        summary: 'Criar evento',
        security: [['cookieAuth' => []]],
        tags: ['Eventos'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/CreateEventoRequest')),
        responses: [
            new OA\Response(response: 201, description: 'Evento criado', content: new OA\JsonContent(ref: '#/components/schemas/IdResponse')),
            new OA\Response(response: 401, description: 'Nao autenticado', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 403, description: 'Permissao negada', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 422, description: 'Payload invalido', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')),
            new OA\Response(response: 500, description: 'Erro interno', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function store(Request $request): Response
    {
        $auth = $this->requireProfile($request, 'instituicao');

        if ($auth instanceof Response) {
            return $auth;
        }

        $data = $request->all();
        $address = is_array($data['endereco'] ?? null) ? $data['endereco'] : $data;
        $missing = array_merge(
            $this->missing($data, ['titulo', 'tipo_evento']),
            $this->missing($address, ['rua', 'bairro', 'cidade', 'uf'])
        );

        if (!isset($data['data_hora_inicio']) && !isset($data['data'])) {
            $missing[] = 'data_hora_inicio';
        }

        if (!isset($address['codigo_postal']) && !isset($address['cep'])) {
            $missing[] = 'codigo_postal';
        }

        if ($missing !== []) {
            return Response::json([
                'status' => 'error',
                'message' => 'Missing required fields.',
                'fields' => array_values(array_unique($missing)),
            ], 422);
        }

        try {
            Database::connection()->beginTransaction();

            $enderecoId = (new EnderecoRepository())->create($address);
            $eventoId = (new EventoRepository())->create((int) $auth['profile']['id'], $enderecoId, $data);

            (new LogRepository())->create(
                'CRIACAO_EVENTO',
                "Evento {$eventoId} criado.",
                'info',
                $request,
                (int) $auth['profile']['id'],
                'instituicao'
            );

            Database::connection()->commit();

            return Response::json([
                'status' => 'success',
                'data' => ['id' => $eventoId],
            ], 201);
        } catch (Throwable $exception) {
            if (Database::connection()->inTransaction()) {
                Database::connection()->rollBack();
            }

            $this->logWriteError($request, 'ERRO_CRIACAO_EVENTO', $exception->getMessage(), $auth['profile']);

            return Response::json([
                'status' => 'error',
                'message' => $exception->getMessage(),
            ], 500);
        }
    }

    #[OA\Get(
        path: '/api/meus-eventos',
        summary: 'Listar eventos da instituicao autenticada',
        security: [['cookieAuth' => []]],
        tags: ['Eventos'],
        responses: [
            new OA\Response(response: 200, description: 'Eventos da instituicao', content: new OA\JsonContent(ref: '#/components/schemas/EventosResponse')),
            new OA\Response(response: 401, description: 'Nao autenticado', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 403, description: 'Permissao negada', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function meusEventos(Request $request): Response
    {
        $auth = $this->requireProfile($request, 'instituicao');

        if ($auth instanceof Response) {
            return $auth;
        }

        return Response::json([
            'status' => 'success',
            'data' => (new EventoRepository())->listByInstituicao((int) $auth['profile']['id']),
        ]);
    }

    /** @return array{profile: array<string, mixed>}|Response */
    private function requireProfile(Request $request, string $type): array|Response
    {
        try {
            $profile = Auth::profile();

            if ($profile['tipo'] !== $type) {
                (new LogRepository())->create(
                    'PERMISSAO_NEGADA',
                    "Expected {$type}, got {$profile['tipo']}.",
                    'warning',
                    $request,
                    (int) $profile['id'],
                    (string) $profile['tipo']
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

    /** @param array<string, mixed> $data */
    private function missing(array $data, array $required): array
    {
        return array_values(array_filter($required, static fn (string $field): bool => empty($data[$field])));
    }

    /** @param array<string, mixed> $profile */
    private function logWriteError(Request $request, string $type, string $message, array $profile): void
    {
        try {
            (new LogRepository())->create($type, $message, 'error', $request, (int) $profile['id'], (string) $profile['tipo']);
        } catch (Throwable) {
        }
    }
}
