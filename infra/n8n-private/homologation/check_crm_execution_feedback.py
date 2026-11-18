#!/usr/bin/env python3
"""Actual synthetic CRM -> mTLS capture -> native n8n -> signed status -> CRM.

Both applications use their own restricted database identities. Only this
disposable CI fixture administrator can inspect the two databases. The status
endpoint is read-only; the CRM stores a separate technical history/projection.
No lead field, worker, paid AI call or operational integration is enabled.
"""
from __future__ import annotations

import base64
import hashlib
import hmac
import json
import os
import re
import secrets
import subprocess
import sys
import tempfile
import time
from concurrent.futures import ThreadPoolExecutor
from pathlib import Path

from check_p24_full_n8n_restore import (
    Failure, NativeDenied, PRIVATE, ROOT, Stack, parse_json, parse_output, require, run,
)
from check_p27_private_receipt_status import (
    COMPANY_A, COMPANY_B, KEY_A, QUERY_FIELDS, closed_json, fingerprint, make_query,
    verify_receipt, wire,
)
from check_p29_ingress_boundaries import literal
from check_crm_lead_capture import CaptureLab, INDEX_SHA256, expected_row
from check_crm_lead_execution import (
    CASE_IDS, TABLES, lineage, n8n_snapshot, readonly_audits, rpc, source_of,
)
from p25_agents_recovery import AgentsRecovery
from crm_feedback_backup import capture_crm_backup, load_crm_backup

sys.path.insert(0, str(ROOT / "runtime/central-auto/lab-executor"))
from native_contract import (  # noqa: E402
    ContractDenied, canonical, expected, materialize, template, verify_export, verify_result,
)

EXECUTION_FIELDS = (
    "schema", "issuer", "audience", "company_id", "tenant_ref", "event_ref", "payload_hash",
    "lead_id", "task_payload_hash", "execution_state", "queue_state", "result_code",
    "observation_hash", "attempt", "request_ref", "nonce_ref", "issued_at", "expires_at", "key_id",
)
OBSERVATION_FIELDS = (
    "company_id", "tenant_ref", "event_ref", "payload_hash", "lead_id", "task_payload_hash",
    "execution_state", "queue_state", "result_code", "attempt",
)
EXECUTION_GRANTS = "auto_ia_lab.execution_read_grants"
HISTORY = "crm_auto_service.execution_observations"
PROJECTIONS = "crm_auto_service.execution_projections"
CRM_DATABASE = "imagine_crm_private_homologation"
DENIAL = b'{"error":"EXECUTION_STATUS_DENIED"}'
HTTP_PROBE = ROOT / "runtime/central-auto/feedback-http-probe.php"
CRM_PROBE = ROOT / "runtime/central-auto/feedback-crm-probe.php"
MONITOR_PROBE = ROOT / "runtime/central-auto/feedback-crm-monitor-probe.php"
MONITOR_OBSERVATION_FIELDS = ("sequence", "execution_state", "queue_state", "result_code", "attempt", "observed_at")
ACTOR = "11111111-1111-4111-8111-111111111111"
FUNNEL_A = "22222222-2222-4222-8222-222222222222"
FUNNEL_B = "33333333-3333-4333-8333-333333333333"
LEAD_A = "44444444-4444-4444-8444-444444444444"
LEAD_B = "55555555-5555-4555-8555-555555555555"


def status_query(body: dict, **changes) -> dict:
    now = int(time.time())
    suffix = secrets.token_hex(12).upper()
    query = dict(zip(QUERY_FIELDS, (
        "AUTO_IA_EXECUTION_QUERY_V1", "imagine-crm", "imagine-ai-agents", "imagine-crm",
        "event.execution.read", body["company_id"], body["envelope"]["tenant_ref"],
        body["envelope"]["event_ref"], body["envelope"]["payload_hash"],
        "SYNTHETIC_QUERY_" + suffix, "SYNTHETIC_NONCE_" + suffix, now, now + 30, "SYNTHETIC_KEY_A",
    )))
    query.update(changes)
    text = "IMAGINE_AGENTS_EXECUTION_QUERY_V1\n" + "\n".join(str(query[name]) for name in QUERY_FIELDS)
    query["signature"] = hmac.new(KEY_A, text.encode(), hashlib.sha256).hexdigest()
    return query


def verify_status(reply: dict, query: dict, key: bytes) -> dict:
    require(reply["transport_ok"] is True and reply["status"] == 200 and
            reply["cache_control"] == "no-store" and
            reply["content_type"].split(";")[0] == "application/json", "FEEDBACK_STATUS_HTTP")
    value = closed_json(base64.b64decode(reply["body_b64"], validate=True))
    require(tuple(value) == EXECUTION_FIELDS + ("signature",), "FEEDBACK_STATUS_EXACT_ORDERED_FIELDS")
    for field in EXECUTION_FIELDS + ("signature",):
        require(type(value[field]) is (int if field in ("attempt", "issued_at", "expires_at") else str),
                "FEEDBACK_STATUS_FIELD_TYPE")
    for field, expected_value in {
        "schema": "AUTO_IA_EXECUTION_STATUS_V1", "issuer": "imagine-ai-agents",
        "audience": "imagine-crm", "key_id": "SYNTHETIC_EXECUTION_STATUS_KEY",
    }.items():
        require(value[field] == expected_value, "FEEDBACK_STATUS_DOMAIN")
    for field in ("company_id", "tenant_ref", "event_ref", "payload_hash", "request_ref", "nonce_ref"):
        require(value[field] == query[field], "FEEDBACK_STATUS_BINDING")
    signed = "IMAGINE_AGENTS_EXECUTION_STATUS_V1\n" + "\n".join(str(value[k]) for k in EXECUTION_FIELDS)
    require(hmac.compare_digest(value["signature"], hmac.new(key, signed.encode(), hashlib.sha256).hexdigest()),
            "FEEDBACK_STATUS_HMAC")
    facts = "IMAGINE_AGENTS_EXECUTION_OBSERVATION_V1\n" + "\n".join(str(value[k]) for k in OBSERVATION_FIELDS)
    require(value["observation_hash"] == hashlib.sha256(facts.encode()).hexdigest(), "FEEDBACK_OBSERVATION_HASH")
    now = int(time.time())
    require(now - 10 <= value["issued_at"] <= now + 5 and
            now < value["expires_at"] <= min(value["issued_at"] + 30, query["expires_at"]),
            "FEEDBACK_STATUS_FRESHNESS")
    return value


def table_snapshot(query, tables) -> dict:
    require(all(re.fullmatch(r"[a-z_][a-z0-9_]*\.[a-z_][a-z0-9_]*", table) for table in tables),
            "FEEDBACK_TABLE_INVENTORY")
    raw = query("SELECT jsonb_build_object(" + ",".join(
        literal(table) + ",(SELECT COALESCE(jsonb_agg(to_jsonb(t) ORDER BY to_jsonb(t)::text),'[]'::jsonb) FROM "
        + table + " t)" for table in tables) + ")")
    return {name: {"rows": len(values), "sha256": hashlib.sha256(canonical(values)).hexdigest()}
            for name, values in json.loads(raw).items()}


def agents_snapshot(lab: CaptureLab) -> dict:
    return table_snapshot(lambda sql: lab._sql("src", sql), tuple(TABLES) +
                          (EXECUTION_GRANTS, "auto_ia_lab.requests"))


