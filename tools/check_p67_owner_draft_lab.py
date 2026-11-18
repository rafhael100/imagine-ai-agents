#!/usr/bin/env python3
"""P67: real PG16 and HTTPS/mTLS, disposable service attestations only.

This does not authenticate a CRM human, execute a rule, or create a CRM event.
All private material is generated in the job, kept out of logs, then removed.
"""
from __future__ import annotations

import base64
import copy
import hashlib
import hmac
import json
import os
from pathlib import Path
import re
import secrets
import subprocess
import sys
import tempfile
import time
import uuid

ROOT = Path(__file__).resolve().parents[1]
BASE = "0de0d18b13138e35bf6cb427cc1b4ba5a408cda4"
CENTRAL = "c08688b16e61d8d1ecc4d8c0ed4da8758a687ee8"
REPOSITORY = "rafhael100/imagine-ai-agents"
RUNTIME = ROOT / "runtime/central-auto/crm-owner-drafts"
COMPANY_A = "aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa"
COMPANY_B = "bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb"
REQUEST_DOMAIN = b"IMAGINE_CRM_OWNER_DRAFT_REQUEST_V1\n"
RESPONSE_DOMAIN = b"IMAGINE_CRM_OWNER_DRAFT_RESPONSE_V1\n"
DENIAL = b'{"error":"OWNER_DRAFT_DENIED"}'
UUID = r"[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}"
HASH = r"[a-f0-9]{64}"
REPLY_FIELDS = {
    "schema", "issuer", "audience", "operation", "company_id", "actor_id",
    "draft_ref", "business_hash", "nonce", "status", "business", "executed",
    "operational", "issued_at", "expires_at", "key_id", "mac",
}


class Denied(RuntimeError):
    pass


def need(value, code):
    if not value:
        raise Denied("P67_" + code)


def command(args, label, *, data=None, timeout=120, check=True, cwd=None):
    result = subprocess.run(args, input=data, capture_output=True, timeout=timeout, cwd=cwd)
    need(not check or result.returncode == 0, label)
    return result


def canonical(value):
    return json.dumps(value, sort_keys=True, separators=(",", ":"), ensure_ascii=False,
                      allow_nan=False).encode("utf-8")


def closed_json(raw):
    def pairs(items):
        result = {}
        for name, value in items:
            need(name not in result, "DUPLICATE_JSON_FIELD")
            result[name] = value
        return result
    value = json.loads(raw, object_pairs_hook=pairs,
                       parse_constant=lambda _: (_ for _ in ()).throw(Denied("P67_JSON_NUMBER")))
    need(isinstance(value, dict), "JSON_OBJECT_REQUIRED")
    return value


def source_preflight():
    """Validate the actual PR and every exported Git blob before loading helpers."""
    need(os.environ.get("GITHUB_ACTIONS") == "true"
         and os.environ.get("GITHUB_REPOSITORY") == REPOSITORY
         and os.environ.get("GITHUB_EVENT_NAME") == "pull_request"
         and os.environ.get("P24_RUNNER_ENVIRONMENT") == "github-hosted", "ACTUAL_HOSTED_PR_REQUIRED")
    need(re.fullmatch(r"[a-f0-9]{40}", os.environ.get("GITHUB_SHA", "")), "EVENT_MERGE_SHA")
    need(not os.environ.get("DOCKER_HOST") and not os.environ.get("DOCKER_CONTEXT"), "LOCAL_DOCKER_ONLY")
    event_file = Path(os.environ.get("GITHUB_EVENT_PATH", ""))
    need(event_file.is_file() and not event_file.is_symlink()
         and event_file.stat().st_size <= 1048576, "ACTUAL_EVENT_FILE")
    event = closed_json(event_file.read_bytes())
    request = event["pull_request"]
    source = Path(os.environ.get("AIA_P67_SOURCE_CHECKOUT", ""))
    need(source.is_absolute() and source.is_dir() and not source.is_symlink()
         and source.resolve() != ROOT, "SOURCE_CHECKOUT")

    def git(*args):
        return command(["git", *args], "SOURCE_GIT", cwd=source).stdout

    head = request["head"]["sha"]
    need(event["repository"]["full_name"] == REPOSITORY
         and event["repository"]["private"] is False
         and request["head"]["repo"]["full_name"] == REPOSITORY
         and request["base"]["repo"]["full_name"] == REPOSITORY
         and request["head"]["ref"] == "feat/aia-p67-crm-owner-drafts-2026-11-17"
         and request["base"]["ref"] == "feat/aia-p66-imagine-operator-ui-2026-11-17"
         and request["base"]["sha"] == BASE
         and re.fullmatch(r"[a-f0-9]{40}", head)
         and head == os.environ.get("AIA_P67_SOURCE_SHA")
         and git("rev-parse", "HEAD").decode().strip() == head, "PR_PROVENANCE")
    git("diff", "--quiet")
    git("diff", "--cached", "--quiet")
    git("merge-base", "--is-ancestor", BASE, head)
    tree = git("merge-tree", "--write-tree", "--name-only", CENTRAL, head).splitlines()[0].decode()
    need(re.fullmatch(r"[a-f0-9]{40}", tree)
         and tree == os.environ.get("AIA_P67_SOURCE_TREE"), "COMBINED_TREE")
    temporary = Path(os.environ.get("RUNNER_TEMP", ""))
    need(temporary.is_absolute() and temporary.is_dir() and not temporary.is_symlink()
         and ROOT.is_relative_to(temporary.resolve())
         and ROOT.name.startswith("aia-p67-tree-")
         and str(ROOT) == os.environ.get("AIA_P67_SOURCE_DIR"), "EXACT_EXPORTED_DIRECTORY")
    for row in git("ls-tree", "-rz", "-r", tree).split(b"\0"):
        if not row:
            continue
        metadata, raw_name = row.split(b"\t", 1)
        mode, kind, digest = metadata.split()
        name = Path(os.fsdecode(raw_name))
        need(kind == b"blob" and mode in (b"100644", b"100755")
             and not name.is_absolute() and ".." not in name.parts, "EXPORT_ENTRY")
        path = ROOT / name
        need(path.is_file() and not path.is_symlink() and path.resolve().is_relative_to(ROOT),
             "EXPORT_FILE")
        body = path.read_bytes()
        actual = hashlib.sha1(b"blob " + str(len(body)).encode() + b"\0" + body).hexdigest()
        need(actual == digest.decode() and bool(path.stat().st_mode & 0o111) == (mode == b"100755"),
             "EXPORT_BLOB")
    need(re.fullmatch(r"sha256:[a-f0-9]{64}", os.environ.get("AIA_P67_PHP_IMAGE", ""))
         and os.environ.get("AGENTS_PHP_IMAGE") == os.environ.get("AIA_P67_PHP_IMAGE"),
         "ACTUAL_P67_PHP_IMAGE")
    print(json.dumps({"check": "P67_LAB_SOURCE", "head": head, "tree": tree,
                      "event_merge_sha": os.environ["GITHUB_SHA"]}), flush=True)
    return temporary.resolve()


