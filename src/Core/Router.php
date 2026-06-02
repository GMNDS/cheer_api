<?php

namespace Cheer\Core;

use Throwable;

final class Router
{
    /** @var array<string, callable> */
    private array $routes = [];

    /** @var list<array{method: string, path: string, pattern: string, handler: callable}> */
    private array $parameterizedRoutes = [];

    public function get(string $path, callable $handler): void
    {
        $this->add('GET', $path, $handler);
    }

    public function post(string $path, callable $handler): void
    {
        $this->add('POST', $path, $handler);
    }

    public function put(string $path, callable $handler): void
    {
        $this->add('PUT', $path, $handler);
    }

    public function patch(string $path, callable $handler): void
    {
        $this->add('PATCH', $path, $handler);
    }

    public function delete(string $path, callable $handler): void
    {
        $this->add('DELETE', $path, $handler);
    }

    public function dispatch(Request $request): Response
    {
        $key = $this->key($request->method(), $request->path());

        if (!isset($this->routes[$key])) {
            $matchedRoute = $this->match($request);

            if ($matchedRoute === null) {
                return Response::json([
                    'status' => 'error',
                    'message' => 'Route not found.',
                ], 404);
            }

            try {
                $response = ($matchedRoute['handler'])($request, ...$matchedRoute['params']);

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
        $normalizedPath = '/' . trim($path, '/');

        if (str_contains($normalizedPath, '{')) {
            $pattern = preg_replace('/\{[a-zA-Z_][a-zA-Z0-9_]*\}/', '([^/]+)', $normalizedPath);
            $this->parameterizedRoutes[] = [
                'method' => strtoupper($method),
                'path' => $normalizedPath,
                'pattern' => '#^' . $pattern . '$#',
                'handler' => $handler,
            ];

            return;
        }

        $this->routes[$this->key($method, $normalizedPath)] = $handler;
    }

    private function key(string $method, string $path): string
    {
        return strtoupper($method) . ' ' . ('/' . trim($path, '/'));
    }

    /** @return array{handler: callable, params: list<string>}|null */
    private function match(Request $request): ?array
    {
        foreach ($this->parameterizedRoutes as $route) {
            if ($route['method'] !== strtoupper($request->method())) {
                continue;
            }

            $matches = [];

            if (preg_match($route['pattern'], $request->path(), $matches) !== 1) {
                continue;
            }

            array_shift($matches);

            return [
                'handler' => $route['handler'],
                'params' => array_map('urldecode', $matches),
            ];
        }

        return null;
    }
}