class FeedbackLab(CaptureLab):
    """Keep all P27 isolation; add one server SAN and the separate read endpoint key."""

    def _certificate(self, name: str, ca: str, server: bool = False) -> None:
        super()._certificate(name, ca, server)
        if server:
            extension = self.directory / (name + ".ext")
            extension.write_text(extension.read_text().replace(
                "DNS:agents-ci.internal,IP:127.0.0.1", "DNS:agents-ci.internal,DNS:agents-s2s,IP:127.0.0.1"))
            cert = self.certificates[name][0]
            cert.chmod(0o600)
            run(["openssl", "x509", "-req", "-in", str(self.directory / (name + ".csr")),
                 "-CA", str(self.directory / (ca + ".crt")), "-CAkey", str(self.directory / (ca + ".key")),
                 "-set_serial", str(secrets.randbits(120) + 1), "-days", "2", "-sha256",
                 "-extfile", str(extension), "-out", str(cert)], "FEEDBACK_CRM_SERVER_SAN")
            cert.chmod(0o444)

    def service(self, kind: str, arguments: list[str]) -> None:
        if kind == "fpm":
            arguments = ["-e", "AGENTS_PRIVATE_EXECUTION_KEY_FILE=/certs/agents-execution.key",
                         "-e", "AGENTS_PRIVATE_EXECUTION_KEY_ID=SYNTHETIC_EXECUTION_STATUS_KEY",
                         "-e", "AGENTS_PRIVATE_EXECUTION_DB_PASSWORD_FILE=/run/secrets/execution-password"] \
                + self.mount(self.secrets["execution-key"], "/certs/agents-execution.key") + arguments
        super().service(kind, arguments)

    def prepare_feedback(self) -> None:
        self.prepare()
        self._sql("src", (ROOT / "runtime/central-auto/lead-ingest-capture.sql").read_text())
        require(rpc(self, "init").get("schema") == "LEAD_EXECUTION_LAB_READY_V1", "FEEDBACK_DOMAIN_INIT")
        self._sql("src", (ROOT / "runtime/central-auto/execution-status.sql").read_text())
        self.secrets["ack-key"] = self.private_file("crm-signing-key", KEY_A)
        for name in ("execution-password", "execution-key"):
            self.secrets[name] = self.private_file(name, secrets.token_hex(32).encode())
        self._sql("src", "CREATE ROLE aia_private_execution_login LOGIN NOINHERIT NOSUPERUSER NOBYPASSRLS "
                  "NOCREATEDB NOCREATEROLE NOREPLICATION PASSWORD " + literal(self.secrets["execution-password"].read_text())
                  + "; GRANT aia_private_execution TO aia_private_execution_login WITH INHERIT FALSE, SET TRUE;")
        for company, tenant, client in ((COMPANY_A, "SYNTHETIC_TENANT_A", "client-a"),
                                         (COMPANY_B, "SYNTHETIC_TENANT_B", "client-b")):
            self._sql("src", "INSERT INTO " + EXECUTION_GRANTS + " VALUES(" + literal(company) + "," +
                      literal(fingerprint(self.certificates[client][0])) + "," + literal(tenant) +
                      ",'event.execution.read',true,1,floor(extract(epoch FROM clock_timestamp()))::bigint+1800)")
        self._sql("src", "INSERT INTO auto_ia_lab.receipt_read_grants VALUES(" + literal(COMPANY_A) + "," +
                  literal(fingerprint(self.certificates["client-a"][0])) +
                  ",'SYNTHETIC_TENANT_A','event.receipt.read',true,1,"
                  "floor(extract(epoch FROM clock_timestamp()))::bigint+1800)")
        self.profile = "immutable-v1"
        self.start_http()

    def execution_http(self, body: dict | bytes, *, client: str | None = "client-a",
                       path: str = "/private/events/execution", method: str = "POST",
                       headers: list[str] | None = None, content_type: str = "application/json") -> dict:
        payload = {"path": path, "method": method, "headers": headers or [], "content_type": content_type,
                   "body_b64": base64.b64encode(wire(body) if isinstance(body, dict) else body).decode(),
                   "client_certificate": client is not None}
        args = ["docker", "run", "--rm", "-i"] + self.base_args() + self.mount(HTTP_PROBE, "/probe.php")
        args += self.mount(self.directory / "ca.crt", "/certs/ca.crt")
        if client is not None:
            args += self.mount(self.certificates[client][0], "/certs/client.crt")
            args += self.mount(self.certificates[client][1], "/certs/client.key")
        args += ["-e", "GITHUB_ACTIONS=true", "-e", "GITHUB_REPOSITORY=rafhael100/imagine-ai-agents",
                 "-e", "CRM_FEEDBACK_CI=synthetic-private-v1", "--entrypoint", "php", self.php_image, "/probe.php"]
        result = run(args, "FEEDBACK_EXECUTION_HTTP", stdin=wire(payload), timeout=15)
        return closed_json(result.stdout)


