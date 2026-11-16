#!/usr/bin/env bash
# AUTO-IA-08 CI only: same nonce across 8 independent DB processes must yield one inbox row.
set -euo pipefail
container="${1:?ephemeral-postgres-container-required}"
tmp="$(mktemp -d)"
trap 'rm -rf "$tmp"' EXIT
now="$(date +%s)"
for i in $(seq 1 8); do
  (
    printf "SET ROLE aia_lab_tenant_a; SELECT auto_ia_lab.receive_no_ai('imagine-crm','lead.ingest','SYNTHETIC_PAR_INBOX_EV_%s','SYNTHETIC_PAR_SHARED_NONCE','%064d',%s,%s);\n" "$i" 0 "$((now-10))" "$((now+120))" |
      docker exec -i "$container" psql -U postgres -d postgres -At -v ON_ERROR_STOP=1 > "$tmp/$i"
  ) &
done
wait
accepted="$(grep -l '^LAB_RECEIVED_NO_DELIVERY$' "$tmp"/* | wc -l)"
denied="$(grep -l '^LAB_DENY_REPLAY_CONFLICT$' "$tmp"/* | wc -l)"
if [[ "$accepted" != 1 || "$denied" != 7 ]]; then
  echo "AUTO_IA_08_FAILED accepted=$accepted denied=$denied" >&2
  cat "$tmp"/* >&2
  exit 1
fi
docker exec -i "$container" psql -U postgres -d postgres -At -v ON_ERROR_STOP=1 <<'SQL' > "$tmp/check"
SET ROLE aia_lab_tenant_a;
SELECT CASE WHEN COUNT(*)=1 THEN 'AUTO_IA_08_PARALLEL_PASS' ELSE 'AUTO_IA_08_PARALLEL_FAIL' END
FROM auto_ia_lab.event_inbox WHERE nonce_ref='SYNTHETIC_PAR_SHARED_NONCE';
SQL
grep -qx 'AUTO_IA_08_PARALLEL_PASS' "$tmp/check"
echo "AUTO_IA_08_PARALLEL_PASS accepted=1 denied=7 provider_calls=0 no_n8n=1"
