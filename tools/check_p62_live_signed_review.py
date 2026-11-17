#!/usr/bin/env python3
"""AIA-P62 actual PG16 + read-only HTTP composition in the combined CI tree.

Reuses the Central P25 disposable cluster helper. No published ports, paid
services, real identities, CRM changes or n8n execution. The P56 page remains
localhost/CI-only; a browser client receives no database credentials.
"""
from __future__ import annotations

import base64
import json
import os
from pathlib import Path
import re
import secrets
import subprocess
import sys
import tempfile
import time

ROOT = Path(__file__).resolve().parents[1]
sys.path.insert(0, str(ROOT / "infra/n8n-private/homologation"))
from check_p24_full_n8n_restore import Failure, require, run
from p25_agents_recovery import AgentsRecovery
from check_p56_live_pg_readonly_http import Scan

PAGE = "/p56-live-pg-review-local.php"
TENANT = "SYNTHETIC_TENANT_A"
POLICY_TABLE = "aia_p57_lab.signed_observations"
STATES = {
    "LAB_AWAITING_REVIEWS_NO_ACTION", "LAB_PARTIAL_REVIEW_NO_ACTION",
    "LAB_TWO_SIGNED_OBSERVATIONS_NO_ACTION", "LAB_REVIEW_REJECTED_NO_ACTION",
}


class PageScan(Scan):
    def __init__(self):
        super().__init__()
        self.states: list[str] = []
        self.current: list[str] = []
        self.history: list[str] = []

    def handle_starttag(self, tag, attrs):
        super().handle_starttag(tag, attrs)
        values = dict(attrs)
        for name, target in (("data-review-state", self.states),
                             ("data-review-current", self.current),
                             ("data-review-history", self.history)):
            if name in values:
                target.append(values[name])


def decode_probe(result: subprocess.CompletedProcess) -> dict:
    if result.returncode:
        markers = re.findall(rb"^P62_PROBE_DENIED_[A-Z0-9_]+$", result.stderr, re.MULTILINE)
        raise Failure(markers[0].decode() if len(markers) == 1 else "P62_PHP_FAILED")
    value = json.loads(result.stdout)
    require(isinstance(value, dict), "P62_PROBE_JSON_OBJECT")
    return value