class CrmLab(AgentsRecovery):
    """Own PG16 database and guarded PHP runtime; inherit ownership-aware cleanup."""

    def __init__(self, prefix: str, directory: Path, agents: FeedbackLab, source: Path, source_sha: str):
        super().__init__(prefix, directory)
        self.project = prefix + "-crm"
        self.agents, self.source, self.source_sha = agents, source, source_sha
        self.php_image = os.environ.get("CRM_FEEDBACK_PHP_IMAGE", "")
        require(re.fullmatch(r"sha256:[a-f0-9]{64}", self.php_image) is not None, "FEEDBACK_CRM_IMAGE_ID")
        self.secrets: dict[str, Path] = {}
        self.admins: dict[str, str] = {}
        self.backup, self.backup_snapshot = load_crm_backup(source)
        self.base_tables = re.findall(r"^CREATE TABLE (crm_[a-z0-9_]+)\s*\(",
                                     (source / "infra/homologation/schema.sql").read_text(), re.MULTILINE)

    def _start(self, which: str) -> None:
        require(which in ("src", "dst") and which not in self.containers, "FEEDBACK_CRM_CLUSTER_SCOPE")
        if not self.directory_created:
            self.directory.mkdir(mode=0o700)
            self.directory_created = True
        name = self.project + "-" + which
        volume = name + "-data"
        for kind, identity in (("container", name), ("volume", volume)):
            require(run(["docker", kind, "inspect", identity], "FEEDBACK_CRM_RESOURCE", check=False).returncode != 0,
                    "FEEDBACK_CRM_PREEXISTING_RESOURCE")
        run(["docker", "volume", "create", "--label", "imagine.p25.owner=" + self.token, volume],
            "FEEDBACK_CRM_CREATE_VOLUME")
        self.volumes[which] = volume
        password = self.directory / (which + "-owner-password")
        password.write_text(secrets.token_hex(32)); password.chmod(0o444)
        self.passwords[which] = password
        admin = "crm_homologation_owner" if which == "src" else "crm_homologation_restore"
        self.admins[which] = admin
        network = "container:" + self.agents.containers["src"] if which == "src" else "none"
        self.containers[which] = name
        result = run(["docker", "run", "-d", "--name", name, "--label", "imagine.p25.owner=" + self.token,
                      "--network", network, "--security-opt", "no-new-privileges:true", "--memory", "512m",
                      "--cpus", "1", "--pids-limit", "128", "-e", "POSTGRES_DB=" + CRM_DATABASE,
                      "-e", "POSTGRES_USER=" + admin, "-e", "PGPORT=5433",
                      "-e", "POSTGRES_PASSWORD_FILE=/run/secrets/crm-owner-password"]
                     + self.agents.mount(password, "/run/secrets/crm-owner-password")
                     + ["--mount", "type=volume,src=" + volume + ",dst=/var/lib/postgresql/data",
                        self.pg_image, "postgres", "-c", "port=5433", "-c", "listen_addresses=127.0.0.1"],
                     "FEEDBACK_CRM_START_POSTGRES")
        identity = result.stdout.decode().strip()
        require(re.fullmatch(r"[a-f0-9]{64}", identity) is not None, "FEEDBACK_CRM_CONTAINER_ID")
        self.containers[which] = identity
        for _ in range(40):
            ready = run(["docker", "exec", identity, "pg_isready", "-h", "127.0.0.1", "-p", "5433",
                         "-U", admin, "-d", CRM_DATABASE], "FEEDBACK_CRM_READINESS", check=False, timeout=5)
            if ready.returncode == 0:
                break
            time.sleep(0.5)
        else:
            raise Failure("FEEDBACK_CRM_DATABASE_NOT_READY")
        data = json.loads(run(["docker", "inspect", identity], "FEEDBACK_CRM_ISOLATION").stdout)[0]
        require(data["HostConfig"]["NetworkMode"] == network and not data["HostConfig"].get("PortBindings")
                and not data["HostConfig"]["Privileged"]
                and data["Config"]["Labels"].get("imagine.p25.owner") == self.token,
                "FEEDBACK_CRM_DATABASE_ISOLATION")
        require(self._sql(which, "SELECT current_database()") == CRM_DATABASE and
                re.fullmatch(r"16\d{4}", self._sql(which, "SHOW server_version_num")) is not None,
                "FEEDBACK_CRM_REAL_DATABASE_GUARD")
        # Do not let another application in the shared loopback bypass authentication.
        run(["docker", "exec", "-i", identity, "sh", "-c", "cat > /var/lib/postgresql/data/pg_hba.conf"],
            "FEEDBACK_CRM_PASSWORD_AUTH", stdin=("local all " + admin + " trust\nlocal all all reject\n"
            "host all all 127.0.0.1/32 scram-sha-256\nhost all all ::1/128 scram-sha-256\n").encode())
        require(self._sql(which, "SELECT pg_reload_conf()") == "t", "FEEDBACK_CRM_HBA_RELOAD")

    def _sql(self, which: str, query: str) -> str:
        require(which in self.containers, "FEEDBACK_CRM_CLUSTER_REQUIRED")
        return run(["docker", "exec", "-i", self.containers[which], "psql", "-X", "-qAt",
                    "-v", "ON_ERROR_STOP=1", "-p", "5433", "-U", self.admins[which], "-d", CRM_DATABASE],
                   "FEEDBACK_CRM_SQL", stdin=query.encode()).stdout.decode().strip()

    def sql(self, query: str) -> str:
        return self._sql("src", query)

    def prepare(self) -> None:
        self._start("src")
        self.sql((self.source / "infra/homologation/schema.sql").read_text())
        # Install the real reviewed runtime role script, including its existing table ACLs.
        args = ["docker", "exec", "-i", "-e", "POSTGRES_DB=" + CRM_DATABASE,
                "-e", "POSTGRES_USER=crm_homologation_owner", "-e", "PGPORT=5433"]
        for name in ("APP", "IAM", "ENROLLMENT", "OPERATOR"):
            password = secrets.token_hex(32)
            if name == "APP":
                # Retain only the existing app role's credential for the separate,
                # read-only CLI proof. No IAM or S2S credential enters that process.
                path = self.directory / "monitor-app-password"
                path.write_text(password); path.chmod(0o444)
                self.secrets["monitor-app-password"] = path
            args += ["-e", "CRM_PRIVATE_" + name + "_PASSWORD=" + password]
        run(args + [self.containers["src"], "sh", "-s"], "FEEDBACK_CRM_RUNTIME_ROLES",
            stdin=(self.source / "infra/homologation/20-runtime-roles.sh").read_bytes())
        for name in ("install-candidate.sql", "outbound-candidate.sql", "execution-feedback-candidate.sql",
                     "execution-monitor-candidate.sql"):
            self.sql((self.source / "infra/homologation/s2s" / name).read_text())
        for name, role in (("sender.password", "crm_auto_sender"),
                           ("execution-reader.password", "crm_auto_execution_reconciler")):
            path = self.directory / name
            path.write_text(secrets.token_hex(32)); path.chmod(0o444)
            self.secrets[name] = path
            self.sql("ALTER ROLE " + role + " LOGIN NOINHERIT PASSWORD " + literal(path.read_text()))
        # Docker's container: network mode rejects --add-host. The client keeps
        # its own immutable hosts fixture while sharing the isolated loopback.
        hosts = self.directory / "crm-hosts"
        hosts.write_text("127.0.0.1 localhost agents-s2s agents-ci.internal\n::1 localhost\n")
        hosts.chmod(0o444)
        self.secrets["hosts"] = hosts
        now = int(self.sql("SELECT floor(extract(epoch FROM clock_timestamp()))::bigint"))
        self.sql("INSERT INTO crm_global_identities(id) VALUES(" + literal(ACTOR) + ");"
                 "INSERT INTO crm_organizations(id) VALUES(" + literal(COMPANY_A) + "),(" + literal(COMPANY_B) + ");")
        for company, tenant, funnel, lead in ((COMPANY_A, "SYNTHETIC_TENANT_A", FUNNEL_A, LEAD_A),
                                              (COMPANY_B, "SYNTHETIC_TENANT_B", FUNNEL_B, LEAD_B)):
            self.sql("INSERT INTO crm_private_funnels VALUES(" + literal(funnel) + "," + literal(company) +
                     ",'FUNIL_TESTE_FEEDBACK'," + str(now) + "); INSERT INTO crm_private_leads"
                     "(id,organization_id,funnel_id,title,created_at) VALUES(" + literal(lead) + "," +
                     literal(company) + "," + literal(funnel) + ",'LEAD_TESTE_FEEDBACK'," + str(now) + ");"
                     "INSERT INTO crm_auto_service.outbound_grants VALUES(" + literal(company) + "," +
                     literal(tenant) + ",'imagine-ai-agents','lead.ingest',true,1," + str(now + 1800) + ");"
                     "INSERT INTO crm_auto_service.execution_read_grants VALUES(" + literal(company) + "," +
                     literal(tenant) + ",'imagine-ai-agents','event.execution.read',true,1," + str(now + 1800) + ");")
        for case in ("MAIN", "UNKNOWN", "PENDING"):
            self.sql("INSERT INTO crm_private_events VALUES(" + literal(CASE_IDS[case]) + "," + literal(COMPANY_A) +
                     "," + literal(ACTOR) + "," + literal(LEAD_A) + ",'lead.created'," + str(now) + ")")
        self.profile, self.metadata = self.backup.detect_profile(self.sql, self.base_tables)
        require(self.profile.get("execution_feedback") is True and self.profile.get("execution_monitor") is True
                and self.profile.get("name") == "synthetic-private-s2s-execution-monitor-v1",
                "FEEDBACK_CRM_BACKUP_PROFILE")

    def monitor(self, mode: str, event: str | None = CASE_IDS["MAIN"], *, company: str = COMPANY_A,
                raw: str | None = None) -> dict:
        """Separate app-role reader, or credential-free decoding after restore.

        This is a scoped CI SQL/parser proof. It does not bootstrap the human
        application or pretend that the port-5433 fixture satisfies its IAM gate.
        """
        require(mode in ("read", "validate", "isolation") and company in (COMPANY_A, COMPANY_B),
                "FEEDBACK_MONITOR_MODE_OR_COMPANY")
        hardening = self.agents.base_args()
        require(hardening[:2] == ["--network", "container:" + self.agents.containers["src"]],
                "FEEDBACK_MONITOR_NETWORK_CONTRACT")
        if mode == "validate":
            hardening[1] = "none"
        args = ["docker", "run", "--rm", "-i"] + hardening
        args += self.agents.mount(MONITOR_PROBE, "/probe.php") + self.agents.mount(self.source, "/crm-source")
        environment = {
            "GITHUB_ACTIONS": os.environ["GITHUB_ACTIONS"], "GITHUB_REPOSITORY": os.environ["GITHUB_REPOSITORY"],
            "CRM_FEEDBACK_CI": "synthetic-private-v1", "CRM_FEEDBACK_SOURCE_SHA": self.source_sha,
        }
        payload = {"company_id": company, "event_id": event}
        if mode == "validate":
            require(isinstance(raw, str), "FEEDBACK_MONITOR_RESTORED_WIRE_REQUIRED")
            payload["body_b64"] = base64.b64encode(raw.encode("utf-8")).decode()
        else:
            require(raw is None, "FEEDBACK_MONITOR_UNEXPECTED_WIRE")
            args += self.agents.mount(self.secrets["monitor-app-password"], "/run/secrets/crm-monitor-app-password")
            environment["CRM_MONITOR_DSN"] = "pgsql:host=127.0.0.1;port=5433;dbname=" + CRM_DATABASE
            environment["PGAPPNAME"] = "crm_feedback_monitor"
        for name, value in environment.items():
            args += ["-e", name + "=" + value]
        args += ["--entrypoint", "php", self.php_image, "/probe.php", mode]
        result = closed_json(run(args, "FEEDBACK_MONITOR_" + mode.upper(), stdin=wire(payload), timeout=20).stdout)
        require(result.get("ok") is True,
                "FEEDBACK_MONITOR_" + mode.upper() + "_" + result.get("reason", "FAILED"))
        require(isinstance(result.get("value"), dict), "FEEDBACK_MONITOR_RESPONSE_OBJECT")
        return result["value"]

    def call(self, mode: str, event: str = CASE_IDS["MAIN"], *, company: str = COMPANY_A,
             profile: str | None = None, **payload) -> dict:
        args = ["docker", "run", "--rm", "-i"] + self.agents.base_args()
        args += self.agents.mount(self.secrets["hosts"], "/etc/hosts")
        args += ["--tmpfs", "/var/www/html/storage:rw,noexec,nosuid,size=16m,uid=33,gid=33,mode=0770",
                 "--tmpfs", "/var/www/html/bootstrap/cache:rw,noexec,nosuid,size=8m,uid=33,gid=33,mode=0770"]
        args += self.agents.mount(CRM_PROBE, "/probe.php") + self.agents.mount(self.source, "/crm-source")
        for source, target in ((self.agents.directory / "ca.crt", "ca.crt"),
                                (self.agents.certificates["client-a"][0], "crm.crt"),
                                (self.agents.certificates["client-a"][1], "crm.key"),
                                (self.agents.secrets["ack-key"], "agents-signing.key"),
                                (self.agents.secrets["execution-key"], "agents-execution.key"),
                                (self.agents.secrets["receipt-key"], "agents-receipt.key")):
            args += self.agents.mount(source, "/certs/" + target)
        password = "sender.password" if mode in ("send", "stage") else "execution-reader.password"
        args += self.agents.mount(self.secrets[password], "/certs/" + password)
        environment = {
            "GITHUB_ACTIONS": "true", "GITHUB_REPOSITORY": "rafhael100/imagine-ai-agents",
            "CRM_FEEDBACK_CI": "synthetic-private-v1", "CRM_FEEDBACK_SOURCE_SHA": self.source_sha,
            "APP_ENV": "staging", "APP_DEBUG": "false", "LOG_CHANNEL": "stderr",
            "CACHE_STORE": "array", "SESSION_DRIVER": "array",
            "APP_KEY": "base64:" + base64.b64encode(secrets.token_bytes(32)).decode(),
            "CRM_AGENTS_S2S_PROFILE": "synthetic-private-v1",
            "CRM_AGENTS_EXECUTION_PROFILE": "synthetic-private-v1" if profile is None else profile,
            "CRM_AUTO_S2S_DSN": "pgsql:host=127.0.0.1;port=5433;dbname=" + CRM_DATABASE,
            "PGAPPNAME": "crm_feedback_reconciler",
        }
        for name, value in environment.items():
            args += ["-e", name + "=" + value]
        args += ["--entrypoint", "php", self.php_image, "/probe.php", mode]
        result = run(args, "FEEDBACK_CRM_" + mode.upper(), stdin=wire({"company_id": company, "event_id": event, **payload}),
                     timeout=20)
        return closed_json(result.stdout)

    def success(self, mode: str, event: str = CASE_IDS["MAIN"], **payload) -> dict:
        result = self.call(mode, event, **payload)
        require(result.get("ok") is True, "FEEDBACK_CRM_" + mode.upper() + "_" + result.get("reason", "FAILED"))
        require(isinstance(result.get("value"), dict), "FEEDBACK_CRM_RESPONSE_OBJECT")
        return result["value"]

    def snapshot(self, which: str = "src") -> dict:
        return table_snapshot(lambda sql: self._sql(which, sql), self.profile["tables"])

    def business(self) -> dict:
        return table_snapshot(self.sql, tuple("public." + table for table in self.base_tables))

    def outbox(self, event: str) -> dict:
        raw = self.sql("SELECT to_jsonb(o) FROM crm_auto_service.outbox o WHERE company_id=" + literal(COMPANY_A) +
                       " AND event_id=" + literal(event))
        require(bool(raw), "FEEDBACK_REAL_CRM_OUTBOX_REQUIRED")
        return json.loads(raw)

    def body(self, event: str) -> dict:
        row = self.outbox(event)
        payload = row["payload"].encode("utf-8")
        envelope = {"schema": "AUTO_IA_S2S_V1", "issuer": "imagine-crm", "audience": "imagine-ai-agents",
                    "product": "imagine-crm", "operation": "lead.ingest", "tenant_ref": row["tenant_ref"],
                    "event_ref": "SYNTHETIC_" + event.replace("-", "").upper(),
                    "nonce_ref": row["nonce_ref"], "request_ref": row["request_ref"],
                    "issued_at": row["issued_at"], "expires_at": row["expires_at"],
                    "payload_hash": hashlib.sha256(payload).hexdigest(), "key_id": "SYNTHETIC_KEY_A"}
        envelope["signature"] = hmac.new(KEY_A, "\n".join(str(v) for v in envelope.values()).encode(),
                                          hashlib.sha256).hexdigest()
        return {"company_id": COMPANY_A, "envelope": envelope, "payload_b64": base64.b64encode(payload).decode()}

    def evidence(self, event: str, state: str) -> dict:
        raw = self.sql("SELECT jsonb_build_object('query',query,'body_b64',encode(convert_to(receipt,'UTF8'),'base64')) "
                       "FROM " + HISTORY + " WHERE company_id=" + literal(COMPANY_A) + " AND event_id=" +
                       literal(event) + " AND execution_state=" + literal(state) + " ORDER BY sequence DESC LIMIT 1")
        require(bool(raw), "FEEDBACK_PERSISTED_HTTP_EVIDENCE_REQUIRED")
        result = json.loads(raw)
        result["body_b64"] = result["body_b64"].replace("\n", "")
        return result


