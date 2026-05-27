<?php

namespace Cheer\Controllers;

use Cheer\Core\Auth;
use Cheer\Core\Database;
use Cheer\Core\Request;
use Cheer\Core\Response;
use Cheer\Repositories\EnderecoRepository;
use Cheer\Repositories\EventoRepository;
use Cheer\Repositories\LogRepository;
use Cheer\Services\DatabaseTransactionManager;
use Cheer\Services\TransactionManagerInterface;
use OpenApi\Attributes as OA;
use Throwable;

final class EventoController
{
    public function __construct(
        private readonly ?TransactionManagerInterface $transactions = null,
        private readonly ?object $enderecoRepository = null,
        private readonly ?object $eventoRepository = null,
        private readonly ?object $logRepository = null,
    ) {
    }

    #[OA\Get(
        path: '/api/eventos',
        summary: 'Listar eventos disponiveis por proximidade',
        description: 'Quando lat e lng forem informados, os eventos sao filtrados e ordenados pela distancia usando raio_km como limite opcional.',
        tags: ['Eventos'],
        parameters: [
            new OA\Parameter(name: 'lat', in: 'query', required: false, description: 'Latitude do ponto de referencia', schema: new OA\Schema(type: 'number', format: 'double')),
            new OA\Parameter(name: 'lng', in: 'query', required: false, description: 'Longitude do ponto de referencia', schema: new OA\Schema(type: 'number', format: 'double')),
            new OA\Parameter(name: 'raio_km', in: 'query', required: false, description: 'Raio maximo para proximidade em quilometros', schema: new OA\Schema(type: 'number', format: 'double', example: 25)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Eventos disponiveis', content: new OA\JsonContent(ref: '#/components/schemas/EventosResponse')),
            new OA\Response(response: 422, description: 'Parametros invalidos', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')),
            new OA\Response(response: 500, description: 'Erro interno', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function index(Request $request): Response
    {
        $nearby = $this->nearbyParameters($request);

        if ($nearby instanceof Response) {
            return $nearby;
        }

        return Response::json([
            'status' => 'success',
            'data' => $this->eventoRepository()->listAvailable($nearby['lat'], $nearby['lng'], $nearby['raio_km']),
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
            $this->transactions()->begin();

            $enderecoId = $this->enderecoRepository()->create($address);
            $eventoId = $this->eventoRepository()->create((int) $auth['profile']['id'], $enderecoId, $data);

            $this->logRepository()->create(
                'CRIACAO_EVENTO',
                "Evento {$eventoId} criado.",
                'info',
                $request,
                (int) $auth['profile']['id'],
                'instituicao'
            );

            $this->transactions()->commit();

            return Response::json([
                'status' => 'success',
                'data' => ['id' => $eventoId],
            ], 201);
        } catch (Throwable $exception) {
            $this->transactions()->rollback();

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
            'data' => $this->eventoRepository()->listByInstituicao((int) $auth['profile']['id']),
        ]);
    }

    /** @return array{profile: array<string, mixed>}|Response */
    private function requireProfile(Request $request, string $type): array|Response
    {
        try {
            $profile = Auth::profile();

            if ($profile['tipo'] !== $type) {
                $this->logRepository()->create(
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

    /**
     * @return array{lat: float, lng: float, raio_km: float}|Response|null
     */
    private function nearbyParameters(Request $request): array|Response|null
    {
        $lat = $request->input('lat');
        $lng = $request->input('lng');
        $radius = $request->input('raio_km', 25);

        $hasLat = $lat !== null && $lat !== '';
        $hasLng = $lng !== null && $lng !== '';

        if ($hasLat !== $hasLng) {
            return Response::json([
                'status' => 'error',
                'message' => 'Missing required fields.',
                'fields' => ['lat', 'lng'],
            ], 422);
        }

        if (!$hasLat) {
            return null;
        }

        $fields = [];

        if (!is_numeric($lat)) {
            $fields[] = 'lat';
        }

        if (!is_numeric($lng)) {
            $fields[] = 'lng';
        }

        if (!is_numeric($radius) || (float) $radius <= 0) {
            $fields[] = 'raio_km';
        }

        $latValue = is_numeric($lat) ? (float) $lat : null;
        $lngValue = is_numeric($lng) ? (float) $lng : null;
        $radiusValue = is_numeric($radius) ? (float) $radius : null;

        if ($latValue !== null && ($latValue < -90 || $latValue > 90)) {
            $fields[] = 'lat';
        }

        if ($lngValue !== null && ($lngValue < -180 || $lngValue > 180)) {
            $fields[] = 'lng';
        }

        if ($fields !== []) {
            return Response::json([
                'status' => 'error',
                'message' => 'Missing required fields.',
                'fields' => array_values(array_unique($fields)),
            ], 422);
        }

        return [
            'lat' => $latValue,
            'lng' => $lngValue,
            'raio_km' => $radiusValue,
        ];
    }

    /** @param array<string, mixed> $profile */
    private function logWriteError(Request $request, string $type, string $message, array $profile): void
    {
        try {
            $this->logRepository()->create($type, $message, 'error', $request, (int) $profile['id'], (string) $profile['tipo']);
        } catch (Throwable) {
        }
    }

    private function transactions(): TransactionManagerInterface
    {
        return $this->transactions ?? new DatabaseTransactionManager();
    }

    private function enderecoRepository(): object
    {
        return $this->enderecoRepository ?? new EnderecoRepository();
    }

    private function eventoRepository(): object
    {
        return $this->eventoRepository ?? new EventoRepository();
    }

    private function logRepository(): object
    {
        return $this->logRepository ?? new LogRepository();
    }
}
