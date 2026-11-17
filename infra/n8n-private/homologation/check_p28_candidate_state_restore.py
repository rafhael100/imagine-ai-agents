#!/usr/bin/env python3
"""P28: recover the actual P26/P27 candidate state, without running it again.

The optional P26 hook captures its existing PG16 and n8n Stack. The five P20
components pass through the existing CMS/Ed25519 consumer. Restored native
workflows stay inactive; recovery exports and reconciles but never executes.
"""
from __future__ import annotations

import hashlib
import json
import os
import re
import secrets
import shutil
import subprocess
import sys
import tarfile
import time
from pathlib import Path

from check_p24_full_n8n_restore import (
    Denied, Failure, NativeDenied, ROOT, PRIVATE, Stack, decrypt, fingerprint,
    make_cert, materialize_verified_package, normalized_volume, parse_json,
    public_fingerprint, require, run, seal, sign, verify,
)
from signed_receipt_p23 import Rejected
from check_p26_native_outbox_execution import main as native_main
from check_p27_private_receipt_status import COMPANY_A, COMPANY_B, KEY_A, KEY_B, make_event, make_query
from native_contract import ContractDenied, materialize, template, verify_export
from p25_agents_recovery import AgentsRecovery

PROBE = ROOT / "runtime/central-auto/p28-state-probe.php"
MARKER = b"CENTRAL_AUTO_P28_NATIVE_CANDIDATE_VOLUME_ONLY\n"
CRM_DATABASE = "p28_crm_marker_ci"
CRM_MARKER = "SYNTHETIC_P28_CRM_MARKER_NOT_REAL_CRM_SCHEMA"


def private_write(path: Path, value: bytes) -> None:
    with path.open("xb") as target:
        os.fchmod(target.fileno(), 0o600)
        target.write(value)
        target.flush()
        os.fsync(target.fileno())


def volume_manifest(path: Path, *, normalized: bool = False) -> dict:
    """Inspect content only, never extract an untrusted archive on the runner."""
    from seal_backup import inspect_volume
    inspect_volume(path)
    manifest = {}
    with tarfile.open(path, "r:") as archive:
        for member in archive:
            name = member.name.removeprefix("./").rstrip("/")
            if normalized:
                require(member.uid == 1000 and member.gid == 1000 and
                        member.mode == (0o700 if member.isdir() else 0o600),
                        "P28_RESTORED_VOLUME_OWNER_MODE")
            if name in ("", "."):
                continue
            if member.isdir():
                manifest[name] = {"type": "directory"}
            else:
                stream = archive.extractfile(member)
                require(stream is not None, "P28_VOLUME_MEMBER_UNREADABLE")
                with stream:
                    digest = hashlib.file_digest(stream, "sha256").hexdigest()
                manifest[name] = {"type": "file", "bytes": member.size, "sha256": digest}
    return manifest