def need_denial(result: dict, reason: str) -> None:
    require(result == {"ok": False, "reason": reason}, "FEEDBACK_EXPECTED_" + reason)


def need_view(view: dict, body: dict, state: str, queue: str, history: int, task: str = "") -> None:
    require(view.get("schema") == "CRM_AGENTS_EXECUTION_VIEW_V1" and view.get("company_id") == COMPANY_A and
            view.get("tenant_ref") == body["envelope"]["tenant_ref"] and
            view.get("event_ref") == body["envelope"]["event_ref"] and
            view.get("payload_hash") == body["envelope"]["payload_hash"] and view.get("lead_id") == LEAD_A and
            view.get("delivery_state") == "accepted" and view.get("history_count") == history and
            view.get("crm_mutated") is False and view.get("operational_authorization") is False,
            "FEEDBACK_CRM_VIEW_SOURCE_AND_HISTORY")
    observation = view.get("observation", {})
    require(observation.get("execution_state") == state and observation.get("queue_state") == queue and
            observation.get("task_payload_hash") == task and observation.get("sequence") == history and
            observation.get("result_code") == ("PREPARED" if state in ("RESULT", "ACKED") else "NONE") and
            observation.get("attempt") == (0 if state in ("NOT_STAGED", "NOT_STARTED") else 1),
            "FEEDBACK_CRM_VIEW_EXECUTION_FACTS")
    require(isinstance(view.get("history"), list) and len(view["history"]) == min(history, 20) and
            [row["sequence"] for row in view["history"]] == list(range(history, max(history - 20, 0), -1)),
            "FEEDBACK_CRM_HISTORY_ORDER")


def read_monitor(crm: CrmLab, event: str | None = CASE_IDS["MAIN"], *, company: str = COMPANY_A) -> dict:
    before = crm.snapshot()
    model = crm.monitor("read", event, company=company)
    require(crm.snapshot() == before, "FEEDBACK_MONITOR_READ_MUTATED_CRM")
    require(set(model) == {"schema", "company_id", "generated_at", "total_events", "events", "selected"}
            and model["schema"] == "CRM_N8N_EXECUTION_MONITOR_V1" and model["company_id"] == company
            and type(model["generated_at"]) is int and type(model["total_events"]) is int
            and isinstance(model["events"], list) and len(model["events"]) == min(50, model["total_events"]),
            "FEEDBACK_MONITOR_CLOSED_MODEL")
    require(all(row.get("history") == [] for row in model["events"]), "FEEDBACK_MONITOR_LIST_LEAKED_HISTORY")
    return model


def need_monitor(model: dict, event: str, view: dict | None, *, delivery: str = "accepted") -> None:
    selected = model.get("selected")
    require(model.get("company_id") == COMPANY_A and isinstance(selected, dict)
            and selected.get("event_id") == event and selected.get("lead_id") == LEAD_A
            and selected.get("lead_title") == "LEAD_TESTE_FEEDBACK" and selected.get("delivery_state") == delivery,
            "FEEDBACK_MONITOR_SOURCE_BINDING")
    # Compare the display fields directly with the already-verified reconciler
    # output, preserving persisted sequence/time. No receipt or fact is invented.
    expected_observation = None if view is None else {
        field: view["observation"][field] for field in MONITOR_OBSERVATION_FIELDS}
    expected_history = [] if view is None else [
        {field: row[field] for field in MONITOR_OBSERVATION_FIELDS} for row in view["history"]]
    require(selected.get("observation") == expected_observation
            and selected.get("history") == expected_history
            and selected.get("history_count") == (0 if view is None else view["history_count"]),
            "FEEDBACK_MONITOR_REAL_SIGNED_HISTORY_DIFFERS")
    summary = dict(selected, history=[])
    require([row for row in model["events"] if row.get("event_id") == event] == [summary],
            "FEEDBACK_MONITOR_LIST_DETAIL_DISAGREE")


def monitor_durable_fields(model: dict) -> dict:
    # The SQL reader deliberately stamps each statement. This is the only
    # source/restore difference excluded; all event and observation bytes count.
    require(type(model.get("generated_at")) is int, "FEEDBACK_MONITOR_GENERATED_AT_REQUIRED")
    return {key: value for key, value in model.items() if key != "generated_at"}


def execution_permissions(lab: FeedbackLab, crm: CrmLab) -> None:
    for role in ("aia_private_execution", "aia_private_execution_login"):
        for table, privilege in (("auto_ia_lab.lead_ingest_payloads", "SELECT"),
                                 ("aia_p26_native.executions", "SELECT"),
                                 (EXECUTION_GRANTS, "UPDATE"),
                                 ("public.aia_lab_n8n_outbox_p6", "UPDATE")):
            require(lab._sql("src", "SELECT has_table_privilege(" + literal(role) + "," + literal(table) +
                             "," + literal(privilege) + ")") == "f", "FEEDBACK_EXECUTION_ROLE_DIRECT_TABLE_ACCESS")
    for role in ("aia_private_transport", "aia_private_receipt", "aia_private_execution_login"):
        require(lab._sql("src", "SELECT has_function_privilege(" + literal(role) +
                         ",'auto_ia_lab.read_company_execution_snapshot(uuid,text,jsonb)','EXECUTE')") == "f",
                "FEEDBACK_EXECUTION_ROLE_SEPARATION")
    require(lab._sql("src", "SELECT count(*) FROM pg_auth_members WHERE member='aia_private_execution_login'::regrole "
                    "AND roleid='aia_private_execution'::regrole AND NOT admin_option AND NOT inherit_option AND set_option") == "1",
            "FEEDBACK_EXECUTION_SET_ONLY_MEMBERSHIP")
    for table in (HISTORY, PROJECTIONS, "crm_auto_service.outbox", "public.crm_private_leads"):
        for privilege in ("SELECT", "INSERT", "UPDATE", "DELETE"):
            require(crm.sql("SELECT has_table_privilege('crm_auto_execution_reconciler'," + literal(table) + "," +
                            literal(privilege) + ")") == "f", "FEEDBACK_CRM_RECONCILER_DIRECT_ACCESS")
    require(crm.sql("SELECT count(*) FROM pg_roles WHERE rolname IN "
                    "('aia_private_transport','aia_private_execution_login')") == "0" and
            lab._sql("src", "SELECT count(*) FROM pg_roles WHERE rolname IN "
                     "('crm_auto_sender','crm_auto_execution_reconciler')") == "0",
            "FEEDBACK_APPLICATION_DATABASE_IDENTITIES_MUST_BE_SEPARATE")
    tables = ("crm_auto_service.execution_read_grants", HISTORY, PROJECTIONS)
    require(crm.backup.scoped_counts(crm.sql, "", role="crm_auto_reader_owner", tables=tables) == "SCOPED|0|0|0",
            "FEEDBACK_CRM_UNSCOPED_RLS")
    require(crm.success("isolation")["agents_database_denied"] is True, "FEEDBACK_CRM_DATABASE_ISOLATION")
    require(crm.monitor("isolation") == {
        "database_role": "crm_homologation_app", "transaction_read_only": True,
        "service_capabilities_denied": True, "tenant_context_cleared": True,
        "human_authorization_tested": False,
    }, "FEEDBACK_MONITOR_APP_ROLE_ISOLATION")


