#!/usr/bin/env python3
"""P24: recover a real, synthetic n8n database, volume and encryption key.

GitHub-hosted disposable CI only. Reuses the reviewed Compose, native workflow,
P20 package/P22 volume validation and P23 signature. No external backup,
customer credentials, operational grants or deployment are demonstrated.
"""
from __future__ import annotations

import hashlib
import json
import os
import re
import secrets
import shutil
import stat
import subprocess
import sys
import tarfile
import tempfile
import time
from pathlib import Path

from check_p21_crypto_pg_restore import decrypt, make_cert
from material_preflight import fingerprint
from seal_backup import COMPONENTS, Denied, inspect_volume, seal, verify_decrypted
from signed_receipt_p23 import Rejected, public_fingerprint, sign, verify

ROOT = Path(__file__).resolve().parents[3]
PRIVATE = ROOT / "infra/n8n-private"
sys.path.insert(0, str(PRIVATE))
from check_native_noai_flow import (  # noqa: E402
    Denied as NativeDenied, parse_json, parse_output, source_ok, verify_export, verify_result,
)

N8N_IMAGE = "ghcr.io/n8n-io/n8n@sha256:b4b90747b81fcda7e76550c0d3aa7f0f64917a1e9573cff40d6834eaf2e0f8ea"
CREDENTIAL_ID = "P24SyntheticCred1"
CREDENTIAL_NAME = "P24 synthetic recovery credential"
MARKER = b"CENTRAL_AUTO_P24_SYNTHETIC_VOLUME_ONLY\n"
AUXILIARY = (
    ("crm-service.dump", "p24_crm_marker_ci", "SYNTHETIC_CRM_COMPONENT"),
    ("agents-service.dump", "p24_agents_marker_ci", "SYNTHETIC_AGENTS_COMPONENT"),
)


class Failure(Exception):
    pass


def require(condition: bool, code: str) -> None:
    if not condition:
        raise Failure(code)


def run(args: list[str], label: str, *, env: dict | None = None,
        stdin: bytes | None = None, output: Path | None = None,
        timeout: int = 180, check: bool = True) -> subprocess.CompletedProcess:
    """Capture all subprocess output; only fixed diagnostic labels reach CI logs."""
    target = output.open("xb") if output is not None else None
    try:
        if target is not None:
            os.fchmod(target.fileno(), 0o600)
        result = subprocess.run(
            args, input=stdin, stdout=target or subprocess.PIPE,
            stderr=subprocess.PIPE, env=env, timeout=timeout, check=False,
        )
        if target is not None:
            target.flush()
            os.fsync(target.fileno())
        if check and result.returncode:
            raise Failure(label + "_EXIT_" + str(result.returncode))
        return result
    finally:
        if target is not None:
            target.close()


def materialize_verified_package(package: Path, destination: Path) -> dict:
    """Validate everything BEFORE creating a private directory or any member.

    Copy only the five fixed component names to newly created regular files.
    Never pass the untrusted outer archive to tar.extract/all or to a shell.
    """
    proof = verify_decrypted(package)
    require(proof["component_hashes_match"] is True and
            proof["volume_tar_safe_for_reviewed_extraction"] is True and
            proof["go_for_installation"] is False, "PACKAGE_PROOF_INVALID")
    destination.mkdir(mode=0o700, exist_ok=False)
    with tarfile.open(package, "r:") as archive:
        for name in COMPONENTS:
            entry = archive.getmember(name)
            require(entry.isfile() and entry.name == name, "COMPONENT_NOT_REGULAR")
            source = archive.extractfile(entry)
            require(source is not None, "COMPONENT_UNREADABLE")
            fd = os.open(destination / name,
                         os.O_WRONLY | os.O_CREAT | os.O_EXCL | os.O_NOFOLLOW, 0o600)
            with source, os.fdopen(fd, "wb") as target:
                shutil.copyfileobj(source, target, length=1024 * 1024)
                target.flush()
                os.fsync(target.fileno())
    return proof


