#!/usr/bin/env python3
"""Verify the actual P67 PR and export its exact additive Central composition."""
from __future__ import annotations

import argparse
import hashlib
import io
import json
import os
from pathlib import Path
import re
import subprocess
import sys
import tarfile
import tempfile

ROOT = Path(__file__).resolve().parents[1]
REPOSITORY = "rafhael100/imagine-ai-agents"
BASE = "0de0d18b13138e35bf6cb427cc1b4ba5a408cda4"
CENTRAL = "c08688b16e61d8d1ecc4d8c0ed4da8758a687ee8"
BASE_REF = "feat/aia-p66-imagine-operator-ui-2026-11-17"
HEAD_REF = "feat/aia-p67-crm-owner-drafts-2026-11-17"


def need(value, label):
    if not value:
        raise RuntimeError(label)


def command(args, *, cwd=ROOT):
    result = subprocess.run(args, cwd=cwd, capture_output=True, timeout=120)
    need(result.returncode == 0, "P67_SOURCE_COMMAND_DENIED")
    return result.stdout


def git(*args):
    return command(["git", *args])


def entries(tree):
    output = {}
    for entry in git("ls-tree", "-rz", "-r", tree).split(b"\0"):
        if not entry:
            continue
        metadata, name = entry.split(b"\t", 1)
        mode, kind, sha = metadata.split()
        path = Path(os.fsdecode(name))
        need(kind == b"blob" and mode in (b"100644", b"100755")
             and not path.is_absolute() and ".." not in path.parts, "P67_SOURCE_ENTRY_DENIED")
        output[str(path)] = (mode.decode(), sha.decode())
    return output


def preflight():
    need(os.environ.get("GITHUB_ACTIONS") == "true"
         and os.environ.get("GITHUB_REPOSITORY") == REPOSITORY
         and os.environ.get("GITHUB_EVENT_NAME") == "pull_request"
         and os.environ.get("P24_RUNNER_ENVIRONMENT") == "github-hosted",
         "P67_GITHUB_HOSTED_PR_REQUIRED")
    need(re.fullmatch(r"[a-f0-9]{40}", os.environ.get("GITHUB_SHA", "")), "P67_EVENT_SHA_REQUIRED")
    need(not os.environ.get("DOCKER_HOST") and not os.environ.get("DOCKER_CONTEXT"), "P67_LOCAL_DOCKER_REQUIRED")
    event_path = Path(os.environ.get("GITHUB_EVENT_PATH", ""))
    need(event_path.is_file() and not event_path.is_symlink()
         and event_path.stat().st_size <= 1048576, "P67_EVENT_FILE_REQUIRED")
    event = json.loads(event_path.read_text())
    request = event["pull_request"]
    need(event["repository"]["full_name"] == REPOSITORY and event["repository"]["private"] is False
         and request["head"]["repo"]["full_name"] == REPOSITORY
         and request["base"]["repo"]["full_name"] == REPOSITORY
         and request["base"]["sha"] == BASE and request["base"]["ref"] == BASE_REF
         and request["head"]["ref"] == HEAD_REF, "P67_PR_PROVENANCE_DENIED")
    head = request["head"]["sha"]
    need(re.fullmatch(r"[a-f0-9]{40}", head) and git("rev-parse", "HEAD").decode().strip() == head,
         "P67_EXACT_HEAD_REQUIRED")
    git("diff", "--quiet")
    git("diff", "--cached", "--quiet")
    git("merge-base", "--is-ancestor", BASE, head)
    allowed_files = {"tools/run_p67_owner_drafts.py", "tools/check_p67_owner_draft_lab.py",
                     "tools/p67-owner-draft-probe.php", ".github/workflows/aia-p67-crm-owner-drafts.yml",
                     "docs/reviews/AIA-P67-CRM-OWNER-DRAFTS.md"}
    for line in git("diff", "--name-status", "--no-renames", BASE, head).decode().splitlines():
        status, name = line.split("\t")
        need((status == "M" and name == "STATUS-ATUAL.md") or
             (status == "A" and (name in allowed_files or name.startswith("runtime/central-auto/crm-owner-drafts/"))),
             "P67_HISTORICAL_SOURCE_CHANGE_DENIED")
    central = ROOT / "_central_p30_pinned"
    need(central.is_dir() and not central.is_symlink(), "P67_CENTRAL_CHECKOUT_REQUIRED")
    need(command(["git", "rev-parse", "HEAD"], cwd=central).decode().strip() == CENTRAL,
         "P67_CENTRAL_PIN_DENIED")
    command(["git", "diff", "--quiet"], cwd=central)
    command(["git", "diff", "--cached", "--quiet"], cwd=central)
    git("fetch", "--quiet", "--no-tags", "--no-write-fetch-head", str(central), CENTRAL)
    # An actual conflict is a failure. No fake commit, event or branch is created.
    merged = git("merge-tree", "--write-tree", "--name-only", CENTRAL, head)
    tree = merged.splitlines()[0].decode()
    need(re.fullmatch(r"[a-f0-9]{40}", tree), "P67_COMPOSED_TREE_DENIED")
    combined = entries(tree)
    for name, item in entries(CENTRAL).items():
        if name.startswith(("runtime/central-auto/", "infra/n8n-private/", "tools/auto_ia/pg/")):
            need(combined.get(name) == item, "P67_CENTRAL_SOURCE_DOWNGRADE_DENIED")
    for name, item in entries(head).items():
        if name.startswith(("src/", "examples/", "tests/", "tools/")):
            need(combined.get(name) == item, "P67_AGENTS_SOURCE_DOWNGRADE_DENIED")
    temporary = Path(os.environ.get("RUNNER_TEMP", ""))
    need(temporary.is_absolute() and temporary.is_dir() and not temporary.is_symlink(),
         "P67_RUNNER_TEMP_REQUIRED")
    print(json.dumps({"check": "P67_SOURCE_PREFLIGHT", "head": head, "base": BASE,
                      "central": CENTRAL, "tree": tree, "event_merge_sha": os.environ["GITHUB_SHA"]}))
    return head, tree, combined, temporary.resolve()