def make_business(company=COMPANY_A, **changes):
    created = int(time.time()) - 60
    value = {
        "schema": "CRM_AIA_OWNER_RULE_DRAFT_V1", "company_id": company,
        "draft_ref": str(uuid.uuid4()), "creator_id": str(uuid.uuid4()),
        "source_run_id": str(uuid.uuid4()), "rule_id": "followup_qualified",
        "settings_version": 0, "lead_id": str(uuid.uuid4()), "lead_version": 1,
        "source_created_at": created, "command_hash": hashlib.sha256(secrets.token_bytes(32)).hexdigest(),
        "projection": {"action": "task.followup.create", "stage": "qualified",
                       "delay_seconds": 90, "due_at": created + 90},
    }
    value.update(changes)
    return value


def validate_business(value):
    need(isinstance(value, dict) and set(value) == {
        "schema", "company_id", "draft_ref", "creator_id", "source_run_id", "rule_id",
        "settings_version", "lead_id", "lead_version", "source_created_at", "command_hash", "projection",
    }, "REPLY_BUSINESS_FIELDS")
    need(value["schema"] == "CRM_AIA_OWNER_RULE_DRAFT_V1"
         and value["rule_id"] == "followup_qualified", "REPLY_BUSINESS_DOMAIN")
    for field in ("company_id", "draft_ref", "creator_id", "source_run_id", "lead_id"):
        need(type(value[field]) is str and re.fullmatch(UUID, value[field]), "REPLY_BUSINESS_UUID")
    need(type(value["command_hash"]) is str and re.fullmatch(HASH, value["command_hash"]),
         "REPLY_COMMAND_HASH")
    for field, low, high in (("settings_version", 0, 20), ("lead_version", 1, 2147483647),
                             ("source_created_at", 1, 4102444800)):
        need(type(value[field]) is int and low <= value[field] <= high, "REPLY_BUSINESS_INTEGER")
    projection = value["projection"]
    need(isinstance(projection, dict)
         and set(projection) == {"action", "stage", "delay_seconds", "due_at"}
         and projection["action"] == "task.followup.create" and projection["stage"] == "qualified",
         "REPLY_PROJECTION")
    need(type(projection["delay_seconds"]) is int and 0 <= projection["delay_seconds"] <= 86400
         and type(projection["due_at"]) is int
         and projection["due_at"] == value["source_created_at"] + projection["delay_seconds"],
         "REPLY_DUE_AT")


def verify_reply(response, request, key, *, status="DRAFT"):
    need(response["transport_ok"] is True and response["status"] == 200
         and response["cache_control"] == "no-store"
         and response["content_type"].split(";")[0] == "application/json", "REPLY_HTTP")
    reply = closed_json(base64.b64decode(response["body_b64"], validate=True))
    need(set(reply) == REPLY_FIELDS, "REPLY_CLOSED_FIELDS")
    auth = request["authorization"]
    for field in REPLY_FIELDS - {"business", "issued_at", "expires_at", "executed", "operational"}:
        need(type(reply[field]) is str, "REPLY_STRING_TYPE")
    need(reply["schema"] == "CRM_AIA_OWNER_DRAFT_RESPONSE_V1"
         and reply["issuer"] == "imagine-ai-agents" and reply["audience"] == "imagine-crm"
         and reply["executed"] is False and reply["operational"] is False
         and reply["status"] == status, "REPLY_DOMAIN_OR_STATE")
    for field in ("operation", "company_id", "actor_id", "draft_ref", "business_hash", "nonce", "key_id"):
        need(reply[field] == auth[field], "REPLY_ATTEMPT_BINDING")
    now = int(time.time())
    need(type(reply["issued_at"]) is int and type(reply["expires_at"]) is int
         and now - 30 <= reply["issued_at"] <= now + 5
         and now < reply["expires_at"] <= min(auth["expires_at"], reply["issued_at"] + 15)
         and reply["issued_at"] < reply["expires_at"], "REPLY_CURRENT_DEADLINE")
    if status == "DRAFT":
        validate_business(reply["business"])
        need(reply["business"]["company_id"] == auth["company_id"]
             and reply["business"]["draft_ref"] == auth["draft_ref"]
             and hashlib.sha256(canonical(reply["business"])).hexdigest() == auth["business_hash"],
             "REPLY_BUSINESS_BINDING")
        if auth["operation"] == "crm.rule.draft.create":
            need(reply["business"]["creator_id"] == auth["actor_id"]
                 and reply["business"] == request["business"], "REPLY_AUTHORSHIP")
    else:
        need(status == "ABSENT" and auth["operation"] == "crm.rule.draft.read"
             and reply["business"] is None, "REPLY_ABSENT")
    unsigned = {name: value for name, value in reply.items() if name != "mac"}
    expected = hmac.new(key, RESPONSE_DOMAIN + canonical(unsigned), hashlib.sha256).hexdigest()
    need(re.fullmatch(HASH, reply["mac"]) and hmac.compare_digest(reply["mac"], expected), "REPLY_MAC")
    return reply


