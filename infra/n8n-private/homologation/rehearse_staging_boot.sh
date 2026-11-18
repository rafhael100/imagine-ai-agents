#!/usr/bin/env bash
# Offline containers with synthetic state, for a disposable CI/Cloud daemon.
set -euo pipefail
[[ $# == 1 && $1 == --disposable-docker ]] || exit 2
root=$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)
work=$(mktemp -d)
project="n8n-boot-fixture-$(basename "$work" | tr -cd '[:alnum:]' | tr '[:upper:]' '[:lower:]')"
compose_dir="$work/compose"
compose=(docker compose --project-name "$project" --project-directory "$compose_dir"
  --env-file "$work/compose.env" -f "$compose_dir/docker-compose.yml"
  -f "$compose_dir/docker-compose.staging.yml" --profile homologation)
cleanup() {
  if [[ -f $work/compose.env ]]; then
    "${compose[@]}" down --volumes >/dev/null 2>&1 || true
  fi
  rm -rf -- "$work"
}
trap cleanup EXIT
unset COMPOSE_FILE COMPOSE_ENV_FILES N8N_IMAGE N8N_PG_IMAGE \
  N8N_PG_ADMIN_PASSWORD_FILE N8N_PG_PASSWORD_FILE N8N_ENCRYPTION_KEY_FILE
chmod 700 "$work"
mkdir -m 700 "$compose_dir"
cp "$root"/docker-compose{,.staging}.yml "$compose_dir/"
# The database container must be able to read its mounted, non-secret SQL bootstrap
# even when the checkout was created with a restrictive host umask.
install -m 0444 "$root/10-runtime-role.sh" "$compose_dir/10-runtime-role.sh"
printf '%s' 'fixture-admin-only' > "$work/admin"
printf '%s' 'fixture-runtime-only' > "$work/runtime"
printf '%s' 'fixture-n8n-encryption-key-only' > "$work/key"
chmod 0444 "$work"/{admin,runtime,key}
cat > "$work/compose.env" <<EOF
N8N_IMAGE=ghcr.io/n8n-io/n8n@sha256:b4b90747b81fcda7e76550c0d3aa7f0f64917a1e9573cff40d6834eaf2e0f8ea
N8N_PG_IMAGE=docker.io/library/postgres@sha256:0d938e975177bef9f1f61f3f9dbcf2e0aa0a139568f66d3415ed8892eb97f6ac
N8N_PG_ADMIN_PASSWORD_FILE=$work/admin
N8N_PG_PASSWORD_FILE=$work/runtime
N8N_ENCRYPTION_KEY_FILE=$work/key
EOF

if ! "${compose[@]}" up -d --no-build --wait --wait-timeout 180 n8n-db n8n; then
  "${compose[@]}" logs --tail 40 n8n-db n8n >&2
  exit 1
fi
query=("${compose[@]}" exec -T n8n-db psql -X -Atq
  -v ON_ERROR_STOP=1 -U n8n_bootstrap_admin -d n8n)
"${query[@]}" -c "CREATE TABLE boot_fixture (marker text PRIMARY KEY); INSERT INTO boot_fixture VALUES ('persistent');"
"${compose[@]}" exec -T n8n node -e \
  'require("fs").writeFileSync("/home/node/.n8n/boot-fixture", "persistent")'
n8n_id=$("${compose[@]}" ps -aq n8n)
db_id=$("${compose[@]}" ps -aq n8n-db)
test -n "$n8n_id" && test -n "$db_id"
before=$("${query[@]}" -c 'SELECT count(*) FROM execution_entity;')
active=$("${query[@]}" -c 'SELECT count(*) FROM workflow_entity WHERE active IS TRUE;')
test "$before" = 0 && test "$active" = 0

"${compose[@]}" stop n8n n8n-db
# Same command used by the proposed boot unit; starts existing services only.
for attempt in 1 2; do
  bash "$root/homologation/resume_staging.sh" "$project" "$compose_dir" "$work/compose.env"
  test "$n8n_id" = "$("${compose[@]}" ps -aq n8n)"
  test "$db_id" = "$("${compose[@]}" ps -aq n8n-db)"
  test 1 = "$("${query[@]}" -c "SELECT count(*) FROM boot_fixture WHERE marker='persistent';")"
  "${compose[@]}" exec -T n8n node -e \
    'process.exit(require("fs").readFileSync("/home/node/.n8n/boot-fixture", "utf8") === "persistent" ? 0 : 1)'
  test "$before" = "$("${query[@]}" -c 'SELECT count(*) FROM execution_entity;')"
  test 0 = "$("${query[@]}" -c 'SELECT count(*) FROM workflow_entity WHERE active IS TRUE;')"
done

# Rehydrate a real PG custom dump under a fresh database in this fixture only.
"${compose[@]}" exec -T n8n-db pg_dump -Fc --no-owner --no-acl \
  -U n8n_bootstrap_admin -d n8n > "$work/database.dump"
"${compose[@]}" exec -T n8n-db createdb -U n8n_bootstrap_admin boot_restore_fixture
"${compose[@]}" exec -T n8n-db pg_restore --exit-on-error --no-owner --no-acl \
  -U n8n_bootstrap_admin -d boot_restore_fixture < "$work/database.dump"
test 1 = "$("${compose[@]}" exec -T n8n-db psql -X -Atq -v ON_ERROR_STOP=1 \
  -U n8n_bootstrap_admin -d boot_restore_fixture \
  -c "SELECT count(*) FROM boot_fixture WHERE marker='persistent';")"
printf '%s\n' 'STAGING_BOOT_FIXTURE_PASS containers_reused=2 zero_executions=1 pg_restore=1'