def verify_export(directory, expected, temporary):
    need(directory.is_absolute() and directory.is_dir() and not directory.is_symlink()
         and directory.resolve().is_relative_to(temporary)
         and directory.name.startswith("aia-p67-tree-"), "P67_EXPORT_PATH_DENIED")
    for name, (mode, sha) in expected.items():
        path = directory / name
        need(path.is_file() and not path.is_symlink() and path.resolve().is_relative_to(directory),
             "P67_EXPORTED_FILE_DENIED")
        body = path.read_bytes()
        actual = hashlib.sha1(b"blob " + str(len(body)).encode() + b"\0" + body).hexdigest()
        need(actual == sha and bool(path.stat().st_mode & 0o111) == (mode == "100755"),
             "P67_EXPORTED_BLOB_DENIED")


def main():
    parser = argparse.ArgumentParser()
    modes = parser.add_mutually_exclusive_group(required=True)
    modes.add_argument("--preflight", action="store_true")
    modes.add_argument("--prepare", action="store_true")
    modes.add_argument("--run", action="store_true")
    arguments = parser.parse_args()
    head, tree, expected, temporary = preflight()
    if arguments.preflight:
        print("AIA_P67_PREFLIGHT_PASS runtime_checks_executed=false")
        return
    if arguments.prepare:
        directory = Path(tempfile.mkdtemp(prefix="aia-p67-tree-", dir=temporary))
        archive = git("archive", tree)
        with tarfile.open(fileobj=io.BytesIO(archive)) as bundle:
            bundle.extractall(directory, filter="data")
        # The archive is public source only, later copied into non-root images.
        directory.chmod(0o755)
        for path in directory.rglob("*"):
            if path.is_dir():
                path.chmod(0o755)
            elif path.is_file():
                path.chmod(0o755 if expected[str(path.relative_to(directory))][0] == "100755" else 0o644)
        verify_export(directory, expected, temporary)
        metadata = {"AIA_P67_SOURCE_CHECKOUT": str(ROOT), "AIA_P67_SOURCE_SHA": head,
                    "AIA_P67_SOURCE_TREE": tree, "AIA_P67_SOURCE_DIR": str(directory)}
        environment = Path(os.environ["GITHUB_ENV"])
        with environment.open("a") as stream:
            for key, value in metadata.items():
                need("\n" not in value and "\r" not in value, "P67_ENV_VALUE_DENIED")
                stream.write(key + "=" + value + "\n")
        print("AIA_P67_EXACT_EXPORT_PASS")
        return
    directory = Path(os.environ.get("AIA_P67_SOURCE_DIR", ""))
    need(os.environ.get("AIA_P67_SOURCE_CHECKOUT") == str(ROOT)
         and os.environ.get("AIA_P67_SOURCE_SHA") == head
         and os.environ.get("AIA_P67_SOURCE_TREE") == tree, "P67_EXPORT_METADATA_DENIED")
    verify_export(directory, expected, temporary)
    # Historical runtime sources remain literal. These unchanged domain checks
    # are repeated; the complete RC77 PostgreSQL/browser matrix is not relabeled.
    for script in ("tools/check_p48_deterministic_noai.php", "tools/check_p49_deterministic_rules.php",
                   "tools/check_p53_review_queue_view.php", "tools/check_p60_signed_review_queue.php",
                   "tools/check_p65_crm_minimal_read.php"):
        subprocess.run(["php", script], cwd=directory, check=True, timeout=120)
    subprocess.run([sys.executable, "tools/check_p67_owner_draft_lab.py"], cwd=directory,
                   check=True, env={**os.environ, "PYTHONDONTWRITEBYTECODE": "1"})
    print("AIA_P67_OWNER_DRAFT_COMPOSED_PASS_NO_GO")


if __name__ == "__main__":
    try:
        main()
    except (RuntimeError, KeyError, ValueError, OSError, subprocess.SubprocessError, tarfile.TarError) as error:
        print(str(error) if isinstance(error, RuntimeError) else "P67_SOURCE_VERIFICATION_FAILED", file=sys.stderr)
        raise SystemExit(2)
