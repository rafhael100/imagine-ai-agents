#!/usr/bin/env bash
set -euo pipefail

# RC65: a virtual Git merge-tree only inside an ephemeral GitHub runner.
# Never creates a merge commit, updates refs or touches a remote VPS.
[[ "$(printenv GITHUB_ACTIONS || true)" == "true" ]] || { echo 'RC65_CI_ONLY'; exit 2; }
[[ "$(printenv GITHUB_REPOSITORY || true)" == "rafhael100/imagine-ai-agents" ]] || { echo 'RC65_WRONG_REPO'; exit 2; }
[[ -n "$(printenv RUNNER_TEMP || true)" && -d "$RUNNER_TEMP" ]] || { echo 'RC65_RUNNER_REQUIRED'; exit 2; }

central="$PWD/_central_p20_pinned"
central_sha="995201f9300590f86b6ce5bf5d46168cee5ef903"
base_sha="2ea5df5c7691d8594a129922363454af7072f497"

test "$(git -C "$central" rev-parse HEAD)" = "$central_sha"
git merge-base --is-ancestor "$base_sha" HEAD
git fetch --quiet --no-tags "$central" "$central_sha"

# Local tree-object construction only; no branch update or worktree merge.
if ! virtual_merge=$(git merge-tree --write-tree --name-only HEAD "$central_sha"); then
  echo 'RC65_MERGE_TREE_CONFLICT_REVIEW_REQUIRED_NO_GO'
  exit 2
fi
tree=$(printf '%s\n' "$virtual_merge" | sed -n '1p')
test "$(printf %s "$tree" | wc -c)" -eq 40
test "$(git cat-file -t "$tree")" = tree

root=$(mktemp -d "$RUNNER_TEMP/aia-rc65-virtual-tree-XXXXXX")
cleanup() {
  case "$root" in
    "$RUNNER_TEMP"/aia-rc65-virtual-tree-*) rm -rf -- "$root" ;;
    *) echo 'RC65_CLEANUP_PATH_DENIED' >&2 ;;
  esac
}
trap cleanup EXIT

git archive "$tree" | tar --extract --file=- --directory="$root"

# Both lineages must be present in the same temporary source tree.
for f in \
  src/Automation/DeterministicAutomationService.php \
  src/Automation/DeterministicRuleEngine.php \
  src/Automation/SignedReviewQueueReadModel.php \
  tools/check_p48_deterministic_noai.php \
  tools/check_p49_deterministic_rules.php \
  tools/check_p50_offline_cli.py \
  tools/check_p53_review_queue_view.php \
  tools/check_p60_signed_review_queue.php \
  runtime/central-auto/index.php \
  runtime/central-auto/receiver-grants.sql \
  infra/n8n-private/homologation/test_p20_security.py \
  infra/n8n-private/homologation/seal_backup.py \
  infra/n8n-private/homologation/test_p22_nested_tar.py \
  infra/n8n-private/homologation/test_p23_signed_receipt.py \
  infra/n8n-private/homologation/signed_receipt_p23.py \
  infra/n8n-private/homologation/check_p21_crypto_pg_restore.py; do
  test -f "$root/$f" || { echo "RC65_REQUIRED_PATH_MISSING:$f"; exit 2; }
done

# Original P23 and Agents test tools, executed without service credentials.
(
  cd "$root"
  php -l runtime/central-auto/index.php
  php -l src/Automation/DeterministicAutomationService.php
  php -l src/Automation/DeterministicRuleEngine.php
  php -l src/Automation/SignedReviewQueueReadModel.php
  python3 infra/n8n-private/homologation/test_p20_security.py
  python3 -m unittest discover -s infra/n8n-private/homologation -p "test_p22_nested_tar.py" -v
  python3 -m unittest discover -s infra/n8n-private/homologation -p "test_p23_signed_receipt.py" -v
  python3 infra/n8n-private/homologation/check_p21_crypto_pg_restore.py
  php tools/check_p48_deterministic_noai.php
  php tools/check_p49_deterministic_rules.php
  python3 tools/check_p50_offline_cli.py
  php tools/check_p53_review_queue_view.php
  php tools/check_p60_signed_review_queue.php
)

cat <<'REPORT'
AIA_RC65_VIRTUAL_COMBINED_TREE_PASS
agents_release=NO_GO
central_runtime_dispatch=DISABLED
owner_identity_real=false
live_s2s_exchange=false
merge_commit_created=false
github_branch_updated=false
customer_data=false
paid_ai_calls=0
n8n_operational_calls=0
REPORT

if [[ -n "$(printenv GITHUB_STEP_SUMMARY || true)" ]]; then
  {
    echo '### AIA RC65 — virtual combined tree, offline only'
    echo
    echo '- Central P23 / Agents RC64: Git merge-tree exported into an ephemeral runner directory'
    echo '- Security tests: Central P20 original plus P22 TAR denial, P23 signed receipt and P21 genuine PG16/17 recovery'
    echo '- Agents: P48/P49/P50/P53/P60 original NO_AI and reviewer read-model suites'
    echo '- Original P19 receiver PHP syntax: checked'
    echo '- Real CRM→Agents exchange or n8n execution using this tree: NOT TESTED'
    echo '- Git merge commit, remote changes, VPS, production and paid AI: NONE / NO-GO'
  } >> "$GITHUB_STEP_SUMMARY"
fi