def create_lab(prefix, directory):
    # Only lifecycle/certificate helpers are reused. No legacy main or old guard
    # is called with a fabricated event, ref, key, or environment.
    sys.path.insert(0, str(ROOT / "infra/n8n-private/homologation"))
    from check_p27_private_receipt_status import ReceiptLab
    from material_preflight import fingerprint

    class OwnerDraftLab(ReceiptLab):
        def __init__(self):
            super().__init__(prefix, directory)
            self.keys = {}
            self.fingerprints = {}
            self.positive = 0
            self.negative = 0
            self.races = 0

        def prepare(self):
            self._start("src")
            command(["docker", "exec", "-i", self.containers["src"], "sh", "-c",
                     "cat > /var/lib/postgresql/data/pg_hba.conf"], "REQUIRE_TCP_PASSWORD",
                    data=b"local all postgres trust\nlocal all all reject\n"
                         b"host all all 127.0.0.1/32 scram-sha-256\n"
                         b"host all all ::1/128 scram-sha-256\n")
            need(self._sql("src", "SELECT pg_reload_conf()") == "t", "DATABASE_HBA_RELOAD")
            for ca in ("ca", "foreign-ca"):
                command(["openssl", "req", "-x509", "-newkey", "rsa:2048", "-nodes", "-days", "2", "-sha256",
                         "-subj", "/CN=P67-" + ca, "-addext", "basicConstraints=critical,CA:TRUE",
                         "-addext", "keyUsage=critical,keyCertSign,cRLSign",
                         "-keyout", str(self.directory / (ca + ".key")),
                         "-out", str(self.directory / (ca + ".crt"))], "CREATE_CA")
                (self.directory / (ca + ".key")).chmod(0o600)
                (self.directory / (ca + ".crt")).chmod(0o444)
            self._certificate("agents", "ca", server=True)
            for name in ("client-a", "client-b", "client-unknown"):
                self._certificate(name, "ca")
            self._certificate("client-foreign", "foreign-ca")
            fixture = {}
            for suffix in ("a", "b"):
                self.fingerprints[suffix] = fingerprint(self.certificates["client-" + suffix][0])
                fixture["fingerprint_" + suffix] = self.fingerprints[suffix]
                for operation in ("create", "read"):
                    secret = secrets.token_hex(32).encode("ascii")
                    self.keys["p67_" + operation + "_" + suffix] = secret
                    fixture[operation + "_" + suffix + "_key"] = secret.decode()
            for operation in ("create", "read"):
                password = secrets.token_hex(32).encode("ascii")
                self.secrets[operation] = self.private_file(operation + "-password", password)
                fixture[operation + "_password"] = password.decode()
            self._sql("src", (RUNTIME / "schema.sql").read_text())
            seeded = self.php("seed", fixture)
            need(seeded == {"schema": "P67_DISPOSABLE_SERVICE_FIXTURE_V1",
                            "human_crm_session_verified": False}, "DISPOSABLE_FIXTURE")
            need(self.php("permissions") == {"execute_only": True, "roles_separate": True,
                                            "direct_tables_denied": True, "forced_rls": True},
                 "DATABASE_PRIVILEGES")
            need(self.php("unauthenticated-db") == {"database_password_required": True},
                 "DATABASE_AUTHENTICATION")

        def php(self, mode, payload=None, client=None):
            self.sequence += 1
            name = self.project + "-probe-" + str(self.sequence)
            need(command(["docker", "container", "inspect", name], "NEW_PROBE_NAME",
                         check=False).returncode != 0, "PREEXISTING_PROBE")
            args = ["docker", "run", "--rm", "-i", "--name", name, "--label",
                    "imagine.p25.owner=" + self.token]
            args += self.base_args() + self.mount(ROOT, "/repo")
            args += ["-e", "GITHUB_ACTIONS=" + os.environ["GITHUB_ACTIONS"],
                     "-e", "GITHUB_REPOSITORY=" + os.environ["GITHUB_REPOSITORY"],
                     "-e", "AIA_P67_PROBE=isolated-p67-v1"]
            if mode in ("seed", "stats", "permissions"):
                args += self.mount(self.passwords["src"], "/run/secrets/p25-password")
            if mode == "http":
                args += self.mount(self.directory / "ca.crt", "/certs/ca.crt")
                if client is not None:
                    cert, key = self.certificates[client]
                    args += self.mount(cert, "/certs/client.crt") + self.mount(key, "/certs/client.key")
            args += ["--entrypoint", "php", self.php_image, "/repo/tools/p67-owner-draft-probe.php", mode]
            self.extra[name] = name
            result = command(args, "PROBE_" + mode.upper(), data=canonical(payload or {}),
                             timeout=30, check=False)
            self.remove_owned(name)
            if result.returncode:
                markers = re.findall(rb"^P67_PROBE_DENIED_[A-Z0-9_]+$", result.stderr, re.MULTILINE)
                raise Denied(markers[0].decode() if len(markers) == 1 else "P67_PROBE_FAILED")
            need(len(result.stdout) <= 40000, "PROBE_OUTPUT_LIMIT")
            return closed_json(result.stdout)

        def start_fpm(self, operation, *, enabled=True):
            args = self.mount(self.secrets[operation], "/run/secrets/owner-draft-db-password")
            args += self.mount(RUNTIME / (operation + "-fpm.conf"),
                               "/usr/local/etc/php-fpm.d/zz-owner-draft.conf")
            args += ["-e", "AGENTS_PRIVATE_CRM_DRAFT_DSN=pgsql:host=127.0.0.1;port=5432;dbname=imagine_agents_ci",
                     "-e", "AGENTS_PRIVATE_CRM_DRAFT_DB_PASSWORD_FILE=/run/secrets/owner-draft-db-password"]
            if enabled:
                args += ["-e", "AGENTS_PRIVATE_CRM_OWNER_DRAFTS=owner-rule-draft-v1"]
            self.service(operation, args + [self.php_image])

        def start_http(self):
            self.start_fpm("create")
            self.start_fpm("read")
            config = (RUNTIME / "nginx.conf").read_text()
            for operation, port in (("create", 9000), ("read", 9001)):
                expected = "fastcgi_pass agents-" + operation + ":" + str(port) + ";"
                need(config.count(expected) == 1, "EXACT_NGINX_UPSTREAMS")
                config = config.replace(expected, "fastcgi_pass 127.0.0.1:" + str(port) + ";")
            nginx = self.private_file("nginx-ci.conf", config.encode())
            args = ["--user", "101:101", "--tmpfs", "/var/cache/nginx:rw,noexec,nosuid,size=16m,mode=1777",
                    "--tmpfs", "/var/run:rw,noexec,nosuid,size=4m,mode=1777"]
            args += self.mount(nginx, "/etc/nginx/conf.d/default.conf")
            args += self.mount(self.directory / "ca.crt", "/certs/ca.crt")
            args += self.mount(self.certificates["agents"][0], "/certs/agents.crt")
            args += self.mount(self.certificates["agents"][1], "/certs/agents.key")
            self.service("nginx", args + ["--entrypoint", "nginx", self.nginx_image, "-g", "daemon off;"])
            self.wait_ready()
            need(self.php("egress") == {"namespace_egress_denied": True}, "NO_RUNTIME_EGRESS")
            for operation in ("create", "read"):
                identity = self.extra[self.project + "-" + operation]
                spec = closed_json(command(["docker", "inspect", "--format", "{{json .}}", identity],
                                           "FPM_MOUNT_BOUNDARY").stdout)
                mounts = spec["Mounts"]
                secret_mounts = [row for row in mounts if row["Destination"].startswith("/run/secrets/")]
                need(len(secret_mounts) == 1
                     and secret_mounts[0]["Source"] == str(self.secrets[operation])
                     and secret_mounts[0]["Destination"] == "/run/secrets/owner-draft-db-password"
                     and secret_mounts[0]["RW"] is False, "SEPARATE_FPM_PASSWORDS")

        def wait_ready(self):
            for _ in range(20):
                if self.php("ready") == {"create": True, "read": True, "https": True}:
                    return
                time.sleep(0.25)
            raise Denied("P67_HTTP_STACK_NOT_READY")

        def stats(self):
            result = self.php("stats")
            need(set(result) == {"drafts", "nonces", "audit", "draft_fingerprint", "fingerprint"}
                 and all(type(result[name]) is int and result[name] >= 0
                         for name in ("drafts", "nonces", "audit"))
                 and all(re.fullmatch(HASH, result[name])
                         for name in ("draft_fingerprint", "fingerprint")), "STATS_FORMAT")
            return result

        def request(self, business, operation="create", *, actor=None, company=None, ttl=30, **changes):
            company = company or business["company_id"]
            suffix = "b" if company == COMPANY_B else "a"
            now = int(time.time())
            auth = {
                "schema": "CRM_AIA_OWNER_DRAFT_AUTH_V1", "issuer": "imagine-crm",
                "audience": "imagine-ai-agents", "operation": "crm.rule.draft." + operation,
                "company_id": company, "actor_role": "owner", "actor_id": actor or business["creator_id"],
                "draft_ref": business["draft_ref"], "business_hash": hashlib.sha256(canonical(business)).hexdigest(),
                "nonce": str(uuid.uuid4()), "issued_at": now, "expires_at": now + ttl,
                "key_id": "p67_" + operation + "_" + suffix,
            }
            auth.update(changes)
            auth["mac"] = hmac.new(self.keys[auth["key_id"]], REQUEST_DOMAIN + canonical(auth),
                                   hashlib.sha256).hexdigest()
            return {"authorization": auth, **({"business": copy.deepcopy(business)} if operation == "create" else {})}

        def resign(self, request, *, key=None, domain=REQUEST_DOMAIN, bind_business=False):
            auth = request["authorization"]
            if bind_business:
                auth["business_hash"] = hashlib.sha256(canonical(request["business"])).hexdigest()
            unsigned = {name: value for name, value in auth.items() if name != "mac"}
            auth["mac"] = hmac.new(key or self.keys[auth["key_id"]], domain + canonical(unsigned),
                                   hashlib.sha256).hexdigest()
            return request

        def http(self, request, *, operation="create", client="client-a", query=False,
                 method="POST", headers=None, content_type="application/json"):
            body = canonical(request) if isinstance(request, dict) else request
            return self.php("http", {"path": "/private/owner-drafts/" + operation + ("?x=1" if query else ""),
                                    "method": method, "body_b64": base64.b64encode(body).decode(),
                                    "headers": headers or [], "content_type": content_type,
                                    "client_certificate": client is not None}, client)

        def accept(self, request, *, status="DRAFT", draft_delta=0, client="client-a"):
            before = self.stats()
            operation = request["authorization"]["operation"].rsplit(".", 1)[1]
            response = self.http(request, operation=operation, client=client)
            reply = verify_reply(response, request, self.keys[request["authorization"]["key_id"]], status=status)
            after = self.stats()
            need(after["drafts"] == before["drafts"] + draft_delta
                 and after["nonces"] == before["nonces"] + 1 and after["audit"] == before["audit"] + 1,
                 "ACCEPTED_EXACT_ATOMIC_COUNTS")
            if draft_delta == 0:
                need(after["draft_fingerprint"] == before["draft_fingerprint"], "READ_OR_REPLAY_BUSINESS_IMMUTABLE")
            self.positive += 1
            return reply, response

        def deny(self, request, *, status=403, tls=False, oversized=False, label="REQUEST", **options):
            before = self.stats()
            response = self.http(request, **options)
            if oversized and response["status"] == 413:
                # nginx may enforce the same bound before PHP has a response.
                pass
            elif tls:
                need(response["status"] in (0, 400, 403) and response["status"] != 200,
                     label + "_TLS_NOT_ACCEPTED")
            else:
                need(response["status"] == status and response["cache_control"] == "no-store"
                     and base64.b64decode(response["body_b64"], validate=True) == DENIAL,
                     label + "_UNIFORM_DENIAL")
            need(self.stats() == before, label + "_DENIAL_LEFT_PARTIAL_WRITES")
            self.negative += 1

    return OwnerDraftLab()


