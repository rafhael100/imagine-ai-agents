#!/usr/bin/env python3
"""P68: a real, private P67 fixture dump restored into a second owned PG16.

The source remains alive to provide the post-snapshot revocation fixture. This
is not an operational source of current authority or an offsite recovery tool.
"""
from __future__ import annotations

import copy
import hashlib
import importlib.util
import json
import os
from pathlib import Path
import re
import secrets
import selectors
import stat
import subprocess
import sys
import tempfile
import time
import uuid

ROOT = Path(__file__).resolve().parents[1]
PHASE = "PREFLIGHT"
LIMIT = 64 * 1024 * 1024
TABLES = {"companies", "mac_keys", "service_grants", "drafts", "nonces", "audit"}


class Denied(RuntimeError):
    pass


def need(value, label):
    if not value:
        raise Denied("P68_" + label)


def step(label):
    global PHASE
    need(re.fullmatch(r"[A-Z_]+", label), "PHASE_LABEL")
    PHASE = label
    print("P68_PHASE_" + label, flush=True)


def command(arguments, label, *, data=None, timeout=60, check=True):
    result = subprocess.run(arguments, input=data, capture_output=True, timeout=timeout)
    need(not check or result.returncode == 0, label)
    return result


def load_module(name, path):
    spec = importlib.util.spec_from_file_location(name, path)
    need(spec is not None and spec.loader is not None, "MODULE_SOURCE")
    module = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(module)
    return module


def preflight():
    # The new runner checks the real PR and requires all old composed blobs to
    # remain literal. No P67 guard/main is called and no GitHub context is set.
    runner = load_module("p68_source_runner", ROOT / "tools/run_p68_owner_draft_recovery.py")
    source = Path(os.environ.get("AIA_P68_SOURCE_CHECKOUT", ""))
    need(source.is_absolute() and source.resolve() != ROOT, "SOURCE_CHECKOUT")
    head, tree, expected, temporary = runner.preflight(source)
    need(os.environ.get("AIA_P68_SOURCE_SHA") == head
         and os.environ.get("AIA_P68_SOURCE_TREE") == tree
         and os.environ.get("AIA_P68_SOURCE_DIR") == str(ROOT), "SOURCE_METADATA")
    runner.verify_export(ROOT, expected, temporary)
    need(re.fullmatch(r"sha256:[a-f0-9]{64}", os.environ.get("AIA_P68_PHP_IMAGE", ""))
         and os.environ.get("AIA_P68_PHP_IMAGE") == os.environ.get("AGENTS_PHP_IMAGE"),
         "ORIGINAL_P67_IMAGE")
    legacy = load_module("p68_original_p67_helpers", ROOT / "tools/check_p67_owner_draft_lab.py")
    return temporary, legacy


def probe(lab, mode, payload=None, *, denied=None):
    lab.sequence += 1
    name = lab.project + "-recovery-probe-" + str(lab.sequence)
    need(command(["docker", "container", "inspect", name], "PROBE_NAME", check=False).returncode != 0,
         "PREEXISTING_PROBE")
    arguments = ["docker", "run", "--rm", "-i", "--name", name,
                 "--label", "imagine.p25.owner=" + lab.token]
    arguments += lab.base_args() + lab.mount(ROOT, "/repo")
    arguments += lab.mount(lab.passwords["src"], "/run/secrets/p25-password")
    arguments += ["-e", "GITHUB_ACTIONS=" + os.environ["GITHUB_ACTIONS"],
                  "-e", "GITHUB_REPOSITORY=" + os.environ["GITHUB_REPOSITORY"],
                  "-e", "AIA_P68_PROBE=isolated-p68-v1", "--entrypoint", "php", lab.php_image,
                  "/repo/tools/p68-owner-draft-recovery-probe.php", mode]
    lab.extra[name] = name
    result = command(arguments, "PROBE", data=json.dumps(payload or {}, sort_keys=True,
                     separators=(",", ":")).encode(), check=False, timeout=30)
    lab.remove_owned(name)
    markers = re.findall(rb"^P68_PROBE_DENIED_P68_[A-Z0-9_]+$", result.stderr, re.MULTILINE)
    if denied is not None:
        need(result.returncode != 0 and markers == [("P68_PROBE_DENIED_P68_" + denied).encode()]
             and not result.stdout, "EXPECTED_RECOVERY_DENIAL")
        return None
    if result.returncode:
        raise Denied(markers[0].decode() if len(markers) == 1 else "P68_PROBE_FAILED")
    need(len(result.stdout) <= 40000, "PROBE_OUTPUT_BOUND")
    result = json.loads(result.stdout)
    need(isinstance(result, dict), "PROBE_OBJECT")
    return result