def projection_fault(crm: CrmLab, lab: FeedbackLab) -> None:
    # The verified HTTP response reaches INSERT history, then a second write
    # fails. Neither the history row nor the projection may survive.
    crm.sql("CREATE FUNCTION public.feedback_projection_fault() RETURNS trigger LANGUAGE plpgsql AS $$ "
            "BEGIN RAISE EXCEPTION 'FEEDBACK_CI_PROJECTION_FAULT'; END $$; "
            "CREATE TRIGGER feedback_projection_fault AFTER INSERT ON " + PROJECTIONS +
            " FOR EACH ROW EXECUTE FUNCTION public.feedback_projection_fault()")
    before, agents_before = crm.snapshot(), agents_snapshot(lab)
    try:
        need_denial(crm.call("reconcile"), "FEEDBACK_CI_PROJECTION_FAULT")
        require(crm.snapshot() == before and agents_snapshot(lab) == agents_before,
                "FEEDBACK_PROJECTION_FAILURE_DID_NOT_ROLL_BACK")
    finally:
        crm.sql("DROP TRIGGER feedback_projection_fault ON " + PROJECTIONS +
                "; DROP FUNCTION public.feedback_projection_fault()")
    print("CRM_FEEDBACK_REAL_HTTP_PROJECTION_FAILURE_ROLLS_BACK_HISTORY_AND_VIEW_PASS", flush=True)


def grant_fence(crm: CrmLab, lab: FeedbackLab, event: str) -> None:
    # A real transaction waits after inserting the observation. A concurrent
    # grant revocation must wait for that same transaction, which then times
    # out and rolls back. All clock/lock evidence comes from PostgreSQL.
    key = 190826301
    crm.sql("CREATE FUNCTION public.feedback_projection_wait() RETURNS trigger LANGUAGE plpgsql AS $$ "
            "BEGIN PERFORM pg_advisory_xact_lock(" + str(key) + "); RETURN NEW; END $$; "
            "CREATE TRIGGER feedback_projection_wait AFTER INSERT ON " + HISTORY +
            " FOR EACH ROW EXECUTE FUNCTION public.feedback_projection_wait()")
    before = crm.snapshot()
    agents_before = agents_snapshot(lab)
    with ThreadPoolExecutor(max_workers=3) as pool:
        blocker = pool.submit(crm.sql, "BEGIN; SET LOCAL application_name='feedback_projection_blocker'; "
                              "SET LOCAL statement_timeout='8s'; SELECT pg_advisory_xact_lock(" + str(key) +
                              "); SELECT pg_sleep(4); COMMIT;")
        try:
            for _ in range(40):
                if crm.sql("SELECT count(*) FROM pg_stat_activity WHERE application_name='feedback_projection_blocker' "
                           "AND wait_event='PgSleep'") == "1":
                    break
                require(not blocker.done(), "FEEDBACK_GRANT_BLOCKER_ENDED")
                time.sleep(0.025)
            else:
                raise Failure("FEEDBACK_GRANT_BLOCKER_NOT_OBSERVED")
            caller = pool.submit(crm.call, "reconcile", event)
            for _ in range(50):
                waiting = crm.sql("SELECT count(*) FROM pg_stat_activity a WHERE usename='crm_auto_execution_reconciler' "
                    "AND wait_event_type='Lock' AND EXISTS(SELECT 1 FROM pg_stat_activity b "
                    "WHERE b.application_name='feedback_projection_blocker' AND b.pid=ANY(pg_blocking_pids(a.pid)))")
                if waiting == "1":
                    break
                require(not caller.done(), "FEEDBACK_GRANT_RECONCILER_DID_NOT_WAIT")
                time.sleep(0.025)
            else:
                raise Failure("FEEDBACK_GRANT_PROJECTION_WAIT_NOT_OBSERVED")
            revoker = pool.submit(crm.sql, "SET application_name='feedback_read_grant_revoker'; "
                "UPDATE crm_auto_service.execution_read_grants SET active=false,revision=revision+1 "
                "WHERE company_id=" + literal(COMPANY_A))
            for _ in range(30):
                if crm.sql("SELECT count(*) FROM pg_stat_activity a WHERE application_name='feedback_read_grant_revoker' "
                           "AND wait_event_type='Lock' AND EXISTS(SELECT 1 FROM pg_stat_activity b "
                           "WHERE b.usename='crm_auto_execution_reconciler' AND b.pid=ANY(pg_blocking_pids(a.pid)))") == "1":
                    break
                require(not revoker.done(), "FEEDBACK_GRANT_REVOCATION_DID_NOT_WAIT")
                time.sleep(0.025)
            else:
                raise Failure("FEEDBACK_GRANT_REVOCATION_LOCK_NOT_OBSERVED")
            need_denial(caller.result(), "CRM_FEEDBACK_DATABASE_TIMEOUT")
            revoker.result(); blocker.result()
            after = crm.snapshot()
            require(all(after[name] == value for name, value in before.items()
                        if name != "crm_auto_service.execution_read_grants") and
                    agents_snapshot(lab) == agents_before, "FEEDBACK_GRANT_FENCE_PARTIAL_WRITE")
            need_denial(crm.call("reconcile", event), "EXECUTION_READ_GRANT_DENIED")
        finally:
            blocker.result()
            crm.sql("DROP TRIGGER feedback_projection_wait ON " + HISTORY +
                    "; DROP FUNCTION public.feedback_projection_wait(); "
                    "UPDATE crm_auto_service.execution_read_grants SET active=true,revision=revision+1 "
                    "WHERE company_id=" + literal(COMPANY_A))
    print("CRM_FEEDBACK_CONCURRENT_READ_GRANT_REVOCATION_FENCED_AND_TIMEOUT_ROLLBACK_PASS", flush=True)


def negative_http(lab: FeedbackLab, crm: CrmLab, body: dict) -> None:
    before, agents_before = crm.snapshot(), agents_snapshot(lab)

    def deny(value, status: int = 403, **options):
        reply = lab.execution_http(value, **options)
        require(reply["status"] == status and reply["cache_control"] == "no-store" and
                base64.b64decode(reply["body_b64"], validate=True) == DENIAL,
                "FEEDBACK_EXECUTION_UNIFORM_DENIAL")

    for changes in ({"company_id": COMPANY_B}, {"tenant_ref": "SYNTHETIC_TENANT_B"},
                    {"event_ref": "SYNTHETIC_MISSING_FEEDBACK"}, {"payload_hash": "0" * 64},
                    {"operation": "event.receipt.read"}, {"schema": "AUTO_IA_RECEIPT_QUERY_V1"},
                    {"issued_at": int(time.time()) - 31, "expires_at": int(time.time()) - 1}):
        deny(status_query(body, **changes))
    bad = status_query(body); bad["signature"] = "0" * 64; deny(bad)
    raw = wire(status_query(body))
    deny(b'{"schema":"AUTO_IA_EXECUTION_QUERY_V1",' + raw[1:])
    deny(b'{"sch\\u0065ma":"AUTO_IA_EXECUTION_QUERY_V1",' + raw[1:])
    deny(raw[:-1] + b',"unexpected":true}')
    for headers in (["Cookie: synthetic=forbidden"], ["Authorization: Bearer SYNTHETIC_FORBIDDEN"],
                    ["Content-Encoding: gzip"]):
        deny(status_query(body), headers=headers)
    deny(status_query(body), method="GET")
    deny(status_query(body), content_type="text/plain")
    deny(status_query(body), path="/private/events/execution?x=1")
    for client in ("client-b", "client-unknown"):
        deny(status_query(body), client=client)
    for client in (None, "client-foreign"):
        reply = lab.execution_http(status_query(body), client=client,
            headers=["IMAGINE_MTLS_VERIFY: SUCCESS", "IMAGINE_MTLS_CERT: forged"])
        require(reply["status"] in (0, 400, 403), "FEEDBACK_MTLS_CERTIFICATE_REQUIRED")
    query = status_query(body)
    reply = lab.execution_http(query)
    verify_status(reply, query, lab.secrets["execution-key"].read_bytes())
    crm.success("validate", query=query, body_b64=reply["body_b64"])
    raw = base64.b64decode(reply["body_b64"], validate=True)
    mutated = closed_json(raw); mutated["lead_id"] = LEAD_B
    need_denial(crm.call("validate", query=query, body_b64=base64.b64encode(wire(mutated)).decode()),
                "EXECUTION_OBSERVATION_HASH")
    mutated = closed_json(raw); mutated["signature"] = "0" * 64
    need_denial(crm.call("validate", query=query, body_b64=base64.b64encode(wire(mutated)).decode()),
                "EXECUTION_STATUS_SIGNATURE")
    need_denial(crm.call("validate", query=query,
                body_b64=base64.b64encode(b'{"schema":"AUTO_IA_EXECUTION_STATUS_V1",' + raw[1:]).decode()),
                "CRM_FEEDBACK_RUNTIME_FAILED")
    need_denial(crm.call("reconcile", profile="disabled"), "EXECUTION_RECONCILER_DISABLED")
    need_denial(crm.call("reconcile", company=COMPANY_B), "EXECUTION_SOURCE_DENIED")
    # Corrupt execution evidence is a retryable failure, never a signed
    # UNKNOWN fact that could replace the CRM's successful projection.
    scope = "tenant_ref=" + literal(body["envelope"]["tenant_ref"]) + " AND event_ref=" + literal(body["envelope"]["event_ref"])
    result_hash = lab._sql("src", "SELECT result_sha256 FROM aia_p26_native.executions WHERE " + scope)
    require(re.fullmatch(r"[a-f0-9]{64}", result_hash) is not None, "FEEDBACK_REAL_EXECUTION_HASH_REQUIRED")
    lab._sql("src", "UPDATE aia_p26_native.executions SET result_sha256=repeat('0',64) WHERE " + scope)
    corrupted = agents_snapshot(lab)
    try:
        deny(status_query(body), status=503)
        need_denial(crm.call("reconcile"), "EXECUTION_TRANSPORT_DENIED")
        require(agents_snapshot(lab) == corrupted and crm.snapshot() == before,
                "FEEDBACK_CORRUPTION_REPAIRED_OR_PROJECTED_BY_STATUS_READ")
    finally:
        lab._sql("src", "UPDATE aia_p26_native.executions SET result_sha256=" + literal(result_hash) + " WHERE " + scope)
    require(crm.snapshot() == before and agents_snapshot(lab) == agents_before,
            "FEEDBACK_NEGATIVE_READ_CHANGED_DURABLE_STATE")
    print("CRM_FEEDBACK_REAL_MTLS_SCOPE_REQUEST_AND_RESPONSE_TAMPER_DENIED_READONLY_PASS", flush=True)


