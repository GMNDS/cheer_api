<?php

namespace Cheer\Core;

use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use RuntimeException;
use stdClass;

final class Auth
{
    /** @return array<string, mixed> */
    public static function profile(): array
    {
        $profile = Session::get('profile');

        if (!is_array($profile)) {
            throw new RuntimeException('Unauthenticated.');
        }

        return $profile;
    }

    public static function check(): bool
    {
        return is_array(Session::get('profile'));
    }

    public static function validateIdToken(string $token, ?string $nonce = null): stdClass
    {
        $claims = JWT::decode($token, self::keys());
        self::assertIssuer($claims);
        self::assertAudience($claims);

        if ($nonce !== null && ($claims->nonce ?? null) !== $nonce) {
            throw new RuntimeException('Invalid token nonce.');
        }

        return $claims;
    }

    public static function claims(Request $request): stdClass
    {
        $token = $request->bearerToken();

        if ($token === null) {
            throw new RuntimeException('Missing bearer token.');
        }

        return self::validateAccessToken($token);
    }

    /** @return list<string> */
    public static function scopes(stdClass $claims): array
    {
        $scope = (string) ($claims->scope ?? '');

        if ($scope === '') {
            return [];
        }

        return array_values(array_filter(explode(' ', $scope)));
    }

    public static function hasScope(stdClass $claims, string $scope): bool
    {
        return in_array($scope, self::scopes($claims), true);
    }

    /** @return array<string, \Firebase\JWT\Key> */
    private static function keys(): array
    {
        $jwksUrl = Config::get('authentik.jwks_url');

        if (!is_string($jwksUrl) || $jwksUrl === '') {
            throw new RuntimeException('Authentik JWKS URL is not configured.');
        }

        $contents = @file_get_contents($jwksUrl);

        if ($contents === false) {
            throw new RuntimeException('Could not load Authentik JWKS.');
        }

        $jwks = json_decode($contents, true);

        if (!is_array($jwks)) {
            throw new RuntimeException('Invalid Authentik JWKS response.');
        }

        return JWK::parseKeySet($jwks);
    }

    private static function assertIssuer(stdClass $claims): void
    {
        $issuer = Config::get('authentik.issuer');

        if (is_string($issuer) && $issuer !== '' && ($claims->iss ?? null) !== $issuer) {
            throw new RuntimeException('Invalid token issuer.');
        }
    }

    private static function assertAudience(stdClass $claims): void
    {
        $clientId = Config::get('authentik.client_id');

        if (!is_string($clientId) || $clientId === '') {
            return;
        }

        $audience = $claims->aud ?? [];
        $audiences = is_array($audience) ? $audience : [$audience];

        if (!in_array($clientId, $audiences, true)) {
            throw new RuntimeException('Invalid token audience.');
        }
    }

    public static function validateAccessToken(string $token): stdClass
    {
        $claims = JWT::decode($token, self::keys());
        self::assertIssuer($claims);
        self::assertAudience($claims);

        return $claims;
    }
}
