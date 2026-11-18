#!/usr/bin/env python3
"""Exercise immutable CRM lead capture with the existing isolated P27/P29 lab.

The original receipt is still a receipt, never an execution acknowledgement.
This proof does not connect n8n, start a worker or authorize a business effect.
"""
from __future__ import annotations

import base64
import hashlib
import hmac
import json
import os
import secrets
import subprocess
import sys
import tempfile
import time
import uuid
from pathlib import Path

from check_p24_full_n8n_restore import Failure, ROOT, require, run
from check_p27_private_receipt_status import (
    COMPANY_A, COMPANY_B, KEY_A, KEY_B, ReceiptLab, closed_json, fingerprint,
    main as receipt_matrix, make_event, make_query, verify_ack, verify_receipt, wire,
)
from check_p29_ingress_boundaries import (
    EVENT_DENIED, INGRESS_FIELDS, abort_sql, additional_checks as p29_checks, finish_sql,
    fresh, literal, signed, start_sql, wait_for,
)

INDEX_SHA256 = "b1508dbc180beb51c3a864401229e8d52f7709b996179f6e8257594b157ee1b4"
CAPTURE = "auto_ia_lab.lead_ingest_payloads"
FUNCTION = "auto_ia_lab.receive_company_signed_lead(uuid,text,jsonb,text)"
ROLES = ("aia_lab_owner", "aia_lab_tenant_a", "aia_lab_tenant_b",
         "aia_private_transport", "aia_private_receipt", "aia_private_receipt_login")


class CaptureLab(ReceiptLab):
    """Add only a server-side FPM profile; reuse all P27 transport isolation."""

    profile: str | None = None
    fpm_arguments: list[str] | None = None

    def service(self, kind: str, arguments: list[str]) -> None:
        if kind == "fpm":
            self.fpm_arguments = list(arguments)
            if self.profile is not None:
                arguments = ["-e", "AGENTS_PRIVATE_LEAD_CAPTURE=" + self.profile] + arguments
        super().service(kind, arguments)

    def restart_profile(self, profile: str | None) -> None:
        require(self.fpm_arguments is not None, "CAPTURE_FPM_NOT_STARTED")
        arguments = list(self.fpm_arguments)
        self.remove_owned(self.project + "-fpm")
        self.profile = profile
        self.service("fpm", arguments)
        for _ in range(20):
            if self.php("ready") == {"fpm": True, "https": True}:
                return
            time.sleep(0.1)
        raise Failure("CAPTURE_FPM_RESTART_FAILED")

    def counts(self, which: str = "src") -> dict:
        return json.loads(self._sql(which, "SELECT json_build_object("
                         "'inbox',(SELECT count(*) FROM auto_ia_lab.event_inbox),"
                         "'audit',(SELECT count(*) FROM auto_ia_lab.service_event_audit),"
                         "'payloads',(SELECT count(*) FROM " + CAPTURE + "),"
                         "'ai_requests',(SELECT count(*) FROM auto_ia_lab.requests))"))

    def rows(self, which: str = "src") -> list[dict]:
        return json.loads(self._sql(which, "SELECT COALESCE(jsonb_agg(to_jsonb(p) "
                         "ORDER BY tenant,product,event_ref),'[]'::jsonb) FROM " + CAPTURE + " p"))


def check_ack(lab: CaptureLab, body: dict, decision: str = "LAB_RECEIVED_NO_DELIVERY",
              client: str = "client-a") -> None:
    verify_ack(lab.http(body, path="/private/events", client=client), body,
               lab.secrets["ack-key"].read_bytes(), decision)


def deny_event(lab: CaptureLab, body: dict | bytes, status: int = 403,
               client: str = "client-a") -> None:
    reply = lab.http(body, path="/private/events", client=client)
    require(reply["status"] == status and reply["cache_control"] == "no-store" and
            base64.b64decode(reply["body_b64"], validate=True) == EVENT_DENIED,
            "CAPTURE_HTTP_DENIAL")


