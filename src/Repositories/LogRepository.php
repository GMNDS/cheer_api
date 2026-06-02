<?php

namespace Cheer\Repositories;

use Cheer\Core\Database;
use Cheer\Core\Request;
use PDO;

final class LogRepository
{
    public function create(
        string $tipoEvento,
        string $descricao,
        string $nivel,
        Request $request,
        ?int $idUsuario = null,
        ?string $tipoUsuario = null,
        string $origem = 'api'
    ): void {
        $statement = Database::connection()->prepare(
            'INSERT INTO logs_eventos (
                tipo_evento,
                descricao,
                nivel,
                origem,
                id_usuario,
                tipo_usuario,
                ip_origem,
                user_agent,
                data_hora
             ) VALUES (
                :tipo_evento,
                :descricao,
                :nivel,
                :origem,
                :id_usuario,
                :tipo_usuario,
                :ip_origem,
                :user_agent,
                CURRENT_TIMESTAMP
             )'
        );
        $statement->execute([
            'tipo_evento' => $tipoEvento,
            'descricao' => $descricao,
            'nivel' => $nivel,
            'origem' => $origem,
            'id_usuario' => $idUsuario,
            'tipo_usuario' => $tipoUsuario,
            'ip_origem' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 255),
        ]);
    }

    /** @param array<string, mixed> $filters */
    public function listByUsuario(int $idUsuario, string $tipoUsuario, array $filters = []): array
    {
        $page = max(1, (int) ($filters['page'] ?? 1));
        $perPage = min(100, max(1, (int) ($filters['per_page'] ?? 20)));
        $offset = ($page - 1) * $perPage;
        $where = ['id_usuario = :id_usuario', 'tipo_usuario = :tipo_usuario'];
        $params = [
            'id_usuario' => $idUsuario,
            'tipo_usuario' => $tipoUsuario,
        ];

        foreach (['nivel', 'tipo_evento', 'origem'] as $field) {
            if (!empty($filters[$field])) {
                $where[] = "{$field} = :{$field}";
                $params[$field] = (string) $filters[$field];
            }
        }

        if (!empty($filters['data_inicio'])) {
            $where[] = 'data_hora >= :data_inicio';
            $params['data_inicio'] = $this->normalizeDateFilter((string) $filters['data_inicio']);
        }

        if (!empty($filters['data_fim'])) {
            $where[] = 'data_hora <= :data_fim';
            $params['data_fim'] = $this->normalizeDateFilter((string) $filters['data_fim']);
        }

        $whereSql = implode(' AND ', $where);
        $connection = Database::connection();
        $countStatement = $connection->prepare("SELECT COUNT(*) FROM logs_eventos WHERE {$whereSql}");
        $countStatement->execute($params);
        $total = (int) $countStatement->fetchColumn();

        $statement = $connection->prepare(
            "SELECT id, tipo_evento, descricao, nivel, origem, id_usuario, tipo_usuario, ip_origem, user_agent, data_hora
             FROM logs_eventos
             WHERE {$whereSql}
             ORDER BY data_hora DESC, id DESC
             LIMIT :limit OFFSET :offset"
        );

        foreach ($params as $key => $value) {
            $statement->bindValue(":{$key}", $value);
        }

        $statement->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $statement->bindValue(':offset', $offset, PDO::PARAM_INT);
        $statement->execute();

        return [
            'items' => $statement->fetchAll(PDO::FETCH_ASSOC),
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
            ],
        ];
    }

    private function normalizeDateFilter(string $value): string
    {
        return str_replace('T', ' ', $value);
    }
}