class ReviewLab(AgentsRecovery):
    def __init__(self, prefix: str, directory: Path):
        super().__init__(prefix, directory)
        self.extra: dict[str, str] = {}
        self.readers: dict[str, Path] = {}
        self.sequence = 0
        self.server: str | None = None
        self.fixture: dict | None = None

    def args(self, scope: str) -> list[str]:
        result = [
            "--network", "container:" + self.containers["src"],
            "--read-only", "--cap-drop", "ALL", "--security-opt", "no-new-privileges:true",
            "--memory", "256m", "--cpus", "1", "--pids-limit", "64",
            "--tmpfs", "/tmp:rw,noexec,nosuid,size=16m",
            "--mount", "type=bind,src=" + str(ROOT) + ",dst=/repo,readonly",
        ]
        if scope == "probe":
            result += ["--mount", "type=bind,src=" + str(self.passwords["src"]) +
                       ",dst=/run/secrets/p25-password,readonly"]
        for name in (("a", "b") if scope == "probe" else ("a",) if scope == "server" else ()):
            result += ["--mount", "type=bind,src=" + str(self.readers[name]) +
                       ",dst=/run/p62/reader-" + name + ",readonly"]
        env = {
            "GITHUB_ACTIONS": "true", "GITHUB_REPOSITORY": "rafhael100/imagine-ai-agents",
            "DB_HOST": "127.0.0.1", "DB_DATABASE": "imagine_agents_ci", "DB_USERNAME": "postgres",
            "AIA_P16_PG_LAB": "true", "AIA_P55_SCOPED_LAB": "true", "AIA_P56_BROWSER_LAB": "true",
            "AIA_P57_REVIEW_LAB": "true", "AIA_P59_AUTHOR_LAB": "true", "AIA_P61_HISTORY_LAB": "true",
            "AIA_P62_SIGNED_REVIEW_LAB": "true", "RUNNER_TEMP": "/run/p62",
            "AIA_P56_READER_PASSWORD_FILE": "/run/p62/reader-a",
        }
        for name, value in env.items():
            result += ["-e", name + "=" + value]
        return result

    def remove_owned(self, name: str):
        identity = self.extra.get(name, name)
        result = run(["docker", "container", "inspect", identity], "P62_CLEANUP_INSPECT", check=False)
        if result.returncode == 0:
            obj = json.loads(result.stdout)[0]
            require((obj["Config"].get("Labels") or {}).get("imagine.p25.owner") == self.token,
                    "P62_FOREIGN_CONTAINER")
            run(["docker", "rm", "-f", obj["Id"]], "P62_REMOVE_OWNED")
        self.extra.pop(name, None)

    def php(self, mode: str, payload: dict | None = None, *, client=False) -> dict:
        self.sequence += 1
        name = self.project + "-probe-" + str(self.sequence)
        require(run(["docker", "container", "inspect", name], "P62_NEW_PROBE", check=False).returncode != 0,
                "P62_PREEXISTING_PROBE")
        self.extra[name] = name
        args = ["docker", "run", "--rm", "-i", "--name", name,
                "--label", "imagine.p25.owner=" + self.token] + self.args("client" if client else "probe")
        args += ["--entrypoint", "php", self.php_image, "/repo/tools/p62-signed-review-probe.php", mode]
        result = run(args, "P62_PROBE", stdin=json.dumps(payload or {}).encode(), timeout=40, check=False)
        self.remove_owned(name)
        return decode_probe(result)

    def prepare(self):
        self._start("src")
        run(["docker", "exec", "-i", self.containers["src"], "sh", "-c",
             "cat > /var/lib/postgresql/data/pg_hba.conf"], "P62_REQUIRE_TCP_PASSWORD",
            stdin=b"local all postgres trust\nlocal all all reject\n"
                  b"host all all 127.0.0.1/32 scram-sha-256\n"
                  b"host all all ::1/128 scram-sha-256\n")
        require(self._sql("src", "SELECT pg_reload_conf()") == "t", "P62_RELOAD_AUTH")
        passwords = {}
        for name in ("a", "b"):
            password = secrets.token_hex(18)
            path = self.directory / ("reader-" + name)
            with path.open("x", encoding="ascii") as out:
                os.fchmod(out.fileno(), 0o444)
                out.write(password)
            self.readers[name] = path
            passwords["reader_" + name] = password
        self.fixture = self.php("seed", passwords)
        require(self.fixture.get("schema") == "P62_SEEDED_V1", "P62_SEED")
        require(self.php("unauthenticated-db", client=True).get("required") is True, "P62_DB_AUTH_REQUIRED")

    def start_server(self):
        name = self.project + "-http"
        require(run(["docker", "container", "inspect", name], "P62_NEW_HTTP", check=False).returncode != 0,
                "P62_PREEXISTING_HTTP")
        self.extra[name] = name
        result = run(["docker", "run", "-d", "--name", name,
                      "--label", "imagine.p25.owner=" + self.token] + self.args("server") +
                     ["--entrypoint", "php", self.php_image, "-S", "127.0.0.1:8786",
                      "-t", "/repo/examples"], "P62_START_HTTP")
        identity = result.stdout.decode().strip()
        require(re.fullmatch(r"[a-f0-9]{64}", identity) is not None, "P62_HTTP_ID")
        self.extra[name] = identity
        self.server = identity
        info = json.loads(run(["docker", "inspect", identity], "P62_HTTP_ISOLATION").stdout)[0]
        require(info["HostConfig"]["NetworkMode"] == "container:" + self.containers["src"]
                and not info["HostConfig"].get("PortBindings")
                and info["HostConfig"]["ReadonlyRootfs"] is True
                and not info["HostConfig"]["Privileged"]
                and info["Config"]["User"] in ("www-data", "33", "33:33"), "P62_HTTP_ISOLATED_NONROOT")
        require(all(item["Destination"] != "/run/secrets/p25-password" for item in info["Mounts"]),
                "P62_HTTP_NO_ADMIN_SECRET")
        # Reuse the running PHP container for short readiness checks.
        for _ in range(30):
            result = run(["docker", "exec", "-i", identity, "php",
                          "/repo/tools/p62-signed-review-probe.php", "http"],
                         "P62_HTTP_READY", stdin=b"{}", timeout=6, check=False)
            if result.returncode == 0:
                require(decode_probe(result)["status"] == 200, "P62_HTTP_INITIAL_VIEW")
                return
            time.sleep(.1)
        raise Failure("P62_HTTP_NOT_READY")

    def http(self, query="", method="GET", host="127.0.0.1:8786") -> tuple[int, str, dict]:
        value = self.php("http", {"path": PAGE + query, "method": method, "host": host}, client=True)
        return value["status"], base64.b64decode(value["body_b64"], validate=True).decode(), value["headers"]

    def fresh_votes(self):
        require(self.fixture is not None, "P62_FIXTURE_REQUIRED")
        self._sql("src", "UPDATE aia_p57_lab.reviewers SET active=true,revision=1; "
                  "UPDATE aia_p59_lab.draft_authors SET author_ref='SYNTHETIC_P62_AUTHOR_A' "
                  "WHERE tenant_ref='SYNTHETIC_TENANT_A'")
        self.php("votes-time-fixture", {"when": "fresh", "signers": self.fixture["signers"]})

    def cleanup(self):
        errors = []
        for name in tuple(self.extra):
            try:
                self.remove_owned(name)
            except Exception:
                errors.append("CONTAINER")
        try:
            super().cleanup()
        except Exception:
            errors.append("CLUSTER")
        require(not errors, "P62_CLEANUP_FAILED")


