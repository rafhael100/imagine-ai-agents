#!/usr/bin/env bash
# CENTRAL-AUTO-P14: official n8n security audit for ONE synthetic CI workflow.
# Does not inspect real tenants, production files, credentials or VPS.
set -euo pipefail
test "${GITHUB_ACTIONS:-}" = true
test "${GITHUB_REPOSITORY:-}" = rafhael100/imagine-ai-agents
test "${COMPOSE_PROJECT_NAME:-}" = imagine-n8n-p4-ci
root="$(cd "$(dirname "$0")/../.." && pwd)"
dc=(docker compose --profile homologation -f "$root/infra/n8n-private/docker-compose.yml")
test -n "$("${dc[@]}" ps -q n8n-db)"
if [[ -n "$("${dc[@]}" ps -q --status running n8n)" ]]; then
  echo "CENTRAL_AUTO_P14_REFUSE_CONCURRENT_N8N" >&2
  exit 1
fi
workflow_count="$("${dc[@]}" exec -T n8n-db psql -qAtX -U n8n_bootstrap_admin -d n8n -c "SELECT count(*) FROM workflow_entity")"
creds_count="$("${dc[@]}" exec -T n8n-db psql -qAtX -U n8n_bootstrap_admin -d n8n -c "SELECT count(*) FROM credentials_entity")"
active_count="$("${dc[@]}" exec -T n8n-db psql -qAtX -U n8n_bootstrap_admin -d n8n -c "SELECT count(*) FROM workflow_entity WHERE active")"
if [[ "$workflow_count" != 1 || "$creds_count" != 0 || "$active_count" != 0 ]]; then
  echo "CENTRAL_AUTO_P14_NOT_SYNTHETIC_ONLY" >&2
  exit 1
fi
work="$(mktemp -d)"
trap 'rm -rf "$work"' EXIT
if ! "${dc[@]}" run -T --rm --no-deps --entrypoint n8n n8n \
  audit --categories=credentials,database,filesystem,nodes \
  > "$work/audit.stdout" 2> "$work/audit.stderr"; then
  echo "CENTRAL_AUTO_P14_OFFICIAL_AUDIT_CLI_FAILED" >&2
  exit 1
fi
python3 - "$work/audit.stdout" "$work/audit.stderr" <<'PY'
import sys
out=open(sys.argv[1],encoding="utf-8",errors="replace").read()
err=open(sys.argv[2],encoding="utf-8",errors="replace").read()
if len(out)>100000 or len(err)>100000:
    raise SystemExit("CENTRAL_AUTO_P14_TOO_MUCH_AUDIT_DATA")
if "Failed to generate security audit" in out+err or "error occurred" in out.lower()+err.lower():
    raise SystemExit("CENTRAL_AUTO_P14_AUDIT_ERROR")
if "No security issues found" not in out+err:
    raise SystemExit("CENTRAL_AUTO_P14_AUDIT_RISK_OR_UNKNOWN_RESULT")
print("CENTRAL_AUTO_P14_OFFICIAL_AUDIT_PASS categories=credentials,database,filesystem,nodes issues=0 synthetic_only=1")
PY
echo "CENTRAL_AUTO_P14_NO_GO real_customer_audit=0 production_access=0 paid_model_calls=0"
