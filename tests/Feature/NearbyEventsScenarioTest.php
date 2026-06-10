<?php

namespace Tests\Feature;

use Cheer\Controllers\EventoController;
use Cheer\Controllers\InscricaoController;
use Cheer\Controllers\RegistrationController;
use Cheer\Core\Request;
use Cheer\Core\Router;
use Tests\Support\FakeAuthentikAdminClient;
use Tests\Support\FakeEnderecoRepository;
use Tests\Support\FakeEventoRepository;
use Tests\Support\FakeInstituicaoRepository;
use Tests\Support\FakeInscricaoRepository;
use Tests\Support\FakeLogRepository;
use Tests\Support\FakeScenarioState;
use Tests\Support\FakeTransactionManager;
use Tests\Support\FakeVoluntarioRepository;
use Tests\TestCase;

final class NearbyEventsScenarioTest extends TestCase
{
    public function testFallsBackToAllEventsWhenNearbySearchIsEmpty(): void
    {
        $state = new FakeScenarioState();
        $state->institutions[1] = [
            'id' => 1,
            'nome' => 'Instituicao Distante',
        ];
        $state->addresses[1] = [
            'id' => 1,
            'cidade' => 'Rio de Janeiro',
            'uf' => 'RJ',
            'lat' => -22.90685,
            'lng' => -43.17290,
        ];
        $state->events[1] = [
            'id' => 1,
            'id_instituicao' => 1,
            'id_endereco' => 1,
            'titulo' => 'Mutirao disponivel',
            'data' => (new \DateTimeImmutable('+7 days'))->format('Y-m-d\T09:00:00P'),
            'data_hora_termino' => null,
            'tipo_evento' => 'voluntariado',
            'vagas' => 10,
            'descricao' => 'Evento fora do raio informado.',
        ];

        $controller = new EventoController(
            new FakeTransactionManager(),
            new FakeEnderecoRepository($state),
            new FakeEventoRepository($state),
            new FakeLogRepository($state)
        );

        $result = $this->render($controller->index(new Request('GET', '/api/eventos', [], [
            'lat' => -23.55100,
            'lng' => -46.63290,
            'raio_km' => 1,
        ], [])));

        self::assertSame(200, $result['status']);

        $body = json_decode($result['body'], true);
        self::assertCount(1, $body['data']);
        self::assertSame('Mutirao disponivel', $body['data'][0]['titulo']);
    }

