#!/usr/bin/env python3
"""Synthetic mTLS capture -> owner-bound P6 -> real native n8n -> durable ACK.

The HTTP acknowledgement remains RECEIVED/DISABLED. A separate owner decision
creates the explicit lead task. Recovery never invokes native execution.
"""
from __future__ import annotations

import base64
import hashlib
import json
import os
import re
import secrets
import subprocess
import sys
import tempfile
import uuid
from concurrent.futures import ThreadPoolExecutor
from pathlib import Path

from check_p24_full_n8n_restore import (
    Failure, NativeDenied, PRIVATE, ROOT, Stack, normalized_volume, parse_json,
    parse_output, require, run,
)
from check_p27_private_receipt_status import (
    COMPANY_A, COMPANY_B, KEY_A, fingerprint, make_event, make_query, verify_receipt,
)
from check_p29_ingress_boundaries import literal
from check_crm_lead_capture import (
    CAPTURE, INDEX_SHA256, ROLES, CaptureLab, capture_metadata, check_ack,
    expected_row, immutable_checks, permissions,
)
from check_p28_candidate_state_restore import private_write, volume_manifest

sys.path.insert(0, str(ROOT / "runtime/central-auto/lab-executor"))
from native_contract import (  # noqa: E402
    ContractDenied, canonical, expected, materialize, template, verify_export, verify_result,
)

PROBE = ROOT / "runtime/central-auto/lab-executor/lead-probe.php"
SIDECAR = "aia_lead_ingest_lab.captured_job_sources"
TABLES = {
    CAPTURE: "tenant,product,event_ref",
    SIDECAR: "tenant_ref,event_ref",
    "auto_ia_lab.event_inbox": "tenant,product,event_ref",
    "auto_ia_lab.company_links": "company_id",
    "auto_ia_lab.service_company_grants": "company_id,fingerprint,operation",
    "auto_ia_lab.receipt_read_grants": "company_id,fingerprint,operation",
    "auto_ia_lab.service_event_audit": "id",
    "auto_ia_lab.mock_s2s_keys": "issuer,key_id",
    "aia_p16_lab.event_replay": "tenant_ref,event_ref",
    "public.aia_lab_n8n_outbox_p6": "tenant_ref,event_ref",
    "aia_p26_native.executions": "tenant_ref,event_ref",
    "aia_p34_lab.tenant_policy": "tenant_ref",
    "aia_p37_lab.executor_keys": "tenant_ref,key_id",
    "aia_p38_lab.signed_ack_evidence": "tenant_ref,event_ref",
    "aia_p41_lab.company_tenant_links": "company_id",
    "aia_p44_lab.owner_keys": "company_id,key_id",
    "aia_p44_lab.consumed_owner_grants": "company_id,grant_ref",
}
AUTHORITY_TABLES = {
    "auto_ia_lab.company_links", "auto_ia_lab.service_company_grants", "auto_ia_lab.receipt_read_grants",
    "auto_ia_lab.mock_s2s_keys", "aia_p34_lab.tenant_policy", "aia_p37_lab.executor_keys",
    "aia_p41_lab.company_tenant_links", "aia_p44_lab.owner_keys",
}
CASE_IDS = {
    "MAIN": "ffffffff-ffff-4fff-8fff-ffffffffffff",
    "REVOKED": "eeeeeeee-eeee-4eee-8eee-eeeeeeeeeeee",
    "UNKNOWN": "dddddddd-dddd-4ddd-8ddd-dddddddddddd",
    "RESULT": "cccccccc-cccc-4ccc-8ccc-cccccccccccc",
    "PENDING": "bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb",
}


def rpc(lab: CaptureLab, mode: str, *, which: str = "src", **payload) -> dict:
    return lab._run_php(which, PROBE, mode, payload)


def fresh_event(case: str | None = None) -> dict:
    # Original ingress UUIDs remain the task references. Descending fixtures
    # keep a new pending case ahead of older expired leases in the P6 order.
    event_id = CASE_IDS[case] if case is not None else str(uuid.uuid4())
    return make_event(COMPANY_A, "SYNTHETIC_TENANT_A", event_id, "SYNTHETIC_KEY_A", KEY_A)


def source_of(body: dict) -> dict:
    payload = json.loads(base64.b64decode(body["payload_b64"], validate=True))
    return {"company_id": body["company_id"], "tenant_ref": body["envelope"]["tenant_ref"],
            "event_ref": body["envelope"]["event_ref"], "lead_id": str(uuid.UUID(payload["lead_id"])),
            "source_payload_hash": body["envelope"]["payload_hash"]}


def rows(lab: CaptureLab, table: str, which: str = "src", *, ledger_clock: bool = True) -> list:
    require(table in TABLES, "LEAD_SNAPSHOT_TABLE_ALLOWLIST")
    projection = "to_jsonb(t)" if ledger_clock or table != "aia_p26_native.executions" else "to_jsonb(t)-'updated_at'"
    return json.loads(lab._sql(which, "SELECT COALESCE(jsonb_agg(" + projection + " ORDER BY " +
                              TABLES[table] + "),'[]'::jsonb) FROM " + table + " t"))


