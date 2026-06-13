<?php

namespace Cheer\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'ErrorResponse',
    required: ['status', 'message'],
    properties: [
        new OA\Property(property: 'status', type: 'string', example: 'error'),
        new OA\Property(property: 'message', type: 'string'),
    ],
    type: 'object'
)]
#[OA\Schema(
    schema: 'ValidationError',
    allOf: [
        new OA\Schema(ref: '#/components/schemas/ErrorResponse'),
        new OA\Schema(
            properties: [
                new OA\Property(
                    property: 'fields',
                    type: 'array',
                    items: new OA\Items(type: 'string')
                ),
            ],
            type: 'object'
        ),
    ]
)]
#[OA\Schema(
    schema: 'EnderecoInput',
    required: ['rua', 'bairro', 'cidade', 'uf', 'codigo_postal'],
    properties: [
        new OA\Property(property: 'rua', type: 'string', example: 'Rua Teste'),
        new OA\Property(property: 'numero', type: 'string', nullable: true, example: '123'),
        new OA\Property(property: 'complemento', type: 'string', nullable: true, example: 'Apto 12'),
        new OA\Property(property: 'bairro', type: 'string', example: 'Centro'),
        new OA\Property(property: 'cidade', type: 'string', example: 'Sao Paulo'),
        new OA\Property(property: 'uf', type: 'string', maxLength: 2, minLength: 2, example: 'SP'),
        new OA\Property(property: 'codigo_postal', type: 'string', example: '01001000'),
        new OA\Property(property: 'lat', type: 'number', format: 'double', nullable: true),
        new OA\Property(property: 'lng', type: 'number', format: 'double', nullable: true),
    ],
    type: 'object'
)]
#[OA\Schema(
    schema: 'RegisterVoluntarioRequest',
    required: ['nome', 'email', 'password', 'cpf', 'endereco'],
    properties: [
        new OA\Property(property: 'nome', type: 'string', example: 'Ana Souza'),
        new OA\Property(property: 'email', type: 'string', format: 'email', example: 'ana@email.com'),
        new OA\Property(property: 'password', type: 'string', format: 'password', minLength: 8, writeOnly: true),
        new OA\Property(property: 'telefone', type: 'string', nullable: true, example: '11999999999'),
        new OA\Property(property: 'cpf', type: 'string', example: '12345678901'),
        new OA\Property(property: 'rg', type: 'string', nullable: true),
        new OA\Property(property: 'genero', type: 'string', nullable: true),
        new OA\Property(property: 'data_nascimento', type: 'string', format: 'date', nullable: true),
        new OA\Property(property: 'endereco', ref: '#/components/schemas/EnderecoInput'),
    ],
    type: 'object'
)]
#[OA\Schema(
    schema: 'RegisterInstituicaoRequest',
    required: ['nome', 'email', 'password', 'cnpj', 'endereco'],
    properties: [
        new OA\Property(property: 'nome', type: 'string', example: 'Instituto Esperanca'),
        new OA\Property(property: 'email', type: 'string', format: 'email', example: 'contato@instituto.org'),
        new OA\Property(property: 'password', type: 'string', format: 'password', minLength: 8, writeOnly: true),
        new OA\Property(property: 'telefone', type: 'string', nullable: true),
        new OA\Property(property: 'cnpj', type: 'string', example: '12345678000199'),
        new OA\Property(property: 'tipo', type: 'string', nullable: true, example: 'ONG'),
        new OA\Property(property: 'ano_fundacao', type: 'integer', nullable: true),
        new OA\Property(property: 'categoria', type: 'string', nullable: true, example: 'Educacao'),
        new OA\Property(property: 'internacional', type: 'boolean', nullable: true),
        new OA\Property(property: 'endereco', ref: '#/components/schemas/EnderecoInput'),
    ],
    type: 'object'
)]
#[OA\Schema(
    schema: 'RegisterResponse',
    required: ['status', 'data'],
    properties: [
        new OA\Property(property: 'status', type: 'string', example: 'success'),
        new OA\Property(
            property: 'data',
            required: ['id', 'tipo', 'authentik_user'],
            properties: [
                new OA\Property(property: 'id', type: 'integer', example: 1),
                new OA\Property(property: 'tipo', type: 'string', enum: ['voluntario', 'instituicao']),
                new OA\Property(property: 'authentik_user', type: 'string'),
            ],
            type: 'object'
        ),
    ],
    type: 'object'
)]
#[OA\Schema(
    schema: 'AuthConfigResponse',
    required: ['status', 'data'],
    properties: [
        new OA\Property(property: 'status', type: 'string', example: 'success'),
        new OA\Property(
            property: 'data',
            required: ['mode', 'authenticated', 'login_url', 'logout_url'],
            properties: [
                new OA\Property(property: 'mode', type: 'string', example: 'session-bff'),
                new OA\Property(property: 'authenticated', type: 'boolean', example: false),
                new OA\Property(property: 'login_url', type: 'string', example: 'http://localhost:8000/api/auth/login'),
                new OA\Property(property: 'logout_url', type: 'string', example: 'http://localhost:8000/api/auth/logout'),
                new OA\Property(property: 'mobile_login_url', type: 'string', example: 'http://localhost:8000/api/auth/mobile/login'),
                new OA\Property(property: 'mobile_logout_url', type: 'string', example: 'http://localhost:8000/api/auth/mobile/logout'),
            ],
            type: 'object'
        ),
    ],
    type: 'object'
)]
#[OA\Schema(
    schema: 'ProfileResponse',
    required: ['status', 'data'],
    properties: [
        new OA\Property(property: 'status', type: 'string', example: 'success'),
        new OA\Property(
            property: 'data',
            oneOf: [
                new OA\Schema(ref: '#/components/schemas/VoluntarioProfile'),
                new OA\Schema(ref: '#/components/schemas/InstituicaoProfile'),
            ]
        ),
    ],
    type: 'object'
)]
#[OA\Schema(
    schema: 'VoluntarioProfile',
    required: ['tipo', 'id', 'nome', 'email', 'cidade', 'uf'],
    properties: [
        new OA\Property(property: 'tipo', type: 'string', enum: ['voluntario']),
        new OA\Property(property: 'id', type: 'integer'),
        new OA\Property(property: 'nome', type: 'string'),
        new OA\Property(property: 'email', type: 'string', format: 'email'),
        new OA\Property(property: 'telefone', type: 'string', nullable: true),
        new OA\Property(property: 'cidade', type: 'string'),
        new OA\Property(property: 'uf', type: 'string'),
    ],
    type: 'object'
)]
#[OA\Schema(
    schema: 'InstituicaoProfile',
    required: ['tipo', 'id', 'nome', 'email', 'cidade', 'uf'],
    properties: [
        new OA\Property(property: 'tipo', type: 'string', enum: ['instituicao']),
        new OA\Property(property: 'id', type: 'integer'),
        new OA\Property(property: 'nome', type: 'string'),
        new OA\Property(property: 'email', type: 'string', format: 'email'),
        new OA\Property(property: 'telefone', type: 'string', nullable: true),
        new OA\Property(property: 'categoria', type: 'string', nullable: true),
        new OA\Property(property: 'cidade', type: 'string'),
        new OA\Property(property: 'uf', type: 'string'),
        new OA\Property(property: 'endereco', ref: '#/components/schemas/EnderecoInput', nullable: true),
    ],
    type: 'object'
)]
#[OA\Schema(
    schema: 'CreateEventoRequest',
    required: ['titulo', 'tipo_evento', 'data_hora_inicio', 'endereco'],
    properties: [
        new OA\Property(property: 'titulo', type: 'string', example: 'Acao solidaria no centro'),
        new OA\Property(property: 'constancia', type: 'string', nullable: true),
        new OA\Property(property: 'data_hora_inicio', type: 'string', format: 'date-time', example: '2026-06-01T09:00:00-03:00'),
        new OA\Property(property: 'data_hora_termino', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'tipo_evento', type: 'string', example: 'voluntariado'),
        new OA\Property(property: 'num_max_voluntarios', type: 'integer', nullable: true, example: 20),
        new OA\Property(property: 'descricao', type: 'string', nullable: true),
        new OA\Property(property: 'endereco', ref: '#/components/schemas/EnderecoInput'),
    ],
    type: 'object'
)]
#[OA\Schema(
    schema: 'Evento',
    properties: [
        new OA\Property(property: 'id', type: 'integer'),
        new OA\Property(property: 'titulo', type: 'string'),
        new OA\Property(property: 'instituicao', type: 'string'),
        new OA\Property(property: 'rua', type: 'string', nullable: true),
        new OA\Property(property: 'numero', type: 'string', nullable: true),
        new OA\Property(property: 'complemento', type: 'string', nullable: true),
        new OA\Property(property: 'bairro', type: 'string', nullable: true),
        new OA\Property(property: 'cidade', type: 'string'),
        new OA\Property(property: 'uf', type: 'string'),
        new OA\Property(property: 'codigo_postal', type: 'string', nullable: true),
        new OA\Property(property: 'lat', type: 'number', format: 'double', nullable: true),
        new OA\Property(property: 'lng', type: 'number', format: 'double', nullable: true),
        new OA\Property(property: 'data', type: 'string'),
        new OA\Property(property: 'data_hora_termino', type: 'string', nullable: true),
        new OA\Property(property: 'tipo_evento', type: 'string'),
        new OA\Property(property: 'vagas', type: 'integer', nullable: true),
        new OA\Property(property: 'descricao', type: 'string', nullable: true),
        new OA\Property(property: 'inscritos', type: 'integer'),
    ],
    type: 'object'
)]
#[OA\Schema(
    schema: 'EventoDetailResponse',
    required: ['status', 'data'],
    properties: [
        new OA\Property(property: 'status', type: 'string', example: 'success'),
        new OA\Property(
            property: 'data',
            allOf: [new OA\Schema(ref: '#/components/schemas/Evento')],
            properties: [
                new OA\Property(property: 'constancia', type: 'string', nullable: true),
                new OA\Property(property: 'id_endereco', type: 'integer'),
                new OA\Property(property: 'endereco', ref: '#/components/schemas/EnderecoInput'),
            ],
            type: 'object'
        ),
    ],
    type: 'object'
)]
#[OA\Schema(
    schema: 'EventosResponse',
    required: ['status', 'data'],
    properties: [
        new OA\Property(property: 'status', type: 'string', example: 'success'),
        new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/Evento')),
    ],
    type: 'object'
)]
#[OA\Schema(
    schema: 'InscritoEvento',
    properties: [
        new OA\Property(property: 'id_evento', type: 'integer'),
        new OA\Property(property: 'evento', type: 'string'),
        new OA\Property(property: 'id_voluntario', type: 'integer'),
        new OA\Property(property: 'nome', type: 'string'),
        new OA\Property(property: 'email', type: 'string', format: 'email'),
        new OA\Property(property: 'telefone', type: 'string', nullable: true),
        new OA\Property(property: 'status', type: 'string', enum: ['pendente', 'aprovado', 'rejeitado']),
        new OA\Property(property: 'data_inscricao', type: 'string'),
    ],
    type: 'object'
)]
#[OA\Schema(
    schema: 'InscritosEventoResponse',
    required: ['status', 'data'],
    properties: [
        new OA\Property(property: 'status', type: 'string', example: 'success'),
        new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/InscritoEvento')),
    ],
    type: 'object'
)]
#[OA\Schema(
    schema: 'CreateInscricaoRequest',
    required: ['id_evento'],
    properties: [
        new OA\Property(property: 'id_evento', type: 'integer', example: 10),
    ],
    type: 'object'
)]
#[OA\Schema(
    schema: 'InscricaoCreatedResponse',
    required: ['status', 'data'],
    properties: [
        new OA\Property(property: 'status', type: 'string', example: 'success'),
        new OA\Property(
            property: 'data',
            required: ['status'],
            properties: [
                new OA\Property(property: 'status', type: 'string', example: 'pendente'),
            ],
            type: 'object'
        ),
    ],
    type: 'object'
)]
#[OA\Schema(
    schema: 'Inscricao',
    properties: [
        new OA\Property(property: 'id', type: 'integer'),
        new OA\Property(property: 'titulo', type: 'string'),
        new OA\Property(property: 'instituicao', type: 'string'),
        new OA\Property(property: 'cidade', type: 'string'),
        new OA\Property(property: 'uf', type: 'string'),
        new OA\Property(property: 'data', type: 'string'),
        new OA\Property(property: 'status', type: 'string'),
        new OA\Property(property: 'data_inscricao', type: 'string'),
    ],
    type: 'object'
)]
#[OA\Schema(
    schema: 'InscricoesResponse',
    required: ['status', 'data'],
    properties: [
        new OA\Property(property: 'status', type: 'string', example: 'success'),
        new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/Inscricao')),
    ],
    type: 'object'
)]
#[OA\Schema(
    schema: 'DashboardInstituicaoResponse',
    required: ['status', 'data'],
    properties: [
        new OA\Property(property: 'status', type: 'string', example: 'success'),
        new OA\Property(
            property: 'data',
            properties: [
                new OA\Property(property: 'kpis', type: 'object'),
                new OA\Property(property: 'series', type: 'object'),
                new OA\Property(property: 'tables', type: 'object'),
            ],
            type: 'object'
        ),
    ],
    type: 'object'
)]
#[OA\Schema(
    schema: 'LogEvento',
    properties: [
        new OA\Property(property: 'id', type: 'integer'),
        new OA\Property(property: 'tipo_evento', type: 'string'),
        new OA\Property(property: 'descricao', type: 'string'),
        new OA\Property(property: 'nivel', type: 'string'),
        new OA\Property(property: 'origem', type: 'string', nullable: true),
        new OA\Property(property: 'id_usuario', type: 'integer', nullable: true),
        new OA\Property(property: 'tipo_usuario', type: 'string', nullable: true),
        new OA\Property(property: 'ip_origem', type: 'string', nullable: true),
        new OA\Property(property: 'user_agent', type: 'string', nullable: true),
        new OA\Property(property: 'data_hora', type: 'string'),
    ],
    type: 'object'
)]
#[OA\Schema(
    schema: 'LogsResponse',
    required: ['status', 'data'],
    properties: [
        new OA\Property(property: 'status', type: 'string', example: 'success'),
        new OA\Property(
            property: 'data',
            properties: [
                new OA\Property(property: 'items', type: 'array', items: new OA\Items(ref: '#/components/schemas/LogEvento')),
                new OA\Property(
                    property: 'pagination',
                    properties: [
                        new OA\Property(property: 'page', type: 'integer'),
                        new OA\Property(property: 'per_page', type: 'integer'),
                        new OA\Property(property: 'total', type: 'integer'),
                    ],
                    type: 'object'
                ),
            ],
            type: 'object'
        ),
    ],
    type: 'object'
)]
#[OA\Schema(
    schema: 'IdResponse',
    required: ['status', 'data'],
    properties: [
        new OA\Property(property: 'status', type: 'string', example: 'success'),
        new OA\Property(
            property: 'data',
            required: ['id'],
            properties: [
                new OA\Property(property: 'id', type: 'integer'),
            ],
            type: 'object'
        ),
    ],
    type: 'object'
)]
#[OA\Schema(
    schema: 'DashboardSeriesPoint',
    properties: [
        new OA\Property(property: 'label', type: 'string'),
        new OA\Property(property: 'value', type: 'integer'),
    ],
    type: 'object'
)]
#[OA\Schema(
    schema: 'DashboardEventoRow',
    properties: [
        new OA\Property(property: 'id', type: 'integer'),
        new OA\Property(property: 'titulo', type: 'string'),
        new OA\Property(property: 'data', type: 'string'),
        new OA\Property(property: 'data_hora_termino', type: 'string', nullable: true),
        new OA\Property(property: 'tipo_evento', type: 'string'),
        new OA\Property(property: 'cidade', type: 'string'),
        new OA\Property(property: 'uf', type: 'string'),
        new OA\Property(property: 'vagas', type: 'integer', nullable: true),
        new OA\Property(property: 'inscritos', type: 'integer'),
    ],
    type: 'object'
)]
#[OA\Schema(
    schema: 'DashboardInscritoRow',
    properties: [
        new OA\Property(property: 'id_evento', type: 'integer'),
        new OA\Property(property: 'evento', type: 'string'),
        new OA\Property(property: 'id_voluntario', type: 'integer'),
        new OA\Property(property: 'nome', type: 'string'),
        new OA\Property(property: 'email', type: 'string'),
        new OA\Property(property: 'telefone', type: 'string', nullable: true),
        new OA\Property(property: 'status', type: 'string'),
        new OA\Property(property: 'data_inscricao', type: 'string'),
    ],
    type: 'object'
)]
#[OA\Schema(
    schema: 'InstitutionDashboardResponse',
    required: ['status', 'data'],
    properties: [
        new OA\Property(property: 'status', type: 'string', example: 'success'),
        new OA\Property(
            property: 'data',
            properties: [
                new OA\Property(
                    property: 'kpis',
                    properties: [
                        new OA\Property(property: 'total_eventos', type: 'integer'),
                        new OA\Property(property: 'eventos_futuros', type: 'integer'),
                        new OA\Property(property: 'total_inscritos', type: 'integer'),
                        new OA\Property(property: 'inscricoes_pendentes', type: 'integer'),
                        new OA\Property(property: 'inscricoes_aprovadas', type: 'integer'),
                        new OA\Property(property: 'inscricoes_rejeitadas', type: 'integer'),
                        new OA\Property(property: 'taxa_ocupacao_percentual', type: 'number', format: 'float'),
                    ],
                    type: 'object'
                ),
                new OA\Property(
                    property: 'series',
                    properties: [
                        new OA\Property(property: 'eventos_por_mes', type: 'array', items: new OA\Items(ref: '#/components/schemas/DashboardSeriesPoint')),
                        new OA\Property(property: 'eventos_por_tipo', type: 'array', items: new OA\Items(ref: '#/components/schemas/DashboardSeriesPoint')),
                        new OA\Property(property: 'inscricoes_por_status', type: 'array', items: new OA\Items(ref: '#/components/schemas/DashboardSeriesPoint')),
                        new OA\Property(property: 'inscritos_por_evento', type: 'array', items: new OA\Items(ref: '#/components/schemas/DashboardSeriesPoint')),
                    ],
                    type: 'object'
                ),
                new OA\Property(
                    property: 'tables',
                    properties: [
                        new OA\Property(property: 'eventos', type: 'array', items: new OA\Items(ref: '#/components/schemas/DashboardEventoRow')),
                        new OA\Property(property: 'inscritos_recentes', type: 'array', items: new OA\Items(ref: '#/components/schemas/DashboardInscritoRow')),
                    ],
                    type: 'object'
                ),
            ],
            type: 'object'
        ),
    ],
    type: 'object'
)]
final class Schemas
{
}
