#!/usr/bin/env python3
"""P29: closed P19 payloads and real PostgreSQL expiry races, without dispatch."""
from __future__ import annotations

import base64
import hashlib
import hmac
import json
import secrets
import subprocess
import sys
import time
import uuid

from check_p24_full_n8n_restore import Failure, ROOT, require, run
from check_p27_private_receipt_status import (
    COMPANY_A, KEY_A, ReceiptLab, closed_json, fingerprint, main as receipt_matrix,
    make_event, make_query, verify_ack, verify_receipt, wire,
)

P29_INDEX_SHA256 = "6cc39d25780494f73d1d1002d9b95eef3d812f899bab96bc9bd43509fe376286"
INGRESS_FIELDS = ("schema", "issuer", "audience", "product", "operation", "tenant_ref",
                  "event_ref", "nonce_ref", "request_ref", "issued_at", "expires_at",
                  "payload_hash", "key_id")
EVENT_DENIED = b'{"error":"EVENT_DENIED"}'


def signed(body: dict, payload: bytes | None = None) -> dict:
    body = dict(body)
    envelope = dict(body["envelope"])
    if payload is not None:
        body["payload_b64"] = base64.b64encode(payload).decode()
        envelope["payload_hash"] = hashlib.sha256(payload).hexdigest()
    canonical = "\n".join(str(envelope[name]) for name in INGRESS_FIELDS).encode()
    envelope["signature"] = hmac.new(KEY_A, canonical, hashlib.sha256).hexdigest()
    body["envelope"] = envelope
    return body


def fresh() -> dict:
    return make_event(COMPANY_A, "SYNTHETIC_TENANT_A", str(uuid.uuid4()), "SYNTHETIC_KEY_A", KEY_A)


def assert_valid_mac(raw: bytes) -> dict:
    # Deliberately model the old last-value decoder for the test fixture only.
    # Thus duplicate-name negatives cannot pass merely due to a broken MAC.
    body = json.loads(raw)
    envelope = body["envelope"]
    payload = base64.b64decode(body["payload_b64"], validate=True)
    require(hashlib.sha256(payload).hexdigest() == envelope["payload_hash"], "P29_FIXTURE_PAYLOAD_HASH")
    expected = hmac.new(KEY_A, "\n".join(str(envelope[n]) for n in INGRESS_FIELDS).encode(),
                        hashlib.sha256).hexdigest()
    require(hmac.compare_digest(expected, envelope["signature"]), "P29_FIXTURE_MAC")
    return body


def deny_raw(lab: ReceiptLab, raw: bytes) -> None:
    assert_valid_mac(raw)
    result = lab.http(raw, path="/private/events")
    require(result["status"] == 403 and result["cache_control"] == "no-store" and
            base64.b64decode(result["body_b64"], validate=True) == EVENT_DENIED,
            "P29_MALFORMED_INGRESS_NOT_UNIFORMLY_DENIED")


def positive(lab: ReceiptLab, body: dict, raw: bytes | None = None) -> None:
    raw = wire(body) if raw is None else raw
    assert_valid_mac(raw)
    verify_ack(lab.http(raw, path="/private/events"), body, lab.secrets["ack-key"].read_bytes(),
               "LAB_RECEIVED_NO_DELIVERY")
    query = make_query(COMPANY_A, "SYNTHETIC_TENANT_A", body["envelope"]["event_ref"],
                       body["envelope"]["payload_hash"])
    verify_receipt(lab.http(query), query, lab.secrets["receipt-key"].read_bytes())