def recover_candidate(agents: AgentsRecovery, stack: Stack, parent: Path, contexts: dict) -> dict:
    require(set(contexts) == {"MAIN", "MALFORMED", "FAILURE", "REVOKED", "FENCE", "AFTER_START"},
            "P28_CONTEXT_SET")
    directory = parent / "p28-recovery"
    directory.mkdir(mode=0o700)
    run_id, attempt = os.environ["GITHUB_RUN_ID"], os.environ["GITHUB_RUN_ATTEMPT"]
    pg_image = os.environ["N8N_PG_IMAGE"]
    destination = Stack("imagine-p28-" + run_id + "-" + attempt + "-restore",
                        directory / "target-secrets", pg_image)
    source_cli = stack.cli

    def no_native_execution(*args, **kwargs):
        raise Failure("P28_NATIVE_EXECUTION_FORBIDDEN_DURING_RECOVERY")

    # The only CLI operation needed here is export_json (which has its own
    # reviewed implementation). Refuse all calls through the execution helper.
    stack.cli = no_native_execution
    destination.cli = no_native_execution

    def rpc(which: str, mode: str, **payload) -> dict:
        result = agents._run_php(which, PROBE, mode, payload)
        require(result.get("schema") != "P28_PROBE_DENIED_V1",
                str(result.get("reason", "P28_PROBE_DENIED")))
        return result

    fingerprints = {name: hashlib.sha256(("SYNTHETIC_P28_CERTIFICATE_" + name).encode()).hexdigest()
                    for name in ("A", "B")}
    events = {
        "A": make_event(COMPANY_A, "SYNTHETIC_TENANT_A", "dddddddd-dddd-4ddd-8ddd-dddddddddddd",
                        "SYNTHETIC_KEY_A", KEY_A),
        "B": make_event(COMPANY_B, "SYNTHETIC_TENANT_B", "eeeeeeee-eeee-4eee-8eee-eeeeeeeeeeee",
                        "SYNTHETIC_KEY_B", KEY_B),
    }

    def query(which: str, name: str) -> dict:
        event = events[name]
        q = make_query(event["company_id"], event["envelope"]["tenant_ref"],
                       event["envelope"]["event_ref"], event["envelope"]["payload_hash"],
                       key_id=event["envelope"]["key_id"])
        return rpc(which, "query-receipt", query=q, fingerprint=fingerprints[name])

    def expected_receipt(name: str) -> dict:
        envelope = events[name]["envelope"]
        return {"tenant_ref": envelope["tenant_ref"], "event_ref": envelope["event_ref"],
                "payload_hash": envelope["payload_hash"], "state": "RECEIVED"}

    def capture_volume(owner: Stack, output: Path) -> None:
        run(["docker", "run", "--rm", "--network", "none", "--read-only", "--cap-drop", "ALL",
             "--security-opt", "no-new-privileges:true", "--user", "1000:1000",
             "-v", owner.project + "_n8n_data:/input:ro", "--entrypoint", "tar", pg_image,
             "-C", "/input", "-cf", "-", "."], "P28_CAPTURE_VOLUME", output=output)

    try:
        print("P28_CAPTURE_EXISTING_NATIVE_CANDIDATE_AND_RECEIPT_GRANTS", flush=True)
        destination.claim_empty()
        require(not stack.dc("ps", "-q", "--status", "running", "n8n").stdout.strip(),
                "P28_SOURCE_APPLICATION_MUST_BE_STOPPED")
        for source in ("tools/auto_ia/pg/001_auto_ia_pg_lab.sql", "tools/auto_ia/pg/002_no_ai_inbox.sql",
                       "tools/auto_ia/pg/003_signed_ingress_lab.sql", "runtime/central-auto/receiver-grants.sql",
                       "runtime/central-auto/receipt-status.sql"):
            agents._sql("src", (ROOT / source).read_text())
        require(rpc("src", "seed-receipts", events=events, fingerprints=fingerprints).get("received") == 2,
                "P28_RECEIPT_FIXTURE")
        for name in ("A", "B"):
            result = query("src", name)
            require(result.get("permission_denied") is False and result.get("receipt") == expected_receipt(name),
                    "P28_SOURCE_RECEIPT_CONTROL")
        state_snapshot = rpc("src", "snapshot")
        require(state_snapshot.get("schema") == "P28_DATABASE_SNAPSHOT_V1", "P28_SOURCE_STATE_SNAPSHOT")
        source_workflows = directory / "source-workflows.json"
        stack.export_json("workflow", source_workflows)
        exported = parse_json(source_workflows)
        require(isinstance(exported, list) and len(exported) == 6, "P28_SIX_SOURCE_WORKFLOWS")
        source_template = template(PRIVATE / "workflows/native-run-status-noai.workflow.json")
        flows = []
        for context in contexts.values():
            flow, bound = materialize(source_template, context)
            require(bound == context, "P28_SOURCE_CONTEXT_GRAPH_BINDING")
            verify_export(flow, exported)
            flows.append(flow)
        require(stack.sql("SELECT count(*) FROM workflow_entity WHERE active") == "0" and
                stack.sql("SELECT count(*) FROM credentials_entity") == "0", "P28_NO_ACTIVE_FLOW_OR_CREDENTIAL")
        require(stack.sql("SELECT count(*) FROM execution_entity WHERE status IS NULL "
                          "OR status NOT IN ('success','error','canceled','crashed')") == "0",
                "P28_UNFINISHED_SOURCE_EXECUTION_FORBIDDEN")

        # A harmless volume marker proves the captured mounted volume, in
        # addition to comparing every archive member's content at restore.
        run(["docker", "run", "--rm", "-i", "--network", "none", "--read-only", "--cap-drop", "ALL",
             "--security-opt", "no-new-privileges:true", "--user", "1000:1000",
             "-v", stack.project + "_n8n_data:/output", "--entrypoint", "sh", pg_image,
             "-eu", "-c", "umask 077; cat > /output/p28-state-marker"], "P28_VOLUME_MARKER", stdin=MARKER)
        snapshot = directory / "snapshot"
        snapshot.mkdir(mode=0o700)
        run(["docker", "exec", agents.containers["src"], "pg_dump", "-U", "postgres",
             "-d", "imagine_agents_ci", "--format", "custom"], "P28_DUMP_AGENTS_OWNER_ACL",
            output=snapshot / "agents-service.dump")
        dump = (snapshot / "agents-service.dump").read_bytes()
        require(dump.startswith(b"PGDMP") and len(dump) < 64 * 1024 * 1024, "P28_AGENT_DUMP_FORMAT_SIZE")
        dump_hash = hashlib.sha256(dump).hexdigest()
        # Kept outside the package, after the dump. Historical active rows must
        # not recreate access lost after the snapshot.
        current = rpc("src", "revoke-after-snapshot", dump_sha256=dump_hash, main_context=contexts["MAIN"])
        require(current.get("schema") == "P28_CURRENT_REVOCATION_FIXTURE_V1" and
                current.get("real_authority_verified") is False, "P28_CURRENT_FIXTURE")
        private_write(directory / "current-revocations-outside-backup.json",
                      json.dumps(current, sort_keys=True).encode())
        require(query("src", "A").get("receipt") is None and query("src", "B").get("receipt") == expected_receipt("B"),
                "P28_SOURCE_POST_SNAPSHOT_REVOCATION")

        stack.dc("exec", "-T", "n8n-db", "pg_dump", "-U", "n8n_bootstrap_admin", "-d", "n8n",
                 "--format", "custom", "--no-owner", "--no-acl", output=snapshot / "n8n-pg.dump")
        stack.dc("exec", "-T", "n8n-db", "createdb", "-U", "n8n_bootstrap_admin", CRM_DATABASE)
        stack.sql("CREATE TABLE p28_crm_marker(value text PRIMARY KEY); INSERT INTO p28_crm_marker VALUES ('"
                  + CRM_MARKER + "')", CRM_DATABASE)
        stack.dc("exec", "-T", "n8n-db", "pg_dump", "-U", "n8n_bootstrap_admin", "-d", CRM_DATABASE,
                 "--format", "custom", "--no-owner", "--no-acl", output=snapshot / "crm-service.dump")
        capture_volume(stack, snapshot / "n8n-data.tar")
        original_volume = volume_manifest(snapshot / "n8n-data.tar")
        require(original_volume.get("p28-state-marker", {}).get("sha256") == hashlib.sha256(MARKER).hexdigest(),
                "P28_CAPTURED_VOLUME_MARKER")
        key_bytes = (stack.directory / "encryption").read_bytes()
        private_write(snapshot / "n8n-encryption.key", key_bytes)

        cert, key = make_cert(directory, "p28-recipient")
        ciphertext = directory / "backup.cms"
        sealed = seal(snapshot, cert, fingerprint(cert), ciphertext)
        require(sealed.get("encrypted") is True and sealed.get("components") == 5, "P28_FIVE_COMPONENT_PACKAGE")
        signer, public = directory / "signer.key", directory / "signer.pub"
        run(["openssl", "genpkey", "-algorithm", "Ed25519", "-out", str(signer)], "P28_SIGNER_CREATE")
        run(["openssl", "pkey", "-in", str(signer), "-pubout", "-out", str(public)], "P28_SIGNER_PUBLIC")
        metadata, signature = directory / "receipt.json", directory / "receipt.sig"
        pin = public_fingerprint(public)
        sign(ciphertext, signer, public, metadata, signature, now=int(time.time()),
             nonce="SYNTHETIC_RECEIPT_" + secrets.token_hex(16).upper())
        copy = directory / "runner-local-copy.cms"
        shutil.copyfile(ciphertext, copy)
        authenticated = verify(copy, public, metadata, signature, pinned_fingerprint=pin, now=int(time.time()))
        require(authenticated.get("ed25519_signature_valid") is True, "P28_COPY_SIGNATURE_REQUIRED")
        clear = directory / "verified-package.tar"
        require(decrypt(copy, cert, key, clear), "P28_AUTHENTICATED_DECRYPT_REQUIRED")
        recovered = directory / "recovered"
        materialize_verified_package(clear, recovered)
        restored_dump = (recovered / "agents-service.dump").read_bytes()
        require(hashlib.sha256(restored_dump).hexdigest() == dump_hash, "P28_AGENT_COMPONENT_CHANGED")

        print("P28_RESTORE_PG16_QUARANTINE_AND_CURRENT_REVOCATIONS", flush=True)
        agents._start("dst")
        agents._sql("dst", (ROOT / "runtime/central-auto/p28-recovery-roles.sql").read_text())
        run(["docker", "exec", "-i", agents.containers["dst"], "pg_restore", "-U", "postgres",
             "-d", "imagine_agents_ci", "--exit-on-error"], "P28_RESTORE_AGENTS_OWNER_ACL", stdin=restored_dump)
        # Compare only public object names, counts and digests in diagnostics.
        # Never print restored rows, SQL bodies, credentials or key material.
        restored_snapshot = rpc("dst", "snapshot")
        if restored_snapshot != state_snapshot:
            differences = {}
            for component, expected in state_snapshot.items():
                actual = restored_snapshot.get(component)
                if actual != expected:
                    if component == "tables":
                        differences[component] = {name: {"expected": value, "actual": actual.get(name)}
                            for name, value in expected.items() if actual.get(name) != value}
                    else:
                        differences[component] = {"expected": expected, "actual": actual}
            print("P28_RESTORE_SNAPSHOT_DIFFERENCES " + json.dumps(differences, sort_keys=True), flush=True)
        quarantine = rpc("dst", "verify-and-quarantine", snapshot=state_snapshot)
        require(quarantine.get("exact_state_restored") is True and quarantine.get("receipt_execute_suspended") is True,
                "P28_RESTORE_QUARANTINE")
        quarantined = query("dst", "A")
        require(quarantined.get("permission_denied") is True and quarantined.get("receipt") is None,
                "P28_NO_READ_BEFORE_CURRENT_AUTHORITY")
        reconciled = rpc("dst", "apply-current-revocations", current=current, dump_sha256=dump_hash,
                         main_context=contexts["MAIN"])
        require(reconciled.get("business_grants_reenabled") == 0 and reconciled.get("expiry_windows_extended") == 0,
                "P28_REVOCATIONS_ONLY")
        before_queries = rpc("dst", "snapshot")
        denied, allowed = query("dst", "A"), query("dst", "B")
        require(denied.get("permission_denied") is False and denied.get("receipt") is None,
                "P28_RESTORED_ACTIVE_GRANT_MUST_NOT_RESUME")
        require(allowed.get("permission_denied") is False and allowed.get("receipt") == expected_receipt("B"),
                "P28_RESTORED_READ_CONTROL")
        require(rpc("dst", "snapshot") == before_queries, "P28_RECEIPT_READ_MUTATED_STATE")
        native = rpc("dst", "verify-native", contexts=contexts)
        require(native.get("historical_ack_idempotent") is True and native.get("signed_evidence_count") == 1 and
                native.get("unknown_automatic_retries") == 0, "P28_RESTORED_NATIVE_CONCILIATION")

        print("P28_RESTORE_N8N_VOLUME_KEY_AND_SIX_INACTIVE_GRAPHS_NO_EXECUTION", flush=True)
        require((recovered / "n8n-encryption.key").read_bytes() == key_bytes, "P28_RECOVERED_KEY_BYTES")
        sanitized = directory / "normalized-volume.tar"
        normalized_volume(recovered / "n8n-data.tar", sanitized)
        require(volume_manifest(sanitized, normalized=True) == original_volume, "P28_NORMALIZED_VOLUME_CONTENT")
        target_key = destination.directory / "encryption"
        target_key.chmod(0o600)
        target_key.write_bytes((recovered / "n8n-encryption.key").read_bytes())
        target_key.chmod(0o444)
        destination.dc("up", "-d", "--wait", "--wait-timeout", "120", "n8n-db", timeout=180)
        destination.dc("exec", "-T", "n8n-db", "pg_restore", "-U", "n8n_bootstrap_admin", "-d", "n8n",
                       "--exit-on-error", "--no-owner", "--no-acl", "--role=n8n_runtime",
                       stdin=(recovered / "n8n-pg.dump").read_bytes())
        destination.dc("exec", "-T", "n8n-db", "createdb", "-U", "n8n_bootstrap_admin", CRM_DATABASE)
        destination.dc("exec", "-T", "n8n-db", "pg_restore", "-U", "n8n_bootstrap_admin", "-d", CRM_DATABASE,
                       "--exit-on-error", "--no-owner", "--no-acl", stdin=(recovered / "crm-service.dump").read_bytes())
        require(destination.sql("SELECT value FROM p28_crm_marker", CRM_DATABASE) == CRM_MARKER,
                "P28_CRM_MARKER_COMPONENT")
        require(destination.sql("SELECT count(*) FROM workflow_entity") == "6" and
                destination.sql("SELECT count(*) FROM workflow_entity WHERE active") == "0" and
                destination.sql("SELECT count(*) FROM credentials_entity") == "0", "P28_RESTORED_INACTIVE_WORKFLOWS")
        require(destination.sql("SELECT count(*) FROM execution_entity WHERE status IS NULL "
                                "OR status NOT IN ('success','error','canceled','crashed')") == "0",
                "P28_UNFINISHED_RESTORED_EXECUTION_FORBIDDEN")
        destination.dc("create", "n8n")
        run(["docker", "run", "--rm", "--network", "none", "--read-only", "--security-opt", "no-new-privileges:true",
             "-v", str(sanitized) + ":/input.tar:ro", "-v", destination.project + "_n8n_data:/output",
             "--entrypoint", "/bin/sh", pg_image, "-eu", "-c",
             'test -z "$(ls -A /output)"; tar -xf /input.tar -C /output --numeric-owner'], "P28_RESTORE_NORMALIZED_VOLUME")
        target_volume = directory / "restored-volume-before-boot.tar"
        capture_volume(destination, target_volume)
        require(volume_manifest(target_volume, normalized=True) == original_volume, "P28_TARGET_VOLUME_CONTENT_OWNER_MODE")
        destination.dc("up", "-d", "--wait", "--wait-timeout", "180", "n8n", timeout=240)
        destination.inspect_runtime()
        destination.dc("stop", "n8n")
        target_export = directory / "target-workflows.json"
        destination.export_json("workflow", target_export)
        restored_workflows = parse_json(target_export)
        require(isinstance(restored_workflows, list) and len(restored_workflows) == 6, "P28_TARGET_WORKFLOW_COUNT")
        for flow in flows:
            verify_export(flow, restored_workflows)
        require(target_key.read_bytes() == key_bytes, "P28_TARGET_KEY_BYTES_CHANGED")
        print("P28_CANDIDATE_STATE_FULL_PACKAGE_RESTORE_PASS NO_GO", flush=True)
        return {
            "schema": "CENTRAL_AUTO_P28_CANDIDATE_STATE_RESTORE_V1", "package_components": 5,
            "signed_encrypted_package_verified": True, "actual_p26_result_and_ack_evidence_restored": True,
            "p27_read_grants_and_function_acls_restored": True, "source_and_target_pg16_clusters": 2,
            "current_read_and_owner_revocations_applied": True, "historical_ack_and_evidence_count": 1,
            "unknown_automatic_retries": 0, "result_preserved_without_new_ack": True,
            "receipt_only_reads_unchanged_state": True, "roles_nologin_memberships_absent": True,
            "n8n_pg17_volume_key_restored": True, "inactive_workflows_exported_and_verified": 6,
            "native_execution_calls_during_recovery": 0, "n8n_durable_execution_count_verified": False,
            "credential_decryption_verified_in_this_profile": False, "new_mtls_exchange_verified": False,
            "real_crm_schema_restored": False, "crm_component": "SYNTHETIC_MARKER_ONLY",
            "real_current_authority_verified": False, "real_key_custody_verified": False,
            "external_backup_verified": False, "different_host_recovery_verified": False,
            "business_grants_reenabled": 0, "expiry_windows_extended": 0,
            "host_published_ports": 0, "ai_api_calls": 0, "credits_debited": 0, "go_for_installation": False,
        }
    finally:
        stack.cli = source_cli
        destination.cleanup()


def main() -> dict:
    report = native_main(after_scenarios=recover_candidate)
    require(report.get("real_n8n_executions_observed") == 2 and
            report.get("candidate_state_recovery", {}).get("native_execution_calls_during_recovery") == 0,
            "P28_NO_ADDITIONAL_NATIVE_EXECUTION")
    return report


if __name__ == "__main__":
    try:
        print(json.dumps(main(), sort_keys=True))
    except (Failure, ContractDenied, NativeDenied, Denied, Rejected, ValueError, KeyError,
            TypeError, OSError, subprocess.SubprocessError) as error:
        label = str(error)
        if re.fullmatch(r"[A-Z0-9_]{1,120}", label) is None:
            label = "UNTRUSTED_DIAGNOSTIC_SUPPRESSED"
        print("CENTRAL_AUTO_P28_DENIED_" + label, file=sys.stderr)
        raise SystemExit(1)
