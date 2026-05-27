<?php

namespace Tests\Support;

use Cheer\Services\TransactionManagerInterface;

final class FakeTransactionManager implements TransactionManagerInterface
{
    public function begin(): void
    {
    }

    public function commit(): void
    {
    }

    public function rollback(): void
    {
    }
}