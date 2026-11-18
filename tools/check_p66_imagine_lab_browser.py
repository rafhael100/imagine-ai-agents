#!/usr/bin/env python3
"""P66 Chromium journey against the original isolated P64 PostgreSQL lab.

The browser receives no database credentials. Comparative ``before`` images
use the two exact RC76 PHP views, captured afterwards against the same rows.
"""
from __future__ import annotations

import base64
import hashlib
import json
import os
from pathlib import Path
import re
import secrets
import selectors
import subprocess
import sys
import tempfile
import time

ROOT = Path(__file__).resolve().parents[1]
REPOSITORY = "rafhael100/imagine-ai-agents"
BASE = "bd50ab49113e775490cb55d76b088731ec108cc9"
CENTRAL = "c08688b16e61d8d1ecc4d8c0ed4da8758a687ee8"
OFFICIAL_IMAGE = "mcr.microsoft.com/playwright:v1.56.1-noble"
PAGES = ("p64-create-drafts-local.php", "p56-live-pg-review-local.php")


class ProbeFailure(Exception):
    pass


def need(condition, label):
    if not condition:
        raise ProbeFailure(label)


def command(args, label, *, data=None, timeout=120, check=True):
    result = subprocess.run(args, input=data, capture_output=True, timeout=timeout, check=False)
    need(not check or result.returncode == 0, label)
    return result


def git(checkout, *args):
    return command(["git", "-C", str(checkout), *args], "P66_GIT_PROVENANCE").stdout


def directory(name):
    raw = os.environ.get(name, "")
    path = Path(raw)
    need(raw and path.is_absolute() and path.is_dir() and not path.is_symlink(), "P66_DIRECTORY")
    return path.resolve()


def preflight():
    need(os.environ.get("GITHUB_ACTIONS") == "true"
         and os.environ.get("GITHUB_REPOSITORY") == REPOSITORY
         and os.environ.get("GITHUB_EVENT_NAME") == "pull_request"
         and os.environ.get("P24_RUNNER_ENVIRONMENT") == "github-hosted", "P66_REAL_CI_REQUIRED")
    need(re.fullmatch(r"[a-f0-9]{40}", os.environ.get("GITHUB_SHA", "")), "P66_EVENT_SHA")
    need(not os.environ.get("DOCKER_HOST") and not os.environ.get("DOCKER_CONTEXT"), "P66_LOCAL_DOCKER_ONLY")
    event_path = Path(os.environ.get("GITHUB_EVENT_PATH", ""))
    need(event_path.is_file() and not event_path.is_symlink()
         and event_path.stat().st_size <= 1048576, "P66_EVENT_FILE")
    event = json.loads(event_path.read_text())
    request = event["pull_request"]
    need(event["repository"]["full_name"] == REPOSITORY and event["repository"]["private"] is False
         and request["head"]["repo"]["full_name"] == REPOSITORY
         and request["base"]["repo"]["full_name"] == REPOSITORY
         and request["base"]["sha"] == BASE
         and request["base"]["ref"] == "test/aia-rc76-op01-combined-evidence-2026-11-17"
         and request["head"]["ref"] == "feat/aia-p66-imagine-operator-ui-2026-11-17", "P66_PR_PROVENANCE")
    checkout = directory("AIA_P66_SOURCE_CHECKOUT")
    temporary = directory("RUNNER_TEMP")
    source = os.environ.get("AIA_P66_SOURCE_SHA", "")
    tree = os.environ.get("AIA_P66_SOURCE_TREE", "")
    need(re.fullmatch(r"[a-f0-9]{40}", source) and re.fullmatch(r"[a-f0-9]{40}", tree)
         and source == request["head"]["sha"]
         and git(checkout, "rev-parse", "HEAD").decode().strip() == source, "P66_SOURCE_HEAD")
    git(checkout, "diff", "--quiet")
    git(checkout, "diff", "--cached", "--quiet")
    git(checkout, "merge-base", "--is-ancestor", BASE, source)
    need(git(checkout, "cat-file", "-t", tree).strip() == b"tree", "P66_SOURCE_TREE")
    combined = git(checkout, "merge-tree", "--write-tree", "--name-only", CENTRAL, source)
    need(combined.splitlines()[0].decode() == tree, "P66_EXACT_COMBINED_TREE")
    need(ROOT != checkout and ROOT.is_relative_to(temporary), "P66_TEMPORARY_EXPORT_REQUIRED")
    # Verify every exported tracked blob, not just a caller-supplied tree name.
    for entry in git(checkout, "ls-tree", "-rz", "-r", tree).split(b"\0"):
        if not entry:
            continue
        metadata, name = entry.split(b"\t", 1)
        mode, kind, expected = metadata.split()
        relative = Path(os.fsdecode(name))
        need(not relative.is_absolute() and ".." not in relative.parts
             and kind == b"blob" and mode in (b"100644", b"100755"), "P66_SOURCE_ENTRY")
        path = ROOT / relative
        need(path.is_file() and not path.is_symlink() and path.resolve().is_relative_to(ROOT), "P66_SOURCE_FILE")
        content = path.read_bytes()
        actual = hashlib.sha1(b"blob " + str(len(content)).encode() + b"\0" + content).hexdigest()
        need(actual == expected.decode(), "P66_EXPORTED_BLOB_CHANGED")
    dependencies = directory("AIA_P66_PLAYWRIGHT_DIR")
    artifacts = directory("AIA_P66_ARTIFACT_DIR")
    need(all(path.is_relative_to(temporary) for path in (dependencies, artifacts)), "P66_RUNNER_PATHS")
    paths = (ROOT, checkout, dependencies, artifacts)
    need(all(not left.is_relative_to(right) and not right.is_relative_to(left)
             for index, left in enumerate(paths) for right in paths[index + 1:]), "P66_SEPARATE_PATHS")
    need(not any(artifacts.iterdir()), "P66_EMPTY_ARTIFACT_DIRECTORY")
    package = dependencies / "node_modules/playwright/package.json"
    need(package.is_file() and json.loads(package.read_text())["version"] == "1.56.1", "P66_PLAYWRIGHT_VERSION")
    image = os.environ.get("AIA_P66_PLAYWRIGHT_IMAGE", "")
    need(re.fullmatch(r"(?:sha256:|mcr\.microsoft\.com/playwright@sha256:)[a-f0-9]{64}", image), "P66_BROWSER_IMAGE_PIN")
    return checkout, temporary, dependencies, artifacts, image


