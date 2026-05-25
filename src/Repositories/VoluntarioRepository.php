<?php

namespace Cheer\Repositories;

use Cheer\Core\Database;

final class VoluntarioRepository
{
    /** @param array<string, mixed> $data */
    public function create(string $authentikUser, int $enderecoId, array $data): int
    {
        $statement = Database::connection()->prepare(
            'INSERT INTO voluntario (
                authentik_user,
                nome,
                email,
                telefone,
                id_endereco,
                cpf,
                rg,
                genero,
                data_nascimento
             ) VALUES (
                :authentik_user,
                :nome,
                :email,
                :telefone,
                :id_endereco,
                :cpf,
                :rg,
                :genero,
                :data_nascimento
             )'
        );
        $statement->execute([
            'authentik_user' => $authentikUser,
            'nome' => $data['nome'],
            'email' => $data['email'],
            'telefone' => $data['telefone'] ?? null,
            'id_endereco' => $enderecoId,
            'cpf' => $this->digits($data['cpf'] ?? ''),
            'rg' => $data['rg'] ?? null,
            'genero' => $data['genero'] ?? null,
            'data_nascimento' => $data['data_nascimento'] ?? null,
        ]);

        return (int) Database::connection()->lastInsertId();
    }

    private function digits(mixed $value): string
    {
        return preg_replace('/\D+/', '', (string) $value);
    }
}
