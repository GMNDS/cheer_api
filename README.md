# Cheer API

## Docker

Crie o arquivo de ambiente a partir do exemplo e ajuste `HOST`, `DB_PASSWORD`,
`DB_ROOT_PASSWORD` e os dados do Authentik:

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
```

## Testes

```bash
composer test
```