def snapshot(lab: CaptureLab, which: str = "src") -> dict:
    # All row snapshots share one PostgreSQL statement/snapshot. Three small
    # RPCs per full check also leave the real task/owner clock budget intact.
    query = "SELECT jsonb_build_object(" + ",".join(
        "'" + table + "',(SELECT COALESCE(jsonb_agg(to_jsonb(t) ORDER BY " + order +
        "),'[]'::jsonb) FROM " + table + " t)" for table, order in TABLES.items()) + ")"
    contents = json.loads(lab._sql(which, query))
    tables = {}
    for table in TABLES:
        values = contents[table]
        tables[table] = {"rows": len(values), "sha256": hashlib.sha256(canonical(values)).hexdigest()}
    # No object IDs. pg_dump may omit an explicit owner-only default table
    # ACL; compare its effective tuples exactly, as the capture restore does.
    # Other catalog definitions remain exact, without cast normalization.
    objects = json.loads(lab._sql(which, """SELECT jsonb_agg(jsonb_build_object(
      'table',n.nspname||'.'||c.relname,'owner',pg_get_userbyid(c.relowner),
      'acl',(SELECT jsonb_agg(jsonb_build_array(pg_get_userbyid(a.grantor),
                   CASE WHEN a.grantee=0 THEN 'PUBLIC' ELSE pg_get_userbyid(a.grantee) END,
                   a.privilege_type,a.is_grantable) ORDER BY pg_get_userbyid(a.grantor),
                   CASE WHEN a.grantee=0 THEN 'PUBLIC' ELSE pg_get_userbyid(a.grantee) END,
                   a.privilege_type,a.is_grantable)
              FROM aclexplode(COALESCE(c.relacl,acldefault('r',c.relowner))) a),
      'rls',c.relrowsecurity,'force_rls',c.relforcerowsecurity,
      'columns',(SELECT jsonb_agg(jsonb_build_array(a.attname,format_type(a.atttypid,a.atttypmod),a.attnotnull)
                   ORDER BY a.attnum) FROM pg_attribute a WHERE a.attrelid=c.oid
                   AND a.attnum>0 AND NOT a.attisdropped),
      'keys',(SELECT jsonb_agg(jsonb_build_array(k.conname,k.contype,k.convalidated,k.condeferrable,
                   pg_get_constraintdef(k.oid)) ORDER BY k.conname) FROM pg_constraint k
                   WHERE k.conrelid=c.oid AND (k.contype IN ('p','u','f')
                     OR c.oid='aia_lead_ingest_lab.captured_job_sources'::regclass)),
      'policies',(SELECT jsonb_agg(jsonb_build_array(p.polname,p.polcmd,p.polpermissive,
                   (SELECT array_agg(pg_get_userbyid(roleid) ORDER BY pg_get_userbyid(roleid))
                      FROM unnest(p.polroles) roleid),
                   pg_get_expr(p.polqual,p.polrelid),pg_get_expr(p.polwithcheck,p.polrelid)) ORDER BY p.polname)
                   FROM pg_policy p WHERE p.polrelid=c.oid),
      'triggers',(SELECT jsonb_agg(jsonb_build_array(t.tgname,t.tgenabled,pg_get_triggerdef(t.oid)) ORDER BY t.tgname)
                   FROM pg_trigger t WHERE t.tgrelid=c.oid AND NOT t.tgisinternal)) ORDER BY n.nspname,c.relname)
      FROM pg_class c JOIN pg_namespace n ON n.oid=c.relnamespace
      WHERE c.relkind='r' AND (n.nspname LIKE 'aia\\_%' ESCAPE '\\'
        OR n.nspname='auto_ia_lab' OR (n.nspname='public' AND c.relname='aia_lab_n8n_outbox_p6'))"""))
    return {"tables": tables, "objects": objects, "capture_metadata": capture_metadata(lab, which)}


def require_snapshot(source: dict, restored: dict, label: str) -> None:
    if source == restored:
        return
    # Only table names/counts/digests and metadata component names are logged.
    # Captured bytes, signing keys, SQL bodies and row values stay private.
    differences = {"tables": {name: {"source": value, "restored": restored["tables"].get(name)}
                               for name, value in source["tables"].items()
                               if value != restored["tables"].get(name)}}
    old_objects = {item["table"]: item for item in source["objects"]}
    new_objects = {item["table"]: item for item in restored["objects"]}
    differences["object_names"] = sorted(name for name in set(old_objects) | set(new_objects)
                                          if old_objects.get(name) != new_objects.get(name))
    differences["capture_metadata_keys"] = sorted(name for name in source["capture_metadata"]
        if source["capture_metadata"][name] != restored["capture_metadata"].get(name))
    print(label + "_DIFFERENCES " + json.dumps(differences, sort_keys=True), flush=True)
    raise Failure(label)


def readonly_audits(lab: CaptureLab, count: int, which: str = "src") -> None:
    before = snapshot(lab, which)
    result = rpc(lab, "audits", which=which)
    require(result.get("schema") == "LEAD_DOMAIN_AUDITS_V1", "LEAD_DOMAIN_AUDIT_SCHEMA")
    for name, decision in (("atomic", "LAB_CONSISTENT_NO_AUTHORIZATION"),
                           ("lineage", "LAB_HISTORICAL_OWNER_LINEAGE_CONSISTENT_NO_GO")):
        audit = result.get(name, {})
        require(audit.get("decision") == decision and audit.get("checked") == count and
                audit.get("issues") == [] and audit.get("external_calls") == 0 and audit.get("ai_calls") == 0,
                "LEAD_DOMAIN_" + name.upper() + "_AUDIT_FAILED")
    require_snapshot(before, snapshot(lab, which), "LEAD_READONLY_DOMAIN_AUDIT_CHANGED_STATE")


