#!/usr/bin/env python3
"""P64 real isolated PG16 + HTTP form -> atomic drafts/authors -> P62/P63.

Reuses the P62/P63 lab and the Central P25 disposable cluster. No host
ports, egress, real author/IAM, CRM/n8n execution, or paid provider.
"""
from __future__ import annotations

import base64
from html.parser import HTMLParser
import json
import os
from pathlib import Path
import re
import secrets
import subprocess
import sys
import tempfile
import time
from urllib.parse import urlencode

from check_p63_verifiable_proposal_detail import DetailLab, DetailScan
from check_p62_live_signed_review import Failure, require, run
from check_p56_live_pg_readonly_http import READ_CSP, is_imagine_logo

ROOT = Path(__file__).resolve().parents[1]
PAGE = "/p64-create-drafts-local.php"
READER = "http://127.0.0.1:8786/p56-live-pg-review-local.php"
ORIGIN = "http://127.0.0.1:8787"
CSP = ("default-src 'none'; style-src 'unsafe-inline'; img-src data:; form-action 'self' " + READER
       + "; base-uri 'none'; frame-ancestors 'none'")


class FormScan(HTMLParser):
    def __init__(self):
        super().__init__()
        self.csrf: list[str] = []
        self.previews: list[str] = []
        self.requests: list[str] = []
        self.proposals: list[str] = []
        self.forms: list[dict] = []
        self.remotes: list[str] = []
        self.logos = 0
        self.links: list[dict] = []

    def handle_starttag(self, tag, attrs):
        values = dict(attrs)
        if tag in ("a", "img") and len(attrs) != len(values):
            self.remotes.append("DUPLICATE_PRESENTATION_ATTRIBUTE")
        if tag == "form":
            self.forms.append(values)
        if tag == "input" and values.get("name") == "csrf":
            self.csrf.append(values.get("value", ""))
        for name, target in (("data-p64-preview", self.previews),
                             ("data-p64-request", self.requests),
                             ("data-p64-proposal", self.proposals)):
            if name in values:
                target.append(values[name])
        if tag == "img":
            if is_imagine_logo(attrs):
                self.logos += 1
            else:
                self.remotes.append("UNEXPECTED_IMAGE")
        if tag == "a":
            self.links.append(values)
        if tag in ("script", "iframe", "link", "object", "embed", "source", "video", "audio", "svg") or any(name.startswith("on") for name, _ in attrs):
            self.remotes.append("UNEXPECTED_ACTIVE_CONTENT")


def decode(result: subprocess.CompletedProcess) -> dict:
    if result.returncode:
        markers = re.findall(rb"^P64_PROBE_DENIED_[A-Z0-9_]+$", result.stderr, re.MULTILINE)
        raise Failure(markers[0].decode() if len(markers) == 1 else "P64_PHP_FAILED")
    value = json.loads(result.stdout)
    require(isinstance(value, dict), "P64_PROBE_OBJECT")
    return value


