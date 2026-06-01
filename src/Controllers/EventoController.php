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
use DateTimeImmutable;
use Exception;
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
            'data' => $nearby === null
                ? $this->eventoRepository()->listAvailable()
                : $this->eventoRepository()->listAvailable($nearby['lat'], $nearby['lng'], $nearby['raio_km']),
        ]);
    }

    #[OA\Get (
        path: '/api/eventos/{id}',
        summary: 'Buscar evento por ID',
        tags: ['Eventos'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'ID do evento',
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Evento encontrado',
            ),
            new OA\Response(
                response: 404,
                description: 'Evento nao encontrado'
            ),
        ]
    )]
    public function show(Request $request, int $id): Response
    {
        $evento = $this->eventoRepository()->findById($id);

        if (!$evento) {
            return Response::json([
                'status' => 'error',
                'message' => 'Evento nao encontrado',
            ], 404);
        }

        return Response::json([
            'status' => 'success',
            'data' => $evento,
        ]);
    }

    #[OA\Put(
    path: '/api/eventos/{id}',
    summary: 'Atualizar evento',
    security: [['cookieAuth' => []]],
    tags: ['Eventos'],
    parameters: [
        new OA\Parameter(
            name: 'id',
            in: 'path',
            required: true,
            schema: new OA\Schema(type: 'integer')
        ),
    ],
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'titulo', type: 'string'),
                new OA\Property(property: 'descricao', type: 'string'),
                new OA\Property(property: 'tipo_evento', type: 'string'),
            ]
        )
    ),
    responses: [
        new OA\Response(response: 200, description: 'Evento atualizado'),
        new OA\Response(response: 404, description: 'Evento nao encontrado'),
    ]
)]
public function update(Request $request, int $id): Response
{
    $auth = $this->requireProfile($request, 'instituicao');

    if ($auth instanceof Response) {
        return $auth;
    }

    $evento = $this->eventoRepository()->findById($id);

    if (!$evento) {
        return Response::json([
            'status' => 'error',
            'message' => 'Evento nao encontrado.',
        ], 404);
    }

    if ((int) $evento['id_instituicao'] !== (int) $auth['profile']['id']) {
    return Response::json([
        'status' => 'error',
        'message' => 'Permission denied.',
    ], 403);
}

    $data = $request->all();

    $this->eventoRepository()->update($id, $data);

    return Response::json([
        'status' => 'success',
        'message' => 'Evento atualizado com sucesso.',
    ]);
}

#[OA\Delete(
    path: '/api/eventos/{id}',
    summary: 'Deletar evento',
    security: [['cookieAuth' => []]],
    tags: ['Eventos'],
    parameters: [
        new OA\Parameter(
            name: 'id',
            in: 'path',
            required: true,
            schema: new OA\Schema(type: 'integer')
        ),
    ],
    responses: [
        new OA\Response(response: 200, description: 'Evento deletado'),
        new OA\Response(response: 404, description: 'Evento nao encontrado'),
    ]
)]
public function destroy(Request $request, int $id): Response
{
    $auth = $this->requireProfile($request, 'instituicao');

    if ($auth instanceof Response) {
        return $auth;
    }

    $evento = $this->eventoRepository()->findById($id);

    if (!$evento) {
        return Response::json([
            'status' => 'error',
            'message' => 'Evento nao encontrado.',
        ], 404);
    }

    if ((int) $evento['id_instituicao'] !== (int) $auth['profile']['id']) {
    return Response::json([
        'status' => 'error',
        'message' => 'Permission denied.',
    ], 403);
}

    $this->eventoRepository()->delete($id);

    return Response::json([
        'status' => 'success',
        'message' => 'Evento deletado com sucesso.',
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

        $invalidFields = $this->invalidEventFields($data);

        if ($invalidFields !== []) {
            return Response::json([
                'status' => 'error',
                'message' => 'Invalid fields.',
                'fields' => $invalidFields,
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

    /** @param array<string, mixed> $data */
    private function invalidEventFields(array $data): array
    {
        $fields = [];
        $start = $this->parseDateTime($data['data_hora_inicio'] ?? $data['data'] ?? null);

        if ($start === null || $start < new DateTimeImmutable()) {
            $fields[] = 'data_hora_inicio';
        }

        if (array_key_exists('data_hora_termino', $data) && $data['data_hora_termino'] !== null && $data['data_hora_termino'] !== '') {
            $end = $this->parseDateTime($data['data_hora_termino']);

            if ($end === null || ($start !== null && $end <= $start)) {
                $fields[] = 'data_hora_termino';
            }
        }

        $volunteerLimit = $data['num_max_voluntarios'] ?? $data['vagas'] ?? null;

        if ($volunteerLimit !== null && $volunteerLimit !== '') {
            $volunteers = filter_var($volunteerLimit, FILTER_VALIDATE_INT);

            if ($volunteers === false || $volunteers <= 0) {
                $fields[] = array_key_exists('num_max_voluntarios', $data) ? 'num_max_voluntarios' : 'vagas';
            }
        }

        return array_values(array_unique($fields));
    }

    private function parseDateTime(mixed $value): ?DateTimeImmutable
    {
        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return new DateTimeImmutable($value);
        } catch (Exception) {
            return null;
        }
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
