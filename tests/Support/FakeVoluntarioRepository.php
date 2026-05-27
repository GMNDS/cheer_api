<?php

namespace Tests\Support;

final class FakeVoluntarioRepository
{
    public function __construct(private readonly FakeScenarioState $state)
    {
    }

    /** @param array<string, mixed> $data */
    public function create(string $authentikUser, int $enderecoId, array $data): int
    {
        $id = $this->state->nextVolunteerId++;
        $this->state->volunteers[$id] = [
            'id' => $id,
            'authentik_user' => $authentikUser,
            'id_endereco' => $enderecoId,
        ] + $data;

        return $id;
    }
}