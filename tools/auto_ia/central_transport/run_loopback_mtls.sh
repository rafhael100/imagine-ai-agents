#!/usr/bin/env bash
# CENTRAL-AUTO-P5: all material short-lived and confined to one GitHub Actions runner.
set -euo pipefail
test "$GITHUB_ACTIONS" = true
test "$GITHUB_REPOSITORY" = rafhael100/imagine-ai-agents
root="$(cd "$(dirname "$0")/../../.." && pwd)"
tmp="$(mktemp -d)"
server_pid=""
cleanup() {
  if [[ -n "$server_pid" ]]; then kill "$server_pid" 2>/dev/null || true; wait "$server_pid" 2>/dev/null || true; fi
  rm -rf "$tmp"
}
trap cleanup EXIT
umask 077
openssl req -x509 -newkey rsa:2048 -nodes -sha256 -days 1 \
  -subj '/CN=SYNTHETIC_CI_CA_ONLY' -keyout "$tmp/ca.key" -out "$tmp/ca.crt" >/dev/null 2>&1
for name in server portal rogue; do
  case "$name" in
    server) common=localhost;;
    portal) common=portal-automation-admin;;
    rogue) common=unlisted-test-client;;
  esac
  openssl req -newkey rsa:2048 -nodes -sha256 \
    -subj "/CN=$common" -keyout "$tmp/$name.key" \
    -out "$tmp/$name.csr" >/dev/null 2>&1
  if [[ "$name" = server ]]; then
    printf 'subjectAltName=DNS:localhost,IP:127.0.0.1\nextendedKeyUsage=serverAuth\n' > "$tmp/ext"
  else
    printf 'extendedKeyUsage=clientAuth\n' > "$tmp/ext"
  fi
  openssl x509 -req -sha256 -days 1 -in "$tmp/$name.csr" \
    -CA "$tmp/ca.crt" -CAkey "$tmp/ca.key" -CAcreateserial \
    -extfile "$tmp/ext" -out "$tmp/$name.crt" >/dev/null 2>&1
done
openssl req -x509 -newkey rsa:2048 -nodes -sha256 -days 1 \
  -subj '/CN=SYNTHETIC_WRONG_CA_ONLY' \
  -keyout "$tmp/rogue-ca.key" -out "$tmp/rogue-ca.crt" >/dev/null 2>&1

export CENTRAL_AUTO_P5_CERT_DIR="$tmp"
export CENTRAL_AUTO_P5_FIXTURES="$tmp/crm-state.json"
export CENTRAL_AUTO_P6_PG_NONCE=true
php "$root/tools/auto_ia/central_transport/generate_crm_snapshot.php" > "$tmp/crm-state.json"
python3 "$root/tools/auto_ia/central_transport/mtls_crm_lab_server.py" > "$tmp/server.log" 2>&1 &
server_pid="$!"
ready=0
for _ in $(seq 1 60); do
  if ! kill -0 "$server_pid" 2>/dev/null; then
    cat "$tmp/server.log" >&2
    exit 1
  fi
  http_code="$(curl --silent --show-error --max-time 2 --cacert "$tmp/ca.crt" \
    --cert "$tmp/portal.crt" --key "$tmp/portal.key" \
    --output /dev/null --write-out '%{http_code}' \
    https://localhost:18787/private/automation-state 2>/dev/null || true)"
  if [[ "$http_code" = 405 ]]; then ready=1; break; fi
  sleep 0.15
done
if [[ "$ready" != 1 ]]; then
  cat "$tmp/server.log" >&2
  echo 'CENTRAL_AUTO_P5_SERVER_NOT_READY' >&2
  exit 1
fi
php "$root/tools/auto_ia/central_transport/check_portal_crm_mtls.php"
echo 'CENTRAL_AUTO_P6_FIRST_MTLS_RUN_PASS db_nonce=1 no_real_service=1'
# Restart the fixture server WITHOUT restarting PostgreSQL. Memory state is lost.
kill "$server_pid"
wait "$server_pid" 2>/dev/null || true
server_pid=""
python3 "$root/tools/auto_ia/central_transport/mtls_crm_lab_server.py" > "$tmp/restarted.log" 2>&1 &
server_pid="$!"
ready=0
for _ in $(seq 1 60); do
  if ! kill -0 "$server_pid" 2>/dev/null; then
    cat "$tmp/restarted.log" >&2
    exit 1
  fi
  http_code="$(curl --silent --max-time 2 --cacert "$tmp/ca.crt"     --cert "$tmp/portal.crt" --key "$tmp/portal.key"     --output /dev/null --write-out '%{http_code}'     https://localhost:18787/private/automation-state 2>/dev/null || true)"
  if [[ "$http_code" = 405 ]]; then ready=1; break; fi
  sleep 0.15
done
test "$ready" = 1
php "$root/tools/auto_ia/central_transport/check_replay_after_restart.php"
echo 'CENTRAL_AUTO_P6_PERSISTENT_REPLAY_PASS postgresql=16 no_vps=1 no_provider=1'

# Fail-closed fault injection: revoke DB admission privilege while HTTPS server is up.
test -n "$CENTRAL_AUTO_P8_PG_CONTAINER"
pg_runner=(docker exec -i "$CENTRAL_AUTO_P8_PG_CONTAINER" psql -U postgres -d imagine_agents_ci -v ON_ERROR_STOP=1)
"${pg_runner[@]}" -c "REVOKE EXECUTE ON FUNCTION central_auto_lab.admit_nonce(text,text,text,text,text,text,bigint) FROM ca_mtls_lab_service" > /dev/null
php "$root/tools/auto_ia/central_transport/check_storage_gate.php" 503
"${pg_runner[@]}" -c "GRANT EXECUTE ON FUNCTION central_auto_lab.admit_nonce(text,text,text,text,text,text,bigint) TO ca_mtls_lab_service" > /dev/null
php "$root/tools/auto_ia/central_transport/check_storage_gate.php" 200
php "$root/tools/auto_ia/central_transport/check_storage_gate.php" 409
echo 'CENTRAL_AUTO_P8_FAIL_CLOSED_PASS storage_denied=503 recovered=200 replay=409'
