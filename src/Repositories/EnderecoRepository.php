<?php

namespace Cheer\Repositories;

use Cheer\Core\Database;

final class EnderecoRepository
{
    /** @param array<string, mixed> $data */
    public function create(array $data): int
    {
        $statement = Database::connection()->prepare(
            'INSERT INTO enderecos (rua, bairro, cidade, uf, codigo_postal, lat, lng)
             VALUES (:rua, :bairro, :cidade, :uf, :codigo_postal, :lat, :lng)'
        );
        $statement->execute([
            'rua' => $data['rua'] ?? '',
            'bairro' => $data['bairro'] ?? '',
            'cidade' => $data['cidade'] ?? '',
            'uf' => strtoupper((string) ($data['uf'] ?? '')),
            'codigo_postal' => preg_replace('/\D+/', '', (string) ($data['codigo_postal'] ?? $data['cep'] ?? '')),
            'lat' => $data['lat'] ?? null,
            'lng' => $data['lng'] ?? null,
        ]);

        return (int) Database::connection()->lastInsertId();
    }
}