def expected_row(lab: CaptureLab, body: dict, client: str = "client-a") -> dict:
    payload = base64.b64decode(body["payload_b64"], validate=True)
    decoded = json.loads(payload)
    envelope = body["envelope"]
    rows = [row for row in lab.rows() if row["event_ref"] == envelope["event_ref"] and
            row["tenant"] == envelope["tenant_ref"]]
    require(len(rows) == 1, "CAPTURE_ONE_BOUND_ROW")
    row = rows[0]
    expected = {"tenant": envelope["tenant_ref"], "product": "imagine-crm",
                "operation": "lead.ingest", "event_ref": envelope["event_ref"],
                "company_id": body["company_id"], "event_id": str(uuid.UUID(decoded["event_id"])),
                "lead_id": str(uuid.UUID(decoded["lead_id"])), "payload_bytes": "\\x" + payload.hex(),
                "payload_hash": hashlib.sha256(payload).hexdigest(),
                "nonce_ref": envelope["nonce_ref"], "request_ref": envelope["request_ref"],
                "issued_epoch": envelope["issued_at"], "expires_epoch": envelope["expires_at"],
                "fingerprint": fingerprint(lab.certificates[client][0]), "key_id": envelope["key_id"],
                "state": "CAPTURED_NOT_DISPATCHED"}
    require(all(row.get(name) == value for name, value in expected.items()), "CAPTURE_BYTES_OR_BINDING_CHANGED")
    require(type(row.get("grant_revision")) is int and row["grant_revision"] > 0 and
            type(row.get("company_revision")) is int and row["company_revision"] > 0,
            "CAPTURE_PROVENANCE_REVISIONS")
    return row


def capture_sql(body: dict, fp: str) -> str:
    payload = base64.b64decode(body["payload_b64"], validate=True).decode("utf-8")
    return ("SELECT auto_ia_lab.receive_company_signed_lead(" + literal(body["company_id"]) +
            "::uuid," + literal(fp) + "," + literal(wire(body["envelope"]).decode()) +
            "::jsonb," + literal(payload) + ");")


def role_call(lab: CaptureLab, body: dict, fp: str, which: str = "src") -> str:
    return lab._sql(which, "SET ROLE aia_private_transport;" + capture_sql(body, fp) + "RESET ROLE;")


def permissions(lab: CaptureLab, which: str = "src") -> None:
    require(lab._sql(which, "SELECT relrowsecurity AND relforcerowsecurity "
                    "AND pg_get_userbyid(relowner)='aia_lab_owner' FROM pg_class WHERE oid='" +
                    CAPTURE + "'::regclass") == "t", "CAPTURE_RLS_OWNER")
    for role in ROLES[1:]:
        for privilege in ("SELECT", "INSERT", "UPDATE", "DELETE", "TRUNCATE", "REFERENCES", "TRIGGER"):
            require(lab._sql(which, "SELECT has_table_privilege(" + literal(role) + "," +
                            literal(CAPTURE) + "," + literal(privilege) + ")") == "f",
                    "CAPTURE_DIRECT_TABLE_PRIVILEGE")
    for role, expected in (("aia_private_transport", "t"), ("aia_private_receipt", "f"),
                           ("aia_private_receipt_login", "f"), ("aia_lab_tenant_a", "f")):
        require(lab._sql(which, "SELECT has_function_privilege(" + literal(role) + "," +
                        literal(FUNCTION) + ",'EXECUTE')") == expected, "CAPTURE_EXECUTE_PRIVILEGE")
    require(lab._sql(which, "SELECT prosecdef AND pg_get_userbyid(proowner)='aia_lab_owner' "
                    "AND proconfig @> ARRAY['search_path=pg_catalog, auto_ia_lab'] FROM pg_proc WHERE oid='" +
                    FUNCTION + "'::regprocedure") == "t", "CAPTURE_DEFINER_SCOPE")
    require(lab._sql(which, "SELECT count(*) FROM pg_constraint WHERE conrelid='" + CAPTURE +
                    "'::regclass AND contype='f' AND confrelid='auto_ia_lab.event_inbox'::regclass "
                    "AND convalidated AND NOT condeferrable") == "1", "CAPTURE_INBOX_FOREIGN_KEY")


