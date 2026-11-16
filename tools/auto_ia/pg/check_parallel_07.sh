#!/usr/bin/env bash
# AUTO-IA-07 CI-only: 8 independent PostgreSQL sessions, only 3 may reserve.
set -euo pipefail
container="${1:?ephemeral-postgres-container-required}"
dbname="${2:-postgres}"
tmp="$(mktemp -d)"
trap 'rm -rf "$tmp"' EXIT
for i in $(seq 1 8); do
  (
    printf "SET ROLE aia_lab_tenant_a; SELECT auto_ia_lab.reserve('imagine-ai-agents','document.summary.propose','SYNTHETIC_PAR_EVENT_%s','SYNTHETIC_PAR_REQUEST_%s','%064d',11,4);\n" "$i" "$i" 0 |
      docker exec -i "$container" psql -U postgres -d "$dbname" -At -v ON_ERROR_STOP=1 >"$tmp/$i"
  ) &
done
wait
passed="$(grep -l '^LAB_RESERVED_NO_AUTHORIZATION$' "$tmp"/* | wc -l)"
denied="$(grep -l '^LAB_DENY_BUDGET$' "$tmp"/* | wc -l)"
if [[ "$passed" != 3 || "$denied" != 5 ]]; then
  echo "AUTO_IA_07_FAILED pass=$passed denied=$denied" >&2
  cat "$tmp"/* >&2
  exit 1
fi
docker exec -i "$container" psql -U postgres -d "$dbname" -At -v ON_ERROR_STOP=1 <<'SQL' >"$tmp/check"
SET ROLE aia_lab_tenant_a;
SELECT CASE WHEN COUNT(*)=3 AND SUM(estimated_minor)=12 AND SUM(estimated_tokens)=33
 THEN 'AUTO_IA_07_PARALLEL_PASS' ELSE 'AUTO_IA_07_PARALLEL_FAIL' END
FROM auto_ia_lab.requests WHERE product='imagine-ai-agents';
SQL
grep -qx 'AUTO_IA_07_PARALLEL_PASS' "$tmp/check"
echo "AUTO_IA_07_PARALLEL_PASS accepted=3 denied=5 budget_minor=12 tokens=33 ai_calls=0"
