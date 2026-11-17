#!/usr/bin/env bash
# Execute complete recovery, private receipt and ingress regressions together.
# Only Git objects are created locally: no merge commit or branch/ref update.
set -euo pipefail
umask 077

[[ "${GITHUB_ACTIONS:-}" == true ]] || { echo 'P29_PROBE_CI_ONLY'; exit 2; }
[[ "${GITHUB_REPOSITORY:-}" == rafhael100/imagine-ai-agents ]] || { echo 'P29_PROBE_WRONG_REPO'; exit 2; }
[[ "${GITHUB_EVENT_NAME:-}" == push ]] || { echo 'P29_PROBE_PUSH_SHA_REQUIRED'; exit 2; }
[[ "${GITHUB_SHA:-}" =~ ^[a-f0-9]{40}$ ]] || { echo 'P29_PROBE_SOURCE_SHA_REQUIRED'; exit 2; }
[[ -n "${RUNNER_TEMP:-}" && -d "$RUNNER_TEMP" && ! -L "$RUNNER_TEMP" ]] || { echo 'P29_PROBE_RUNNER_REQUIRED'; exit 2; }

repo=$(git rev-parse --show-toplevel)
cd "$repo"
central_sha=$(git rev-parse HEAD)
agents_sha=3fb86c5fb011bc9df1f12a4a30a7c094370b6872
central_base_sha=096023ebe41e842b05ceeb6998288955110c3504
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
  echo 'P29_PROBE_MERGE_TREE_CONFLICT_REQUIRES_REVIEW_NO_GO'
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
  runtime/central-auto/p28-recovery-roles.sql; do
  test "$(git rev-parse "$central_sha:$file")" = "$(git rev-parse "$tree:$file")"
done

# Reviewed ingress implementation and regressions must survive the merge tree.
for file in \
  runtime/central-auto/index.php \
  runtime/central-auto/strict-json.php \
  runtime/central-auto/receiver-grants.sql \
  runtime/central-auto/p29-json-probe.php \
  infra/n8n-private/homologation/check_p29_ingress_boundaries.py; do
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

root=$(mktemp -d "$RUNNER_TEMP/aia-p29-probe-p29-tree-XXXXXX")
cleanup() {
  case "$root" in
    "$RUNNER_TEMP"/aia-p29-probe-p29-tree-*) rm -rf -- "$root" ;;
    *) echo 'P29_PROBE_CLEANUP_PATH_DENIED' >&2 ;;
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
printf 'P29_PROBE_SOURCE central=%s agents=%s tree=%s\n' "$central_sha" "$agents_sha" "$tree"

(
  cd "$root"
  php -l runtime/central-auto/index.php
  php -l runtime/central-auto/strict-json.php
  php -l runtime/central-auto/p29-json-probe.php
  python3 infra/n8n-private/homologation/check_p29_ingress_boundaries.py
)
printf "%s\n" "P29_ISOLATED_INGRESS_PROBE_PASS full_candidate_matrix=false go_for_installation=false"
