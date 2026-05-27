<?php

namespace Tests\Unit\Services;

use Cheer\Services\GeoDistance;
use Tests\TestCase;

final class GeoDistanceTest extends TestCase
{
    public function testReturnsZeroForTheSamePoint(): void
    {
        self::assertSame(0.0, GeoDistance::between(-23.5505, -46.6333, -23.5505, -46.6333));
    }

    public function testCalculatesDistanceInKilometers(): void
    {
        $distance = GeoDistance::between(0.0, 0.0, 0.0, 1.0);

        self::assertEqualsWithDelta(111.19, $distance, 0.25);
    }
}