def lineage(lab: CaptureLab, which: str = "src") -> int:
    counts = json.loads(lab._sql(which, """SELECT jsonb_build_object('sources',count(*),
      'complete',count(*) FILTER (WHERE c.event_ref IS NOT NULL AND i.event_ref IS NOT NULL
        AND r.event_ref IS NOT NULL AND o.event_ref IS NOT NULL AND g.event_ref IS NOT NULL
        AND c.company_id=s.company_id AND c.payload_hash=s.source_payload_hash
        AND i.payload_hash=s.source_payload_hash AND r.payload_hash=s.task_payload_hash
        AND o.event_hash=s.task_payload_hash AND g.payload_hash=s.task_payload_hash
        AND g.company_id=s.company_id AND c.state='CAPTURED_NOT_DISPATCHED'
        AND encode(public.digest(c.payload_bytes,'sha256'),'hex')=s.source_payload_hash
        AND encode(public.digest(convert_to(o.canonical_event,'UTF8'),'sha256'),'hex')=s.task_payload_hash
        AND o.canonical_event::jsonb->>'contract'='SYNTHETIC_CRM_LEAD_INGEST_V1'
        AND o.canonical_event::jsonb->>'kind'='crm.lead.ingest'
        AND o.canonical_event::jsonb->>'event_ref'=s.event_ref
        AND o.canonical_event::jsonb->>'tenant_ref'=s.tenant_ref
        AND o.canonical_event::jsonb->>'company_id'=s.company_id::text
        AND o.canonical_event::jsonb->>'lead_id'=c.lead_id::text
        AND o.canonical_event::jsonb->>'source_payload_hash'=s.source_payload_hash
        AND g.canonical_decision::jsonb->>'operation'='lead.ingest'))
      FROM aia_lead_ingest_lab.captured_job_sources s
      LEFT JOIN auto_ia_lab.lead_ingest_payloads c ON c.tenant=s.tenant_ref
        AND c.product=s.source_product AND c.event_ref=s.event_ref
      LEFT JOIN auto_ia_lab.event_inbox i ON i.tenant=c.tenant AND i.product=c.product AND i.event_ref=c.event_ref
      LEFT JOIN aia_p16_lab.event_replay r ON r.tenant_ref=s.tenant_ref AND r.event_ref=s.event_ref
      LEFT JOIN public.aia_lab_n8n_outbox_p6 o ON o.tenant_ref=s.tenant_ref AND o.event_ref=s.event_ref
      LEFT JOIN aia_p44_lab.consumed_owner_grants g ON g.tenant_ref=s.tenant_ref AND g.event_ref=s.event_ref"""))
    require(counts["sources"] > 0 and counts["complete"] == counts["sources"], "LEAD_SOURCE_TASK_LINEAGE_BROKEN")
    require(lab._sql(which, """SELECT count(*) FROM aia_p26_native.executions e
      LEFT JOIN aia_lead_ingest_lab.captured_job_sources s USING(tenant_ref,event_ref)
      WHERE s.event_ref IS NULL OR e.payload_hash<>s.task_payload_hash OR e.company_id<>s.company_id""") == "0",
            "LEAD_EXECUTION_WITHOUT_CAPTURE_LINEAGE")
    require(lab._sql(which, """SELECT count(*) FROM aia_p38_lab.signed_ack_evidence e
      LEFT JOIN aia_p26_native.executions x USING(tenant_ref,event_ref)
      LEFT JOIN public.aia_lab_n8n_outbox_p6 o USING(tenant_ref,event_ref)
      WHERE x.event_ref IS NULL OR o.event_ref IS NULL OR o.state<>'ACKED'
        OR e.payload_hash<>x.payload_hash OR e.payload_hash<>o.event_hash
        OR e.canonical_receipt<>x.canonical_receipt OR e.signature_b64<>x.signature_b64""") == "0",
            "LEAD_ACK_EVIDENCE_LINEAGE_BROKEN")
    for table in ("central_auto_lab.mtls_nonce_ledger", "aia_p42_lab.consumed_transport_proofs"):
        present = lab._sql(which, "SELECT to_regclass('" + table + "') IS NOT NULL")
        require(present == "f" or lab._sql(which, "SELECT count(*) FROM " + table) == "0",
                "LEAD_SOURCE_MUST_NOT_INVENT_P7_PROOF")
    return counts["sources"]


def n8n_snapshot(stack: Stack) -> dict:
    return json.loads(stack.sql("""SELECT jsonb_build_object(
      'workflows',(SELECT COALESCE(jsonb_agg(jsonb_build_array(id,active) ORDER BY id),'[]'::jsonb) FROM workflow_entity),
      'executions',(SELECT COALESCE(jsonb_agg(to_jsonb(e) ORDER BY id),'[]'::jsonb) FROM execution_entity e),
      'credentials',(SELECT count(*) FROM credentials_entity))"""))


def capture_volume(stack: Stack, path: Path) -> None:
    run(["docker", "run", "--rm", "--network", "none", "--read-only", "--cap-drop", "ALL",
         "--security-opt", "no-new-privileges:true", "--user", "1000:1000",
         "-v", stack.project + "_n8n_data:/input:ro", "--entrypoint", "tar", stack.env["N8N_PG_IMAGE"],
         "-C", "/input", "-cf", "-", "."], "LEAD_CAPTURE_NATIVE_VOLUME", output=path)


