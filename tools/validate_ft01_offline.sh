#!/usr/bin/env bash
# Zero-network validator for a complete Git checkout of Imagine AI Agents FT01.
set -euo pipefail
cd "$(dirname "${BASH_SOURCE[0]}")/.."

if ! command -v php >/dev/null 2>&1; then
  echo 'BLOCKED: PHP CLI is missing; no installation attempted' >&2
  exit 2
fi
if (( $(php -r 'echo PHP_VERSION_ID;') < 80100 )); then
  echo 'BLOCKED: PHP 8.1+ is required' >&2
  exit 2
fi
if ! git rev-parse --is-inside-work-tree >/dev/null 2>&1; then
  echo 'BLOCKED: a complete Git checkout is required; no network fetch attempted' >&2
  exit 2
fi
head_sha="$(git rev-parse HEAD)"
if [[ -z "${EXPECTED_SHA:-}" ]]; then
  echo 'BLOCKED: set EXPECTED_SHA to the exact reviewed GitHub commit SHA' >&2
  exit 2
fi
if [[ "$head_sha" != "$EXPECTED_SHA" ]]; then
  echo "BLOCKED: checkout SHA mismatch (got $head_sha)" >&2
  exit 2
fi
for required in \
  src/MockOrchestration/SupervisedCoordinator.php \
  src/MockTooling/MockCapabilityGrant.php \
  src/MockUsage/MockUsageLedger.php \
  src/MockKnowledge/MockKnowledgeSource.php \
  tests/Unit/supervised.php \
  tests/Unit/catalog_release.php \
  tests/Unit/knowledge_context.php \
  src/MockEvaluation/MockEvaluationComparator.php \
  src/MockReview/MockReviewBoard.php \
  src/MockReview/MockReviewPacket.php \
  tests/Unit/evaluation_compare.php \
  tests/Unit/review_handoff.php \
  examples/f110-review-demo.php \
  src/MockTrust/MockTrustEnvelope.php \
  src/MockTrust/MockOwnerScopeEvidence.php \
  src/MockTrust/MockTrustGateway.php \
  tests/Unit/trust_contract.php \
  examples/f111-trust-demo.php \
  src/MockBridge/MockCrmReadRequest.php \
  src/MockBridge/MockCrmReadBoundary.php \
  src/MockBridge/MockReadProjectionInvariant.php \
  tests/Unit/crm_read_contract.php \
  src/MockAudit/MockCrmReadAuditTrail.php \
  src/MockAudit/MockCrmReadAuditedBoundary.php \
  tests/Unit/crm_read_audit.php \
  src/MockTrust/MockSharedTrustState.php \
  tests/Unit/shared_trust_state.php \
  tests/Unit/op01_conformance.php \
  src/MockBridge/MockCrmOwnerPolicy.php \
  tests/Unit/owner_policy_recheck.php \
  src/MockBridge/MockCrmReadPublicFacade.php \
  tests/Unit/public_read_facade.php \
  src/MockTelemetry/MockCrmReadEdgeCounters.php \
  src/MockTelemetry/MockCrmReadEdgeBoundary.php \
  tests/Unit/edge_telemetry.php \
  contracts/fixtures/f115-crm-op01-conformance.synthetic.json \
  contracts/crm-aia-readonly.v1.synthetic.schema.json \
  tests/Contracts/test_crm_aia_readonly_schema.py \
  tests/Unit/context_ref_strict.php \
  tests/Unit/intent_binding.php \
  tests/Unit/trace_capacity.php \
  tests/Unit/run_projection.php \
  tests/Unit/run_identity.php \
  tests/Unit/status_outage_trace.php \
  src/MockPilot/MockReadOnlyPilot.php \
  examples/f127-read-only-pilot-demo.php \
  tests/Unit/read_only_pilot.php \
  tests/Unit/run.php; do
  if [[ ! -f "$required" ]]; then
    echo "BLOCKED: incomplete checkout, missing $required" >&2
    exit 2
  fi
done

expected_php="${EXPECTED_PHP:-83}"
expected_cases="${EXPECTED_CASES:-597}"
[[ "$expected_php" =~ ^[0-9]+$ && "$expected_cases" =~ ^[0-9]+$ ]] || {
  echo 'BLOCKED: expected counts must be integers' >&2
  exit 2
}
count=0
while IFS= read -r -d '' file; do
  if ! php -l "$file" >/dev/null; then
    echo "FAIL: PHP lint failed: $file" >&2
    exit 1
  fi
  count=$((count + 1))
done < <(find src tests/Unit examples -type f -name '*.php' -print0)
if (( count != expected_php )); then
  echo "BLOCKED: expected $expected_php PHP files but found $count" >&2
  exit 2
fi

set +e
output="$(php tests/Unit/run.php 2>&1)"
code=$?
set -e
printf '%s\n' "$output"
if (( code != 0 )) || ! grep -qx "$expected_cases cases, 0 failures" <<< "$output"; then
  echo "FAIL: official regression was not $expected_cases/0 (exit=$code)" >&2
  exit 1
fi
printf 'VALIDATION_PASS sha=%s php_lint=%s official_tests=%s\n' "$head_sha" "$count" "$expected_cases"
