#!/usr/bin/env bash
# AUTO-IA-14: official n8n runtime in disposable GitHub Actions.
# No VPS, customer data, API providers, public ports, or external network.
set -euo pipefail
if [[ "${GITHUB_ACTIONS:-}" != "true" ||
      "${GITHUB_REPOSITORY:-}" != "rafhael100/imagine-ai-agents" ]]; then
  echo "AUTO_IA_14_DENY_NON_CI_RUN" >&2
  exit 1
fi
image="ghcr.io/n8n-io/n8n@sha256:b4b90747b81fcda7e76550c0d3aa7f0f64917a1e9573cff40d6834eaf2e0f8ea"
root="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
workflow="$root/tools/auto_ia/demo/n8n-noai-demo.workflow.json"
python3 "$root/tools/auto_ia/policy/check_n8n_zero_ai.py"
node "$root/tools/auto_ia/demo/check_n8n_noai_workflow.cjs"
test -f "$workflow"
work="$(mktemp -d)"
container=""
cleanup() {
  if [[ -n "$container" ]]; then
    docker rm -f "$container" >/dev/null 2>&1 || true
  fi
  rm -rf "$work"
}
trap cleanup EXIT
echo "AUTO_IA_14_PULL_STABLE_IMAGE_FOR_DIGEST_REVIEW"
docker pull "$image"
docker image inspect "$image" --format '{{index .RepoDigests 0}}' | tee "$work/digest.txt"
grep -Eq '^ghcr.io/n8n-io/n8n@sha256:[a-f0-9]{64}$' "$work/digest.txt"
# The container is isolated with --network none and NO published ports.
container="$(docker run -d --rm --network none \
  --name "imagine-auto-ia-14-$GITHUB_RUN_ID" \
  --security-opt no-new-privileges --cap-drop ALL --pids-limit 256 \
  --cpus 3 --memory 4096m \
  --mount "type=bind,source=$workflow,target=/tmp/noai.workflow.json,readonly" \
  -e N8N_ENCRYPTION_KEY=SYNTHETIC_CI_ONLY_NOT_FOR_PRODUCTION \
  -e N8N_DIAGNOSTICS_ENABLED=false \
  -e N8N_PERSONALIZATION_ENABLED=false \
  -e N8N_COMMUNITY_PACKAGES_ENABLED=false \
  -e NODE_OPTIONS=--max-old-space-size=3072 \
  -e N8N_RUNNERS_ENABLED=true \
  -e N8N_RUNNERS_MODE=internal \
  --entrypoint /bin/sh \
  "$image" -c 'exec sleep 1200')"
echo "AUTO_IA_14_CONTAINER_ISOLATED"
docker inspect "$container" --format '{{.HostConfig.NetworkMode}}' | grep -qx none
python3 - "$container" <<'PY'
import json,subprocess,sys
d=json.loads(subprocess.check_output(["docker","inspect",sys.argv[1]]))[0]
assert not d["NetworkSettings"]["Ports"] or all(
    v is None for v in d["NetworkSettings"]["Ports"].values())
assert d["HostConfig"]["Privileged"] is False
assert [s.upper() for s in d["HostConfig"]["CapDrop"]] == ["ALL"]
PY
docker exec "$container" n8n --version | tee "$work/version.txt"
grep -Eq '^2\.47\.7$' "$work/version.txt"
docker exec "$container" n8n import:workflow --input=/tmp/noai.workflow.json
docker exec "$container" n8n export:workflow --all --output=/tmp/exported.json
docker cp "$container:/tmp/exported.json" "$work/exported.json"
python3 "$root/tools/auto_ia/demo/check_n8n_roundtrip.py" "$workflow" "$work/exported.json"
python3 - "$work/exported.json" > "$work/id.txt" <<'PY'
import json,sys
data=json.load(open(sys.argv[1],encoding="utf-8"))
if isinstance(data,dict):
    data=[data]
assert isinstance(data,list) and len(data)==1, "wrong workflow count"
w=data[0]
assert w.get("active") is False, "workflow unexpectedly active"
assert {n["type"] for n in w["nodes"]} == {
  "n8n-nodes-base.manualTrigger","n8n-nodes-base.code"}
assert all("credentials" not in n for n in w["nodes"])
ident=w.get("id")
assert isinstance(ident,str) and 0<len(ident)<90
print(ident)
PY
id="$(cat "$work/id.txt")"
echo "AUTO_IA_14_IMPORT_PASS active=false"
docker exec "$container" n8n execute --id "$id" --rawOutput | tee "$work/execute.log"
python3 "$root/tools/auto_ia/demo/verify_n8n_execution.py" "$work/execute.log"
echo "AUTO_IA_15_N8N_EXECUTION_PROVEN external_network=none leads=12 clients=2 ai_calls=0 credit_debit=0 vps_changed=0"
