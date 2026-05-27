<?php

namespace Tests\Support;

use Cheer\Core\Request;

final class FakeLogRepository
{
    public function __construct(private readonly FakeScenarioState $state)
    {
    }

    public function create(
        string $tipoEvento,
        string $descricao,
        string $nivel,
        Request $request,
        ?int $idUsuario = null,
        ?string $tipoUsuario = null,
        string $origem = 'api'
    ): void {
        $this->state->logs[] = [
            'tipo_evento' => $tipoEvento,
            'descricao' => $descricao,
            'nivel' => $nivel,
            'id_usuario' => $idUsuario,
            'tipo_usuario' => $tipoUsuario,
            'origem' => $origem,
        ];
    }
}