<?php

namespace Tests\Feature;

use Cheer\Core\Request;
use Cheer\Core\Router;
use Tests\TestCase;

final class HealthRouteTest extends TestCase
{
    public function testHealthRouteReturnsApplicationStatus(): void
    {
        $router = new Router();

        require dirname(__DIR__, 2) . '/routes/api.php';

        $result = $this->render($router->dispatch(new Request('GET', '/health', [], [], [])));

        self::assertSame(200, $result['status']);
        self::assertJsonStringEqualsJsonString(
            '{"status":"success","message":"Cheer API running"}',
            $result['body']
        );
    }
}
