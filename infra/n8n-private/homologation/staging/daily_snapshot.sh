#!/usr/bin/env bash
# Capture a synthetic staging snapshot into a protected local spool.
set -euo pipefail
umask 077
case "${1:-}" in
  '') quiesce=false ;;
  --quiesce) quiesce=true ;;
  *) exit 64 ;;
esac
[[ "$#" -le 1 ]]

: "${COMPOSE_PROJECT_NAME:?Set the disposable staging project}"
[[ "$COMPOSE_PROJECT_NAME" =~ ^imagine-n8n-stage-[a-f0-9]{12}$ ]]
: "${STAGING_SPOOL:?Set the private staging spool}"
: "${STAGING_AGE_RECIPIENT:?Set the public recovery recipient}"
: "${STAGING_AGE_SHA256:?Set the independently checked fingerprint}"
: "${STAGING_N8N_KEY_VERSION:?Set the recovery key reference}"
[[ "$STAGING_N8N_KEY_VERSION" =~ ^[a-zA-Z0-9._-]{1,80}$ ]]
[[ "$(printf %s "$STAGING_AGE_RECIPIENT" | sha256sum | cut -d ' ' -f 1)" == "$STAGING_AGE_SHA256" ]]
[[ -d "$STAGING_SPOOL" && ! -L "$STAGING_SPOOL" && -O "$STAGING_SPOOL" ]]
[[ "$(stat -c %a "$STAGING_SPOOL")" == 700 ]]

exec 9>"$STAGING_SPOOL/.capture.lock"
flock -n 9 || { echo "snapshot skipped: capture already running" >&2; exit 75; }
snapshot="$(date -u +%F)"
destination="$STAGING_SPOOL/$snapshot.tar.gz.age"
compose="$(cd "$(dirname "$0")/../.." && pwd)/docker-compose.yml"
dc=(docker compose --project-name "$COMPOSE_PROJECT_NAME" --profile homologation -f "$compose")
resume_marker="$STAGING_SPOOL/.resume-$COMPOSE_PROJECT_NAME"
resume_writer() {
  "${dc[@]}" up -d --no-build --pull never --wait --wait-timeout 180 n8n &&
    rm -f -- "$resume_marker"
}
if [[ -f "$resume_marker" ]]; then
  resume_writer
fi
if [[ -s "$destination" ]]; then
  echo "local snapshot already captured: $snapshot"
  exit 0
fi

db="$("${dc[@]}" ps -q n8n-db)"
app="$("${dc[@]}" ps -a -q n8n)"
[[ -n "$db" && -n "$app" ]]
[[ 0 == "$(docker inspect --format '{{len .HostConfig.PortBindings}}' "$app")" ]]

work="$(mktemp -d "$STAGING_SPOOL/.capture.XXXXXXXX")"
resume=false
cleanup() {
  local result=$?
  rm -rf -- "$work"
  if "$resume" && ! resume_writer; then
    echo "staging writer did not resume" >&2
    result=1
  fi
  exit "$result"
}
trap cleanup EXIT
trap 'exit 143' TERM
trap 'exit 130' INT
if "$quiesce" && [[ "$(docker inspect --format '{{.State.Status}}' "$app")" == running ]]; then
  : > "$resume_marker"
  resume=true
  "${dc[@]}" stop n8n
fi
[[ "$(docker inspect --format '{{.State.Status}}' "$app")" == exited ]]
captured_at="$(date -u +%FT%TZ)"
"${dc[@]}" exec -T n8n-db pg_dump -U n8n_bootstrap_admin -d n8n \
  --format custom --no-acl --no-owner > "$work/n8n-db.dump"
"${dc[@]}" exec -T n8n-db pg_restore --list < "$work/n8n-db.dump" >/dev/null
docker cp "$app:/home/node/.n8n/." - > "$work/n8n-data.tar"
tar -tf "$work/n8n-data.tar" >/dev/null
printf '%s\n' "$captured_at" > "$work/captured-at"
printf '%s\n' "$STAGING_N8N_KEY_VERSION" > "$work/n8n-key-version"
(
  cd "$work"
  sha256sum n8n-db.dump n8n-data.tar captured-at n8n-key-version > SHA256SUMS
)
tar -C "$work" -czf - n8n-db.dump n8n-data.tar captured-at n8n-key-version SHA256SUMS |
  age -r "$STAGING_AGE_RECIPIENT" -o "$work/payload.age"
[[ -s "$work/payload.age" ]]
touch -d "$captured_at" "$work/payload.age"
# Link atomically and refuse to overwrite an already-published file.
ln "$work/payload.age" "$destination"
echo "local encrypted snapshot captured: $snapshot"
