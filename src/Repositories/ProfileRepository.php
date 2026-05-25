<?php

namespace Cheer\Repositories;

use Cheer\Core\Database;
use PDO;

final class ProfileRepository
{
    /** @return array<string, mixed>|null */
    public function findByAuthentikUser(string $authentikUser): ?array
    {
        return $this->findVoluntarioByAuthentikUser($authentikUser)
            ?? $this->findInstituicaoByAuthentikUser($authentikUser);
    }

    /** @return array<string, mixed>|null */
    public function findVoluntarioByAuthentikUser(string $authentikUser): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT
                v.id,
                v.authentik_user,
                v.nome,
                v.email,
                v.telefone,
                v.cpf,
                v.rg,
                v.genero,
                v.data_nascimento,
                e.rua,
                e.bairro,
                e.cidade,
                e.uf,
                e.codigo_postal
             FROM voluntario v
             INNER JOIN enderecos e ON e.id = v.id_endereco
             WHERE v.authentik_user = :authentik_user
             LIMIT 1'
        );
        $statement->execute(['authentik_user' => $authentikUser]);
        $profile = $statement->fetch(PDO::FETCH_ASSOC);

        if (!is_array($profile)) {
            return null;
        }

        $profile['tipo'] = 'voluntario';

        return $profile;
    }

    /** @return array<string, mixed>|null */
    public function findInstituicaoByAuthentikUser(string $authentikUser): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT
                i.id,
                i.authentik_user,
                i.nome,
                i.email,
                i.telefone,
                i.cnpj,
                i.tipo AS tipo_instituicao,
                i.ano_fundacao,
                i.categoria,
                i.internacional,
                e.rua,
                e.bairro,
                e.cidade,
                e.uf,
                e.codigo_postal
             FROM instituicao i
             INNER JOIN enderecos e ON e.id = i.id_endereco
             WHERE i.authentik_user = :authentik_user
             LIMIT 1'
        );
        $statement->execute(['authentik_user' => $authentikUser]);
        $profile = $statement->fetch(PDO::FETCH_ASSOC);

        if (!is_array($profile)) {
            return null;
        }

        $profile['tipo'] = 'instituicao';

        return $profile;
    }
}
