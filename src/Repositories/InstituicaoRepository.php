<?php

namespace Cheer\Repositories;

use Cheer\Core\Database;

final class InstituicaoRepository
{
    /** @param array<string, mixed> $data */
    public function create(string $authentikUser, int $enderecoId, array $data): int
    {
        $statement = Database::connection()->prepare(
            'INSERT INTO instituicao (
                authentik_user,
                nome,
                email,
                telefone,
                id_endereco,
                cnpj,
                tipo,
                ano_fundacao,
                categoria,
                internacional
             ) VALUES (
                :authentik_user,
                :nome,
                :email,
                :telefone,
                :id_endereco,
                :cnpj,
                :tipo,
                :ano_fundacao,
                :categoria,
                :internacional
             )'
        );
        $statement->execute([
            'authentik_user' => $authentikUser,
            'nome' => $data['nome'],
            'email' => $data['email'],
            'telefone' => $data['telefone'] ?? null,
            'id_endereco' => $enderecoId,
            'cnpj' => $this->digits($data['cnpj'] ?? ''),
            'tipo' => $data['tipo'] ?? null,
            'ano_fundacao' => $data['ano_fundacao'] ?? null,
            'categoria' => $data['categoria'] ?? null,
            'internacional' => isset($data['internacional']) ? (int) (bool) $data['internacional'] : null,
        ]);

        return (int) Database::connection()->lastInsertId();
    }

    private function digits(mixed $value): string
    {
        return preg_replace('/\D+/', '', (string) $value);
    }
}