def normalized_volume(source: Path, destination: Path) -> None:
    """Rebuild a validated TAR using only canonical regular files/directories.

    Do not inherit archive ownership, extension headers, links or permissions.
    The resulting archive has the n8n container's fixed uid/gid and private
    modes. Only this newly constructed archive is extracted into an empty,
    job-owned Docker volume; the original volume TAR is never extracted.
    """
    inspect_volume(source)
    with destination.open("xb") as output:
        os.fchmod(output.fileno(), 0o600)
        with tarfile.open(fileobj=output, mode="w|", format=tarfile.PAX_FORMAT) as target:
            root = tarfile.TarInfo(".")
            root.type = tarfile.DIRTYPE
            root.mode = 0o700
            root.uid = root.gid = 1000
            target.addfile(root)
            with tarfile.open(source, "r|*") as original:
                for entry in original:
                    name = entry.name.removeprefix("./").rstrip("/")
                    if name in ("", "."):
                        continue
                    clean = tarfile.TarInfo(name)
                    clean.uid = clean.gid = 1000
                    clean.mode = 0o700 if entry.isdir() else 0o600
                    if entry.isdir():
                        clean.type = tarfile.DIRTYPE
                        target.addfile(clean)
                    else:
                        require(entry.isfile(), "VOLUME_TYPE_CHANGED")
                        clean.size = entry.size
                        stream = original.extractfile(entry)
                        require(stream is not None, "VOLUME_UNREADABLE")
                        with stream:
                            target.addfile(clean, stream)
        output.flush()
        os.fsync(output.fileno())


