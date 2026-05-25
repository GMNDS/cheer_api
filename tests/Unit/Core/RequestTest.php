<?php

namespace Tests\Unit\Core;

use Cheer\Core\Request;
use Tests\TestCase;

final class RequestTest extends TestCase
{
    public function testBodyValuesOverrideQueryValues(): void
    {
        $request = new Request(
            'POST',
            '/eventos',
            [],
            ['status' => 'rascunho', 'pagina' => 2],
            ['status' => 'publicado', 'titulo' => 'Mutirao']
        );

        self::assertSame('publicado', $request->input('status'));
        self::assertSame(2, $request->input('pagina'));
        self::assertSame('padrao', $request->input('ausente', 'padrao'));
        self::assertSame([
            'status' => 'publicado',
            'pagina' => 2,
            'titulo' => 'Mutirao',
        ], $request->all());
    }

    public function testExtractsBearerTokenIgnoringSchemeCase(): void
    {
        $request = new Request('GET', '/me', ['authorization' => 'bEaReR token-value'], [], []);

        self::assertSame('token-value', $request->bearerToken());
    }

    public function testIgnoresNonBearerAuthorization(): void
    {
        $request = new Request('GET', '/me', ['authorization' => 'Basic credentials'], [], []);

        self::assertNull($request->bearerToken());
    }
}