def immutable_checks(lab: CaptureLab, which: str = "src") -> None:
    before = lab.rows(which)
    require(bool(before), "CAPTURE_IMMUTABILITY_EMPTY_FIXTURE")
    for sql in ("UPDATE " + CAPTURE + " SET payload_bytes=payload_bytes;",
                "DELETE FROM " + CAPTURE + ";", "TRUNCATE " + CAPTURE + ";"):
        result = run(["docker", "exec", "-i", lab.containers[which], "psql", "-X", "-qAt",
                      "-v", "ON_ERROR_STOP=1", "-U", "postgres", "-d", "imagine_agents_ci"],
                     "CAPTURE_IMMUTABILITY_PROBE", stdin=sql.encode(), check=False)
        require(result.returncode != 0 and lab.rows(which) == before, "CAPTURE_IMMUTABILITY_BYPASSED")


def concurrent_duplicate(lab: CaptureLab) -> None:
    body = fresh(); fp = fingerprint(lab.certificates["client-a"][0])
    before = lab.counts()
    suffix = secrets.token_hex(5)
    owner_name, waiter_name = "capture-owner-" + suffix, "capture-waiter-" + suffix
    owner = waiter = None
    try:
        owner = start_sql(lab, owner_name, "BEGIN;SET LOCAL ROLE aia_private_transport;" + capture_sql(body, fp))
        wait_for(lab, "SELECT count(*) FROM pg_stat_activity WHERE application_name=" + literal(owner_name) +
                 " AND state='idle in transaction'", "CAPTURE_DUPLICATE_OWNER_NOT_READY", owner)
        waiter = start_sql(lab, waiter_name, "SET SESSION AUTHORIZATION aia_private_transport;" + capture_sql(body, fp))
        wait_for(lab, "SELECT count(*) FROM pg_stat_activity w JOIN pg_stat_activity b "
                 "ON b.pid=ANY(pg_blocking_pids(w.pid)) WHERE w.application_name=" + literal(waiter_name) +
                 " AND b.application_name=" + literal(owner_name) + " AND w.wait_event_type='Lock'",
                 "CAPTURE_DUPLICATE_REAL_WAIT_NOT_OBSERVED", waiter)
        require(finish_sql(owner, "COMMIT;") == "LAB_RECEIVED_NO_DELIVERY", "CAPTURE_CONCURRENT_FIRST")
        require(finish_sql(waiter) == "LAB_DUPLICATE_NO_DELIVERY", "CAPTURE_CONCURRENT_DUPLICATE")
        require(lab.counts() == {"inbox": before["inbox"] + 1, "audit": before["audit"] + 2,
                                "payloads": before["payloads"] + 1, "ai_requests": 0},
                "CAPTURE_CONCURRENT_DUPLICATED_STORAGE")
        expected_row(lab, body)
    finally:
        abort_sql(waiter); abort_sql(owner)