class CreationLab(DetailLab):
    def __init__(self, prefix: str, directory: Path):
        super().__init__(prefix, directory)
        self.writers: dict[str, Path] = {}
        self.writer_server: str | None = None

    def args(self, scope: str) -> list[str]:
        result = super().args("client" if scope == "writer" else scope)
        result += ["-e", "AIA_P64_CREATE_LAB=true"]
        names = ("a", "b") if scope == "probe" else ("a",) if scope == "writer" else ()
        for name in names:
            if name in self.writers:
                result += ["--mount", "type=bind,src=" + str(self.writers[name])
                           + ",dst=/run/p64/writer-" + name + ",readonly"]
        if scope == "writer":
            result += ["--tmpfs", "/run/p64-sessions:rw,noexec,nosuid,size=4m,mode=0700,uid=33,gid=33",
                       "-e", "AIA_P64_FORM_LAB=true", "-e", "AIA_P64_WRITER_PASSWORD_FILE=/run/p64/writer-a",
                       "-e", "DB_USERNAME=aia_p64_ci_write_a"]
        return result

    def probe(self, mode: str, payload: dict | None = None, *, client=False) -> dict:
        self.sequence += 1
        name = self.project + "-p64-" + str(self.sequence)
        require(run(["docker", "container", "inspect", name], "P64_NEW_PROBE", check=False).returncode != 0,
                "P64_PREEXISTING_PROBE")
        self.extra[name] = name
        args = ["docker", "run", "--rm", "-i", "--name", name,
                "--label", "imagine.p25.owner=" + self.token] + self.args("client" if client else "probe")
        args += ["--entrypoint", "php", self.php_image, "/repo/tools/p64-authored-draft-probe.php", mode]
        result = run(args, "P64_PROBE", stdin=json.dumps(payload or {}).encode(), timeout=50, check=False)
        self.remove_owned(name)
        return decode(result)

    def prepare(self):
        super().prepare()
        passwords = {}
        for name in ("a", "b"):
            password = secrets.token_hex(18)
            path = self.directory / ("writer-" + name)
            with path.open("x", encoding="ascii") as out:
                os.fchmod(out.fileno(), 0o444)
                out.write(password)
            self.writers[name] = path
            passwords["writer_" + name] = password
        require(self.probe("seed", passwords).get("schema") == "P64_SEEDED_V1", "P64_SEED")

    def start_writer(self):
        name = self.project + "-writer-http"
        require(run(["docker", "container", "inspect", name], "P64_NEW_HTTP", check=False).returncode != 0,
                "P64_PREEXISTING_HTTP")
        self.extra[name] = name
        identity = run(["docker", "run", "-d", "--name", name,
                        "--label", "imagine.p25.owner=" + self.token] + self.args("writer") +
                       ["--entrypoint", "php", self.php_image, "-S", "127.0.0.1:8787",
                        "-t", "/repo/examples"], "P64_START_WRITER").stdout.decode().strip()
        require(re.fullmatch(r"[a-f0-9]{64}", identity) is not None, "P64_WRITER_ID")
        self.extra[name] = identity
        self.writer_server = identity
        info = json.loads(run(["docker", "inspect", identity], "P64_WRITER_ISOLATION").stdout)[0]
        require(info["HostConfig"]["NetworkMode"] == "container:" + self.containers["src"]
                and not info["HostConfig"].get("PortBindings")
                and info["HostConfig"]["ReadonlyRootfs"] is True
                and not info["HostConfig"]["Privileged"]
                and info["Config"]["User"] in ("www-data", "33", "33:33"), "P64_WRITER_ISOLATED")
        destinations = {item["Destination"] for item in info["Mounts"]}
        require("/run/p64/writer-a" in destinations
                and not destinations.intersection({"/run/secrets/p25-password", "/run/p62/reader-a",
                                                   "/run/p64/writer-b"}), "P64_WRITER_LIMITED_SECRETS")
        require(self.server is not None, "P64_READER_REQUIRED")
        reader = json.loads(run(["docker", "inspect", self.server], "P64_READER_SEPARATE").stdout)[0]
        require(not any(item["Destination"].startswith("/run/p64") for item in reader["Mounts"]),
                "P64_READER_NO_WRITER_SECRET")
        for _ in range(30):
            result = run(["docker", "exec", "-i", identity, "php",
                          "/repo/tools/p64-authored-draft-probe.php", "http"],
                         "P64_WRITER_READY", stdin=b"{}", timeout=7, check=False)
            if result.returncode == 0:
                require(decode(result)["status"] == 200, "P64_INITIAL_FORM")
                return
            time.sleep(.1)
        raise Failure("P64_WRITER_NOT_READY")

    def request(self, *, cookie: str | None = None, fields: dict | None = None,
                raw: str | None = None, discard=False, **kwargs) -> dict:
        payload = {"path": PAGE}
        payload.update(kwargs)
        if cookie is not None:
            payload["cookie"] = cookie
        if fields is not None or raw is not None:
            payload.setdefault("method", "POST")
            payload.setdefault("origin", ORIGIN)
            payload.setdefault("content_type", "application/x-www-form-urlencoded")
            payload["body_b64"] = base64.b64encode((raw if raw is not None else urlencode(fields)).encode()).decode()
        return self.probe("http-discard" if discard else "http", payload, client=True)

    def stage(self, request: str, *, visits=120, connection="a", **kwargs) -> dict:
        return self.probe("stage", {"request": request, "visits": visits, "connection": connection, **kwargs})

    def stats(self) -> dict:
        return self.probe("stats")


