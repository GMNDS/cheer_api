<?php

namespace Tests\Feature;

use Cheer\Controllers\RegistrationController;
use Cheer\Core\Request;
use Tests\Support\FakeAuthentikAdminClient;
use Tests\Support\FakeEnderecoRepository;
use Tests\Support\FakeInstituicaoRepository;
use Tests\Support\FakeLogRepository;
use Tests\Support\FakeScenarioState;
use Tests\Support\FakeTransactionManager;
use Tests\Support\FakeVoluntarioRepository;
use Tests\TestCase;

final class RegistrationValidationTest extends TestCase
{
    public function testRejectsInvalidVolunteerCpf(): void
    {
        $result = $this->render($this->controller()->registerVoluntario(new Request('POST', '/api/auth/register-voluntario', [], [], array_replace(
            $this->volunteerPayload(),
            ['cpf' => '123']
        ))));

        self::assertSame(422, $result['status']);

        $body = json_decode($result['body'], true);
        self::assertContains('cpf', $body['fields']);
    }

    public function testRejectsInvalidInstitutionCnpj(): void
    {
        $result = $this->render($this->controller()->registerInstituicao(new Request('POST', '/api/auth/register-instituicao', [], [], array_replace(
            $this->institutionPayload(),
            ['cnpj' => '123']
        ))));

        self::assertSame(422, $result['status']);

        $body = json_decode($result['body'], true);
        self::assertContains('cnpj', $body['fields']);
    }

    public function testRejectsWeakPassword(): void
    {
        $result = $this->render($this->controller()->registerVoluntario(new Request('POST', '/api/auth/register-voluntario', [], [], array_replace(
            $this->volunteerPayload(),
            ['password' => 'senhafraca']
        ))));

        self::assertSame(422, $result['status']);

        $body = json_decode($result['body'], true);
        self::assertContains('password', $body['fields']);
    }

    private function controller(): RegistrationController
    {
        $state = new FakeScenarioState();

        return new RegistrationController(
            new FakeAuthentikAdminClient(),
            new FakeTransactionManager(),
            new FakeEnderecoRepository($state),
            new FakeInstituicaoRepository($state),
            new FakeVoluntarioRepository($state),
            new FakeLogRepository($state)
        );
    }

    /** @return array<string, mixed> */
    private function volunteerPayload(): array
    {
        return [
            'nome' => 'Voluntario Teste',
            'email' => 'voluntario@teste.local',
            'password' => 'Teste@12345',
            'cpf' => '12345678901',
            'endereco' => $this->address(),
        ];
    }

    /** @return array<string, mixed> */
    private function institutionPayload(): array
    {
        return [
            'nome' => 'Instituicao Teste',
            'email' => 'instituicao@teste.local',
            'password' => 'Teste@12345',
            'cnpj' => '12345678000199',
            'endereco' => $this->address(),
        ];
    }

    /** @return array<string, string> */
    private function address(): array
    {
        return [
            'rua' => 'Rua Central',
            'bairro' => 'Centro',
            'cidade' => 'Sao Paulo',
            'uf' => 'SP',
            'codigo_postal' => '01001000',
        ];
    }
}