def restore_crm(crm: CrmLab, lab: FeedbackLab, stack: Stack, directory: Path) -> dict:
    # The existing CRM backup contract compares owners/ACLs/RLS, functions,
    # constraints and triggers. Only actual data produced by this HTTP proof
    # are restored here; encryption remains in the CRM backup regression suite.
    original = crm.snapshot()
    require(len(original) == 33, "FEEDBACK_CRM_NATIVE_RESTORE_TABLE_SCOPE")
    native_before, agents_before = n8n_snapshot(stack), agents_snapshot(lab)
    monitor_cases = [(COMPANY_A, CASE_IDS[case]) for case in ("MAIN", "UNKNOWN", "PENDING")]
    monitor_cases += [(COMPANY_A, None), (COMPANY_B, CASE_IDS["MAIN"])]
    original_monitors = {(company, event): read_monitor(crm, event, company=company)
                         for company, event in monitor_cases}
    require(crm.snapshot() == original, "FEEDBACK_MONITOR_PRE_DUMP_READ_MUTATED_CRM")
    captured = capture_crm_backup(crm.backup, crm.backup_snapshot, crm.containers["src"],
                                  crm.base_tables, table_snapshot)
    profile, metadata, source_catalog = captured["profile"], captured["security"], captured["catalog"]
    require(captured["rows"] == original and crm.snapshot() == original,
            "FEEDBACK_CRM_SNAPSHOT_DIFFERS_FROM_MONITOR_STATE")
    contents = captured["contents"]
    revoke = ("UPDATE crm_auto_service.execution_read_grants SET active=false,revision=revision+1 WHERE active; "
              "UPDATE crm_auto_service.outbound_grants SET active=false,revision=revision+1 WHERE active;")
    crm.sql(revoke)
    revoked = crm.snapshot()
    crm._start("dst")
    crm._sql("dst", ";".join("CREATE ROLE " + role +
        " NOLOGIN NOINHERIT NOSUPERUSER NOBYPASSRLS NOCREATEDB NOCREATEROLE NOREPLICATION"
        for role in profile["roles"]))
    run(["docker", "exec", "-i", crm.containers["dst"], "pg_restore", "-p", "5433", "-U",
         "crm_homologation_restore", "-d", CRM_DATABASE, "--exit-on-error"],
        "FEEDBACK_CRM_RESTORE_OWNER_ACL", stdin=contents)
    restored_query = lambda sql: crm._sql("dst", sql)
    fidelity = crm.backup.verify_restored_security(restored_query, profile, metadata, crm.base_tables,
                                                   source_catalog=source_catalog)
    require(crm.snapshot("dst") == original, "FEEDBACK_CRM_RESTORED_BYTES_OR_HISTORY_DIFFER")
    crm._sql("dst", revoke)
    require(crm.snapshot("dst") == revoked, "FEEDBACK_CRM_POST_SNAPSHOT_REVOCATIONS_DIFFER")
    for case in ("MAIN", "UNKNOWN"):
        crm._sql("dst", "BEGIN; SET LOCAL ROLE crm_auto_execution_reconciler; DO $probe$ BEGIN "
                 "PERFORM crm_auto_service.read_execution_projection(" + literal(COMPANY_A) + "," +
                 literal(CASE_IDS[case]) + "); RAISE EXCEPTION 'FEEDBACK_RESTORED_GRANT_ADMITTED'; "
                 "EXCEPTION WHEN SQLSTATE 'P0001' THEN IF SQLERRM<>'EXECUTION_READ_GRANT_DENIED' THEN RAISE; "
                 "END IF; END $probe$; ROLLBACK;")
    for company, event in monitor_cases:
        # Restored roles remain NOLOGIN with no password. The CI administrator
        # assumes only the app role for this read; PHP validates offline with no
        # database or service secret and --network none.
        event_sql = "NULL::uuid" if event is None else literal(event) + "::uuid"
        raw = crm._sql("dst", "BEGIN READ ONLY; SET LOCAL ROLE crm_homologation_app; "
                      "SET LOCAL row_security=on; SET LOCAL app.tenant_id=" + literal(company) + "; "
                      "SELECT CASE WHEN current_user='crm_homologation_app' "
                      "AND current_setting('transaction_read_only')='on' "
                      "THEN crm_auto_service.read_execution_monitor(" + event_sql + ")::text ELSE NULL END; ROLLBACK;")
        require(bool(raw), "FEEDBACK_RESTORED_MONITOR_APP_ROLE_REQUIRED")
        restored = crm.monitor("validate", event, company=company, raw=raw)
        require(monitor_durable_fields(restored) == monitor_durable_fields(original_monitors[(company, event)]),
                "FEEDBACK_RESTORED_MONITOR_MODEL_DIFFERS")
    require(crm.snapshot("dst") == revoked and n8n_snapshot(stack) == native_before and
            agents_snapshot(lab) == agents_before, "FEEDBACK_RESTORE_MUTATED_OR_REEXECUTED")
    require(crm.backup.scoped_counts(restored_query, COMPANY_B, role="crm_auto_reader_owner",
            tables=(HISTORY, PROJECTIONS)) == "SCOPED|0|0", "FEEDBACK_RESTORED_CROSS_TENANT_RLS")
    return {"schema": "CRM_EXECUTION_FEEDBACK_RESTORE_V1", "profile": profile["name"],
            "table_snapshots": len(original), "http_evidence_history_projection_exact": True,
            "security_manifest_preserved": True, "roles_nologin_without_memberships": True,
            "source_security_catalog_rows_and_dump_same_snapshot": True,
            "snapshot_exported_at_utc": captured["snapshot_exported_at_utc"],
            "catalog_fidelity": fidelity,
            "post_snapshot_read_and_outbound_revocations_applied": True,
            "monitor_app_role_and_offline_php_preserved": True,
            "monitor_restore_ignored_fields": ["generated_at"],
            "monitor_models_compared": len(monitor_cases),
            "native_execution_calls_during_restore": 0, "separate_pg16_clusters": 2,
            "external_backup_verified": False, "go_for_installation": False}


