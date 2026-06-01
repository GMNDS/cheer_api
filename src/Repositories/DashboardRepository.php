<?php

namespace Cheer\Repositories;

use Cheer\Core\Database;
use DateTimeImmutable;
use PDO;

final class DashboardRepository
{
    /** @return array<string, mixed> */
    public function institutionDashboard(int $instituicaoId): array
    {
        $eventos = $this->eventos($instituicaoId);
        $statusCounts = $this->inscricoesPorStatus($instituicaoId);
        $vagas = array_sum(array_map(static fn (array $evento): int => max((int) ($evento['vagas'] ?? 0), 0), $eventos));
        $totalInscritos = array_sum(array_map(static fn (array $evento): int => (int) ($evento['inscritos'] ?? 0), $eventos));

        return [
            'kpis' => [
                'total_eventos' => count($eventos),
                'eventos_futuros' => $this->countFutureEvents($eventos),
                'total_inscritos' => $totalInscritos,
                'inscricoes_pendentes' => $statusCounts['pendente'] ?? 0,
                'inscricoes_aprovadas' => $statusCounts['aprovado'] ?? 0,
                'inscricoes_rejeitadas' => $statusCounts['rejeitado'] ?? 0,
                'taxa_ocupacao_percentual' => $vagas > 0 ? round(($totalInscritos / $vagas) * 100, 2) : 0,
            ],
            'series' => [
                'eventos_por_mes' => $this->eventosPorMes($instituicaoId),
                'eventos_por_tipo' => $this->eventosPorTipo($instituicaoId),
                'inscricoes_por_status' => $this->statusSeries($statusCounts),
                'inscritos_por_evento' => array_map(
                    static fn (array $evento): array => [
                        'label' => (string) $evento['titulo'],
                        'value' => (int) $evento['inscritos'],
                    ],
                    $eventos
                ),
            ],
            'tables' => [
                'eventos' => $eventos,
                'inscritos_recentes' => $this->inscritosRecentes($instituicaoId),
            ],
        ];
    }

    /** @return list<array<string, mixed>> */
    private function eventos(int $instituicaoId): array
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
             GROUP BY ev.id, ev.titulo, ev.data_hora_inicio, ev.data_hora_termino, ev.tipo_evento, end.cidade, end.uf, ev.num_max_voluntarios
             ORDER BY ev.data_hora_inicio DESC'
        );
        $statement->execute(['id_instituicao' => $instituicaoId]);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array<string, int> */
    private function inscricoesPorStatus(int $instituicaoId): array
    {
        $statement = Database::connection()->prepare(
            'SELECT ve.status, COUNT(*) AS total
             FROM voluntario_evento ve
             INNER JOIN evento ev ON ev.id = ve.id_evento
             WHERE ev.id_instituicao = :id_instituicao
             GROUP BY ve.status'
        );
        $statement->execute(['id_instituicao' => $instituicaoId]);

        $counts = [];
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $counts[(string) ($row['status'] ?? '')] = (int) ($row['total'] ?? 0);
        }

        return $counts;
    }

    /** @return list<array{label: string, value: int}> */
    private function eventosPorMes(int $instituicaoId): array
    {
        $statement = Database::connection()->prepare(
            "SELECT DATE_FORMAT(ev.data_hora_inicio, '%Y-%m') AS label, COUNT(*) AS value
             FROM evento ev
             WHERE ev.id_instituicao = :id_instituicao
             GROUP BY DATE_FORMAT(ev.data_hora_inicio, '%Y-%m')
             ORDER BY label ASC"
        );
        $statement->execute(['id_instituicao' => $instituicaoId]);

        return array_map([$this, 'seriesRow'], $statement->fetchAll(PDO::FETCH_ASSOC));
    }

    /** @return list<array{label: string, value: int}> */
    private function eventosPorTipo(int $instituicaoId): array
    {
        $statement = Database::connection()->prepare(
            'SELECT ev.tipo_evento AS label, COUNT(*) AS value
             FROM evento ev
             WHERE ev.id_instituicao = :id_instituicao
             GROUP BY ev.tipo_evento
             ORDER BY value DESC, label ASC'
        );
        $statement->execute(['id_instituicao' => $instituicaoId]);

        return array_map([$this, 'seriesRow'], $statement->fetchAll(PDO::FETCH_ASSOC));
    }

    /** @return list<array<string, mixed>> */
    private function inscritosRecentes(int $instituicaoId): array
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
             ORDER BY ve.data_inscricao DESC
             LIMIT 25'
        );
        $statement->execute(['id_instituicao' => $instituicaoId]);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @param array<string, int> $counts */
    private function statusSeries(array $counts): array
    {
        return [
            ['label' => 'Pendente', 'value' => $counts['pendente'] ?? 0],
            ['label' => 'Aprovado', 'value' => $counts['aprovado'] ?? 0],
            ['label' => 'Rejeitado', 'value' => $counts['rejeitado'] ?? 0],
        ];
    }

    /** @param list<array<string, mixed>> $eventos */
    private function countFutureEvents(array $eventos): int
    {
        $now = new DateTimeImmutable();
        $total = 0;

        foreach ($eventos as $evento) {
            $date = $this->parseDate((string) ($evento['data'] ?? ''));

            if ($date !== null && $date >= $now) {
                $total++;
            }
        }

        return $total;
    }

    /** @param array<string, mixed> $row */
    private function seriesRow(array $row): array
    {
        return [
            'label' => (string) ($row['label'] ?? 'Nao informado'),
            'value' => (int) ($row['value'] ?? 0),
        ];
    }

    private function parseDate(string $value): ?DateTimeImmutable
    {
        try {
            return $value === '' ? null : new DateTimeImmutable($value);
        } catch (\Exception) {
            return null;
        }
    }
}