class Stack:
    def __init__(self, project: str, directory: Path, pg_image: str):
        self.project = project
        self.directory = directory
        directory.mkdir(mode=0o700)
        self.env = dict(os.environ)
        self.env.update({
            "COMPOSE_PROJECT_NAME": project, "N8N_EXPECTED_PROJECT": project,
            "N8N_IMAGE": N8N_IMAGE, "N8N_PG_IMAGE": pg_image,
            "N8N_PG_ADMIN_PASSWORD_FILE": str(directory / "admin"),
            "N8N_PG_PASSWORD_FILE": str(directory / "runtime"),
            "N8N_ENCRYPTION_KEY_FILE": str(directory / "encryption"),
        })
        # Direct file mounts must be readable by uid 1000 in n8n. The parent
        # directory is 0700 on the runner; no real secret is used in this lab.
        for name in ("admin", "runtime", "encryption"):
            p = directory / name
            p.write_text(secrets.token_hex(32), encoding="ascii")
            p.chmod(0o444)
        self.owned = False

    def command(self, *args: str) -> list[str]:
        return ["docker", "compose", "--profile", "homologation", "-f",
                str(PRIVATE / "docker-compose.yml"), *args]

    def dc(self, *args: str, label: str = "COMPOSE_COMMAND", **kwargs):
        return run(self.command(*args), label, env=self.env, **kwargs)

    def claim_empty(self) -> None:
        for kind, command in (
            ("container", ["docker", "ps", "-aq"]),
            ("volume", ["docker", "volume", "ls", "-q"]),
            ("network", ["docker", "network", "ls", "-q"]),
        ):
            items = run(command + ["--filter", "label=com.docker.compose.project=" + self.project],
                        "CHECK_EMPTY_" + kind.upper()).stdout.strip()
            require(not items, "REFUSE_PREEXISTING_PROJECT")
        # Also reject unlabelled volumes/networks with the names Compose uses.
        for resource, suffixes in (("volume", ("n8n_data", "n8n_pgdata")),
                                   ("network", ("n8n_storage", "n8n_apps_private"))):
            for suffix in suffixes:
                existing = run(["docker", resource, "inspect", self.project + "_" + suffix],
                               "CHECK_RESOURCE", check=False)
                require(existing.returncode != 0, "REFUSE_PREEXISTING_RESOURCE")
        config = self.dc("config", "--format", "json").stdout
        run([sys.executable, str(PRIVATE / "check_compose_static.py")],
            "COMPOSE_POLICY", env=self.env, stdin=config)
        self.owned = True

    def sql(self, query: str, database: str = "n8n") -> str:
        return self.dc("exec", "-T", "n8n-db", "psql", "-X", "-qAt",
                       "-v", "ON_ERROR_STOP=1", "-U", "n8n_bootstrap_admin",
                       "-d", database, "-c", query, label="POSTGRES_QUERY").stdout.decode().strip()

    def cli(self, *args: str, **kwargs):
        return self.dc("run", "-T", "--rm", "--no-deps", "--entrypoint", "n8n",
                       "n8n", *args, label="N8N_CLI", **kwargs)

    def import_json(self, kind: str, payload: bytes) -> None:
        require(kind in ("workflow", "credentials"), "IMPORT_KIND")
        script = ("umask 077; cat > /tmp/p24-input.json; "
                  "n8n import:" + kind + " --input=/tmp/p24-input.json "
                  ">/tmp/p24-command.log 2>&1")
        self.dc("run", "-T", "--rm", "--no-deps", "--entrypoint", "/bin/sh",
                "n8n", "-eu", "-c", script, label="N8N_IMPORT_" + kind.upper(), stdin=payload)

    def export_json(self, kind: str, output: Path, *, decrypted: bool = False,
                    wrong_key: Path | None = None, check: bool = True):
        require(kind in ("workflow", "credentials") and
                (not decrypted or kind == "credentials"), "EXPORT_KIND")
        script = ("umask 077; n8n export:" + kind + " --all " +
                  ("--decrypted " if decrypted else "") +
                  "--output=/tmp/p24-output.json >/tmp/p24-command.log 2>&1; "
                  "test -s /tmp/p24-output.json; cat /tmp/p24-output.json")
        env = dict(self.env)
        if wrong_key is not None:
            env["N8N_ENCRYPTION_KEY_FILE"] = str(wrong_key)
        return run(self.command("run", "-T", "--rm", "--no-deps", "--entrypoint",
                                "/bin/sh", "n8n", "-eu", "-c", script),
                   "N8N_EXPORT_" + kind.upper(), env=env, output=output, check=check)

    def inspect_runtime(self) -> None:
        ids = self.dc("ps", "-q", "--status", "running").stdout.decode().split()
        require(len(ids) == 2, "EXPECTED_TWO_RUNNING_SERVICES")
        for ident in ids:
            item = json.loads(run(["docker", "inspect", ident], "INSPECT_CONTAINER").stdout)[0]
            require(not item["HostConfig"]["Privileged"] and
                    not item["HostConfig"].get("PortBindings"), "RUNTIME_EXPOSURE")
            require(item["Config"]["Labels"]["com.docker.compose.project"] == self.project,
                    "CONTAINER_PROJECT_MISMATCH")
            if item["Config"]["Labels"]["com.docker.compose.service"] == "n8n":
                require(item["Config"]["Image"] == N8N_IMAGE and any(
                    mount.get("Type") == "volume" and
                    mount.get("Name") == self.project + "_n8n_data" and
                    mount.get("Destination") == "/home/node/.n8n"
                    for mount in item["Mounts"]), "N8N_IMAGE_OR_VOLUME_CHANGED")
            require(not any(value.startswith(("POSTGRES_PASSWORD=", "DB_POSTGRESDB_PASSWORD=",
                                               "N8N_ENCRYPTION_KEY="))
                            for value in item["Config"]["Env"]), "SECRET_IN_CONTAINER_ENV")
            for network in item["NetworkSettings"]["Networks"]:
                data = json.loads(run(["docker", "network", "inspect", network],
                                      "INSPECT_NETWORK").stdout)[0]
                require(data["Internal"] is True and
                        data["Labels"]["com.docker.compose.project"] == self.project,
                        "NETWORK_NOT_INTERNAL_OR_NOT_OWNED")
        require(re.fullmatch(r"17\d{4}", self.sql("SHOW server_version_num")) is not None,
                "POSTGRES_17_RUNTIME_REQUIRED")
        require(self.sql("SELECT rolsuper OR rolbypassrls OR rolcreatedb OR rolcreaterole "
                         "FROM pg_roles WHERE rolname='n8n_runtime'") == "f", "DB_ROLE_ESCALATED")
        require(self.sql("SELECT count(*)>0 FROM pg_stat_activity WHERE usename='n8n_runtime' "
                         "AND datname='n8n'") == "t", "N8N_NOT_USING_RESTRICTED_ROLE")

    def cleanup(self) -> None:
        if self.owned:
            self.dc("down", "--volumes", "--remove-orphans", label="CLEANUP", timeout=120)