def response_binding_probes(response, request, key):
    """Independent oracle, including valid-MAC responses bound to another attempt."""
    original = verify_reply(response, request, key)
    count = 0
    for field, replacement in (
        ("nonce", str(uuid.uuid4())), ("actor_id", str(uuid.uuid4())),
        ("company_id", COMPANY_B), ("draft_ref", str(uuid.uuid4())),
        ("business_hash", "0" * 64), ("operation", "crm.rule.draft.read"), ("key_id", "p67_read_a"),
    ):
        rebound = copy.deepcopy(request)
        rebound["authorization"][field] = replacement
        try:
            verify_reply(response, rebound, key)
        except Denied:
            count += 1
        else:
            raise Denied("P67_RESPONSE_TRANSPLANT_ACCEPTED")
    for field, replacement in (
        ("schema", "UNKNOWN"), ("issuer", "imagine-crm"), ("audience", "imagine-ai-agents"),
        ("executed", True), ("operational", True), ("executed", 0),
        ("status", "EXECUTED"), ("business", None), ("expires_at", int(time.time()) - 1),
        ("issued_at", int(time.time()) + 6), ("unrequested", True),
    ):
        reply = copy.deepcopy(original)
        reply[field] = replacement
        reply["mac"] = hmac.new(key, RESPONSE_DOMAIN + canonical(
            {name: value for name, value in reply.items() if name != "mac"}), hashlib.sha256).hexdigest()
        altered = {**response, "body_b64": base64.b64encode(canonical(reply)).decode()}
        try:
            verify_reply(altered, request, key)
        except Denied:
            count += 1
        else:
            raise Denied("P67_RESPONSE_MUTATION_ACCEPTED")
    reply = copy.deepcopy(original)
    reply["mac"] = ("1" if reply["mac"][0] == "0" else "0") + reply["mac"][1:]
    raw = canonical(original)
    duplicates = [
        b'{"status":"DRAFT",' + raw[1:],
        b'{"sta\\u0074us":"DRAFT",' + raw[1:],
        canonical(reply),
    ]
    for raw in duplicates:
        altered = {**response, "body_b64": base64.b64encode(raw).decode()}
        try:
            verify_reply(altered, request, key)
        except Denied:
            count += 1
        else:
            raise Denied("P67_RESPONSE_DUPLICATE_OR_MAC_ACCEPTED")
    verify_reply(response, request, key)  # Avoid a stale control making every negative pass.
    return count


