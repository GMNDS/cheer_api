<?php

namespace Cheer\Repositories;

use Cheer\Core\Database;
use PDO;

final class InscricaoRepository
{
    public function create(int $voluntarioId, int $eventoId): void
    {
        $statement = Database::connection()->prepare(
            'INSERT INTO voluntario_evento (id_voluntario, id_evento, status, data_inscricao)
             VALUES (:id_voluntario, :id_evento, :status, CURRENT_TIMESTAMP)'
        );
        $statement->execute([
            'id_voluntario' => $voluntarioId,
            'id_evento' => $eventoId,
            'status' => 'pendente',
        ]);
    }

    /** @return list<array<string, mixed>> */
    public function listByVoluntario(int $voluntarioId): array
    {
        $statement = Database::connection()->prepare(
            'SELECT
                ev.id,
                ev.titulo,
                inst.nome AS instituicao,
                end.cidade,
                end.uf,
                ev.data_hora_inicio AS data,
                ve.status,
                ve.data_inscricao
             FROM voluntario_evento ve
             INNER JOIN evento ev ON ev.id = ve.id_evento
             INNER JOIN instituicao inst ON inst.id = ev.id_instituicao
             INNER JOIN enderecos end ON end.id = ev.id_endereco
             WHERE ve.id_voluntario = :id_voluntario
             ORDER BY ve.data_inscricao DESC'
        );
        $statement->execute(['id_voluntario' => $voluntarioId]);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }
}
