#!/usr/bin/env python3
"""Verify the real P68 PR; export P67 + Central unchanged with recovery tests."""
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
BASE = "0c722eee6bd53a7ee46c4ba68ab70200fc191b78"
CENTRAL = "c08688b16e61d8d1ecc4d8c0ed4da8758a687ee8"
BASE_REF = "feat/aia-p67-crm-owner-drafts-2026-11-17"
HEAD_REF = "test/aia-p68-owner-draft-recovery-2026-11-17"
ADDITIONS = {
    "tools/run_p68_owner_draft_recovery.py", "tools/check_p68_owner_draft_recovery.py",
    "tools/p68-owner-draft-recovery-probe.php", ".github/workflows/aia-p68-owner-draft-recovery.yml",
    "docs/reviews/AIA-P68-OWNER-DRAFT-RECOVERY.md",
}


class Denied(RuntimeError):
    pass


def need(value, label):
    if not value:
        raise Denied("P68_" + label)


def git(root, *arguments):
    result = subprocess.run(["git", *arguments], cwd=root, capture_output=True, timeout=120)
    need(result.returncode == 0, "SOURCE_COMMAND_DENIED")
    return result.stdout


def entries(root, tree):
    result = {}
    for entry in git(root, "ls-tree", "-rz", "-r", tree).split(b"\0"):
        if not entry:
            continue
        metadata, name = entry.split(b"\t", 1)
        mode, kind, sha = metadata.split()
        path = Path(os.fsdecode(name))
        need(kind == b"blob" and mode in (b"100644", b"100755")
             and not path.is_absolute() and ".." not in path.parts, "SOURCE_ENTRY_DENIED")
        result[str(path)] = (mode.decode(), sha.decode())
    return result


def preflight(root=ROOT):
    need(os.environ.get("GITHUB_ACTIONS") == "true"
         and os.environ.get("GITHUB_REPOSITORY") == REPOSITORY
         and os.environ.get("GITHUB_EVENT_NAME") == "pull_request"
         and os.environ.get("P24_RUNNER_ENVIRONMENT") == "github-hosted", "ACTUAL_HOSTED_PR_REQUIRED")
    need(re.fullmatch(r"[a-f0-9]{40}", os.environ.get("GITHUB_SHA", "")), "EVENT_MERGE_SHA")
    need(not os.environ.get("DOCKER_HOST") and not os.environ.get("DOCKER_CONTEXT"), "LOCAL_DOCKER_ONLY")
    event_path = Path(os.environ.get("GITHUB_EVENT_PATH", ""))
    need(event_path.is_file() and not event_path.is_symlink()
         and event_path.stat().st_size <= 1048576, "ACTUAL_EVENT_FILE")
    event = json.loads(event_path.read_text())
    request = event["pull_request"]
    need(root.is_absolute() and root.is_dir() and not root.is_symlink(), "SOURCE_CHECKOUT")
    need(event["repository"]["full_name"] == REPOSITORY and event["repository"]["private"] is False
         and request["head"]["repo"]["full_name"] == REPOSITORY
         and request["base"]["repo"]["full_name"] == REPOSITORY
         and request["base"]["sha"] == BASE and request["base"]["ref"] == BASE_REF
         and request["head"]["ref"] == HEAD_REF, "PR_PROVENANCE")
    head = request["head"]["sha"]
    need(re.fullmatch(r"[a-f0-9]{40}", head)
         and git(root, "rev-parse", "HEAD").decode().strip() == head, "EXACT_HEAD")
    git(root, "diff", "--quiet")
    git(root, "diff", "--cached", "--quiet")
    git(root, "merge-base", "--is-ancestor", BASE, head)
    changes = [line.split("\t") for line in git(root, "diff", "--name-status", "--no-renames", BASE, head).decode().splitlines()]
    need({name for status, name in changes} == ADDITIONS
         and all(status == "A" for status, name in changes), "ONLY_FIVE_RECOVERY_ADDITIONS")
    central = root / "_central_p30_pinned"
    need(central.is_dir() and not central.is_symlink()
         and git(central, "rev-parse", "HEAD").decode().strip() == CENTRAL, "CENTRAL_PIN")
    git(central, "diff", "--quiet")
    git(central, "diff", "--cached", "--quiet")
    git(root, "fetch", "--quiet", "--no-tags", "--no-write-fetch-head", str(central), CENTRAL)
    baseline = git(root, "merge-tree", "--write-tree", "--name-only", CENTRAL, BASE).splitlines()[0].decode()
    tree = git(root, "merge-tree", "--write-tree", "--name-only", CENTRAL, head).splitlines()[0].decode()
    need(re.fullmatch(r"[a-f0-9]{40}", baseline) and re.fullmatch(r"[a-f0-9]{40}", tree), "COMBINED_TREE")
    old, combined = entries(root, baseline), entries(root, tree)
    need(set(combined) == set(old) | ADDITIONS
         and all(combined.get(name) == value for name, value in old.items()), "P67_AND_CENTRAL_MUST_REMAIN_LITERAL")
    temporary = Path(os.environ.get("RUNNER_TEMP", ""))
    need(temporary.is_absolute() and temporary.is_dir() and not temporary.is_symlink(), "RUNNER_TEMP")
    print(json.dumps({"check": "P68_SOURCE", "head": head, "base": BASE, "central": CENTRAL,
                      "tree": tree, "event_merge_sha": os.environ["GITHUB_SHA"]}), flush=True)
    return head, tree, combined, temporary.resolve()


