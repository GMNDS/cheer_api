<?php

namespace Tests\Unit\Core;

use Cheer\Core\Request;
use Cheer\Core\Response;
use Cheer\Core\Router;
use Tests\TestCase;

final class RouterTest extends TestCase
{
    public function testDispatchesARegisteredRouteAndWrapsHandlerData(): void
    {
        $router = new Router();
        $router->get('/eventos', static fn (Request $request): array => [
            'path' => $request->path(),
        ]);

        $result = $this->render($router->dispatch(new Request('GET', '/eventos', [], [], [])));

        self::assertSame(200, $result['status']);
        self::assertJsonStringEqualsJsonString(
            '{"data":{"path":"/eventos"}}',
            $result['body']
        );
    }

    public function testReturnsNotFoundForAnUnknownRoute(): void
    {
        $router = new Router();

        $result = $this->render($router->dispatch(new Request('GET', '/ausente', [], [], [])));

        self::assertSame(404, $result['status']);
        self::assertJsonStringEqualsJsonString(
            '{"status":"error","message":"Route not found."}',
            $result['body']
        );
    }

    public function testPreservesAResponseReturnedByAHandler(): void
    {
        $router = new Router();
        $router->post('/eventos', static fn (): Response => Response::json(['created' => true], 201));

        $result = $this->render($router->dispatch(new Request('POST', '/eventos', [], [], [])));

        self::assertSame(201, $result['status']);
        self::assertJsonStringEqualsJsonString('{"created":true}', $result['body']);
    }
}