def page(lab: ReviewLab, current: str = "available", query="") -> tuple[str, PageScan]:
    status, body, headers = lab.http(query)
    require(status == 200 and "NO-GO operacional" in body and "#F9941D" in body, "P62_PAGE")
    require("default-src 'none'" in headers.get("content-security-policy", "")
            and headers.get("x-frame-options") == "DENY"
            and headers.get("x-content-type-options") == "nosniff"
            and headers.get("cache-control", "").startswith("no-store"), "P62_HEADERS")
    parsed = PageScan()
    parsed.feed(body)
    require(parsed.actions == [] and parsed.remotes == [], "P62_NO_ACTION_OR_REMOTE_ASSET")
    require(parsed.current == [current] and parsed.history == ["verified"], "P62_CURRENT_AND_HISTORY")
    for hidden in ("SYNTHETIC_P62_PRIVATE_B", "SYNTHETIC_P62_AUTHOR", "SYNTHETIC_P62_PRODUCT",
                   "SYNTHETIC_P62_SECURITY", "canonical_vote", "signature_b64", "public_key_b64"):
        require(hidden not in body, "P62_PRIVATE_FIELDS_NOT_RENDERED")
    require(re.search(r"Consulta: [0-9]{2}/[0-9]{2}/[0-9]{4} [0-9]{2}:[0-9]{2}:[0-9]{2} "
                      r"\(America/Sao_Paulo, UTC[+-][0-9]{2}:[0-9]{2}\)", body) is not None,
            "P62_OBSERVATION_TIME_VISIBLE")
    if current == "unavailable":
        require(set(parsed.states) == {"UNAVAILABLE"} and "aguardando" not in body.lower(),
                "P62_REVOKED_NOT_AWAITING")
    return body, parsed


def denied_page(lab: ReviewLab):
    status, body, _ = lab.http()
    require(status == 503 and body == "P56_READONLY_LAB_DENIED", "P62_NO_PARTIAL_PAGE")
    require("SYNTHETIC_" not in body, "P62_UNIFORM_FAILURE")