def capture(lab, snapshot=None):
    result = probe(lab, "capture", {"snapshot": snapshot} if snapshot else None)
    need(set(result) == {"schema", "tables", "security", "sequence"}
         and result["schema"] == "P68_P67_STATE_V1" and set(result["tables"]) == TABLES,
         "CAPTURE_CLOSED_SHAPE")
    for group in ("tables", "security"):
        for name, item in result[group].items():
            fields = {"rows", "sha256"} | ({"items"} if (group, name) == ("security", "relations") else set())
            need(set(item) == fields and type(item["rows"]) is int and item["rows"] >= 0
                 and re.fullmatch(r"[a-f0-9]{64}", item["sha256"]), "CAPTURE_FINGERPRINT")
    relations = result["security"]["relations"]["items"]
    need(set(relations) == TABLES | {"audit_id_seq"}, "RELATION_DIAGNOSTIC_SCOPE")
    roles = {"PUBLIC", "postgres", "pg_database_owner", "aia_p67_owner", "aia_p67_create", "aia_p67_read"}
    privileges = {"SELECT", "INSERT", "UPDATE", "DELETE", "TRUNCATE", "REFERENCES", "TRIGGER", "USAGE"}
    for row in relations.values():
        need(set(row) == {"relkind", "owner", "relrowsecurity", "relforcerowsecurity", "acl"}
             and row["relkind"] in ("r", "S") and row["owner"] in roles
             and type(row["relrowsecurity"]) is bool and type(row["relforcerowsecurity"]) is bool
             and isinstance(row["acl"], list), "RELATION_DIAGNOSTIC_FIELDS")
        need(all(isinstance(acl, list) and len(acl) == 4 and acl[0] in roles and acl[1] in roles
                 and acl[2] in privileges and type(acl[3]) is bool for acl in row["acl"]), "RELATION_DIAGNOSTIC_ACL")
    need(set(result["sequence"]) == {"last_value", "is_called"}
         and type(result["sequence"]["last_value"]) is int
         and result["sequence"]["is_called"] is True, "USED_AUDIT_SEQUENCE")
    return result


def compare_state(actual, expected, label):
    need(actual["schema"] == expected["schema"], label + "_SCHEMA")
    for group in ("tables", "security"):
        need(set(actual[group]) == set(expected[group]), label + "_INVENTORY")
        for name in expected[group]:
            if (group, name) == ("security", "relations") and actual[group][name] != expected[group][name]:
                for relation in sorted(TABLES | {"audit_id_seq"}):
                    before = expected[group][name]["items"][relation]
                    after = actual[group][name]["items"][relation]
                    fields = [field for field in before if before[field] != after[field]]
                    if fields:
                        # Only the seven fixed schema objects and validated public
                        # owner/kind/RLS/ACL metadata can reach this diagnostic.
                        print(json.dumps({"check": "P68_RELATION_DIFFERENCE", "relation": relation,
                              "expected": {field: before[field] for field in fields},
                              "actual": {field: after[field] for field in fields}}, sort_keys=True), flush=True)
            need(actual[group][name] == expected[group][name], label + "_" + group.upper() + "_" + name.upper())
    need(actual["sequence"] == expected["sequence"], label + "_SEQUENCE")


