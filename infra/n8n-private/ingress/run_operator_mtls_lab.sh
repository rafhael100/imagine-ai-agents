#!/usr/bin/env bash
# P15 CI-only. Never run on Leaseweb, never use real client certificates.
set -euo pipefail
test "${GITHUB_ACTIONS:-}" = true
test "${GITHUB_REPOSITORY:-}" = rafhael100/imagine-ai-agents
test "${COMPOSE_PROJECT_NAME:-}" = imagine-n8n-p15-ci
test "${N8N_OPERATOR_PORT:-18744}" = 18744
root="$(cd "$(dirname "$0")/../../.." && pwd)"
base="$root/infra/n8n-private/docker-compose.yml"
overlay="$root/infra/n8n-private/docker-compose.operator-loopback.yml"
work="$(mktemp -d)"
umask 077
dc=(docker compose --profile homologation --profile operator-lab -f "$base" -f "$overlay")
cleanup(){
  "${dc[@]}" down --volumes --remove-orphans >/dev/null 2>&1 || true
  rm -rf "$work"
}
trap cleanup EXIT

# Generate TWO independent CAs so trust of a rogue external CA is rejected.
for ca in server-ca client-ca rogue-ca; do
  openssl req -x509 -newkey rsa:2048 -nodes -days 1 -sha256 \
    -subj "/CN=CENTRAL_AUTO_P15_${ca}" \
    -keyout "$work/$ca.key" -out "$work/$ca.crt" >/dev/null 2>&1
done
openssl req -newkey rsa:2048 -nodes -sha256 -subj "/CN=localhost" \
  -keyout "$work/server.key" -out "$work/server.csr" >/dev/null 2>&1
printf "subjectAltName=DNS:localhost,IP:127.0.0.1\nextendedKeyUsage=serverAuth\n" > "$work/ext"
openssl x509 -req -sha256 -days 1 -in "$work/server.csr" -CA "$work/server-ca.crt" \
  -CAkey "$work/server-ca.key" -CAcreateserial -extfile "$work/ext" \
  -out "$work/server.crt" >/dev/null 2>&1
for identity in imagine-operator-ci rogue-operator same-cn-operator; do
  name="$identity"
  if [[ "$identity" = same-cn-operator ]]; then name=imagine-operator-ci; fi
  openssl req -newkey rsa:2048 -nodes -sha256 \
    -subj "/CN=$name" -keyout "$work/$identity.key" \
    -out "$work/$identity.csr" >/dev/null 2>&1
  printf "extendedKeyUsage=clientAuth\n" > "$work/ext"
  openssl x509 -req -sha256 -days 1 -in "$work/$identity.csr" \
    -CA "$work/client-ca.crt" -CAkey "$work/client-ca.key" \
    -CAcreateserial -extfile "$work/ext" \
    -out "$work/$identity.crt" >/dev/null 2>&1
done
openssl req -newkey rsa:2048 -nodes -sha256 -subj "/CN=imagine-operator-ci" \
  -keyout "$work/untrusted.key" -out "$work/untrusted.csr" >/dev/null 2>&1
printf "extendedKeyUsage=clientAuth\n" > "$work/ext"
openssl x509 -req -sha256 -days 1 -in "$work/untrusted.csr" \
  -CA "$work/rogue-ca.crt" -CAkey "$work/rogue-ca.key" \
  -CAcreateserial -extfile "$work/ext" \
  -out "$work/untrusted.crt" >/dev/null 2>&1
