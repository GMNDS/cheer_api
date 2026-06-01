<?php

namespace Tests\Support;

final class FakeDashboardRepository
{
    public function __construct(private readonly FakeScenarioState $state)
    {
    }

    /** @return array<string, mixed> */
    public function institutionDashboard(int $instituicaoId): array
    {
        $eventos = $this->eventos($instituicaoId);
        $inscritosRecentes = $this->inscritosRecentes($instituicaoId);
        $statusCounts = [
            'pendente' => 0,
            'aprovado' => 0,
            'rejeitado' => 0,
        ];

        foreach ($inscritosRecentes as $inscrito) {
            $status = (string) ($inscrito['status'] ?? '');

            if (array_key_exists($status, $statusCounts)) {
                $statusCounts[$status]++;
            }
        }

        $totalInscritos = array_sum(array_map(static fn (array $evento): int => (int) $evento['inscritos'], $eventos));
        $totalVagas = array_sum(array_map(static fn (array $evento): int => max((int) ($evento['vagas'] ?? 0), 0), $eventos));

        return [
            'kpis' => [
                'total_eventos' => count($eventos),
                'eventos_futuros' => count(array_filter($eventos, static fn (array $evento): bool => (string) $evento['data'] >= date(DATE_ATOM))),
                'total_inscritos' => $totalInscritos,
                'inscricoes_pendentes' => $statusCounts['pendente'],
                'inscricoes_aprovadas' => $statusCounts['aprovado'],
                'inscricoes_rejeitadas' => $statusCounts['rejeitado'],
                'taxa_ocupacao_percentual' => $totalVagas > 0 ? round(($totalInscritos / $totalVagas) * 100, 2) : 0,
            ],
            'series' => [
                'eventos_por_mes' => $this->eventosPorMes($eventos),
                'eventos_por_tipo' => $this->eventosPorTipo($eventos),
                'inscricoes_por_status' => [
                    ['label' => 'Pendente', 'value' => $statusCounts['pendente']],
                    ['label' => 'Aprovado', 'value' => $statusCounts['aprovado']],
                    ['label' => 'Rejeitado', 'value' => $statusCounts['rejeitado']],
                ],
                'inscritos_por_evento' => array_map(static fn (array $evento): array => [
                    'label' => (string) $evento['titulo'],
                    'value' => (int) $evento['inscritos'],
                ], $eventos),
            ],
            'tables' => [
                'eventos' => $eventos,
                'inscritos_recentes' => $inscritosRecentes,
            ],
        ];
    }

    /** @return list<array<string, mixed>> */
    private function eventos(int $instituicaoId): array
    {
        $eventos = [];

        foreach ($this->state->events as $event) {
            if ((int) $event['id_instituicao'] !== $instituicaoId) {
                continue;
            }

            $address = $this->state->addresses[$event['id_endereco']] ?? [];

            $eventos[] = [
                'id' => $event['id'],
                'titulo' => $event['titulo'],
                'data' => $event['data'],
                'data_hora_termino' => $event['data_hora_termino'] ?? null,
                'tipo_evento' => $event['tipo_evento'],
                'cidade' => (string) ($address['cidade'] ?? ''),
                'uf' => (string) ($address['uf'] ?? ''),
                'vagas' => $event['vagas'],
                'inscritos' => count(array_filter($this->state->signups, static fn (array $signup): bool => $signup['event_id'] === $event['id'])),
            ];
        }

        return $eventos;
    }

    /** @return list<array<string, mixed>> */
    private function inscritosRecentes(int $instituicaoId): array
    {
        $rows = [];

        foreach ($this->state->signups as $signup) {
            $event = $this->state->events[$signup['event_id']] ?? null;

            if (!is_array($event) || (int) $event['id_instituicao'] !== $instituicaoId) {
                continue;
            }

            $volunteer = $this->state->volunteers[$signup['volunteer_id']] ?? [];

            $rows[] = [
                'id_evento' => $event['id'],
                'evento' => $event['titulo'],
                'id_voluntario' => $signup['volunteer_id'],
                'nome' => (string) ($volunteer['nome'] ?? ''),
                'email' => (string) ($volunteer['email'] ?? ''),
                'telefone' => $volunteer['telefone'] ?? null,
                'status' => $signup['status'],
                'data_inscricao' => $signup['data_inscricao'] ?? '2026-06-01 12:00:00',
            ];
        }

        return $rows;
    }

    /** @param list<array<string, mixed>> $eventos */
    private function eventosPorMes(array $eventos): array
    {
        $counts = [];

        foreach ($eventos as $evento) {
            $label = substr((string) $evento['data'], 0, 7);
            $counts[$label] = ($counts[$label] ?? 0) + 1;
        }

        ksort($counts);

        return array_map(static fn (string $label, int $value): array => [
            'label' => $label,
            'value' => $value,
        ], array_keys($counts), array_values($counts));
    }

    /** @param list<array<string, mixed>> $eventos */
    private function eventosPorTipo(array $eventos): array
    {
        $counts = [];

        foreach ($eventos as $evento) {
            $label = (string) $evento['tipo_evento'];
            $counts[$label] = ($counts[$label] ?? 0) + 1;
        }

        return array_map(static fn (string $label, int $value): array => [
            'label' => $label,
            'value' => $value,
        ], array_keys($counts), array_values($counts));
    }
}
