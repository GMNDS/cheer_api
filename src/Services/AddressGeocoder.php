<?php

namespace Cheer\Services;

use Cheer\Core\Config;

final class AddressGeocoder
{
    /**
     * @param array<string, mixed> $address
     * @return array{lat: float, lng: float}|null
     */
    public function resolve(array $address): ?array
    {
        $lat = $this->decimalOrNull($address['lat'] ?? null);
        $lng = $this->decimalOrNull($address['lng'] ?? null);

        if ($lat !== null && $lng !== null) {
            return ['lat' => $lat, 'lng' => $lng];
        }

        $providerUrl = trim((string) Config::get('geocoding.provider_url', ''));

        if ($providerUrl === '') {
            return null;
        }

        $query = $this->buildQuery($address);

        if ($query === '') {
            return null;
        }

        $requestUrl = rtrim($providerUrl, '?') . '?' . http_build_query([
            'format' => 'jsonv2',
            'limit' => 1,
            'q' => $query,
        ], '', '&', PHP_QUERY_RFC3986);

        $curl = curl_init($requestUrl);

        if ($curl === false) {
            return null;
        }

        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPGET => true,
            CURLOPT_TIMEOUT => (int) Config::get('geocoding.timeout', 10),
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'User-Agent: ' . (string) Config::get('geocoding.user_agent', (string) Config::get('app.name', 'Cheer API')),
            ],
        ]);

        $body = curl_exec($curl);
        curl_close($curl);

        if ($body === false) {
            return null;
        }

        $payload = json_decode((string) $body, true);

        if (!is_array($payload) || $payload === []) {
            return null;
        }

        $candidate = $payload[0] ?? null;

        if (!is_array($candidate)) {
            return null;
        }

        $resolvedLat = $this->decimalOrNull($candidate['lat'] ?? $candidate['latitude'] ?? null);
        $resolvedLng = $this->decimalOrNull($candidate['lon'] ?? $candidate['lng'] ?? $candidate['longitude'] ?? null);

        if ($resolvedLat === null || $resolvedLng === null) {
            return null;
        }

        return ['lat' => $resolvedLat, 'lng' => $resolvedLng];
    }

    /**
     * @param array<string, mixed> $address
     */
    public function buildQuery(array $address): string
    {
        $parts = array_filter([
            trim((string) ($address['rua'] ?? '')),
            trim((string) ($address['numero'] ?? '')),
            trim((string) ($address['bairro'] ?? '')),
            trim((string) ($address['cidade'] ?? '')),
            trim((string) ($address['uf'] ?? '')),
            trim((string) ($address['codigo_postal'] ?? $address['cep'] ?? '')),
        ], static fn (string $part): bool => $part !== '');

        return implode(', ', $parts);
    }

    private function decimalOrNull(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value) ? (float) $value : null;
    }
}