    public function testCreatesInstitutionVolunteerSignupAndListsNearbyEvents(): void
    {
        $state = new FakeScenarioState();

        $registration = new RegistrationController(
            new FakeAuthentikAdminClient(),
            new FakeTransactionManager(),
            new FakeEnderecoRepository($state),
            new FakeInstituicaoRepository($state),
            new FakeVoluntarioRepository($state),
            new FakeLogRepository($state)
        );

        $eventos = new EventoController(
            new FakeTransactionManager(),
            new FakeEnderecoRepository($state),
            new FakeEventoRepository($state),
            new FakeLogRepository($state)
        );

        $inscricoes = new InscricaoController(
            new FakeInscricaoRepository($state),
            new FakeLogRepository($state)
        );

        $router = new Router();
        $router->post('/api/auth/register-instituicao', [$registration, 'registerInstituicao']);
        $router->post('/api/auth/register-voluntario', [$registration, 'registerVoluntario']);
        $router->post('/api/eventos', [$eventos, 'store']);
        $router->post('/api/eventos/inscrever', [$inscricoes, 'store']);
        $router->get('/api/eventos', [$eventos, 'index']);
        $router->get('/api/minhas-inscricoes', [$inscricoes, 'minhasInscricoes']);

        $institutionPayload = [
            'nome' => 'Instituicao Centro',
            'email' => 'instituicao.teste@cheer.local',
            'password' => 'Teste@12345',
            'cnpj' => '12345678000199',
            'tipo' => 'ONG',
            'endereco' => [
                'rua' => 'Rua Central',
                'bairro' => 'Centro',
                'cidade' => 'Sao Paulo',
                'uf' => 'SP',
                'codigo_postal' => '01001000',
                'lat' => -23.55052,
                'lng' => -46.63331,
            ],
        ];

        $volunteerPayload = [
            'nome' => 'Voluntario Proximo',
            'email' => 'voluntario.teste@cheer.local',
            'password' => 'Teste@12345',
            'cpf' => '12345678901',
            'endereco' => [
                'rua' => 'Rua do Voluntario',
                'bairro' => 'Centro',
                'cidade' => 'Sao Paulo',
                'uf' => 'SP',
                'codigo_postal' => '01001000',
                'lat' => -23.55100,
                'lng' => -46.63290,
            ],
        ];

        $institutionResult = $this->render($router->dispatch(new Request('POST', '/api/auth/register-instituicao', [], [], $institutionPayload)));
        self::assertSame(201, $institutionResult['status']);
        $institutionBody = json_decode($institutionResult['body'], true);
        $institutionId = (int) $institutionBody['data']['id'];

        $_SESSION['profile'] = [
            'tipo' => 'instituicao',
            'id' => $institutionId,
        ];

        $closeEventStart = (new \DateTimeImmutable('+7 days'))->format('Y-m-d\T09:00:00P');
        $farEventStart = (new \DateTimeImmutable('+8 days'))->format('Y-m-d\T09:00:00P');

        $eventClosePayload = [
            'titulo' => 'Mutirao do Centro',
            'tipo_evento' => 'voluntariado',
            'data_hora_inicio' => $closeEventStart,
            'descricao' => 'Evento perto da voluntaria.',
            'endereco' => [
                'rua' => 'Rua Central 10',
                'bairro' => 'Centro',
                'cidade' => 'Sao Paulo',
                'uf' => 'SP',
                'codigo_postal' => '01001000',
                'lat' => -23.55110,
                'lng' => -46.63310,
            ],
        ];

        $eventFarPayload = [
            'titulo' => 'Mutirao distante',
            'tipo_evento' => 'voluntariado',
            'data_hora_inicio' => $farEventStart,
            'descricao' => 'Evento longe da voluntaria.',
            'endereco' => [
                'rua' => 'Rua Longe 100',
                'bairro' => 'Bairro Distante',
                'cidade' => 'Rio de Janeiro',
                'uf' => 'RJ',
                'codigo_postal' => '20000000',
                'lat' => -22.90685,
                'lng' => -43.17290,
            ],
        ];

        $closeEventResult = $this->render($router->dispatch(new Request('POST', '/api/eventos', [], [], $eventClosePayload)));
        self::assertSame(201, $closeEventResult['status']);
        $closeEventBody = json_decode($closeEventResult['body'], true);
        $closeEventId = (int) $closeEventBody['data']['id'];

        $farEventResult = $this->render($router->dispatch(new Request('POST', '/api/eventos', [], [], $eventFarPayload)));
        self::assertSame(201, $farEventResult['status']);

        $volunteerResult = $this->render($router->dispatch(new Request('POST', '/api/auth/register-voluntario', [], [], $volunteerPayload)));
        self::assertSame(201, $volunteerResult['status']);
        $volunteerBody = json_decode($volunteerResult['body'], true);
        $volunteerId = (int) $volunteerBody['data']['id'];

        $_SESSION['profile'] = [
            'tipo' => 'voluntario',
            'id' => $volunteerId,
        ];

        $signupResult = $this->render($router->dispatch(new Request('POST', '/api/eventos/inscrever', [], [], ['id_evento' => $closeEventId])));
        self::assertSame(201, $signupResult['status']);

        $nearbyResult = $this->render($router->dispatch(new Request('GET', '/api/eventos', [], ['lat' => -23.55100, 'lng' => -46.63290, 'raio_km' => 5], [])));
        self::assertSame(200, $nearbyResult['status']);

        $nearbyData = json_decode($nearbyResult['body'], true);
        self::assertCount(1, $nearbyData['data']);
        self::assertSame('Mutirao do Centro', $nearbyData['data'][0]['titulo']);

        $inscricoesResult = $this->render($router->dispatch(new Request('GET', '/api/minhas-inscricoes', [], [], [])));
        self::assertSame(200, $inscricoesResult['status']);

        $inscricoesData = json_decode($inscricoesResult['body'], true);
        self::assertCount(1, $inscricoesData['data']);
        self::assertSame('Mutirao do Centro', $inscricoesData['data'][0]['titulo']);
    }
}
