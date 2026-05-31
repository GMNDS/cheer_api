<?php

namespace Cheer\Repositories;

use Cheer\Core\Database;
use PDO;

final class InscricaoRepository
{
    private const ALLOWED_STATUSES = ['pendente', 'aprovado', 'rejeitado'];
    
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

    /** @return list<array<string, mixed>> */
    public function listByEvento(int $eventoId): array
    {
        $statement = Database::connection()->prepare(
            'SELECT
            v.id,
            v.nome,
            v.email,
            v.telefone,
            ve.status,
            ve.data_inscricao
            FROM voluntario_evento ve
            INNER JOIN voluntario v ON v.id = ve.id_voluntario
            WHERE ve.id_evento = :id_evento
            ORDER BY ve.data_inscricao ASC'
        );
        $statement->execute(['id_evento' => $eventoId]);
        
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findInscricao(int $voluntarioId, int $eventoId): array|false
    {

        $statement = Database::connection()->prepare(
            'SELECT * FROM voluntario_evento
             WHERE id_voluntario = :id_voluntario AND id_evento = :id_evento
             LIMIT 1'
        );
        $statement->execute([
            'id_voluntario' => $voluntarioId,
            'id_evento'     => $eventoId,
        ]);

        return $statement->fetch(PDO::FETCH_ASSOC);
    }

    public function updateStatus(int $voluntarioId, int $eventoId, string $status): bool
    {
        if (!in_array($status, self::ALLOWED_STATUSES, true)) {
            return false;
        }

        $statement = Database::connection()->prepare(
            'UPDATE voluntario_evento
             SET status = :status
             WHERE id_voluntario = :id_voluntario AND id_evento = :id_evento'
        );

        return $statement->execute([
            'status'        => $status,
            'id_voluntario' => $voluntarioId,
            'id_evento'     => $eventoId,
        ]);
    }

    public function isAllowedStatus(string $status): bool
    {
        return in_array($status, self::ALLOWED_STATUSES, true);
    }
}
        
    




    
