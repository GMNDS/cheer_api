<?php

namespace Cheer\Services;

use Cheer\Core\Config;
use RuntimeException;

final class AuthentikOAuthClient
{
    /** @return array<string, mixed> */
    public function exchangeCode(string $code, string $codeVerifier, ?string $redirectUri = null): array
    {
        $payload = [
            'grant_type' => 'authorization_code',
            'client_id' => Config::get('authentik.client_id'),
            'client_secret' => Config::get('authentik.client_secret'),
            'redirect_uri' => $redirectUri ?? Config::get('authentik.redirect_uri'),
            'code' => $code,
            'code_verifier' => $codeVerifier,
        ];

        return $this->postForm((string) Config::get('authentik.token_url'), $payload);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function postForm(string $url, array $payload): array
    {
        if ($url === '') {
            throw new RuntimeException('Authentik token URL is not configured.');
        }

        $curl = curl_init($url);

        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($payload, '', '&', PHP_QUERY_RFC3986),
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Content-Type: application/x-www-form-urlencoded',
            ],
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

        $body = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $error = curl_error($curl);
        curl_close($curl);

        if ($body === false) {
            throw new RuntimeException("Could not connect to Authentik token endpoint: {$error}");
        }

        $data = json_decode((string) $body, true);

        if (!is_array($data)) {
            throw new RuntimeException('Invalid response from Authentik token endpoint.');
        }

        if ($status < 200 || $status >= 300) {
            $message = $data['error_description'] ?? $data['error'] ?? $body;
            throw new RuntimeException("Authentik token endpoint failed with status {$status}: {$message}");
        }

        return $data;
    }
}
