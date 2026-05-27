<?php

namespace Tests\Support;

final class FakeScenarioState
{
    /** @var array<int, array<string, mixed>> */
    public array $addresses = [];

    /** @var array<int, array<string, mixed>> */
    public array $institutions = [];

    /** @var array<int, array<string, mixed>> */
    public array $volunteers = [];

    /** @var array<int, array<string, mixed>> */
    public array $events = [];

    /** @var list<array{volunteer_id: int, event_id: int, status: string}> */
    public array $signups = [];

    /** @var list<array<string, mixed>> */
    public array $logs = [];

    public int $nextAddressId = 1;
    public int $nextInstitutionId = 1;
    public int $nextVolunteerId = 1;
    public int $nextEventId = 1;
}