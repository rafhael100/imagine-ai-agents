#!/usr/bin/env bash
# CENTRAL-AUTO-P9 — ephemeral native n8n + PostgreSQL; no paid AI or network egress.
set -euo pipefail
test "$GITHUB_ACTIONS" = true
test "$GITHUB_REPOSITORY" = rafhael100/imagine-ai-agents
root="$(cd "$(dirname "$0")/../.." && pwd)"
flow="$root/infra/n8n-private/workflows/native-funnel-noai.workflow.json"
checker="$root/infra/n8n-private/check_native_noai_flow.py"
python3 "$checker" static "$flow"
dc=(docker compose --profile homologation -f "$root/infra/n8n-private/docker-compose.yml")
container="$("${dc[@]}" ps -q n8n)"
test -n "$container"
work="$(mktemp -d)"
trap 'rm -rf "$work"' EXIT
docker cp "$flow" "$container:/tmp/central-auto-p9-native.json"
"${dc[@]}" exec -T n8n n8n import:workflow --input=/tmp/central-auto-p9-native.json > "$work/import.log"
"${dc[@]}" exec -T n8n n8n export:workflow --all --output=/tmp/central-auto-p9-export.json > "$work/export.log"
docker cp "$container:/tmp/central-auto-p9-export.json" "$work/exported.json"
python3 "$checker" export "$flow" "$work/exported.json"
id="$(python3 - "$work/exported.json" <<'PY'
import json,sys
data=json.load(open(sys.argv[1],encoding="utf-8"))
if isinstance(data,dict): data=[data]
found=[x for x in data if x.get("name")=="Imagine — Funil nativo sem Code e sem IA (laboratorio)"]
assert len(found)==1 and found[0].get("active") is False
assert isinstance(found[0].get("id"),str) and found[0]["id"]
print(found[0]["id"])
PY
)"
test -n "$id"
if ! "${dc[@]}" exec -T n8n n8n execute --id="$id" --rawOutput > "$work/execute.log" 2> "$work/execute.err"; then
  echo "CENTRAL_AUTO_P9_N8N_EXEC_FAILURE_DIAGNOSTIC_NO_SECRETS" >&2
  python3 - "$work/execute.log" "$work/execute.err" <<'PY'
import sys,re
for name in sys.argv[1:]:
  lines=open(name,encoding="utf-8",errors="replace").read().splitlines()[-24:]
  for line in lines:
    if len(line)>250:line=line[:250]+"[TRUNCATED]"
    line=re.sub(r"(?i)(password|token|secret|authorization|credential)(\\s*[=:]\\s*)(\\S+) ",r"\\1\\2[REDACTED] ",line)
    print(line,file=sys.stderr)
PY
  exit 1
fi
python3 "$checker" result "$flow" "$work/execute.log"
echo "CENTRAL_AUTO_P9_NATIVE_N8N_PASS postgresql_runtime=restricted no_code=1 provider_api=0 credits=0 release=NO_GO"