def capture_expiry(lab: CaptureLab, grant_expiry: bool) -> None:
    body = fresh(); fp = fingerprint(lab.certificates["client-a"][0])
    before = lab.counts()
    now = int(lab._sql("src", "SELECT floor(extract(epoch FROM clock_timestamp()))::bigint"))
    deadline = now + 6
    body["envelope"]["issued_at"] = now
    body["envelope"]["expires_at"] = now + (60 if grant_expiry else 6)
    body = signed(body)
    lock_id = secrets.randbelow(1000000000) + 1000000
    lab._sql("src", "CREATE FUNCTION auto_ia_lab.capture_wait_fixture() RETURNS trigger LANGUAGE plpgsql AS $$ "
             "BEGIN PERFORM pg_advisory_xact_lock(" + str(lock_id) + ");RETURN NEW;END $$;"
             "CREATE TRIGGER capture_wait_fixture AFTER INSERT ON " + CAPTURE +
             " FOR EACH ROW EXECUTE FUNCTION auto_ia_lab.capture_wait_fixture();")
    if grant_expiry:
        lab._sql("src", "UPDATE auto_ia_lab.service_company_grants SET expires_at=" + str(deadline) +
                 " WHERE company_id=" + literal(COMPANY_A))
    suffix = secrets.token_hex(5)
    blocker_name, waiter_name = "capture-blocker-" + suffix, "capture-expiry-" + suffix
    blocker = waiter = None
    try:
        blocker = start_sql(lab, blocker_name, "BEGIN;SELECT pg_advisory_xact_lock(" + str(lock_id) + ");")
        wait_for(lab, "SELECT count(*) FROM pg_stat_activity WHERE application_name=" + literal(blocker_name) +
                 " AND state='idle in transaction'", "CAPTURE_EXPIRY_BLOCKER_NOT_READY", blocker)
        waiter = start_sql(lab, waiter_name, "SET SESSION AUTHORIZATION aia_private_transport;" + capture_sql(body, fp))
        wait_for(lab, "SELECT count(*) FROM pg_stat_activity w JOIN pg_stat_activity b "
                 "ON b.pid=ANY(pg_blocking_pids(w.pid)) WHERE w.application_name=" + literal(waiter_name) +
                 " AND b.application_name=" + literal(blocker_name) + " AND w.wait_event_type='Lock'",
                 "CAPTURE_AFTER_INSERT_WAIT_NOT_OBSERVED", waiter)
        require(int(lab._sql("src", "SELECT floor(extract(epoch FROM clock_timestamp()))::bigint")) < deadline,
                "CAPTURE_EXPIRED_BEFORE_INSERT_WAIT")
        stop = time.monotonic() + 9
        while int(lab._sql("src", "SELECT floor(extract(epoch FROM clock_timestamp()))::bigint")) < deadline:
            require(time.monotonic() < stop, "CAPTURE_SERVER_CLOCK_NOT_ADVANCING")
            time.sleep(0.05)
        finish_sql(blocker, "COMMIT;")
        require(finish_sql(waiter) == "LAB_DENY_ENVELOPE", "CAPTURE_EXPIRED_INSERT_ACCEPTED")
        require(lab.counts() == before, "CAPTURE_EXPIRED_INBOX_AUDIT_OR_PAYLOAD_SURVIVED")
    finally:
        abort_sql(waiter); abort_sql(blocker)
        lab._sql("src", "DROP TRIGGER capture_wait_fixture ON " + CAPTURE +
                 ";DROP FUNCTION auto_ia_lab.capture_wait_fixture();")
        if grant_expiry:
            lab._sql("src", "UPDATE auto_ia_lab.service_company_grants SET expires_at=" +
                     str(int(time.time()) + 1800) + " WHERE company_id=" + literal(COMPANY_A))
    check_ack(lab, fresh())


def capture_metadata(lab: CaptureLab, which: str) -> dict:
    # Include raw constraints: any change across pg_dump/pg_restore is a failure
    # that needs explicit investigation, never silently normalize definitions.
    query = """SELECT jsonb_build_object(
      'table',(SELECT jsonb_build_object('owner',pg_get_userbyid(relowner),'acl',relacl::text,
                 'rls',relrowsecurity,'force_rls',relforcerowsecurity) FROM pg_class
                 WHERE oid='auto_ia_lab.lead_ingest_payloads'::regclass),
      'columns',(SELECT jsonb_agg(jsonb_build_array(attname,format_type(atttypid,atttypmod),attnotnull)
                  ORDER BY attnum) FROM pg_attribute WHERE attrelid='auto_ia_lab.lead_ingest_payloads'::regclass
                  AND attnum>0 AND NOT attisdropped),
      'constraints',(SELECT jsonb_agg(jsonb_build_array(conname,contype,convalidated,condeferrable,
                     pg_get_constraintdef(oid)) ORDER BY conname) FROM pg_constraint
                     WHERE conrelid='auto_ia_lab.lead_ingest_payloads'::regclass),
      'policies',(SELECT jsonb_agg(jsonb_build_array(polname,polcmd,polpermissive,
                    (SELECT array_agg(pg_get_userbyid(roleid) ORDER BY pg_get_userbyid(roleid)) FROM unnest(polroles) roleid),
                    pg_get_expr(polqual,polrelid),pg_get_expr(polwithcheck,polrelid)) ORDER BY polname)
                  FROM pg_policy WHERE polrelid='auto_ia_lab.lead_ingest_payloads'::regclass),
      'triggers',(SELECT jsonb_agg(jsonb_build_array(t.tgname,t.tgenabled,pg_get_triggerdef(t.oid),
                    p.prosrc,p.prosecdef,p.proconfig,pg_get_userbyid(p.proowner),p.proacl::text) ORDER BY t.tgname)
                  FROM pg_trigger t JOIN pg_proc p ON p.oid=t.tgfoid
                  WHERE t.tgrelid='auto_ia_lab.lead_ingest_payloads'::regclass AND NOT t.tgisinternal),
      'function',(SELECT jsonb_build_array(prosrc,prosecdef,proconfig,pg_get_userbyid(proowner),proacl::text)
                  FROM pg_proc WHERE oid='auto_ia_lab.receive_company_signed_lead(uuid,text,jsonb,text)'::regprocedure)
    )"""
    return json.loads(lab._sql(which, query))


