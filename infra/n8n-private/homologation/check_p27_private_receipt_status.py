#!/usr/bin/env python3
"""P27: recover a P19 admission receipt through real, isolated HTTPS/mTLS.

All certificates, passwords, events and authorities are disposable CI fixtures.
There are no host ports, internet-capable runtime networks or n8n connections.
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
import tempfile
import time
from pathlib import Path
from urllib.parse import quote

from check_p24_full_n8n_restore import Failure, ROOT, require, run
from material_preflight import fingerprint
from p25_agents_recovery import AgentsRecovery

COMPANY_A = "aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa"
COMPANY_B = "bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb"
KEY_A = b"SYNTHETIC_OFFLINE_HMAC_TEST_KEY_A_32_BYTES_ONLY"
KEY_B = b"SYNTHETIC_OFFLINE_HMAC_TEST_KEY_B_32_BYTES_ONLY"
QUERY_DOMAIN = "IMAGINE_AGENTS_EVENT_RECEIPT_QUERY_V1"
RECEIPT_DOMAIN = "IMAGINE_AGENTS_EVENT_RECEIPT_V1"
QUERY_FIELDS = ("schema", "issuer", "audience", "product", "operation", "company_id",
                "tenant_ref", "event_ref", "payload_hash", "request_ref", "nonce_ref",
                "issued_at", "expires_at", "key_id")
RECEIPT_FIELDS = ("schema", "issuer", "audience", "company_id", "tenant_ref", "event_ref",
                  "payload_hash", "state", "dispatch", "processing", "request_ref", "nonce_ref",
                  "issued_at", "expires_at", "key_id")
INDEX_SHA256 = "ce41d2e071b4d17d370a92af55dc5ed7e55ba90d8d85a6192f257f7a5a601390"
DENIAL = b'{"error":"RECEIPT_DENIED"}'


def wire(value: dict) -> bytes:
    return json.dumps(value, separators=(",", ":"), ensure_ascii=True).encode("ascii")


def sign_query(query: dict, key: bytes = KEY_A) -> dict:
    value = {name: query[name] for name in QUERY_FIELDS}
    canonical = QUERY_DOMAIN + "\n" + "\n".join(str(value[name]) for name in QUERY_FIELDS)
    value["signature"] = hmac.new(key, canonical.encode(), hashlib.sha256).hexdigest()
    return value


def make_query(company: str, tenant: str, event: str, payload_hash: str, **changes) -> dict:
    now = int(time.time())
    suffix = secrets.token_hex(8).upper()
    value = dict(zip(QUERY_FIELDS, (
        "AUTO_IA_RECEIPT_QUERY_V1", "imagine-crm", "imagine-ai-agents", "imagine-crm",
        "event.receipt.read", company, tenant, event, payload_hash,
        "SYNTHETIC_P27_QUERY_" + suffix, "SYNTHETIC_P27_NONCE_" + suffix,
        now, now + 60, "SYNTHETIC_KEY_A",
    )))
    value.update(changes)
    return sign_query(value, KEY_B if value["key_id"] == "SYNTHETIC_KEY_B" else KEY_A)


def closed_json(data: bytes) -> dict:
    def pairs(items):
        value = {}
        for name, item in items:
            require(name not in value, "P27_RESPONSE_DUPLICATE_KEY")
            value[name] = item
        return value
    value = json.loads(data, object_pairs_hook=pairs)
    require(isinstance(value, dict), "P27_RESPONSE_NOT_OBJECT")
    return value


def verify_receipt(response: dict, query: dict, key: bytes) -> dict:
    require(response["transport_ok"] is True and response["status"] == 200 and
            response["cache_control"] == "no-store" and
            response["content_type"].split(";")[0] == "application/json", "P27_RECEIPT_HTTP")
    reply = closed_json(base64.b64decode(response["body_b64"], validate=True))
    require(set(reply) == set(RECEIPT_FIELDS) | {"signature"}, "P27_RECEIPT_CLOSED_FIELDS")
    for field in RECEIPT_FIELDS + ("signature",):
        require(type(reply[field]) is (int if field in ("issued_at", "expires_at") else str),
                "P27_RECEIPT_FIELD_TYPE")
    for name, expected in {
        "schema": "AUTO_IA_EVENT_RECEIPT_V1", "issuer": "imagine-ai-agents", "audience": "imagine-crm",
        "state": "RECEIVED", "dispatch": "DISABLED", "processing": "NOT_DISPATCHED",
        "key_id": "SYNTHETIC_P27_RECEIPT_KEY",
    }.items():
        require(reply[name] == expected, "P27_RECEIPT_STATE_OR_DOMAIN")
    for name in ("company_id", "tenant_ref", "event_ref", "payload_hash", "request_ref", "nonce_ref"):
        require(reply[name] == query[name], "P27_RECEIPT_QUERY_BINDING")
    now = int(time.time())
    require(now - 10 <= reply["issued_at"] <= now + 5 and
            now < reply["expires_at"] <= min(query["expires_at"], reply["issued_at"] + 30),
            "P27_RECEIPT_FRESHNESS")
    canonical = RECEIPT_DOMAIN + "\n" + "\n".join(str(reply[name]) for name in RECEIPT_FIELDS)
    expected_mac = hmac.new(key, canonical.encode(), hashlib.sha256).hexdigest()
    require(hmac.compare_digest(reply["signature"], expected_mac), "P27_RECEIPT_MAC")
    return reply


def verify_ack(response: dict, event: dict, key: bytes, decision: str) -> None:
    require(response["transport_ok"] is True and response["status"] == 200, "P27_ORIGINAL_ACK_HTTP")
    reply = closed_json(base64.b64decode(response["body_b64"], validate=True))
    fields = ("schema", "company_id", "event_ref", "payload_hash", "decision", "dispatch")
    require(set(reply) == set(fields) | {"signature"}, "P27_ORIGINAL_ACK_CLOSED")
    require(reply["schema"] == "AUTO_IA_ACK_V1" and reply["company_id"] == event["company_id"] and
            reply["event_ref"] == event["envelope"]["event_ref"] and
            reply["payload_hash"] == event["envelope"]["payload_hash"] and
            reply["decision"] == decision and reply["dispatch"] == "DISABLED", "P27_ORIGINAL_ACK_SEMANTICS")
    expected = hmac.new(key, "\n".join(reply[name] for name in fields).encode(), hashlib.sha256).hexdigest()
    require(hmac.compare_digest(reply["signature"], expected), "P27_ORIGINAL_ACK_MAC")


def make_event(company: str, tenant: str, event_id: str, key_id: str, key: bytes) -> dict:
    payload = wire({"schema": "CRM_LEAD_EVENT_V1", "company_id": company, "event_id": event_id,
                    "lead_id": "cccccccc-cccc-4ccc-8ccc-cccccccccccc", "action": "lead.created"})
    ref = "SYNTHETIC_" + event_id.replace("-", "").upper()
    now = int(time.time())
    envelope = {"schema": "AUTO_IA_S2S_V1", "issuer": "imagine-crm", "audience": "imagine-ai-agents",
                "product": "imagine-crm", "operation": "lead.ingest", "tenant_ref": tenant,
                "event_ref": ref, "nonce_ref": ref + "_N", "request_ref": ref + "_R",
                "issued_at": now, "expires_at": now + 290, "payload_hash": hashlib.sha256(payload).hexdigest(),
                "key_id": key_id}
    envelope["signature"] = hmac.new(key, "\n".join(str(v) for v in envelope.values()).encode(),
                                      hashlib.sha256).hexdigest()
    return {"company_id": company, "envelope": envelope, "payload_b64": base64.b64encode(payload).decode()}


class ReceiptLab(AgentsRecovery):
    def __init__(self, prefix: str, directory: Path):
        super().__init__(prefix, directory)
        self.nginx_image = os.environ.get("AGENTS_NGINX_IMAGE", "")
        require(re.fullmatch(r"(?:docker.io/library/)?nginx@sha256:[a-f0-9]{64}", self.nginx_image) is not None,
                "P27_NGINX_DIGEST_REQUIRED")
        self.extra: dict[str, str] = {}
        self.certificates: dict[str, tuple[Path, Path]] = {}
        self.secrets: dict[str, Path] = {}
        self.sequence = 0

    def private_file(self, name: str, contents: bytes) -> Path:
        path = self.directory / name
        with path.open("xb") as out:
            os.fchmod(out.fileno(), 0o444)
            out.write(contents)
        return path

    @staticmethod
    def mount(path: Path, target: str) -> list[str]:
        return ["--mount", "type=bind,src=" + str(path) + ",dst=" + target + ",readonly"]

    def _certificate(self, name: str, ca: str, server: bool = False) -> None:
        key, csr, cert, extension = (self.directory / (name + suffix) for suffix in (".key", ".csr", ".crt", ".ext"))
        run(["openssl", "req", "-new", "-newkey", "rsa:2048", "-nodes", "-keyout", str(key),
             "-out", str(csr), "-subj", "/CN=" + ("agents-ci.internal" if server else name)], "P27_CERT_REQUEST")
        extension.write_text("basicConstraints=critical,CA:FALSE\nkeyUsage=critical,digitalSignature,keyEncipherment\n"
                             + ("extendedKeyUsage=serverAuth\nsubjectAltName=DNS:agents-ci.internal,IP:127.0.0.1\n"
                                if server else "extendedKeyUsage=clientAuth\n"))
        run(["openssl", "x509", "-req", "-in", str(csr), "-CA", str(self.directory / (ca + ".crt")),
             "-CAkey", str(self.directory / (ca + ".key")), "-set_serial", str(secrets.randbits(120) + 1),
             "-days", "2", "-sha256", "-extfile", str(extension), "-out", str(cert)], "P27_SIGN_CERT")
        cert.chmod(0o444); key.chmod(0o444)
        self.certificates[name] = (cert, key)

    def prepare(self) -> None:
        self._start("src")
        # Official image init defaults must not turn the shared loopback into a
        # password-free database path for the TLS client. Change only our fresh
        # job-owned cluster; SQL fixture administration retains its local socket.
        run(["docker", "exec", "-i", self.containers["src"], "sh", "-c",
             "cat > /var/lib/postgresql/data/pg_hba.conf"], "P27_REQUIRE_TCP_PASSWORD",
            stdin=b"local all postgres trust\nlocal all all reject\nhost all all 127.0.0.1/32 scram-sha-256\nhost all all ::1/128 scram-sha-256\n")
        require(self._sql("src", "SELECT pg_reload_conf()") == "t", "P27_RELOAD_AUTH")
        for ca in ("ca", "foreign-ca"):
            run(["openssl", "req", "-x509", "-newkey", "rsa:2048", "-nodes", "-days", "2", "-sha256",
                 "-subj", "/CN=P27-" + ca, "-addext", "basicConstraints=critical,CA:TRUE",
                 "-addext", "keyUsage=critical,keyCertSign,cRLSign",
                 "-keyout", str(self.directory / (ca + ".key")),
                 "-out", str(self.directory / (ca + ".crt"))], "P27_CREATE_CA")
            (self.directory / (ca + ".key")).chmod(0o600)
            (self.directory / (ca + ".crt")).chmod(0o444)
        self._certificate("agents", "ca", server=True)
        for name in ("client-a", "client-b", "client-unknown"):
            self._certificate(name, "ca")
        self._certificate("client-foreign", "foreign-ca")
        for name in ("transport-password", "receipt-password", "ack-key", "receipt-key"):
            self.secrets[name] = self.private_file(name, secrets.token_hex(32).encode())
        for source in ("tools/auto_ia/pg/001_auto_ia_pg_lab.sql", "tools/auto_ia/pg/002_no_ai_inbox.sql",
                       "tools/auto_ia/pg/003_signed_ingress_lab.sql", "runtime/central-auto/receiver-grants.sql",
                       "runtime/central-auto/receipt-status.sql"):
            self._sql("src", (ROOT / source).read_text())
        seeded = self.php("seed", {"fingerprint_a": fingerprint(self.certificates["client-a"][0]),
                                  "fingerprint_b": fingerprint(self.certificates["client-b"][0]),
                                  "transport_password": self.secrets["transport-password"].read_text(),
                                  "receipt_password": self.secrets["receipt-password"].read_text()})
        require(seeded.get("schema") == "P27_SEEDED_V1", "P27_SEED")
        require(self.php("verify-permissions")["execute_only"] is True, "P27_SQL_PERMISSIONS")
        require(self.php("unauthenticated-db")["db_password_required"] is True, "P27_DB_AUTHENTICATION")

    def base_args(self) -> list[str]:
        return ["--network", "container:" + self.containers["src"], "--read-only", "--cap-drop", "ALL",
                "--security-opt", "no-new-privileges:true", "--memory", "256m", "--cpus", "1", "--pids-limit", "64",
                "--tmpfs", "/tmp:rw,noexec,nosuid,size=16m"]

    def remove_owned(self, name: str) -> None:
        identity = self.extra.get(name, name)
        result = run(["docker", "container", "inspect", identity], "P27_CLEANUP_INSPECT", check=False)
        if result.returncode == 0:
            data = json.loads(result.stdout)[0]
            require((data["Config"].get("Labels") or {}).get("imagine.p25.owner") == self.token,
                    "P27_CLEANUP_FOREIGN_RESOURCE")
            run(["docker", "rm", "-f", data["Id"]], "P27_CLEANUP_OWNED")
        self.extra.pop(name, None)

    def php(self, mode: str, payload: dict | None = None, client: str | None = None) -> dict:
        self.sequence += 1
        name = self.project + "-probe-" + str(self.sequence)
        require(run(["docker", "container", "inspect", name], "P27_PROBE_NAME", check=False).returncode != 0,
                "P27_PREEXISTING_PROBE")
        args = ["docker", "run", "--rm", "-i", "--name", name, "--label", "imagine.p25.owner=" + self.token]
        args += self.base_args() + self.mount(ROOT, "/repo")
        args += ["-e", "GITHUB_ACTIONS=true", "-e", "GITHUB_REPOSITORY=rafhael100/imagine-ai-agents"]
        if mode in ("seed", "stats", "verify-permissions"):
            args += self.mount(self.passwords["src"], "/run/secrets/p25-password")
            args += ["-e", "DB_HOST=127.0.0.1", "-e", "DB_DATABASE=imagine_agents_ci", "-e", "DB_USERNAME=postgres"]
        if mode == "http":
            args += self.mount(self.directory / "ca.crt", "/certs/ca.crt")
            if client is not None:
                cert, key = self.certificates[client]
                args += self.mount(cert, "/certs/client.crt") + self.mount(key, "/certs/client.key")
        args += ["--entrypoint", "php", self.php_image, "/repo/runtime/central-auto/p27-receipt-probe.php", mode]
        self.extra[name] = name
        result = run(args, "P27_PHP_" + mode.upper(), stdin=wire(payload or {}), timeout=30, check=False)
        self.remove_owned(name)
        if result.returncode:
            markers = re.findall(rb"^P27_PROBE_DENIED_[A-Z0-9_]+$", result.stderr, re.MULTILINE)
            raise Failure(markers[0].decode() if len(markers) == 1 else "P27_PHP_" + mode.upper() + "_FAILED")
        return closed_json(result.stdout)

    def service(self, kind: str, arguments: list[str]) -> None:
        name = self.project + "-" + kind
        require(run(["docker", "container", "inspect", name], "P27_SERVICE_NAME", check=False).returncode != 0,
                "P27_PREEXISTING_SERVICE")
        self.extra[name] = name
        result = run(["docker", "run", "-d", "--name", name, "--label", "imagine.p25.owner=" + self.token]
                     + self.base_args() + arguments, "P27_START_" + kind.upper())
        identity = result.stdout.decode().strip()
        require(re.fullmatch(r"[a-f0-9]{64}", identity) is not None, "P27_SERVICE_ID")
        self.extra[name] = identity
        data = json.loads(run(["docker", "inspect", identity], "P27_SERVICE_ISOLATION").stdout)[0]
        require(data["HostConfig"]["NetworkMode"] == "container:" + self.containers["src"] and
                not data["HostConfig"].get("PortBindings") and not data["HostConfig"]["Privileged"] and
                data["HostConfig"]["ReadonlyRootfs"], "P27_SERVICE_NETWORK_OR_STORAGE")

    def start_http(self) -> None:
        args = []
        env = {"AGENTS_PRIVATE_S2S_PROFILE": "synthetic-private-v1",
               "AGENTS_PRIVATE_DSN": "pgsql:host=127.0.0.1;port=5432;dbname=imagine_agents_ci",
               "AGENTS_PRIVATE_DB_PASSWORD_FILE": "/run/secrets/transport-password",
               "AGENTS_PRIVATE_ACK_KEY_FILE": "/run/secrets/ack-key",
               "AGENTS_PRIVATE_RECEIPT_DB_PASSWORD_FILE": "/run/secrets/receipt-password",
               "AGENTS_PRIVATE_RECEIPT_KEY_FILE": "/run/secrets/receipt-key",
               "AGENTS_PRIVATE_RECEIPT_KEY_ID": "SYNTHETIC_P27_RECEIPT_KEY"}
        for name, value in env.items():
            args += ["-e", name + "=" + value]
        for name, path in self.secrets.items():
            args += self.mount(path, "/run/secrets/" + name)
        self.service("fpm", args + [self.php_image])
        config = (ROOT / "runtime/central-auto/nginx.conf").read_text()
        require(config.count("fastcgi_pass agents-app:9000;") == 2, "P27_EXPECTED_UPSTREAMS")
        config = config.replace("fastcgi_pass agents-app:9000;", "fastcgi_pass 127.0.0.1:9000;")
        nginx = self.private_file("nginx-ci.conf", config.encode())
        args = ["--user", "101:101", "--tmpfs", "/var/cache/nginx:rw,noexec,nosuid,size=16m,mode=1777",
                "--tmpfs", "/var/run:rw,noexec,nosuid,size=4m,mode=1777"]
        args += self.mount(nginx, "/etc/nginx/conf.d/default.conf")
        args += self.mount(self.directory / "ca.crt", "/certs/ca.crt")
        args += self.mount(self.certificates["agents"][0], "/certs/agents.crt")
        args += self.mount(self.certificates["agents"][1], "/certs/agents.key")
        self.service("nginx", args + ["--entrypoint", "nginx", self.nginx_image, "-g", "daemon off;"])
        for _ in range(20):
            ready = self.php("ready")
            if ready == {"fpm": True, "https": True}:
                break
            time.sleep(0.5)
        else:
            raise Failure("P27_HTTP_STACK_NOT_READY")
        require(self.php("egress")["namespace_egress_denied"] is True, "P27_EGRESS")

    def http(self, body: dict | bytes, *, client: str | None = "client-a",
             path: str = "/private/events/receipt", method: str = "POST",
             headers: list[str] | None = None, content_type: str = "application/json") -> dict:
        raw = wire(body) if isinstance(body, dict) else body
        return self.php("http", {"path": path, "method": method, "body_b64": base64.b64encode(raw).decode(),
                                  "headers": headers or [], "content_type": content_type,
                                  "client_certificate": client is not None}, client)

    def deny(self, query: dict | bytes, **options) -> None:
        reply = self.http(query, **options)
        require(reply["status"] == 403 and base64.b64decode(reply["body_b64"], validate=True) == DENIAL and
                reply["cache_control"] == "no-store", "P27_NONUNIFORM_DENIAL")

    def locked_timeout(self, query: dict) -> None:
        args = ["docker", "exec", "-i", self.containers["src"], "psql", "-X", "-qAt", "-v", "ON_ERROR_STOP=1",
                "-U", "postgres", "-d", "imagine_agents_ci", "-c",
                "BEGIN; LOCK TABLE auto_ia_lab.receipt_read_grants IN ACCESS EXCLUSIVE MODE; SELECT pg_sleep(8); ROLLBACK;"]
        process = subprocess.Popen(args, stdout=subprocess.PIPE, stderr=subprocess.PIPE)
        try:
            for _ in range(20):
                held = self._sql("src", "SELECT count(*) FROM pg_locks WHERE relation='auto_ia_lab.receipt_read_grants'::regclass AND mode='AccessExclusiveLock' AND granted")
                if held == "1":
                    break
                time.sleep(0.1)
            else:
                raise Failure("P27_LOCK_FIXTURE_NOT_READY")
            reply = self.http(query)
            require(reply["status"] == 503 and base64.b64decode(reply["body_b64"], validate=True) == DENIAL,
                    "P27_LOCK_TIMEOUT_DID_NOT_FAIL_CLOSED")
            process.communicate(timeout=12)
            require(process.returncode == 0, "P27_LOCK_FIXTURE_FAILED")
        finally:
            if process.poll() is None:
                process.kill()
                process.communicate(timeout=5)

    def cleanup(self) -> None:
        failures = []
        for name in tuple(reversed(self.extra)):
            try:
                self.remove_owned(name)
            except (Failure, OSError, ValueError, subprocess.SubprocessError):
                failures.append(name)
        try:
            super().cleanup()
        except (Failure, OSError, ValueError, subprocess.SubprocessError):
            failures.append("PG")
        require(not failures, "P27_OWNED_CLEANUP_FAILED")


def exercise(lab: ReceiptLab) -> dict:
    lab.prepare(); lab.start_http()
    receipt_key = lab.secrets["receipt-key"].read_bytes()
    ack_key = lab.secrets["ack-key"].read_bytes()
    event_a = make_event(COMPANY_A, "SYNTHETIC_TENANT_A", "11111111-1111-4111-8111-111111111111", "SYNTHETIC_KEY_A", KEY_A)
    event_b = make_event(COMPANY_B, "SYNTHETIC_TENANT_B", "22222222-2222-4222-8222-222222222222", "SYNTHETIC_KEY_B", KEY_B)
    for event, client in ((event_a, "client-a"), (event_b, "client-b")):
        verify_ack(lab.http(event, client=client, path="/private/events"), event, ack_key, "LAB_RECEIVED_NO_DELIVERY")
    # The sender has lost the original ACK. No event is posted again to obtain
    # the following receipt; only this new query route is used.
    def query_a(**changes):
        query = make_query(COMPANY_A, "SYNTHETIC_TENANT_A", event_a["envelope"]["event_ref"],
                           event_a["envelope"]["payload_hash"])
        query.update(changes)
        return sign_query(query, KEY_B if query["key_id"] == "SYNTHETIC_KEY_B" else KEY_A)
    before = lab.php("stats")
    require(before == {"inbox": 2, "ingress_audit": 2, "ai_requests": 0}, "P27_INITIAL_ADMISSIONS")
    lab.deny(query_a())  # lead.ingest by itself confers no read permission.
    fp = fingerprint(lab.certificates["client-a"][0])
    lab._sql("src", "INSERT INTO auto_ia_lab.receipt_read_grants VALUES('" + COMPANY_A + "','" + fp +
             "','SYNTHETIC_TENANT_A','event.receipt.read',true,1," + str(int(time.time()) + 1800) + ")")
    query = query_a(); reply = lab.http(query)
    verify_receipt(reply, query, receipt_key)
    verify_receipt(lab.http(query), query, receipt_key)  # Idempotent read retry.
    next_query = query_a()
    verify_receipt(lab.http(next_query), next_query, receipt_key)
    try:
        verify_receipt(reply, next_query, receipt_key)
    except Failure:
        pass
    else:
        raise Failure("P27_OLD_RESPONSE_ACCEPTED_FOR_NEW_QUERY")
    require(lab.php("stats") == before, "P27_RECEIPT_QUERY_REDELIVERED_EVENT")
    verify_ack(lab.http(event_a, path="/private/events"), event_a, ack_key, "LAB_DUPLICATE_NO_DELIVERY")
    before = lab.php("stats")
    require(before == {"inbox": 2, "ingress_audit": 3, "ai_requests": 0}, "P27_ORIGINAL_DUPLICATE")
    query_b = make_query(COMPANY_B, "SYNTHETIC_TENANT_B", event_b["envelope"]["event_ref"],
                         event_b["envelope"]["payload_hash"], key_id="SYNTHETIC_KEY_B")
    verify_receipt(lab.http(query_b, client="client-b"), query_b, receipt_key)

    # All these requests reach the same handler and have the same minimal denial.
    negatives = [query_a(event_ref="SYNTHETIC_P27_MISSING"), query_a(payload_hash="0" * 64),
                 query_a(company_id=COMPANY_B), query_a(tenant_ref="SYNTHETIC_TENANT_B"),
                 query_a(issued_at=int(time.time()) - 50, expires_at=int(time.time()) - 5),
                 query_a(issued_at=int(time.time()) + 60, expires_at=int(time.time()) + 90),
                 query_a(expires_at=int(time.time()) + 90), query_a(operation="lead.ingest")]
    wrong_mac = query_a(); wrong_mac["signature"] = "0" * 64; negatives.append(wrong_mac)
    wrong_domain = query_a()
    wrong_domain["signature"] = hmac.new(KEY_A, "\n".join(str(wrong_domain[n]) for n in QUERY_FIELDS).encode(), hashlib.sha256).hexdigest()
    negatives.append(wrong_domain)
    for query in negatives:
        lab.deny(query)
    lab.deny(query_a(), client="client-b")
    lab.deny(query_a(), client="client-unknown")
    lab.deny(query_a(), client="client-unknown", headers=["IMAGINE_MTLS_VERIFY: SUCCESS",
             "IMAGINE_MTLS_CERT: " + quote(lab.certificates["client-a"][0].read_text(), safe="")])
    lab.deny(make_query(COMPANY_B, "SYNTHETIC_TENANT_B", event_a["envelope"]["event_ref"],
                        event_a["envelope"]["payload_hash"], key_id="SYNTHETIC_KEY_B"), client="client-b")
    # Duplicate decoded names, malformed integers/arrays and unknown fields must
    # be refused before PostgreSQL jsonb can normalize their representation.
    valid = wire(query_a())
    malformed = [b'{"schema":"AUTO_IA_RECEIPT_QUERY_V1",' + valid[1:],
                 b'{"\\u0073chema":"AUTO_IA_RECEIPT_QUERY_V1",' + valid[1:],
                 valid[:-1] + b',"unexpected":1}', valid[:-1], b'[]', b'{}',
                 valid.replace(b'"issued_at":', b'"issued_at":true,"ignored":', 1),
                 b' ' * 4097]
    for field_value in ("numeric-string", 1.5, True, None, [], {}):
        malformed_query = query_a()
        # The numeric-string case keeps an otherwise VALID MAC and current
        # window. Only the closed type contract prevents its admission.
        malformed_query["issued_at"] = str(malformed_query["issued_at"]) if field_value == "numeric-string" else field_value
        malformed.append(wire(malformed_query))
    for raw in malformed:
        lab.deny(raw)
    for options in ({"method": "GET"}, {"path": "/private/events/receipt?x=1"},
                    {"content_type": "text/plain"}, {"headers": ["Cookie: SYNTHETIC=1"]},
                    {"headers": ["Authorization: Bearer SYNTHETIC"]}):
        lab.deny(query_a(), **options)
    for client in (None, "client-foreign"):
        blocked = lab.http(query_a(), client=client,
                           headers=["IMAGINE_MTLS_VERIFY: SUCCESS", "IMAGINE_MTLS_CERT: SYNTHETIC"])
        require(blocked["status"] != 200 and b"AUTO_IA_EVENT_RECEIPT_V1" not in
                base64.b64decode(blocked["body_b64"], validate=True), "P27_MTLS_SPOOF_ACCEPTED")

    # Reuse the exact signed query across each immediate revocation. No cached
    # answer, duplicate bypass or historical admission can retain authority.
    for table, scope in (("receipt_read_grants", "company_id='" + COMPANY_A + "'"),
                         ("service_company_grants", "company_id='" + COMPANY_A + "'"),
                         ("company_links", "company_id='" + COMPANY_A + "'")):
        query = query_a(); verify_receipt(lab.http(query), query, receipt_key)
        lab._sql("src", "UPDATE auto_ia_lab." + table + " SET active=false,revision=revision+1 WHERE " + scope)
        lab.deny(query)
        lab._sql("src", "UPDATE auto_ia_lab." + table + " SET active=true,revision=revision+1 WHERE " + scope)
    query = query_a(); verify_receipt(lab.http(query), query, receipt_key)
    lab._sql("src", "UPDATE auto_ia_lab.mock_s2s_keys SET enabled=false WHERE key_id='SYNTHETIC_KEY_A'")
    lab.deny(query)
    lab._sql("src", "UPDATE auto_ia_lab.mock_s2s_keys SET enabled=true WHERE key_id='SYNTHETIC_KEY_A'")
    for table in ("receipt_read_grants", "service_company_grants"):
        lab._sql("src", "UPDATE auto_ia_lab." + table + " SET expires_at=1 WHERE company_id='" + COMPANY_A + "'")
        lab.deny(query_a())
        lab._sql("src", "UPDATE auto_ia_lab." + table + " SET expires_at=" + str(int(time.time()) + 1800) + " WHERE company_id='" + COMPANY_A + "'")
    lab.locked_timeout(query_a())
    final_query = query_a(); verify_receipt(lab.http(final_query), final_query, receipt_key)
    require(lab.php("stats") == before, "P27_READ_OR_DENIAL_CHANGED_EVENT_STATE")
    require(lab.php("verify-permissions")["roles_separate"] is True, "P27_PERMISSIONS_CHANGED")
    return {"schema": "P27_PRIVATE_RECEIPT_STATUS_VERIFIED_V1", "http_mtls_verified": True,
            "receipt_recovered_without_event_resend": True, "read_grant_separate_from_ingress": True,
            "current_grant_link_key_rechecked": True, "denials_uniform_for_missing_scope_hash_revocation": True,
            "response_bound_to_query_nonce_hash": True, "request_and_response_domains_separate": True,
            "duplicate_json_keys_rejected": True, "lock_timeout_failed_closed": True,
            "receipt_role_nologin_execute_only": True, "receipt_owner_acl_rls_verified": True,
            "existing_p19_ack_unchanged": True, "existing_p19_ack_source_sha256": INDEX_SHA256,
            "event_inbox_rows": before["inbox"], "host_published_ports": 0, "network": "none",
            "dispatch": "DISABLED", "processing": "NOT_DISPATCHED", "ai_calls": 0,
            "service_effects": 0, "p26_execution_claimed_for_p19": False, "real_authority_verified": False,
            "external_backup_verified": False, "go_for_installation": False}


def main(*, expected_ingress_sha256: str = INDEX_SHA256, after_receipt_checks=None) -> dict:
    require(os.environ.get("GITHUB_ACTIONS") == "true" and
            os.environ.get("GITHUB_REPOSITORY") == "rafhael100/imagine-ai-agents" and
            os.environ.get("P24_RUNNER_ENVIRONMENT") == "github-hosted" and
            not os.environ.get("DOCKER_HOST") and not os.environ.get("DOCKER_CONTEXT"),
            "P27_HOSTED_CI_LOCAL_DOCKER_ONLY")
    # An explicit reviewed entry point can reuse this matrix for later ingress
    # changes. Standalone P27 continues to require its original source pin.
    require(re.fullmatch(r"[a-f0-9]{64}", expected_ingress_sha256) is not None, "P27_INGRESS_PIN_FORMAT")
    require(hashlib.sha256((ROOT / "runtime/central-auto/index.php").read_bytes()).hexdigest() == expected_ingress_sha256,
            "P27_EXISTING_INGRESS_CHANGED")
    require(run(["docker", "context", "show"], "P27_DOCKER_CONTEXT").stdout.strip() == b"default",
            "P27_LOCAL_DOCKER_CONTEXT_REQUIRED")
    context = json.loads(run(["docker", "context", "inspect", "default"], "P27_DOCKER_ENDPOINT").stdout)
    require(context[0]["Endpoints"]["docker"]["Host"] == "unix:///var/run/docker.sock",
            "P27_LOCAL_DOCKER_SOCKET_REQUIRED")
    old_umask = os.umask(0o077)
    try:
        with tempfile.TemporaryDirectory(prefix="aia-p27-", dir=os.environ.get("RUNNER_TEMP")) as work:
            lab = ReceiptLab("p27-" + secrets.token_hex(6), Path(work) / "receipt-lab")
            try:
                proof = exercise(lab)
                proof["existing_p19_ack_source_sha256"] = expected_ingress_sha256
                if after_receipt_checks is not None:
                    proof["additional_ingress_boundaries"] = after_receipt_checks(lab)
                return proof
            finally:
                lab.cleanup()
    finally:
        os.umask(old_umask)


if __name__ == "__main__":
    try:
        print(json.dumps(main(), sort_keys=True))
        print("CENTRAL_AUTO_P27_PRIVATE_RECEIPT_STATUS_PASS NO_GO")
    except (Failure, OSError, ValueError, KeyError, subprocess.SubprocessError) as error:
        print("CENTRAL_AUTO_P27_DENIED_" + (str(error) if isinstance(error, Failure) else type(error).__name__),
              file=__import__("sys").stderr)
        raise SystemExit(1)