def form(response: dict) -> tuple[str, FormScan]:
    require(response["status"] == 200, "P64_FORM_200")
    headers = response["headers"]
    require(headers.get("content-security-policy") == CSP and headers.get("x-frame-options") == "DENY"
            and headers.get("cache-control", "").startswith("no-store")
            and headers.get("x-content-type-options") == "nosniff", "P64_EXACT_SECURITY_HEADERS")
    body = base64.b64decode(response["body_b64"], validate=True).decode()
    scan = FormScan()
    scan.feed(body)
    require(scan.csrf and len(set(scan.csrf)) == 1 and re.fullmatch(r"[a-f0-9]{64}", scan.csrf[0]),
            "P64_CSRF_TOKEN")
    require(scan.remotes == [] and all(item == {"method": "post", "action": PAGE} for item in scan.forms),
            "P64_FORM_FIXED_LOCAL_ACTIONS")
    require(scan.logos == 1 and scan.links == [
        {"class": "skip-link", "data-imagine-skip": "true", "href": "#main-content"},
        {"href": PAGE, "data-imagine-nav": "create", "aria-current": "page"},
        {"href": READER, "data-imagine-nav": "review"},
    ], "P64_CANONICAL_LOGO_AND_FIXED_NAVIGATION")
    require("#F9941D" in body and "NO-GO operacional" in body, "P64_PRODUCT_PAGE")
    for forbidden in ("SYNTHETIC_P64_AUTHOR", "password", "canonical_vote", "signature_b64", "public_key_b64"):
        require(forbidden not in body, "P64_NO_SECRET_OR_AUTHOR_ID_IN_FORM")
    return body, scan


def session(lab: CreationLab) -> tuple[str, str]:
    response = lab.request()
    _, scan = form(response)
    cookie = response["headers"].get("set-cookie", "")
    require("httponly" in cookie.lower() and "samesite=strict" in cookie.lower() and "path=" + PAGE in cookie.lower(),
            "P64_SESSION_COOKIE")
    require(re.fullmatch(r"AIA_P64_LAB=[a-zA-Z0-9,-]+", cookie.split(";", 1)[0]) is not None,
            "P64_SESSION_COOKIE_FORMAT")
    return cookie.split(";", 1)[0], scan.csrf[0]


def preview(lab: CreationLab, cookie: str, csrf: str, visits="120", lead="10", client="20") -> tuple[dict, FormScan]:
    response = lab.request(cookie=cookie, fields={"action": "preview", "csrf": csrf,
        "weekly_visits": visits, "lead_rate": lead, "client_rate": client})
    _, scan = form(response)
    require(len(scan.previews) == 1 and len(scan.requests) == 1, "P64_SESSION_BOUND_PREVIEW")
    return {"action": "save", "csrf": csrf, "preview_id": scan.previews[0]}, scan


def assert_saved(response: dict, refs: list[str]):
    require(response["status"] == 303 and response["headers"]["location"] == READER +
            ("?proposal=" + refs[0] if len(refs) == 1 else ""), "P64_FIXED_POST_REDIRECT_GET")
    require(response["headers"].get("content-security-policy") == CSP, "P64_REDIRECT_ALLOWED_BY_EXACT_CSP")