def restore_capture(lab: CaptureLab) -> dict:
    rows, metadata, counts = lab.rows(), capture_metadata(lab, "src"), lab.counts()
    dump = lab.directory / "lead-capture.dump"
    run(["docker", "exec", lab.containers["src"], "pg_dump", "-U", "postgres", "-d",
         "imagine_agents_ci", "--format", "custom"], "CAPTURE_DUMP", output=dump)
    contents = dump.read_bytes()
    require(contents.startswith(b"PGDMP") and len(contents) < 64 * 1024 * 1024 and
            dump.stat().st_mode & 0o777 == 0o600, "CAPTURE_DUMP_PRIVATE_FORMAT")
    digest = hashlib.sha256(contents).hexdigest()
    lab._start("dst")
    lab._sql("dst", "\n".join("CREATE ROLE " + role +
             " NOLOGIN NOINHERIT NOSUPERUSER NOBYPASSRLS NOCREATEDB NOCREATEROLE NOREPLICATION;" for role in ROLES))
    require(hashlib.sha256(dump.read_bytes()).hexdigest() == digest, "CAPTURE_DUMP_CHANGED")
    run(["docker", "exec", "-i", lab.containers["dst"], "pg_restore", "-U", "postgres", "-d",
         "imagine_agents_ci", "--exit-on-error"], "CAPTURE_RESTORE", stdin=contents)
    # No login, listener or worker is enabled. Quarantine restored ingress
    # grants before inspecting payloads; historical storage does not grant use.
    lab._sql("dst", "UPDATE auto_ia_lab.service_company_grants SET active=false,revision=revision+1;"
             "UPDATE auto_ia_lab.receipt_read_grants SET active=false,revision=revision+1;")
    require(lab.rows("dst") == rows and lab.counts("dst") == counts,
            "CAPTURE_RESTORED_BYTES_OR_ROWS_DIFFER")
    require(capture_metadata(lab, "dst") == metadata, "CAPTURE_RESTORED_METADATA_DIFFER")
    permissions(lab, "dst"); immutable_checks(lab, "dst")
    require(lab._sql("dst", "SELECT count(*) FROM pg_roles WHERE rolname IN (" +
                    ",".join(literal(role) for role in ROLES) +
                    ") AND NOT(rolcanlogin OR rolinherit OR rolsuper OR rolbypassrls OR rolcreatedb OR rolcreaterole OR rolreplication)") == str(len(ROLES)),
            "CAPTURE_RESTORE_REOPENED_ROLE")
    probe = fresh(); before = lab.counts("dst")
    require(role_call(lab, probe, fingerprint(lab.certificates["client-a"][0]), "dst") == "LAB_DENY_GRANT",
            "CAPTURE_RESTORED_GRANT_NOT_QUARANTINED")
    require(lab.counts("dst") == before, "CAPTURE_RESTORED_DENIAL_MUTATED_STORAGE")
    return {"new_capture_table_explicitly_restored": True, "exact_bytes_and_bindings_preserved": True,
            "owner_acl_rls_fk_triggers_function_preserved": True, "separate_pg16_clusters": 2,
            "restored_roles_nologin": True, "ingress_grants_quarantined": True,
            "external_backup_verified": False, "different_host_restore_verified": False,
            "n8n_execution_triggered": False}


