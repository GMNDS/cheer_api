<?php

namespace Tests\Feature;

use Cheer\Controllers\DashboardController;
use Cheer\Core\Request;
use Cheer\Core\Router;
use Tests\Support\FakeDashboardRepository;
use Tests\Support\FakeScenarioState;
use Tests\TestCase;

final class DashboardRouteTest extends TestCase
{
    public function testInstitutionDashboardReturnsAggregatedData(): void
    {
        $state = $this->stateWithDashboardData();
        $_SESSION['profile'] = ['tipo' => 'instituicao', 'id' => 1];

        $result = $this->render($this->router($state)->dispatch(new Request('GET', '/api/dashboard/instituicao', [], [], [])));

        self::assertSame(200, $result['status']);

        $body = json_decode($result['body'], true);
        self::assertSame('success', $body['status']);
        self::assertSame(2, $body['data']['kpis']['total_eventos']);
        self::assertSame(2, $body['data']['kpis']['total_inscritos']);
        self::assertSame(1, $body['data']['kpis']['inscricoes_pendentes']);
        self::assertSame(1, $body['data']['kpis']['inscricoes_aprovadas']);
        self::assertCount(2, $body['data']['tables']['eventos']);
        self::assertCount(2, $body['data']['tables']['inscritos_recentes']);
    }

    public function testDashboardRequiresAuthentication(): void
    {
        $result = $this->render($this->router(new FakeScenarioState())->dispatch(new Request('GET', '/api/dashboard/instituicao', [], [], [])));

        self::assertSame(401, $result['status']);
    }

    public function testDashboardRequiresInstitutionProfile(): void
    {
        $_SESSION['profile'] = ['tipo' => 'voluntario', 'id' => 1];

        $result = $this->render($this->router(new FakeScenarioState())->dispatch(new Request('GET', '/api/dashboard/instituicao', [], [], [])));

        self::assertSame(403, $result['status']);
    }

    public function testInstitutionWithoutEventsReturnsEmptyDashboard(): void
    {
        $_SESSION['profile'] = ['tipo' => 'instituicao', 'id' => 1];

        $result = $this->render($this->router(new FakeScenarioState())->dispatch(new Request('GET', '/api/dashboard/instituicao', [], [], [])));

        self::assertSame(200, $result['status']);

        $body = json_decode($result['body'], true);
        self::assertSame(0, $body['data']['kpis']['total_eventos']);
        self::assertSame([], $body['data']['tables']['eventos']);
        self::assertSame([], $body['data']['tables']['inscritos_recentes']);
    }

    private function router(FakeScenarioState $state): Router
    {
        $router = new Router();
        $dashboard = new DashboardController(new FakeDashboardRepository($state));
        $router->get('/api/dashboard/instituicao', [$dashboard, 'instituicao']);

        return $router;
    }

    private function stateWithDashboardData(): FakeScenarioState
    {
        $state = new FakeScenarioState();
        $state->addresses[1] = ['cidade' => 'Sao Paulo', 'uf' => 'SP'];
        $state->events[1] = [
            'id' => 1,
            'id_instituicao' => 1,
            'id_endereco' => 1,
            'titulo' => 'Mutirao Centro',
            'data' => (new \DateTimeImmutable('+1 day'))->format(DATE_ATOM),
            'data_hora_termino' => null,
            'tipo_evento' => 'voluntariado',
            'vagas' => 10,
        ];
        $state->events[2] = [
            'id' => 2,
            'id_instituicao' => 1,
            'id_endereco' => 1,
            'titulo' => 'Arrecadacao',
            'data' => (new \DateTimeImmutable('+2 days'))->format(DATE_ATOM),
            'data_hora_termino' => null,
            'tipo_evento' => 'arrecadacao',
            'vagas' => 20,
        ];
        $state->volunteers[1] = ['nome' => 'Ana', 'email' => 'ana@example.test', 'telefone' => '11999999999'];
        $state->volunteers[2] = ['nome' => 'Bruno', 'email' => 'bruno@example.test', 'telefone' => null];
        $state->signups[] = ['volunteer_id' => 1, 'event_id' => 1, 'status' => 'pendente'];
        $state->signups[] = ['volunteer_id' => 2, 'event_id' => 2, 'status' => 'aprovado'];

        return $state;
    }
}
