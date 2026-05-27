<?php

namespace Tests\Support;

use Cheer\Services\GeoDistance;

final class FakeEventoRepository
{
    public function __construct(private readonly FakeScenarioState $state)
    {
    }

    /** @param array<string, mixed> $data */
    public function create(int $instituicaoId, int $enderecoId, array $data): int
    {
        $id = $this->state->nextEventId++;
        $this->state->events[$id] = [
            'id' => $id,
            'id_instituicao' => $instituicaoId,
            'id_endereco' => $enderecoId,
            'titulo' => $data['titulo'] ?? '',
            'data' => $data['data_hora_inicio'] ?? $data['data'] ?? '',
            'data_hora_termino' => $data['data_hora_termino'] ?? null,
            'tipo_evento' => $data['tipo_evento'] ?? 'voluntariado',
            'vagas' => $data['num_max_voluntarios'] ?? $data['vagas'] ?? null,
            'descricao' => $data['descricao'] ?? null,
        ];

        return $id;
    }

    /** @return list<array<string, mixed>> */
    public function listAvailable(?float $latitude = null, ?float $longitude = null, ?float $radiusKm = null): array
    {
        $radiusKm ??= 25.0;
        $events = [];

        foreach ($this->state->events as $event) {
            $institution = $this->state->institutions[$event['id_instituicao']] ?? null;
            $address = $this->state->addresses[$event['id_endereco']] ?? null;

            if (!is_array($institution) || !is_array($address)) {
                continue;
            }

            $row = [
                'id' => $event['id'],
                'titulo' => $event['titulo'],
                'instituicao' => (string) ($institution['nome'] ?? 'Instituicao'),
                'cidade' => (string) ($address['cidade'] ?? ''),
                'uf' => (string) ($address['uf'] ?? ''),
                'data' => $event['data'],
                'data_hora_termino' => $event['data_hora_termino'],
                'tipo_evento' => $event['tipo_evento'],
                'vagas' => $event['vagas'],
                'descricao' => $event['descricao'],
                'inscritos' => $this->countSignupsForEvent($event['id']),
            ];

            if ($latitude !== null && $longitude !== null) {
                if (!isset($address['lat'], $address['lng']) || !is_numeric($address['lat']) || !is_numeric($address['lng'])) {
                    continue;
                }

                $distance = GeoDistance::between($latitude, $longitude, (float) $address['lat'], (float) $address['lng']);

                if ($distance > $radiusKm) {
                    continue;
                }

                $row['_distance_km'] = $distance;
            }

            $events[] = $row;
        }

        if ($latitude !== null && $longitude !== null) {
            usort($events, static fn (array $left, array $right): int => ($left['_distance_km'] <=> $right['_distance_km']) ?: strcmp((string) $left['data'], (string) $right['data']));
        }

        return array_map(static function (array $event): array {
            unset($event['_distance_km']);

            return $event;
        }, $events);
    }

    /** @return list<array<string, mixed>> */
    public function listByInstituicao(int $instituicaoId): array
    {
        $events = [];

        foreach ($this->state->events as $event) {
            if ((int) $event['id_instituicao'] !== $instituicaoId) {
                continue;
            }

            $address = $this->state->addresses[$event['id_endereco']] ?? [];

            $events[] = [
                'id' => $event['id'],
                'titulo' => $event['titulo'],
                'cidade' => (string) ($address['cidade'] ?? ''),
                'uf' => (string) ($address['uf'] ?? ''),
                'data' => $event['data'],
                'data_hora_termino' => $event['data_hora_termino'],
                'tipo_evento' => $event['tipo_evento'],
                'vagas' => $event['vagas'],
                'descricao' => $event['descricao'],
                'inscritos' => $this->countSignupsForEvent($event['id']),
            ];
        }

        return $events;
    }

    private function countSignupsForEvent(int $eventId): int
    {
        return count(array_filter($this->state->signups, static fn (array $signup): bool => $signup['event_id'] === $eventId));
    }
}