def verify_docker(image):
    need(command(["docker", "context", "show"], "P66_DOCKER_CONTEXT").stdout.strip() == b"default",
         "P66_DEFAULT_DOCKER")
    context = json.loads(command(["docker", "context", "inspect", "default"], "P66_DOCKER_ENDPOINT").stdout)
    need(context[0]["Endpoints"]["docker"]["Host"] == "unix:///var/run/docker.sock", "P66_DOCKER_SOCKET")
    selected = json.loads(command(["docker", "image", "inspect", image], "P66_LOCAL_BROWSER_IMAGE").stdout)[0]
    official = json.loads(command(["docker", "image", "inspect", OFFICIAL_IMAGE], "P66_OFFICIAL_BROWSER_IMAGE").stdout)[0]
    need(selected["Id"] == official["Id"], "P66_OFFICIAL_IMAGE_MATCH")


def scanner_regressions(lab):
    """Exercise the actual allowlists against both real HTTP documents in memory."""
    from check_p56_live_pg_readonly_http import Scan
    from check_p62_live_signed_review import Failure
    from check_p64_authored_draft_journey import form

    writer = lab.request()
    writer_body, _ = form(writer)
    status, reader_body, _ = lab.http()
    need(status == 200, "P66_SCANNER_READER_HTTP")

    def accepted(body, create):
        if create:
            try:
                form({**writer, "body_b64": base64.b64encode(body.encode()).decode()})
                return True
            except Failure:
                return False
        scan = Scan(allow_create=True, allow_detail=True)
        scan.feed(body)
        return scan.valid_shell() and not scan.actions and not scan.remotes

    negatives = 0
    for body, create in ((writer_body, True), (reader_body, False)):
        need(accepted(body, create), "P66_SCANNER_POSITIVE_CONTROL")
        logo = re.search(r'<img\b[^>]*data-imagine-brand="original"[^>]*>', body)
        need(logo is not None, "P66_SCANNER_ORIGINAL_IMAGE")
        tag = logo.group()
        src = re.search(r'src="([^"]+)"', tag).group(1)
        nav = 'href="/p64-create-drafts-local.php"' if create else 'href="?kind=all"'
        need(nav in body, "P66_SCANNER_ORIGINAL_NAVIGATION")
        mutations = [body.replace(tag, "", 1), body.replace(tag, tag + tag, 1),
                     body.replace(src, src[:22] + ("A" if src[22] != "A" else "B") + src[23:], 1),
                     *[body.replace(src, replacement, 1) for replacement in
                       ("https://external.invalid/logo.png", "/assets/logo-imagine.png", "data:image/png;base64,aGk=")],
                     body.replace(tag, tag[:-1] + ' onerror="void(0)">', 1),
                     body.replace(tag, tag[:-1] + ' srcset="/unexpected.png 2x">', 1),
                     body + '<script>void(0)</script>', body + '<iframe></iframe>',
                     body + '<a href="https://external.invalid/">Unexpected</a>',
                     body.replace(nav, 'href="https://external.invalid/" ' + nav, 1)]
        for index, mutated in enumerate(mutations):
            need(mutated != body and not accepted(mutated, create),
                 "P66_SCANNER_NEGATIVE_" + ("WRITER_" if create else "READER_") + str(index + 1))
            negatives += 1
    standalone = Scan()
    standalone.feed(reader_body)
    need(not standalone.valid_shell() or standalone.remotes, "P66_CREATOR_NOT_GRANTED_TO_STANDALONE_READER")
    return negatives + 1