def nominal_and_http_negatives(lab):
    business = make_business()
    request = lab.request(business)
    reply, response = lab.accept(request, draft_delta=1)
    need(reply["business"]["settings_version"] == 0, "RAW_CRM_VERSION_ZERO")
    binding_count = response_binding_probes(response, request, lab.keys["p67_create_a"])
    lab.deny(request, label="NONCE_REPLAY")
    lab.accept(lab.request(business))
    lab.accept(lab.request(business, "read"))
    historical, _ = lab.accept(lab.request(business, "read", actor=str(uuid.uuid4())))
    need(historical["actor_id"] != business["creator_id"]
         and historical["business"]["creator_id"] == business["creator_id"], "HISTORICAL_AUTHOR_PRESERVED")
    other = make_business(COMPANY_B, settings_version=20)
    lab.accept(lab.request(other), client="client-b", draft_delta=1)
    lab.accept(lab.request(other, "read"), client="client-b")
    lab.accept(lab.request(business, "read", company=COMPANY_B), client="client-b", status="ABSENT")
    lab.accept(lab.request(make_business(), "read"), status="ABSENT")
    lab.accept(lab.request(business, "read", business_hash="0" * 64), status="ABSENT")

    changed = copy.deepcopy(business)
    changed["lead_version"] += 1
    lab.deny(lab.request(changed), label="IMMUTABLE_DRAFT_CONFLICT")
    changed = copy.deepcopy(business)
    changed["draft_ref"] = str(uuid.uuid4())
    lab.deny(lab.request(changed), label="IMMUTABLE_SOURCE_CONFLICT")
    changed = copy.deepcopy(business)
    changed["creator_id"] = str(uuid.uuid4())
    lab.deny(lab.request(changed), label="AUTHOR_REPAIR_DENIED")

    # All mutations receive fresh nonces; schema failures do not pass merely
    # because the server already consumed a control request.
    for field, value in (
        ("actor_role", "member"), ("issuer", "imagine-ai-agents"),
        ("audience", "imagine-crm"), ("schema", "UNKNOWN"),
        ("operation", "crm.rule.draft.read"), ("company_id", COMPANY_B),
        ("actor_id", str(uuid.uuid4())), ("draft_ref", str(uuid.uuid4())),
        ("nonce", "not-a-uuid"), ("nonce", "AAAAAAAA-AAAA-4AAA-8AAA-AAAAAAAAAAAA"),
        ("business_hash", "0" * 64), ("issued_at", True), ("issued_at", "1"),
        ("unrequested", True),
    ):
        altered = lab.request(make_business())
        altered["authorization"][field] = value
        lab.resign(altered)
        lab.deny(altered, label="AUTH_" + field.upper())
    for field, value in (
        ("schema", "UNKNOWN"), ("rule_id", "lead.created"),
        ("settings_version", -1), ("settings_version", 21), ("settings_version", True),
        ("settings_version", 1.0), ("lead_version", 0), ("lead_version", 2147483648),
        ("source_created_at", 0), ("command_hash", "A" * 64), ("unrequested", True),
    ):
        altered = lab.request(make_business())
        altered["business"][field] = value
        lab.resign(altered, bind_business=True)
        lab.deny(altered, label="BUSINESS_" + field.upper())
    for field, value in (
        ("action", "task.execute"), ("stage", "new"), ("delay_seconds", -1),
        ("delay_seconds", 86401), ("delay_seconds", "90"), ("due_at", 1), ("unrequested", True),
    ):
        altered = lab.request(make_business())
        altered["business"]["projection"][field] = value
        lab.resign(altered, bind_business=True)
        lab.deny(altered, label="PROJECTION_" + field.upper())
    for target, field in (("authorization", "actor_role"), ("business", "source_run_id"),
                          ("projection", "due_at")):
        altered = lab.request(make_business())
        section = altered["business"]["projection"] if target == "projection" else altered[target]
        del section[field]
        lab.resign(altered, bind_business=True)
        lab.deny(altered, label="MISSING_CLOSED_FIELD")
    for changes in (
        {"issued_at": int(time.time()) - 31, "expires_at": int(time.time()) - 1},
        {"issued_at": int(time.time()) + 20, "expires_at": int(time.time()) + 25},
        {"issued_at": int(time.time()), "expires_at": int(time.time()) + 31},
        {"issued_at": int(time.time()), "expires_at": int(time.time())},
    ):
        lab.deny(lab.request(make_business(), **changes), label="AUTH_TTL")
    for bad_key, domain in (
        (secrets.token_hex(32).encode(), REQUEST_DOMAIN),
        (bytes.fromhex(lab.keys["p67_create_a"].decode()), REQUEST_DOMAIN),
        (lab.keys["p67_read_a"], REQUEST_DOMAIN),
        (lab.keys["p67_create_a"], RESPONSE_DOMAIN),
    ):
        altered = lab.resign(lab.request(make_business()), key=bad_key, domain=domain)
        lab.deny(altered, label="WRONG_MAC_KEY_OR_DOMAIN")
    altered = lab.request(make_business())
    altered["authorization"]["key_id"] = "p67_read_a"
    lab.resign(altered)
    lab.deny(altered, label="WRONG_OPERATION_KEY")
    altered = lab.request(make_business())
    altered["authorization"]["key_id"] = "missing_key"
    lab.resign(altered, key=lab.keys["p67_create_a"])
    lab.deny(altered, label="MISSING_KEY")
    for client in ("client-b", "client-unknown", None, "client-foreign"):
        lab.deny(lab.request(make_business()), client=client, tls=client in (None, "client-foreign"),
                 label="CERTIFICATE_SCOPE")
    lab.deny(lab.request(make_business()), client="client-unknown",
             headers=["X-Client-Verify: SUCCESS", "X-Client-Fingerprint: " + lab.fingerprints["a"],
                      "Imagine-Mtls-Verify: SUCCESS", "X-Forwarded-Client-Cert: synthetic"],
             label="SPOOFED_CLIENT_CERTIFICATE_HEADERS")
    for operation in ("create", "read"):
        for headers in (["Cookie: session=SYNTHETIC"], ["Authorization: Bearer SYNTHETIC"],
                        ["Content-Encoding: identity"]):
            lab.deny(lab.request(business, operation), operation=operation, headers=headers,
                     label="FORBIDDEN_HUMAN_OR_ENCODING_HEADER")
        lab.deny(lab.request(business, operation), operation=operation, query=True, label="QUERY")
        for method in ("GET", "PUT"):
            lab.deny(lab.request(business, operation), operation=operation, method=method,
                     label="METHOD")
        for content_type in ("text/plain", "application/json; charset=utf-8"):
            lab.deny(lab.request(business, operation), operation=operation, content_type=content_type,
                     label="CONTENT_TYPE")
    lab.deny(lab.request(business, "read"), operation="create", label="READ_ON_CREATE_PATH")
    lab.deny(lab.request(business), operation="read", label="CREATE_ON_READ_PATH")
    for mode in ("root", "auth", "business", "projection", "escaped_auth"):
        altered = lab.request(make_business())
        raw = canonical(altered)
        if mode == "root":
            raw = b'{"authorization":' + canonical(altered["authorization"]) + b"," + raw[1:]
        elif mode == "auth":
            raw = raw.replace(b'"actor_role":"owner"', b'"actor_role":"member","actor_role":"owner"', 1)
        elif mode == "escaped_auth":
            raw = raw.replace(b'"actor_role":"owner"', b'"actor_\\u0072ole":"member","actor_role":"owner"', 1)
        elif mode == "business":
            raw = raw.replace(b'"settings_version":0', b'"settings_version":20,"settings_version":0', 1)
        else:
            raw = raw.replace(b'"stage":"qualified"', b'"stage":"new","stage":"qualified"', 1)
        lab.deny(raw, label="DUPLICATE_" + mode.upper())
    for raw in (b"{}", b"[]", b'{"authorization":', b'{"authorization":null}',
                b'{"authorization":{}}', b"\xff", b'{"x":' + b"[" * 10 + b"0" + b"]" * 10 + b"}"):
        lab.deny(raw, label="MALFORMED_BODY")
    raw = canonical(lab.request(make_business()))
    lab.deny(raw + b" " * (8193 - len(raw)), oversized=True, label="BODY_LIMIT")
    altered = lab.request(business, "read")
    altered["business"] = business
    lab.deny(altered, operation="read", label="READ_EXTRA_BUSINESS")
    lab.accept(lab.request(business, "read"))
    print(json.dumps({"check": "P67_HTTP_AND_MAC", "positive": lab.positive,
                      "negative": lab.negative, "response_binding_negatives": binding_count,
                      "historical_author_preserved": True, "raw_settings_version_zero": True}), flush=True)
    return business, binding_count


