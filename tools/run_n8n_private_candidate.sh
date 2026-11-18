#!/usr/bin/env bash
# Build and verify the actual consolidated candidate; never synthesize a merge tree.
# All archives and the operator packet contain public Git source only.
set -euo pipefail
umask 077
export PYTHONDONTWRITEBYTECODE=1

deny() { printf 'N8N_PRIVATE_CANDIDATE_DENY_%s\n' "$1" >&2; exit 2; }
[[ $# -eq 0 ]] || deny ARGUMENTS
[[ "${GITHUB_ACTIONS:-}" == true ]] || deny CI_ONLY
[[ "${GITHUB_REPOSITORY:-}" == rafhael100/imagine-ai-agents ]] || deny WRONG_REPOSITORY
[[ "${N8N_CANDIDATE_REPOSITORY_PRIVATE:-}" == false ]] || deny PUBLIC_REPOSITORY_REQUIRED
[[ "${GITHUB_EVENT_NAME:-}" == push ]] || deny PUSH_REQUIRED
[[ "${GITHUB_REF:-}" == refs/heads/feat/n8n-private-candidate-bundle-2026-11-18 ]] || deny BRANCH
[[ "${GITHUB_SHA:-}" =~ ^[a-f0-9]{40}$ ]] || deny SOURCE_SHA
[[ "${GITHUB_RUN_ID:-}" =~ ^[0-9]{1,20}$ ]] || deny RUN_ID
[[ "${GITHUB_RUN_ATTEMPT:-}" =~ ^[0-9]{1,4}$ ]] || deny RUN_ATTEMPT
[[ "${P24_RUNNER_ENVIRONMENT:-}" == github-hosted &&
   "${P30_RUNNER_ENVIRONMENT:-}" == github-hosted ]] || deny HOSTED_RUNNER_REQUIRED
[[ "${RUNNER_TEMP:-}" == /* && -d "$RUNNER_TEMP" && ! -L "$RUNNER_TEMP" ]] || deny RUNNER_TEMP
[[ -z "${DOCKER_HOST:-}" && -z "${DOCKER_CONTEXT:-}" ]] || deny REMOTE_DOCKER

repo=$(git rev-parse --show-toplevel)
cd "$repo"
candidate_sha=$(git rev-parse HEAD)
candidate_tree=$(git rev-parse 'HEAD^{tree}')
central_source_sha=dfa4685cd5df4a9499c3420f580980e769a1981d
domain_source_sha=05e4ea7942e530055f4e455544d82f5d3edbd56d
operator_source_sha=c08688b16e61d8d1ecc4d8c0ed4da8758a687ee8
adapter_sha=8b04d0e936168c77db028160b5aeb1758db497c4
crm_sha=e1b3b7f118eff0350a00789aecc7bb2bb2331464
crm="$repo/_crm_feedback_pinned"
packet="$RUNNER_TEMP/n8n-private-candidate-packet"

[[ "$candidate_sha" == "$GITHUB_SHA" ]] || deny CHECKOUT_SHA
[[ "$candidate_tree" =~ ^[a-f0-9]{40}$ ]] || deny CHECKOUT_TREE
test "$(git rev-parse --is-shallow-repository)" = false
git diff --quiet
git diff --cached --quiet
for source_sha in "$central_source_sha" "$domain_source_sha" "$operator_source_sha" "$adapter_sha"; do
  test "$(git cat-file -t "$source_sha")" = commit
  git merge-base --is-ancestor "$source_sha" "$candidate_sha"
done
[[ -d "$crm" && ! -L "$crm" ]] || deny CRM_CHECKOUT
test "$(git -C "$crm" rev-parse HEAD)" = "$crm_sha"
git -C "$crm" diff --quiet
git -C "$crm" diff --cached --quiet
[[ ! -e "$packet" && ! -L "$packet" ]] || deny PACKET_ALREADY_EXISTS

# The reviewed Central 475 implementation and regressions must remain exact.
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
  runtime/central-auto/lab-executor/test_native_contract_probe.php \
  runtime/central-auto/lab-executor/schema.sql \
  runtime/central-auto/execution-status.php \
  runtime/central-auto/execution-status.sql \
  runtime/central-auto/execution-contract.php \
  runtime/central-auto/execution-contract-test.php \
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
  test "$(git rev-parse "$central_source_sha:$file")" = "$(git rev-parse "$candidate_sha:$file")"
done

# Preserve the reviewed ingress, execution feedback and monitor integration.
for file in \
  runtime/central-auto/index.php \
  runtime/central-auto/strict-json.php \
  runtime/central-auto/receiver-grants.sql \
  runtime/central-auto/lead-ingest-capture.sql \
  infra/n8n-private/homologation/check_crm_lead_capture.py \
  runtime/central-auto/p29-json-probe.php \
  infra/n8n-private/homologation/check_crm_execution_feedback.py \
  runtime/central-auto/feedback-crm-probe.php \
  runtime/central-auto/feedback-crm-monitor-probe.php \
  runtime/central-auto/feedback-http-probe.php \
  infra/n8n-private/homologation/check_crm_lead_execution.py \
  runtime/central-auto/lab-executor/lead-probe.php \
  infra/n8n-private/workflows/native-crm-lead-noai.workflow.json \
  infra/n8n-private/homologation/check_p29_ingress_boundaries.py; do
  test "$(git rev-parse "$central_source_sha:$file")" = "$(git rev-parse "$candidate_sha:$file")"
done

# Pin the reviewed Agents domain extension and the original regression suites.
for file in \
  src/Automation/DeterministicAutomationService.php \
  src/Automation/DeterministicRuleEngine.php \
  src/Automation/ReviewQueueReadModel.php \
  src/Automation/SignedReviewQueueReadModel.php \
  tools/labs/PgN8nOutboxLab.php \
  src/MockOrchestration/CapturedLeadTaskContract.php \
  tools/labs/PgCapturedLeadStageLab.php \
  tools/labs/PgCapturedLeadProofLab.php \
  tools/labs/LabAutomationTaskContract.php \
  tools/labs/PgOwnerDecisionLab.php \
  tools/labs/PgTransportOwnerLineageAuditLab.php \
  tools/labs/PgAtomicStagingAuditLab.php \
  tools/check_captured_lead_contract.php \
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
  test "$(git rev-parse "$domain_source_sha:$file")" = "$(git rev-parse "$candidate_sha:$file")"
done

# P30 is imported unchanged; do not replace its native authenticator or policy facts.
for file in \
  infra/n8n-private/homologation/check_p30_native_operator_mfa.py \
  infra/n8n-private/homologation/MFA-P30.md \
  infra/n8n-private/homologation/LICENSE-P20.md \
  tools/run_rc72_p30_virtual_tree.sh \
  .github/workflows/central-auto-p30-native-operator-mfa.yml; do
  test "$(git rev-parse "$operator_source_sha:$file")" = "$(git rev-parse "$candidate_sha:$file")"
done
for file in \
  infra/n8n-private/docker-compose.yml \
  infra/n8n-private/10-runtime-role.sh \
  tools/run_crm_execution_feedback_virtual_tree.sh \
  .github/workflows/central-auto-crm-execution-feedback.yml; do
  test "$(git rev-parse "$central_source_sha:$file")" = "$(git rev-parse "$candidate_sha:$file")"
done

# Validate the daemon before any pull, build or disposable resource is created.
test "$(docker context show)" = default
test "$(docker context inspect default --format '{{.Endpoints.docker.Host}}')" = unix:///var/run/docker.sock
python3 --version
php --version
docker compose version
openssl version

workspace=$(mktemp -d "$RUNNER_TEMP/n8n-private-candidate-XXXXXX")
cleanup() {
  case "$workspace" in
    "$RUNNER_TEMP"/n8n-private-candidate-*) rm -rf -- "$workspace" ;;
    *) printf 'N8N_PRIVATE_CANDIDATE_CLEANUP_PATH_DENIED\n' >&2 ;;
  esac
}
trap cleanup EXIT
root="$workspace/candidate"
crm_build="$workspace/crm"
mkdir "$root" "$crm_build" "$workspace/checks"
# These are actual commit archives; no untracked workspace file enters either build.
git archive "$candidate_sha" | tar --extract --file=- --directory="$root" --no-same-owner --no-same-permissions
git -C "$crm" archive "$crm_sha" | tar --extract --file=- --directory="$crm_build" --no-same-owner --no-same-permissions
bootstrap="$root/infra/n8n-private/10-runtime-role.sh"
test -f "$bootstrap" && test ! -L "$bootstrap"
chmod 0644 "$bootstrap"
# Source is public; different container uids must be able to read mounted files.
chmod -R a+rX "$root" "$crm_build"
printf 'source_integrity\tPASS\n' > "$workspace/checks.tsv"
printf 'N8N_PRIVATE_CANDIDATE_SOURCE commit=%s tree=%s crm=%s\n' "$candidate_sha" "$candidate_tree" "$crm_sha"

pinned_n8n=ghcr.io/n8n-io/n8n@sha256:b4b90747b81fcda7e76550c0d3aa7f0f64917a1e9573cff40d6834eaf2e0f8ea
[[ "${N8N_IMAGE:-$pinned_n8n}" == "$pinned_n8n" ]] || deny N8N_IMAGE_OVERRIDE
export N8N_IMAGE="$pinned_n8n"
docker pull "$N8N_IMAGE" >/dev/null
docker pull postgres:17 >/dev/null
docker pull postgres:16 >/dev/null
docker pull nginx:stable-alpine >/dev/null
# Remote identities are observed RepoDigests, not floating tags or local image IDs.
observed_n8n=$(docker image inspect "$N8N_IMAGE" --format '{{json .RepoDigests}}')
python3 - "$N8N_IMAGE" "$observed_n8n" <<'PY_N8N'
import json, sys
if sys.argv[1] not in json.loads(sys.argv[2]):
    raise SystemExit('N8N_PRIVATE_CANDIDATE_N8N_DIGEST_NOT_OBSERVED')
PY_N8N
N8N_PG_IMAGE=$(docker image inspect postgres:17 --format '{{index .RepoDigests 0}}')
AGENTS_PG_IMAGE=$(docker image inspect postgres:16 --format '{{index .RepoDigests 0}}')
AGENTS_NGINX_IMAGE=$(docker image inspect nginx:stable-alpine --format '{{index .RepoDigests 0}}')
[[ "$N8N_PG_IMAGE" =~ ^(docker.io/library/)?postgres@sha256:[a-f0-9]{64}$ ]] || deny N8N_POSTGRES_DIGEST
[[ "$AGENTS_PG_IMAGE" =~ ^(docker.io/library/)?postgres@sha256:[a-f0-9]{64}$ ]] || deny AGENTS_POSTGRES_DIGEST
[[ "$AGENTS_NGINX_IMAGE" =~ ^(docker.io/library/)?nginx@sha256:[a-f0-9]{64}$ ]] || deny NGINX_DIGEST
[[ "$N8N_PG_IMAGE" != "$AGENTS_PG_IMAGE" ]] || deny POSTGRES_MAJOR_IDENTITIES
export N8N_PG_IMAGE AGENTS_PG_IMAGE AGENTS_NGINX_IMAGE

agents_tag="imagine-n8n-private-agents-php:$GITHUB_RUN_ID-$GITHUB_RUN_ATTEMPT"
crm_tag="imagine-n8n-private-crm-php:$GITHUB_RUN_ID-$GITHUB_RUN_ATTEMPT"
docker build --quiet -f "$root/runtime/central-auto/Dockerfile" -t "$agents_tag" "$root" >/dev/null
docker build --quiet -f "$crm_build/infra/homologation/Dockerfile" -t "$crm_tag" "$crm_build" >/dev/null
# These two identities are local builds, not published registry digests.
AGENTS_PHP_IMAGE=$(docker image inspect "$agents_tag" --format '{{.Id}}')
CRM_FEEDBACK_PHP_IMAGE=$(docker image inspect "$crm_tag" --format '{{.Id}}')
[[ "$AGENTS_PHP_IMAGE" =~ ^sha256:[a-f0-9]{64}$ ]] || deny AGENTS_PHP_IMAGE_ID
[[ "$CRM_FEEDBACK_PHP_IMAGE" =~ ^sha256:[a-f0-9]{64}$ ]] || deny CRM_PHP_IMAGE_ID
export AGENTS_PHP_IMAGE CRM_FEEDBACK_PHP_IMAGE

# Add external, pinned public test sources only after the direct candidate build.
mkdir "$root/_auto_ia_pinned_source"
git archive "$adapter_sha" | tar --extract --file=- --directory="$root/_auto_ia_pinned_source" --no-same-owner --no-same-permissions
mv "$crm_build" "$root/_crm_feedback_pinned_source"
chmod -R a+rX "$root/_auto_ia_pinned_source"
export AIA_P24_SOURCE_SHA="$candidate_sha"
export AIA_RC66_AGENTS_SHA="$domain_source_sha"
# Historical environment name required by the original harness. Its value is
# now the real HEAD tree; no virtual merge is created or described as a commit.
export AIA_RC66_VIRTUAL_TREE_SHA="$candidate_tree"
export AIA_CRM_FEEDBACK_SOURCE="$root/_crm_feedback_pinned_source"
export AIA_CRM_FEEDBACK_SHA="$crm_sha"

run_check() {
  local name="$1"
  shift
  [[ "$name" =~ ^[a-z0-9_]{1,64}$ ]] || deny CHECK_NAME
  printf 'N8N_PRIVATE_CANDIDATE_CHECK_BEGIN %s\n' "$name"
  # Original probes already emit sanitized diagnostics. Logs stay in temporary
  # CI storage and are never included in the public source packet.
  "$@" 2>&1 | tee "$workspace/checks/$name.log"
  printf '%s\tPASS\n' "$name" >> "$workspace/checks.tsv"
  printf 'N8N_PRIVATE_CANDIDATE_CHECK_PASS %s\n' "$name"
}
php_syntax() {
  local file
  for file in \
    runtime/central-auto/index.php \
    runtime/central-auto/strict-json.php \
    runtime/central-auto/p29-json-probe.php \
    runtime/central-auto/p25-recovery-probe.php \
    runtime/central-auto/execution-status.php \
    runtime/central-auto/execution-contract.php \
    runtime/central-auto/feedback-crm-probe.php \
    runtime/central-auto/feedback-crm-monitor-probe.php \
    runtime/central-auto/feedback-http-probe.php \
    runtime/central-auto/event-status.php \
    runtime/central-auto/p27-receipt-probe.php \
    runtime/central-auto/p28-state-probe.php \
    runtime/central-auto/p28-constraint-compare.php \
    runtime/central-auto/p28-constraint-compare-test.php \
    runtime/central-auto/lab-executor/*.php \
    src/Automation/DeterministicAutomationService.php \
    src/Automation/DeterministicRuleEngine.php \
    src/Automation/ReviewQueueReadModel.php \
    src/Automation/SignedReviewQueueReadModel.php; do
    php -l "$file" || return $?
  done
}
(
  cd "$root"
  run_check php_syntax php_syntax
  run_check p2_safety_contracts python3 -m unittest discover -s infra/n8n-private/homologation -p 'test_p2*.py' -v
  run_check native_contracts python3 -m unittest discover -s runtime/central-auto/lab-executor -p 'test*_contract.py' -v
  run_check execution_contract php runtime/central-auto/execution-contract-test.php
  run_check crm_execution_receipt_contract php "$AIA_CRM_FEEDBACK_SOURCE/scripts/ci/s2s/test-agents-execution-receipt.php"
  run_check captured_lead_contract php tools/check_captured_lead_contract.php
  run_check p48_deterministic_noai php tools/check_p48_deterministic_noai.php
  run_check p49_deterministic_rules php tools/check_p49_deterministic_rules.php
  run_check p50_offline_cli python3 tools/check_p50_offline_cli.py
  run_check p53_review_queue php tools/check_p53_review_queue_view.php
  run_check p60_signed_review_queue php tools/check_p60_signed_review_queue.php
  run_check operator_packet_contracts python3 -m unittest discover -s infra/n8n-private/homologation -p 'test_operator_packet.py' -v
  # These retain the original ingress, native execution, mTLS, scoped monitor
  # and recovery matrix, now against one complete source candidate.
  run_check crm_lead_capture python3 infra/n8n-private/homologation/check_crm_lead_capture.py
  run_check crm_lead_execution python3 infra/n8n-private/homologation/check_crm_lead_execution.py
  run_check crm_execution_feedback python3 infra/n8n-private/homologation/check_crm_execution_feedback.py
  run_check p25_agents_state_restore python3 infra/n8n-private/homologation/check_p25_agents_state_restore.py
  run_check p28_candidate_state_restore python3 infra/n8n-private/homologation/check_p28_candidate_state_restore.py
  # P30 owns a fresh, separate Stack. Its operator state is not the feedback
  # snapshot and individual enrollment does not establish global enforcement.
  run_check p30_native_operator_mfa python3 infra/n8n-private/homologation/check_p30_native_operator_mfa.py
)

# Construct observations only after every real command succeeds. Never copy
# raw probe output, runtime secrets, dumps or container state into the packet.
python3 - "$workspace" <<'PY_OBSERVATIONS'
import json, os, pathlib, sys
workspace = pathlib.Path(sys.argv[1])
checks = {}
for line in (workspace / 'checks.tsv').read_text().splitlines():
    name, status = line.split('\t')
    if name in checks or status != 'PASS':
        raise SystemExit('N8N_PRIVATE_CANDIDATE_CHECK_RECORD_INVALID')
    checks[name] = status
reports = []
for line in (workspace / 'checks/p30_native_operator_mfa.log').read_text().splitlines():
    if line.startswith('{'):
        value = json.loads(line)
        if value.get('schema') == 'CENTRAL_AUTO_P30_NATIVE_OPERATOR_MFA_V1':
            reports.append(value)
if len(reports) != 1:
    raise SystemExit('N8N_PRIVATE_CANDIDATE_P30_REPORT_MISSING')
p30 = reports[0]
if (p30.get('n8n_image') != os.environ['N8N_IMAGE'] or
        p30.get('n8n_version_verified') != '2.47.7' or
        p30.get('native_totp_enrollment_verified') is not True or
        type(p30.get('synthetic_nominal_operators')) is not int or
        p30['synthetic_nominal_operators'] != 1 or
        any(p30.get(name) is not False for name in (
            'mfa_enforced_instance_wide', 'mandatory_mfa_policy_gate_closed', 'go_for_installation'))):
    raise SystemExit('N8N_PRIVATE_CANDIDATE_P30_POLICY_CHANGED')
report = {
    'schema': 'N8N_PRIVATE_CANDIDATE_OBSERVATIONS_V1',
    'run_id': os.environ['GITHUB_RUN_ID'],
    'run_attempt': os.environ['GITHUB_RUN_ATTEMPT'],
    'source_sha': os.environ['GITHUB_SHA'],
    'remote_images': {
        'n8n': os.environ['N8N_IMAGE'],
        'n8n_postgres': os.environ['N8N_PG_IMAGE'],
        'agents_postgres': os.environ['AGENTS_PG_IMAGE'],
        'nginx': os.environ['AGENTS_NGINX_IMAGE'],
    },
    'local_build_images': {
        'agents_php': os.environ['AGENTS_PHP_IMAGE'],
        'crm_php': os.environ['CRM_FEEDBACK_PHP_IMAGE'],
    },
    'checks': checks,
    'p30': {name: p30[name] for name in (
        'n8n_version_verified', 'synthetic_nominal_operators',
        'mfa_enforced_instance_wide', 'mandatory_mfa_policy_gate_closed', 'go_for_installation')},
}
with (workspace / 'observations.json').open('x') as target:
    json.dump(report, target, sort_keys=True, separators=(',', ':'))
    target.write('\n')
PY_OBSERVATIONS

# Recheck the public source before packet creation. The create command also
# verifies provenance and emits deterministic source archives with no .git data.
cd "$repo"
test "$(git rev-parse HEAD)" = "$candidate_sha"
git diff --quiet
git diff --cached --quiet
python3 "$root/infra/n8n-private/homologation/operator_packet.py" create \
  --candidate-repo "$repo" --crm-repo "$crm" \
  --observations "$workspace/observations.json" --output-dir "$packet"
python3 "$root/infra/n8n-private/homologation/operator_packet.py" verify \
  "$packet" --expected-candidate-sha "$candidate_sha"
packet_manifest_sha=$(sha256sum "$packet/manifest.json")
packet_manifest_sha=${packet_manifest_sha%% *}
[[ "$packet_manifest_sha" =~ ^[a-f0-9]{64}$ ]] || deny PACKET_MANIFEST_DIGEST

cat <<'REPORT'
N8N_PRIVATE_CANDIDATE_VERIFIED_PASS
candidate_source=ACTUAL_HEAD_TREE
operator_packet=PUBLIC_SOURCE_AND_CI_OBSERVATIONS_ONLY
agents_release=NO_GO
default_runtime_dispatch=DISABLED
lead_execution=DISPOSABLE_CI_ONLY
mfa_enforced_instance_wide=false
mandatory_mfa_policy_gate_closed=false
human_operator_identity=false
external_backup_verified=false
registry_php_images_published=false
paid_ai_calls=0
go_for_installation=false
REPORT

if [[ -n "${GITHUB_STEP_SUMMARY:-}" ]]; then
  {
    echo '### Consolidated private n8n candidate'
    echo
    printf -- '- Actual candidate commit: `%s`\n' "$candidate_sha"
    printf -- '- Actual candidate tree: `%s`\n' "$candidate_tree"
    printf -- '- Preserved Central #475: `%s`\n' "$central_source_sha"
    printf -- '- Preserved lead domain #468: `%s`\n' "$domain_source_sha"
    printf -- '- Preserved native operator P30 #464: `%s`\n' "$operator_source_sha"
    printf -- '- Pinned CRM #654: `%s`\n' "$crm_sha"
    printf -- '- Historical replay adapter: `%s`\n' "$adapter_sha"
    printf -- '- Verified packet manifest SHA256: `%s`\n' "$packet_manifest_sha"
    echo '- Direct source build; no temporary merge tree or domain checkout supplied missing files.'
    echo '- Existing contracts, lead/native execution, signed CRM history, scoped monitor and recovery checks passed.'
    echo '- The original P30 driver passed in its separate disposable Stack; global MFA enforcement remains false.'
    echo '- Remote image digests and local PHP build IDs are recorded separately in the verified packet.'
    echo '- Public archives and manifest are source evidence, not a backup, installer or operational approval.'
    echo '- The review artifact is retained for seven days; keep its trusted CI manifest digest for later verification.'
    echo '- Human CRM session/MFA/browser evidence remains in CRM #654; no browser rerun is claimed here.'
    echo '- DRAFT / NO-GO: no real operator, external backup, registry publication, paid AI or installation.'
  } >> "$GITHUB_STEP_SUMMARY"
fi
