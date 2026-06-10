# Cheer API

## Docker

Crie o arquivo de ambiente a partir do exemplo e ajuste `HOST`, `DB_PASSWORD`,
`DB_ROOT_PASSWORD`, os dados do Authentik e `CORS_ALLOWED_ORIGIN` com a URL do
frontend. Para permitir mais de uma origem, separe as URLs por virgula:

```env
CORS_ALLOWED_ORIGIN="http://localhost:5173,https://cheer.example.com"
```

```bash
cp .env.example .env
```

O servico `api` espera que o Traefik ja esteja conectado na rede definida em
`TRAEFIK_NETWORK`. Caso use a rede padrao do exemplo:

```bash
docker network create proxy
docker compose up -d --build
```

O Compose sobe a API PHP/Apache e um MySQL. O banco aplica os arquivos em
`database/migrations` apenas ao criar o volume `database_data` pela primeira vez.
Para um volume ja existente, aplique migrations novas manualmente:

```bash
docker compose exec db sh -c 'mysql -u root -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE" < /docker-entrypoint-initdb.d/002_add_numero_to_enderecos.sql'
docker compose exec db sh -c 'mysql -u root -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE" < /docker-entrypoint-initdb.d/003_create_mobile_auth_codes.sql'
```

## Authentik mobile

O app OAuth confidencial do backend pode ser usado tambem para mobile como BFF.
Adicione no provider do Authentik os dois redirect URIs da API:

```text
https://api.example.com/api/auth/callback
https://api.example.com/api/auth/mobile/callback
```

Configure o deep link permitido do aplicativo no backend:

```env
AUTHENTIK_MOBILE_CALLBACK_URI=https://api.example.com/api/auth/mobile/callback
AUTHENTIK_MOBILE_APP_REDIRECT_URIS="cheer://auth/callback"
```

Fluxo do aplicativo:

1. Abrir `GET /api/auth/mobile/login?redirect_uri=cheer://auth/callback&state=...` no navegador do sistema.
2. Receber o deep link `cheer://auth/callback?code=...&state=...`.
3. Chamar `POST /api/auth/mobile/exchange` com `{ "code": "..." }` e guardar o cookie de sessao retornado pela API.

## Testes

```bash
composer test
```