# Local-file Compose secrets preserve ownership and mode of the source.
# Root in this container has CAP_DAC_OVERRIDE removed: public certificates
# are world-readable, but the ephemeral server key is root-owned 0400.
chmod 0444 "$work/server.crt" "$work/client-ca.crt"
sudo chown 0:0 "$work/server.key"
sudo chmod 0400 "$work/server.key"
export N8N_OPERATOR_CERT_FILE="$work/server.crt"
export N8N_OPERATOR_KEY_FILE="$work/server.key"
export N8N_OPERATOR_CLIENT_CA_FILE="$work/client-ca.crt"
# The CA may sign two certificates sharing a CN; only the exact reviewed
# operator certificate fingerprint must pass, never any same-CN impostor.
fingerprint="$(openssl x509 -in "$work/imagine-operator-ci.crt" -noout -fingerprint -sha1 | cut -d= -f2 | tr -d ":" | tr "A-F" "a-f")"
[[ "$fingerprint" =~ ^[a-f0-9]{40}$ ]]
printf "%s 1;\n" "$fingerprint" > "$work/operator-fingerprints.conf"
chmod 0444 "$work/operator-fingerprints.conf"
export N8N_OPERATOR_PIN_FILE="$work/operator-fingerprints.conf"
export N8N_OPERATOR_PORT=18744

# Static guard before any Docker mutation. Full Compose must be exactly 3
# services; only the mTLS proxy may use a host-bound TCP port.
"${dc[@]}" config --format json | python3 "$root/infra/n8n-private/ingress/check_operator_overlay.py" > "$work/policy.json"
if ! "${dc[@]}" up -d --wait --wait-timeout 180 >/dev/null; then
  echo "CENTRAL_AUTO_P15_INGRESS_START_FAILED" >&2
  "${dc[@]}" logs --no-color --tail 40 operator-ingress > "$work/proxy-start.log" 2>&1 || true
  python3 - "$work/proxy-start.log" <<'PY'
import sys,re
lines=open(sys.argv[1],encoding="utf-8",errors="replace").read().splitlines()
for line in lines[-40:]:
    if not any(w in line.lower() for w in ("error","emerg","warn","denied","failed","nginx:","permission")):
        continue
    line=re.sub(r"(?i)(password|token|secret|authorization)(\s*[:=]\s*)\S+",r"\1\2[REDACTED]",line)
    print("P15_NGINX_DIAGNOSTIC "+line[:240],file=sys.stderr)
PY
  exit 1
fi
container="$("${dc[@]}" ps -q operator-ingress)"
test -n "$container"
python3 - "$container" <<'PY'
import json,subprocess,sys
data=json.loads(subprocess.check_output(["docker","inspect",sys.argv[1]]))[0]
ports=data["HostConfig"]["PortBindings"]
assert set(ports)=={"8443/tcp"}
assert len(ports["8443/tcp"])==1
assert ports["8443/tcp"][0]["HostIp"]=="127.0.0.1"
assert str(ports["8443/tcp"][0]["HostPort"])=="18744"
print("CENTRAL_AUTO_P15_HOST_LOOPBACK_ONLY_PASS")
PY

# Make sure the mTLS operator certificate is the ONLY client that can reach UI.
ok=0
for _ in $(seq 1 55); do
  code="$(curl --silent --show-error --connect-timeout 2 --max-time 4 \
    --cacert "$work/server-ca.crt" \
    --cert "$work/imagine-operator-ci.crt" --key "$work/imagine-operator-ci.key" \
    --output /dev/null --write-out "%{http_code}" \
    https://localhost:18744/ 2>/dev/null || true)"
  if [[ "$code" = 200 || "$code" = 301 || "$code" = 302 || "$code" = 303 ]]; then
    ok=1;break
  fi
  sleep 0.2
done
test "$ok" = 1 || { echo "P15_VALID_OPERATOR_CANNOT_REACH_N8N" >&2;exit 1; }

no_cert="$(curl --silent --max-time 4 --cacert "$work/server-ca.crt" \
  --output /dev/null --write-out "%{http_code}" \
  https://localhost:18744/ 2>/dev/null || true)"
case "$no_cert" in 200|301|302|303) echo "P15_NO_CERT_ACCEPTED" >&2;exit 1;; esac
rogue_cn="$(curl --silent --max-time 4 --cacert "$work/server-ca.crt" \
  --cert "$work/rogue-operator.crt" --key "$work/rogue-operator.key" \
  --output /dev/null --write-out "%{http_code}" \
  https://localhost:18744/ 2>/dev/null || true)"