def exercise_capture(lab: CaptureLab) -> dict:
    lab.prepare()
    # Enabling capture without its persistence component is a transient server
    # failure, never an implicit fallback to receipt-only admission.
    lab.profile = "immutable-v1"
    lab.start_http()
    before_schema = lab.php("stats")
    deny_event(lab, fresh(), status=503)
    require(lab.php("stats") == before_schema, "CAPTURE_MISSING_SCHEMA_FELL_BACK_OR_WROTE_STATE")
    lab._sql("src", (ROOT / "runtime/central-auto/lead-ingest-capture.sql").read_text())
    lab.restart_profile(None)
    permissions(lab)
    legacy = fresh(); check_ack(lab, legacy)
    require(lab.counts() == {"inbox": 1, "audit": 1, "payloads": 0, "ai_requests": 0},
            "CAPTURE_DEFAULT_PROFILE_ENABLED")
    lab.restart_profile("unknown-profile")
    before = lab.counts(); deny_event(lab, fresh(), status=503)
    require(lab.counts() == before, "CAPTURE_BAD_PROFILE_WROTE_STATE")
    lab.restart_profile("immutable-v1")
    before = lab.counts(); deny_event(lab, legacy)
    require(lab.counts() == before, "CAPTURE_LEGACY_BACKFILL_OR_AUDIT")

    body = fresh(); payload = json.loads(base64.b64decode(body["payload_b64"]))
    payload["lead_id"] = "ABCDEF12-3456-7123-8ABC-0123456789AB"
    raw_payload = json.dumps(dict(reversed(list(payload.items()))), indent=1).encode()
    raw_payload = raw_payload.replace(b'"event_id"', b'"\\u0065vent_id"', 1)
    body = signed(body, raw_payload)
    before = lab.counts(); check_ack(lab, body)
    captured = expected_row(lab, body)
    require(lab.counts() == {"inbox": before["inbox"] + 1, "audit": before["audit"] + 1,
                            "payloads": 1, "ai_requests": 0}, "CAPTURE_FIRST_ADMISSION_NOT_ATOMIC")
    check_ack(lab, body, "LAB_DUPLICATE_NO_DELIVERY")
    require(expected_row(lab, body) == captured, "CAPTURE_DUPLICATE_REWROTE_PROVENANCE")
    fp = fingerprint(lab.certificates["client-a"][0])
    lab._sql("src", "INSERT INTO auto_ia_lab.receipt_read_grants VALUES(" + literal(COMPANY_A) + "," +
             literal(fp) + ",'SYNTHETIC_TENANT_A','event.receipt.read',true,1," + str(int(time.time()) + 1800) + ")")
    before = lab.counts()
    query = make_query(COMPANY_A, "SYNTHETIC_TENANT_A", body["envelope"]["event_ref"], body["envelope"]["payload_hash"])
    verify_receipt(lab.http(query), query, lab.secrets["receipt-key"].read_bytes())
    require(lab.counts() == before and expected_row(lab, body) == captured,
            "CAPTURE_RECEIPT_QUERY_CHANGED_PAYLOAD")

    company_b = make_event(COMPANY_B, "SYNTHETIC_TENANT_B", str(uuid.uuid4()), "SYNTHETIC_KEY_B", KEY_B)
    check_ack(lab, company_b, client="client-b"); expected_row(lab, company_b, "client-b")
    before = lab.counts()
    deny_event(lab, company_b, client="client-a")
    require(lab.counts() == before, "CAPTURE_CROSS_TENANT_CHANGED_STATE")
    conflict_payload = dict(payload); conflict_payload["lead_id"] = "dddddddd-dddd-4ddd-8ddd-dddddddddddd"
    conflict = signed(body, wire(conflict_payload)); deny_event(lab, conflict)
    duplicate_payload = b'{"sch\\u0065ma":"CRM_LEAD_EVENT_V1",' + raw_payload.lstrip()[1:]
    # Keep the event ID/ref bound so the malformed payload is denied because
    # of its duplicate decoded name, not an unrelated reference mismatch.
    malformed = signed(body, duplicate_payload)
    deny_event(lab, malformed)
    require(role_call(lab, malformed, fp) == "LAB_DENY_PAYLOAD", "CAPTURE_SQL_DUPLICATE_JSON_ACCEPTED")
    require(lab.counts() == before and expected_row(lab, body) == captured,
            "CAPTURE_CONFLICT_OR_MALFORMED_MUTATED_STATE")

    # The historical inbox did not retain request_ref. Capture must bind it
    # without changing the receipt format or accepting a new request as a retry.
    changed_request = json.loads(wire(body))
    changed_request["envelope"]["request_ref"] = "SYNTHETIC_CAPTURE_DIFFERENT_REQUEST"
    deny_event(lab, signed(changed_request))
    require(lab.counts() == before and expected_row(lab, body) == captured,
            "CAPTURE_REQUEST_REF_CONFLICT_CHANGED_PROVENANCE")

    # Current authorization is independent of immutable admission provenance.
    # Revocation denies even an exact duplicate; a newly current revision can
    # confirm that duplicate without overwriting who originally admitted it.
    lab._sql("src", "UPDATE auto_ia_lab.service_company_grants SET active=false,revision=revision+1 "
             "WHERE company_id=" + literal(COMPANY_A))
    deny_event(lab, body)
    require(lab.counts() == before and expected_row(lab, body) == captured,
            "CAPTURE_REVOKED_DUPLICATE_CHANGED_STATE")
    lab._sql("src", "UPDATE auto_ia_lab.service_company_grants SET active=true,revision=revision+1 "
             "WHERE company_id=" + literal(COMPANY_A))
    check_ack(lab, body, "LAB_DUPLICATE_NO_DELIVERY")
    require(expected_row(lab, body) == captured, "CAPTURE_CURRENT_GRANT_REWROTE_HISTORICAL_PROVENANCE")

    # Exercise an actual new mTLS identity AND HMAC key. Only current authority
    # changes; event bytes, request, nonce and time window are the same retry.
    rotated_fp = fingerprint(lab.certificates["client-unknown"][0])
    rotated_key = b"SYNTHETIC_OFFLINE_ROTATED_KEY_32_BYTES_FOR_CAPTURE_ONLY"
    lab._sql("src", "INSERT INTO auto_ia_lab.service_company_grants VALUES(" + literal(COMPANY_A) + "," +
             literal(rotated_fp) + ",'SYNTHETIC_TENANT_A','lead.ingest',true,9," + str(int(time.time()) + 1800) + ");"
             "INSERT INTO auto_ia_lab.mock_s2s_keys VALUES('imagine-crm','SYNTHETIC_KEY_ROTATED'," +
             literal(rotated_key.decode()) + ",true);")
    rotated = json.loads(wire(body))
    rotated["envelope"]["key_id"] = "SYNTHETIC_KEY_ROTATED"
    canonical = "\n".join(str(rotated["envelope"][name]) for name in INGRESS_FIELDS).encode()
    rotated["envelope"]["signature"] = hmac.new(rotated_key, canonical, hashlib.sha256).hexdigest()
    before = lab.counts()
    check_ack(lab, rotated, "LAB_DUPLICATE_NO_DELIVERY", client="client-unknown")
    require(lab.counts() == {**before, "audit": before["audit"] + 1} and
            expected_row(lab, body) == captured, "CAPTURE_ROTATED_IDENTITY_CHANGED_ORIGIN_OR_ADMISSION")
    before = lab.counts()
    lab._sql("src", "UPDATE auto_ia_lab.service_company_grants SET active=false,revision=revision+1 "
             "WHERE company_id=" + literal(COMPANY_A) + " AND fingerprint=" + literal(rotated_fp))
    deny_event(lab, rotated, client="client-unknown")
    require(lab.counts() == before, "CAPTURE_REVOKED_ROTATED_GRANT_CHANGED_STATE")
    lab._sql("src", "UPDATE auto_ia_lab.service_company_grants SET active=true,revision=revision+1 "
             "WHERE company_id=" + literal(COMPANY_A) + " AND fingerprint=" + literal(rotated_fp) + ";"
             "UPDATE auto_ia_lab.mock_s2s_keys SET enabled=false WHERE issuer='imagine-crm' "
             "AND key_id='SYNTHETIC_KEY_ROTATED';")
    deny_event(lab, rotated, client="client-unknown")
    require(lab.counts() == before and expected_row(lab, body) == captured,
            "CAPTURE_REVOKED_ROTATED_KEY_CHANGED_STATE")

    # A failure after the new INSERT must undo the existing inbox and audit too.
    lab._sql("src", "CREATE FUNCTION auto_ia_lab.capture_fail_fixture() RETURNS trigger LANGUAGE plpgsql AS $$ "
             "BEGIN RAISE EXCEPTION 'SYNTHETIC_CAPTURE_FAILURE';END $$;"
             "CREATE TRIGGER capture_fail_fixture AFTER INSERT ON " + CAPTURE +
             " FOR EACH ROW EXECUTE FUNCTION auto_ia_lab.capture_fail_fixture();")
    before = lab.counts(); failed = fresh()
    try:
        deny_event(lab, failed, status=503)
        require(lab.counts() == before, "CAPTURE_WRITE_FAILURE_LEFT_PARTIAL_ADMISSION")
    finally:
        lab._sql("src", "DROP TRIGGER capture_fail_fixture ON " + CAPTURE +
                 ";DROP FUNCTION auto_ia_lab.capture_fail_fixture();")
    check_ack(lab, failed); expected_row(lab, failed)
    concurrent_duplicate(lab)
    capture_expiry(lab, False); capture_expiry(lab, True)
    permissions(lab); immutable_checks(lab)
    recovery = restore_capture(lab)
    return {"schema": "CRM_LEAD_CAPTURE_VERIFIED_V1", "http_mtls_verified": True,
            "capture_opt_in_server_side": True, "default_profile_does_not_capture": True,
            "enabled_profile_without_schema_denied_without_admission": True,
            "unknown_profile_denied_without_writes": True, "legacy_admission_not_backfilled": True,
            "exact_original_payload_bytes_bound_to_inbox": True,
            "duplicate_and_receipt_query_preserve_capture": True,
            "sql_and_http_duplicate_payload_keys_denied": True, "cross_tenant_and_hash_conflict_denied": True,
            "request_reference_bound_to_original_capture": True,
            "current_grant_rechecked_without_rewriting_origin": True,
            "rotated_certificate_and_key_preserve_original_provenance": True,
            "revoked_rotated_grant_and_key_denied_without_writes": True,
            "write_failure_rolls_back_inbox_audit_payload": True,
            "concurrent_duplicate_has_one_payload": True,
            "post_insert_envelope_and_grant_expiry_rolled_back": True,
            "capture_table_forced_rls_execute_only": True, "immutable_rows_verified": True,
            "recovery": recovery, "counts": lab.counts(), "host_published_ports": 0,
            "network": "none", "dispatch": "DISABLED", "processing": "NOT_DISPATCHED",
            "n8n_calls": 0, "ai_calls": 0, "service_effects": 0,
            "real_authority_verified": False, "go_for_installation": False}


