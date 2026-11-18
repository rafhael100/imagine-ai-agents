#!/usr/bin/env bash
# Execute P65 and every RC75 regression in one temporary combined tree.
# Only Git objects are created locally: no merge commit or branch/ref update.
set -euo pipefail
umask 077

[[ "${GITHUB_ACTIONS:-}" == true ]] || { echo 'RC76_CI_ONLY'; exit 2; }
[[ "${GITHUB_REPOSITORY:-}" == rafhael100/imagine-ai-agents ]] || { echo 'RC76_WRONG_REPO'; exit 2; }
[[ "${GITHUB_EVENT_NAME:-}" == pull_request ]] || { echo 'RC76_PULL_REQUEST_REQUIRED'; exit 2; }
[[ "${P24_RUNNER_ENVIRONMENT:-}" == github-hosted ]] || { echo 'RC76_GITHUB_HOSTED_REQUIRED'; exit 2; }
[[ "${GITHUB_SHA:-}" =~ ^[a-f0-9]{40}$ ]] || { echo 'RC76_EVENT_SHA_REQUIRED'; exit 2; }
[[ -n "${RUNNER_TEMP:-}" && -d "$RUNNER_TEMP" && ! -L "$RUNNER_TEMP" ]] || { echo 'RC76_RUNNER_REQUIRED'; exit 2; }
preflight_only=false
if [[ $# -eq 1 && "$1" == --preflight ]]; then
  preflight_only=true
elif [[ $# -ne 0 ]]; then
  echo 'RC76_ARGUMENTS_DENIED'; exit 2
fi

# Read the real pull_request event; GITHUB_SHA remains GitHub's merge ref SHA.
# Neither that variable nor GITHUB_EVENT_NAME is rewritten for the older gates.
expected_head=$(python3 - <<'PY_RC76_EVENT'
import json, os, re
from pathlib import Path

def require(condition):
    if not condition:
        raise SystemExit('RC76_EVENT_PROVENANCE_DENIED')

path=Path(os.environ.get('GITHUB_EVENT_PATH',''))
require(path.is_file() and not path.is_symlink() and path.stat().st_size<=1048576)
try:
    event=json.loads(path.read_text())
    repository=event['repository']
    request=event['pull_request']
    base=request['base']; head=request['head']
    expected='rafhael100/imagine-ai-agents'
    require(repository['full_name']==expected and repository['private'] is False)
    require(base['repo']['full_name']==expected and head['repo']['full_name']==expected)
    require(base['ref']=='feat/aia-p65-crm-minimal-read-consumer-2026-11-17')
    require(base['sha']=='740abafc813dbde70f5a2ad6e138402ed7945276')
    require(head['ref']=='test/aia-rc76-op01-combined-evidence-2026-11-17')
    require(re.fullmatch('[a-f0-9]{40}',head['sha']) is not None)
except (KeyError, TypeError, ValueError, OSError):
    raise SystemExit('RC76_EVENT_PROVENANCE_DENIED')
print(head['sha'])
PY_RC76_EVENT
)

repo=$(git rev-parse --show-toplevel)
cd "$repo"
agents_sha=$(git rev-parse HEAD)
agents_base_sha=6b65a109799df5f34e6431474ba8f3e58cb0fccc
p65_sha=740abafc813dbde70f5a2ad6e138402ed7945276
# Exact Central P30 candidate; its checks and this combined gate are separate.
central_sha=c08688b16e61d8d1ecc4d8c0ed4da8758a687ee8
central="$repo/_central_p30_pinned"

test "$agents_sha" = "$expected_head"
git diff --quiet
git diff --cached --quiet
git merge-base --is-ancestor "$agents_base_sha" "$agents_sha"
git merge-base --is-ancestor "$p65_sha" "$agents_sha"
# This evidence-only increment may add exactly its runner, workflow and record.
# Every preexisting P65/P64 file must remain byte-for-byte unchanged.
python3 - "$p65_sha" "$agents_sha" <<'PY_RC76_OVERLAY'
import subprocess, sys
expected={
    ('A','tools/run_rc76_p65_virtual_tree.sh'),
    ('A','.github/workflows/aia-rc76-p65-combined.yml'),
    ('A','docs/reviews/AIA-RC76-P65-COMBINED-EVIDENCE.md'),
}
result=subprocess.run(['git','diff','--name-status','--no-renames',sys.argv[1],sys.argv[2]],
                      check=True,capture_output=True,text=True)
actual={tuple(line.split('\t')) for line in result.stdout.splitlines()}
if actual!=expected:
    raise SystemExit('RC76_SOURCE_OVERLAY_DENIED')
PY_RC76_OVERLAY
test "$(git -C "$central" rev-parse HEAD)" = "$central_sha"
test "$(git -C "$central" rev-parse --is-shallow-repository)" = false
git -C "$central" diff --quiet
git -C "$central" diff --cached --quiet
git fetch --quiet --no-tags --no-write-fetch-head "$central" "$central_sha"

if ! virtual_merge=$(git merge-tree --write-tree --name-only "$central_sha" "$agents_sha"); then
  echo 'RC76_MERGE_TREE_CONFLICT_REQUIRES_REVIEW_NO_GO'
  exit 2
fi
tree=$(printf '%s\n' "$virtual_merge" | sed -n '1p')
[[ "$tree" =~ ^[a-f0-9]{40}$ ]]
test "$(git cat-file -t "$tree")" = tree

# Reject an accidental downgrade to a previous Central candidate.
# The exact Central implementation and regressions must survive the virtual merge.
for file in \
  infra/n8n-private/homologation/seal_backup.py \
  infra/n8n-private/homologation/signed_receipt_p23.py \
  infra/n8n-private/homologation/test_p24_receipt_safety.py \
  infra/n8n-private/homologation/check_p24_full_n8n_restore.py \
  infra/n8n-private/homologation/check_p25_agents_state_restore.py \
  infra/n8n-private/homologation/p25_agents_recovery.py \
  runtime/central-auto/p25-recovery-probe.php \
  runtime/central-auto/p25-recovery-roles.sql \
  infra/n8n-private/homologation/check_p26_native_outbox_execution.py \
  infra/n8n-private/workflows/native-run-status-noai.workflow.json \
  runtime/central-auto/lab-executor/native_contract.py \
  runtime/central-auto/lab-executor/PgNativeExecutionLedgerLab.php \
  runtime/central-auto/lab-executor/probe.php \
  runtime/central-auto/lab-executor/test_native_contract.py \
  runtime/central-auto/lab-executor/schema.sql \
  runtime/central-auto/event-status.php \
  runtime/central-auto/receipt-status.sql \
  runtime/central-auto/p27-receipt-probe.php \
  runtime/central-auto/nginx.conf \
  runtime/central-auto/Dockerfile \
  infra/n8n-private/homologation/check_p27_private_receipt_status.py \
  infra/n8n-private/homologation/check_p28_candidate_state_restore.py \
  runtime/central-auto/p28-state-probe.php \
  runtime/central-auto/p28-recovery-roles.sql \
  runtime/central-auto/p28-constraint-compare.php \
  runtime/central-auto/p28-constraint-compare-test.php; do
  test "$(git rev-parse "$central_sha:$file")" = "$(git rev-parse "$tree:$file")"
done

# Reviewed ingress implementation and regressions must survive the merge tree.
for file in \
  runtime/central-auto/index.php \
  runtime/central-auto/strict-json.php \
  runtime/central-auto/receiver-grants.sql \
  runtime/central-auto/p29-json-probe.php \
  infra/n8n-private/homologation/check_p29_ingress_boundaries.py \
  infra/n8n-private/homologation/check_p30_native_operator_mfa.py; do
  test "$(git rev-parse "$central_sha:$file")" = "$(git rev-parse "$tree:$file")"
done

# P62 changes the reviewed composition and existing page explicitly.
for file in \
  tools/labs/PgReadOnlySnapshotLab.php \
  tools/labs/PgSignedReviewQueueBridgeLab.php \
  tools/labs/PgScopedReviewQueueBridgeLab.php \
  tools/labs/PgSignedDraftReviewLab.php \
  tools/labs/PgHistoricalSignedReviewAuditLab.php \
  examples/p56-live-pg-review-local.php \
  tools/check_p62_live_signed_review.py \
  tools/p62-signed-review-probe.php; do
  test "$(git rev-parse "$agents_sha:$file")" = "$(git rev-parse "$tree:$file")"
done

# The new persisted inputs and detail reader must survive the virtual merge.
for file in \
  tools/labs/PgDeterministicDraftRegistryLab.php \
  tools/labs/PgVerifiableProposalDetailLab.php \
  tools/p63-proposal-detail-probe.php \
  tools/check_p63_verifiable_proposal_detail.py; do
  test "$(git rev-parse "$agents_sha:$file")" = "$(git rev-parse "$tree:$file")"
done

# The new scoped writer and atomic creation journey must survive the merge.
for file in \
  tools/labs/PgScopedDraftWriterLab.php \
  tools/labs/PgAuthoredDraftStagingLab.php \
  tools/labs/PgDraftAuthorProvenanceLab.php \
  examples/p64-create-drafts-local.php \
  tools/p64-authored-draft-probe.php \
  tools/check_p64_authored_draft_journey.py; do
  test "$(git rev-parse "$agents_sha:$file")" = "$(git rev-parse "$tree:$file")"
done

# Pin the original Agents domain code and its corresponding tests unchanged.
for file in \
  src/Automation/DeterministicAutomationService.php \
  src/Automation/DeterministicRuleEngine.php \
  src/Automation/ReviewQueueReadModel.php \
  src/Automation/SignedReviewQueueReadModel.php \
  tools/labs/PgN8nOutboxLab.php \
  tools/labs/PgSyntheticReplayGate.php \
  tools/labs/PgBoundOutboxLeaseLab.php \
  tools/labs/PgBoundOutboxAckLab.php \
  tools/labs/PgTenantAuthorityLab.php \
  tools/labs/PgOwnerBoundSignedDispatchLab.php \
  tools/labs/PgOwnerExecutionGuardLab.php \
  tools/labs/PgCompanyDispatchGuardLab.php \
  tools/labs/PgSyntheticAckEvidenceLab.php \
  tools/labs/MockContentBoundExecutorReceiptV2.php \
  tools/check_p48_deterministic_noai.php \
  tools/check_p49_deterministic_rules.php \
  tools/check_p50_offline_cli.py \
  tools/preview_noai_automation.php \
  tools/check_p53_review_queue_view.php \
  tools/check_p60_signed_review_queue.php; do
  test "$(git rev-parse "$agents_base_sha:$file")" = "$(git rev-parse "$agents_sha:$file")"
  test "$(git rev-parse "$agents_sha:$file")" = "$(git rev-parse "$tree:$file")"
done

# Literal P65 overlay and its preceding combined gate survive the merge tree.
for file in \
  src/Integration/Crm/MinimalLeadProjection.php \
  tools/check_p65_crm_minimal_read.php \
  tools/p65-crm-minimal-read-probe.php \
  .github/workflows/aia-p65-crm-minimal-read.yml \
  docs/reviews/AIA-P65-CRM-MINIMAL-READ-CONSUMER.md \
  tools/run_rc75_p64_virtual_tree.sh \
  .github/workflows/aia-p64-local-draft-creation.yml \
  docs/reviews/AIA-P64-LOCAL-AUTHORED-DRAFT-JOURNEY.md; do
  test "$(git rev-parse "$p65_sha:$file")" = "$(git rev-parse "$agents_sha:$file")"
  test "$(git rev-parse "$agents_sha:$file")" = "$(git rev-parse "$tree:$file")"
done

printf 'RC76_PREFLIGHT central=%s p65=%s agents=%s tree=%s event_merge_sha=%s\n' \
  "$central_sha" "$p65_sha" "$agents_sha" "$tree" "$GITHUB_SHA"
if [[ "$preflight_only" == true ]]; then
  echo 'AIA_RC76_PREFLIGHT_PASS runtime_checks_executed=false'
  exit 0
fi

root=$(mktemp -d "$RUNNER_TEMP/aia-rc76-p65-tree-XXXXXX")
cleanup() {
  case "$root" in
    "$RUNNER_TEMP"/aia-rc76-p65-tree-*) rm -rf -- "$root" ;;
    *) echo 'RC76_CLEANUP_PATH_DENIED' >&2 ;;
  esac
}
trap cleanup EXIT
git archive "$tree" | tar --extract --file=- --directory="$root" --no-same-owner --no-same-permissions
# The public bootstrap source is mounted as one file and sourced by postgres,
# whose uid differs from the runner's. The private umask above must not make
# this non-secret init script unreadable inside that container. No secret or
# other checkout path receives wider permissions.
bootstrap="$root/infra/n8n-private/10-runtime-role.sh"
test -f "$bootstrap" && test ! -L "$bootstrap"
chmod 0644 "$bootstrap"
# This archived public source is mounted read-only into the PHP container.
# The pinned replay adapter is historical source, not a restored backup input.
adapter_sha=8b04d0e936168c77db028160b5aeb1758db497c4
git merge-base --is-ancestor "$adapter_sha" "$central_sha"
mkdir "$root/_auto_ia_pinned_source"
git archive "$adapter_sha" | tar --extract --file=- --directory="$root/_auto_ia_pinned_source" --no-same-owner --no-same-permissions
chmod -R a+rX "$root"

# The exported tree intentionally has no .git directory. Pass provenance as
# metadata; do not create a synthetic commit or mislabel the original P23 SHA.
export AIA_P24_SOURCE_SHA="$central_sha"
export AIA_RC66_AGENTS_SHA="$agents_sha"
export AIA_RC66_VIRTUAL_TREE_SHA="$tree"
export PYTHONDONTWRITEBYTECODE=1
printf 'RC76_SOURCE central=%s agents=%s tree=%s\n' "$central_sha" "$agents_sha" "$tree"

(
  cd "$root"
  php -l runtime/central-auto/index.php
  php -l runtime/central-auto/strict-json.php
  php -l runtime/central-auto/p29-json-probe.php
  php -l runtime/central-auto/p25-recovery-probe.php
  php -l runtime/central-auto/event-status.php
  php -l runtime/central-auto/p27-receipt-probe.php
  php -l runtime/central-auto/p28-state-probe.php
  php -l runtime/central-auto/p28-constraint-compare.php
  php -l runtime/central-auto/p28-constraint-compare-test.php
  for file in runtime/central-auto/lab-executor/*.php; do php -l "$file"; done
  php -l src/Automation/DeterministicAutomationService.php
  php -l src/Automation/DeterministicRuleEngine.php
  php -l src/Automation/ReviewQueueReadModel.php
  php -l src/Automation/SignedReviewQueueReadModel.php
  python3 -m unittest discover -s infra/n8n-private/homologation -p 'test_p2*.py' -v
  python3 runtime/central-auto/lab-executor/test_native_contract.py
  php tools/check_p48_deterministic_noai.php
  php tools/check_p49_deterministic_rules.php
  python3 tools/check_p50_offline_cli.py
  php tools/check_p53_review_queue_view.php
  php tools/check_p60_signed_review_queue.php
  # Run n8n and actual Agents state recovery through one signed package.
  python3 infra/n8n-private/homologation/check_p25_agents_state_restore.py
  # P28 runs P26 once, then restores that actual state without new execution.
  python3 infra/n8n-private/homologation/check_p28_candidate_state_restore.py
  # P29 retains the complete P27 matrix and adds signed ingress negatives.
  python3 infra/n8n-private/homologation/check_p29_ingress_boundaries.py
  python3 infra/n8n-private/homologation/check_p30_native_operator_mfa.py
  php -l tools/labs/PgReadOnlySnapshotLab.php
  php -l tools/labs/PgSignedReviewQueueBridgeLab.php
  php -l tools/labs/PgScopedReviewQueueBridgeLab.php
  php -l tools/labs/PgSignedDraftReviewLab.php
  php -l tools/labs/PgHistoricalSignedReviewAuditLab.php
  php -l tools/p62-signed-review-probe.php
  php -l examples/p56-live-pg-review-local.php
  python3 tools/check_p62_live_signed_review.py
  php -l tools/labs/PgDeterministicDraftRegistryLab.php
  php -l tools/labs/PgVerifiableProposalDetailLab.php
  php -l tools/p63-proposal-detail-probe.php
  python3 tools/check_p63_verifiable_proposal_detail.py
  php -l tools/labs/PgScopedDraftWriterLab.php
  php -l tools/labs/PgAuthoredDraftStagingLab.php
  php -l tools/labs/PgDraftAuthorProvenanceLab.php
  php -l examples/p64-create-drafts-local.php
  php -l tools/p64-authored-draft-probe.php
  python3 tools/check_p64_authored_draft_journey.py
  # Execute the original P65 consumer from this same exported combined tree.
  php -l src/Integration/Crm/MinimalLeadProjection.php
  php -l tools/check_p65_crm_minimal_read.php
  php -l tools/p65-crm-minimal-read-probe.php
  php tools/check_p65_crm_minimal_read.php
  resource=10000000-0000-4000-8000-000000000001
  payload='{"contract":"CRM_AIA_HOMOLOGATION_LEAD_READ_V1","operation":"crm.lead.read_minimal","status":"READ","scope":"SYNTHETIC_PRIVATE_HOMOLOGATION","projection":{"ref":"10000000-0000-4000-8000-000000000001","stage":"new"},"operational":false}'
  accepted=$(printf '%s' "$payload" | php tools/p65-crm-minimal-read-probe.php --status 200 --expected-ref "$resource")
  test "$accepted" = AIA_P65_CRM_MINIMAL_READ_ACCEPTED
  if denied=$(printf '%s' "$payload" | php tools/p65-crm-minimal-read-probe.php --status 404 --expected-ref "$resource"); then
    echo 'RC76_P65_NON_200_ACCEPTED' >&2
    exit 2
  else
    status=$?
    test "$status" -eq 2
    test "$denied" = AIA_P65_CRM_MINIMAL_READ_DENIED
  fi
  echo 'AIA_RC76_P65_ORIGINAL_PROBE_PASS'
)

cat <<'REPORT'
AIA_RC76_P65_COMBINED_TREE_PASS
agents_release=NO_GO
central_runtime_dispatch=DISABLED
real_owner_identity=false
p65_contract_verified_in_combined_tree=true
crm_authority_runtime_verified_in_this_job=false
service_delegation_verified=false
external_backup_verified=false
merge_commit_created=false
remote_branch_updated=false
paid_ai_calls=0
go_for_installation=false
REPORT

if [[ -n "${GITHUB_STEP_SUMMARY:-}" ]]; then
  {
    echo '### AIA P65 / RC76 — minimal projection and complete pinned candidate'
    echo
    printf -- '- Central candidate commit: `%s`\n' "$central_sha"
    printf -- '- Agents RC76 candidate commit: `%s`\n' "$agents_sha"
    printf -- '- Virtual combined tree: `%s`\n' "$tree"
    echo '- P20/P22/P23/P24 safety regressions ran on the combined tree.'
    echo '- P62 composes current signed reviews and historical evidence from one tenant-scoped read-only snapshot.'
    echo '- P63 persists canonical inputs and verifies proposal detail by reevaluating the original deterministic engine in that snapshot.'
    echo '- P64 previews server-validated synthetic inputs and stores drafts with authorship in one transaction using a restricted writer.'
    printf -- '- Literal P65 source: `%s`\n' "$p65_sha"
    echo '- Original P65 closed-contract suite and accepted/denied CLI probes ran in the same exported tree.'
    echo '- CRM HTTP session/authority evidence remains in the separate CRM workflow; this job does not recreate it.'
    echo '- Original Agents P48/P49/P50/P53/P60 suites passed; receiver PHP syntax checked.'
    echo '- P25 recovered actual Agents state and complete synthetic n8n from the same signed package.'
    echo '- P26 executes a native n8n projection from the existing owner-bound outbox and reconciles its result.'
    echo '- P27 validates signed receipt lookup over private HTTPS with synthetic mTLS and current independent grants.'
    echo '- P28 restores actual native results, ACK/evidence and receipt grants from the signed package without repeating work.'
    echo '- P29 rejects ambiguous JSON and malformed references, and rolls back admission that expires while waiting.'
    echo '- P30 exercises native enrollment, second-factor login, recovery codes and session revocation in the pinned n8n image.'
    echo '- MFA enforcement and individual enrollment are reported independently; no license entitlement is fabricated.'
    echo '- No remote merge, real S2S authority, external backup, paid AI or installation approval.'
  } >> "$GITHUB_STEP_SUMMARY"
fi