def policy_wait(lab: ReviewLab) -> dict:
    """A real DDL transaction finishes before the reader's first snapshot."""
    require(lab.server is not None, "P62_HTTP_REQUIRED")
    blocker = subprocess.Popen(
        ["docker", "exec", "-i", lab.containers["src"], "psql", "-X", "-qAt",
         "-v", "ON_ERROR_STOP=1", "-U", "postgres", "-d", "imagine_agents_ci"],
        stdin=subprocess.PIPE, stdout=subprocess.PIPE, stderr=subprocess.PIPE,
    )
    reader = None
    try:
        blocker.stdin.write((
            "SET application_name='p62_policy_blocker'; SET statement_timeout='10s'; "
            "SET idle_in_transaction_session_timeout='10s'; BEGIN; "
            "ALTER POLICY p62_read_a ON aia_p57_lab.signed_observations USING(false);\n"
        ).encode())
        blocker.stdin.flush()
        deadline = time.monotonic() + 5
        while time.monotonic() < deadline:
            ready = lab._sql("src", "SELECT count(*) FROM pg_stat_activity "
                             "WHERE application_name='p62_policy_blocker' "
                             "AND state='idle in transaction'")
            if ready == "1":
                break
            require(blocker.poll() is None, "P62_POLICY_BLOCKER_EXITED")
            time.sleep(.02)
        else:
            raise Failure("P62_POLICY_BLOCKER_NOT_READY")
        reader = subprocess.Popen(
            ["docker", "exec", "-i", lab.server, "php",
             "/repo/tools/p62-signed-review-probe.php", "view-lock-observed"],
            stdin=subprocess.PIPE, stdout=subprocess.PIPE, stderr=subprocess.PIPE,
        )
        reader.stdin.write(b'{"application":"p62_policy_reader"}')
        reader.stdin.close()
        reader.stdin = None
        deadline = time.monotonic() + .65
        observed = False
        while time.monotonic() < deadline:
            if lab._sql("src", "SELECT count(*) FROM pg_stat_activity r "
                        "JOIN pg_stat_activity b ON b.pid=ANY(pg_blocking_pids(r.pid)) "
                        "WHERE r.application_name='p62_policy_reader' AND r.wait_event_type='Lock' "
                        "AND b.application_name='p62_policy_blocker'") == "1":
                observed = True
                break
            require(reader.poll() is None, "P62_POLICY_READER_EXITED_EARLY")
        require(observed, "P62_POLICY_WAIT_NOT_OBSERVED")
        output, error = blocker.communicate(b"COMMIT;\n", timeout=4)
        require(blocker.returncode == 0, "P62_POLICY_COMMIT")
        stdout, stderr = reader.communicate(timeout=5)
        observed = decode_probe(subprocess.CompletedProcess([], reader.returncode, stdout, stderr))
        require(observed.get("schema") == "P62_LOCK_OBSERVED_V1"
                and observed.get("lock_completed") is True, "P62_LOCK_MUST_COMPLETE_NOT_TIMEOUT")
        value = observed["result"]
        require(value["decision"] == "DENY" and value["queue"] is None, "P62_POLICY_POST_WAIT_DENIED")
        denied_page(lab)
        return {"ddl_wait_observed": True, "post_wait_policy_validated": True,
                "hidden_observations_not_reported_as_zero": True}
    finally:
        for process in (reader, blocker):
            if process is not None and process.poll() is None:
                try:
                    process.communicate(b"ROLLBACK;\n" if process is blocker else None, timeout=2)
                except (subprocess.TimeoutExpired, BrokenPipeError):
                    process.kill()
                    process.communicate(timeout=2)
        lab._sql("src", "ALTER POLICY p62_read_a ON aia_p57_lab.signed_observations "
                 "USING(tenant_ref='SYNTHETIC_TENANT_A')")


