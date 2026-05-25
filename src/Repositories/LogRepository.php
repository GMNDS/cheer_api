<?php

namespace Cheer\Repositories;

use Cheer\Core\Database;
use Cheer\Core\Request;

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
}
