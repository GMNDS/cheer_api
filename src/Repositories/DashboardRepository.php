<?php

namespace Cheer\Repositories;

use Cheer\Core\Database;
use PDO;

final class DashboardRepository
{
    /** @return array<string, mixed> */
    public function institutionDashboard(int $instituicaoId): array
    {
        $events = $this->events($instituicaoId);
        $subscriptions = $this->subscriptions($instituicaoId);
        $now = new \DateTimeImmutable();
        $totalCapacity = 0;

        foreach ($events as $event) {
            $vagas = (int) ($event['vagas'] ?? 0);

            if ($vagas > 0) {
                $totalCapacity += $vagas;
            }
        }

        $statusCounts = [
            'pendente' => 0,
            'aprovado' => 0,
            'rejeitado' => 0,
        ];

        foreach ($subscriptions as $subscription) {
            $status = (string) ($subscription['status'] ?? 'pendente');
            $statusCounts[$status] = ($statusCounts[$status] ?? 0) + 1;
        }

        $totalSubscriptions = count($subscriptions);
        $futureEvents = count(array_filter($events, static function (array $event) use ($now): bool {
            $date = new \DateTimeImmutable((string) ($event['data'] ?? 'now'));

            return $date >= $now;
        }));

        return [
            'kpis' => [
                'total_eventos' => count($events),
                'eventos_futuros' => $futureEvents,
                'total_inscritos' => $totalSubscriptions,
                'inscricoes_pendentes' => $statusCounts['pendente'] ?? 0,
                'inscricoes_aprovadas' => $statusCounts['aprovado'] ?? 0,
                'inscricoes_rejeitadas' => $statusCounts['rejeitado'] ?? 0,
                'taxa_ocupacao_percentual' => $totalCapacity > 0 ? round(($totalSubscriptions / $totalCapacity) * 100, 1) : 0,
            ],
            'series' => [
                'eventos_por_mes' => $this->eventsByMonth($events),
                'eventos_por_tipo' => $this->eventsByType($events),
                'inscricoes_por_status' => [
                    ['label' => 'Pendente', 'value' => $statusCounts['pendente'] ?? 0],
                    ['label' => 'Aprovado', 'value' => $statusCounts['aprovado'] ?? 0],
                    ['label' => 'Rejeitado', 'value' => $statusCounts['rejeitado'] ?? 0],
                ],
                'inscritos_por_evento' => array_map(
                    static fn (array $event): array => ['label' => (string) $event['titulo'], 'value' => (int) $event['inscritos']],
                    array_slice($events, 0, 10)
                ),
            ],
            'tables' => [
                'eventos' => $events,
                'inscritos_recentes' => array_slice($subscriptions, 0, 25),
            ],
        ];
    }

    /** @return list<array<string, mixed>> */
    private function events(int $instituicaoId): array
    {
        $statement = Database::connection()->prepare(
            'SELECT
                ev.id,
                ev.titulo,
                ev.data_hora_inicio AS data,
                ev.data_hora_termino,
                ev.tipo_evento,
                end.cidade,
                end.uf,
                ev.num_max_voluntarios AS vagas,
                COUNT(ve.id_voluntario) AS inscritos
             FROM evento ev
             INNER JOIN enderecos end ON end.id = ev.id_endereco
             LEFT JOIN voluntario_evento ve ON ve.id_evento = ev.id
             WHERE ev.id_instituicao = :id_instituicao
             GROUP BY ev.id, end.cidade, end.uf, ev.data_hora_inicio, ev.data_hora_termino, ev.tipo_evento, ev.num_max_voluntarios
             ORDER BY ev.data_hora_inicio DESC'
        );
        $statement->execute(['id_instituicao' => $instituicaoId]);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return list<array<string, mixed>> */
    private function subscriptions(int $instituicaoId): array
    {
        $statement = Database::connection()->prepare(
            'SELECT
                ev.id AS id_evento,
                ev.titulo AS evento,
                v.id AS id_voluntario,
                v.nome,
                v.email,
                v.telefone,
                ve.status,
                ve.data_inscricao
             FROM voluntario_evento ve
             INNER JOIN evento ev ON ev.id = ve.id_evento
             INNER JOIN voluntario v ON v.id = ve.id_voluntario
             WHERE ev.id_instituicao = :id_instituicao
             ORDER BY ve.data_inscricao DESC'
        );
        $statement->execute(['id_instituicao' => $instituicaoId]);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @param list<array<string, mixed>> $events */
    private function eventsByMonth(array $events): array
    {
        $counts = [];

        foreach ($events as $event) {
            $date = new \DateTimeImmutable((string) ($event['data'] ?? 'now'));
            $key = $date->format('Y-m');
            $counts[$key] = ($counts[$key] ?? 0) + 1;
        }

        ksort($counts);

        return array_map(
            static fn (string $key, int $value): array => ['label' => $key, 'value' => $value],
            array_keys($counts),
            array_values($counts)
        );
    }

    /** @param list<array<string, mixed>> $events */
    private function eventsByType(array $events): array
    {
        $counts = [];

        foreach ($events as $event) {
            $type = (string) ($event['tipo_evento'] ?? 'nao_informado');
            $counts[$type] = ($counts[$type] ?? 0) + 1;
        }

        return array_map(
            static fn (string $key, int $value): array => ['label' => $key, 'value' => $value],
            array_keys($counts),
            array_values($counts)
        );
    }
}