def exercise(lab: FeedbackLab, crm: CrmLab, stack: Stack, directory: Path) -> dict:
    stack.claim_empty()
    stack.dc("up", "-d", "--wait", "--wait-timeout", "180", timeout=240)
    stack.inspect_runtime(); stack.dc("stop", "n8n")
    lab.prepare_feedback(); crm.prepare()
    execution_permissions(lab, crm)
    business_before = crm.business()
    require(crm.success("send")["admission_verified"] is True, "FEEDBACK_REAL_EMITTER_ACK")
    main_body = crm.body(CASE_IDS["MAIN"])
    require(crm.outbox(CASE_IDS["MAIN"])["state"] == "accepted", "FEEDBACK_SOURCE_ADMISSION_ACK")
    expected_row(lab, main_body)
    require(crm.business() == business_before and rpc(lab, "stats")["outbox"] == 0,
            "FEEDBACK_RECEIPT_MUST_NOT_EXECUTE_OR_MUTATE_LEAD")
    projection_fault(crm, lab)
    monitor_agents_before, monitor_native_before = agents_snapshot(lab), n8n_snapshot(stack)
    need_monitor(read_monitor(crm), CASE_IDS["MAIN"], None)
    require(agents_snapshot(lab) == monitor_agents_before and n8n_snapshot(stack) == monitor_native_before,
            "FEEDBACK_UNOBSERVED_MONITOR_MUTATED_AGENTS_OR_NATIVE")
    source_template = template(PRIVATE / "workflows/native-crm-lead-noai.workflow.json")
    observed_calls = 0

    def observe(body: dict, state: str, queue: str, count: int, task: str = "") -> dict:
        event = json.loads(base64.b64decode(body["payload_b64"]))["event_id"]
        before = agents_snapshot(lab)
        native_before = n8n_snapshot(stack)
        view = crm.success("reconcile", event)
        need_view(view, body, state, queue, count, task)
        need_monitor(read_monitor(crm, event), event, view)
        require(agents_snapshot(lab) == before and crm.business() == business_before
                and n8n_snapshot(stack) == native_before,
                "FEEDBACK_READ_MUTATED_AGENTS_OR_COMMERCIAL_CRM")
        return view

    # Both transactions start without a projection/history row. The source
    # outbox lock serializes their actual HTTPS observations and one insertion.
    initial, destination_before = crm.snapshot(), agents_snapshot(lab)
    require(initial[HISTORY]["rows"] == 0 and initial[PROJECTIONS]["rows"] == 0,
            "FEEDBACK_CONCURRENT_FIRST_OBSERVATION_MUST_START_EMPTY")
    with ThreadPoolExecutor(max_workers=2) as pool:
        duplicates = list(pool.map(lambda _: crm.success("reconcile"), range(2)))
    for view in duplicates:
        need_view(view, main_body, "NOT_STAGED", "NONE", 1)
    first = duplicates[0]
    after = crm.snapshot()
    require(duplicates[1] == first and after[HISTORY]["rows"] == 1 and after[PROJECTIONS]["rows"] == 1 and
            all(after[name] == value for name, value in initial.items() if name not in (HISTORY, PROJECTIONS)) and
            agents_snapshot(lab) == destination_before and crm.business() == business_before,
            "FEEDBACK_CONCURRENT_FIRST_POLL_DUPLICATED_OR_MUTATED_STATE")
    require(observe(main_body, "NOT_STAGED", "NONE", 1) == first and crm.snapshot() == after,
            "FEEDBACK_REPEAT_POLL_REPLACED_OR_DUPLICATED_EVIDENCE")

    def stage(case: str, body: dict) -> tuple[dict, str]:
        seed = base64.b64encode(secrets.token_bytes(32)).decode()
        proposal = rpc(lab, "prepare", case=case, source=source_of(body), seed_b64=seed)
        require(proposal.get("schema") == "LEAD_PREPARED_PROPOSAL_V1", "FEEDBACK_OWNER_PROPOSAL")
        flow, context = materialize(source_template, proposal["context"])
        require(rpc(lab, "stage", proposal=proposal).get("decision") == "LAB_CAPTURED_LEAD_STAGED_NO_DELIVERY",
                "FEEDBACK_OWNER_BOUND_STAGE")
        stack.import_json("workflow", canonical([flow]))
        exported = directory / ("feedback-" + case.lower() + ".json")
        stack.export_json("workflow", exported)
        verify_export(flow, parse_json(exported))
        return context, seed

    main, seed = stage("MAIN", main_body)
    observe(main_body, "NOT_STARTED", "PENDING", 2, main["payload_hash"])
    reserved = rpc(lab, "lease", context=main)
    require(reserved.get("decision") == "LAB_LEASE_RESERVED_NO_DELIVERY" and
            reserved.get("event_ref") == main["event_ref"], "FEEDBACK_OWNER_BOUND_LEASE")
    observe(main_body, "NOT_STARTED", "LEASED", 3, main["payload_hash"])
    require(rpc(lab, "begin", context=main).get("decision") == "P26_CALL_ONCE", "FEEDBACK_DURABLE_EXECUTION_INTENT")
    observe(main_body, "STARTED", "LEASED", 4, main["payload_hash"])
    execution = parse_output(stack.cli("execute", "--id=" + main["workflow_id"], "--rawOutput", timeout=50).stdout)
    observed_calls += 1
    verify_result(execution, main)
    result = expected(main)
    require(result["lead_status"] == "PREPARED" and result["crm_mutated"] is False and result["ai_api_calls"] == 0,
            "FEEDBACK_NATIVE_PREPARED_RESULT")
    require(rpc(lab, "record-result", context=main, seed_b64=seed, execution=execution).get("decision") == "P26_RESULT_DURABLE",
            "FEEDBACK_NATIVE_RESULT_NOT_DURABLE")
    observe(main_body, "RESULT", "LEASED", 5, main["payload_hash"])
    old_result = crm.evidence(CASE_IDS["MAIN"], "RESULT")
    require(rpc(lab, "reconcile", context=main).get("state") == "ACKED", "FEEDBACK_NATIVE_RESULT_ACK")
    acked = observe(main_body, "ACKED", "ACKED", 6, main["payload_hash"])
    before = crm.snapshot()
    need_denial(crm.call("replay", **old_result), "EXECUTION_STATE_REGRESSION")
    require(crm.snapshot() == before, "FEEDBACK_SIGNED_RESULT_REGRESSED_ACKED")
    require(rpc(lab, "begin", context=main).get("decision") == "DENY" and
            rpc(lab, "reconcile", context=main).get("state") == "ACKED", "FEEDBACK_DUPLICATE_EXECUTION_RESTARTED")
    require(crm.success("reconcile") == acked and crm.snapshot() == before,
            "FEEDBACK_ACKED_POLL_REPLACED_OR_DUPLICATED_EVIDENCE")
    # The original admission receipt remains RECEIVED/DISABLED after real execution.
    query = make_query(COMPANY_A, main["tenant_ref"], main["event_ref"], main_body["envelope"]["payload_hash"])
    verify_receipt(lab.http(query), query, lab.secrets["receipt-key"].read_bytes())
    print("CRM_FEEDBACK_REAL_EMITTER_NATIVE_N8N_SIGNED_STATUS_AND_DURABLE_ACKED_HISTORY_PASS", flush=True)

    crm.success("send", CASE_IDS["UNKNOWN"])
    unknown_body = crm.body(CASE_IDS["UNKNOWN"])
    expected_row(lab, unknown_body)
    grant_fence(crm, lab, CASE_IDS["UNKNOWN"])
    unknown, _ = stage("UNKNOWN", unknown_body)
    require(rpc(lab, "lease", context=unknown).get("event_ref") == unknown["event_ref"], "FEEDBACK_UNKNOWN_LEASE")
    require(rpc(lab, "begin", context=unknown).get("decision") == "P26_CALL_ONCE", "FEEDBACK_UNKNOWN_INTENT")
    observe(unknown_body, "STARTED", "LEASED", 1, unknown["payload_hash"])
    started = crm.evidence(CASE_IDS["UNKNOWN"], "STARTED")
    require(rpc(lab, "reconcile", context=unknown).get("state") == "UNKNOWN", "FEEDBACK_UNKNOWN_RECONCILIATION")
    unknown_view = observe(unknown_body, "UNKNOWN", "LEASED", 2, unknown["payload_hash"])
    before = crm.snapshot()
    need_denial(crm.call("replay", CASE_IDS["UNKNOWN"], **started), "EXECUTION_STATE_REGRESSION")
    require(rpc(lab, "begin", context=unknown).get("decision") == "DENY" and
            crm.success("reconcile", CASE_IDS["UNKNOWN"]) == unknown_view and crm.snapshot() == before,
            "FEEDBACK_UNKNOWN_MUST_BE_ABSORBING_WITHOUT_RETRY")
    native_history = n8n_snapshot(stack)
    # This pinned stack deliberately uses EXECUTIONS_DATA_SAVE_ON_SUCCESS=none.
    # The verified CLI output and P26 result are durable evidence; an empty
    # n8n execution table must not be mislabeled as a saved success record.
    require(observed_calls == 1 and len(native_history["workflows"]) == 2 and
            native_history["credentials"] == 0 and
            lab._sql("src", "SELECT count(*) FROM aia_p26_native.executions WHERE result_json IS NOT NULL") == "1",
            "FEEDBACK_NATIVE_EXECUTION_COUNT")
    negative_http(lab, crm, main_body)
    crm.success("stage", CASE_IDS["PENDING"])
    pending_before, destination_before = crm.snapshot(), agents_snapshot(lab)
    need_denial(crm.call("reconcile", CASE_IDS["PENDING"]), "EXECUTION_TRANSPORT_DENIED")
    require(crm.snapshot() == pending_before and agents_snapshot(lab) == destination_before,
            "FEEDBACK_UNDELIVERED_EVENT_GAINED_STATUS")
    need_monitor(read_monitor(crm, CASE_IDS["PENDING"]), CASE_IDS["PENDING"], None, delivery="pending")
    listed = read_monitor(crm, None)
    require(listed["selected"] is None and listed["total_events"] == 3
            and [row["event_id"] for row in listed["events"]]
            == sorted((CASE_IDS[case] for case in ("MAIN", "UNKNOWN", "PENDING")), reverse=True),
            "FEEDBACK_MONITOR_REAL_EVENT_LIST_ORDER")
    foreign = read_monitor(crm, CASE_IDS["MAIN"], company=COMPANY_B)
    require(foreign["events"] == [] and foreign["selected"] is None and foreign["total_events"] == 0,
            "FEEDBACK_MONITOR_FOREIGN_TENANT_EXPOSED_EVENT")
    require(crm.snapshot() == pending_before and agents_snapshot(lab) == destination_before
            and n8n_snapshot(stack) == native_history, "FEEDBACK_MONITOR_SCOPE_READS_MUTATED_STATE")

    # Read authority is independent from the historical sender/owner/executor.
    rpc(lab, "revoke-owner", context=main)
    lab._sql("src", "UPDATE auto_ia_lab.service_company_grants SET active=false,revision=revision+1 WHERE company_id="
             + literal(COMPANY_A) + "; UPDATE aia_p37_lab.executor_keys SET active=false,revision=revision+1 "
             "WHERE key_id=" + literal(main["executor_key_id"]))
    crm.sql("UPDATE crm_auto_service.outbound_grants SET active=false,revision=revision+1 WHERE company_id=" + literal(COMPANY_A))
    require(crm.success("reconcile") == acked, "FEEDBACK_HISTORICAL_STATUS_DEPENDED_ON_REVOKED_EXECUTOR")
    historical_monitor = read_monitor(crm)
    need_monitor(historical_monitor, CASE_IDS["MAIN"], acked)
    for side in ("agents", "crm"):
        query = ("UPDATE " + (EXECUTION_GRANTS if side == "agents" else "crm_auto_service.execution_read_grants") +
                 " SET active=false,revision=revision+1 WHERE company_id=" + literal(COMPANY_A))
        (lambda q: lab._sql("src", q) if side == "agents" else crm.sql(q))(query)
        before, destination_before = crm.snapshot(), agents_snapshot(lab)
        need_denial(crm.call("reconcile"), "EXECUTION_TRANSPORT_DENIED" if side == "agents" else "EXECUTION_READ_GRANT_DENIED")
        if side == "agents":
            denied = lab.execution_http(status_query(main_body))
            require(denied["status"] == 403 and base64.b64decode(denied["body_b64"], validate=True) == DENIAL,
                    "FEEDBACK_REVOKED_DESTINATION_READ_GRANT_HTTP_STATUS")
        require(crm.snapshot() == before and agents_snapshot(lab) == destination_before,
                "FEEDBACK_REVOKED_READ_GRANT_WROTE_STATE")
        after_revocation = read_monitor(crm)
        need_monitor(after_revocation, CASE_IDS["MAIN"], acked)
        require(monitor_durable_fields(after_revocation) == monitor_durable_fields(historical_monitor)
                and agents_snapshot(lab) == destination_before and n8n_snapshot(stack) == native_history,
                "FEEDBACK_MONITOR_HISTORY_DEPENDED_ON_SERVICE_READ_GRANT")
        query = query.replace("active=false", "active=true")
        (lambda q: lab._sql("src", q) if side == "agents" else crm.sql(q))(query)
        require(crm.success("reconcile") == acked, "FEEDBACK_READ_GRANT_RECOVERY_DUPLICATED_HISTORY")
    require(crm.business() == business_before and lineage(lab) == 2 and lab.counts()["ai_requests"] == 0,
            "FEEDBACK_BUSINESS_DATA_OR_LINEAGE_CHANGED")
    readonly_audits(lab, 2)
    require(n8n_snapshot(stack) == native_history, "FEEDBACK_STATUS_LOOKUP_RERAN_NATIVE_WORKFLOW")
    recovery = restore_crm(crm, lab, stack, directory)
    require(n8n_snapshot(stack) == native_history, "FEEDBACK_RESTORE_RERAN_NATIVE_WORKFLOW")
    print("CRM_FEEDBACK_MONITOR_SQL_AND_PHP_REAL_HISTORY_READONLY_RESTORE_PASS", flush=True)
    return {"schema": "CRM_EXECUTION_FEEDBACK_VERIFIED_V1", "crm_source_sha": crm.source_sha,
            "actual_crm_emitter_and_reconciler": True, "actual_native_n8n_executions": observed_calls,
            "execution_states_observed": ["NOT_STAGED", "NOT_STARTED", "STARTED", "RESULT", "ACKED", "UNKNOWN"],
            "signed_status_via_real_mtls": True, "agents_status_reads_readonly": True,
            "crm_projection_and_immutable_http_history": True, "main_history_observations": 6,
            "monitor_sql_and_php_from_real_history": True, "human_identity_exercised": False,
            "monitor_source_database_role": "crm_homologation_app",
            "monitor_unobserved_and_not_staged_distinct": True,
            "monitor_foreign_tenant_returns_no_event": True,
            "monitor_history_survives_service_read_revocation": True,
            "concurrent_poll_creates_one_observation": True, "grant_revocation_fenced_through_projection": True,
            "write_failure_rolls_back_history_and_projection": True,
            "acked_and_unknown_regressions_denied": True, "unknown_automatic_retries": 0,
            "admission_receipt_dispatch": "DISABLED", "native_result": "PREPARED",
            "commercial_lead_mutations": 0, "crm_technical_projection_written": True,
            "crm_recovery": recovery, "host_published_ports": 0, "ai_api_calls": 0,
            "real_owner_iam": False, "real_signer_custody": False, "go_for_installation": False}