def malformed_contracts(lab: ReceiptLab) -> int:
    before = lab.php("stats")
    negatives: list[bytes] = []
    for field in ("lead_id", "event_id", "company_id"):
        for value in ([], {}, None, 17, "not-a-uuid", "11111111-1111-4111-8111-11111111111Z"):
            body = fresh()
            payload = json.loads(base64.b64decode(body["payload_b64"]))
            payload[field] = value
            if field == "event_id" and isinstance(value, str):
                # The old receiver's event_ref derivation and the SQL reference
                # alphabet both accept this string; only the UUID boundary fails.
                body["envelope"]["event_ref"] = "SYNTHETIC_" + value.replace("-", "").upper()
            negatives.append(wire(signed(body, wire(payload))))
    for escaped in (False, True):
        body = fresh(); raw = wire(body)
        field = b'"\\u0063ompany_id"' if escaped else b'"company_id"'
        negatives.append(b"{" + field + b":" + json.dumps(COMPANY_A).encode() + b"," + raw[1:])
        field = b'"\\u0073chema"' if escaped else b'"schema"'
        negatives.append(raw.replace(b'"envelope":{', b'"envelope":{' + field + b':"AUTO_IA_S2S_V1",', 1))
        payload = base64.b64decode(body["payload_b64"])
        duplicate = b"{" + field + b':"CRM_LEAD_EVENT_V1",' + payload[1:]
        negatives.append(wire(signed(body, duplicate)))
    body = fresh(); payload = base64.b64decode(body["payload_b64"])
    lead = json.loads(payload)["lead_id"]
    negatives.append(wire(signed(body, b'{"lead_id":' + json.dumps(lead).encode() + b"," + payload[1:])))
    for raw in negatives:
        deny_raw(lab, raw)
    require(lab.php("stats") == before, "P29_MALFORMED_CREATED_INBOX_OR_AUDIT")

    # Existing JSON wire freedoms stay valid: formatting, key ordering, escaped
    # names and valid UUID versions/case. No canonical byte serialization is new.
    body = fresh()
    reordered = dict(reversed(list(body.items())))
    reordered["envelope"] = dict(reversed(list(body["envelope"].items())))
    positive(lab, body, json.dumps(reordered, indent=2).encode())
    body = fresh(); payload = json.loads(base64.b64decode(body["payload_b64"]))
    payload["lead_id"] = "ABCDEF12-3456-7123-8ABC-0123456789AB"
    payload_raw = json.dumps(dict(reversed(list(payload.items()))), indent=1).encode()
    payload_raw = payload_raw.replace(b'"event_id"', b'"\\u0065vent_id"', 1)
    body = signed(body, payload_raw)
    raw = wire(body).replace(b'"envelope"', b'"\\u0065nvelope"', 1)
    positive(lab, body, raw)
    after = lab.php("stats")
    require(after == {"inbox": before["inbox"] + 2, "ingress_audit": before["ingress_audit"] + 2,
                      "ai_requests": before["ai_requests"]}, "P29_VALID_CONTROL_COUNTS")
    return len(negatives)


def literal(value: str) -> str:
    return "'" + value.replace("'", "''") + "'"


def call_sql(body: dict, fp: str) -> str:
    return ("SELECT auto_ia_lab.receive_company_signed_no_ai(" + literal(COMPANY_A) + "::uuid," +
            literal(fp) + "," + literal(wire(body["envelope"]).decode()) + "::jsonb);")


def start_sql(lab: ReceiptLab, application: str, sql: str) -> subprocess.Popen:
    process = subprocess.Popen(
        ["docker", "exec", "-i", lab.containers["src"], "psql", "-X", "-qAt", "-v", "ON_ERROR_STOP=1",
         "-U", "postgres", "-d", "imagine_agents_ci"],
        stdin=subprocess.PIPE, stdout=subprocess.PIPE, stderr=subprocess.PIPE,
    )
    setup = ("SET application_name=" + literal(application) + ";"
             "SET statement_timeout='15000ms';SET lock_timeout='12000ms';"
             "SET idle_in_transaction_session_timeout='15000ms';")
    try:
        process.stdin.write((setup + sql + "\n").encode())
        process.stdin.flush()
    except BaseException:
        process.kill(); process.communicate(timeout=5)
        raise
    return process


def wait_for(lab: ReceiptLab, sql: str, label: str, process: subprocess.Popen | None = None) -> None:
    deadline = time.monotonic() + 5
    while time.monotonic() < deadline:
        if lab._sql("src", sql) == "1":
            return
        require(process is None or process.poll() is None, label + "_PROCESS_EXITED")
        time.sleep(0.05)
    raise Failure(label)


def finish_sql(process: subprocess.Popen, tail: str = "") -> str:
    out, _ = process.communicate(input=(tail + "\n").encode(), timeout=8)
    require(process.returncode == 0, "P29_SQL_SESSION_FAILED")
    return out.decode().strip()


def abort_sql(process: subprocess.Popen | None) -> None:
    if process is None or process.poll() is not None:
        return
    try:
        process.communicate(input=b"ROLLBACK;\n", timeout=2)
    except (subprocess.SubprocessError, OSError, ValueError):
        process.kill(); process.communicate(timeout=5)


