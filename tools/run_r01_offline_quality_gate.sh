#!/usr/bin/env bash
# Complete, zero-network quality gate for a reviewed Imagine AI Agents checkout.
# This script does not permit CRM access, secrets, deployment or production use.
set -euo pipefail
cd "$(dirname "$0")/.."

if ! command -v php >/dev/null 2>&1 || ! command -v python3 >/dev/null 2>&1; then
  echo "BLOCKED: PHP and Python3 must already exist (no installation attempted)" >&2
  exit 2
fi
if [[ -z "$(printenv EXPECTED_SHA 2>/dev/null)" ]]; then
  echo "BLOCKED: EXPECTED_SHA must match the exact reviewed checkout commit" >&2
  exit 2
fi

echo 'R01_OFFLINE_GATE_START operational_decision=NO_GO'
python3 tools/check_r01_readiness.py
python3 tools/check_op01_contract_parity.py
python3 tools/generate_op01_bilateral_packet.py --check
python3 tools/check_op01_public_response_parity.py
python3 tools/generate_op01_dashboard.py --check
python3 tools/check_op01_readiness_dashboard.py

for name in \
  test_r01_readiness.py \
  test_op01_contract_parity.py \
  test_op01_parser_mutation_parity.py \
  test_op01_bilateral_packet.py \
  test_op01_contract_matrix.py \
  test_op01_public_response_parity.py \
  test_f127_offline_pilot_demo.py \
  test_f180_queued_batch_demo.py \
  test_f181_queue_observation_demo.py \
  test_f182_queue_supervision_demo.py \
  test_ci_guardrails.py \
  test_op01_readiness_dashboard.py \
  test_op01_dashboard_generator.py \
  test_synthetic_php_isolation.py \
  test_quality_gate_coverage.py \
  test_canonical_project_status.py
do
  python3 -m unittest discover -s tests/Contracts -p "$name" -v
done

php -l tools/op01_php_parser_probe.php
php -l tools/op01_php_public_response_probe.php
php -l tools/check_synthetic_php_isolation.php
php tools/check_synthetic_php_isolation.php
bash tools/validate_ft01_offline.sh

echo 'R01_OFFLINE_GATE_PASS operational_decision=NO_GO'
