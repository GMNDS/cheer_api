<?php

use Cheer\Core\Database;
use Dotenv\Dotenv;

$rootPath = dirname(__DIR__);

require_once $rootPath . '/vendor/autoload.php';

if (is_file($rootPath . '/.env')) {
    Dotenv::createImmutable($rootPath)->safeLoad();
}

$migrationsPath = __DIR__ . '/migrations';
$maxAttempts = 30;
$connection = null;

for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
    try {
        $connection = Database::connection();
        break;
    } catch (Throwable $exception) {
        if ($attempt === $maxAttempts) {
            fwrite(STDERR, "[migrate] Database connection failed: {$exception->getMessage()}\n");
            exit(1);
        }

        fwrite(STDOUT, "[migrate] Waiting for database ({$attempt}/{$maxAttempts})...\n");
        sleep(2);
    }
}

if (!$connection instanceof PDO) {
    fwrite(STDERR, "[migrate] Database connection unavailable.\n");
    exit(1);
}

$connection->exec(
    'CREATE TABLE IF NOT EXISTS schema_migrations (
        migration VARCHAR(255) PRIMARY KEY NOT NULL,
        applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    )'
);

$appliedStatement = $connection->query('SELECT migration FROM schema_migrations');
$applied = array_fill_keys($appliedStatement->fetchAll(PDO::FETCH_COLUMN), true);

$files = glob($migrationsPath . '/*.sql') ?: [];
sort($files, SORT_STRING);

foreach ($files as $file) {
    $migration = basename($file);

    if (isset($applied[$migration])) {
        continue;
    }

    if (migrationAlreadyPresent($connection, $migration)) {
        markMigrationApplied($connection, $migration);
        fwrite(STDOUT, "[migrate] Marked existing migration: {$migration}\n");
        continue;
    }

    fwrite(STDOUT, "[migrate] Applying migration: {$migration}\n");
    applySqlFile($connection, $file);
    markMigrationApplied($connection, $migration);
}

fwrite(STDOUT, "[migrate] Database migrations are up to date.\n");

function applySqlFile(PDO $connection, string $file): void
{
    $sql = file_get_contents($file);

    if ($sql === false) {
        throw new RuntimeException("Could not read migration {$file}.");
    }

    $statements = preg_split('/;\s*(?:\r?\n|$)/', $sql) ?: [];

    foreach ($statements as $statement) {
        $statement = trim($statement);

        if ($statement === '') {
            continue;
        }

        $connection->exec($statement);
    }
}

function markMigrationApplied(PDO $connection, string $migration): void
{
    $statement = $connection->prepare('INSERT INTO schema_migrations (migration) VALUES (:migration)');
    $statement->execute(['migration' => $migration]);
}

function migrationAlreadyPresent(PDO $connection, string $migration): bool
{
    return match ($migration) {
        '001_create_initial_schema.sql' => tableExists($connection, 'instituicao')
            && tableExists($connection, 'voluntario')
            && tableExists($connection, 'evento')
            && tableExists($connection, 'logs_eventos'),
        '002_add_numero_to_enderecos.sql' => columnExists($connection, 'enderecos', 'numero'),
        '003_change_ip_origem_type.sql' => columnIsVarcharAtLeast($connection, 'logs_eventos', 'ip_origem', 255),
        '003_create_mobile_auth_codes.sql' => tableExists($connection, 'mobile_auth_codes')
            && indexExists($connection, 'mobile_auth_codes', 'idx_mobile_auth_codes_expires_at'),
        default => false,
    };
}

function tableExists(PDO $connection, string $table): bool
{
    $statement = $connection->prepare(
        'SELECT COUNT(*)
         FROM information_schema.tables
         WHERE table_schema = DATABASE()
           AND table_name = :table'
    );
    $statement->execute(['table' => $table]);

    return (int) $statement->fetchColumn() > 0;
}

function columnExists(PDO $connection, string $table, string $column): bool
{
    $statement = $connection->prepare(
        'SELECT COUNT(*)
         FROM information_schema.columns
         WHERE table_schema = DATABASE()
           AND table_name = :table
           AND column_name = :column'
    );
    $statement->execute(['table' => $table, 'column' => $column]);

    return (int) $statement->fetchColumn() > 0;
}

function columnIsVarcharAtLeast(PDO $connection, string $table, string $column, int $length): bool
{
    $statement = $connection->prepare(
        'SELECT data_type, character_maximum_length
         FROM information_schema.columns
         WHERE table_schema = DATABASE()
           AND table_name = :table
           AND column_name = :column
         LIMIT 1'
    );
    $statement->execute(['table' => $table, 'column' => $column]);
    $row = $statement->fetch(PDO::FETCH_ASSOC);

    if (!is_array($row)) {
        return false;
    }

    return strtolower((string) $row['data_type']) === 'varchar'
        && (int) $row['character_maximum_length'] >= $length;
}

function indexExists(PDO $connection, string $table, string $index): bool
{
    $statement = $connection->prepare(
        'SELECT COUNT(*)
         FROM information_schema.statistics
         WHERE table_schema = DATABASE()
           AND table_name = :table
           AND index_name = :index_name'
    );
    $statement->execute(['table' => $table, 'index_name' => $index]);

    return (int) $statement->fetchColumn() > 0;
}
