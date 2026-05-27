<?php

namespace Cheer\Core;

use PDO;
use PDOException;
use RuntimeException;

final class Database
{
    private static ?PDO $connection = null;

    public static function reset(): void
    {
        self::$connection = null;
    }

    public static function connection(): PDO
    {
        if (self::$connection !== null) {
            return self::$connection;
        }

        $driver = Config::get('database.driver', 'mysql');

        if ($driver === 'sqlite') {
            $database = (string) Config::get('database.database', ':memory:');
            $dsn = str_starts_with($database, 'sqlite:') ? $database : "sqlite:{$database}";

            try {
                self::$connection = new PDO($dsn, null, null, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]);
            } catch (PDOException $exception) {
                $message = Config::get('app.debug', false)
                    ? 'Could not connect to the database: ' . $exception->getMessage()
                    : 'Could not connect to the database.';

                throw new RuntimeException($message, 0, $exception);
            }

            return self::$connection;
        }

        if ($driver !== 'mysql') {
            throw new RuntimeException("Unsupported database driver: {$driver}");
        }

        $host = Config::get('database.host');
        $port = Config::get('database.port');
        $database = Config::get('database.database');
        $charset = Config::get('database.charset', 'utf8mb4');
        $username = Config::get('database.username');
        $password = Config::get('database.password');
        $dsn = "mysql:host={$host};port={$port};dbname={$database};charset={$charset}";

        try {
            self::$connection = new PDO($dsn, $username, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $exception) {
            $message = Config::get('app.debug', false)
                ? 'Could not connect to the database: ' . $exception->getMessage()
                : 'Could not connect to the database.';

            throw new RuntimeException($message, 0, $exception);
        }

        return self::$connection;
    }
}