def credential(path: Path, expected: dict, *, encrypted: bool) -> str | None:
    require(stat.S_IMODE(path.stat().st_mode) == 0o600, "EXPORT_FILE_NOT_PRIVATE")
    data = json.loads(path.read_bytes())
    require(isinstance(data, list) and len(data) == 1, "CREDENTIAL_COUNT")
    item = data[0]
    require(isinstance(item, dict) and
            item.get("id") == CREDENTIAL_ID and item.get("type") == "httpBasicAuth" and
            item.get("name") == CREDENTIAL_NAME, "CREDENTIAL_IDENTITY")
    if encrypted:
        value = item.get("data")
        require(isinstance(value, str) and len(value) > 20 and
                expected["password"] not in value and expected["user"] not in value,
                "CREDENTIAL_NOT_CIPHERTEXT")
        return hashlib.sha256(value.encode()).hexdigest()
    require(item.get("data") == expected, "RECOVERED_CREDENTIAL_DATA_MISMATCH")
    return None


def native_roundtrip(stack: Stack, path: Path, source: dict) -> dict:
    stack.export_json("workflow", path)
    exported = parse_json(path)
    require(isinstance(exported, list) and len(exported) == 1, "WORKFLOW_COUNT")
    verify_export(source, exported)
    ident = exported[0].get("id")
    require(isinstance(ident, str) and re.fullmatch(r"[A-Za-z0-9_-]{1,128}", ident) is not None,
            "WORKFLOW_ID")
    output = stack.cli("execute", "--id=" + ident, "--rawOutput").stdout
    return verify_result(parse_output(output))


