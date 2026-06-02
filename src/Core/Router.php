<?php

namespace Cheer\Core;

use Throwable;

final class Router
{
    /** @var array<int, array{method:string, pattern:string, params:array<int, string>, handler:callable}> */
    private array $routes = [];

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
        $method = strtoupper($request->method());
        $reqPath = '/' . trim($request->path(), '/');

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }

            $regex = $this->toRegex($route['pattern']);

            if (!preg_match($regex, $reqPath, $matches)) {
                continue;
            }

            $pathParams = [];

            foreach ($route['params'] as $name) {
                $pathParams[$name] = $matches[$name] ?? null;
            }

            $requestWithParams = $request->withPathParams($pathParams);

            try {
                $response = ($route['handler'])($requestWithParams, ...array_values(
                    array_map(
                        static fn ($value) => is_numeric($value) ? (int) $value : $value,
                        $pathParams
                    )
                ));

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

        return Response::json([
            'status' => 'error',
            'message' => 'Route not found.',
        ], 404);
    }

    private function add(string $method, string $path, callable $handler): void
    {
        $pattern = '/' . trim($path, '/');

        $this->routes[] = [
            'method' => strtoupper($method),
            'pattern' => $pattern,
            'params' => $this->extractParams($pattern),
            'handler' => $handler,
        ];
    }

    /** @return array<int, string> */
    private function extractParams(string $pattern): array
    {
        preg_match_all('/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/', $pattern, $matches);

        return $matches[1] ?? [];
    }

    private function toRegex(string $pattern): string
    {
        $regex = preg_replace_callback(
            '/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/',
            static fn (array $matches): string => '(?P<' . $matches[1] . '>[^/]+)',
            $pattern
        );

        if ($regex === null) {
            return '#^$#';
        }

        return '#^' . str_replace('#', '\\#', $regex) . '$#';
    }
}