def browser(lab, dependencies, artifacts, image, mode, checkpoint, detail=None):
    name = lab.project + "-chromium-" + mode
    need(command(["docker", "container", "inspect", name], "P66_NEW_BROWSER", check=False).returncode != 0,
         "P66_PREEXISTING_BROWSER")
    lab.extra[name] = name
    args = ["docker", "create", "--pull", "never", "-i", "--name", name,
            "--label", "imagine.p25.owner=" + lab.token,
            "--network", "container:" + lab.containers["src"],
            "--read-only", "--cap-drop", "ALL", "--security-opt", "no-new-privileges:true",
            "--user", str(os.getuid()) + ":" + str(os.getgid()),
            "--memory", "1g", "--cpus", "2", "--pids-limit", "256", "--shm-size", "256m",
            "--tmpfs", "/tmp:rw,noexec,nosuid,size=256m,mode=1777",
            "--mount", "type=bind,src=" + str(ROOT) + ",dst=/repo,readonly",
            "--mount", "type=bind,src=" + str(dependencies) + ",dst=/deps,readonly",
            "--mount", "type=bind,src=" + str(artifacts) + ",dst=/artefacts",
            "--workdir", "/repo", "-e", "AIA_P66_BROWSER_CI=isolated-p66-v1",
            "-e", "XDG_CONFIG_HOME=/tmp/p66-config", "-e", "XDG_CACHE_HOME=/tmp/p66-cache"]
    for key in ("GITHUB_ACTIONS", "GITHUB_REPOSITORY", "AIA_P66_SOURCE_SHA", "AIA_P66_SOURCE_TREE"):
        args += ["-e", key + "=" + os.environ[key]]
    args += ["--entrypoint", "node", image, "/repo/tests/Browser/p66_imagine_lab.cjs", mode]
    if detail is not None:
        need(re.fullmatch(r"SYNTHETIC_PROPOSAL_[A-F0-9]{24}", detail), "P66_COMPARISON_REFERENCE")
        args.append(detail)
    identity = command(args, "P66_CREATE_BROWSER").stdout.decode().strip()
    need(re.fullmatch(r"[a-f0-9]{64}", identity), "P66_BROWSER_ID")
    lab.extra[name] = identity
    inspect = json.loads(command(["docker", "inspect", identity], "P66_BROWSER_INSPECT").stdout)[0]
    pg = json.loads(command(["docker", "inspect", lab.containers["src"]], "P66_PG_INSPECT").stdout)[0]
    host = inspect["HostConfig"]
    bindings = {mount["Destination"]: mount for mount in inspect["Mounts"] if mount["Type"] == "bind"}
    need(pg["HostConfig"]["NetworkMode"] == "none"
         and pg["Config"]["Labels"].get("imagine.p25.owner") == lab.token
         and inspect["Config"]["Labels"].get("imagine.p25.owner") == lab.token
         and host["NetworkMode"] == "container:" + lab.containers["src"]
         and not host.get("PortBindings") and not host["Privileged"] and host["ReadonlyRootfs"]
         and host["CapDrop"] == ["ALL"] and "no-new-privileges:true" in host["SecurityOpt"]
         and set(bindings) == {"/repo", "/deps", "/artefacts"}
         and not bindings["/repo"]["RW"] and not bindings["/deps"]["RW"]
         and bindings["/artefacts"]["RW"], "P66_BROWSER_ISOLATION")
    result = None
    phase = "startup"
    with tempfile.TemporaryFile() as errors:
        process = subprocess.Popen(["docker", "start", "-a", "-i", identity],
                                   stdin=subprocess.PIPE, stdout=subprocess.PIPE, stderr=errors)
        try:
            selector = selectors.DefaultSelector()
            selector.register(process.stdout, selectors.EVENT_READ)
            pending = b""
            deadline = time.monotonic() + 240
            while True:
                need(time.monotonic() < deadline, "P66_BROWSER_TIMEOUT_" + phase.upper())
                if not selector.select(timeout=1):
                    continue
                block = os.read(process.stdout.fileno(), 16384)
                if not block:
                    break
                pending += block
                need(len(pending) < 65536, "P66_BROWSER_OUTPUT_LIMIT")
                while b"\n" in pending:
                    line, pending = pending.split(b"\n", 1)
                    if line.startswith(b"P66_CHECKPOINT "):
                        phase = line[15:].decode("ascii")
                        need(phase in ("preview", "saved", "complete") and mode == "after", "P66_CHECKPOINT_NAME")
                        checkpoint(phase)
                        process.stdin.write(("P66_ACK " + phase + "\n").encode())
                        process.stdin.flush()
                    elif line.startswith(b"P66_RESULT "):
                        need(result is None, "P66_SINGLE_BROWSER_RESULT")
                        result = json.loads(line[11:])
                    elif re.fullmatch(rb"P66_BROWSER_DENIED_[A-Z0-9_]+", line):
                        raise ProbeFailure(line.decode())
                    else:
                        raise ProbeFailure("P66_UNEXPECTED_BROWSER_OUTPUT")
            need(not pending and process.wait(timeout=10) == 0 and isinstance(result, dict), "P66_BROWSER_FINISH")
            selector.close()
        finally:
            if process.poll() is None:
                process.kill()
                process.communicate(timeout=10)
            if process.stdin:
                process.stdin.close()
            if process.stdout:
                process.stdout.close()
            lab.remove_owned(name)
    return result


