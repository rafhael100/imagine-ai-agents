#!/usr/bin/env bash
set -euo pipefail
umask 077
base="$(cd "$(dirname "$0")/../.." && pwd)"
unset AWS_ACCESS_KEY_ID AWS_SECRET_ACCESS_KEY AWS_SESSION_TOKEN IMAGINE_R2_ENDPOINT
unset N8N_STAGING_R2_ACCESS_KEY_ID N8N_STAGING_R2_SECRET_ACCESS_KEY
work="$(mktemp -d)"
src="imagine-n8n-stage-$(openssl rand -hex 6)"
dst="imagine-n8n-stage-$(openssl rand -hex 6)"
export N8N_IMAGE=ghcr.io/n8n-io/n8n@sha256:b4b90747b81fcda7e76550c0d3aa7f0f64917a1e9573cff40d6834eaf2e0f8ea
export N8N_PG_IMAGE=postgres@sha256:b60e90042aa6b8dbecd58e224b3e36114dbf8ad754465264c2476ad58a86e45c
for f in admin runtime encryption; do
  printf %s "$(openssl rand -hex 32)" > "$work/$f"
  chmod 0444 "$work/$f"
done
export N8N_PG_ADMIN_PASSWORD_FILE="$work/admin"
export N8N_PG_PASSWORD_FILE="$work/runtime"
export N8N_ENCRYPTION_KEY_FILE="$work/encryption"
dc() { docker compose --project-name "$1" --profile homologation -f "$base/docker-compose.yml" "${@:2}"; }
cleanup() {
  dc "$dst" down --volumes >/dev/null 2>&1 || true
  dc "$src" down --volumes >/dev/null 2>&1 || true
  rm -rf -- "$work"
}
trap cleanup EXIT
age-keygen -o "$work/lab.agekey" 2>"$work/keygen.log"
export STAGING_AGE_RECIPIENT="$(age-keygen -y "$work/lab.agekey")"
export STAGING_AGE_SHA256="$(printf %s "$STAGING_AGE_RECIPIENT" | sha256sum | cut -d ' ' -f 1)"
export COMPOSE_PROJECT_NAME="$src"
export STAGING_SPOOL="$work/spool"
export STAGING_N8N_KEY_VERSION=synthetic-lab-v1
mkdir "$STAGING_SPOOL" "$work/decoded"
backup="$base/homologation/staging/daily_snapshot.sh"
docker image inspect "$N8N_IMAGE" "$N8N_PG_IMAGE" >/dev/null
if [[ ! -r "$base/10-runtime-role.sh" || "$(stat -c %a "$base/10-runtime-role.sh")" != 644 ]]; then
  echo "The tracked PostgreSQL bootstrap file needs mode 0644 in the private checkout" >&2
  exit 1
fi
dc "$src" up -d --pull never --wait --wait-timeout 100 n8n
dc "$src" exec -T n8n node -e "require('fs').writeFileSync('/home/node/.n8n/hom05-marker','synthetic-marker')"
flow="$base/workflows/native-funnel-noai.workflow.json"
checker="$base/check_native_noai_flow.py"
source_container="$(dc "$src" ps -q n8n)"
docker cp "$flow" "$source_container:/tmp/hom05-workflow.json"
dc "$src" exec -T n8n n8n import:workflow --input=/tmp/hom05-workflow.json > "$work/import-flow.log"
dc "$src" exec -T n8n node -e '
  require("fs").writeFileSync("/tmp/hom05-credential.json",JSON.stringify([{
    id:"Hom05Synthetic01", name:"HOM-05 synthetic", type:"httpBasicAuth",
    data:{user:"fixture",password:"synthetic-only"}
  }]),{mode:0o600})'
dc "$src" exec -T n8n n8n import:credentials --input=/tmp/hom05-credential.json > "$work/import-cred.log"
if "$backup"; then echo 'unexpected success with active writer'; exit 1; fi
test -z "$(find "$STAGING_SPOOL" -name '*.age' -print -quit)"
echo 'ACTIVE_WRITER_REFUSED'
if STAGING_AGE_SHA256=invalid "$backup"; then echo 'unexpected recipient accepted'; exit 1; fi
echo 'WRONG_RECIPIENT_REFUSED'
"$backup" --quiesce
test -n "$(dc "$src" ps -q --status running n8n)"
archive="$STAGING_SPOOL/$(date -u +%F).tar.gz.age"
before="$(sha256sum "$archive")"
"$backup"
test "$before" = "$(sha256sum "$archive")"
echo 'SAME_DAY_RETRY_IDEMPOTENT'
start="$(date +%s)"
head -c -1 "$archive" > "$work/truncated.age"
if age -d -i "$work/lab.agekey" -o /dev/null "$work/truncated.age" 2>/dev/null; then exit 1; fi
echo 'TRUNCATED_CIPHERTEXT_REFUSED'
age -d -i "$work/lab.agekey" -o "$work/decoded/archive.tar.gz" "$archive"
python3 "$base/homologation/staging/unpack_snapshot.py" "$work/decoded/archive.tar.gz" "$work/decoded"
(cd "$work/decoded" && sha256sum -c SHA256SUMS)
dc "$dst" up -d --pull never --wait --wait-timeout 100 n8n-db
dc "$dst" exec -T n8n-db pg_restore -U n8n_bootstrap_admin -d n8n --exit-on-error --no-acl --no-owner --role=n8n_runtime < "$work/decoded/n8n-db.dump"
dc "$dst" create --pull never n8n
destination="$(dc "$dst" ps -a -q n8n)"
docker cp -a - "$destination:/home/node/.n8n/" < "$work/decoded/n8n-data.tar"
dc "$dst" up -d --pull never --wait --wait-timeout 100 n8n
test "$(dc "$dst" exec -T n8n cat /home/node/.n8n/hom05-marker)" = synthetic-marker
id="$(dc "$dst" exec -T n8n-db psql -XqAt -U n8n_bootstrap_admin -d n8n -c 'SELECT id FROM workflow_entity WHERE NOT active')"
[[ "$id" =~ ^[a-zA-Z0-9_-]+$ ]]
dc "$dst" exec -T n8n n8n export:credentials --all --decrypted --output=/tmp/hom05-restored-credential.json > "$work/export-cred.log"
dc "$dst" exec -T n8n node -e '
  const c = JSON.parse(require("fs").readFileSync("/tmp/hom05-restored-credential.json"));
  if(c.length!==1||c[0].data.user!=="fixture"||c[0].data.password!=="synthetic-only") process.exit(1)'
dc "$dst" stop n8n
dc "$dst" run -T --rm --no-deps --pull never --entrypoint n8n n8n execute --id="$id" --rawOutput > "$work/workflow-result.log"
python3 "$checker" result "$flow" "$work/workflow-result.log"
echo "STAGING_RESTORE_PASS seconds=$(($(date +%s)-start)) ciphertext_bytes=$(wc -c < "$archive")"
