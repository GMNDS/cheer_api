<?php

namespace Cheer\Core;

final class Response
{
    /** @param array<string, string> $headers */
    public function __construct(
        private readonly mixed $body,
        private readonly int $status = 200,
        private readonly array $headers = []
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function json(array $data, int $status = 200): self
    {
        return new self($data, $status, ['Content-Type' => 'application/json; charset=utf-8']);
    }

    public static function redirect(string $url, int $status = 302): self
    {
        return new self('', $status, ['Location' => $url]);
    }

    public static function content(string $content, string $contentType, int $status = 200): self
    {
        return new self($content, $status, ['Content-Type' => $contentType]);
    }

    public function send(): void
    {
        http_response_code($this->status);

        foreach ($this->headers as $name => $value) {
            header("{$name}: {$value}");
        }

        if (is_array($this->body) && ($this->headers['Content-Type'] ?? '') === 'application/json; charset=utf-8') {
            echo json_encode($this->body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            return;
        }

        echo (string) $this->body;
    }
}