def main() -> dict:
    require(os.environ.get("GITHUB_ACTIONS") == "true" and
            os.environ.get("GITHUB_REPOSITORY") == "rafhael100/imagine-ai-agents" and
            os.environ.get("P24_RUNNER_ENVIRONMENT") == "github-hosted", "HOSTED_CI_ONLY")
    require(not os.environ.get("DOCKER_HOST") and not os.environ.get("DOCKER_CONTEXT"),
            "REMOTE_DOCKER_FORBIDDEN")
    require(run(["docker", "context", "show"], "DOCKER_CONTEXT").stdout.strip() == b"default",
            "DEFAULT_DOCKER_CONTEXT_REQUIRED")
    context = json.loads(run(["docker", "context", "inspect", "default"], "DOCKER_CONTEXT").stdout)
    require(context[0]["Endpoints"]["docker"]["Host"] == "unix:///var/run/docker.sock",
            "LOCAL_DOCKER_SOCKET_REQUIRED")
    run_id = os.environ.get("GITHUB_RUN_ID", "")
    attempt = os.environ.get("GITHUB_RUN_ATTEMPT", "")
    require(re.fullmatch(r"[0-9]{1,20}", run_id) is not None and
            re.fullmatch(r"[0-9]{1,4}", attempt) is not None, "RUN_ID_INVALID")
    pg_image = os.environ.get("N8N_PG_IMAGE", "")
    require(re.fullmatch(r"(?:docker.io/library/)?postgres@sha256:[a-f0-9]{64}", pg_image) is not None,
            "POSTGRES_DIGEST_REQUIRED")
    source_flow = source_ok(parse_json(PRIVATE / "workflows/native-funnel-noai.workflow.json"))
    os.umask(0o077)
    with tempfile.TemporaryDirectory(prefix="central-p24-", dir=os.environ["RUNNER_TEMP"]) as tmp:
        workspace = Path(tmp)
        prefix = "imagine-p24-" + run_id + "-" + attempt
        src = Stack(prefix + "-src", workspace / "source-secrets", pg_image)
        dst = Stack(prefix + "-dst", workspace / "destination-secrets", pg_image)
        try:
            src.claim_empty()
            dst.claim_empty()
            print("P24_STAGE_SOURCE_BOOT", flush=True)
            src.dc("up", "-d", "--wait", "--wait-timeout", "180", timeout=240)
            src.inspect_runtime()
            src.dc("stop", "n8n")
            src.import_json("workflow", json.dumps(source_flow).encode())
            original_result = native_roundtrip(src, workspace / "original-workflow.json", source_flow)
            dummy = {"user": "SYNTHETIC_P24_USER", "password": "SYNTHETIC_" + secrets.token_hex(24)}
            src.import_json("credentials", json.dumps([{
                "id": CREDENTIAL_ID, "name": CREDENTIAL_NAME,
                "type": "httpBasicAuth", "data": dummy,
            }]).encode())
            src.export_json("credentials", workspace / "source-encrypted.json")
            credential_hash = credential(workspace / "source-encrypted.json", dummy, encrypted=True)
            src.export_json("credentials", workspace / "source-decrypted.json", decrypted=True)
            credential(workspace / "source-decrypted.json", dummy, encrypted=False)
            src.dc("run", "-T", "--rm", "--no-deps", "--entrypoint", "node", "n8n", "-e",
                   "require('fs').writeFileSync('/home/node/.n8n/p24-recovery-marker'," +
                   json.dumps(MARKER.decode()) + ",{mode:0o600})", label="WRITE_SYNTHETIC_VOLUME_MARKER")
            require(src.sql("SELECT count(*) FROM workflow_entity") == "1" and
                    src.sql("SELECT count(*) FROM credentials_entity") == "1" and
                    src.sql("SELECT count(*) FROM workflow_entity WHERE active") == "0",
                    "SOURCE_NOT_EXCLUSIVELY_SYNTHETIC")
            require(not src.dc("ps", "-q", "--status", "running", "n8n").stdout.strip(),
                    "SOURCE_APPLICATION_MUST_BE_STOPPED")

            print("P24_STAGE_REAL_N8N_SNAPSHOT", flush=True)
            snapshot = workspace / "snapshot"
            snapshot.mkdir(mode=0o700)
            src.dc("exec", "-T", "n8n-db", "pg_dump", "-U", "n8n_bootstrap_admin", "-d", "n8n",
                   "--format", "custom", "--no-owner", "--no-acl", output=snapshot / "n8n-pg.dump")
            for filename, database, value in AUXILIARY:
                src.dc("exec", "-T", "n8n-db", "createdb", "-U", "n8n_bootstrap_admin", database)
                src.sql("CREATE TABLE p24_synthetic_marker(value text PRIMARY KEY); "
                        "INSERT INTO p24_synthetic_marker VALUES ('" + value + "')", database)
                src.dc("exec", "-T", "n8n-db", "pg_dump", "-U", "n8n_bootstrap_admin", "-d", database,
                       "--format", "custom", "--no-owner", "--no-acl", output=snapshot / filename)
            run(["docker", "run", "--rm", "--network", "none", "--read-only", "--cap-drop", "ALL",
                 "--security-opt", "no-new-privileges:true", "--user", "1000:1000",
                 "-v", src.project + "_n8n_data:/input:ro", "--entrypoint", "tar", pg_image,
                 "-C", "/input", "-cf", "-", "."], "SNAPSHOT_VOLUME", output=snapshot / "n8n-data.tar")
            key_bytes = (src.directory / "encryption").read_bytes()
            (snapshot / "n8n-encryption.key").write_bytes(key_bytes)

            cert, key = make_cert(workspace, "p24-recipient")
            wrong_cert, wrong_key = make_cert(workspace, "p24-wrong-recipient")
            ciphertext = workspace / "backup.cms"
            seal_result = seal(snapshot, cert, fingerprint(cert), ciphertext)
            require(seal_result["encrypted"] is True and seal_result["go_for_installation"] is False,
                    "SEAL_RESULT")
            signer = workspace / "signer.key"
            public = workspace / "signer.pub"
            run(["openssl", "genpkey", "-algorithm", "Ed25519", "-out", str(signer)], "SIGNER_CREATE")
            run(["openssl", "pkey", "-in", str(signer), "-pubout", "-out", str(public)], "PUBLIC_SIGNER")
            pinned_signer = public_fingerprint(public)
            metadata = workspace / "receipt.json"
            signature = workspace / "receipt.sig"
            now = int(time.time())
            sign(ciphertext, signer, public, metadata, signature, now=now,
                 nonce="SYNTHETIC_RECEIPT_" + secrets.token_hex(16).upper())
            copy_dir = workspace / "runner-local-copy"
            copy_dir.mkdir(mode=0o700)
            copy = copy_dir / "backup.cms"
            shutil.copyfile(ciphertext, copy)
            verified = verify(copy, public, metadata, signature,
                              pinned_fingerprint=pinned_signer, now=int(time.time()))
            require(verified["ed25519_signature_valid"] is True and
                    verified["go_for_installation"] is False, "SIGNATURE_RESULT")
            tamper = bytearray(copy.read_bytes())
            tamper[-20] ^= 1
            altered = copy_dir / "tampered.cms"
            altered.write_bytes(tamper)
            try:
                verify(altered, public, metadata, signature,
                       pinned_fingerprint=pinned_signer, now=int(time.time()))
            except Rejected:
                pass
            else:
                raise Failure("SIGNED_TAMPER_ACCEPTED")
            quarantine = workspace / "quarantine"
            quarantine.mkdir(mode=0o700)
            require(not decrypt(copy, wrong_cert, wrong_key, quarantine / "wrong-key.tar"),
                    "WRONG_CMS_KEY_ACCEPTED")
            require(not decrypt(altered, cert, key, quarantine / "tamper.tar"), "GCM_TAMPER_ACCEPTED")
            # Positive consumer ordering is explicit: signature above -> CMS
            # authenticated decrypt -> full manifest/nested-TAR verification -> copy.
            clear = quarantine / "verified.tar"
            require(decrypt(copy, cert, key, clear), "AUTHENTICATED_DECRYPT_FAILED")
            recovered = workspace / "recovered-components"
            materialize_verified_package(clear, recovered)
            require((recovered / "n8n-encryption.key").read_bytes() == key_bytes, "RECOVERED_KEY_CHANGED")
            sanitized = workspace / "normalized-volume.tar"
            normalized_volume(recovered / "n8n-data.tar", sanitized)
            (dst.directory / "encryption").chmod(0o600)
            (dst.directory / "encryption").write_bytes((recovered / "n8n-encryption.key").read_bytes())
            (dst.directory / "encryption").chmod(0o444)

            print("P24_STAGE_ISOLATED_RESTORE", flush=True)
            dst.dc("up", "-d", "--wait", "--wait-timeout", "120", "n8n-db", timeout=180)
            dst.dc("exec", "-T", "n8n-db", "pg_restore", "-U", "n8n_bootstrap_admin", "-d", "n8n",
                   "--exit-on-error", "--no-owner", "--no-acl", "--role=n8n_runtime",
                   stdin=(recovered / "n8n-pg.dump").read_bytes())
            for filename, database, value in AUXILIARY:
                dst.dc("exec", "-T", "n8n-db", "createdb", "-U", "n8n_bootstrap_admin", database)
                dst.dc("exec", "-T", "n8n-db", "pg_restore", "-U", "n8n_bootstrap_admin", "-d", database,
                       "--exit-on-error", "--no-owner", "--no-acl", stdin=(recovered / filename).read_bytes())
                require(dst.sql("SELECT value FROM p24_synthetic_marker", database) == value,
                        "AUXILIARY_SYNTHETIC_COMPONENT_CHANGED")
            dst.dc("create", "n8n")
            # The input here is OUR regenerated archive with fixed owners/modes,
            # never the untrusted backup TAR. Only an empty job-owned volume mounts RW.
            run(["docker", "run", "--rm", "--network", "none", "--read-only",
                 "--security-opt", "no-new-privileges:true", "-v", str(sanitized) + ":/input.tar:ro",
                 "-v", dst.project + "_n8n_data:/output", "--entrypoint", "/bin/sh", pg_image,
                 "-eu", "-c", 'test -z "$(ls -A /output)"; tar -xf /input.tar -C /output --numeric-owner'],
                "RESTORE_NORMALIZED_VOLUME")
            dst.dc("up", "-d", "--wait", "--wait-timeout", "180", "n8n", timeout=240)
            dst.inspect_runtime()
            require(dst.sql("SELECT count(*) FROM workflow_entity") == "1" and
                    dst.sql("SELECT count(*) FROM credentials_entity") == "1" and
                    dst.sql("SELECT count(*) FROM workflow_entity WHERE active") == "0", "RESTORED_ROW_COUNTS")
            marker = dst.dc("exec", "-T", "n8n", "node", "-e",
                            "process.stdout.write(require('fs').readFileSync('/home/node/.n8n/p24-recovery-marker'))").stdout
            require(marker == MARKER, "RESTORED_VOLUME_MARKER")
            dst.dc("stop", "n8n")
            result = native_roundtrip(dst, workspace / "restored-workflow.json", source_flow)
            require(result == original_result, "NATIVE_RESULT_CHANGED_AFTER_RESTORE")
            dst.export_json("credentials", workspace / "restored-encrypted.json")
            require(credential(workspace / "restored-encrypted.json", dummy, encrypted=True) == credential_hash,
                    "ENCRYPTED_CREDENTIAL_CHANGED_AFTER_RESTORE")
            dst.export_json("credentials", workspace / "restored-decrypted.json", decrypted=True)
            credential(workspace / "restored-decrypted.json", dummy, encrypted=False)
            bad_key = workspace / "wrong-n8n-key"
            bad_key.write_text(secrets.token_hex(32), encoding="ascii")
            bad_key.chmod(0o444)
            wrong_output = workspace / "wrong-key-export.json"
            wrong = dst.export_json("credentials", wrong_output, decrypted=True, wrong_key=bad_key, check=False)
            require(wrong.returncode != 0 and wrong_output.stat().st_size == 0,
                    "WRONG_RESTORED_N8N_KEY_ACCEPTED")
            print("P24_STAGE_RESTORED_APPLICATION_PASS", flush=True)
            return {
                "schema": "CENTRAL_AUTO_P24_FULL_N8N_CRYPTO_RESTORE_V1",
                "real_n8n_schema_pg17_restored": True,
                "n8n_volume_and_key_restored": True,
                "separate_disposable_projects": 2,
                "inactive_native_workflows_restored": 1,
                "native_workflow_executed_before_and_after": True,
                "native_nodes_only": 3,
                "synthetic_credentials_decrypted_after_restore": 1,
                "encrypted_credential_bytes_preserved": True,
                "wrong_n8n_key_bootstrap_or_decrypt_denied": True,
                "wrong_cms_key_denied": True,
                "signed_tamper_denied_before_decryption": True,
                "gcm_tamper_denied": True,
                "database_passwords_rotated": True,
                "auxiliary_pg17_marker_databases_restored": 2,
                "real_crm_agents_schemas_restored": False,
                "networks_internal": True, "host_published_ports": 0,
                "ai_api_calls": 0, "ai_tokens": 0, "credits_debited": 0,
                "provider_usage_independently_verified": False,
                "external_backup_verified": False,
                "different_host_restore_verified": False,
                "real_signer_custody_verified": False,
                "real_customer_credentials_restored": False,
                "vps_modified": False, "go_for_installation": False,
            }
        finally:
            # Each project was required to be absent before we claimed it.
            # Cleanup never names another project's containers, networks or volumes.
            errors = []
            for stack in (dst, src):
                try:
                    stack.cleanup()
                except (Failure, OSError, subprocess.SubprocessError):
                    errors.append(stack.project)
            if errors:
                raise Failure("OWNED_STACK_CLEANUP_FAILED")


if __name__ == "__main__":
    try:
        print(json.dumps(main(), sort_keys=True))
        print("CENTRAL_AUTO_P24_FULL_N8N_CRYPTO_RESTORE_PASS NO_GO")
    except (Failure, Denied, Rejected, NativeDenied, OSError, ValueError, KeyError,
            tarfile.TarError, subprocess.SubprocessError) as error:
        # Only our static error codes, never command stderr or secret contents.
        code = str(error) if isinstance(error, Failure) else type(error).__name__
        print("CENTRAL_AUTO_P24_DENIED_" + code, file=sys.stderr)
        raise SystemExit(1)