def snapshot_dump(lab, output):
    """One exported snapshot for the manifest and custom dump; source quiescent."""
    application = "p68_snapshot_" + secrets.token_hex(5)
    process = subprocess.Popen(
        ["docker", "exec", "-i", lab.containers["src"], "psql", "-X", "-qAt",
         "-v", "ON_ERROR_STOP=1", "-U", "postgres", "-d", "imagine_agents_ci"],
        stdin=subprocess.PIPE, stdout=subprocess.PIPE, stderr=subprocess.PIPE,
    )
    try:
        setup = ("SET application_name='" + application + "';SET statement_timeout='60s';"
                 "SET idle_in_transaction_session_timeout='60s';SET lock_timeout='2s';"
                 "BEGIN ISOLATION LEVEL REPEATABLE READ READ ONLY;SELECT pg_export_snapshot();\n")
        process.stdin.write(setup.encode())
        process.stdin.flush()
        frame = b""
        deadline = time.monotonic() + 10
        with selectors.DefaultSelector() as selector:
            selector.register(process.stdout, selectors.EVENT_READ)
            while b"\n" not in frame:
                remaining = deadline - time.monotonic()
                need(remaining > 0 and selector.select(timeout=remaining), "SNAPSHOT_EXPORT_TIMEOUT")
                chunk = os.read(process.stdout.fileno(), 81 - len(frame))
                need(chunk and len(frame) + len(chunk) <= 80, "SNAPSHOT_EXPORT_FRAME")
                frame += chunk
        snapshot = frame.decode().strip()
        need(re.fullmatch(r"[0-9A-Fa-f]{8}-[0-9A-Fa-f]{8}-[0-9]+", snapshot), "SNAPSHOT_EXPORT")
        captured = capture(lab, snapshot)
        with output.open("xb") as stream:
            os.fchmod(stream.fileno(), 0o600)
            dumped = subprocess.run(
                ["docker", "exec", lab.containers["src"], "pg_dump", "-U", "postgres",
                 "-d", "imagine_agents_ci", "--format=custom", "--snapshot=" + snapshot,
                 "--lock-wait-timeout=2s"],
                stdout=stream, stderr=subprocess.PIPE, timeout=45,
            )
            need(dumped.returncode == 0, "CONSISTENT_PG_DUMP")
        output_tail, _ = process.communicate(input=b"ROLLBACK;\n", timeout=5)
        need(process.returncode == 0 and not output_tail.strip(), "SNAPSHOT_CLOSED")
        # Sequences are not MVCC data. The lab has no concurrent callers and
        # verifies complete source equality before any subsequent mutation.
        compare_state(capture(lab), captured, "QUIESCENT_SNAPSHOT")
        need(lab._sql("src", "SELECT count(*) FROM pg_stat_activity WHERE application_name='"
                      + application + "'") == "0", "SNAPSHOT_SESSION_CLEANUP")
        body = checked_dump(output)
        return captured, hashlib.sha256(body).hexdigest()
    finally:
        if process.poll() is None:
            try:
                process.communicate(input=b"ROLLBACK;\n", timeout=3)
            except (OSError, ValueError, subprocess.SubprocessError):
                process.kill()
                process.communicate(timeout=5)


def checked_dump(path, expected_hash=None):
    metadata = path.lstat()
    need(stat.S_ISREG(metadata.st_mode) and not path.is_symlink() and metadata.st_nlink == 1
         and stat.S_IMODE(metadata.st_mode) == 0o600 and 5 < metadata.st_size < LIMIT,
         "PRIVATE_DUMP_FILE")
    body = path.read_bytes()
    need(body.startswith(b"PGDMP")
         and (expected_hash is None or hashlib.sha256(body).hexdigest() == expected_hash), "DUMP_INTEGRITY")
    return body


def restore(lab, dump, digest):
    body = checked_dump(dump, digest)
    lab._start("src")  # A different lab, volume, owner token and network namespace.
    command(["docker", "exec", "-i", lab.containers["src"], "sh", "-c",
             "cat > /var/lib/postgresql/data/pg_hba.conf"], "DESTINATION_TCP_PASSWORD",
            data=b"local all postgres trust\nlocal all all reject\n"
                 b"host all all 127.0.0.1/32 scram-sha-256\n"
                 b"host all all ::1/128 scram-sha-256\n")
    need(lab._sql("src", "SELECT pg_reload_conf()") == "t", "DESTINATION_HBA_RELOAD")
    roles = "".join("CREATE ROLE aia_p67_" + name + " NOLOGIN NOINHERIT NOSUPERUSER NOBYPASSRLS "
                    "NOCREATEDB NOCREATEROLE NOREPLICATION;" for name in ("owner", "create", "read"))
    lab._sql("src", roles)
    command(["docker", "exec", "-i", lab.containers["src"], "pg_restore", "-U", "postgres",
             "-d", "imagine_agents_ci", "--exit-on-error"], "RESTORE_OWNER_ACL", data=body)


