<?php

namespace Tests\Support;

final class FakeInscricaoRepository
{
    public function __construct(private readonly FakeScenarioState $state)
    {
    }

    public function create(int $voluntarioId, int $eventoId): void
    {
        $this->state->signups[] = [
            'volunteer_id' => $voluntarioId,
            'event_id' => $eventoId,
            'status' => 'pendente',
        ];
    }

    /** @return list<array<string, mixed>> */
    public function listByVoluntario(int $voluntarioId): array
    {
        $events = [];

        foreach ($this->state->signups as $signup) {
            if ($signup['volunteer_id'] !== $voluntarioId) {
                continue;
            }

            $event = $this->state->events[$signup['event_id']] ?? null;
            if (!is_array($event)) {
                continue;
            }

            $institution = $this->state->institutions[$event['id_instituicao']] ?? [];
            $address = $this->state->addresses[$event['id_endereco']] ?? [];

            $events[] = [
                'id' => $event['id'],
                'titulo' => $event['titulo'],
                'instituicao' => (string) ($institution['nome'] ?? ''),
                'cidade' => (string) ($address['cidade'] ?? ''),
                'uf' => (string) ($address['uf'] ?? ''),
                'data' => $event['data'],
                'status' => $signup['status'],
                'data_inscricao' => '2026-05-26 12:00:00',
            ];
        }

        return $events;
    }
}