def sql_literal(value):
    need(isinstance(value, str) and "\x00" not in value, "SQL_FIXTURE_LITERAL")
    return "'" + value.replace("'", "''") + "'"


def sql_call(lab, request, operation=None, *, client="a", boolean=True):
    operation = operation or request["authorization"]["operation"].rsplit(".", 1)[1]
    need(operation in ("create", "read") and client in ("a", "b"), "SQL_ROLE_SCOPE")
    expression = ("aia_p67_owner." + operation + "_draft(" + sql_literal(lab.fingerprints[client])
                  + "," + sql_literal(canonical(request).decode()) + "::jsonb)")
    return ("SET LOCAL ROLE aia_p67_" + operation + ";SELECT " + expression
            + (" IS NULL" if boolean else "") + ";")


def start_sql(lab, application, sql):
    need(re.fullmatch(r"p67_[a-z0-9_]{1,50}", application), "SQL_APPLICATION_SCOPE")
    process = subprocess.Popen(
        ["docker", "exec", "-i", lab.containers["src"], "psql", "-X", "-qAt", "-v", "ON_ERROR_STOP=1",
         "-U", "postgres", "-d", "imagine_agents_ci"],
        stdin=subprocess.PIPE, stdout=subprocess.PIPE, stderr=subprocess.PIPE,
    )
    # Only the disposable race session has larger waits. The tested HTTP
    # endpoints retain their production-candidate 3s / 1.5s timeout settings.
    setup = ("SET application_name=" + sql_literal(application) + ";"
             "SET statement_timeout='15000ms';SET lock_timeout='12000ms';"
             "SET idle_in_transaction_session_timeout='15000ms';")
    try:
        process.stdin.write((setup + sql + "\n").encode())
        process.stdin.flush()
    except BaseException:
        process.kill()
        process.communicate(timeout=5)
        raise
    return process


def wait_for(lab, query, code, *, processes=()):
    deadline = time.monotonic() + 5
    while time.monotonic() < deadline:
        if lab._sql("src", query) == "1":
            return
        need(all(process.poll() is None for process in processes), code + "_PROCESS_EXITED")
        time.sleep(0.05)
    raise Denied("P67_" + code)


def idle_query(application):
    return ("SELECT count(*) FROM pg_stat_activity WHERE application_name=" + sql_literal(application)
            + " AND state='idle in transaction'")


def blocked_query(waiter, blocker):
    return ("SELECT count(*) FROM pg_stat_activity w JOIN pg_stat_activity b "
            "ON b.pid=ANY(pg_blocking_pids(w.pid)) WHERE w.application_name=" + sql_literal(waiter)
            + " AND b.application_name=" + sql_literal(blocker) + " AND w.wait_event_type='Lock'")


def finish_sql(process, tail=""):
    output, _ = process.communicate(input=(tail + "\n").encode(), timeout=8)
    need(process.returncode == 0, "SQL_SESSION_FAILED")
    need(len(output) <= 16384, "SQL_SESSION_OUTPUT_BOUND")
    return output.decode().strip()


def abort_sql(process):
    if process is None or process.poll() is not None:
        return
    try:
        process.communicate(input=b"ROLLBACK;\n", timeout=2)
    except (subprocess.SubprocessError, OSError, ValueError):
        process.kill()
        process.communicate(timeout=5)


def wait_epoch(lab, epoch):
    deadline = time.monotonic() + 9
    while time.monotonic() < deadline:
        now = int(lab._sql("src", "SELECT floor(extract(epoch FROM clock_timestamp()))::bigint"))
        if now >= epoch:
            return
        time.sleep(0.1)
    raise Denied("P67_REAL_CLOCK_BARRIER")


def grant_where(operation):
    need(operation in ("create", "read"), "GRANT_OPERATION")
    return ("company_id=" + sql_literal(COMPANY_A) + "::uuid AND operation="
            + sql_literal("crm.rule.draft." + operation))


def authority_negatives(lab, business):
    # The service grant for creation is independent of the read grant.
    for operation in ("create", "read"):
        where = grant_where(operation)
        lab._sql("src", "UPDATE aia_p67_owner.service_grants SET active=false,revision=revision+1 WHERE " + where)
        try:
            lab.deny(lab.request(business, operation), operation=operation, label="CURRENT_GRANT_REVOKED")
            if operation == "create":
                lab.accept(lab.request(business, "read"))
        finally:
            lab._sql("src", "UPDATE aia_p67_owner.service_grants SET active=true,revision=revision+1 WHERE " + where)
        for table, predicate in (
            ("service_grants", where), ("mac_keys", "key_id=" + sql_literal("p67_" + operation + "_a")),
        ):
            for column, bad, restored in (
                ("active", "false", "true"),
                ("expires_at", "floor(extract(epoch FROM clock_timestamp()))::bigint-1",
                 "floor(extract(epoch FROM clock_timestamp()))::bigint+1800"),
            ):
                lab._sql("src", "UPDATE aia_p67_owner." + table + " SET " + column + "=" + bad
                         + ",revision=revision+1 WHERE " + predicate)
                try:
                    lab.deny(lab.request(business, operation), operation=operation,
                             label="CURRENT_" + table.upper() + "_" + column.upper())
                finally:
                    lab._sql("src", "UPDATE aia_p67_owner." + table + " SET " + column + "=" + restored
                             + ",revision=revision+1 WHERE " + predicate)
    lab._sql("src", "UPDATE aia_p67_owner.companies SET active=false,revision=revision+1 WHERE company_id="
             + sql_literal(COMPANY_A))
    try:
        for operation in ("create", "read"):
            lab.deny(lab.request(business, operation), operation=operation, label="CURRENT_COMPANY_INACTIVE")
    finally:
        lab._sql("src", "UPDATE aia_p67_owner.companies SET active=true,revision=revision+1 WHERE company_id="
                 + sql_literal(COMPANY_A))
    # A real audit insertion error must roll back a newly inserted business row
    # and the nonce. Existing historical drafts are preserved on read failure.
    lab._sql("src", "ALTER TABLE aia_p67_owner.audit ADD CONSTRAINT p67_fixture_audit_failure CHECK(false) NOT VALID")
    try:
        lab.deny(lab.request(make_business()), status=503, label="CREATE_AUDIT_FAILURE")
        lab.deny(lab.request(business, "read"), operation="read", status=503, label="READ_AUDIT_FAILURE")
    finally:
        lab._sql("src", "ALTER TABLE aia_p67_owner.audit DROP CONSTRAINT p67_fixture_audit_failure")
    lab.accept(lab.request(business, "read"))