test "$rogue_cn" = 403 || { echo "P15_UNRECOGNIZED_OPERATOR_NOT_DENIED" >&2;exit 1; }
same_cn="$(curl --silent --max-time 4 --cacert "$work/server-ca.crt" \
  --cert "$work/same-cn-operator.crt" --key "$work/same-cn-operator.key" \
  --output /dev/null --write-out "%{http_code}" \
  https://localhost:18744/ 2>/dev/null || true)"
test "$same_cn" = 403 || { echo "P16_SAME_CN_IMPOSTOR_ACCEPTED" >&2;exit 1; }
wrong_ca="$(curl --silent --max-time 4 --cacert "$work/server-ca.crt" \
  --cert "$work/untrusted.crt" --key "$work/untrusted.key" \
  --output /dev/null --write-out "%{http_code}" \
  https://localhost:18744/ 2>/dev/null || true)"
case "$wrong_ca" in 200|301|302|303) echo "P15_ROGUE_CA_ACCEPTED" >&2;exit 1;; esac

# P17: perform a live, synthetic certificate rotation in the same nginx.
# The former cert is still signed by the trusted CA, but must lose access
# immediately after the pinned certificate is changed and nginx reloaded.
old_fingerprint="$fingerprint"
new_fingerprint="$(openssl x509 -in "$work/same-cn-operator.crt" -noout -fingerprint -sha1 | cut -d= -f2 | tr -d ":" | tr "A-F" "a-f")"
[[ "$new_fingerprint" =~ ^[a-f0-9]{40}$ ]]
test "$new_fingerprint" != "$old_fingerprint"
chmod 0644 "$work/operator-fingerprints.conf"
printf "%s 1;\n" "$new_fingerprint" > "$work/operator-fingerprints.conf"
chmod 0444 "$work/operator-fingerprints.conf"
! grep -q "$old_fingerprint" "$work/operator-fingerprints.conf"
"${dc[@]}" exec -T operator-ingress nginx -t >/dev/null
"${dc[@]}" exec -T operator-ingress nginx -s reload >/dev/null
# Independent fresh curl TLS connections; old certificate must be 403.
ok=0
for _ in $(seq 1 25); do
  old_code="$(curl --silent --max-time 4 --cacert "$work/server-ca.crt" \
    --cert "$work/imagine-operator-ci.crt" --key "$work/imagine-operator-ci.key" \
    --output /dev/null --write-out "%{http_code}" \
    https://localhost:18744/ 2>/dev/null || true)"
  rotated_code="$(curl --silent --max-time 4 --cacert "$work/server-ca.crt" \
    --cert "$work/same-cn-operator.crt" --key "$work/same-cn-operator.key" \
    --output /dev/null --write-out "%{http_code}" \
    https://localhost:18744/ 2>/dev/null || true)"
  if [[ "$old_code" = 403 ]] && [[ "$rotated_code" = 200 || "$rotated_code" = 301 || "$rotated_code" = 302 || "$rotated_code" = 303 ]]; then
    ok=1;break
  fi
  sleep 0.25
done
test "$ok" = 1 || { echo "P17_ROTATED_CERT_NOT_ENFORCED" >&2;exit 1; }
echo "CENTRAL_AUTO_P17_LIVE_CERT_ROTATION_PASS old_cert_denied=1 new_cert_allowed=1 same_cn=1 same_ca=1 nginx_reload=1 mfa=NO NO_GO"

# The original n8n and DB still have NO host-published ports.
python3 - "$("${dc[@]}" ps -q n8n)" "$("${dc[@]}" ps -q n8n-db)" <<'PY'
import json,subprocess,sys
for name in sys.argv[1:]:
    assert name
    obj=json.loads(subprocess.check_output(["docker","inspect",name]))[0]
    assert not obj["HostConfig"]["PortBindings"]
    assert not obj["HostConfig"]["Privileged"]
print("CENTRAL_AUTO_P15_PRIVATE_BACKEND_NO_PORTS_PASS")
PY
echo "CENTRAL_AUTO_P16_EXACT_CERT_PIN_PASS valid_client=1 missing_cert_denied=1 wrong_cn_denied=1 same_cn_different_cert_denied=1 rogue_ca_denied=1 loopback_only=1 mfa=NO NO_GO"
