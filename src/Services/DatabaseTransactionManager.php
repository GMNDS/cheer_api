<?php

namespace Cheer\Services;

use Cheer\Core\Database;

final class DatabaseTransactionManager implements TransactionManagerInterface
{
    public function begin(): void
    {
        Database::connection()->beginTransaction();
    }

    public function commit(): void
    {
        Database::connection()->commit();
    }

    public function rollback(): void
    {
        $connection = Database::connection();

        if ($connection->inTransaction()) {
            $connection->rollBack();
        }
    }
}