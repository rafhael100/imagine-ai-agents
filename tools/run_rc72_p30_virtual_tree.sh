#!/usr/bin/env bash
# Execute ingress, recovery and native operator authentication in one candidate.
# Only Git objects are created locally: no merge commit or branch/ref update.
set -euo pipefail
umask 077

[[ "${GITHUB_ACTIONS:-}" == true ]] || { echo 'RC72_CI_ONLY'; exit 2; }
[[ "${GITHUB_REPOSITORY:-}" == rafhael100/imagine-ai-agents ]] || { echo 'RC72_WRONG_REPO'; exit 2; }
[[ "${GITHUB_EVENT_NAME:-}" == push ]] || { echo 'RC72_PUSH_SHA_REQUIRED'; exit 2; }
[[ "${GITHUB_SHA:-}" =~ ^[a-f0-9]{40}$ ]] || { echo 'RC72_SOURCE_SHA_REQUIRED'; exit 2; }
[[ -n "${RUNNER_TEMP:-}" && -d "$RUNNER_TEMP" && ! -L "$RUNNER_TEMP" ]] || { echo 'RC72_RUNNER_REQUIRED'; exit 2; }

repo=$(git rev-parse --show-toplevel)
cd "$repo"
central_sha=$(git rev-parse HEAD)
agents_sha=3fb86c5fb011bc9df1f12a4a30a7c094370b6872
central_base_sha=1838e7e13667c2f2c33d19b5b3eb469637a7c431
agents="$repo/_agents_rc65_pinned"

test "$central_sha" = "$GITHUB_SHA"
git diff --quiet
git diff --cached --quiet
git merge-base --is-ancestor "$central_base_sha" "$central_sha"
test "$(git -C "$agents" rev-parse HEAD)" = "$agents_sha"
test "$(git -C "$agents" rev-parse --is-shallow-repository)" = false
# Transfer only objects from the independent local checkout, not a network URL.
# Suppress FETCH_HEAD writing as well as remote/tracking branch updates.
git fetch --quiet --no-tags --no-write-fetch-head "$agents" "$agents_sha"

if ! virtual_merge=$(git merge-tree --write-tree --name-only "$central_sha" "$agents_sha"); then
  echo 'RC72_MERGE_TREE_CONFLICT_REQUIRES_REVIEW_NO_GO'
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
  test "$(git rev-parse "$agents_sha:$file")" = "$(git rev-parse "$tree:$file")"
done

root=$(mktemp -d "$RUNNER_TEMP/aia-rc72-p30-tree-XXXXXX")
cleanup() {
  case "$root" in
    "$RUNNER_TEMP"/aia-rc72-p30-tree-*) rm -rf -- "$root" ;;
    *) echo 'RC72_CLEANUP_PATH_DENIED' >&2 ;;
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
printf 'RC72_SOURCE central=%s agents=%s tree=%s\n' "$central_sha" "$agents_sha" "$tree"

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
)

cat <<'REPORT'
AIA_RC72_P30_COMBINED_TREE_PASS
agents_release=NO_GO
central_runtime_dispatch=DISABLED
real_owner_identity=false
live_crm_agents_exchange=false
external_backup_verified=false
merge_commit_created=false
remote_branch_updated=false
paid_ai_calls=0
go_for_installation=false
REPORT

if [[ -n "${GITHUB_STEP_SUMMARY:-}" ]]; then
  {
    echo '### AIA RC72 / Central P30 — same temporary source tree'
    echo
    printf -- '- Central candidate commit: `%s`\n' "$central_sha"
    printf -- '- Pinned Agents RC65 commit: `%s`\n' "$agents_sha"
    printf -- '- Virtual combined tree: `%s`\n' "$tree"
    echo '- P20/P22/P23/P24 safety regressions ran on the combined tree.'
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