def current_authority(source, legacy):
    before = capture(source)
    source._sql("src", "BEGIN;UPDATE aia_p67_owner.service_grants SET active=false,revision=revision+1"
                " WHERE company_id='" + legacy.COMPANY_A + "' AND operation='crm.rule.draft.create';"
                "UPDATE aia_p67_owner.mac_keys SET active=false,revision=revision+1 WHERE key_id='p67_read_a';COMMIT;")
    after = capture(source)
    for name in TABLES - {"service_grants", "mac_keys"}:
        need(after["tables"][name] == before["tables"][name], "REVOCATION_ONLY_AUTHORITY")
    need(after["security"] == before["security"] and after["sequence"] == before["sequence"],
         "REVOCATION_PRESERVES_SECURITY_AND_SEQUENCE")
    value = probe(source, "authority")
    need(set(value) == {"companies", "grants", "keys"}
         and len(value["companies"]) == 2 and len(value["grants"]) == 4 and len(value["keys"]) == 4,
         "CURRENT_AUTHORITY_INVENTORY")
    need(sum(item["active"] is False for item in value["grants"]) == 1
         and sum(item["active"] is False for item in value["keys"]) == 1
         and all("secret" not in item for item in value["keys"]), "POST_SNAPSHOT_REVOCATIONS")
    return value, after


def reconcile(source, destination, authority, restored):
    need(probe(destination, "quarantine") == {"restored_roles_nologin": True,
                                             "quarantined_functions_denied": True}, "QUARANTINE")
    quarantined = capture(destination)
    need(quarantined["tables"] == restored["tables"]
         and quarantined["sequence"] == restored["sequence"], "QUARANTINE_PRESERVES_DATA")
    fresh = {operation: secrets.token_hex(32) for operation in ("create", "read")}
    old = {operation: source.secrets[operation].read_text() for operation in ("create", "read")}
    need(fresh["create"] != fresh["read"] and all(fresh[name] != old[name] for name in fresh), "FRESH_PASSWORDS")
    invalid = copy.deepcopy(authority)
    invalid["keys"][-1]["secret_hash"] = "0" * 64
    need(invalid != authority, "INVALID_AUTHORITY_CONTROL")
    for metadata, code in (({}, "CURRENT_AUTHORITY_SHAPE"),
                           ({"authority": invalid}, "NO_SCOPE_OR_KEY_REPLACEMENT")):
        probe(destination, "apply-current", {**fresh, **metadata}, denied=code)
        compare_state(capture(destination), quarantined, "DENIED_RECOVERY_UNCHANGED")
        need(probe(destination, "quarantine") == {"restored_roles_nologin": True,
                                                 "quarantined_functions_denied": True},
             "DENIED_RECOVERY_REMAINS_INERT")
    applied = probe(destination, "apply-current", {"authority": authority, **fresh})
    need(applied == {"current_authority_applied": True, "no_scope_added": True,
                     "fresh_logins_provisioned": True}, "CURRENT_AUTHORITY_BEFORE_LOGIN")
    need(probe(destination, "authority") == authority, "CURRENT_AUTHORITY_EXACT")
    recovered = capture(destination)
    need(recovered["security"] == restored["security"]
         and recovered["sequence"] == restored["sequence"], "ORIGINAL_PRIVILEGES_AND_SEQUENCE")
    for name in ("drafts", "nonces", "audit"):
        need(recovered["tables"][name] == restored["tables"][name], "BUSINESS_HISTORY_PRESERVED")
    need(probe(destination, "old-passwords", old) == {"source_passwords_rejected": 2}, "LOGIN_PASSWORDS_NOT_RESTORED")
    for operation, password in fresh.items():
        destination.secrets[operation] = destination.private_file(operation + "-password", password.encode())
    destination.keys = dict(source.keys)
    destination.fingerprints = dict(source.fingerprints)
    destination.certificates = dict(source.certificates)
    destination.private_file("ca.crt", (source.directory / "ca.crt").read_bytes())
    need(destination.php("permissions") == {"execute_only": True, "roles_separate": True,
                                            "direct_tables_denied": True, "forced_rls": True},
         "RESTORED_EXECUTE_ONLY_AND_RLS")
    return recovered


