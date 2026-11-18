#!/usr/bin/env python3
"""Admit the real hosted candidate before exercising separate owner providers.

This driver does not impersonate either historical PR. Both original main
guards remain available; only their extracted scenario APIs are called here.
"""
from __future__ import annotations

import hashlib
import importlib
import importlib.util
import io
import json
import os
from pathlib import Path, PurePosixPath
import re
import stat
import subprocess
import sys
import tarfile

ROOT = Path(__file__).resolve().parents[1]
REPOSITORY = "rafhael100/imagine-ai-agents"
BRANCH = "refs/heads/integrate/n8n-owner-provider-components-2026-11-18"
PARENT = "64f52f0dcd44a5545d1bfc6c1d685b33c158601f"
P67 = "0c722eee6bd53a7ee46c4ba68ab70200fc191b78"
P68 = "85f4c37a6d905b91923d3e31ba9068b2c35d7657"
MAX_ARCHIVE = 128 * 1024 * 1024
IMPORTS = {'runtime/central-auto/crm-owner-drafts/Dockerfile': ['0c722eee6bd53a7ee46c4ba68ab70200fc191b78',
                                                      '100644',
                                                      '5aa7d966c429ec62f89922d5713c2d9c712fa338',
                                                      '5aa7d966c429ec62f89922d5713c2d9c712fa338'],
 'runtime/central-auto/crm-owner-drafts/OwnerDraftContract.php': ['0c722eee6bd53a7ee46c4ba68ab70200fc191b78',
                                                                  '100644',
                                                                  '7963f62e0d3619cbcd26111f47804386da08f2ef',
                                                                  '7963f62e0d3619cbcd26111f47804386da08f2ef'],
 'runtime/central-auto/crm-owner-drafts/create-fpm.conf': ['0c722eee6bd53a7ee46c4ba68ab70200fc191b78',
                                                           '100644',
                                                           '04e0cedcf0e6c91507a1c87e031fcb37299c569e',
                                                           '04e0cedcf0e6c91507a1c87e031fcb37299c569e'],
 'runtime/central-auto/crm-owner-drafts/create.php': ['0c722eee6bd53a7ee46c4ba68ab70200fc191b78',
                                                      '100644',
                                                      '797de085ba7565cb1a04096e823a4d4ce9567331',
                                                      '797de085ba7565cb1a04096e823a4d4ce9567331'],
 'runtime/central-auto/crm-owner-drafts/endpoint.php': ['0c722eee6bd53a7ee46c4ba68ab70200fc191b78',
                                                        '100644',
                                                        '61b4b01ac7630a1020c4d0f68135f6a809efac33',
                                                        '61b4b01ac7630a1020c4d0f68135f6a809efac33'],
 'runtime/central-auto/crm-owner-drafts/nginx.conf': ['0c722eee6bd53a7ee46c4ba68ab70200fc191b78',
                                                      '100644',
                                                      '3c1b58c11fc2e70dc5953f20bd5ef1c97cbd3e56',
                                                      '3c1b58c11fc2e70dc5953f20bd5ef1c97cbd3e56'],
 'runtime/central-auto/crm-owner-drafts/read-fpm.conf': ['0c722eee6bd53a7ee46c4ba68ab70200fc191b78',
                                                         '100644',
                                                         'e02ce246adfa96a7decfc2910d5fd03a7a8ed934',
                                                         'e02ce246adfa96a7decfc2910d5fd03a7a8ed934'],
 'runtime/central-auto/crm-owner-drafts/read.php': ['0c722eee6bd53a7ee46c4ba68ab70200fc191b78',
                                                    '100644',
                                                    'abfa0c74d86a7aa2d936e91e3f75ce71e37b5639',
                                                    'abfa0c74d86a7aa2d936e91e3f75ce71e37b5639'],
 'runtime/central-auto/crm-owner-drafts/schema.sql': ['0c722eee6bd53a7ee46c4ba68ab70200fc191b78',
                                                      '100644',
                                                      '296eebc35899c0d6406b3a527efa2a49478002a8',
                                                      '296eebc35899c0d6406b3a527efa2a49478002a8'],
 'tools/check_p67_owner_draft_lab.py': ['0c722eee6bd53a7ee46c4ba68ab70200fc191b78',
                                        '100644',
                                        'ae872c721c1bd9c07071f9f3e63f82340b38be62',
                                        '5fcaf922e226e8bc82687ca67e9e4b26b9b3d7e2'],
 'tools/check_p68_owner_draft_recovery.py': ['85f4c37a6d905b91923d3e31ba9068b2c35d7657',
                                             '100644',
                                             '3e094557a83cffec5d5ad3f91335ad97d7ce61ca',
                                             '8b7bc20f0dbef4dd7ac6c14a78a436110ca8d991'],
 'tools/p67-owner-draft-probe.php': ['0c722eee6bd53a7ee46c4ba68ab70200fc191b78',
                                     '100644',
                                     '9f97bbe9a81f10af409c0f32fb821c9d3742fa42',
                                     '9f97bbe9a81f10af409c0f32fb821c9d3742fa42'],
 'tools/p68-owner-draft-recovery-probe.php': ['85f4c37a6d905b91923d3e31ba9068b2c35d7657',
                                              '100644',
                                              '9746018d31b92889f164481b60f5fe97f2144e84',
                                              '9746018d31b92889f164481b60f5fe97f2144e84'],
 'tools/run_p68_owner_draft_recovery.py': ['85f4c37a6d905b91923d3e31ba9068b2c35d7657',
                                           '100644',
                                           '21555f3f53d72050544f3efd98dbfef58d8181a5',
                                           '21555f3f53d72050544f3efd98dbfef58d8181a5']}
