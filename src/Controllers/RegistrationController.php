<?php

namespace Cheer\Controllers;

use Cheer\Core\Database;
use Cheer\Core\Request;
use Cheer\Core\Response;
use Cheer\Repositories\EnderecoRepository;
use Cheer\Repositories\InstituicaoRepository;
use Cheer\Repositories\LogRepository;
use Cheer\Repositories\VoluntarioRepository;
use Cheer\Services\AuthentikAdminClient;
use OpenApi\Attributes as OA;
use Throwable;

final class RegistrationController
{
    #[OA\Post(
        path: '/api/auth/register-voluntario',
        summary: 'Cadastrar voluntario',
        tags: ['Auth'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/RegisterVoluntarioRequest')),
        responses: [
            new OA\Response(response: 201, description: 'Voluntario cadastrado', content: new OA\JsonContent(ref: '#/components/schemas/RegisterResponse')),
            new OA\Response(response: 422, description: 'Payload invalido', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')),
            new OA\Response(response: 500, description: 'Erro interno', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function registerVoluntario(Request $request): Response
    {
        $data = $request->all();
        $address = $this->addressFrom($data);
        $missing = $this->missing($data, ['nome', 'email', 'password', 'cpf']);
        $missing = array_merge($missing, $this->missingAddress($address));

        if ($missing !== []) {
            return $this->validationError($missing);
        }

        return $this->register($request, $data, $address, 'voluntario');
    }

    #[OA\Post(
        path: '/api/auth/register-instituicao',
        summary: 'Cadastrar instituicao',
        tags: ['Auth'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/RegisterInstituicaoRequest')),
        responses: [
            new OA\Response(response: 201, description: 'Instituicao cadastrada', content: new OA\JsonContent(ref: '#/components/schemas/RegisterResponse')),
            new OA\Response(response: 422, description: 'Payload invalido', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')),
            new OA\Response(response: 500, description: 'Erro interno', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function registerInstituicao(Request $request): Response
    {
        $data = $request->all();
        $address = $this->addressFrom($data);
        $missing = $this->missing($data, ['nome', 'email', 'password', 'cnpj']);
        $missing = array_merge($missing, $this->missingAddress($address));

        if ($missing !== []) {
            return $this->validationError($missing);
        }

        return $this->register($request, $data, $address, 'instituicao');
    }

    /** @param array<string, mixed> $data */
    private function register(Request $request, array $data, array $address, string $tipo): Response
    {
        $authentik = new AuthentikAdminClient();
        $authentikUser = [];

        try {
            $authentikUser = $authentik->createUser(
                trim((string) $data['nome']),
                strtolower(trim((string) $data['email'])),
                (string) $data['password'],
                $tipo
            );
            $authentikIdentifier = $authentik->localIdentifier($authentikUser);

            if ($authentikIdentifier === '') {
                throw new \RuntimeException('Could not resolve Authentik user identifier.');
            }

            Database::connection()->beginTransaction();

            $enderecoId = (new EnderecoRepository())->create($address);
            $profileId = $tipo === 'instituicao'
                ? (new InstituicaoRepository())->create($authentikIdentifier, $enderecoId, $data)
                : (new VoluntarioRepository())->create($authentikIdentifier, $enderecoId, $data);

            (new LogRepository())->create(
                $tipo === 'instituicao' ? 'CADASTRO_INSTITUICAO' : 'CADASTRO_VOLUNTARIO',
                "Cadastro de {$tipo} {$profileId}.",
                'info',
                $request,
                $profileId,
                $tipo
            );

            Database::connection()->commit();

            return Response::json([
                'status' => 'success',
                'data' => [
                    'id' => $profileId,
                    'tipo' => $tipo,
                    'authentik_user' => $authentikIdentifier,
                ],
            ], 201);
        } catch (Throwable $exception) {
            $this->rollbackIfNeeded();
            $authentik->deleteUserIfCreated($authentikUser);
            $this->logRegistrationError($request, $exception->getMessage(), $tipo);

            return Response::json([
                'status' => 'error',
                'message' => $exception->getMessage(),
            ], 500);
        }
    }

    /** @param array<string, mixed> $data */
    private function addressFrom(array $data): array
    {
        $address = $data['endereco'] ?? $data['address'] ?? $data;

        return is_array($address) ? $address : [];
    }

    /** @param array<string, mixed> $data */
    private function missing(array $data, array $required): array
    {
        return array_values(array_filter($required, static fn (string $field): bool => empty($data[$field])));
    }

    /** @param array<string, mixed> $address */
    private function missingAddress(array $address): array
    {
        $missing = $this->missing($address, ['rua', 'bairro', 'cidade', 'uf']);

        if (empty($address['codigo_postal']) && empty($address['cep'])) {
            $missing[] = 'codigo_postal';
        }

        return $missing;
    }

    /** @param list<string> $fields */
    private function validationError(array $fields): Response
    {
        return Response::json([
            'status' => 'error',
            'message' => 'Missing required fields.',
            'fields' => array_values(array_unique($fields)),
        ], 422);
    }

    private function logRegistrationError(Request $request, string $message, string $tipo): void
    {
        try {
            (new LogRepository())->create(
                'ERRO_CADASTRO',
                $message,
                'error',
                $request,
                null,
                $tipo
            );
        } catch (Throwable) {
        }
    }

    private function rollbackIfNeeded(): void
    {
        try {
            if (Database::connection()->inTransaction()) {
                Database::connection()->rollBack();
            }
        } catch (Throwable) {
        }
    }
}