def assert_detail(lab: CreationLab, proposal: str):
    status, body, headers = lab.http("?proposal=" + proposal)
    require(status == 200, "P64_P63_DETAIL_AVAILABLE")
    require(headers.get("content-security-policy") == READ_CSP, "P64_READER_EXACT_CSP")
    scan = DetailScan(allow_create=True)
    scan.feed(body)
    require(scan.detail_states == ["VERIFIED"] and scan.detail_proposals == [proposal]
            and scan.operators == ["gte"] and scan.current == ["available"]
            and scan.actions == [] and scan.remotes == [] and scan.valid_shell(), "P64_DETAIL_SAME_VERIFIED_QUEUE")
    require("Autoria sintética vinculada e verificada" in body
            and "Insumos originais da simulação" in body, "P64_ORIGINAL_INPUTS_AND_AUTHOR")


def concurrent(lab: CreationLab, requests: list[dict], expected: list[str], label: str) -> list[dict]:
    """Observe two actual writer sessions waiting, then release one tenant lock."""
    require(lab.writer_server is not None, "P64_WRITER_REQUIRED")
    blocker = subprocess.Popen(["docker", "exec", "-i", lab.containers["src"],
        "psql", "-X", "-qAt", "-v", "ON_ERROR_STOP=1", "-U", "postgres", "-d", "imagine_agents_ci"],
        stdin=subprocess.PIPE, stdout=subprocess.PIPE, stderr=subprocess.PIPE)
    workers: list[subprocess.Popen] = []
    try:
        blocker.stdin.write(("SET application_name='p64_blocker'; SET idle_in_transaction_session_timeout='8s'; "
                            "BEGIN; SELECT pg_advisory_xact_lock(640051,1);\n").encode())
        blocker.stdin.flush()
        deadline = time.monotonic() + 3
        while time.monotonic() < deadline:
            if lab._sql("src", "SELECT count(*) FROM pg_stat_activity WHERE application_name='p64_blocker' "
                        "AND state='idle in transaction'") == "1":
                break
            time.sleep(.02)
        else:
            raise Failure("P64_BLOCKER_NOT_READY")
        for i, payload in enumerate(requests):
            worker = subprocess.Popen(["docker", "exec", "-i", lab.writer_server, "php",
                "/repo/tools/p64-authored-draft-probe.php", "stage"],
                stdin=subprocess.PIPE, stdout=subprocess.PIPE, stderr=subprocess.PIPE)
            worker.stdin.write(json.dumps({**payload, "application": "p64_" + label + "_" + str(i)}).encode())
            worker.stdin.close()
            worker.stdin = None
            workers.append(worker)
        deadline = time.monotonic() + 1.8
        while time.monotonic() < deadline:
            waiting = lab._sql("src", "SELECT count(*) FROM pg_stat_activity "
                "WHERE application_name LIKE 'p64_" + label + "_%' AND wait_event='advisory' "
                "AND cardinality(pg_blocking_pids(pid))>0")
            if waiting == "2":
                break
            time.sleep(.02)
        else:
            raise Failure("P64_CONCURRENT_WAIT_NOT_OBSERVED")
        blocker.stdin.write(b"COMMIT;\n")
        blocker.stdin.flush()
        outputs = []
        for worker in workers:
            stdout, stderr = worker.communicate(timeout=8)
            outputs.append(decode(subprocess.CompletedProcess([], worker.returncode, stdout, stderr)))
        require(sorted(item["decision"] for item in outputs) == sorted(expected), "P64_CONCURRENT_DECISIONS")
        return outputs
    finally:
        for worker in workers:
            if worker.poll() is None:
                worker.kill()
                worker.communicate(timeout=5)
        if blocker.poll() is None:
            try:
                blocker.communicate(b"ROLLBACK;\n", timeout=5)
            except subprocess.TimeoutExpired:
                blocker.kill()
                blocker.communicate(timeout=5)