def sql_shape_negatives(lab):
    """The execute-only API must enforce shape even without the HTTP parser."""
    for kind in ("member", "business_extra", "projection_action", "wrong_actor", "wrong_hash",
                 "wrong_mac", "read_body_on_create", "create_body_on_read"):
        business = make_business()
        request = lab.request(business)
        operation = "create"
        if kind == "member":
            request["authorization"]["actor_role"] = "member"
            lab.resign(request)
        elif kind == "business_extra":
            request["business"]["extra"] = True
            lab.resign(request, bind_business=True)
        elif kind == "projection_action":
            request["business"]["projection"]["action"] = "task.execute"
            lab.resign(request, bind_business=True)
        elif kind == "wrong_actor":
            request["authorization"]["actor_id"] = str(uuid.uuid4())
            lab.resign(request)
        elif kind == "wrong_hash":
            request["authorization"]["business_hash"] = "0" * 64
            lab.resign(request)
        elif kind == "wrong_mac":
            request["authorization"]["mac"] = "0" * 64
        elif kind == "read_body_on_create":
            request = lab.request(business, "read")
        else:
            operation = "read"
        before = lab.stats()
        result = lab._sql("src", "BEGIN;" + sql_call(lab, request, operation) + "COMMIT;")
        need(result == "t" and lab.stats() == before, "SQL_SHAPE_OR_ATOMICITY_" + kind.upper())
        lab.negative += 1


def temporal_race(lab, business, kind):
    need(kind in ("key_wait_auth_expiry", "audit_create_grant_expiry",
                  "audit_read_key_expiry", "audit_read_auth_expiry"), "TEMPORAL_RACE_KIND")
    operation = "read" if kind.startswith("audit_read") else "create"
    fixture = business if operation == "read" else make_business()
    suffix = secrets.token_hex(5)
    blocker_name, waiter_name = "p67_time_block_" + suffix, "p67_time_wait_" + suffix
    holder = waiter = None
    restored = None
    try:
        if kind == "audit_create_grant_expiry":
            deadline = int(lab._sql("src", "SELECT floor(extract(epoch FROM clock_timestamp()))::bigint")) + 6
            restored = ("service_grants", grant_where(operation))
        elif kind == "audit_read_key_expiry":
            deadline = int(lab._sql("src", "SELECT floor(extract(epoch FROM clock_timestamp()))::bigint")) + 6
            restored = ("mac_keys", "key_id=" + sql_literal("p67_read_a"))
        if restored:
            lab._sql("src", "UPDATE aia_p67_owner." + restored[0]
                     + " SET expires_at=" + str(deadline) + ",revision=revision+1 WHERE " + restored[1])
        before = lab.stats()
        if kind == "key_wait_auth_expiry":
            held = "UPDATE aia_p67_owner.mac_keys SET active=active WHERE key_id='p67_create_a';"
        else:
            held = "LOCK TABLE aia_p67_owner.audit IN ACCESS EXCLUSIVE MODE;"
        holder = start_sql(lab, blocker_name, "BEGIN;" + held)
        wait_for(lab, idle_query(blocker_name), "TIME_BARRIER_NOT_HELD", processes=(holder,))
        request = lab.request(fixture, operation, ttl=4 if not restored else 30)
        if not restored:
            deadline = request["authorization"]["expires_at"]
        waiter = start_sql(lab, waiter_name, "BEGIN;" + sql_call(lab, request))
        wait_for(lab, blocked_query(waiter_name, blocker_name), "REAL_TIME_WAIT_NOT_OBSERVED",
                 processes=(holder, waiter))
        wait_epoch(lab, deadline)
        need(finish_sql(holder, "COMMIT;") == "", "TIME_HOLDER_RESULT")
        holder = None
        need(finish_sql(waiter, "COMMIT;") == "t", "EXPIRED_SQL_NOT_DENIED")
        waiter = None
        need(lab.stats() == before, "LATE_EXPIRY_LEFT_PARTIAL_WRITES")
        lab.races += 1
        print(json.dumps({"check": "P67_REAL_PG_RACE", "case": kind, "denied_without_partial_writes": True}),
              flush=True)
    finally:
        abort_sql(waiter)
        abort_sql(holder)
        if restored:
            lab._sql("src", "UPDATE aia_p67_owner." + restored[0]
                     + " SET expires_at=floor(extract(epoch FROM clock_timestamp()))::bigint+1800,"
                     "revision=revision+1 WHERE " + restored[1])


def revocation_wait(lab, business, table, operation):
    need(table in ("companies", "service_grants", "mac_keys")
         and operation in ("create", "read"), "REVOCATION_RACE_SCOPE")
    predicate = {
        "companies": "company_id=" + sql_literal(COMPANY_A),
        "service_grants": grant_where(operation),
        "mac_keys": "key_id=" + sql_literal("p67_" + operation + "_a"),
    }[table]
    suffix = secrets.token_hex(5)
    blocker_name, waiter_name = "p67_revoke_block_" + suffix, "p67_revoke_wait_" + suffix
    holder = waiter = None
    before = lab.stats()
    try:
        holder = start_sql(lab, blocker_name, "BEGIN;UPDATE aia_p67_owner." + table
                           + " SET active=false,revision=revision+1 WHERE " + predicate + ";")
        wait_for(lab, idle_query(blocker_name), "REVOCATION_BARRIER_NOT_HELD", processes=(holder,))
        request = lab.request(business if operation == "read" else make_business(), operation)
        waiter = start_sql(lab, waiter_name, "BEGIN;" + sql_call(lab, request))
        wait_for(lab, blocked_query(waiter_name, blocker_name), "REAL_REVOCATION_WAIT_NOT_OBSERVED",
                 processes=(holder, waiter))
        need(finish_sql(holder, "COMMIT;") == "", "REVOKER_RESULT")
        holder = None
        need(finish_sql(waiter, "COMMIT;") == "t", "COMMITTED_REVOCATION_NOT_OBSERVED")
        waiter = None
        need(lab.stats() == before, "REVOKED_WAIT_LEFT_PARTIAL_WRITES")
        lab.races += 1
        print(json.dumps({"check": "P67_REAL_PG_RACE", "case": table + "_" + operation + "_revocation",
                          "denied_without_partial_writes": True}), flush=True)
    finally:
        abort_sql(waiter)
        abort_sql(holder)
        lab._sql("src", "UPDATE aia_p67_owner." + table
                 + " SET active=true,revision=revision+1 WHERE " + predicate)


