<?php

namespace Tests\Support;

final class FakeEnderecoRepository
{
    public function __construct(private readonly FakeScenarioState $state)
    {
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): int
    {
        $id = $this->state->nextAddressId++;
        $this->state->addresses[$id] = $data + ['id' => $id];

        return $id;
    }
}