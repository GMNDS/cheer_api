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
        $method  = strtoupper($request->method());
        $reqPath = '/' . trim($request->path(), '/');

        foreach ($this->routes as $route) {
            if (!str_starts_with($route['pattern'], $method . ' ')) {
                continue;
            }

            $pattern = substr($route['pattern'], strlen($method) + 1);
            $regex   = $this->toRegex($pattern);

            if (!preg_match($regex, $reqPath, $matches)) {
                continue;
            }

            // Build named path params and inject into request
            $pathParams = [];
            foreach ($route['params'] as $name) {
                $pathParams[$name] = $matches[$name] ?? null;
            }

            $requestWithParams = $request->withPathParams($pathParams);

            try {
                $response = ($route['handler'])($requestWithParams, ...array_values(
                    array_map(
                        static fn ($v) => is_numeric($v) ? (int) $v : $v,
                        $pathParams
                    )
                ));

                return $response instanceof Response
                    ? $response
                    : Response::json(['data' => $response]);
            } catch (Throwable $exception) {
                $debug = Config::get('app.debug', false);

                return Response::json([
                    'status'  => 'error',
                    'message' => $debug ? $exception->getMessage() : 'Internal server error.',
                ], 500);
            }
        }

        return Response::json([
            'status'  => 'error',
            'message' => 'Route not found.',
        ], 404);
    }

    private function add(string $method, string $path, callable $handler): void
    {
        $normalizedPath = '/' . trim($path, '/');
        $params         = [];

        preg_match_all('/\{(\w+)\}/', $normalizedPath, $paramMatches);
        $params = $paramMatches[1];

        $this->routes[] = [
            'pattern' => strtoupper($method) . ' ' . $normalizedPath,
            'handler' => $handler,
            'params'  => $params,
        ];
    }

    private function toRegex(string $path): string
    {
        $escaped = preg_quote($path, '#');

        $regex = preg_replace_callback(
            '/\\\{(\w+)\\\}/',
            static fn (array $matches): string => '(?P<' . $matches[1] . '>[^/]+)',
            $escaped
        );

        return '#^' . $regex . '$#';
    }
}
