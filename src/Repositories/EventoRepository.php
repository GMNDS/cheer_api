<?php

namespace Cheer\Repositories;

use Cheer\Core\Database;
use Cheer\Services\GeoDistance;
use PDO;

final class EventoRepository
{
    /** @return list<array<string, mixed>> */
    public function listAvailable(?float $latitude = null, ?float $longitude = null, ?float $radiusKm = null): array
    {
        $statement = Database::connection()->query(
            'SELECT
                ev.id,
                ev.titulo,
                inst.nome AS instituicao,
                end.rua,
                end.numero,
                end.complemento,
                end.bairro,
                end.cidade,
                end.uf,
                end.codigo_postal,
                end.lat,
                end.lng,
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
               GROUP BY ev.id, inst.nome, end.rua, end.numero, end.complemento, end.bairro, end.cidade, end.uf, end.codigo_postal, end.lat, end.lng, ev.data_hora_inicio, ev.data_hora_termino, ev.tipo_evento, ev.num_max_voluntarios, ev.descricao
             ORDER BY ev.data_hora_inicio ASC'
        );

        $events = $statement->fetchAll(PDO::FETCH_ASSOC);

        if ($latitude === null || $longitude === null) {
            return array_map([$this, 'sanitizeEvent'], $events);
        }

        $radiusKm = $radiusKm ?? 25.0;
        $filtered = [];

        foreach ($events as $event) {
            if (!isset($event['lat'], $event['lng']) || !is_numeric($event['lat']) || !is_numeric($event['lng'])) {
                continue;
            }

            $distanceKm = GeoDistance::between(
                $latitude,
                $longitude,
                (float) $event['lat'],
                (float) $event['lng']
            );

            if ($distanceKm > $radiusKm) {
                continue;
            }

            $event['_distance_km'] = $distanceKm;
            $filtered[] = $event;
        }

        usort($filtered, static function (array $left, array $right): int {
            $distanceComparison = $left['_distance_km'] <=> $right['_distance_km'];

            if ($distanceComparison !== 0) {
                return $distanceComparison;
            }

            return strcmp((string) ($left['data'] ?? ''), (string) ($right['data'] ?? ''));
        });

        return array_map([$this, 'sanitizeEvent'], $filtered);
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
                end.rua,
                end.numero,
                end.complemento,
                end.bairro,
                end.cidade,
                end.uf,
                end.codigo_postal,
                end.lat,
                end.lng,
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
             GROUP BY ev.id, end.rua, end.numero, end.complemento, end.bairro, end.cidade, end.uf, end.codigo_postal, end.lat, end.lng, ev.data_hora_inicio, ev.data_hora_termino, ev.tipo_evento, ev.num_max_voluntarios, ev.descricao
             ORDER BY ev.data_hora_inicio DESC'
        );
        $statement->execute(['id_instituicao' => $instituicaoId]);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array<string, mixed>|null */
    public function findOwned(int $eventId, int $instituicaoId): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT
                ev.id,
                ev.id_instituicao,
                ev.id_endereco,
                ev.titulo,
                ev.constancia,
                ev.data_hora_inicio AS data,
                ev.data_hora_termino,
                ev.tipo_evento,
                ev.num_max_voluntarios AS vagas,
                ev.descricao,
                end.rua,
                end.numero,
                end.complemento,
                end.bairro,
                end.cidade,
                end.uf,
                end.codigo_postal,
                end.lat,
                end.lng
             FROM evento ev
             INNER JOIN enderecos end ON end.id = ev.id_endereco
             WHERE ev.id = :id AND ev.id_instituicao = :id_instituicao
             LIMIT 1'
        );
        $statement->execute([
            'id' => $eventId,
            'id_instituicao' => $instituicaoId,
        ]);

        $event = $statement->fetch(PDO::FETCH_ASSOC);

        if ($event === false) {
            return null;
        }

        $event['endereco'] = [
            'rua' => $event['rua'],
            'numero' => $event['numero'],
            'complemento' => $event['complemento'],
            'bairro' => $event['bairro'],
            'cidade' => $event['cidade'],
            'uf' => $event['uf'],
            'codigo_postal' => $event['codigo_postal'],
            'lat' => $event['lat'],
            'lng' => $event['lng'],
        ];

        unset($event['rua'], $event['numero'], $event['complemento'], $event['bairro'], $event['cidade'], $event['uf'], $event['codigo_postal'], $event['lat'], $event['lng']);

        return $event;
    }

    /** @param array<string, mixed> $data */
    public function updateOwned(int $eventId, int $instituicaoId, array $data): void
    {
        $statement = Database::connection()->prepare(
            'UPDATE evento
             SET titulo = :titulo,
                 constancia = :constancia,
                 data_hora_inicio = :data_hora_inicio,
                 data_hora_termino = :data_hora_termino,
                 tipo_evento = :tipo_evento,
                 num_max_voluntarios = :num_max_voluntarios,
                 descricao = :descricao,
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = :id AND id_instituicao = :id_instituicao'
        );
        $statement->execute([
            'id' => $eventId,
            'id_instituicao' => $instituicaoId,
            'titulo' => $data['titulo'] ?? '',
            'constancia' => $data['constancia'] ?? null,
            'data_hora_inicio' => $data['data_hora_inicio'] ?? $data['data'] ?? '',
            'data_hora_termino' => $data['data_hora_termino'] ?? null,
            'tipo_evento' => $data['tipo_evento'] ?? 'voluntariado',
            'num_max_voluntarios' => $data['num_max_voluntarios'] ?? $data['vagas'] ?? null,
            'descricao' => $data['descricao'] ?? null,
        ]);
    }

    public function deleteOwned(int $eventId, int $instituicaoId): bool
    {
        $connection = Database::connection();

        $deleteSubscriptions = $connection->prepare(
            'DELETE FROM voluntario_evento
             WHERE id_evento = :id
               AND EXISTS (
                   SELECT 1
                   FROM evento ev
                   WHERE ev.id = voluntario_evento.id_evento
                     AND ev.id_instituicao = :id_instituicao
               )'
        );
        $deleteSubscriptions->execute([
            'id' => $eventId,
            'id_instituicao' => $instituicaoId,
        ]);

        $deleteEvent = $connection->prepare(
            'DELETE FROM evento WHERE id = :id AND id_instituicao = :id_instituicao'
        );
        $deleteEvent->execute([
            'id' => $eventId,
            'id_instituicao' => $instituicaoId,
        ]);

        return $deleteEvent->rowCount() > 0;
    }

    /** @param array<string, mixed> $event */
    private function sanitizeEvent(array $event): array
    {
        unset($event['_distance_km']);

        return $event;
    }
}
