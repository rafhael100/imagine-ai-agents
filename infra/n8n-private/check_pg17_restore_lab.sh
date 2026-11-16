#!/usr/bin/env bash
# CENTRAL-AUTO-P10: restore n8n PG17 into a SECOND disposable database.
# Tests only synthetic DB, reusing the same runner-only n8n volume and key.
set -euo pipefail
test "${GITHUB_ACTIONS:-}" = true
test "${GITHUB_REPOSITORY:-}" = rafhael100/imagine-ai-agents
test "${COMPOSE_PROJECT_NAME:-}" = imagine-n8n-p4-ci
root="$(cd "$(dirname "$0")/../.." && pwd)"
flow="$root/infra/n8n-private/workflows/native-funnel-noai.workflow.json"
checker="$root/infra/n8n-private/check_native_noai_flow.py"
work="$(mktemp -d)"
trap 'rm -rf "$work"' EXIT
umask 077
dc=(docker compose --profile homologation -f "$root/infra/n8n-private/docker-compose.yml")
test -n "$("${dc[@]}" ps -q n8n-db)"
if [[ -n "$("${dc[@]}" ps -q --status running n8n)" ]]; then
  echo CENTRAL_AUTO_P10_REFUSE_RUNNING_N8N >&2
  exit 1
fi
version="$("${dc[@]}" exec -T n8n-db psql -X -qAt -U n8n_bootstrap_admin -d n8n -c 'SHOW server_version_num')"
[[ "$version" =~ ^17[0-9]{4}$ ]] || { echo CENTRAL_AUTO_P10_NOT_PG17 >&2; exit 1; }
count="$("${dc[@]}" exec -T n8n-db psql -X -qAt -U n8n_bootstrap_admin -d n8n -c 'SELECT count(*) FROM workflow_entity')"
test "$count" = 1 || { echo CENTRAL_AUTO_P10_UNEXPECTED_WORKFLOWS >&2; exit 1; }
# Dump exists only on the CI runner and is never uploaded or shared.
"${dc[@]}" exec -T n8n-db pg_dump -U n8n_bootstrap_admin -d n8n \
  --format custom --no-acl --no-owner > "$work/n8n.dump"
test -s "$work/n8n.dump"
"${dc[@]}" exec -T n8n-db createdb -U n8n_bootstrap_admin -O n8n_runtime n8n_restore_ci
"${dc[@]}" exec -T n8n-db pg_restore -U n8n_bootstrap_admin -d n8n_restore_ci \
  --exit-on-error --no-owner --no-acl --role=n8n_runtime < "$work/n8n.dump"
restored="$("${dc[@]}" exec -T n8n-db psql -X -qAt -U n8n_bootstrap_admin -d n8n_restore_ci -c 'SELECT count(*) FROM workflow_entity')"
test "$restored" = "$count"
hash_sql="SELECT md5(string_agg(name || '|' || nodes::text || '|' || connections::text || '|' || active::text, '|' ORDER BY name)) FROM workflow_entity"
original_hash="$("${dc[@]}" exec -T n8n-db psql -X -qAt -U n8n_bootstrap_admin -d n8n -c "$hash_sql")"
restored_hash="$("${dc[@]}" exec -T n8n-db psql -X -qAt -U n8n_bootstrap_admin -d n8n_restore_ci -c "$hash_sql")"
test -n "$original_hash" && test "$original_hash" = "$restored_hash"
role_sql="SELECT rolsuper OR rolbypassrls OR rolcreatedb OR rolcreaterole FROM pg_roles WHERE rolname='n8n_runtime'"
test "$("${dc[@]}" exec -T n8n-db psql -X -qAt -U n8n_bootstrap_admin -d n8n_restore_ci -c "$role_sql")" = f
mkdir "$work/output"
chmod 777 "$work/output"
"${dc[@]}" run -T --rm --no-deps -e DB_POSTGRESDB_DATABASE=n8n_restore_ci \
  -v "$work/output:/ci-p10-output" --entrypoint n8n n8n \
  export:workflow --all --output=/ci-p10-output/workflows.json > "$work/export.log"
python3 "$checker" export "$flow" "$work/output/workflows.json"
id="$(python3 - "$work/output/workflows.json" <<'PY'
import json,sys
d=json.load(open(sys.argv[1],encoding='utf-8'))
if isinstance(d,dict): d=[d]
matches=[x for x in d if x.get('name')=='Imagine — Funil nativo sem Code e sem IA (laboratorio)']
assert len(matches)==1 and matches[0].get('active') is False
print(matches[0]['id'])
PY
)"
test -n "$id"
"${dc[@]}" run -T --rm --no-deps -e DB_POSTGRESDB_DATABASE=n8n_restore_ci \
  --entrypoint n8n n8n execute --id="$id" --rawOutput > "$work/result.log"
python3 "$checker" result "$flow" "$work/result.log"
echo "CENTRAL_AUTO_P10_RESTORE_PASS pg17=1 restored_workflow=1 native_calculation=120_12_2 owner_privilege=0 NO_GO"