DEPENDENCIES = {'infra/n8n-private/check_native_noai_flow.py': 'a12410e9cd1aa356f51140118dc3b48d4dbeba72',
 'infra/n8n-private/homologation/check_p21_crypto_pg_restore.py': '75e1839ba0a4ad572826d79621ff739c3974aa6b',
 'infra/n8n-private/homologation/check_p24_full_n8n_restore.py': '68b8defb0331398223c09038146ee5128851ae3f',
 'infra/n8n-private/homologation/check_p27_private_receipt_status.py': '7d8945c08a0e8f82ba721e2fba4d8d68d478ea2d',
 'infra/n8n-private/homologation/material_preflight.py': '4e0961b7843949c0fcc6632735e87917a6d46438',
 'infra/n8n-private/homologation/p25_agents_recovery.py': '779b1d4c57e621f7447f4a13d2ada8a52c82bdd4',
 'infra/n8n-private/homologation/seal_backup.py': 'ea33dfc2660f6c741244b47f4fcfe5247e679a3e',
 'infra/n8n-private/homologation/signed_receipt_p23.py': '053d9f31a9436d4309d4e14e67b36f78b34d5672',
 'runtime/central-auto/strict-json.php': '45867695a8e5268ec19d04ae3193ada11a77c9a9'}

class Denied(RuntimeError):
    pass


def need(value, label):
    if not value:
        raise Denied("OWNER_COMPONENT_" + label)


def command(arguments, *, cwd=None):
    result = subprocess.run(arguments, cwd=cwd, capture_output=True, timeout=120)
    need(result.returncode == 0, "COMMAND_DENIED")
    return result.stdout


def git(root, *arguments):
    return command(["git", *arguments], cwd=root)


def blob(data):
    return hashlib.sha1(b"blob " + str(len(data)).encode() + b"\0" + data).hexdigest()


def closed_json(raw):
    def pairs(items):
        result = {}
        for key, value in items:
            need(key not in result, "DUPLICATE_JSON_KEY")
            result[key] = value
        return result
    return json.loads(raw, object_pairs_hook=pairs)