def unique_wait(lab, *, commit):
    suffix = secrets.token_hex(5)
    first_name, waiter_name = "p67_unique_first_" + suffix, "p67_unique_wait_" + suffix
    first = waiter = None
    before = lab.stats()
    business = make_business()
    try:
        accepted = lab.request(business)
        first = start_sql(lab, first_name, "BEGIN;" + sql_call(lab, accepted))
        wait_for(lab, idle_query(first_name), "UNIQUE_WINNER_NOT_READY", processes=(first,))
        contender = lab.request(business, ttl=4)
        waiter = start_sql(lab, waiter_name, "BEGIN;" + sql_call(lab, contender))
        wait_for(lab, blocked_query(waiter_name, first_name), "REAL_UNIQUE_WAIT_NOT_OBSERVED",
                 processes=(first, waiter))
        wait_epoch(lab, contender["authorization"]["expires_at"])
        need(finish_sql(first, "COMMIT;" if commit else "ROLLBACK;") == "f", "UNIQUE_WINNER_NOT_ACCEPTED")
        first = None
        need(finish_sql(waiter, "COMMIT;") == "t", "EXPIRED_UNIQUE_CONTENDER_NOT_DENIED")
        waiter = None
        after = lab.stats()
        if commit:
            need(after["drafts"] == before["drafts"] + 1 and after["nonces"] == before["nonces"] + 1
                 and after["audit"] == before["audit"] + 1, "UNIQUE_WINNER_CHANGED_BY_DENIAL")
        else:
            need(after == before, "UNIQUE_ROLLBACK_LEFT_ORPHAN")
        lab.races += 1
        lab.accept(lab.request(business, "read"), status="DRAFT" if commit else "ABSENT")
        print(json.dumps({"check": "P67_REAL_PG_RACE", "case": "unique_commit" if commit else "unique_rollback",
                          "winner_preserved": commit, "expired_contender_rolled_back": True}), flush=True)
    finally:
        abort_sql(waiter)
        abort_sql(first)


def admitted_revocation_order(lab):
    """An already admitted transaction finishes before the blocked revocation."""
    suffix = secrets.token_hex(5)
    first_name, revoker_name = "p67_admitted_" + suffix, "p67_after_revoke_" + suffix
    first = revoker = None
    before = lab.stats()
    business = make_business()
    try:
        first = start_sql(lab, first_name, "BEGIN;" + sql_call(lab, lab.request(business)))
        wait_for(lab, idle_query(first_name), "ADMITTED_TRANSACTION_NOT_READY", processes=(first,))
        revoker = start_sql(lab, revoker_name,
                            "BEGIN;UPDATE aia_p67_owner.companies SET active=false,revision=revision+1"
                            " WHERE company_id=" + sql_literal(COMPANY_A) + ";")
        wait_for(lab, blocked_query(revoker_name, first_name), "AUTHORITY_NOT_HELD_THROUGH_TRANSACTION",
                 processes=(first, revoker))
        need(finish_sql(first, "COMMIT;") == "f", "ADMITTED_TRANSACTION_NOT_ACCEPTED")
        first = None
        need(finish_sql(revoker, "COMMIT;") == "", "AFTER_ADMISSION_REVOKER_RESULT")
        revoker = None
        after = lab.stats()
        need(after["drafts"] == before["drafts"] + 1 and after["nonces"] == before["nonces"] + 1
             and after["audit"] == before["audit"] + 1, "ADMITTED_ATOMIC_COUNTS")
        lab.deny(lab.request(business, "read"), operation="read", label="AFTER_ADMISSION_CURRENT_AUTHORITY")
        lab.races += 1
        print(json.dumps({"check": "P67_REAL_PG_RACE", "case": "admission_before_revocation",
                          "authority_lock_held_until_commit": True}), flush=True)
    finally:
        abort_sql(revoker)
        abort_sql(first)
        lab._sql("src", "UPDATE aia_p67_owner.companies SET active=true,revision=revision+1 WHERE company_id="
                 + sql_literal(COMPANY_A))
    historical, _ = lab.accept(lab.request(business, "read", actor=str(uuid.uuid4())))
    need(historical["business"] == business, "POST_REVOCATION_HISTORICAL_IMMUTABILITY")


def http_lock_timeouts(lab, business):
    for operation in ("create", "read"):
        holder = None
        application = "p67_http_timeout_" + secrets.token_hex(5)
        try:
            holder = start_sql(lab, application, "BEGIN;UPDATE aia_p67_owner.mac_keys SET active=active"
                               " WHERE key_id=" + sql_literal("p67_" + operation + "_a") + ";")
            wait_for(lab, idle_query(application), "HTTP_TIMEOUT_BARRIER", processes=(holder,))
            lab.deny(lab.request(business if operation == "read" else make_business(), operation),
                     operation=operation, status=503, label="HTTP_DATABASE_LOCK_TIMEOUT")
            finish_sql(holder, "ROLLBACK;")
            holder = None
            lab.races += 1
        finally:
            abort_sql(holder)
    lab.accept(lab.request(business, "read"))


def default_off(lab, business):
    for operation in ("create", "read"):
        lab.remove_owned(lab.project + "-" + operation)
        lab.start_fpm(operation, enabled=False)
    lab.wait_ready()
    for operation in ("create", "read"):
        lab.deny(lab.request(business, operation), operation=operation, status=404, label="DEFAULT_OFF")


def main():
    temporary = source_preflight()
    with tempfile.TemporaryDirectory(prefix="aia-p67-private-", dir=temporary) as parent:
        lab = create_lab("p67-" + secrets.token_hex(6), Path(parent) / "lab")
        try:
            lab.prepare()
            lab.start_http()
            business, bindings = nominal_and_http_negatives(lab)
            authority_negatives(lab, business)
            sql_shape_negatives(lab)
            for kind in ("key_wait_auth_expiry", "audit_create_grant_expiry",
                         "audit_read_key_expiry", "audit_read_auth_expiry"):
                temporal_race(lab, business, kind)
            for table, operation in (("companies", "create"), ("service_grants", "create"),
                                     ("service_grants", "read"), ("mac_keys", "read")):
                revocation_wait(lab, business, table, operation)
            unique_wait(lab, commit=True)
            unique_wait(lab, commit=False)
            admitted_revocation_order(lab)
            http_lock_timeouts(lab, business)
            final_state = lab.stats()
            need(final_state["nonces"] == final_state["audit"], "FINAL_NONCE_AUDIT_PARITY")
            default_off(lab, business)
            summary = {
                "schema": "P67_OWNER_DRAFT_LAB_RESULT_V1", "result": "PASS",
                "positive_http": lab.positive, "negative_http_and_sql": lab.negative,
                "response_binding_negatives": bindings, "real_pg_lock_races": lab.races,
                "drafts": final_state["drafts"], "nonces": final_state["nonces"], "audit": final_state["audit"],
                "postgresql_16": True, "https_mutual_tls": True,
                "historical_author_preserved": True, "read_no_business_mutation": True,
                "reads_write_technical_nonce_and_audit": True, "late_denial_partial_writes": False,
                "service_attestations_synthetic": True, "human_crm_session_verified": False,
                "crm_rule_source_verified": False, "host_ports": False, "runtime_egress": False,
                "external_ai": False, "operational": False, "executed": False, "release": "NO_GO",
            }
        finally:
            lab.cleanup()
        print(json.dumps(summary, sort_keys=True), flush=True)
    print("AIA_P67_OWNER_DRAFT_LAB_PASS_NO_GO", flush=True)


if __name__ == "__main__":
    try:
        main()
    except Exception as error:
        # SQL bodies, certificates, passwords, MAC material and subprocess
        # stderr never enter CI diagnostics, including unexpected failures.
        marker = str(error)
        if not re.fullmatch(r"P(?:25|27|67)_[A-Z0-9_]{1,120}", marker):
            marker = "P67_UNEXPECTED_FAILURE"
        print(marker, file=sys.stderr, flush=True)
        raise SystemExit(1)
