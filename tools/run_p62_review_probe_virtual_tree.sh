#!/usr/bin/env bash
set -euo pipefail
umask 077
[[ "${GITHUB_ACTIONS:-}" == true && "${GITHUB_REPOSITORY:-}" == rafhael100/imagine-ai-agents && "${GITHUB_EVENT_NAME:-}" == push ]] || exit 2
[[ "${GITHUB_SHA:-}" =~ ^[a-f0-9]{40}$ && -n "${RUNNER_TEMP:-}" && -d "$RUNNER_TEMP" && ! -L "$RUNNER_TEMP" ]] || exit 2
repo=$(git rev-parse --show-toplevel)
cd "$repo"
agents_sha=$(git rev-parse HEAD)
central_sha=c0c46f6f1d3d4ff007469388d3f53fa4719eb71e
test "$agents_sha" = "$GITHUB_SHA"
git diff --quiet
git diff --cached --quiet
git merge-base --is-ancestor 3fb86c5fb011bc9df1f12a4a30a7c094370b6872 "$agents_sha"
test "$(git -C _central_probe_pinned rev-parse HEAD)" = "$central_sha"
git fetch --quiet --no-tags --no-write-fetch-head "$repo/_central_probe_pinned" "$central_sha"
if ! merged=$(git merge-tree --write-tree --name-only "$central_sha" "$agents_sha"); then
  echo P62_PROBE_MERGE_CONFLICT
  exit 2
fi
tree=$(printf '%s\n' "$merged" | sed -n '1p')
[[ "$tree" =~ ^[a-f0-9]{40}$ ]]
for file in 'examples/p56-live-pg-review-local.php' 'tools/labs/PgScopedReviewQueueBridgeLab.php' 'tools/labs/PgSignedDraftReviewLab.php' 'tools/labs/PgHistoricalSignedReviewAuditLab.php' 'tools/labs/PgReadOnlySnapshotLab.php' 'tools/labs/PgSignedReviewQueueBridgeLab.php' 'tools/p62-signed-review-probe.php' 'tools/check_p62_live_signed_review.py'; do
  test "$(git rev-parse "$agents_sha:$file")" = "$(git rev-parse "$tree:$file")"
done
for file in infra/n8n-private/homologation/check_p24_full_n8n_restore.py infra/n8n-private/homologation/p25_agents_recovery.py runtime/central-auto/Dockerfile; do
  test "$(git rev-parse "$central_sha:$file")" = "$(git rev-parse "$tree:$file")"
done
root=$(mktemp -d "$RUNNER_TEMP/aia-p62-probe-XXXXXX")
cleanup() {
  case "$root" in "$RUNNER_TEMP"/aia-p62-probe-*) rm -rf -- "$root";; *) echo P62_CLEANUP_PATH_DENIED >&2;; esac
}
trap cleanup EXIT
git archive "$tree" | tar --extract --file=- --directory="$root" --no-same-owner --no-same-permissions
adapter_sha=8b04d0e936168c77db028160b5aeb1758db497c4
git merge-base --is-ancestor "$adapter_sha" "$central_sha"
mkdir "$root/_auto_ia_pinned_source"
git archive "$adapter_sha" | tar --extract --file=- --directory="$root/_auto_ia_pinned_source" --no-same-owner --no-same-permissions
chmod -R a+rX "$root"
export PYTHONDONTWRITEBYTECODE=1
printf 'P62_PROBE_SOURCE agents=%s central=%s tree=%s\n' "$agents_sha" "$central_sha" "$tree"
(
  cd "$root"
  for file in tools/labs/PgReadOnlySnapshotLab.php tools/labs/PgSignedReviewQueueBridgeLab.php tools/labs/PgScopedReviewQueueBridgeLab.php tools/labs/PgSignedDraftReviewLab.php tools/labs/PgHistoricalSignedReviewAuditLab.php tools/p62-signed-review-probe.php examples/p56-live-pg-review-local.php; do php -l "$file"; done
  python3 tools/check_p62_live_signed_review.py
)
printf '%s\n' 'AIA_P62_ISOLATED_PROBE_PASS full_candidate_matrix=false go_for_installation=false'
