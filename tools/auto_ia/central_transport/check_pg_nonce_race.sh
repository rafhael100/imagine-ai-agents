#!/usr/bin/env bash
# A separate 8-process race against a real disposable PG16. No n8n or AI.
set -euo pipefail
test "$GITHUB_ACTIONS" = true
test "$GITHUB_REPOSITORY" = rafhael100/imagine-ai-agents
export PGHOST=127.0.0.1
export PGUSER=ca_mtls_lab_service
export PGPASSWORD=SYNTHETIC_CENTRAL_AUTO_P6_DB_LOGIN_ONLY
export PGDATABASE=imagine_agents_ci
tmp="$(mktemp -d)"
trap 'rm -rf "$tmp"' EXIT
now="$(date +%s)"
digest="$(printf '%064d' 0)"
for i in $(seq 1 8); do
  (
    psql -X -q -At -v ON_ERROR_STOP=1 -c \
      "SELECT central_auto_lab.admit_nonce('portal-automation-admin','crm-automation-state','aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa','SYNTHETIC_RACE_REQUEST_$i','SYNTHETIC_RACE_NONCE','$digest',$now);" \
      > "$tmp/$i"
  ) &
done
wait
accepted="$(grep -l '^LAB_ADMITTED_NO_DELIVERY$' "$tmp"/* | wc -l)"
denied="$(grep -l '^LAB_REPLAY_DENIED$' "$tmp"/* | wc -l)"
if [[ "$accepted" != 1 || "$denied" != 7 ]]; then
  echo "CENTRAL_AUTO_P6_RACE_FAIL accepted=$accepted denied=$denied" >&2
  cat "$tmp"/*
  exit 1
fi
status="$(psql -X -Atq -v ON_ERROR_STOP=1 -c \
 "SELECT has_table_privilege('ca_mtls_lab_service','central_auto_lab.mtls_nonce_ledger','SELECT')::text || ':' || has_table_privilege('ca_mtls_lab_service','central_auto_lab.mtls_nonce_ledger','INSERT')::text;")"
test "$status" = "false:false" || { echo "DIRECT_LEDGER_ACCESS_DENIED_EXPECTED $status" >&2; exit 1; }
wrong="$(psql -X -Atq -v ON_ERROR_STOP=1 -c \
 "SELECT central_auto_lab.admit_nonce('portal-automation-admin','crm-automation-state','bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb','SYNTHETIC_WRONG_COMPANY','SYNTHETIC_WRONG_NONCE','$digest',$now);")"
test "$wrong" = LAB_DENY_SCOPE
echo "CENTRAL_AUTO_P6_ATOMIC_RACE_PASS admitted=1 replay_denied=7 direct_table_access=0 other_company_denied=1"
