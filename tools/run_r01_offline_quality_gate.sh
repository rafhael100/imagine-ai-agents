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
  test_f183_supervision_tab.py \
  test_f184_review_sandbox.py \
  test_f185_agent_draft_workbench.py \
  test_f186_draft_collection.py \
  test_f187_draft_transfer.py \
  test_f190_queue_pause_demo.py \
  test_f191_rehearsal_verifier.py \
  test_f193_supervision_report.py \
  test_f194_visual_baseline.py \
  test_f195_transition_delta.py \
  test_f196_transition_tab.py \
  test_f197_visual_delta.py \
  test_f198_tamper_gate.py \
  test_f199_agent_scope_preview.py \
  test_f201_triage_rehearsal.py \
  test_f202_triage_tab.py \
  test_f203_triage_adversarial.py \
  test_f206_health_rehearsal.py \
  test_f208_crm_cross_source.py \
  test_f209_op01_source_matrix.py \
  test_ci_guardrails.py \
  test_op01_readiness_dashboard.py \
  test_op01_dashboard_generator.py \
  test_synthetic_php_isolation.py \
  test_quality_gate_coverage.py \
  test_canonical_project_status.py \
  test_evid44_postmerge.py \
  test_evid45_postmerge.py \
  test_evid46_postmerge.py \
  test_evid47_postmerge.py \
  test_evid48_postmerge.py
do
  python3 -m unittest discover -s tests/Contracts -p "$name" -v
done

# Run the original dependency-free DOM test when Node is already available.
# The mandatory Python F183 parity suite still runs where Node is absent.
if command -v node >/dev/null 2>&1; then
  node tests/Browser/f108_smoke.cjs
  node tests/Browser/f196_tamper_smoke.cjs
  node tests/Browser/f202_triage_tamper_smoke.cjs
else
  echo 'NODE_VISUAL_SMOKE_SKIPPED: Node not installed (no install attempted)'
fi

python3 tools/verify_f190_rehearsal.py
python3 tools/verify_f201_triage.py
python3 tools/verify_f206_health.py
python3 tools/render_f206_health.py >/dev/null
# Inert HTML rendering must succeed; the preview is not persisted.
python3 tools/render_f190_supervision.py >/dev/null

php -l tools/op01_php_parser_probe.php
php -l tools/op01_php_public_response_probe.php
php -l tools/check_synthetic_php_isolation.php
php tools/check_synthetic_php_isolation.php
bash tools/validate_ft01_offline.sh

echo 'R01_OFFLINE_GATE_PASS operational_decision=NO_GO'
