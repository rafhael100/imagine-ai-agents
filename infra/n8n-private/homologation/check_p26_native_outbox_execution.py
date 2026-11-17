#!/usr/bin/env python3
"""P26: one real native path plus failure/reconciliation boundaries in private CI.

P19 lead.ingest stays an independent received-only inbox. Here a separately
signed synthetic owner decision admits mock.run.completed/run.status.project.
No paid API, credentials in n8n, published port, customer, or deployment.
"""
from __future__ import annotations

import base64
import json
import os
import re
import secrets
import subprocess
import sys
import tempfile
from pathlib import Path

from check_p24_full_n8n_restore import (Failure, NativeDenied, ROOT, PRIVATE, Stack,
                                       parse_json, parse_output, require, run)
from p25_agents_recovery import AgentsRecovery

sys.path.insert(0, str(ROOT / "runtime/central-auto/lab-executor"))
from native_contract import (ContractDenied, canonical, materialize, template,
                             verify_export, verify_result)  # noqa: E402

PROBE = ROOT / "runtime/central-auto/lab-executor/probe.php"


def main(after_scenarios=None) -> dict:
    require(os.environ.get("GITHUB_ACTIONS") == "true" and
            os.environ.get("GITHUB_REPOSITORY") == "rafhael100/imagine-ai-agents" and
            os.environ.get("P24_RUNNER_ENVIRONMENT") == "github-hosted", "P26_HOSTED_CI_ONLY")
    require(not os.environ.get("DOCKER_HOST") and not os.environ.get("DOCKER_CONTEXT"),
            "P26_REMOTE_DOCKER_FORBIDDEN")
    require(run(["docker", "context", "show"], "P26_DOCKER_CONTEXT").stdout.strip() == b"default",
            "P26_DEFAULT_DOCKER_CONTEXT")
    context = json.loads(run(["docker", "context", "inspect", "default"], "P26_DOCKER_CONTEXT").stdout)
    require(context[0]["Endpoints"]["docker"]["Host"] == "unix:///var/run/docker.sock",
            "P26_LOCAL_DOCKER_SOCKET")
    run_id, attempt = os.environ.get("GITHUB_RUN_ID", ""), os.environ.get("GITHUB_RUN_ATTEMPT", "")
    require(re.fullmatch(r"[0-9]{1,20}", run_id) is not None and
            re.fullmatch(r"[0-9]{1,4}", attempt) is not None, "P26_RUN_ID")
    pg_image = os.environ.get("N8N_PG_IMAGE", "")
    require(re.fullmatch(r"(?:docker.io/library/)?postgres@sha256:[a-f0-9]{64}", pg_image) is not None,
            "P26_PG_DIGEST")
    source = template(PRIVATE / "workflows/native-run-status-noai.workflow.json")
    os.umask(0o077)
    with tempfile.TemporaryDirectory(prefix="central-p26-", dir=os.environ["RUNNER_TEMP"]) as tmp:
        directory = Path(tmp)
        prefix = "imagine-p26-" + run_id + "-" + attempt
        stack = Stack(prefix + "-native", directory / "native-secrets", pg_image)
        agents = AgentsRecovery(prefix, directory / "agents-state")
        observed_executions = 0

        def rpc(mode: str, **payload) -> dict:
            return agents._run_php("src", PROBE, mode, payload)

        def prepare(case: str) -> tuple[dict, str]:
            # Each grant/envelope is created now, after engine boot, and receives
            # no historical epoch from the driver. Import precedes lease intent.
            seed = base64.b64encode(secrets.token_bytes(32)).decode()
            staged = rpc("stage", case=case, seed_b64=seed)
            require(staged.get("tenant_ref") == "SYNTHETIC_P26_TENANT", "P26_STAGE_" + case)
            flow, bound = materialize(source, staged)
            stack.import_json("workflow", canonical(flow))
            exported = directory / (case.lower() + "-export.json")
            stack.export_json("workflow", exported)
            verify_export(flow, parse_json(exported))
            lease = rpc("lease", context=bound)
            require(lease.get("decision") == "LAB_LEASE_RESERVED_NO_DELIVERY" and
                    lease.get("event_ref") == bound["event_ref"] and lease.get("attempt") == 1,
                    "P26_OWNER_BOUND_LEASE_" + case)
            return bound, seed

        def begin(bound: dict) -> None:
            result = rpc("begin", context=bound)
            require(result.get("decision") == "P26_CALL_ONCE" and result.get("state") == "STARTED",
                    "P26_INTENT_REQUIRED")

        def execute_and_record(bound: dict, seed: str) -> dict:
            nonlocal observed_executions
            # This direct CLI call is the only source of positive observations.
            # On timeout/loss of output the durable STARTED intent remains, so a
            # later reconciliation marks UNKNOWN instead of calling n8n again.
            execution = parse_output(stack.cli("execute", "--id=" + bound["workflow_id"],
                                               "--rawOutput", timeout=50).stdout)
            verify_result(execution, bound)
            observed_executions += 1
            stored = rpc("record-result", context=bound, seed_b64=seed, execution=execution)
            require(stored.get("decision") == "P26_RESULT_DURABLE" and stored.get("state") == "RESULT",
                    "P26_RESULT_COMMIT_REQUIRED")
            return execution

        try:
            stack.claim_empty()
            print("P26_BOOT_PRIVATE_NATIVE_AND_AGENTS", flush=True)
            stack.dc("up", "-d", "--wait", "--wait-timeout", "180", timeout=240)
            stack.inspect_runtime()
            stack.dc("stop", "n8n")
            agents._start("src")
            agents._sql("src", (ROOT / "tools/auto_ia/central_transport/001_pg_durable_nonce.sql").read_text())
            require(rpc("init").get("schema") == "P26_LAB_READY_V1", "P26_INIT")

            main_context, main_seed = prepare("MAIN")
            substituted = {**main_context, "workflow_sha256": "0" * 64}
            require(rpc("begin", context=substituted).get("decision") == "DENY", "P26_GRAPH_SWAP_DENIED")
            foreign = {**main_context, "tenant_ref": "SYNTHETIC_P26_OTHER_TENANT"}
            require(rpc("begin", context=foreign).get("decision") == "DENY", "P26_FOREIGN_TENANT_DENIED")
            begin(main_context)
            observed = execute_and_record(main_context, main_seed)
            before = rpc("stats")
            require(before.get("acked") == 0 and before.get("evidence") == 0 and
                    before["states"][0]["state"] == "RESULT", "P26_NO_ACK_BEFORE_RESTART")
            # Fresh PHP process, no private key or observation supplied: resume
            # solely from the committed result/receipt and current domain state.
            del main_seed
            recovered = rpc("reconcile", context=main_context)
            require(recovered.get("decision") == "P26_ACK_EVIDENCE_CONFIRMED" and
                    recovered.get("state") == "ACKED", "P26_RESTART_ACK")
            replay = rpc("reconcile", context=main_context)
            require(replay.get("decision") == "P26_ACK_EVIDENCE_CONFIRMED" and
                    replay.get("state") == "ACKED" and observed_executions == 1,
                    "P26_IDEMPOTENT_REPLAY_NO_EXECUTION")
            rpc("fixture-ledger-lag", context=main_context)
            lagged = rpc("stats")
            require(lagged.get("acked") == 1 and lagged.get("evidence") == 1 and
                    lagged["states"][0]["state"] == "RESULT", "P26_POST_ACK_LEDGER_LAG_FIXTURE")
            repaired = rpc("reconcile", context=main_context)
            require(repaired.get("decision") == "P26_ACK_EVIDENCE_CONFIRMED" and
                    repaired.get("state") == "ACKED" and observed_executions == 1,
                    "P26_POST_ACK_CRASH_RECONCILED_WITHOUT_EXECUTION")
            print("P26_REAL_NATIVE_RESULT_DURABLE_RESTART_ACK_EVIDENCE_PASS", flush=True)

            malformed, malformed_seed = prepare("MALFORMED")
            begin(malformed)
            try:
                verify_result(observed, malformed)
            except ContractDenied:
                pass
            else:
                raise Failure("P26_OTHER_EVENT_RESULT_ACCEPTED")
            # Replay of a real result for a different event is not a new success.
            denied = rpc("record-result", context=malformed, seed_b64=malformed_seed, execution=observed)
            require(denied.get("decision") == "DENY" and denied.get("state") == "UNKNOWN",
                    "P26_MALFORMED_RESULT_ACK_DENIED")
            require(rpc("reconcile", context=malformed).get("state") == "UNKNOWN", "P26_MALFORMED_NO_RETRY")

            failed, _ = prepare("FAILURE")
            begin(failed)
            # Inject loss/failure at the unsafe boundary AFTER durable intent.
            # No assertion is made that a native process ran for this fixture.
            failure_callbacks = 0
            try:
                failure_callbacks += 1
                raise Failure("P26_INJECTED_EXECUTOR_FAILURE")
            except Failure:
                unknown = rpc("reconcile", context=failed)
            require(unknown.get("state") == "UNKNOWN", "P26_CRASH_WINDOW_UNKNOWN")
            require(rpc("reconcile", context=failed).get("state") == "UNKNOWN" and
                    rpc("begin", context=failed).get("decision") == "DENY" and failure_callbacks == 1,
                    "P26_UNSAFE_OUTCOME_NOT_REEXECUTED")
            print("P26_MALFORMED_AND_UNCERTAIN_OUTCOMES_BLOCK_ACK_AND_RETRY_PASS", flush=True)

            revoked, _ = prepare("REVOKED")
            count_before = observed_executions
            rpc("revoke-owner", context=revoked)  # specifically AFTER reserve
            require(rpc("begin", context=revoked).get("decision") == "DENY" and
                    observed_executions == count_before, "P26_POST_LEASE_REVOCATION_NO_CALL")
            require(all(x["event_ref"] != revoked["event_ref"] for x in rpc("stats")["states"]),
                    "P26_REVOKED_NO_START_INTENT")

            fenced, _ = prepare("FENCE")
            rpc("fixture-expire-lease", context=fenced)
            replacement = rpc("lease", context=fenced)
            require(replacement.get("event_ref") == fenced["event_ref"] and
                    replacement.get("attempt") == 2, "P26_SECOND_DOMAIN_LEASE")
            require(rpc("begin", context=fenced).get("decision") == "DENY" and
                    observed_executions == count_before, "P26_OLD_ATTEMPT_NO_CALL")
            print("P26_REVOKED_POST_LEASE_AND_STALE_ATTEMPT_NO_NATIVE_CALL_PASS", flush=True)

            after, after_seed = prepare("AFTER_START")
            begin(after)
            execute_and_record(after, after_seed)
            rpc("revoke-owner", context=after)
            denied = rpc("reconcile", context=after)
            require(denied.get("decision") == "DENY" and denied.get("state") == "RESULT" and
                    denied.get("reason") == "OWNER_DECISION_REVOKED_EXPIRED_OR_INVALID",
                    "P26_REVOCATION_AFTER_NATIVE_DENIES_ACK")
            # A stored observed result never renews its lease. Fault injection
            # expires the lease; the runtime clock and receipt are unchanged.
            rpc("fixture-expire-lease", context=after)
            expired = rpc("reconcile", context=after)
            require(expired.get("decision") == "DENY" and expired.get("state") == "RESULT" and
                    expired.get("reason") == "LEASE_OR_FENCING_DENIED" and observed_executions == 2,
                    "P26_EXPIRED_LEASE_PRESERVES_RESULT_WITHOUT_RETRY")
            rpc("fixture-expire-receipt", context=after, seed_b64=after_seed)
            expired_receipt = rpc("reconcile", context=after)
            require(expired_receipt.get("decision") == "DENY" and expired_receipt.get("state") == "RESULT" and
                    expired_receipt.get("reason") == "RECEIPT_INVALID_WINDOW" and observed_executions == 2,
                    "P26_EXPIRED_V2_PRESERVES_RESULT_WITHOUT_RETRY")
            totals = rpc("stats")
            require(totals.get("acked") == 1 and totals.get("evidence") == 1, "P26_EXACT_ACK_EVIDENCE")
            states = [row["state"] for row in totals["states"]]
            require(states.count("ACKED") == 1 and states.count("RESULT") == 1 and
                    states.count("UNKNOWN") == 2 and len(states) == 4, "P26_DURABLE_STATE_COUNTS")
            print("P26_REAL_NATIVE_OUTBOX_RECONCILIATION_PASS NO_GO", flush=True)
            report = {
                "schema": "CENTRAL_AUTO_P26_NATIVE_OUTBOX_EXECUTION_V1",
                "successful": 1, "real_n8n_executions_observed": observed_executions,
                "execution_observation_source": "trusted_ci_cli_output",
                "n8n_durable_execution_count_verified": False,
                "acked": 1, "atomic_signed_ack_evidence": 1,
                "restart_after_result_without_second_execution": True,
                "restart_after_atomic_ack_before_auxiliary_ledger_reconciled": True,
                "replay_idempotent": True, "malformed_result_denies_ack": True,
                "injected_execution_failure_unknown_without_retry": True,
                "revocation_after_lease_before_call_denies_execution": True,
                "revoked_case_native_calls": 0, "old_attempt_native_calls": 0,
                "revocation_after_execution_denies_ack_result_retained": True,
                "expired_lease_denies_ack_result_retained": True,
                "expired_signed_v2_denies_ack_result_retained": True,
                "post_start_revocation_cancels_native_atomically": False,
                "transport_fixture": "P7_DATABASE_ADMISSION_ONLY_NO_NEW_MTLS",
                "ai_api_calls": 0, "credits_debited": 0, "host_published_ports": 0,
                "real_owner_iam": False, "real_n8n_signer_custody": False,
                "p19_received_ack_dispatch": "DISABLED", "go_for_installation": False,
            }
            if after_scenarios is not None:
                extension = after_scenarios(agents, stack, directory, {
                    "MAIN": main_context, "MALFORMED": malformed, "FAILURE": failed,
                    "REVOKED": revoked, "FENCE": fenced, "AFTER_START": after,
                })
                require(isinstance(extension, dict) and extension.get("go_for_installation") is False,
                        "P26_AFTER_SCENARIOS_REPORT")
                report["candidate_state_recovery"] = extension
            return report
        finally:
            errors = []
            for resource in (agents, stack):
                try:
                    resource.cleanup()
                except Exception:
                    errors.append("P26_OWNED_RESOURCE_CLEANUP")
            require(not errors, "P26_OWNED_RESOURCE_CLEANUP_FAILED")


if __name__ == "__main__":
    try:
        print(json.dumps(main(), sort_keys=True))
    except (Failure, ContractDenied, NativeDenied, ValueError, KeyError, TypeError,
            OSError, subprocess.SubprocessError) as error:
        label = str(error)
        if re.fullmatch(r"[A-Z0-9_]{1,120}", label) is None:
            label = "UNTRUSTED_DIAGNOSTIC_SUPPRESSED"
        print("CENTRAL_AUTO_P26_DENIED_" + label, file=sys.stderr)
        raise SystemExit(1)
