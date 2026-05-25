<?php

namespace Cheer\Repositories;

use Cheer\Core\Database;
use PDO;

final class EventoRepository
{
    /** @return list<array<string, mixed>> */
    public function listAvailable(): array
    {
        $statement = Database::connection()->query(
            'SELECT
                ev.id,
                ev.titulo,
                inst.nome AS instituicao,
                end.cidade,
                end.uf,
                ev.data_hora_inicio AS data,
                ev.data_hora_termino,
                ev.tipo_evento,
                ev.num_max_voluntarios AS vagas,
                ev.descricao,
                COUNT(ve.id_voluntario) AS inscritos
             FROM evento ev
             INNER JOIN instituicao inst ON inst.id = ev.id_instituicao
             INNER JOIN enderecos end ON end.id = ev.id_endereco
             LEFT JOIN voluntario_evento ve ON ve.id_evento = ev.id
             GROUP BY ev.id, inst.nome, end.cidade, end.uf
             ORDER BY ev.data_hora_inicio ASC'
        );

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @param array<string, mixed> $data */
    public function create(int $instituicaoId, int $enderecoId, array $data): int
    {
        $statement = Database::connection()->prepare(
            'INSERT INTO evento (
                id_instituicao,
                id_endereco,
                titulo,
                constancia,
                data_hora_inicio,
                data_hora_termino,
                tipo_evento,
                num_max_voluntarios,
                descricao,
                created_at,
                updated_at
             ) VALUES (
                :id_instituicao,
                :id_endereco,
                :titulo,
                :constancia,
                :data_hora_inicio,
                :data_hora_termino,
                :tipo_evento,
                :num_max_voluntarios,
                :descricao,
                CURRENT_TIMESTAMP,
                CURRENT_TIMESTAMP
             )'
        );
        $statement->execute([
            'id_instituicao' => $instituicaoId,
            'id_endereco' => $enderecoId,
            'titulo' => $data['titulo'] ?? '',
            'constancia' => $data['constancia'] ?? null,
            'data_hora_inicio' => $data['data_hora_inicio'] ?? $data['data'] ?? '',
            'data_hora_termino' => $data['data_hora_termino'] ?? null,
            'tipo_evento' => $data['tipo_evento'] ?? 'voluntariado',
            'num_max_voluntarios' => $data['num_max_voluntarios'] ?? $data['vagas'] ?? null,
            'descricao' => $data['descricao'] ?? null,
        ]);

        return (int) Database::connection()->lastInsertId();
    }

    /** @return list<array<string, mixed>> */
    public function listByInstituicao(int $instituicaoId): array
    {
        $statement = Database::connection()->prepare(
            'SELECT
                ev.id,
                ev.titulo,
                end.cidade,
                end.uf,
                ev.data_hora_inicio AS data,
                ev.data_hora_termino,
                ev.tipo_evento,
                ev.num_max_voluntarios AS vagas,
                ev.descricao,
                COUNT(ve.id_voluntario) AS inscritos
             FROM evento ev
             INNER JOIN enderecos end ON end.id = ev.id_endereco
             LEFT JOIN voluntario_evento ve ON ve.id_evento = ev.id
             WHERE ev.id_instituicao = :id_instituicao
             GROUP BY ev.id, end.cidade, end.uf
             ORDER BY ev.data_hora_inicio DESC'
        );
        $statement->execute(['id_instituicao' => $instituicaoId]);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }
}