def restore_joint(lab: CaptureLab, stack: Stack, directory: Path, flows: dict, contexts: dict) -> dict:
    recovery = directory / "joint-recovery"
    recovery.mkdir(mode=0o700)
    prefix = "imagine-lead-" + os.environ["GITHUB_RUN_ID"] + "-" + os.environ["GITHUB_RUN_ATTEMPT"]
    target = Stack(prefix + "-restored", recovery / "native-secrets", stack.env["N8N_PG_IMAGE"])
    original_cli = stack.cli

    def no_execute(*args, **kwargs):
        raise Failure("LEAD_RECOVERY_NATIVE_EXECUTION_FORBIDDEN")

    stack.cli = no_execute
    target.cli = no_execute
    try:
        target.claim_empty()
        require(not stack.dc("ps", "-q", "--status", "running", "n8n").stdout.strip(),
                "LEAD_RECOVERY_SOURCE_APPLICATION_NOT_STOPPED")
        for service in ("nginx", "fpm"):
            lab.remove_owned(lab.project + "-" + service)
        source_state = snapshot(lab)
        source_count = lineage(lab)
        original_n8n = n8n_snapshot(stack)
        require(all(row[1] is False for row in original_n8n["workflows"]) and
                original_n8n["credentials"] == 0 and len(original_n8n["workflows"]) == len(flows),
                "LEAD_RECOVERY_ACTIVE_FLOW_OR_CREDENTIAL")
        require(stack.sql("SELECT count(*) FROM execution_entity WHERE status IS NULL OR "
                          "status NOT IN ('success','error','canceled','crashed')") == "0",
                "LEAD_RECOVERY_UNFINISHED_NATIVE_EXECUTION")
        dump_path = recovery / "agents.dump"
        run(["docker", "exec", lab.containers["src"], "pg_dump", "-U", "postgres", "-d",
             "imagine_agents_ci", "--format", "custom"], "LEAD_DUMP_AGENTS_OWNER_ACL", output=dump_path)
        dumped = dump_path.read_bytes()
        require(dumped.startswith(b"PGDMP") and len(dumped) < 64 * 1024 * 1024, "LEAD_AGENTS_DUMP_FORMAT")
        dump_hash = hashlib.sha256(dumped).hexdigest()
        # Current revocations are made after the dump and kept outside it.
        current = rpc(lab, "quarantine")
        require(current.get("schema") == "LEAD_EXECUTION_QUARANTINED_V1" and
                current.get("business_grants_reenabled") == 0 and current.get("expiry_windows_extended") == 0,
                "LEAD_CURRENT_REVOCATION_FIXTURE")
        revoked_source = snapshot(lab)
        private_write(recovery / "revocations-outside-backup.json", canonical({
            "dump_sha256": dump_hash, "current": current,
            "authority": {name: revoked_source["tables"][name] for name in sorted(AUTHORITY_TABLES)}}))
        stack.dc("exec", "-T", "n8n-db", "pg_dump", "-U", "n8n_bootstrap_admin", "-d", "n8n",
                 "--format", "custom", "--no-owner", "--no-acl", output=recovery / "n8n.dump")
        native_dump = (recovery / "n8n.dump").read_bytes()
        require(native_dump.startswith(b"PGDMP") and len(native_dump) < 64 * 1024 * 1024,
                "LEAD_NATIVE_DUMP_FORMAT")
        capture_volume(stack, recovery / "n8n-data.tar")
        original_volume = volume_manifest(recovery / "n8n-data.tar")
        key = (stack.directory / "encryption").read_bytes()
        private_write(recovery / "n8n-encryption.key", key)

        lab._start("dst")
        for role in ROLES:
            lab._sql("dst", "CREATE ROLE " + role +
                     " NOLOGIN NOINHERIT NOSUPERUSER NOBYPASSRLS NOCREATEDB NOCREATEROLE NOREPLICATION")
        require(hashlib.sha256(dump_path.read_bytes()).hexdigest() == dump_hash, "LEAD_AGENTS_DUMP_CHANGED")
        run(["docker", "exec", "-i", lab.containers["dst"], "pg_restore", "-U", "postgres", "-d",
             "imagine_agents_ci", "--exit-on-error"], "LEAD_RESTORE_AGENTS_OWNER_ACL", stdin=dumped)
        restored = snapshot(lab, "dst")
        require_snapshot(source_state, restored, "LEAD_JOINT_RESTORED_SNAPSHOT_DIFFERS")
        permissions(lab, "dst")
        immutable_checks(lab, "dst")
        require(lineage(lab, "dst") == source_count, "LEAD_RESTORED_LINEAGE_COUNT")
        for role in ROLES:
            require(lab._sql("dst", "SELECT NOT(rolcanlogin OR rolinherit OR rolsuper OR rolbypassrls "
                            "OR rolcreatedb OR rolcreaterole OR rolreplication) FROM pg_roles WHERE rolname='" +
                            role + "'") == "t" and
                    lab._sql("dst", "SELECT count(*) FROM pg_auth_members WHERE member='" + role +
                             "'::regrole") == "0", "LEAD_RESTORED_ROLE_OR_MEMBERSHIP")
        require(rpc(lab, "quarantine", which="dst") == current, "LEAD_RESTORED_REVOCATION_DELTA")
        quarantined = snapshot(lab, "dst")
        require_snapshot(revoked_source, quarantined, "LEAD_RESTORED_CURRENT_QUARANTINE_DIFFERS")
        stable_ledger = rows(lab, "aia_p26_native.executions", "dst", ledger_clock=False)
        require(rpc(lab, "reconcile", which="dst", context=contexts["MAIN"]).get("state") == "ACKED",
                "LEAD_RESTORED_HISTORICAL_ACK_LOST")
        require(rpc(lab, "reconcile", which="dst", context=contexts["UNKNOWN"]).get("state") == "UNKNOWN",
                "LEAD_RESTORED_UNKNOWN_RETRIED")
        result = rpc(lab, "reconcile", which="dst", context=contexts["RESULT"])
        require(result.get("decision") == "DENY" and result.get("state") == "RESULT",
                "LEAD_RESTORED_RESULT_MUST_NOT_GAIN_ACK")
        for context in contexts.values():
            require(rpc(lab, "begin", which="dst", context=context).get("decision") == "DENY",
                    "LEAD_RESTORED_EXECUTOR_REENABLED")
        require(rpc(lab, "lease", which="dst", context=contexts["PENDING"]).get("decision") == "DENY",
                "LEAD_RESTORED_PENDING_LEASE_REENABLED")
        after = snapshot(lab, "dst")
        require(all(after["tables"][name] == quarantined["tables"][name]
                    for name in TABLES if name != "aia_p26_native.executions") and
                rows(lab, "aia_p26_native.executions", "dst", ledger_clock=False) == stable_ledger,
                "LEAD_RESTORED_RECONCILIATION_CHANGED_DURABLE_RESULT")
        require(lineage(lab, "dst") == source_count, "LEAD_RESTORED_RECONCILIATION_LINEAGE")
        readonly_audits(lab, source_count, "dst")

        normalized = recovery / "normalized-volume.tar"
        normalized_volume(recovery / "n8n-data.tar", normalized)
        require(volume_manifest(normalized, normalized=True) == original_volume, "LEAD_VOLUME_NORMALIZATION_CONTENT")
        target_key = target.directory / "encryption"
        target_key.chmod(0o600); target_key.write_bytes(key); target_key.chmod(0o444)
        target.dc("up", "-d", "--wait", "--wait-timeout", "120", "n8n-db", timeout=180)
        target.dc("exec", "-T", "n8n-db", "pg_restore", "-U", "n8n_bootstrap_admin", "-d", "n8n",
                  "--exit-on-error", "--no-owner", "--no-acl", "--role=n8n_runtime", stdin=native_dump)
        require(n8n_snapshot(target) == original_n8n, "LEAD_RESTORED_NATIVE_DATABASE_DIFFERS")
        target.dc("create", "n8n")
        run(["docker", "run", "--rm", "--network", "none", "--read-only", "--security-opt", "no-new-privileges:true",
             "-v", str(normalized) + ":/input.tar:ro", "-v", target.project + "_n8n_data:/output",
             "--entrypoint", "/bin/sh", target.env["N8N_PG_IMAGE"], "-eu", "-c",
             'test -z "$(ls -A /output)"; tar -xf /input.tar -C /output --numeric-owner'], "LEAD_RESTORE_NATIVE_VOLUME")
        capture_volume(target, recovery / "restored-volume.tar")
        require(volume_manifest(recovery / "restored-volume.tar", normalized=True) == original_volume,
                "LEAD_RESTORED_NATIVE_VOLUME_DIFFERS")
        target.dc("up", "-d", "--wait", "--wait-timeout", "180", "n8n", timeout=240)
        target.inspect_runtime()
        target.dc("stop", "n8n")
        target.export_json("workflow", recovery / "restored-workflows.json")
        exported = parse_json(recovery / "restored-workflows.json")
        require(isinstance(exported, list) and len(exported) == len(flows), "LEAD_RESTORED_NATIVE_FLOW_COUNT")
        for flow in flows.values():
            verify_export(flow, exported)
        require(n8n_snapshot(target) == original_n8n and target_key.read_bytes() == key,
                "LEAD_NATIVE_RESTORE_EXECUTED_OR_CHANGED_HISTORY")
        return {"schema": "CRM_LEAD_JOINT_RESTORE_VERIFIED_V1", "exact_table_snapshots": len(TABLES),
                "capture_sidecar_replay_outbox_owner_ledger_evidence_restored": True,
                "source_task_result_ack_lineage_preserved": True, "roles_remain_nologin": True,
                "original_atomic_and_lineage_audits_readonly_after_revocation": True,
                "post_snapshot_revocations_applied": True, "historical_ack_retained": True,
                "unknown_automatic_retries": 0, "result_preserved_without_new_ack": True,
                "n8n_pg17_volume_key_restored": True, "inactive_workflows_verified": len(flows),
                "native_execution_calls_during_recovery": 0, "native_execution_table_snapshot_preserved": True,
                "external_backup_verified": False, "different_host_restore_verified": False,
                "real_current_authority_verified": False, "go_for_installation": False}
    finally:
        stack.cli = original_cli
        target.cleanup()