def exercise(lab: CreationLab) -> dict:
    lab.start_server()
    lab.start_writer()
    initial = lab.stats()
    cookie, csrf = session(lab)
    save, shown = preview(lab, cookie, csrf, lead="10,50", client="20.00")
    require(len(shown.proposals) == 2 and lab.stats() == initial, "P64_PREVIEW_NO_DATABASE_WRITE")
    sent = lab.request(cookie=cookie, fields=save, discard=True)
    require(sent == {"request_sent": True, "response_read": False}, "P64_LOST_RESPONSE_ACTUAL_CLIENT")
    deadline = time.monotonic() + 5
    while time.monotonic() < deadline:
        observed = lab.stats()
        if observed["batches"] == 1 and observed["drafts"] == observed["authors"] == 2:
            break
        time.sleep(.05)
    else:
        raise Failure("P64_LOST_RESPONSE_COMMIT_NOT_OBSERVED")
    assert_saved(lab.request(cookie=cookie, fields=save), shown.proposals)
    require(lab.stats() == observed, "P64_LOST_RESPONSE_IDEMPOTENT")
    for ref in shown.proposals:
        assert_detail(lab, ref)
    # One proposal uses the fixed detail redirect; no empty batch is stored.
    one_save, one = preview(lab, cookie, csrf, visits="100")
    require(len(one.proposals) == 1, "P64_ONE_DRAFT_PREVIEW")
    assert_saved(lab.request(cookie=cookie, fields=one_save), one.proposals)
    empty_save, empty = preview(lab, cookie, csrf, visits="0")
    require(empty.proposals == [], "P64_EMPTY_PREVIEW")
    before = lab.stats()
    require(lab.request(cookie=cookie, fields=empty_save)["status"] == 503, "P64_NO_EMPTY_BATCH")
    require(lab.stats() == before, "P64_EMPTY_SAVE_NO_CHANGE")
    other_cookie, other_csrf = session(lab)
    before = lab.stats()
    csrf_raw = urlencode({"action": "preview", "csrf": csrf, "weekly_visits": "120", "lead_rate": "10", "client_rate": "20"})
    negatives = [
        ({"fields": {**save, "csrf": "0" * 64}}, 403),
        ({"fields": save, "origin": "https://foreign.invalid"}, 403),
        ({"fields": save, "origin": "null"}, 403),
        ({"fields": save, "origin": ""}, 403),
        ({"fields": save, "host": "localhost:8787"}, 403),
        ({"fields": {**save, "tenant_ref": "SYNTHETIC_TENANT_B"}}, 400),
        ({"fields": {**save, "author_ref": "SYNTHETIC_OTHER"}}, 400),
        ({"fields": {**save, "request_ref": "SYNTHETIC_OTHER"}}, 400),
        ({"fields": {**save, "weekly_visits": "999"}}, 400),
        ({"fields": {**save, "preview_id": "0" * 48}}, 409),
        ({"raw": csrf_raw + "&weekly%5Fvisits=120"}, 400),
        ({"raw": csrf_raw.replace("weekly_visits=", "weekly_visits%5B%5D=")}, 400),
        ({"raw": csrf_raw.replace("lead_rate=10", "lead_rate=1e2")}, 400),
        ({"raw": csrf_raw.replace("lead_rate=10", "lead_rate=1%2C000")}, 400),
        ({"raw": csrf_raw.replace("lead_rate=10", "lead_rate=100%2C01")}, 400),
        ({"raw": csrf_raw.replace("weekly_visits=120", "weekly_visits=120.0")}, 400),
        ({"raw": csrf_raw.replace("csrf=", "csrf=%Q0")}, 400),
        ({"raw": csrf_raw, "content_type": "application/json"}, 415),
        ({"path": PAGE + "?tenant=SYNTHETIC_TENANT_B"}, 400),
        ({"method": "PUT"}, 405),
    ]
    for args, expected in negatives:
        require(lab.request(cookie=cookie, **args)["status"] == expected, "P64_HTTP_NEGATIVE")
    require(lab.request(cookie=other_cookie, fields={**save, "csrf": other_csrf})["status"] == 409,
            "P64_PREVIEW_NOT_TRANSFERABLE")
    require(lab.stats() == before, "P64_HTTP_NEGATIVES_NO_MUTATION")
    for _ in range(2):
        preview(lab, cookie, csrf)
    require(lab.request(cookie=cookie, fields={"action": "preview", "csrf": csrf,
        "weekly_visits": "120", "lead_rate": "10", "client_rate": "20"})["status"] == 409,
        "P64_PENDING_PREVIEW_CAPACITY")
    assert_saved(lab.request(cookie=cookie, fields=save), shown.proposals)
    require(lab.stats() == before, "P64_PREVIEW_CAP_NO_EVICTION_OR_WRITE")
    require(lab.stage("SYNTHETIC_P64_PRIVATE_B", connection="b")["decision"] == "LAB_AUTHORED_DRAFTS_STORED",
            "P64_SECOND_TENANT_CONTROL")
    before = lab.stats()
    require(lab.stage("SYNTHETIC_P64_WRONG_TENANT", tenant="SYNTHETIC_TENANT_B")["decision"] == "DENY",
            "P64_WRITER_TENANT_BINDING")
    permissions = lab.probe("permissions")
    guards = lab.probe("guards")
    require(all(permissions.values()) and all(guards.values()) and lab.stats() == before,
            "P64_LIMITED_WRITER_GUARDS")
    # Catalog/policy drift is denied before interpreting data or inserting.
    profile_cases = [
        ("GRANT SELECT(command_canonical) ON aia_p51_lab.preview_batches TO aia_p64_ci_write_a WITH GRANT OPTION",
         "REVOKE GRANT OPTION FOR SELECT(command_canonical) ON aia_p51_lab.preview_batches FROM aia_p64_ci_write_a"),
        ("GRANT UPDATE ON aia_p59_lab.draft_authors TO aia_p64_ci_write_a",
         "REVOKE UPDATE ON aia_p59_lab.draft_authors FROM aia_p64_ci_write_a; GRANT UPDATE(tenant_ref) ON aia_p59_lab.draft_authors TO aia_p64_ci_write_a"),
        ("ALTER POLICY p64_write_a_lock ON aia_p51_lab.review_drafts WITH CHECK(true)",
         "ALTER POLICY p64_write_a_lock ON aia_p51_lab.review_drafts WITH CHECK(false)"),
        ("ALTER POLICY p64_write_a_select ON aia_p59_lab.draft_authors USING(tenant_ref='SYNTHETIC_TENANT_ A')",
         "ALTER POLICY p64_write_a_select ON aia_p59_lab.draft_authors USING(tenant_ref='SYNTHETIC_TENANT_A')"),
        ("GRANT SELECT ON aia_p57_lab.reviewers TO aia_p64_ci_write_a",
         "REVOKE SELECT ON aia_p57_lab.reviewers FROM aia_p64_ci_write_a"),
    ]
    for mutate, restore in profile_cases:
        lab._sql("src", mutate)
        require(lab.probe("profile") == {"accepted": False}, "P64_DRIFTED_PROFILE_DENIED")
        require(lab.request(cookie=cookie, fields=save)["status"] == 503, "P64_HTTP_DRIFT_DENIED")
        require(lab.stats() == before, "P64_PROFILE_NEGATIVE_NO_MUTATION")
        lab._sql("src", restore)
        require(lab.probe("profile") == {"accepted": True}, "P64_RESTORED_PROFILE")
        assert_saved(lab.request(cookie=cookie, fields=save), shown.proposals)
    # A second-author trigger fails after the first insert. Its sequence is
    # deliberately nontransactional evidence that both inserts were reached.
    lab._sql("src", "CREATE SEQUENCE public.p64_author_attempts; "
        "GRANT USAGE,SELECT ON SEQUENCE public.p64_author_attempts TO aia_p64_ci_write_a; "
        "CREATE FUNCTION public.p64_fail_second_author() RETURNS trigger LANGUAGE plpgsql AS $$ BEGIN "
        "IF nextval('public.p64_author_attempts')=2 THEN RAISE EXCEPTION 'P64_SECOND_AUTHOR' USING ERRCODE='P6401'; "
        "END IF; RETURN NEW; END $$; CREATE TRIGGER p64_second_author BEFORE INSERT ON aia_p59_lab.draft_authors "
        "FOR EACH ROW EXECUTE FUNCTION public.p64_fail_second_author();")
    before = lab.stats()
    require(lab.stage("SYNTHETIC_P64_SECOND_AUTHOR")["decision"] == "DENY", "P64_SECOND_AUTHOR_DENIED")
    require(lab._sql("src", "SELECT last_value FROM public.p64_author_attempts") == "2"
            and lab.stats() == before, "P64_ALL_WRITES_ROLLED_BACK")
    lab._sql("src", "DROP TRIGGER p64_second_author ON aia_p59_lab.draft_authors; "
        "DROP FUNCTION public.p64_fail_second_author(); DROP SEQUENCE public.p64_author_attempts")
    control = lab.stage("SYNTHETIC_P64_SECOND_AUTHOR")
    require(control["decision"] == "LAB_AUTHORED_DRAFTS_STORED", "P64_ROLLBACK_RETRY_CONTROL")
    before = lab.stats()
    require(lab.stage("SYNTHETIC_P64_SECOND_AUTHOR", visits=121)["decision"] == "DENY"
            and lab.stats() == before, "P64_REPLAY_CHANGED_INPUT_DENIED")
    # Remove one fixture binding as the administrator; replay must not repair it.
    missing = control["proposal_refs"][0]
    lab._sql("src", "DELETE FROM aia_p59_lab.draft_authors WHERE tenant_ref='SYNTHETIC_TENANT_A' "
             "AND proposal_ref='" + missing + "'")
    before = lab.stats()
    require(lab.stage("SYNTHETIC_P64_SECOND_AUTHOR")["decision"] == "DENY" and lab.stats() == before,
            "P64_REPLAY_NEVER_REPAIRS_AUTHOR")
    lab._sql("src", "INSERT INTO aia_p59_lab.draft_authors "
        "(tenant_ref,proposal_ref,author_ref,draft_sha256,source) SELECT tenant_ref,proposal_ref,"
        "'SYNTHETIC_P64_AUTHOR_A',draft_sha256,'SYNTHETIC_CI_AUTHOR_FIXTURE' FROM aia_p51_lab.review_drafts "
        "WHERE tenant_ref='SYNTHETIC_TENANT_A' AND proposal_ref='" + missing + "'")
    require(lab.stage("SYNTHETIC_P64_SECOND_AUTHOR")["decision"] == "LAB_AUTHORED_DRAFTS_REPLAY",
            "P64_AUTHOR_RESTORED_CONTROL")
    before = lab.stats()
    same = concurrent(lab, [{"request": "SYNTHETIC_P64_RACE"}] * 2,
                      ["LAB_AUTHORED_DRAFTS_STORED", "LAB_AUTHORED_DRAFTS_REPLAY"], "same")
    after = lab.stats()
    require(after["batches"] == before["batches"] + 1 and after["drafts"] == before["drafts"] + 2
            and after["authors"] == before["authors"] + 2
            and same[0]["proposal_refs"] == same[1]["proposal_refs"], "P64_CONCURRENT_EXACT_IDEMPOTENCE")
    capacity = lab.probe("capacity-fixture")
    require(capacity["tenant_drafts"] == 127, "P64_LAST_SLOT_FIXTURE")
    race = concurrent(lab, [{"request": "SYNTHETIC_P64_LAST_A", "visits": 100},
                            {"request": "SYNTHETIC_P64_LAST_B", "visits": 100}],
                      ["LAB_AUTHORED_DRAFTS_STORED", "DENY"], "capacity")
    winner = next(item for item in race if item["decision"] == "LAB_AUTHORED_DRAFTS_STORED")
    require(lab._sql("src", "SELECT count(*) FROM aia_p51_lab.review_drafts "
                    "WHERE tenant_ref='SYNTHETIC_TENANT_A'") == "128", "P64_CAP_NEVER_EXCEEDED")
    before = lab.stats()
    require(lab.stage(winner["request_ref"], visits=100)["decision"] == "LAB_AUTHORED_DRAFTS_REPLAY"
            and lab.stats() == before, "P64_REPLAY_AT_CAPACITY")
    batch_limit = lab.probe("batch-capacity-fixture")
    require(batch_limit == {"tenant_batches": 128, "tenant_drafts": 127}, "P64_BATCH_LIMIT_FIXTURE")
    before = lab.stats()
    require(lab.stage("SYNTHETIC_P64_BATCH_OVER", visits=100)["decision"] == "DENY"
            and lab.stats() == before, "P64_BATCH_CAPACITY_NO_RESIDUAL")
    assert_saved(lab.request(cookie=cookie, fields=save), shown.proposals)
    assert_detail(lab, shown.proposals[0])
    return {
        "schema": "AIA_P64_LOCAL_AUTHORED_DRAFT_JOURNEY_VERIFIED_V1",
        "preview_and_rates_pt_br": True, "preview_has_no_database_write": True,
        "http_to_atomic_batch_and_p63_detail": True, "lost_response_reuses_request": True,
        "http_negative_cases": len(negatives) + 1, "writer_permissions": permissions, "transaction_guards": guards,
        "profile_drift_denied": len(profile_cases), "second_author_failure_rolls_back_every_row": True,
        "missing_author_replay_does_not_repair": True, "concurrent_exact_replay": True,
        "concurrent_last_capacity_slot": True, "batch_and_draft_capacity": 128, "replay_at_capacity": True,
        "fixed_reader_redirect_and_csp": True, "pending_preview_cap_preserves_retry": True,
        "browser_engine_executed": False,
        "read_and_write_processes_separate": True, "host_published_ports": 0,
        "network_mode": "none", "real_human_identity": False, "human_approval_granted": False,
        "n8n_executions": 0, "paid_ai_calls": 0, "go_for_installation": False,
    }


