# Configuracao do Authentik

Este MVP usa o Authentik para autenticacao e a API PHP como BFF do frontend Cheer.

O frontend inicia o login pelo backend:

```txt
GET /api/auth/login
```

O backend redireciona para o Authentik, recebe o callback em:

```txt
GET /api/auth/callback
```

Depois troca o `code` por tokens usando `AUTHENTIK_CLIENT_SECRET`, valida o ID token via JWKS, cria uma sessao local por cookie HttpOnly e usa o `sub` do token para localizar o perfil local em:

- `voluntario.authentik_user`
- `instituicao.authentik_user`

## Application

- Name: Cheer
- Slug: cheer
- Provider: OAuth2/OpenID Provider

## Provider OAuth2/OIDC

Se o frontend for SPA, use:

- Client type: Confidential
- Authorization Code
- Redirect URI: `http://localhost:8000/api/auth/callback`

O backend tambem envia PKCE no login, mesmo sendo confidential client.

## Scopes

Use no inicio:

- `openid`
- `profile`
- `email`

Para distinguir perfil, o BFF primeiro consulta o banco local pelo `sub`. Voce tambem pode criar scopes ou groups:

- `voluntario`
- `instituicao`

Esses scopes/groups ajudam em autorizacao, mas o vinculo principal do MVP fica no banco local.

## Cadastro via API administrativa

As rotas publicas de cadastro criam o usuario no Authentik e depois salvam o perfil local:

```txt
POST /api/auth/register-voluntario
POST /api/auth/register-instituicao
```

Para isso, crie um token administrativo no Authentik com permissao para:

- criar usuarios
- alterar senha de usuario
- listar grupos
- adicionar usuario a grupo

Depois preencha:

```env
AUTHENTIK_API_TOKEN=token-administrativo
AUTHENTIK_VOLUNTARIO_GROUP_NAME=voluntario
AUTHENTIK_INSTITUICAO_GROUP_NAME=instituicao
```

Se preferir evitar busca por nome, configure os UUIDs dos grupos:

```env
AUTHENTIK_VOLUNTARIO_GROUP_UUID=
AUTHENTIK_INSTITUICAO_GROUP_UUID=
```

O campo salvo em `voluntario.authentik_user` e `instituicao.authentik_user` vem de `AUTHENTIK_LOCAL_USER_IDENTIFIER`. Use `uid` se o provider OIDC estiver com subject baseado no ID do usuario. Valores aceitos:

```txt
uid
pk
username
email
```

## Variaveis da API

```env
AUTHENTIK_BASE_URL=https://auth.astrum.app.br
AUTHENTIK_API_TOKEN=token-administrativo
AUTHENTIK_APPLICATION_SLUG=cheer
AUTHENTIK_CLIENT_ID=client-id-do-provider
AUTHENTIK_CLIENT_SECRET=client-secret-do-provider
AUTHENTIK_REDIRECT_URI=http://localhost:8000/api/auth/callback
AUTHENTIK_POST_LOGIN_REDIRECT_URI=http://localhost:5173
AUTHENTIK_POST_LOGOUT_REDIRECT_URI=http://localhost:5173
AUTHENTIK_SCOPES="openid profile email"
AUTHENTIK_LOCAL_USER_IDENTIFIER=uid
```

O backend precisa conseguir acessar:

```txt
https://auth.astrum.app.br/application/o/cheer/jwks/
```

## Documentacao da API

A documentacao OpenAPI e gerada com `zircote/swagger-php` a partir de PHP attributes nos controllers e schemas em `src/OpenApi`.

```txt
GET /openapi.json
GET /docs
```

`/docs` usa Scalar apontando para `/openapi.json`.
