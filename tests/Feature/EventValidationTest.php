<?php

namespace Tests\Feature;

use Cheer\Controllers\EventoController;
use Cheer\Core\Request;
use Cheer\Core\Router;
use Tests\Support\FakeEnderecoRepository;
use Tests\Support\FakeEventoRepository;
use Tests\Support\FakeLogRepository;
use Tests\Support\FakeScenarioState;
use Tests\Support\FakeTransactionManager;
use Tests\TestCase;

final class EventValidationTest extends TestCase
{
    public function testRejectsPastStartDate(): void
    {
        $result = $this->postEvent([
            'data_hora_inicio' => (new \DateTimeImmutable('-1 day'))->format(DATE_ATOM),
        ]);

        self::assertSame(422, $result['status']);

        $body = json_decode($result['body'], true);
        self::assertContains('data_hora_inicio', $body['fields']);
    }

    public function testRejectsInvalidStartDate(): void
    {
        $result = $this->postEvent([
            'data_hora_inicio' => 'data-invalida',
        ]);

        self::assertSame(422, $result['status']);

        $body = json_decode($result['body'], true);
        self::assertContains('data_hora_inicio', $body['fields']);
    }

    public function testRejectsEndDateBeforeStartDate(): void
    {
        $result = $this->postEvent([
            'data_hora_inicio' => (new \DateTimeImmutable('+2 days'))->format(DATE_ATOM),
            'data_hora_termino' => (new \DateTimeImmutable('+1 day'))->format(DATE_ATOM),
        ]);

        self::assertSame(422, $result['status']);

        $body = json_decode($result['body'], true);
        self::assertContains('data_hora_termino', $body['fields']);
    }

    public function testRejectsNonPositiveVolunteerLimit(): void
    {
        $result = $this->postEvent([
            'num_max_voluntarios' => 0,
        ]);

        self::assertSame(422, $result['status']);

        $body = json_decode($result['body'], true);
        self::assertContains('num_max_voluntarios', $body['fields']);
    }

    public function testListsEventsWithoutNearbyParameters(): void
    {
        $state = new FakeScenarioState();
        $router = $this->router($state);

        $_SESSION['profile'] = [
            'tipo' => 'instituicao',
            'id' => 1,
        ];
        $state->institutions[1] = ['id' => 1, 'nome' => 'Instituicao'];

        $create = $this->render($router->dispatch(new Request('POST', '/api/eventos', [], [], $this->validPayload())));
        self::assertSame(201, $create['status']);

        $list = $this->render($router->dispatch(new Request('GET', '/api/eventos', [], [], [])));
        self::assertSame(200, $list['status']);

        $body = json_decode($list['body'], true);
        self::assertCount(1, $body['data']);
        self::assertSame('Evento Futuro', $body['data'][0]['titulo']);
    }

    /** @param array<string, mixed> $overrides */
    private function postEvent(array $overrides): array
    {
        $state = new FakeScenarioState();
        $router = $this->router($state);

        $_SESSION['profile'] = [
            'tipo' => 'instituicao',
            'id' => 1,
        ];

        return $this->render($router->dispatch(new Request('POST', '/api/eventos', [], [], array_replace($this->validPayload(), $overrides))));
    }

    private function router(FakeScenarioState $state): Router
    {
        $eventos = new EventoController(
            new FakeTransactionManager(),
            new FakeEnderecoRepository($state),
            new FakeEventoRepository($state),
            new FakeLogRepository($state)
        );

        $router = new Router();
        $router->post('/api/eventos', [$eventos, 'store']);
        $router->get('/api/eventos', [$eventos, 'index']);

        return $router;
    }

    /** @return array<string, mixed> */
    private function validPayload(): array
    {
        return [
            'titulo' => 'Evento Futuro',
            'tipo_evento' => 'voluntariado',
            'data_hora_inicio' => (new \DateTimeImmutable('+1 day'))->format(DATE_ATOM),
            'num_max_voluntarios' => 10,
            'endereco' => [
                'rua' => 'Rua Central',
                'bairro' => 'Centro',
                'cidade' => 'Sao Paulo',
                'uf' => 'SP',
                'codigo_postal' => '01001000',
            ],
        ];
    }
}