def validate_event(environment, event):
    need(environment.get("GITHUB_ACTIONS") == "true"
         and environment.get("GITHUB_REPOSITORY") == REPOSITORY
         and environment.get("GITHUB_EVENT_NAME") == "push"
         and environment.get("GITHUB_REF") == BRANCH
         and environment.get("N8N_CANDIDATE_REPOSITORY_PRIVATE") == "false"
         and environment.get("P24_RUNNER_ENVIRONMENT") == "github-hosted"
         and environment.get("P30_RUNNER_ENVIRONMENT") == "github-hosted", "REAL_HOSTED_PUSH_REQUIRED")
    sha = environment.get("GITHUB_SHA", "")
    need(re.fullmatch(r"[a-f0-9]{40}", sha), "EVENT_SHA")
    need(all(re.fullmatch(r"[1-9][0-9]{0,19}", environment.get(name, ""))
             for name in ("GITHUB_RUN_ID", "GITHUB_RUN_ATTEMPT")), "RUN_IDENTITY")
    need(not environment.get("DOCKER_HOST") and not environment.get("DOCKER_CONTEXT"), "LOCAL_DOCKER_ONLY")
    need(type(event) is dict and type(event.get("repository")) is dict, "EVENT_OBJECT")
    repository = event["repository"]
    need(repository.get("full_name") == REPOSITORY and repository.get("private") is False
         and repository.get("fork") is False and event.get("ref") == BRANCH
         and event.get("after") == sha and event.get("deleted") is False, "EVENT_PROVENANCE")
    return sha


def verify_file(path, mode, digest):
    need(path.is_file() and not path.is_symlink() and path.resolve() == path
         and stat.S_ISREG(path.stat().st_mode) and path.stat().st_nlink == 1, "SOURCE_REGULAR_FILE")
    need(blob(path.read_bytes()) == digest
         and bool(path.stat().st_mode & 0o111) == (mode == "100755"), "SOURCE_BLOB_OR_MODE")


def verify_archive(directory, archive):
    """Compare a clean export, including its exact inventory, without extraction."""
    need(type(archive) is bytes and 0 < len(archive) <= MAX_ARCHIVE, "ARCHIVE_LIMIT")
    need(directory.is_dir() and not directory.is_symlink() and directory.resolve() == directory,
         "ARCHIVE_DIRECTORY")
    files, directories = set(), set()
    with tarfile.open(fileobj=io.BytesIO(archive)) as stream:
        for entry in stream:
            name = entry.name.rstrip("/")
            path = PurePosixPath(name)
            need(name and not path.is_absolute() and str(path) == name
                 and all(part not in (".", "..", ".git") for part in path.parts)
                 and "\\" not in name and not entry.linkname, "ARCHIVE_ENTRY")
            target = directory / name
            if entry.isdir():
                need(name not in directories and target.is_dir() and not target.is_symlink(), "ARCHIVE_DIRECTORY_ENTRY")
                directories.add(name)
                continue
            need(entry.isreg() and entry.mode in (0o644, 0o755)
                 and name not in files and 0 <= entry.size <= 16 * 1024 * 1024, "ARCHIVE_FILE_ENTRY")
            data = stream.extractfile(entry).read(entry.size + 1)
            need(len(data) == entry.size, "ARCHIVE_FILE_SIZE")
            verify_file(target, "100755" if entry.mode == 0o755 else "100644", blob(data))
            files.add(name)
    paths = list(directory.rglob("*"))
    need(files and all(not path.is_symlink() for path in paths), "ARCHIVE_LINK")
    need({str(path.relative_to(directory)) for path in paths if path.is_file()} == files
         and {str(path.relative_to(directory)) for path in paths if path.is_dir()} == directories,
         "ARCHIVE_EXACT_INVENTORY")


