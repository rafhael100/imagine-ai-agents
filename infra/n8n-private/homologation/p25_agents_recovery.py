"""P25: actual candidate Agents schema recovery inside the P24 signed package.

Two new PG16 clusters, same required CI database name, no network/host ports.
The PHP probe invokes original P6/P16/P28/P30/P34 classes plus P19 SQL ingress.
All authority and signing data is synthetic. No operational IAM is inferred.
"""
from __future__ import annotations

import hashlib
import json
import os
import re
import secrets
import stat
import time
from pathlib import Path

from check_p24_full_n8n_restore import Failure, ROOT, require, run


class AgentsRecovery:
    def __init__(self, prefix: str, directory: Path):
        require(re.fullmatch(r"[a-z0-9][a-z0-9-]{1,58}", prefix) is not None,
                "P25_PROJECT_NAME")
        require(os.environ.get("GITHUB_ACTIONS") == "true" and
                os.environ.get("GITHUB_REPOSITORY") == "rafhael100/imagine-ai-agents" and
                os.environ.get("P24_RUNNER_ENVIRONMENT") == "github-hosted",
                "P25_HOSTED_CI_ONLY")
        require(not os.environ.get("DOCKER_HOST") and not os.environ.get("DOCKER_CONTEXT"),
                "P25_REMOTE_DOCKER_FORBIDDEN")
        self.project = prefix + "-agents"
        self.directory = directory
        require(not directory.exists() and not directory.is_symlink(), "P25_NEW_DIRECTORY_REQUIRED")
        self.directory_created = False
        self.pg_image = os.environ.get("AGENTS_PG_IMAGE", "")
        self.php_image = os.environ.get("AGENTS_PHP_IMAGE", "")
        require(re.fullmatch(r"(?:docker.io/library/)?postgres@sha256:[a-f0-9]{64}", self.pg_image) is not None,
                "P25_PG_DIGEST_REQUIRED")
        require(re.fullmatch(r"sha256:[a-f0-9]{64}", self.php_image) is not None,
                "P25_LOCAL_PHP_IMAGE_ID_REQUIRED")
        self.token = secrets.token_hex(16)
        self.containers: dict[str, str] = {}
        self.volumes: dict[str, str] = {}
        self.passwords: dict[str, Path] = {}
        self.seed = None
        self.current = None
        self.dump_hash = None

    def _start(self, which: str) -> None:
        require(which in ("src", "dst") and which not in self.containers, "P25_CLUSTER_SCOPE")
        if not self.directory_created:
            self.directory.mkdir(mode=0o700, exist_ok=False)
            self.directory_created = True
        name = self.project + "-" + which
        volume = name + "-data"
        for resource, identity in (("container", name), ("volume", volume)):
            existing = run(["docker", resource, "inspect", identity], "P25_RESOURCE_CHECK", check=False)
            require(existing.returncode != 0, "P25_PREEXISTING_RESOURCE")
        run(["docker", "volume", "create", "--label", "imagine.p25.owner=" + self.token, volume],
            "P25_CREATE_VOLUME")
        self.volumes[which] = volume
        created = json.loads(run(["docker", "volume", "inspect", volume], "P25_INSPECT_NEW_VOLUME").stdout)[0]
        require((created.get("Labels") or {}).get("imagine.p25.owner") == self.token,
                "P25_NEW_VOLUME_OWNERSHIP")
        password = self.directory / (which + "-password")
        password.write_text(secrets.token_hex(32), encoding="ascii")
        password.chmod(0o444)
        self.passwords[which] = password
        # Track the claimed name before run so partial container creation can be
        # inspected and removed only when its ownership label matches this job.
        self.containers[which] = name
        result = run([
            "docker", "run", "-d", "--name", name,
            "--label", "imagine.p25.owner=" + self.token,
            "--network", "none", "--security-opt", "no-new-privileges:true",
            "--memory", "512m", "--cpus", "1", "--pids-limit", "128",
            "-e", "POSTGRES_DB=imagine_agents_ci", "-e", "POSTGRES_USER=postgres",
            "-e", "POSTGRES_PASSWORD_FILE=/run/secrets/p25-password",
            "--mount", "type=bind,src=" + str(password) + ",dst=/run/secrets/p25-password,readonly",
            "--mount", "type=volume,src=" + volume + ",dst=/var/lib/postgresql/data",
            self.pg_image,
        ], "P25_START_POSTGRES")
        container = result.stdout.decode().strip()
        require(re.fullmatch(r"[a-f0-9]{64}", container) is not None, "P25_CONTAINER_ID")
        self.containers[which] = container
        for _ in range(60):
            ready = run(["docker", "exec", container, "pg_isready", "-h", "127.0.0.1", "-U", "postgres",
                         "-d", "imagine_agents_ci"], "P25_WAIT_POSTGRES", check=False, timeout=10)
            if ready.returncode == 0:
                break
            time.sleep(1)
        else:
            raise Failure("P25_POSTGRES_NOT_READY")
        data = json.loads(run(["docker", "inspect", container], "P25_INSPECT_POSTGRES").stdout)[0]
        require(data["HostConfig"]["NetworkMode"] == "none" and
                not data["HostConfig"].get("PortBindings") and
                data["Config"]["Labels"].get("imagine.p25.owner") == self.token and
                not data["HostConfig"]["Privileged"], "P25_CLUSTER_ISOLATION")
        require(re.fullmatch(r"16\d{4}", self._sql(which, "SHOW server_version_num")) is not None,
                "P25_POSTGRES_16_REQUIRED")

    def _sql(self, which: str, query: str) -> str:
        require(which in self.containers, "P25_MISSING_CLUSTER")
        result = run(["docker", "exec", "-i", self.containers[which], "psql", "-X", "-qAt",
                      "-v", "ON_ERROR_STOP=1", "-U", "postgres", "-d", "imagine_agents_ci"],
                     "P25_SQL", stdin=query.encode())
        return result.stdout.decode().strip()

    def _run_php(self, which: str, script: Path, mode: str,
                 payload: dict | None = None) -> dict:
        require(which in self.containers and which in self.passwords, "P25_PHP_CLUSTER")
        relative = script.resolve().relative_to(ROOT)
        require(script.is_file() and not script.is_symlink(), "P25_PHP_SOURCE")
        args = ["docker", "run", "--rm", "-i", "--network", "container:" + self.containers[which],
                "--read-only", "--cap-drop", "ALL", "--security-opt", "no-new-privileges:true",
                "--memory", "256m", "--cpus", "1", "--pids-limit", "64",
                "--tmpfs", "/tmp:rw,noexec,nosuid,size=16m",
                "--mount", "type=bind,src=" + str(ROOT) + ",dst=/repo,readonly",
                "--mount", "type=bind,src=" + str(self.passwords[which]) + ",dst=/run/secrets/p25-password,readonly"]
        for name, value in {
            "GITHUB_ACTIONS": "true", "GITHUB_REPOSITORY": "rafhael100/imagine-ai-agents",
            "AIA_P6_PG_LAB": "true", "AIA_P16_PG_LAB": "true", "DB_HOST": "127.0.0.1",
            "DB_DATABASE": "imagine_agents_ci", "DB_USERNAME": "postgres",
        }.items():
            args += ["-e", name + "=" + value]
        args += ["--entrypoint", "php", self.php_image, "/repo/" + relative.as_posix(), mode]
        result = run(args, "P25_PHP_" + mode.upper().replace("-", "_"),
                     stdin=json.dumps(payload).encode() if payload is not None else None,
                     check=False)
        if result.returncode:
            # The probe emits fixed diagnostic codes, never PostgreSQL errors or
            # secret-bearing stderr. Unknown failure output remains suppressed.
            markers = re.findall(rb"^P25_PROBE_DENIED_[A-Z0-9_]+$", result.stderr, re.MULTILINE)
            if len(markers) == 1:
                raise Failure(markers[0].decode("ascii"))
            raise Failure("P25_PHP_" + mode.upper().replace("-", "_") + "_FAILED")
        try:
            parsed = json.loads(result.stdout)
        except (ValueError, UnicodeError) as error:
            raise Failure("P25_PHP_OUTPUT_NOT_CLOSED_JSON") from error
        require(isinstance(parsed, dict), "P25_PHP_OUTPUT_OBJECT")
        return parsed

    def _probe(self, which: str, mode: str, payload: dict | None = None) -> dict:
        return self._run_php(which, ROOT / "runtime/central-auto/p25-recovery-probe.php", mode, payload)

    def prepare_and_dump(self, output: Path) -> None:
        require(self.seed is None and self.dump_hash is None, "P25_PREPARE_ONCE")
        self._start("src")
        for name in ("001_auto_ia_pg_lab.sql", "002_no_ai_inbox.sql", "003_signed_ingress_lab.sql"):
            self._sql("src", (ROOT / "tools/auto_ia/pg" / name).read_text())
        self._sql("src", (ROOT / "runtime/central-auto/receiver-grants.sql").read_text())
        self.seed = self._probe("src", "seed")
        require(self.seed.get("schema") == "P25_SEEDED_CANDIDATE_V1", "P25_SEED_RESULT")
        # Keep OWNER and ACL records. Unlike the n8n restore, SECURITY DEFINER
        # ownership, PUBLIC revokes and role grants are part of this schema.
        run(["docker", "exec", self.containers["src"], "pg_dump", "-U", "postgres",
             "-d", "imagine_agents_ci", "--format", "custom"], "P25_DUMP", output=output)
        data = output.read_bytes()
        require(data.startswith(b"PGDMP") and len(data) < 64 * 1024 * 1024, "P25_DUMP_FORMAT")
        self.dump_hash = hashlib.sha256(data).hexdigest()
        # This CURRENT fixture is intentionally captured AFTER the dump and
        # kept outside it. Restored historical rows cannot supply current trust.
        self.current = self._probe("src", "revoke-after-snapshot")
        require(self.current.get("schema") == "P25_CURRENT_AUTHORITY_FIXTURE_V1" and
                self.current.get("real_authority_verified") is False, "P25_CURRENT_AUTHORITY")
        retained = self.directory / "current-authority-fixture.json"
        with retained.open("x", encoding="utf-8") as target:
            os.fchmod(target.fileno(), 0o600)
            json.dump(self.current, target, sort_keys=True)
        print("P25_CANDIDATE_DUMP_WITH_OWNER_ACL_AND_POST_SNAPSHOT_REVOCATION_PASS", flush=True)

    def restore_and_verify(self, dump: Path) -> dict:
        require(self.seed is not None and self.current is not None and self.dump_hash is not None,
                "P25_CURRENT_AUTHORITY_REQUIRED")
        meta = dump.lstat()
        require(stat.S_ISREG(meta.st_mode) and meta.st_nlink == 1 and
                stat.S_IMODE(meta.st_mode) in (0o400, 0o600) and meta.st_size < 64 * 1024 * 1024,
                "P25_VERIFIED_DUMP_PRIVATE_REGULAR")
        contents = dump.read_bytes()
        require(contents.startswith(b"PGDMP") and hashlib.sha256(contents).hexdigest() == self.dump_hash,
                "P25_RECOVERED_COMPONENT_DIFFERS")
        self._start("dst")
        # No roles or passwords come from a restored cluster-global SQL file.
        # Recreate only the fixed NOLOGIN/non-superuser roles needed by the ACLs.
        self._sql("dst", (ROOT / "runtime/central-auto/p25-recovery-roles.sql").read_text())
        run(["docker", "exec", "-i", self.containers["dst"], "pg_restore", "-U", "postgres",
             "-d", "imagine_agents_ci", "--exit-on-error"], "P25_RESTORE_OWNER_ACL", stdin=contents)
        proof = self._probe("dst", "verify-restored", {"seed": self.seed, "current": self.current})
        require(proof.get("schema") == "P25_AGENT_RECOVERY_BEHAVIOR_V1" and
                proof.get("go_for_installation") is False and
                proof.get("roles_remained_nologin") is True and
                proof.get("stale_snapshot_lease_and_ack_denied_before_reconciliation") is True,
                "P25_RECOVERY_BEHAVIOR")
        proof.update({"separate_pg16_clusters": 2, "host_published_ports": 0,
                      "external_backup_verified": False, "different_host_restore_verified": False,
                      "real_crm_schema_restored": False})
        print("P25_CANDIDATE_AGENTS_RECOVERY_BEHAVIOR_PASS NO_GO", flush=True)
        return proof

    def cleanup(self) -> None:
        errors = []
        for which in ("dst", "src"):
            if which in self.containers:
                identity = self.containers[which]
                result = run(["docker", "container", "inspect", identity], "P25_INSPECT_CLEANUP", check=False)
                if result.returncode == 0:
                    data = json.loads(result.stdout)[0]
                    if (data["Config"].get("Labels") or {}).get("imagine.p25.owner") != self.token:
                        errors.append("CONTAINER_OWNER")
                        continue
                    removed = run(["docker", "rm", "-f", identity], "P25_REMOVE_CONTAINER", check=False)
                    if removed.returncode:
                        errors.append("CONTAINER_REMOVE")
            if which in self.volumes:
                volume = self.volumes[which]
                result = run(["docker", "volume", "inspect", volume], "P25_INSPECT_VOLUME", check=False)
                if result.returncode == 0:
                    data = json.loads(result.stdout)[0]
                    if (data.get("Labels") or {}).get("imagine.p25.owner") != self.token:
                        errors.append("VOLUME_OWNER")
                        continue
                    removed = run(["docker", "volume", "rm", volume], "P25_REMOVE_VOLUME", check=False)
                    if removed.returncode:
                        errors.append("VOLUME_REMOVE")
        require(not errors, "P25_OWNED_RESOURCE_CLEANUP_FAILED")
