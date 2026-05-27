<?php

namespace Tests;

use Cheer\Core\Response;
use PHPUnit\Framework\TestCase as PHPUnitTestCase;

abstract class TestCase extends PHPUnitTestCase
{
    /** @return array{status: int, body: string} */
    protected function render(Response $response): array
    {
        ob_start();
        $response->send();
        $body = (string) ob_get_clean();

        return [
            'status' => (int) http_response_code(),
            'body' => $body,
        ];
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        http_response_code(200);

        parent::tearDown();
    }
}
