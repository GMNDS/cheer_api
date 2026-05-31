<?php

namespace Cheer\Core;

final class Request
{
    /** @param array<string, string> $headers */
    public function __construct(
        private readonly string $method,
        private readonly string $path,
        private readonly array $headers,
        private readonly array $query,
        private readonly array $body,
        private readonly array $pathParams = [],
    ) {
    }

    public static function capture(): self
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';
        $path = str_replace('\\', '/', $path);

        $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
        if ($scriptName !== '') {
            if (str_starts_with($path, $scriptName)) {
                $path = substr($path, strlen($scriptName)) ?: '/';
            } else {
                $scriptDirectory = rtrim(dirname($scriptName), '/');

                if ($scriptDirectory !== '' && str_starts_with($path, $scriptDirectory)) {
                    $path = substr($path, strlen($scriptDirectory)) ?: '/';
                }
            }
        }

        $rawBody = file_get_contents('php://input') ?: '';
        $decodedBody = json_decode($rawBody, true);

        return new self(
            strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET'),
            '/' . trim($path, '/'),
            self::headers(),
            $_GET,
            is_array($decodedBody) ? $decodedBody : $_POST
        );
    }

    public function withPathParams(array $params): self
    {
        return new self(
            $this->method,
            $this->path,
            $this->headers,
            $this->query,
            $this->body,
            $params
        );
    }

    public function method(): string
    {
        return $this->method;
    }

    public function path(): string
    {
        return $this->path === '' ? '/' : $this->path;
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->pathParams[$key] ?? $this->body[$key] ?? $this->query[$key] ?? $default;
    }

    /** @return array<string, mixed> */
    public function all(): array
    {
        return array_merge($this->query, $this->body, $this->pathParams);
    }

    public function ip(): ?string
    {
        return $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? null;
    }

    public function userAgent(): ?string
    {
        return $_SERVER['HTTP_USER_AGENT'] ?? null;
    }

    public function bearerToken(): ?string
    {
        $header = $this->header('authorization');

        if ($header === null || !str_starts_with(strtolower($header), 'bearer ')) {
            return null;
        }

        return trim(substr($header, 7));
    }

    public function header(string $key): ?string
    {
        return $this->headers[strtolower($key)] ?? null;
    }

    /** @return array<string, string> */
    private static function headers(): array
    {
        $headers = function_exists('getallheaders') ? getallheaders() : [];
        $normalized = [];

        foreach ($headers as $key => $value) {
            $normalized[strtolower((string) $key)] = (string) $value;
        }

        return $normalized;
    }
}