def temporal_race(lab: ReceiptLab, kind: str) -> None:
    require(kind in ("key-wait", "unique-commit", "unique-rollback", "audit-grant-expiry"),
            "P29_RACE_KIND")
    before = lab.php("stats")
    body = fresh()
    server_now = int(lab._sql("src", "SELECT floor(extract(epoch FROM clock_timestamp()))::bigint"))
    deadline = server_now + 6
    body["envelope"]["issued_at"] = server_now
    body["envelope"]["expires_at"] = server_now + (30 if kind == "audit-grant-expiry" else 6)
    body = signed(body)
    assert_valid_mac(wire(body))
    fp = fingerprint(lab.certificates["client-a"][0])
    suffix = secrets.token_hex(6)
    blocker_name = "p29-blocker-" + suffix
    waiter_name = "p29-waiter-" + suffix
    blocker = waiter = None
    if kind == "audit-grant-expiry":
        lab._sql("src", "UPDATE auto_ia_lab.service_company_grants SET expires_at=" + str(deadline) +
                 " WHERE company_id=" + literal(COMPANY_A))
    try:
        if kind == "key-wait":
            blocked_sql = "BEGIN;UPDATE auto_ia_lab.mock_s2s_keys SET enabled=enabled WHERE key_id='SYNTHETIC_KEY_A';"
        elif kind.startswith("unique-"):
            # A separate valid admission retains its own uncommitted row/audit.
            # Commit/rollback of that writer tests both ON CONFLICT continuations.
            blocked_sql = "BEGIN;SET LOCAL ROLE aia_private_transport;" + call_sql(body, fp)
        else:
            blocked_sql = "BEGIN;LOCK TABLE auto_ia_lab.service_event_audit IN ACCESS EXCLUSIVE MODE;"
        blocker = start_sql(lab, blocker_name, blocked_sql)
        wait_for(lab, "SELECT count(*) FROM pg_stat_activity WHERE application_name=" + literal(blocker_name) +
                 " AND state='idle in transaction'", "P29_BLOCKER_NOT_READY", blocker)
        waiter = start_sql(lab, waiter_name, "SET SESSION AUTHORIZATION aia_private_transport;" + call_sql(body, fp))
        wait_for(lab, "SELECT count(*) FROM pg_stat_activity w JOIN pg_stat_activity b "
                 "ON b.pid=ANY(pg_blocking_pids(w.pid)) WHERE w.application_name=" + literal(waiter_name) +
                 " AND b.application_name=" + literal(blocker_name) + " AND w.wait_event_type='Lock'",
                 "P29_REAL_WAIT_NOT_OBSERVED", waiter)
        require(int(lab._sql("src", "SELECT floor(extract(epoch FROM clock_timestamp()))::bigint")) < deadline,
                "P29_FIXTURE_EXPIRED_BEFORE_WAIT_OBSERVED")
        # Use the server's advancing real clock, never a patched application
        # clock. No SQL/HTTP endpoint timeout is changed; only these CLI sessions
        # wait longer so the race is deterministic rather than scheduling luck.
        stop = time.monotonic() + 9
        while int(lab._sql("src", "SELECT floor(extract(epoch FROM clock_timestamp()))::bigint")) < deadline:
            require(time.monotonic() < stop, "P29_SERVER_CLOCK_DID_NOT_ADVANCE")
            time.sleep(0.05)
        owner_result = finish_sql(blocker, "ROLLBACK;" if kind == "unique-rollback" else "COMMIT;")
        if kind.startswith("unique-"):
            require(owner_result == "LAB_RECEIVED_NO_DELIVERY", "P29_WINNING_ADMISSION_NOT_VALID")
        result = finish_sql(waiter)
        require(result == "LAB_DENY_ENVELOPE", "P29_EXPIRED_WAIT_ADMITTED")
        expected = 1 if kind == "unique-commit" else 0
        ref = literal(body["envelope"]["event_ref"])
        inbox = int(lab._sql("src", "SELECT count(*) FROM auto_ia_lab.event_inbox WHERE event_ref=" + ref))
        audit = int(lab._sql("src", "SELECT count(*) FROM auto_ia_lab.service_event_audit WHERE event_ref=" + ref))
        require(inbox == expected and audit == expected, "P29_RESIDUAL_OR_DELETED_FOREIGN_ADMISSION")
        if expected:
            require(lab._sql("src", "SELECT payload_hash=" + literal(body["envelope"]["payload_hash"]) +
                    " AND nonce_ref=" + literal(body["envelope"]["nonce_ref"]) +
                    " AND issued_epoch=" + str(server_now) + " AND expires_epoch=" + str(deadline) +
                    " AND floor(extract(epoch FROM received_at))<" + str(deadline) +
                    " FROM auto_ia_lab.event_inbox WHERE event_ref=" + ref) == "t",
                    "P29_WINNING_ROW_NOT_PRESERVED")
            require(lab._sql("src", "SELECT outcome FROM auto_ia_lab.service_event_audit WHERE event_ref=" + ref) ==
                    "LAB_RECEIVED_NO_DELIVERY", "P29_EXPIRED_ATTEMPT_AUDITED")
        require(lab.php("stats") == {"inbox": before["inbox"] + expected,
                                    "ingress_audit": before["ingress_audit"] + expected,
                                    "ai_requests": before["ai_requests"]}, "P29_RACE_CHANGED_OTHER_STATE")
    finally:
        abort_sql(waiter)
        abort_sql(blocker)
        if kind == "audit-grant-expiry":
            lab._sql("src", "UPDATE auto_ia_lab.service_company_grants SET expires_at=" +
                     str(int(time.time()) + 1800) + ",revision=revision+1 WHERE company_id=" + literal(COMPANY_A))
    before_control = lab.php("stats")
    positive(lab, fresh())
    require(lab.php("stats") == {"inbox": before_control["inbox"] + 1,
                                "ingress_audit": before_control["ingress_audit"] + 1,
                                "ai_requests": before_control["ai_requests"]},
            "P29_POST_RACE_POSITIVE_CONTROL")


