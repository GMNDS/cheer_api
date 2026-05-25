<?php

namespace Cheer\Services;

use Cheer\Core\Config;
use RuntimeException;

final class AuthentikAdminClient
{
    /** @return array<string, mixed> */
    public function createUser(string $name, string $email, string $password, string $tipo): array
    {
        $username = $this->usernameFromEmail($email);
        $user = $this->request('POST', '/core/users/', [
            'username' => $username,
            'name' => $name,
            'email' => $email,
            'is_active' => true,
            'type' => 'internal',
            'path' => Config::get('authentik.user_path', 'users'),
            'attributes' => [
                'cheer_tipo' => $tipo,
            ],
        ]);

        try {
            $pk = $user['pk'] ?? null;

            if (!is_int($pk)) {
                throw new RuntimeException('Authentik did not return a valid user pk.');
            }

            $this->request('POST', "/core/users/{$pk}/set_password/", [
                'password' => $password,
            ], [204]);

            $groupUuid = $this->groupUuidFor($tipo);

            if ($groupUuid !== null) {
                $this->request('POST', "/core/groups/{$groupUuid}/add_user/", [
                    'pk' => $pk,
                ], [204]);
            }
        } catch (RuntimeException $exception) {
            $this->deleteUserIfCreated($user);
            throw $exception;
        }

        return $user;
    }

    /** @param array<string, mixed> $user */
    public function localIdentifier(array $user): string
    {
        $field = (string) Config::get('authentik.local_user_identifier', 'uid');

        return match ($field) {
            'pk' => (string) ($user['pk'] ?? ''),
            'username' => (string) ($user['username'] ?? ''),
            'email' => (string) ($user['email'] ?? ''),
            default => (string) ($user['uid'] ?? $user['id'] ?? $user['pk'] ?? ''),
        };
    }

    public function deleteUserIfCreated(array $user): void
    {
        $pk = $user['pk'] ?? null;

        if (!is_int($pk)) {
            return;
        }

        try {
            $this->request('DELETE', "/core/users/{$pk}/", null, [204]);
        } catch (RuntimeException) {
        }
    }

    private function groupUuidFor(string $tipo): ?string
    {
        $uuidKey = $tipo === 'instituicao'
            ? 'authentik.instituicao_group_uuid'
            : 'authentik.voluntario_group_uuid';
        $nameKey = $tipo === 'instituicao'
            ? 'authentik.instituicao_group_name'
            : 'authentik.voluntario_group_name';

        $configuredUuid = (string) Config::get($uuidKey, '');

        if ($configuredUuid !== '') {
            return $configuredUuid;
        }

        $name = (string) Config::get($nameKey, '');

        if ($name === '') {
            return null;
        }

        $response = $this->request('GET', '/core/groups/?name=' . rawurlencode($name));
        $results = $response['results'] ?? [];

        if (!is_array($results) || !isset($results[0]) || !is_array($results[0])) {
            throw new RuntimeException("Authentik group not found: {$name}");
        }

        $pk = $results[0]['pk'] ?? null;

        if (!is_string($pk) || $pk === '') {
            throw new RuntimeException("Authentik group does not have a valid uuid: {$name}");
        }

        return $pk;
    }

    /**
     * @param array<string, mixed>|null $payload
     * @param list<int> $expectedStatuses
     * @return array<string, mixed>
     */
    private function request(string $method, string $path, ?array $payload = null, array $expectedStatuses = [200, 201]): array
    {
        $baseUrl = rtrim((string) Config::get('authentik.api_base_url', ''), '/');
        $token = (string) Config::get('authentik.api_token', '');

        if ($baseUrl === '' || $token === '') {
            throw new RuntimeException('Authentik admin API is not configured.');
        }

        $url = $baseUrl . '/' . ltrim($path, '/');
        $curl = curl_init($url);
        $headers = [
            'Accept: application/json',
            "Authorization: Bearer {$token}",
        ];

        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_TIMEOUT => 15,
        ]);

        if ((bool) Config::get('authentik.verify_ssl', true) === false) {
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        }

        $caBundle = (string) Config::get('authentik.ca_bundle', '');

        if ($caBundle !== '') {
            curl_setopt($curl, CURLOPT_CAINFO, $caBundle);
        }

        if ($payload !== null) {
            $headers[] = 'Content-Type: application/json';
            curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        }

        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);

        $body = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $error = curl_error($curl);
        curl_close($curl);

        if ($body === false) {
            throw new RuntimeException("Could not connect to Authentik admin API: {$error}");
        }

        $decoded = $body !== '' ? json_decode((string) $body, true) : [];
        $data = is_array($decoded) ? $decoded : [];

        if (!in_array($status, $expectedStatuses, true)) {
            $message = $data['detail'] ?? $data['error'] ?? $body;

            if (is_array($message)) {
                $message = json_encode($message, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }

            throw new RuntimeException("Authentik admin API {$method} {$path} failed with status {$status}: {$message}");
        }

        return $data;
    }

    private function usernameFromEmail(string $email): string
    {
        return strtolower(trim($email));
    }
}
