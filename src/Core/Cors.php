<?php

namespace Cheer\Core;

final class Cors
{
    public static function allowedOrigin(?string $requestOrigin, string $configuredOrigins, bool $allowCredentials): ?string
    {
        $origins = self::parseOrigins($configuredOrigins);

        if ($origins === []) {
            return null;
        }

        if (in_array('*', $origins, true)) {
            if (!$allowCredentials) {
                return '*';
            }

            return null;
        }

        if ($requestOrigin !== null && in_array($requestOrigin, $origins, true)) {
            return $requestOrigin;
        }

        return $requestOrigin === null || $requestOrigin === '' ? $origins[0] : null;
    }

    /** @return list<string> */
    private static function parseOrigins(string $configuredOrigins): array
    {
        $origins = array_map('trim', explode(',', $configuredOrigins));
        $origins = array_filter($origins, static fn (string $origin): bool => $origin !== '');

        return array_values(array_unique($origins));
    }
}
