<?php

namespace Cheer\Controllers;

use Cheer\Core\Auth;
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

        $events = $this->eventoRepository()->listAvailable($nearby['lat'] ?? null, $nearby['lng'] ?? null, $nearby['raio_km'] ?? null);

        if ($nearby !== null && $events === []) {
            $events = $this->eventoRepository()->listAvailable();
        }

        return Response::json([
            'status' => 'success',
            'data' => $events,
        ]);
    }

    #[OA\Get(
        path: '/api/eventos/{id}',
        summary: 'Detalhar evento da instituicao autenticada',
        security: [['cookieAuth' => []]],
        tags: ['Eventos'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Evento encontrado', content: new OA\JsonContent(ref: '#/components/schemas/EventoDetailResponse')),
            new OA\Response(response: 401, description: 'Nao autenticado', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 403, description: 'Permissao negada', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 404, description: 'Evento nao encontrado', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function show(Request $request, string $id): Response
    {
        $auth = $this->requireProfile($request, 'instituicao');

        if ($auth instanceof Response) {
            return $auth;
        }

        $evento = $this->eventoRepository()->findOwned((int) $id, (int) $auth['profile']['id']);

        if ($evento === null) {
            return Response::json([
                'status' => 'error',
                'message' => 'Evento nao encontrado.',
            ], 404);
        }

        return Response::json([
            'status' => 'success',
            'data' => $evento,
        ]);
    }

    #[OA\Put(
        path: '/api/eventos/{id}',
        summary: 'Atualizar evento da instituicao autenticada',
        security: [['cookieAuth' => []]],
        tags: ['Eventos'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/CreateEventoRequest')),
        responses: [
            new OA\Response(response: 200, description: 'Evento atualizado', content: new OA\JsonContent(ref: '#/components/schemas/EventoDetailResponse')),
            new OA\Response(response: 401, description: 'Nao autenticado', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 403, description: 'Permissao negada', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 404, description: 'Evento nao encontrado', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 422, description: 'Payload invalido', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')),
        ]
    )]
    public function update(Request $request, string $id): Response
    {
        $auth = $this->requireProfile($request, 'instituicao');

        if ($auth instanceof Response) {
            return $auth;
        }

        $instituicaoId = (int) $auth['profile']['id'];
        $eventoId = (int) $id;
        $current = $this->eventoRepository()->findOwned($eventoId, $instituicaoId);

        if ($current === null) {
            return Response::json([
                'status' => 'error',
                'message' => 'Evento nao encontrado.',
            ], 404);
        }

        $data = $this->mergeEventPayload($current, $request->all());
        $address = is_array($data['endereco'] ?? null) ? $data['endereco'] : $data;
        $missing = array_merge(
            $this->missing($data, ['titulo', 'tipo_evento', 'data_hora_inicio']),
            $this->missing($address, ['rua', 'bairro', 'cidade', 'uf', 'codigo_postal'])
        );

        if ($missing !== []) {
            return Response::json([
                'status' => 'error',
                'message' => 'Missing required fields.',
                'fields' => array_values(array_unique($missing)),
            ], 422);
        }

        $validationFields = $this->invalidEventFields($data);

        if ($validationFields !== []) {
            return Response::json([
                'status' => 'error',
                'message' => 'Payload invalido.',
                'fields' => $validationFields,
            ], 422);
        }

        try {
            $this->transactions()->begin();

            $this->enderecoRepository()->update((int) $current['id_endereco'], $address);
            $this->eventoRepository()->updateOwned($eventoId, $instituicaoId, $data);

            $this->logRepository()->create(
                'ATUALIZACAO_EVENTO',
                "Evento {$eventoId} atualizado.",
                'info',
                $request,
                $instituicaoId,
                'instituicao'
            );

            $this->transactions()->commit();

            return Response::json([
                'status' => 'success',
                'data' => $this->eventoRepository()->findOwned($eventoId, $instituicaoId),
            ]);
        } catch (Throwable $exception) {
            $this->transactions()->rollback();
            $this->logWriteError($request, 'ERRO_ATUALIZACAO_EVENTO', $exception->getMessage(), $auth['profile']);

            return Response::json([
                'status' => 'error',
                'message' => $exception->getMessage(),
            ], 500);
        }
    }

    #[OA\Delete(
        path: '/api/eventos/{id}',
        summary: 'Excluir evento da instituicao autenticada',
        security: [['cookieAuth' => []]],
        tags: ['Eventos'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Evento excluido', content: new OA\JsonContent(ref: '#/components/schemas/IdResponse')),
            new OA\Response(response: 401, description: 'Nao autenticado', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 403, description: 'Permissao negada', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 404, description: 'Evento nao encontrado', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function destroy(Request $request, string $id): Response
    {
        $auth = $this->requireProfile($request, 'instituicao');

        if ($auth instanceof Response) {
            return $auth;
        }

        $instituicaoId = (int) $auth['profile']['id'];
        $eventoId = (int) $id;

        try {
            $this->transactions()->begin();
            $deleted = $this->eventoRepository()->deleteOwned($eventoId, $instituicaoId);

            if (!$deleted) {
                $this->transactions()->rollback();

                return Response::json([
                    'status' => 'error',
                    'message' => 'Evento nao encontrado.',
                ], 404);
            }

            $this->logRepository()->create(
                'EXCLUSAO_EVENTO',
                "Evento {$eventoId} excluido.",
                'info',
                $request,
                $instituicaoId,
                'instituicao'
            );

            $this->transactions()->commit();

            return Response::json([
                'status' => 'success',
                'data' => ['id' => $eventoId],
            ]);
        } catch (Throwable $exception) {
            $this->transactions()->rollback();
            $this->logWriteError($request, 'ERRO_EXCLUSAO_EVENTO', $exception->getMessage(), $auth['profile']);

            return Response::json([
                'status' => 'error',
                'message' => $exception->getMessage(),
            ], 500);
        }
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

        $validationFields = $this->invalidEventFields($data);

        if ($validationFields !== []) {
            return Response::json([
                'status' => 'error',
                'message' => 'Payload invalido.',
                'fields' => $validationFields,
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

            if (($profile['tipo'] ?? null) !== $type) {
                $this->logRepository()->create(
                    'PERMISSAO_NEGADA',
                    "Expected {$type}.",
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

    /** @param array<string, mixed> $data */
    private function missing(array $data, array $required): array
    {
        return array_values(array_filter($required, static fn (string $field): bool => empty($data[$field])));
    }

    /** @param array<string, mixed> $data */
    private function invalidEventFields(array $data): array
    {
        $fields = [];
        $startValue = $data['data_hora_inicio'] ?? $data['data'] ?? null;
        $endValue = $data['data_hora_termino'] ?? null;
        $startDate = $this->parseDateTime($startValue);

        if ($startDate === null || $startDate < new \DateTimeImmutable()) {
            $fields[] = 'data_hora_inicio';
        }

        if ($endValue !== null && $endValue !== '') {
            $endDate = $this->parseDateTime($endValue);

            if ($endDate === null || ($startDate !== null && $endDate <= $startDate)) {
                $fields[] = 'data_hora_termino';
            }
        }

        $capacity = $data['num_max_voluntarios'] ?? $data['vagas'] ?? null;

        if ($capacity !== null && $capacity !== '' && (!is_numeric($capacity) || (int) $capacity <= 0)) {
            $fields[] = 'num_max_voluntarios';
        }

        return array_values(array_unique($fields));
    }

    private function parseDateTime(mixed $value): ?\DateTimeImmutable
    {
        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return new \DateTimeImmutable($value);
        } catch (Throwable) {
            return null;
        }
    }

    /** @param array<string, mixed> $current @param array<string, mixed> $incoming */
    private function mergeEventPayload(array $current, array $incoming): array
    {
        $currentAddress = is_array($current['endereco'] ?? null) ? $current['endereco'] : [];
        $incomingAddress = is_array($incoming['endereco'] ?? null) ? $incoming['endereco'] : [];

        return [
            'titulo' => $incoming['titulo'] ?? $current['titulo'],
            'tipo_evento' => $incoming['tipo_evento'] ?? $current['tipo_evento'],
            'constancia' => array_key_exists('constancia', $incoming) ? $incoming['constancia'] : ($current['constancia'] ?? null),
            'descricao' => array_key_exists('descricao', $incoming) ? $incoming['descricao'] : ($current['descricao'] ?? null),
            'num_max_voluntarios' => array_key_exists('num_max_voluntarios', $incoming) ? $incoming['num_max_voluntarios'] : ($current['vagas'] ?? null),
            'data_hora_inicio' => $incoming['data_hora_inicio'] ?? $incoming['data'] ?? $current['data'],
            'data_hora_termino' => array_key_exists('data_hora_termino', $incoming) ? $incoming['data_hora_termino'] : ($current['data_hora_termino'] ?? null),
            'endereco' => array_merge($currentAddress, $incomingAddress),
        ];
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