def restored_http(destination, legacy, business_a, business_b, original_nonce, recovered):
    destination.start_http()
    reader = str(uuid.uuid4())
    need(reader != business_b["creator_id"], "DISTINCT_CURRENT_READER")
    reply, _ = destination.accept(destination.request(business_b, "read", actor=reader), client="client-b")
    need(reply["business"] == business_b, "HISTORICAL_AUTHOR_AND_CONTENT")
    after_read = capture(destination)
    need(after_read["sequence"]["last_value"] == recovered["sequence"]["last_value"] + 1
         and after_read["tables"]["audit"]["rows"] == recovered["tables"]["audit"]["rows"] + 1,
         "RESTORED_AUDIT_SEQUENCE_CONTINUES")
    destination.accept(destination.request(business_b), client="client-b")
    immutable = capture(destination)
    need(immutable["tables"]["drafts"] == recovered["tables"]["drafts"], "IDEMPOTENT_RETRY_AUTHORSHIP")
    # A new valid MAC and current TTL reuse a nonce actually consumed before
    # the snapshot. Its denial must come from restored replay state, not age.
    replay = destination.request(business_b, nonce=original_nonce)
    need(replay["authorization"]["expires_at"] > replay["authorization"]["issued_at"], "REPLAY_CURRENT_TTL")
    destination.deny(replay, client="client-b", label="RESTORED_NONCE_CURRENT_AUTH")
    source_collision = copy.deepcopy(business_b)
    source_collision["draft_ref"] = str(uuid.uuid4())
    destination.deny(destination.request(source_collision), client="client-b", label="RESTORED_SOURCE_UNIQUE")
    author_collision = copy.deepcopy(business_b)
    author_collision["creator_id"] = str(uuid.uuid4())
    destination.deny(destination.request(author_collision), client="client-b", label="RESTORED_REF_IMMUTABLE_AUTHOR")
    destination.deny(destination.request(business_a), label="CURRENT_CREATE_GRANT_REVOKED")
    destination.deny(destination.request(business_a, "read"), operation="read", label="CURRENT_READ_KEY_REVOKED")
    compare_state(capture(destination), immutable, "RESTORED_DENIALS_UNCHANGED")
    destination.accept(destination.request(business_a, "read", company=legacy.COMPANY_B),
                       status="ABSENT", client="client-b")
    destination.accept(destination.request(legacy.make_business(legacy.COMPANY_B), "read"),
                       status="ABSENT", client="client-b")
    destination.accept(destination.request(business_b, "read", actor=reader), client="client-b")
    final = capture(destination)
    need(final["tables"]["drafts"] == recovered["tables"]["drafts"]
         and final["tables"]["nonces"]["rows"] == final["tables"]["audit"]["rows"]
         and final["tables"]["audit"]["rows"] == recovered["tables"]["audit"]["rows"] + destination.positive,
         "FINAL_IMMUTABLE_HISTORY_AND_AUDIT")
    return final


