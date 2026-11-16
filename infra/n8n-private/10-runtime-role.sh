#!/bin/sh
# Docker PostgreSQL initdb only: dedicated n8n database, never another product database.
set -eu
test "$POSTGRES_DB" = n8n
test "$POSTGRES_USER" = n8n_bootstrap_admin
psql -X -q --username "$POSTGRES_USER" --dbname "$POSTGRES_DB" -v ON_ERROR_STOP=1 <<'SQL'
SELECT format('CREATE ROLE n8n_runtime LOGIN PASSWORD %L NOSUPERUSER NOBYPASSRLS NOCREATEDB NOCREATEROLE NOINHERIT',
  btrim(pg_read_file('/run/secrets/n8n_pg_password'))) \gexec
GRANT CONNECT,TEMPORARY ON DATABASE n8n TO n8n_runtime;
REVOKE CREATE ON SCHEMA public FROM PUBLIC;
ALTER SCHEMA public OWNER TO n8n_runtime;
SQL