def main() -> dict:
    require(os.environ.get("GITHUB_ACTIONS") == "true"
            and os.environ.get("GITHUB_REPOSITORY") == "rafhael100/imagine-ai-agents"
            and os.environ.get("P24_RUNNER_ENVIRONMENT") == "github-hosted", "P64_CI_ONLY")
    require(run(["docker", "context", "show"], "P64_DOCKER_CONTEXT").stdout.strip() == b"default",
            "P64_DEFAULT_DOCKER_REQUIRED")
    context = json.loads(run(["docker", "context", "inspect", "default"], "P64_DOCKER_ENDPOINT").stdout)
    require(context[0]["Endpoints"]["docker"]["Host"] == "unix:///var/run/docker.sock", "P64_LOCAL_DOCKER_REQUIRED")
    with tempfile.TemporaryDirectory(prefix="aia-p64-", dir=os.environ.get("RUNNER_TEMP")) as tmp:
        lab = CreationLab("aia-p64-" + secrets.token_hex(7), Path(tmp) / "private")
        try:
            lab.prepare()
            return exercise(lab)
        finally:
            lab.cleanup()


if __name__ == "__main__":
    try:
        print(json.dumps(main(), sort_keys=True))
        print("AIA_P64_LOCAL_AUTHORED_DRAFT_JOURNEY_PASS NO_GO")
    except (Failure, OSError, ValueError, subprocess.SubprocessError) as error:
        print(str(error) if isinstance(error, Failure) else "P64_RUNTIME_FAILURE", file=sys.stderr)
        raise SystemExit(1)