def admit_sources():
    event_path = Path(os.environ.get("GITHUB_EVENT_PATH", ""))
    need(event_path.is_file() and not event_path.is_symlink()
         and 0 < event_path.stat().st_size <= 1048576, "EVENT_FILE")
    sha = validate_event(os.environ, closed_json(event_path.read_bytes()))
    temporary = Path(os.environ.get("RUNNER_TEMP", ""))
    checkout = Path(os.environ.get("AIA_OWNER_COMPONENTS_CHECKOUT", ""))
    need(temporary.is_absolute() and temporary.is_dir() and not temporary.is_symlink(), "RUNNER_TEMP")
    need(checkout.is_absolute() and checkout.is_dir() and not checkout.is_symlink()
         and checkout.resolve() == checkout and checkout != ROOT, "ACTUAL_CHECKOUT")
    need(ROOT.is_relative_to(temporary.resolve()) and ROOT.name == "owner"
         and ROOT.parent.name.startswith("n8n-private-candidate-")
         and str(ROOT) == os.environ.get("AIA_OWNER_COMPONENTS_ARCHIVE"), "EXPORTED_SOURCE_PATH")
    need(git(checkout, "rev-parse", "HEAD").decode().strip() == sha
         and git(checkout, "rev-parse", "--is-shallow-repository").strip() == b"false", "ACTUAL_HEAD")
    git(checkout, "diff", "--quiet")
    git(checkout, "diff", "--cached", "--quiet")
    git(checkout, "merge-base", "--is-ancestor", PARENT, sha)
    tree = git(checkout, "rev-parse", "HEAD^{tree}").decode().strip()
    need(re.fullmatch(r"[a-f0-9]{40}", tree)
         and tree == os.environ.get("AIA_OWNER_COMPONENTS_TREE"), "ACTUAL_TREE")
    reference = checkout / "_owner_components_pinned"
    need(reference.is_dir() and not reference.is_symlink() and reference.resolve() == reference,
         "OWNER_REFERENCE_CHECKOUT")
    need(git(reference, "rev-parse", "HEAD").decode().strip() == P68
         and git(reference, "rev-parse", "--is-shallow-repository").strip() == b"false", "OWNER_REFERENCE_PIN")
    git(reference, "diff", "--quiet")
    git(reference, "diff", "--cached", "--quiet")
    git(reference, "merge-base", "--is-ancestor", P67, P68)
    verify_archive(ROOT, git(checkout, "-c", "tar.umask=0022", "archive", sha))
    for relative, item in IMPORTS.items():
        source, mode, original, adapted = item
        need(git(reference, "rev-parse", source + ":" + relative).decode().strip() == original,
             "IMPORTED_PREIMAGE")
        need(git(checkout, "rev-parse", sha + ":" + relative).decode().strip() == adapted,
             "IMPORTED_CANDIDATE_BLOB")
        verify_file(ROOT / relative, mode, adapted)
    for relative, digest in DEPENDENCIES.items():
        need(git(checkout, "rev-parse", PARENT + ":" + relative).decode().strip() == digest
             and git(checkout, "rev-parse", sha + ":" + relative).decode().strip() == digest,
             "UNCHANGED_NATIVE_DEPENDENCY")
        verify_file(ROOT / relative, "100644", digest)
    need(command(["docker", "context", "show"]).strip() == b"default", "DEFAULT_DOCKER_CONTEXT")
    context = closed_json(command(["docker", "context", "inspect", "default"]))
    need(type(context) is list and len(context) == 1
         and context[0]["Endpoints"]["docker"]["Host"] == "unix:///var/run/docker.sock", "LOCAL_DOCKER_SOCKET")
    return {"source_sha": sha, "source_tree": tree, "temporary": temporary.resolve(),
            "run_id": os.environ["GITHUB_RUN_ID"], "run_attempt": os.environ["GITHUB_RUN_ATTEMPT"]}


def validate_owner_image(identity, observed, native_identity, sha):
    need(isinstance(identity, str) and re.fullmatch(r"sha256:[a-f0-9]{64}", identity)
         and isinstance(native_identity, str) and re.fullmatch(r"sha256:[a-f0-9]{64}", native_identity)
         and identity != native_identity, "SEPARATE_OWNER_IMAGE")
    need(type(observed) is list and len(observed) == 1 and observed[0].get("Id") == identity,
         "OBSERVED_OWNER_IMAGE")
    config = observed[0].get("Config") or {}
    labels = config.get("Labels") or {}
    need(labels.get("imagine.owner.source_sha") == sha
         and labels.get("imagine.owner.dockerfile_blob") == "5aa7d966c429ec62f89922d5713c2d9c712fa338"
         and config.get("User") == "www-data", "OWNER_BUILD_BINDING")
    return identity


