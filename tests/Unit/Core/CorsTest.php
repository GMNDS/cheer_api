<?php

namespace Tests\Unit\Core;

use Cheer\Core\Cors;
use Tests\TestCase;

final class CorsTest extends TestCase
{
    public function testAllowsMatchingOriginFromCommaSeparatedList(): void
    {
        self::assertSame(
            'https://front.example.com',
            Cors::allowedOrigin(
                'https://front.example.com',
                'http://localhost:5173, https://front.example.com',
                true
            )
        );
    }

    public function testRejectsOriginOutsideConfiguredList(): void
    {
        self::assertNull(Cors::allowedOrigin(
            'https://evil.example.com',
            'http://localhost:5173, https://front.example.com',
            true
        ));
    }

    public function testKeepsWildcardOnlyWhenCredentialsAreDisabled(): void
    {
        self::assertSame('*', Cors::allowedOrigin('https://front.example.com', '*', false));
        self::assertNull(Cors::allowedOrigin('https://front.example.com', '*', true));
    }
}