def exercise(lab: CaptureLab, stack: Stack, directory: Path) -> dict:
    source_template = template(PRIVATE / "workflows/native-crm-lead-noai.workflow.json")
    legacy_template = template(PRIVATE / "workflows/native-run-status-noai.workflow.json")
    flows, contexts = {}, {}
    observed_executions = 0
    stack.claim_empty()
    stack.dc("up", "-d", "--wait", "--wait-timeout", "180", timeout=240)
    stack.inspect_runtime()
    stack.dc("stop", "n8n")
    lab.prepare()
    lab._sql("src", (ROOT / "runtime/central-auto/lead-ingest-capture.sql").read_text())
    lab.profile = "immutable-v1"
    lab.start_http()
    require(rpc(lab, "init").get("schema") == "LEAD_EXECUTION_LAB_READY_V1", "LEAD_EXECUTION_INIT")

    def proposal(case: str, source: dict) -> tuple[dict, str]:
        seed = base64.b64encode(secrets.token_bytes(32)).decode()
        p = rpc(lab, "prepare", case=case, source=source, seed_b64=seed)
        require(p.get("schema") == "LEAD_PREPARED_PROPOSAL_V1", "LEAD_OWNER_PROPOSAL_" + case)
        require(p["context"]["event_ref"] == source["event_ref"] and
                p["context"]["payload_hash"] != source["source_payload_hash"], "LEAD_SOURCE_AND_TASK_HASH_SCOPE")
        return p, seed

    def stage(case: str, body: dict | None = None, *, lease: bool = True) -> tuple[dict, str, dict]:
        body = body or fresh_event(case)
        if body["envelope"]["event_ref"] != main_body["envelope"]["event_ref"]:
            check_ack(lab, body)
        captured = expected_row(lab, body)
        p, seed = proposal(case, source_of(body))
        if case == "MAIN":
            before_stage = snapshot(lab)
            # AgentsRecovery._run_php uses two independent unnamed --rm
            # containers/connections; neither a shared process nor a fake clock.
            with ThreadPoolExecutor(max_workers=2) as pool:
                attempts = [pool.submit(rpc, lab, "stage", proposal=p) for _ in range(2)]
                decisions = [future.result().get("decision") for future in attempts]
            require(sorted(decisions) == ["DENY", "LAB_CAPTURED_LEAD_STAGED_NO_DELIVERY"],
                    "LEAD_CONCURRENT_STAGE_MUST_HAVE_ONE_WINNER")
            after_stage = snapshot(lab)
            changed = {SIDECAR, "aia_p16_lab.event_replay", "public.aia_lab_n8n_outbox_p6",
                       "aia_p44_lab.consumed_owner_grants"}
            require(all(after_stage["tables"][name]["rows"] == before_stage["tables"][name]["rows"] + 1
                        for name in changed) and
                    all(after_stage["tables"][name] == before_stage["tables"][name]
                        for name in TABLES if name not in changed) and
                    after_stage["objects"] == before_stage["objects"] and
                    after_stage["capture_metadata"] == before_stage["capture_metadata"],
                    "LEAD_CONCURRENT_STAGE_PARTIAL_OR_DUPLICATE_WRITE")
        else:
            require(rpc(lab, "stage", proposal=p).get("decision") == "LAB_CAPTURED_LEAD_STAGED_NO_DELIVERY",
                    "LEAD_ATOMIC_OWNER_BOUND_STAGE_" + case)
        staged_snapshot = snapshot(lab)
        require(rpc(lab, "stage", proposal=p).get("decision") == "DENY" and snapshot(lab) == staged_snapshot,
                "LEAD_DUPLICATE_STAGE_WRITES")
        flow, bound = materialize(source_template, p["context"])
        stack.import_json("workflow", canonical(flow))
        exported = directory / (case.lower() + "-export.json")
        stack.export_json("workflow", exported)
        verify_export(flow, parse_json(exported))
        flows[case], contexts[case] = flow, bound
        if lease:
            reserved = rpc(lab, "lease", context=bound)
            require(reserved.get("decision") == "LAB_LEASE_RESERVED_NO_DELIVERY" and
                    reserved.get("event_ref") == bound["event_ref"] and reserved.get("attempt") == 1,
                    "LEAD_OWNER_BOUND_LEASE_" + case)
        require(expected_row(lab, body) == captured, "LEAD_STAGE_REWROTE_CAPTURE")
        return bound, seed, body

    def begin(bound: dict) -> None:
        result = rpc(lab, "begin", context=bound)
        require(result.get("decision") == "P26_CALL_ONCE" and result.get("state") == "STARTED",
                "LEAD_DURABLE_INTENT_REQUIRED")

    def execute(bound: dict, seed: str) -> dict:
        nonlocal observed_executions
        result = parse_output(stack.cli("execute", "--id=" + bound["workflow_id"], "--rawOutput", timeout=50).stdout)
        verify_result(result, bound)
        output = expected(bound)
        require(output["operation"] == "lead.ingest" and output["lead_status"] == "PREPARED" and
                output["crm_mutated"] is False and output["ai_api_calls"] == 0,
                "LEAD_NATIVE_RESULT_NOT_PREPARED")
        observed_executions += 1
        recorded = rpc(lab, "record-result", context=bound, seed_b64=seed, execution=result)
        require(recorded.get("decision") == "P26_RESULT_DURABLE" and recorded.get("state") == "RESULT",
                "LEAD_NATIVE_RESULT_NOT_DURABLE")
        return result

    main_body = fresh_event("MAIN")
    check_ack(lab, main_body)
    captured = expected_row(lab, main_body)
    require(rpc(lab, "stats")["outbox"] == 0, "LEAD_HTTP_RECEIPT_DISPATCHED_WITHOUT_OWNER")
    before_retry = lab.counts()
    check_ack(lab, main_body, "LAB_DUPLICATE_NO_DELIVERY")
    require(lab.counts() == {**before_retry, "audit": before_retry["audit"] + 1} and
            expected_row(lab, main_body) == captured, "LEAD_HTTP_RETRY_DUPLICATED_CAPTURE")
    normal_source = source_of(main_body)
    negatives = {
        "ABSENT": source_of(fresh_event()),
        "FOREIGN": {**normal_source, "company_id": COMPANY_B, "tenant_ref": "SYNTHETIC_TENANT_B"},
        "HASH": {**normal_source, "source_payload_hash": "0" * 64},
        "RUN_GRANT": normal_source,
    }
    for case, source in negatives.items():
        p, _ = proposal(case, source)
        before = snapshot(lab)
        require(rpc(lab, "stage", proposal=p).get("decision") == "DENY", "LEAD_STAGE_NEGATIVE_" + case)
        require(snapshot(lab) == before, "LEAD_STAGE_NEGATIVE_PARTIAL_WRITE_" + case)
    print("CRM_LEAD_OWNER_SOURCE_AND_RUN_GRANT_BOUNDARIES_PASS", flush=True)

    main, main_seed, _ = stage("MAIN", main_body)
    before = snapshot(lab)
    for substituted in ({**main, "workflow_sha256": "0" * 64},
                        {**main, "template_sha256": "0" * 64},
                        {**main, "company_id": COMPANY_B},
                        {**main, "tenant_ref": "SYNTHETIC_TENANT_B"}):
        require(rpc(lab, "begin", context=substituted).get("decision") == "DENY",
                "LEAD_CONTEXT_SUBSTITUTION_ACCEPTED")
    require(snapshot(lab) == before, "LEAD_CONTEXT_SUBSTITUTION_WROTE_INTENT")
    try:
        materialize(legacy_template, main)
    except ContractDenied:
        pass
    else:
        raise Failure("LEAD_RUN_TEMPLATE_SUBSTITUTION_ACCEPTED")
    begin(main)
    execute(main, main_seed)
    require(rpc(lab, "stats")["acked"] == 0 and rpc(lab, "stats")["evidence"] == 0,
            "LEAD_ACK_BEFORE_RECONCILIATION")
    del main_seed
    # Each RPC is a new PHP container/process. No observed output or private
    # executor key is supplied to reconciliation after RESULT committed.
    for _ in range(2):
        recovered = rpc(lab, "reconcile", context=main)
        require(recovered.get("decision") == "P26_ACK_EVIDENCE_CONFIRMED" and
                recovered.get("state") == "ACKED" and observed_executions == 1,
                "LEAD_RESTART_ACK_WITHOUT_REEXECUTION")
    # P27 deliberately seeds only B's receipt-read authority. A's read grant
    # is a separate explicit fixture; neither capture nor owner stage adds it.
    read_fp = fingerprint(lab.certificates["client-a"][0])
    lab._sql("src", "INSERT INTO auto_ia_lab.receipt_read_grants VALUES(" + literal(COMPANY_A) + "," +
             literal(read_fp) + ",'SYNTHETIC_TENANT_A','event.receipt.read',true,1," +
             "floor(extract(epoch FROM clock_timestamp()))::bigint+1800)")
    query = make_query(COMPANY_A, normal_source["tenant_ref"], normal_source["event_ref"],
                       normal_source["source_payload_hash"])
    before_receipt = lab.counts()
    verify_receipt(lab.http(query), query, lab.secrets["receipt-key"].read_bytes())
    require(lab.counts() == before_receipt and expected_row(lab, main_body) == captured,
            "LEAD_EXECUTION_OR_RECEIPT_REWROTE_INGRESS_CAPTURE")
    print("CRM_LEAD_REAL_MTLS_TO_NATIVE_N8N_RESULT_AND_ACK_PASS", flush=True)

    revoked, _, _ = stage("REVOKED")
    rpc(lab, "revoke-owner", context=revoked)
    before = snapshot(lab)
    require(rpc(lab, "begin", context=revoked).get("decision") == "DENY" and
            snapshot(lab) == before and observed_executions == 1, "LEAD_POST_LEASE_REVOCATION_CALLED_NATIVE")
    unknown, _, _ = stage("UNKNOWN")
    begin(unknown)
    for _ in range(2):
        require(rpc(lab, "reconcile", context=unknown).get("state") == "UNKNOWN" and
                rpc(lab, "begin", context=unknown).get("decision") == "DENY" and observed_executions == 1,
                "LEAD_UNKNOWN_AUTOMATIC_RETRY")
    retained, retained_seed, _ = stage("RESULT")
    begin(retained); execute(retained, retained_seed)
    rpc(lab, "revoke-owner", context=retained)
    denied = rpc(lab, "reconcile", context=retained)
    require(denied.get("decision") == "DENY" and denied.get("state") == "RESULT",
            "LEAD_POST_EXECUTION_REVOCATION_GAINED_ACK")
    stage("PENDING", lease=False)
    totals = rpc(lab, "stats")
    states = [entry["state"] for entry in totals["states"]]
    require(totals["acked"] == 1 and totals["evidence"] == 1 and totals["sources"] == 5 and
            totals["outbox"] == 5 and sorted(states) == ["ACKED", "RESULT", "UNKNOWN"] and
            observed_executions == 2 and lineage(lab) == 5 and lab.counts()["ai_requests"] == 0,
            "LEAD_FINAL_DURABLE_COUNTS")
    readonly_audits(lab, 5)
    recovery = restore_joint(lab, stack, directory, flows, contexts)
    require(observed_executions == 2, "LEAD_RECOVERY_CALLED_NATIVE")
    return {"schema": "CRM_LEAD_EXECUTION_VERIFIED_V1", "real_synthetic_mtls_capture": True,
            "owner_authorization_separate_from_received_ack": True, "p7_source_proofs_invented": 0,
            "capture_source_hash_and_task_hash_distinct": True, "source_task_result_ack_lineage_verified": True,
            "real_n8n_executions_observed": observed_executions,
            "execution_observation_source": "trusted_ci_cli_output", "lead_status": "PREPARED",
            "crm_mutated": False, "acked": 1, "atomic_signed_ack_evidence": 1,
            "restart_after_durable_result_without_second_execution": True,
            "missing_source_cross_company_hash_and_run_grant_denied": True,
            "duplicate_http_and_stage_do_not_create_another_task": True,
            "concurrent_stage_has_one_atomic_admission": True,
            "original_atomic_and_lineage_audits_are_readonly": True,
            "template_and_context_substitution_denied": True,
            "post_lease_revocation_denies_native_call": True, "unknown_automatic_retries": 0,
            "post_execution_revocation_retains_result_without_ack": True,
            "received_ack_dispatch": "DISABLED", "joint_state_recovery": recovery,
            "real_owner_iam": False, "real_n8n_signer_custody": False,
            "ai_api_calls": 0, "credits_debited": 0, "host_published_ports": 0, "go_for_installation": False}


