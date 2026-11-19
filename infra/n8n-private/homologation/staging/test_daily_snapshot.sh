#!/usr/bin/env bash
# Test scheduler entry point with a simulated staging collection and real age.
set -euo pipefail
umask 077
root="$(cd "$(dirname "$0")" && pwd)"
work="$(mktemp -d)"
trap 'rm -rf -- "$work"' EXIT
mkdir "$work/bin" "$work/spool"
age-keygen -o "$work/identity" 2>/dev/null
export STAGING_AGE_RECIPIENT="$(age-keygen -y "$work/identity")"
export STAGING_AGE_SHA256="$(printf %s "$STAGING_AGE_RECIPIENT" | sha256sum | cut -d ' ' -f 1)"
export STAGING_N8N_KEY_VERSION=synthetic-lab-v1
export STAGING_SPOOL="$work/spool"
export COMPOSE_PROJECT_NAME=imagine-n8n-stage-000000000001
export DOCKER_TEST_STATE="$work/state"
export PATH="$work/bin:$PATH"
cat > "$work/bin/docker" <<'MOCK'
#!/usr/bin/env bash
set -euo pipefail
case "$1" in
  inspect)
    case "$3" in
      *PortBindings*) echo 0 ;;
      *State.Status*) cat "$DOCKER_TEST_STATE" ;;
      *) exit 2 ;;
    esac ;;
  cp) tar -cf - --files-from /dev/null ;;
  compose)
    shift 7
    case "$1" in
      ps) echo "${@: -1}" ;;
      stop) echo exited > "$DOCKER_TEST_STATE" ;;
      up)
        if [[ "${FAIL_RESUME:-0}" == 1 ]]; then exit 1; fi
        echo running > "$DOCKER_TEST_STATE" ;;
      exec)
        case "$4" in
          pg_dump)
            if [[ "${FAIL_DUMP:-0}" == 1 ]]; then exit 1; fi
            printf PGDMP-synthetic ;;
          pg_restore) grep -qx PGDMP-synthetic ;;
          *) exit 2 ;;
        esac ;;
      *) exit 2 ;;
    esac ;;
  *) exit 2 ;;
esac
MOCK
chmod 700 "$work/bin/docker"
printf 'running\n' > "$DOCKER_TEST_STATE"
if "$root/daily_snapshot.sh"; then exit 1; fi
echo 'active writer refused by manual capture'
if FAIL_DUMP=1 "$root/daily_snapshot.sh" --quiesce; then exit 1; fi
test "$(cat "$DOCKER_TEST_STATE")" = running
test -z "$(find "$STAGING_SPOOL" -name '*.age' -print -quit)"
echo 'failed dump left no ciphertext and resumed writer'
if STAGING_AGE_SHA256=invalid "$root/daily_snapshot.sh" --quiesce; then exit 1; fi
echo 'recipient fingerprint mismatch refused'
if FAIL_RESUME=1 "$root/daily_snapshot.sh" --quiesce; then exit 1; fi
test "$(cat "$DOCKER_TEST_STATE")" = exited
cipher="$STAGING_SPOOL/$(date -u +%F).tar.gz.age"
first="$(sha256sum "$cipher")"
"$root/daily_snapshot.sh" --quiesce
test "$(cat "$DOCKER_TEST_STATE")" = running
test "$first" = "$(sha256sum "$cipher")"
echo 'same-day retry resumed writer and preserved ciphertext'
flock "$STAGING_SPOOL/.capture.lock" bash -c '"$1" --quiesce; test "$?" -eq 75' _ "$root/daily_snapshot.sh"
echo 'concurrent capture refused'
age -d -i "$work/identity" -o "$work/archive.tar.gz" "$cipher"
mkdir "$work/restore"
python3 "$root/unpack_snapshot.py" "$work/archive.tar.gz" "$work/restore"
(cd "$work/restore" && sha256sum -c SHA256SUMS)
head -c -1 "$cipher" > "$work/truncated.age"
if age -d -i "$work/identity" -o /dev/null "$work/truncated.age" 2>/dev/null; then exit 1; fi
echo 'truncated ciphertext refused'