def main():
    temporary, legacy = preflight()
    with tempfile.TemporaryDirectory(prefix="aia-p68-private-", dir=temporary) as directory:
        private = Path(directory)
        need(stat.S_IMODE(private.stat().st_mode) == 0o700, "PRIVATE_TEMPORARY_DIRECTORY")
        source = destination = None
        try:
            step("SOURCE_FIXTURE")
            source = legacy.create_lab("p68-source-" + secrets.token_hex(6), private / "source")
            source.prepare()
            source.start_http()
            business_a = legacy.make_business()
            business_b = legacy.make_business(legacy.COMPANY_B)
            source.accept(source.request(business_a), draft_delta=1)
            initial_b = source.request(business_b)
            source.accept(initial_b, draft_delta=1, client="client-b")
            source.accept(source.request(business_b, "read", actor=str(uuid.uuid4())), client="client-b")
            step("CONSISTENT_SNAPSHOT")
            dump = private / "owner-draft-fixture.dump"
            saved, digest = snapshot_dump(source, dump)
            need(saved["tables"]["drafts"]["rows"] == 2
                 and saved["tables"]["nonces"]["rows"] == saved["tables"]["audit"]["rows"] == 3,
                 "SNAPSHOT_REAL_HTTP_FIXTURE")
            step("CURRENT_REVOCATION")
            authority, source_current = current_authority(source, legacy)
            source.deny(source.request(business_a), label="SOURCE_GRANT_REVOKED")
            source.deny(source.request(business_a, "read"), operation="read", label="SOURCE_KEY_REVOKED")
            compare_state(capture(source), source_current, "SOURCE_REVOCATION_DENIALS_UNCHANGED")
            step("RESTORE_NEW_CLUSTER")
            destination = legacy.create_lab("p68-target-" + secrets.token_hex(6), private / "destination")
            restore(destination, dump, digest)
            need(source.containers["src"] != destination.containers["src"]
                 and source.volumes["src"] != destination.volumes["src"] and source.token != destination.token,
                 "SEPARATE_OWNED_CLUSTERS")
            restored = capture(destination)
            compare_state(restored, saved, "EXACT_RESTORE")
            step("QUARANTINE_AND_CURRENT_AUTHORITY")
            recovered = reconcile(source, destination, authority, restored)
            for name in ("companies", "mac_keys", "service_grants"):
                need(recovered["tables"][name] == source_current["tables"][name], "CURRENT_AUTHORITY_TABLES")
            step("RESTORED_HTTP")
            final = restored_http(destination, legacy, business_a, business_b,
                                  initial_b["authorization"]["nonce"], recovered)
            compare_state(capture(source), source_current, "SOURCE_PRESERVED_BY_DESTINATION")
            checked_dump(dump, digest)
            summary = {
                "schema": "P68_OWNER_DRAFT_RECOVERY_RESULT_V1", "result": "PASS",
                "pg16_clusters": 2, "restored_datasets": 6, "restored_functions": 9,
                "source_positive_http": source.positive, "source_revocation_denials": source.negative,
                "restored_positive_http": destination.positive, "restored_negative_http": destination.negative,
                "current_authority_negative_probes": 2, "source_passwords_rejected": 2,
                "drafts": final["tables"]["drafts"]["rows"], "audit": final["tables"]["audit"]["rows"],
                "consistent_exported_snapshot": True, "ownership_acl_rls_preserved": True,
                "historical_authorship_preserved": True, "idempotency_and_nonce_preserved": True,
                "durability_boundary": "EXPORTED_SNAPSHOT", "post_snapshot_commits_recovered": False,
                "zero_rpo_or_distributed_recovery_proven": False,
                "sequence_continued": True, "current_revocations_applied_before_login": True,
                "missing_current_authority_fails_closed": True, "source_preserved": True,
                "current_authority_from_still_live_fixture_source": True,
                "independent_disaster_authority_available": False, "external_backup_verified": False,
                "real_crm_schema_restored": False, "human_crm_session_verified": False,
                "secret_or_dump_artifacts": False, "runtime_egress": False, "host_ports": False,
                "operational": False, "executed": False, "release": "NO_GO",
            }
        finally:
            # Preserve the failing phase in diagnostics while cleanup always runs.
            print("P68_PHASE_OWNED_CLEANUP", flush=True)
            errors = []
            for lab in (destination, source):
                if lab is not None:
                    try:
                        lab.cleanup()
                    except Exception:
                        errors.append(True)
            need(not errors, "OWNED_CLEANUP_FAILED")
    need(not private.exists(), "PRIVATE_ARCHIVE_CLEANUP")
    print(json.dumps(summary, sort_keys=True), flush=True)
    print("AIA_P68_OWNER_DRAFT_RECOVERY_PASS_NO_GO", flush=True)


if __name__ == "__main__":
    try:
        main()
    except Exception as error:
        marker = str(error)
        if not re.fullmatch(r"P(?:25|27|67|68)_[A-Z0-9_]{1,160}", marker):
            marker = "P68_UNEXPECTED_FAILURE"
        print("P68_FAILED_" + PHASE + "_" + marker, file=sys.stderr, flush=True)
        raise SystemExit(1)