def main() -> dict:
    require(os.environ.get("GITHUB_ACTIONS") == "true" and
            os.environ.get("GITHUB_REPOSITORY") == "rafhael100/imagine-ai-agents" and
            os.environ.get("P24_RUNNER_ENVIRONMENT") == "github-hosted", "FEEDBACK_HOSTED_CI_ONLY")
    require(not os.environ.get("DOCKER_HOST") and not os.environ.get("DOCKER_CONTEXT"), "FEEDBACK_REMOTE_DOCKER_FORBIDDEN")
    require(run(["docker", "context", "show"], "FEEDBACK_DOCKER_CONTEXT").stdout.strip() == b"default",
            "FEEDBACK_DEFAULT_DOCKER_CONTEXT")
    context = json.loads(run(["docker", "context", "inspect", "default"], "FEEDBACK_DOCKER_CONTEXT").stdout)
    require(context[0]["Endpoints"]["docker"]["Host"] == "unix:///var/run/docker.sock", "FEEDBACK_LOCAL_DOCKER_SOCKET")
    run_id, attempt = os.environ.get("GITHUB_RUN_ID", ""), os.environ.get("GITHUB_RUN_ATTEMPT", "")
    require(re.fullmatch(r"[0-9]{1,20}", run_id) is not None and re.fullmatch(r"[0-9]{1,4}", attempt) is not None,
            "FEEDBACK_GITHUB_RUN_ID")
    source_sha = os.environ.get("AIA_CRM_FEEDBACK_SHA", "")
    source = Path(os.environ.get("AIA_CRM_FEEDBACK_SOURCE", ""))
    require(re.fullmatch(r"[a-f0-9]{40}", source_sha) is not None and source.is_absolute()
            and source.is_dir() and not source.is_symlink()
            and source.resolve() == ROOT / "_crm_feedback_pinned_source", "FEEDBACK_PINNED_CRM_ARCHIVE_REQUIRED")
    for relative in ("infra/homologation/schema.sql", "infra/homologation/20-runtime-roles.sh",
                     "infra/homologation/s2s/execution-feedback-candidate.sql",
                     "infra/homologation/s2s/execution-monitor-candidate.sql",
                     "app/Homologation/ExecutionMonitor.php", "app/Homologation/S2S/StrictJson.php",
                     "scripts/ops/private_crm_backup_contract.py",
                     "scripts/ops/private_crm_backup_catalog.py", "scripts/ops/private_crm_backup_snapshot.py"):
        require((source / relative).is_file() and not (source / relative).is_symlink(), "FEEDBACK_CRM_PUBLIC_SOURCE_REQUIRED")
    require(MONITOR_PROBE.is_file() and not MONITOR_PROBE.is_symlink(), "FEEDBACK_MONITOR_PROBE_REQUIRED")
    require(hashlib.sha256((ROOT / "runtime/central-auto/index.php").read_bytes()).hexdigest() == INDEX_SHA256,
            "FEEDBACK_EXISTING_INGRESS_PIN")
    pg_image = os.environ.get("N8N_PG_IMAGE", "")
    require(re.fullmatch(r"(?:docker.io/library/)?postgres@sha256:[a-f0-9]{64}", pg_image) is not None,
            "FEEDBACK_N8N_PG_DIGEST")
    os.umask(0o077)
    with tempfile.TemporaryDirectory(prefix="central-crm-feedback-", dir=os.environ["RUNNER_TEMP"]) as tmp:
        directory = Path(tmp)
        prefix = "imagine-feedback-" + run_id + "-" + attempt
        lab = FeedbackLab(prefix, directory / "agents-state")
        crm = CrmLab(prefix, directory / "crm-state", lab, source, source_sha)
        stack = Stack(prefix + "-native", directory / "native-secrets", pg_image)
        try:
            return exercise(lab, crm, stack, directory)
        finally:
            failures = []
            for resource in (crm, lab, stack):
                try:
                    resource.cleanup()
                except Exception:
                    failures.append("FEEDBACK_OWNED_CLEANUP")
            require(not failures, "FEEDBACK_OWNED_RESOURCE_CLEANUP_FAILED")


if __name__ == "__main__":
    try:
        print(json.dumps(main(), sort_keys=True))
        print("CENTRAL_AUTO_CRM_EXECUTION_FEEDBACK_PASS NO_GO", flush=True)
    except (Failure, ContractDenied, NativeDenied, ValueError, KeyError, TypeError, OSError,
            RuntimeError, subprocess.SubprocessError) as error:
        label = str(error)
        if re.fullmatch(r"[A-Z0-9_]{1,120}", label) is None:
            label = "UNTRUSTED_DIAGNOSTIC_SUPPRESSED"
        print("CENTRAL_AUTO_CRM_EXECUTION_FEEDBACK_DENIED_" + label, file=sys.stderr)
        raise SystemExit(1)
