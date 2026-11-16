#!/usr/bin/env bash
# CENTRAL-AUTO-P12: isolated CI restore of PG17 + n8n data volume + key.
# Synthetic workflow ONLY; not a production disaster recovery procedure.
set -euo pipefail
test "${GITHUB_ACTIONS:-}" = true
test "${GITHUB_REPOSITORY:-}" = rafhael100/imagine-ai-agents
test "${COMPOSE_PROJECT_NAME:-}" = imagine-n8n-p4-ci
test -f "$N8N_ENCRYPTION_KEY_FILE"
test -f "$N8N_PG_ADMIN_PASSWORD_FILE"
test -f "$N8N_PG_PASSWORD_FILE"
test "${N8N_PG_IMAGE:-}" != ""
root="$(cd "$(dirname "$0")/../.." && pwd)"
compose="$root/infra/n8n-private/docker-compose.yml"
checker="$root/infra/n8n-private/check_native_noai_flow.py"
flow="$root/infra/n8n-private/workflows/native-funnel-noai.workflow.json"
src=(docker compose --profile homologation -f "$compose")
dst_project=imagine-n8n-p12-recovery-ci
work="$(mktemp -d)"
cleanup() {
  restore_dc down --volumes --remove-orphans >/dev/null 2>&1 || true
  rm -rf "$work"
}
umask 077
# All destination access is pinned to the hardcoded, disposable CI project.
restore_dc() {
  COMPOSE_PROJECT_NAME="$dst_project" N8N_EXPECTED_PROJECT="$dst_project" \
    N8N_PG_ADMIN_PASSWORD_FILE="$work/admin" \
    N8N_PG_PASSWORD_FILE="$work/runtime" \
    N8N_ENCRYPTION_KEY_FILE="$work/encryption" \
    docker compose --profile homologation -f "$compose" "$@"
}
trap cleanup EXIT
if [[ -n "$("${src[@]}" ps -q --status running n8n)" ]]; then
  echo "CENTRAL_AUTO_P12_REFUSE_RUNNING_ORIGINAL_N8N" >&2
  exit 1
fi
test -n "$("${src[@]}" ps -q n8n-db)"
# Source must contain exactly one reviewed synthetic workflow and zero
# credential records. Never copy a real user's credential into a CI archive.
test "$("${src[@]}" exec -T n8n-db psql -X -qAt -U n8n_bootstrap_admin -d n8n -c 'SELECT count(*) FROM workflow_entity')" = 1
test "$("${src[@]}" exec -T n8n-db psql -X -qAt -U n8n_bootstrap_admin -d n8n -c 'SELECT count(*) FROM credentials_entity')" = 0
"${src[@]}" exec -T n8n-db pg_dump -U n8n_bootstrap_admin -d n8n --format custom --no-acl --no-owner > "$work/n8n-db.dump"
test -s "$work/n8n-db.dump"
# Back up the n8n_data Docker volume, never touching a real host.
# The official pinned PG17 image provides tar; no new images are used.
docker run --rm --network none \
  -v "imagine-n8n-p4-ci_n8n_data:/input:ro" \
  -v "$work:/backup" --entrypoint /bin/sh "$N8N_PG_IMAGE" \
  -c 'cd /input && tar -cf /backup/n8n_data.tar .' >/dev/null
test -s "$work/n8n_data.tar"
# Test-only destination DB passwords intentionally rotate on recovery.
# PostgreSQL bootstrap uses btrim(file), while n8n reads the secret file.
# The two consumers must receive byte-identical values, WITHOUT newline.
openssl rand -hex 32 | tr -d "\n" > "$work/admin"
openssl rand -hex 32 | tr -d "\n" > "$work/runtime"
test "$(wc -c < "$work/admin")" -eq 64
test "$(wc -c < "$work/runtime")" -eq 64
cp "$N8N_ENCRYPTION_KEY_FILE" "$work/encryption"
chmod 0444 "$work/admin" "$work/runtime" "$work/encryption"
cmp -s "$N8N_ENCRYPTION_KEY_FILE" "$work/encryption"
# Validate a second Compose project does not publish host ports.
restore_dc config --format json | N8N_EXPECTED_PROJECT="$dst_project" \
  python3 "$root/infra/n8n-private/check_compose_static.py" >/dev/null
restore_dc up -d --wait --wait-timeout 120 n8n-db >/dev/null
restore_dc exec -T n8n-db pg_restore -U n8n_bootstrap_admin -d n8n \
  --exit-on-error --no-owner --no-acl --role=n8n_runtime < "$work/n8n-db.dump"
docker volume create "${dst_project}_n8n_data" >/dev/null
docker run --rm --network none \
  -v "${dst_project}_n8n_data:/output" -v "$work:/backup:ro" \
  --entrypoint /bin/sh "$N8N_PG_IMAGE" \
  -c 'cd /output && tar -xf /backup/n8n_data.tar' >/dev/null
if ! restore_dc up -d --wait --wait-timeout 180 n8n >/dev/null; then
  echo CENTRAL_AUTO_P12_RECOVERED_N8N_START_FAILED >&2
  restore_dc logs --no-color --tail 80 n8n > "$work/start.log" 2>&1 || true
  python3 - "$work/start.log" <<'PY'
import sys,re
for line in open(sys.argv[1],encoding='utf-8',errors='replace').read().splitlines():
    if not any(term in line.lower() for term in ('error','fail','permission','mismatch','fatal','eacces','exit','database','config')):
        continue
    line=re.sub(r'(?i)(password|secret|token|authorization|api.key)(\s*[:=]\s*)\S+',r'\1\2[REDACTED]',line)
    print('P12_DIAGNOSTIC '+line[:220],file=sys.stderr)
PY
  exit 1
fi
restore_dc exec -T n8n node -e "require('http').get('http://127.0.0.1:5678/healthz/readiness',r=>process.exit(r.statusCode===200?0:1)).on('error',()=>process.exit(1))"
test "$(restore_dc exec -T n8n-db psql -X -qAt -U n8n_bootstrap_admin -d n8n -c 'SELECT count(*) FROM workflow_entity')" = 1
# The recovered app must also use the same restricted DB role.
test "$(restore_dc exec -T n8n-db psql -X -qAt -U n8n_bootstrap_admin -d n8n -c "SELECT rolsuper OR rolbypassrls OR rolcreatedb OR rolcreaterole FROM pg_roles WHERE rolname='n8n_runtime'")" = f
restore_dc stop n8n >/dev/null
mkdir "$work/export"
chmod 777 "$work/export"
restore_dc run -T --rm --no-deps -v "$work/export:/out" --entrypoint n8n n8n \
  export:workflow --all --output=/out/workflows.json > "$work/export.log"
python3 "$checker" export "$flow" "$work/export/workflows.json"
id="$(python3 - "$work/export/workflows.json" <<'PY'
import json,sys
data=json.load(open(sys.argv[1],encoding='utf-8'))
if isinstance(data,dict):data=[data]
matches=[x for x in data if x.get('name')=='Imagine — Funil nativo sem Code e sem IA (laboratorio)']
assert len(matches)==1 and matches[0].get('active') is False
print(matches[0]['id'])
PY
)"
restore_dc run -T --rm --no-deps --entrypoint n8n n8n \
  execute --id="$id" --rawOutput > "$work/result.log"
python3 "$checker" result "$flow" "$work/result.log"
echo "CENTRAL_AUTO_P12_FULL_CI_RESTORE_PASS separate_compose=1 pg17=1 volume_restored=1 synthetic_key_preserved=1 pg_passwords_rotated=1 zero_creds=1 no_ai=1 NO_GO"