def main():
    need(len(sys.argv) == 1, "P66_ARGUMENTS")
    checkout, temporary, dependencies, artifacts, image = preflight()
    verify_docker(image)
    # Import only after real event and exported source verification. No old main runs.
    from check_p64_authored_draft_journey import CreationLab

    class ComparisonLab(CreationLab):
        originals = None

        def args(self, scope):
            args = super().args(scope)
            if self.originals is not None and scope in ("server", "writer"):
                for filename in PAGES:
                    args += ["--mount", "type=bind,src=" + str(self.originals / filename)
                             + ",dst=/repo/examples/" + filename + ",readonly"]
            return args

    with tempfile.TemporaryDirectory(prefix="aia-p66-", dir=temporary) as temporary_lab:
        scratch = Path(temporary_lab)
        originals = scratch / "original-views"
        originals.mkdir(mode=0o700)
        for filename in PAGES:
            path = "examples/" + filename
            data = git(checkout, "show", BASE + ":" + path)
            expected = git(checkout, "rev-parse", BASE + ":" + path).decode().strip()
            need(hashlib.sha1(b"blob " + str(len(data)).encode() + b"\0" + data).hexdigest() == expected,
                 "P66_ORIGINAL_VIEW_BLOB")
            (originals / filename).write_bytes(data)
            (originals / filename).chmod(0o444)
        lab = ComparisonLab("aia-p66-" + secrets.token_hex(7), scratch / "private")
        try:
            lab.prepare()
            lab.start_server()
            lab.start_writer()
            initial = lab.stats()
            need(all(initial[key] == 0 for key in ("batches", "drafts", "authors", "votes")), "P66_EMPTY_FIXTURE")
            scanner_negatives = scanner_regressions(lab)
            need(lab.stats() == initial, "P66_SCANNER_READS_PRESERVE_ROWS")
            phases = []
            saved = None

            def checkpoint(phase):
                nonlocal saved
                need(phase == ("preview", "saved", "complete")[len(phases)] if len(phases) < 3 else False,
                     "P66_CHECKPOINT_ORDER")
                current = lab.stats()
                if phase == "preview":
                    need(current == initial, "P66_PREVIEW_READ_ONLY")
                elif phase == "saved":
                    need({key: current[key] for key in ("batches", "drafts", "authors", "votes")}
                         == {"batches": 1, "drafts": 2, "authors": 2, "votes": 0}, "P66_EXACT_CREATION")
                    saved = current
                else:
                    need(current == saved, "P66_NAVIGATION_READ_ONLY")
                phases.append(phase)

            after = browser(lab, dependencies, artifacts, image, "after", checkpoint)
            detail = after.get("detail_ref", "")
            need(re.fullmatch(r"SYNTHETIC_PROPOSAL_[A-F0-9]{24}", detail), "P66_DETAIL_REFERENCE")
            need(phases == ["preview", "saved", "complete"]
                 and after == {"schema": "AIA_P66_BROWSER_AFTER_V1", "detail_ref": detail,
                               "screenshots": 6, "posts": 4, "css_200_percent_reflow": True,
                               "native_browser_zoom_tested": False}, "P66_COMPLETE_JOURNEY")
            # Only the two owned HTTP servers restart; PG, rows and roles stay put.
            lab.remove_owned(lab.project + "-writer-http")
            lab.remove_owned(lab.project + "-http")
            lab.writer_server = lab.server = None
            lab.originals = originals
            lab.start_server()
            lab.start_writer()
            need(lab.stats() == saved, "P66_RESTART_PRESERVES_ROWS")
            before = browser(lab, dependencies, artifacts, image, "before", checkpoint, detail)
            need(before == {"schema": "AIA_P66_BROWSER_BEFORE_V1", "screenshots": 3, "posts": 0}
                 and lab.stats() == saved, "P66_ORIGINAL_CAPTURE_READ_ONLY")
            expected = {"p66-before-" + page + "-desktop.png" for page in ("create", "queue", "detail")}
            expected |= {"p66-after-" + page + "-" + size + ".png"
                         for page in ("create", "queue", "detail") for size in ("desktop", "mobile")}
            need({path.name for path in artifacts.iterdir()} == expected
                 and all(path.is_file() and not path.is_symlink() and 0 < path.stat().st_size <= 5000000
                         for path in artifacts.iterdir()), "P66_EXACT_SCREENSHOTS")
            return {"schema": "AIA_P66_IMAGINE_LAB_CHROMIUM_V1", "screenshots": 9,
                    "presentation_scanner_negatives": scanner_negatives,
                    "browser_engine_executed": True, "viewports": [360, 768, 1440],
                    "css_200_percent_reflow": True, "native_browser_zoom_tested": False,
                    "before_version_captured_after_journey": True,
                    "same_rows_in_comparison": True, "preview_and_reads_without_db_write": True,
                    "created_batches": 1, "created_drafts": 2, "created_authors": 2,
                    "browser_credential_mounts": 0, "host_published_ports": 0,
                    "network_mode": "shared_none_namespace", "paid_ai_calls": 0,
                    "go_for_installation": False}
        finally:
            lab.cleanup()


if __name__ == "__main__":
    try:
        print(json.dumps(main(), sort_keys=True))
        print("AIA_P66_IMAGINE_LAB_CHROMIUM_PASS NO_GO")
    except Exception as error:
        # Helper exceptions are never dumped: they may contain captured process data.
        label = str(error) if isinstance(error, ProbeFailure) else "P66_RUNTIME_FAILURE"
        print(label if re.fullmatch(r"P66_[A-Z0-9_]+", label) else "P66_RUNTIME_FAILURE", file=sys.stderr)
        raise SystemExit(1)
