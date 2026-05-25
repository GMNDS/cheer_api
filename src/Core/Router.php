<?php

namespace Cheer\Core;

use Throwable;

final class Router
{
    /** @var array<string, callable> */
    private array $routes = [];

    public function get(string $path, callable $handler): void
    {
        $this->add('GET', $path, $handler);
    }

    public function post(string $path, callable $handler): void
    {
        $this->add('POST', $path, $handler);
    }

    public function dispatch(Request $request): Response
    {
        $key = $this->key($request->method(), $request->path());

        if (!isset($this->routes[$key])) {
            return Response::json([
                'status' => 'error',
                'message' => 'Route not found.',
            ], 404);
        }

        try {
            $response = ($this->routes[$key])($request);

            return $response instanceof Response
                ? $response
                : Response::json(['data' => $response]);
        } catch (Throwable $exception) {
            $debug = Config::get('app.debug', false);

            return Response::json([
                'status' => 'error',
                'message' => $debug ? $exception->getMessage() : 'Internal server error.',
            ], 500);
        }
    }

    private function add(string $method, string $path, callable $handler): void
    {
        $this->routes[$this->key($method, $path)] = $handler;
    }

    private function key(string $method, string $path): string
    {
        return strtoupper($method) . ' ' . ('/' . trim($path, '/'));
    }
}