def verify_export(directory, expected, temporary):
    need(directory.is_absolute() and directory.is_dir() and not directory.is_symlink()
         and directory.resolve().is_relative_to(temporary)
         and directory.name.startswith("aia-p68-tree-"), "EXPORT_PATH")
    paths = list(directory.rglob("*"))
    need(all(not path.is_symlink() for path in paths), "EXPORT_NO_SYMLINKS")
    files = {str(path.relative_to(directory)) for path in paths if not path.is_dir()}
    need(files == set(expected), "EXPORT_EXACT_INVENTORY")
    for name, (mode, sha) in expected.items():
        path = directory / name
        need(path.is_file() and not path.is_symlink() and path.resolve().is_relative_to(directory), "EXPORT_FILE")
        data = path.read_bytes()
        need(hashlib.sha1(b"blob " + str(len(data)).encode() + b"\0" + data).hexdigest() == sha
             and bool(path.stat().st_mode & 0o111) == (mode == "100755"), "EXPORT_BLOB")


def main():
    parser = argparse.ArgumentParser()
    modes = parser.add_mutually_exclusive_group(required=True)
    modes.add_argument("--preflight", action="store_true")
    modes.add_argument("--prepare", action="store_true")
    modes.add_argument("--run", action="store_true")
    options = parser.parse_args()
    head, tree, expected, temporary = preflight()
    if options.preflight:
        return
    if options.prepare:
        directory = Path(tempfile.mkdtemp(prefix="aia-p68-tree-", dir=temporary))
        with tarfile.open(fileobj=io.BytesIO(git(ROOT, "archive", tree))) as archive:
            archive.extractall(directory, filter="data")
        directory.chmod(0o755)
        for path in directory.rglob("*"):
            if path.is_dir():
                path.chmod(0o755)
            elif path.is_file():
                path.chmod(0o755 if expected[str(path.relative_to(directory))][0] == "100755" else 0o644)
        verify_export(directory, expected, temporary)
        values = {"AIA_P68_SOURCE_CHECKOUT": str(ROOT), "AIA_P68_SOURCE_SHA": head,
                  "AIA_P68_SOURCE_TREE": tree, "AIA_P68_SOURCE_DIR": str(directory)}
        with Path(os.environ["GITHUB_ENV"]).open("a") as stream:
            for name, value in values.items():
                need("\n" not in value and "\r" not in value, "ENV_VALUE")
                stream.write(name + "=" + value + "\n")
        return
    directory = Path(os.environ.get("AIA_P68_SOURCE_DIR", ""))
    need(os.environ.get("AIA_P68_SOURCE_CHECKOUT") == str(ROOT)
         and os.environ.get("AIA_P68_SOURCE_SHA") == head
         and os.environ.get("AIA_P68_SOURCE_TREE") == tree, "EXPORT_METADATA")
    verify_export(directory, expected, temporary)
    subprocess.run([sys.executable, "tools/check_p68_owner_draft_recovery.py"], cwd=directory,
                   env={**os.environ, "PYTHONDONTWRITEBYTECODE": "1"}, check=True, timeout=600)
    print("AIA_P68_DISPOSABLE_RECOVERY_PASS_NO_GO")


if __name__ == "__main__":
    try:
        main()
    except (Denied, KeyError, ValueError, OSError, subprocess.SubprocessError, tarfile.TarError) as error:
        print(str(error) if isinstance(error, Denied) else "P68_SOURCE_VERIFICATION_FAILED", file=sys.stderr)
        raise SystemExit(2)