def main() -> dict:
    # Re-execute the entire P27/P29 HTTP and timing regression matrix under the
    # new source pin before exercising capture. The default profile stays off.
    parser = closed_json(run(["php", str(ROOT / "runtime/central-auto/p29-json-probe.php")],
                             "CAPTURE_P29_JSON_PROBE").stdout)
    require(parser.get("schema") == "P29_JSON_PARSER_VERIFIED_V1" and
            parser.get("canonical_serialization_required") is False, "CAPTURE_P29_JSON_PARSER")
    baseline = receipt_matrix(expected_ingress_sha256=INDEX_SHA256, after_receipt_checks=p29_checks)
    baseline["json_parser"] = parser
    old_umask = os.umask(0o077)
    try:
        with tempfile.TemporaryDirectory(prefix="aia-lead-capture-", dir=os.environ.get("RUNNER_TEMP")) as work:
            lab = CaptureLab("capture-" + secrets.token_hex(6), Path(work) / "capture-lab")
            try:
                proof = exercise_capture(lab)
                proof["existing_p27_p29_matrix"] = baseline
                proof["receiver_sha256"] = INDEX_SHA256
                return proof
            finally:
                lab.cleanup()
    finally:
        os.umask(old_umask)


if __name__ == "__main__":
    try:
        print(json.dumps(main(), sort_keys=True))
        print("CENTRAL_AUTO_CRM_LEAD_CAPTURE_PASS NO_GO")
    except (Failure, OSError, ValueError, KeyError, subprocess.SubprocessError) as error:
        print("CENTRAL_AUTO_CRM_LEAD_CAPTURE_DENIED_" +
              (str(error) if isinstance(error, Failure) else type(error).__name__), file=sys.stderr)
        raise SystemExit(1)