def additional_checks(lab: ReceiptLab) -> dict:
    negatives = malformed_contracts(lab)
    races = ("key-wait", "unique-commit", "unique-rollback", "audit-grant-expiry")
    for kind in races:
        temporal_race(lab, kind)
    return {"schema": "P29_INGRESS_BOUNDARIES_VERIFIED_V1",
            "signed_malformed_http_requests_denied": negatives,
            "payload_company_event_lead_uuid_checked": True, "duplicate_names_rejected_at_all_layers": True,
            "valid_json_serializations_preserved": True, "original_ack_format_and_mac_preserved": True,
            "real_postgresql_expiry_races_verified": list(races),
            "expired_attempt_inbox_and_audit_rolled_back": True, "committed_winner_preserved": True,
            "post_race_valid_http_controls_passed": len(races),
            "runtime_timeouts_unchanged": True, "dispatch": "DISABLED", "ai_calls": 0, "service_effects": 0,
            "real_authority_verified": False, "go_for_installation": False}


def main() -> dict:
    parser = run(["php", str(ROOT / "runtime/central-auto/p29-json-probe.php")], "P29_JSON_PROBE", check=False)
    if parser.returncode:
        import re
        codes = re.findall(rb"^P29_JSON_DENIED_[A-Z0-9_]+$", parser.stderr, re.MULTILINE)
        raise Failure(codes[0].decode() if len(codes) == 1 else "P29_JSON_PROBE_FAILED")
    parser_proof = closed_json(parser.stdout)
    require(parser_proof.get("schema") == "P29_JSON_PARSER_VERIFIED_V1" and
            parser_proof.get("canonical_serialization_required") is False, "P29_JSON_PROBE_RESULT")
    proof = receipt_matrix(expected_ingress_sha256=P29_INDEX_SHA256, after_receipt_checks=additional_checks)
    proof["schema"] = "P29_PRIVATE_INGRESS_AND_RECEIPT_VERIFIED_V1"
    proof["json_parser"] = parser_proof
    return proof


if __name__ == "__main__":
    try:
        print(json.dumps(main(), sort_keys=True))
        print("CENTRAL_AUTO_P29_INGRESS_BOUNDARIES_PASS NO_GO")
    except (Failure, OSError, ValueError, KeyError, subprocess.SubprocessError) as error:
        print("CENTRAL_AUTO_P29_DENIED_" + (str(error) if isinstance(error, Failure) else type(error).__name__),
              file=sys.stderr)
        raise SystemExit(1)
