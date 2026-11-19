#!/usr/bin/env bash
# Issue temporary certificates for the proposed staging topology and check pins.
set -euo pipefail
umask 077
validator="$(cd "$(dirname "$0")/.." && pwd)/material_preflight.py"
work="$(mktemp -d)"
trap 'rm -rf -- "$work"' EXIT
cd "$work"
openssl req -x509 -newkey rsa:2048 -nodes -keyout ca.key -out ca.crt -days 3 \
  -subj /CN=Imagine-synthetic-staging-CA > /dev/null 2>&1
issue() {
  local hostname="$1" eku="$2"
  openssl req -newkey rsa:2048 -nodes -keyout "$hostname.key" -out "$hostname.csr" \
    -subj "/CN=$hostname" > /dev/null 2>&1
  printf 'extendedKeyUsage=%s\nsubjectAltName=DNS:%s\n' "$eku" "$hostname" > "$hostname.ext"
  openssl x509 -req -in "$hostname.csr" -CA ca.crt -CAkey ca.key -CAcreateserial \
    -out "$hostname.crt" -days 2 -extfile "$hostname.ext" > /dev/null 2>&1
}
verify() {
  local hostname="$1"
  shift
  local pin
  pin="$(openssl x509 -in "$hostname.crt" -outform DER | sha256sum | cut -d ' ' -f 1)"
  python3 "$validator" --ca ca.crt --certificate "$hostname.crt" \
    --private-key "$hostname.key" --expected-fingerprint "$pin" "$@"
}
editor=n8n-hml.imagine.invalid
issue "$editor" serverAuth
verify "$editor" --purpose sslserver --hostname "$editor"
for service in portal crm agents; do
  issue "$service-hml.imagine.invalid" clientAuth
  verify "$service-hml.imagine.invalid" --purpose sslclient
done
if verify "$editor" --purpose sslserver --hostname crm-hml.imagine.invalid; then
  echo 'unexpected hostname accepted' >&2
  exit 1
fi
touch index.txt
cat > ca.conf <<'CONF'
[ca]
default_ca=staging
[staging]
database=index.txt
certificate=ca.crt
private_key=ca.key
default_md=sha256
default_crl_days=1
CONF
openssl ca -config ca.conf -revoke portal-hml.imagine.invalid.crt >/dev/null 2>&1
openssl ca -config ca.conf -gencrl -out staging.crl >/dev/null 2>&1
openssl verify -CAfile ca.crt -CRLfile staging.crl -crl_check crm-hml.imagine.invalid.crt
if openssl verify -CAfile ca.crt -CRLfile staging.crl -crl_check portal-hml.imagine.invalid.crt > verify.log 2>&1; then
  echo 'unexpected revoked certificate accepted' >&2
  exit 1
fi
grep -q 'certificate revoked' verify.log
echo 'revoked staging certificate rejected by CRL'
echo 'synthetic staging identities and editor hostname validated'