def verify_owner_image(context):
    identity = os.environ.get("OWNER_PROVIDER_PHP_IMAGE", "")
    need(os.environ.get("AGENTS_PHP_IMAGE") == identity, "OWNER_PROCESS_IMAGE")
    return validate_owner_image(identity, closed_json(command(["docker", "image", "inspect", identity])),
                                os.environ.get("NATIVE_PROVIDER_PHP_IMAGE", ""), context["source_sha"])


def verify_module(module, expected):
    origin = getattr(module, "__file__", None)
    need(isinstance(origin, str) and Path(origin).resolve() == expected, "MODULE_ORIGIN")


def load_owner_modules(component):
    # Each owner group runs in a fresh Python process. Verify origins before
    # any scenario function can create a disposable resource.
    sys.path.insert(0, str(ROOT / "infra/n8n-private/homologation"))
    sys.path.insert(0, str(ROOT / "infra/n8n-private"))
    for relative in DEPENDENCIES:
        if relative.endswith(".py"):
            module = importlib.import_module(Path(relative).stem)
            verify_module(module, ROOT / relative)
    modules = {}
    for name in ("check_p67_owner_draft_lab", "check_p68_owner_draft_recovery"):
        if name.endswith("recovery") and component == "p67":
            continue
        need(name not in sys.modules, "OWNER_MODULE_PRELOADED")
        path = ROOT / "tools" / (name + ".py")
        spec = importlib.util.spec_from_file_location(name, path)
        need(spec is not None and spec.loader is not None, "OWNER_MODULE_SPEC")
        module = importlib.util.module_from_spec(spec)
        sys.modules[name] = module
        spec.loader.exec_module(module)
        verify_module(module, path)
        need(module.ROOT == ROOT and callable(module.exercise), "OWNER_EXERCISE_BINDING")
        modules[name] = module
    return modules


def execute(arguments):
    need(len(arguments) == 1 and arguments[0] in ("preflight", "p67", "p68"), "ARGUMENTS")
    component = arguments[0]
    context = admit_sources()
    if component == "preflight":
        print("OWNER_PROVIDER_COMPONENT_SOURCE_PREFLIGHT_PASS runtime_checks_executed=false", flush=True)
        return 0
    image = verify_owner_image(context)
    modules = load_owner_modules(component)
    legacy = modules["check_p67_owner_draft_lab"]
    result = (legacy.exercise(context["temporary"]) if component == "p67"
              else modules["check_p68_owner_draft_recovery"].exercise(context["temporary"], legacy))
    print(json.dumps({"schema": "OWNER_PROVIDER_COMPONENT_RESULT_V1", "component": component,
                      "source_sha": context["source_sha"], "source_tree": context["source_tree"],
                      "run_id": context["run_id"], "run_attempt": context["run_attempt"],
                      "import_source_sha": P67 if component == "p67" else P68,
                      "owner_php_image": image, "result": result,
                      "go_for_installation": False, "separate_from_native_feedback": True}, sort_keys=True), flush=True)
    return 0


def main(arguments=None):
    try:
        return execute(sys.argv[1:] if arguments is None else arguments)
    except Exception as error:
        # exercise() retains its original finally cleanup, but does not pass
        # through the old __main__ error handler. Never expose raw failures.
        trusted_error = isinstance(error, Denied) or type(error).__module__ in (
            "check_p67_owner_draft_lab", "check_p68_owner_draft_recovery", "check_p24_full_n8n_restore")
        label = error.args[0] if trusted_error and len(error.args) == 1 and type(error.args[0]) is str else ""
        if not re.fullmatch(r"(?:OWNER_COMPONENT|P25|P27|P67|P68)_[A-Z0-9_]{1,160}", label):
            label = "OWNER_COMPONENT_UNEXPECTED_FAILURE"
        print("OWNER_PROVIDER_COMPONENT_DENIED_" + label, file=sys.stderr, flush=True)
        return 1


if __name__ == "__main__":
    raise SystemExit(main())