def exercise(lab: ReviewLab) -> dict:
    domain = lab.php("checks")
    lab.fresh_votes()
    lab.start_server()
    before = lab.php("stats")
    _, parsed = page(lab)
    require(parsed.articles == 4 and set(parsed.states) == STATES, "P62_FOUR_VISIBLE_STATES")
    for kind, count in (("human", 2), ("task", 1), ("notice", 1)):
        _, filtered = page(lab, query="?kind=" + kind)
        require(filtered.articles == count, "P62_EXISTING_FILTERS")
    for query, method, host, expected in (
        ("", "POST", "127.0.0.1:8786", 405),
        ("", "PUT", "127.0.0.1:8786", 405),
        ("", "DELETE", "127.0.0.1:8786", 405),
        ("?kind=unknown", "GET", "127.0.0.1:8786", 400),
        ("?tenant=SYNTHETIC_TENANT_B", "GET", "127.0.0.1:8786", 400),
        ("?kind=all&tenant=SYNTHETIC_TENANT_B", "GET", "127.0.0.1:8786", 400),
        ("", "GET", "evil.example", 403),
    ):
        status, body, _ = lab.http(query, method, host)
        require(status == expected and body == "P56_READONLY_LAB_DENIED", "P62_HTTP_BOUNDARY")
    require(lab.php("stats") == before, "P62_HTTP_READS_PRESERVED_ROWS")
    snapshots = []
    for change in ("revoke", "author", "signature"):
        lab.fresh_votes()
        proof = lab.php("concurrent-snapshot", {"change": change})
        require(proof.get("whole_old_snapshot_preserved") is True and
                proof.get("next_snapshot_observes_change") is True, "P62_CONCURRENT_PROOF")
        snapshots.append(proof)
        before = lab.php("stats")
        if change == "revoke":
            page(lab, "unavailable")
            for kind in ("human", "task", "notice"):
                page(lab, "unavailable", "?kind=" + kind)
        else:
            denied_page(lab)
        require(lab.php("stats") == before, "P62_NEGATIVE_VIEW_DID_NOT_MUTATE")
    lab.fresh_votes()
    lab.php("votes-time-fixture", {"when": "expired", "signers": lab.fixture["signers"]})
    before = lab.php("stats")
    expired = lab.php("view")
    require(expired["decision"] == "LAB_SIGNED_REVIEW_SNAPSHOT_NO_GO" and
            expired["current_review_available"] is False and expired["signed_review"] is None and
            expired["historical"]["checked_signed_observations"] == 4, "P62_EXPIRED_HISTORY_SEPARATE")
    page(lab, "unavailable")
    require(lab.php("stats") == before, "P62_EXPIRED_VIEW_DID_NOT_MUTATE")
    lab.fresh_votes()
    # Each fixture mutation is followed by a generic denial and a valid
    # control after explicit restoration by the test owner connection.
    negatives = (
        ("DELETE FROM aia_p59_lab.draft_authors WHERE tenant_ref='SYNTHETIC_TENANT_A' "
         "AND proposal_ref=(SELECT proposal_ref FROM aia_p51_lab.review_drafts "
         "WHERE tenant_ref='SYNTHETIC_TENANT_A' AND request_ref='SYNTHETIC_P62_WAIT')",
         "INSERT INTO aia_p59_lab.draft_authors "
         "(tenant_ref,proposal_ref,author_ref,draft_sha256,source) "
         "SELECT tenant_ref,proposal_ref,'SYNTHETIC_P62_AUTHOR_A',draft_sha256,"
         "'SYNTHETIC_CI_AUTHOR_FIXTURE' FROM aia_p51_lab.review_drafts "
         "WHERE tenant_ref='SYNTHETIC_TENANT_A' AND request_ref='SYNTHETIC_P62_WAIT'"),
        ("REVOKE SELECT ON aia_p59_lab.draft_authors FROM aia_p55_ci_read_a",
         "GRANT SELECT ON aia_p59_lab.draft_authors TO aia_p55_ci_read_a"),
        ("DROP POLICY p62_read_a ON aia_p57_lab.signed_observations",
         "CREATE POLICY p62_read_a ON aia_p57_lab.signed_observations FOR SELECT "
         "TO aia_p55_ci_read_a USING(tenant_ref='SYNTHETIC_TENANT_A')"),
        ("ALTER POLICY p62_read_a ON aia_p57_lab.signed_observations USING(false)",
         "ALTER POLICY p62_read_a ON aia_p57_lab.signed_observations "
         "USING(tenant_ref='SYNTHETIC_TENANT_A')"),
        ("ALTER POLICY p62_read_a ON aia_p57_lab.signed_observations "
         "USING(tenant_ref='SYNTHETIC_TENANT_ A')",
         "ALTER POLICY p62_read_a ON aia_p57_lab.signed_observations "
         "USING(tenant_ref='SYNTHETIC_TENANT_A')"),
        ("ALTER POLICY p62_read_a ON aia_p57_lab.signed_observations "
         "USING(tenant_ref='SYNTHETIC_TENANT_(A)')",
         "ALTER POLICY p62_read_a ON aia_p57_lab.signed_observations "
         "USING(tenant_ref='SYNTHETIC_TENANT_A')"),
        ("CREATE POLICY p62_extra ON aia_p57_lab.signed_observations AS RESTRICTIVE "
         "FOR SELECT TO aia_p55_ci_read_a USING(false)",
         "DROP POLICY p62_extra ON aia_p57_lab.signed_observations"),
    )
    for mutate, restore in negatives:
        lab.fresh_votes()
        lab._sql("src", mutate)
        before = lab.php("stats")
        denied_page(lab)
        require(lab.php("view")["decision"] == "DENY", "P62_ACCESS_PROFILE_DENIED")
        require(lab.php("stats") == before, "P62_ACCESS_NEGATIVE_DID_NOT_MUTATE")
        lab._sql("src", restore)
        page(lab)
    lab.fresh_votes()
    policy_race = policy_wait(lab)
    page(lab)
    return {
        "schema": "AIA_P62_LIVE_SIGNED_REVIEW_VERIFIED_V1", "domain": domain,
        "concurrent_snapshots": snapshots, "policy_wait": policy_race,
        "http_four_review_states": True, "existing_filters_preserved": True,
        "revoked_and_expired_history_separate": True, "corrupt_views_fail_closed": True,
        "missing_or_restrictive_policy_denied": True, "read_only_rows_preserved": True,
        "host_published_ports": 0, "network_mode": "none", "real_human_identity": False,
        "human_approval_granted": False, "n8n_executions": 0, "paid_ai_calls": 0,
        "go_for_installation": False,
    }


def main() -> dict:
    require(run(["docker", "context", "show"], "P62_DOCKER_CONTEXT").stdout.strip() == b"default",
            "P62_DOCKER_CONTEXT_REQUIRED")
    context = json.loads(run(["docker", "context", "inspect", "default"], "P62_DOCKER_ENDPOINT").stdout)
    require(context[0]["Endpoints"]["docker"]["Host"] == "unix:///var/run/docker.sock",
            "P62_LOCAL_DOCKER_SOCKET_REQUIRED")
    with tempfile.TemporaryDirectory(prefix="aia-p62-", dir=os.environ.get("RUNNER_TEMP")) as tmp:
        lab = ReviewLab("aia-p62-" + secrets.token_hex(7), Path(tmp) / "private")
        try:
            lab.prepare()
            return exercise(lab)
        finally:
            lab.cleanup()


if __name__ == "__main__":
    try:
        print(json.dumps(main(), sort_keys=True))
        print("AIA_P62_LIVE_SIGNED_REVIEW_PASS NO_GO")
    except (Failure, OSError, ValueError, subprocess.SubprocessError) as error:
        code = str(error) if isinstance(error, Failure) else "P62_RUNTIME_FAILURE"
        print(code, file=sys.stderr)
        raise SystemExit(1)