def main() -> dict:
    require(os.environ.get("GITHUB_ACTIONS") == "true" and
            os.environ.get("GITHUB_REPOSITORY") == "rafhael100/imagine-ai-agents" and
            os.environ.get("P24_RUNNER_ENVIRONMENT") == "github-hosted", "LEAD_EXECUTION_HOSTED_CI_ONLY")
    require(not os.environ.get("DOCKER_HOST") and not os.environ.get("DOCKER_CONTEXT"),
            "LEAD_EXECUTION_REMOTE_DOCKER_FORBIDDEN")
    require(run(["docker", "context", "show"], "LEAD_DOCKER_CONTEXT").stdout.strip() == b"default",
            "LEAD_DEFAULT_DOCKER_CONTEXT")
    docker_context = json.loads(run(["docker", "context", "inspect", "default"], "LEAD_DOCKER_CONTEXT").stdout)
    require(docker_context[0]["Endpoints"]["docker"]["Host"] == "unix:///var/run/docker.sock",
            "LEAD_LOCAL_DOCKER_SOCKET")
    run_id, attempt = os.environ.get("GITHUB_RUN_ID", ""), os.environ.get("GITHUB_RUN_ATTEMPT", "")
    require(re.fullmatch(r"[0-9]{1,20}", run_id) is not None and re.fullmatch(r"[0-9]{1,4}", attempt) is not None,
            "LEAD_EXECUTION_RUN_ID")
    pg_image = os.environ.get("N8N_PG_IMAGE", "")
    require(re.fullmatch(r"(?:docker.io/library/)?postgres@sha256:[a-f0-9]{64}", pg_image) is not None,
            "LEAD_EXECUTION_PG_DIGEST")
    require(hashlib.sha256((ROOT / "runtime/central-auto/index.php").read_bytes()).hexdigest() == INDEX_SHA256,
            "LEAD_EXECUTION_INGRESS_SOURCE_CHANGED")
    os.umask(0o077)
    with tempfile.TemporaryDirectory(prefix="central-lead-execution-", dir=os.environ["RUNNER_TEMP"]) as tmp:
        directory = Path(tmp)
        prefix = "imagine-lead-" + run_id + "-" + attempt
        stack = Stack(prefix + "-native", directory / "native-secrets", pg_image)
        lab = CaptureLab(prefix, directory / "agents-state")
        try:
            return exercise(lab, stack, directory)
        finally:
            failures = []
            for resource in (lab, stack):
                try:
                    resource.cleanup()
                except Exception:
                    failures.append("LEAD_OWNED_CLEANUP")
            require(not failures, "LEAD_OWNED_RESOURCE_CLEANUP_FAILED")


if __name__ == "__main__":
    try:
        print(json.dumps(main(), sort_keys=True))
        print("CENTRAL_AUTO_CRM_LEAD_EXECUTION_PASS NO_GO", flush=True)
    except (Failure, ContractDenied, NativeDenied, ValueError, KeyError, TypeError, OSError,
            subprocess.SubprocessError) as error:
        label = str(error)
        if re.fullmatch(r"[A-Z0-9_]{1,120}", label) is None:
            label = "UNTRUSTED_DIAGNOSTIC_SUPPRESSED"
        print("CENTRAL_AUTO_CRM_LEAD_EXECUTION_DENIED_" + label, file=sys.stderr)
        raise SystemExit(1)
