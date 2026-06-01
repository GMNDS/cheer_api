<?php

namespace Tests\Feature;

use Cheer\Controllers\AuthController;
use Cheer\Core\Request;
use Tests\TestCase;

final class ProfileRouteTest extends TestCase
{
    public function testInstitutionProfileIncludesAddress(): void
    {
        $_SESSION['profile'] = [
            'tipo' => 'instituicao',
            'id' => 10,
            'nome' => 'Instituto Teste',
            'email' => 'contato@instituto.test',
            'telefone' => '11999999999',
            'categoria' => 'Educacao',
            'rua' => 'Rua Central',
            'bairro' => 'Centro',
            'cidade' => 'Sao Paulo',
            'uf' => 'SP',
            'codigo_postal' => '01001000',
        ];

        $result = $this->render((new AuthController())->me(new Request('GET', '/api/me', [], [], [])));

        self::assertSame(200, $result['status']);

        $body = json_decode($result['body'], true);
        self::assertSame('instituicao', $body['data']['tipo']);
        self::assertSame('Sao Paulo', $body['data']['cidade']);
        self::assertSame([
            'rua' => 'Rua Central',
            'bairro' => 'Centro',
            'cidade' => 'Sao Paulo',
            'uf' => 'SP',
            'codigo_postal' => '01001000',
        ], $body['data']['endereco']);
    }
}
