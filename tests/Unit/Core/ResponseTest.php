<?php

namespace Tests\Unit\Core;

use Cheer\Core\Response;
use Tests\TestCase;

final class ResponseTest extends TestCase
{
    public function testSendsJsonWithConfiguredStatus(): void
    {
        $result = $this->render(Response::json([
            'status' => 'success',
            'path' => '/saude',
        ], 201));

        self::assertSame(201, $result['status']);
        self::assertJsonStringEqualsJsonString(
            '{"status":"success","path":"/saude"}',
            $result['body']
        );
    }

    public function testSendsTextContent(): void
    {
        $result = $this->render(Response::content('ready', 'text/plain', 202));

        self::assertSame(202, $result['status']);
        self::assertSame('ready', $result['body']);
    }
}
