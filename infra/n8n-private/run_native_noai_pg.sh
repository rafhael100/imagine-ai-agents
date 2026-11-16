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
"${dc[@]}" exec -T n8n n8n execute --id="$id" --rawOutput > "$work/execute.log"
python3 "$checker" result "$flow" "$work/execute.log"
echo "CENTRAL_AUTO_P9_NATIVE_N8N_PASS postgresql_runtime=restricted no_code=1 provider_api=0 credits=0 release=NO_GO"
