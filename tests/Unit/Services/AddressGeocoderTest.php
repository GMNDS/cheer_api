<?php

namespace Tests\Unit\Services;

use Cheer\Services\AddressGeocoder;
use Tests\TestCase;

final class AddressGeocoderTest extends TestCase
{
    public function testBuildsAQueryFromAddressParts(): void
    {
        $geocoder = new AddressGeocoder();

        self::assertSame(
            'Rua Central, Centro, Sao Paulo, SP, 01001000',
            $geocoder->buildQuery([
                'rua' => 'Rua Central',
                'bairro' => 'Centro',
                'cidade' => 'Sao Paulo',
                'uf' => 'SP',
                'codigo_postal' => '01001000',
            ])
        );
    }

    public function testResolveReturnsCoordinatesAlreadyProvidedInPayload(): void
    {
        $geocoder = new AddressGeocoder();

        self::assertSame(
            ['lat' => -23.55052, 'lng' => -46.63331],
            $geocoder->resolve([
                'lat' => '-23.55052',
                'lng' => '-46.63331',
            ])
        );
    }
}