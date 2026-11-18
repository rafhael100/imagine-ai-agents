#!/usr/bin/env python3
"""Public Git sources for operator review; never an installation authorization.

Creation is restricted to the reviewed hosted CI. Offline verification needs
the candidate commit from a separate trusted channel. No archive is extracted,
and no worktree file, secret, Docker state or raw runtime log is collected.
"""
from __future__ import annotations

import argparse
import base64
import gzip
import hashlib
import io
import json
import os
from pathlib import Path
import re
import stat
import subprocess
import sys
import tarfile
import zlib

SCHEMA = "N8N_PRIVATE_OPERATOR_PACKET_V1"
OBSERVATIONS_SCHEMA = "N8N_PRIVATE_CANDIDATE_OBSERVATIONS_V1"
AGENTS_REPOSITORY = "rafhael100/imagine-ai-agents"
REPOSITORIES = {"candidate": AGENTS_REPOSITORY, "crm": "rafhael100/imagine-crm", "adapter": AGENTS_REPOSITORY}
FIXED_PINS = {"crm": "e1b3b7f118eff0350a00789aecc7bb2bb2331464", "adapter": "8b04d0e936168c77db028160b5aeb1758db497c4"}
LINEAGE = {
    "central_475": "dfa4685cd5df4a9499c3420f580980e769a1981d",
    "domain_468": "05e4ea7942e530055f4e455544d82f5d3edbd56d",
    "operator_464": "c08688b16e61d8d1ecc4d8c0ed4da8758a687ee8",
}
N8N_IMAGE = "ghcr.io/n8n-io/n8n@sha256:b4b90747b81fcda7e76550c0d3aa7f0f64917a1e9573cff40d6834eaf2e0f8ea"
CHECKS = (
    "source_integrity", "php_syntax", "p2_safety_contracts", "native_contracts",
    "execution_contract", "crm_execution_receipt_contract", "captured_lead_contract",
    "p48_deterministic_noai", "p49_deterministic_rules", "p50_offline_cli",
    "p53_review_queue", "p60_signed_review_queue", "crm_lead_capture", "crm_lead_execution",
    "crm_execution_feedback", "p25_agents_state_restore", "p28_candidate_state_restore",
    "p30_native_operator_mfa", "operator_packet_contracts",
)
P30 = {"n8n_version_verified": "2.47.7", "synthetic_nominal_operators": 1,
       "mfa_enforced_instance_wide": False, "mandatory_mfa_policy_gate_closed": False,
       "go_for_installation": False}
BOUNDARIES = {
    "source": "PUBLIC_TRACKED_GIT_OBJECTS_ONLY", "runtime_profile": "DISPOSABLE_CI_ONLY",
    "source_ancestry_checked_by_ci": True, "full_history_verified_offline": False,
    "source_links_authenticate_approvals": False, "image_identity_verified_offline": False,
    "ci_observations_authenticated_offline": False, "checksums_are_ci_attestation": False,
    "local_build_images_distributed": False, "bit_reproducible_image_build_claimed": False,
    "secrets_or_runtime_state_included": False, "external_n8n_backup_verified": False,
    "mandatory_mfa_policy_gate_closed": False, "go_for_installation": False,
}
GATES = {
    "HOM-01": {"state": "HISTORICAL_HOST_INVENTORY_2026_11_16",
               "reference": "https://github.com/rafhael100/imagine-ai-agents/issues/414#issuecomment-6925460120",
               "current_host_verified": False},
    "HOM-02": {"state": "FINAL_CAPACITY_PORT_NETWORK_AND_ROLLBACK_REVIEW_PENDING"},
    "HOM-03": {"state": "MANDATORY_MFA_AND_LICENSE_REVIEW_PENDING"},
    "HOM-04": {"state": "OPERATIONAL_SERVICE_IDENTITIES_AND_MIGRATIONS_PENDING"},
    "HOM-05": {"state": "N8N_PACKAGE_EXTERNAL_BACKUP_AND_RESTORE_UNPROVED",
               "existing_baseline": "R2_EU_DAILY_30D_WEEKLY_VERIFY_MONTHLY_RESTORE_QUARTERLY_DR",
               "reference": "https://github.com/rafhael100/imagine-portal/blob/86c9f74e890d5d2ae254c63b65dee36af3a58e7f/apps/portal/deploy/phase6/RELEASE-GATES.md"},
    "HOM-06": {"state": "INTERNAL_CROSS_REVIEW_AND_SPECIFIC_INSTALLATION_APPROVAL_PENDING"},
}
REQUIRED = {
    "candidate": {
        "runtime/central-auto/Dockerfile", "runtime/central-auto/index.php",
        "runtime/central-auto/strict-json.php", "runtime/central-auto/event-status.php",
        "runtime/central-auto/execution-status.php", "runtime/central-auto/execution-contract.php",
        "src/MockOrchestration/CapturedLeadTaskContract.php", "tools/labs/PgCapturedLeadStageLab.php",
        "infra/n8n-private/docker-compose.yml", "infra/n8n-private/10-runtime-role.sh",
        "infra/n8n-private/workflows/native-crm-lead-noai.workflow.json",
        "infra/n8n-private/homologation/check_p30_native_operator_mfa.py",
        "infra/n8n-private/homologation/check_crm_execution_feedback.py",
        "infra/n8n-private/homologation/operator_packet.py",
    },
    "crm": {"infra/homologation/Dockerfile", "composer.json", "composer.lock", "bootstrap/app.php",
            "app/Homologation/ExecutionMonitor.php", "app/Homologation/S2S/AgentsExecutionReconciler.php",
            "infra/homologation/s2s/execution-feedback-candidate.sql",
            "infra/homologation/s2s/execution-monitor-candidate.sql",
            "resources/views/partials/crm-execution-monitor.blade.php"},
    "adapter": {"tools/auto_ia/convergence/MockP7ToAutoIaAdapter.php", "tools/auto_ia/pg/003_signed_ingress_lab.sql"},
}
SHA1 = re.compile(r"[a-f0-9]{40}")
SHA256 = re.compile(r"[a-f0-9]{64}")
MAX_FILES = 20_000
MAX_FILE_BYTES = 16 * 1024**2
MAX_SOURCE_BYTES = 128 * 1024**2
MAX_ARCHIVE_BYTES = 160 * 1024**2
# Bound decompression before tarfile handles PAX/longname metadata. Each allowed
# path is <=1024 bytes; this covers its headers/padding without trusting tar size.
MAX_TAR_BYTES = MAX_SOURCE_BYTES + MAX_FILES * 4096 + 10240
PACKET_FILES = {"candidate.tar.gz", "crm.tar.gz", "adapter.tar.gz", "manifest.json", "SHA256SUMS"}


class Denied(ValueError):
    pass


def need(condition, reason):
    if not condition:
        raise Denied(reason)


def canonical(value):
    return (json.dumps(value, ensure_ascii=False, sort_keys=True, separators=(",", ":")) + "\n").encode("utf-8")


def closed_json(raw):
    def pairs(items):
        result = {}
        for key, value in items:
            need(key not in result, "DUPLICATE_JSON_KEY")
            result[key] = value
        return result
    try:
        return json.loads(raw.decode("utf-8"), object_pairs_hook=pairs,
                          parse_constant=lambda _: (_ for _ in ()).throw(Denied("NON_JSON_NUMBER")))
    except (ValueError, UnicodeError, RecursionError):
        raise Denied("JSON_DENIED") from None


def fields(value, expected):
    need(type(value) is dict and set(value) == set(expected), "FIELDS_DENIED")


def regular_bytes(path, maximum):
    info = path.lstat()
    need(stat.S_ISREG(info.st_mode) and info.st_nlink == 1 and 0 < info.st_size <= maximum, "FILE_DENIED")
    raw = path.read_bytes()
    need(len(raw) == info.st_size, "FILE_CHANGED")
    return raw


def safe_path(path):
    need(isinstance(path, str) and 0 < len(path.encode("utf-8")) <= 1024, "PATH_DENIED")
    parts = path.split("/")
    need("\\" not in path and ":" not in path and all(p not in ("", ".", "..") for p in parts)
         and not any(ord(c) < 32 or ord(c) == 127 for c in path), "PATH_DENIED")
    need(not any(p.lower() in {".git", ".ssh", ".aws", ".gnupg"} for p in parts), "PRIVATE_PATH_DENIED")
    name = parts[-1].lower()
    need(not (name == ".env" or (name.startswith(".env.") and name != ".env.example")
              or name in {"id_rsa", "id_ed25519"}
              or name.endswith((".key", ".pem", ".p12", ".pfx", ".dump", ".sqlite", ".log"))), "PRIVATE_PATH_DENIED")
    return path


def git(repo, *args, input_data=None):
    result = subprocess.run(["git", "-C", str(repo), *args], input=input_data,
                            capture_output=True, timeout=90, check=False)
    need(result.returncode == 0, "GIT_COMMAND_DENIED")
    return result.stdout


def git_hash(kind, data):
    return hashlib.sha1(kind.encode() + b" " + str(len(data)).encode() + b"\0" + data).hexdigest()


def tree_hash(files):
    root = {}
    for path, mode, data in files:
        at = root
        parts = safe_path(path).split("/")
        for part in parts[:-1]:
            at = at.setdefault(part, {})
            need(type(at) is dict, "PATH_COLLISION")
        need(parts[-1] not in at, "DUPLICATE_PATH")
        at[parts[-1]] = (mode, git_hash("blob", data))

    def digest(directory):
        ordered = sorted(directory.items(), key=lambda pair: pair[0].encode("utf-8") + (b"/" if type(pair[1]) is dict else b""))
        content = bytearray()
        for name, entry in ordered:
            mode, sha = ("40000", digest(entry)) if type(entry) is dict else entry
            content.extend(mode.encode() + b" " + name.encode("utf-8") + b"\0" + bytes.fromhex(sha))
        return git_hash("tree", bytes(content))
    return digest(root)


def git_source(repo, commit):
    need(SHA1.fullmatch(commit) is not None, "COMMIT_DENIED")
    commit_object = git(repo, "cat-file", "commit", commit)
    need(0 < len(commit_object) <= 65536 and git_hash("commit", commit_object) == commit, "COMMIT_IDENTITY_DENIED")
    entries = []
    total = 0
    listing = git(repo, "ls-tree", "-r", "-l", "-z", "--full-tree", commit)
    need(0 < len(listing) <= 4 * 1024**2, "TREE_LISTING_DENIED")
    for row in listing.rstrip(b"\0").split(b"\0"):
        metadata, encoded_path = row.split(b"\t", 1)
        mode, kind, sha, size = metadata.split()
        need(mode in (b"100644", b"100755") and kind == b"blob", "NON_REGULAR_GIT_OBJECT")
        path = safe_path(encoded_path.decode("utf-8"))
        size = int(size)
        need(0 <= size <= MAX_FILE_BYTES, "SOURCE_FILE_TOO_LARGE")
        total += size
        need(total <= MAX_SOURCE_BYTES and len(entries) < MAX_FILES, "SOURCE_TOO_LARGE")
        entries.append((path, mode.decode(), sha.decode(), size))
    entries.sort(key=lambda entry: entry[0].encode("utf-8"))
    batch = git(repo, "cat-file", "--batch", input_data=b"".join(entry[2].encode() + b"\n" for entry in entries))
    files, cursor = [], 0
    for path, mode, sha, size in entries:
        end = batch.find(b"\n", cursor)
        need(end >= cursor and batch[cursor:end] == f"{sha} blob {size}".encode(), "GIT_BLOB_HEADER_DENIED")
        data = batch[end + 1:end + 1 + size]
        cursor = end + 1 + size
        need(batch[cursor:cursor + 1] == b"\n" and git_hash("blob", data) == sha, "GIT_BLOB_IDENTITY_DENIED")
        cursor += 1
        files.append((path, mode, data))
    need(cursor == len(batch), "GIT_BLOB_TRAILER_DENIED")
    tree = tree_hash(files)
    need(commit_object.split(b"\n", 1)[0] == b"tree " + tree.encode(), "GIT_TREE_IDENTITY_DENIED")
    return files, {"commit": commit, "tree": tree, "commit_object_b64": base64.b64encode(commit_object).decode()}


def archive_bytes(files):
    output = io.BytesIO()
    with gzip.GzipFile(filename="", fileobj=output, mode="wb", compresslevel=9, mtime=0) as compressed:
        with tarfile.open(fileobj=compressed, mode="w", format=tarfile.PAX_FORMAT) as archive:
            for path, mode, data in sorted(files, key=lambda entry: entry[0].encode("utf-8")):
                item = tarfile.TarInfo(safe_path(path))
                item.size, item.mode = len(data), int(mode, 8) & 0o777
                item.uid = item.gid = item.mtime = 0
                archive.addfile(item, io.BytesIO(data))
    return output.getvalue()


def inspect_archive(raw, source, required):
    need(len(raw) == source["archive_bytes"] and hashlib.sha256(raw).hexdigest() == source["sha256"], "ARCHIVE_SHA256_DENIED")
    try:
        with gzip.GzipFile(fileobj=io.BytesIO(raw), mode="rb") as compressed:
            tar_bytes = compressed.read(MAX_TAR_BYTES + 1)
    except (OSError, EOFError, zlib.error):
        raise Denied("ARCHIVE_ENCODING_DENIED") from None
    need(len(tar_bytes) <= MAX_TAR_BYTES, "ARCHIVE_DECOMPRESSION_LIMIT")
    files, seen, total = [], set(), 0
    with tarfile.open(fileobj=io.BytesIO(tar_bytes), mode="r:") as archive:
        for item in archive:
            path = safe_path(item.name)
            need(item.isreg() and not item.linkname and item.mode in (0o644, 0o755), "ARCHIVE_TYPE_DENIED")
            need(item.uid == item.gid == item.mtime == 0 and not item.uname and not item.gname
                 and set(item.pax_headers) <= {"path"}, "ARCHIVE_METADATA_DENIED")
            need(path not in seen, "DUPLICATE_PATH")
            seen.add(path)
            need(0 <= item.size <= MAX_FILE_BYTES and len(seen) <= MAX_FILES, "ARCHIVE_LIMIT_DENIED")
            total += item.size
            need(total <= MAX_SOURCE_BYTES, "ARCHIVE_LIMIT_DENIED")
            stream = archive.extractfile(item)
            need(stream is not None, "ARCHIVE_FILE_DENIED")
            data = stream.read(item.size + 1)
            need(len(data) == item.size, "ARCHIVE_SIZE_DENIED")
            files.append((path, "100755" if item.mode == 0o755 else "100644", data))
    need(len(files) == source["file_count"] and total == source["source_bytes"], "SOURCE_COUNTS_DENIED")
    need(required <= seen, "BUILD_SOURCE_INCOMPLETE")
    need(tree_hash(files) == source["tree"], "ARCHIVE_TREE_DENIED")
    need(archive_bytes(files) == raw, "NON_CANONICAL_ARCHIVE")


def validate_observations(value):
    fields(value, {"schema", "run_id", "run_attempt", "source_sha", "remote_images", "local_build_images", "checks", "p30"})
    need(value["schema"] == OBSERVATIONS_SCHEMA and isinstance(value["source_sha"], str)
         and SHA1.fullmatch(value["source_sha"]) is not None, "OBSERVATIONS_SOURCE_DENIED")
    need(all(isinstance(value[key], str) and re.fullmatch(r"[1-9][0-9]{0,19}", value[key])
             for key in ("run_id", "run_attempt")), "OBSERVATIONS_RUN_DENIED")
    fields(value["checks"], CHECKS)
    need(all(result == "PASS" for result in value["checks"].values()), "INCOMPLETE_CI_CHECKS")
    need(canonical(value["p30"]) == canonical(P30), "P30_FACTS_DENIED")
    remote = value["remote_images"]
    fields(remote, {"n8n", "n8n_postgres", "agents_postgres", "nginx"})
    need(remote["n8n"] == N8N_IMAGE, "N8N_IMAGE_DENIED")
    for name, repository in (("n8n_postgres", "postgres"), ("agents_postgres", "postgres"), ("nginx", "nginx")):
        need(isinstance(remote[name], str) and re.fullmatch(r"(?:docker.io/library/)?" + repository + r"@sha256:[a-f0-9]{64}", remote[name]), "REMOTE_IMAGE_REFERENCE_DENIED")
    fields(value["local_build_images"], {"agents_php", "crm_php"})
    need(all(isinstance(image, str) and re.fullmatch(r"sha256:[a-f0-9]{64}", image)
             for image in value["local_build_images"].values()), "LOCAL_IMAGE_ID_DENIED")


def manifest_checks(manifest, expected_candidate):
    fields(manifest, {"schema", "sources", "lineage", "observations", "gates", "boundaries"})
    need(manifest["schema"] == SCHEMA and canonical(manifest["lineage"]) == canonical(LINEAGE)
         and canonical(manifest["gates"]) == canonical(GATES)
         and canonical(manifest["boundaries"]) == canonical(BOUNDARIES), "MANIFEST_POLICY_DENIED")
    validate_observations(manifest["observations"])
    need(manifest["observations"]["source_sha"] == expected_candidate, "EXPECTED_CANDIDATE_DENIED")
    fields(manifest["sources"], REPOSITORIES)
    for name, source in manifest["sources"].items():
        fields(source, {"repository", "commit", "tree", "commit_object_b64", "archive", "sha256", "archive_bytes", "file_count", "source_bytes"})
        need(source["repository"] == REPOSITORIES[name] and source["archive"] == name + ".tar.gz"
             and source["commit"] == (expected_candidate if name == "candidate" else FIXED_PINS[name]), "SOURCE_PIN_DENIED")
        need(all(isinstance(source[k], str) and regex.fullmatch(source[k]) for k, regex in (("commit", SHA1), ("tree", SHA1), ("sha256", SHA256))), "SOURCE_HASH_DENIED")
        need(type(source["archive_bytes"]) is int and 0 < source["archive_bytes"] <= MAX_ARCHIVE_BYTES
             and type(source["file_count"]) is int and 0 < source["file_count"] <= MAX_FILES
             and type(source["source_bytes"]) is int and 0 <= source["source_bytes"] <= MAX_SOURCE_BYTES, "SOURCE_LIMIT_DENIED")
        need(isinstance(source["commit_object_b64"], str) and len(source["commit_object_b64"]) <= 90000, "COMMIT_METADATA_DENIED")
        commit = base64.b64decode(source["commit_object_b64"], validate=True)
        need(0 < len(commit) <= 65536 and git_hash("commit", commit) == source["commit"]
             and commit.split(b"\n", 1)[0] == b"tree " + source["tree"].encode(), "COMMIT_IDENTITY_DENIED")


def checksum_bytes(files):
    return b"".join(hashlib.sha256(files[name]).hexdigest().encode() + b"  " + name.encode() + b"\n" for name in sorted(files))


def write_packet(sources, observations, output):
    validate_observations(observations)
    manifest = {"schema": SCHEMA, "sources": {}, "lineage": LINEAGE,
                "observations": observations, "gates": GATES, "boundaries": BOUNDARIES}
    payloads = {}
    for name in REPOSITORIES:
        files, identity = sources[name]
        need(REQUIRED[name] <= {entry[0] for entry in files}, "BUILD_SOURCE_INCOMPLETE")
        archive = archive_bytes(files)
        payloads[name + ".tar.gz"] = archive
        manifest["sources"][name] = {**identity, "repository": REPOSITORIES[name], "archive": name + ".tar.gz",
            "sha256": hashlib.sha256(archive).hexdigest(), "archive_bytes": len(archive),
            "file_count": len(files), "source_bytes": sum(len(entry[2]) for entry in files)}
    manifest_checks(manifest, observations["source_sha"])
    payloads["manifest.json"] = canonical(manifest)
    payloads["SHA256SUMS"] = checksum_bytes(payloads)
    need(output.parent.is_dir() and not output.parent.is_symlink(), "OUTPUT_PARENT_DENIED")
    output.mkdir(mode=0o700, exist_ok=False)
    for name, data in payloads.items():
        with (output / name).open("xb") as stream:
            stream.write(data)
        (output / name).chmod(0o600)
    return verify_packet(output, observations["source_sha"])


def verify_packet(directory, expected_candidate, expected_manifest=None):
    need(isinstance(expected_candidate, str) and SHA1.fullmatch(expected_candidate), "EXPECTED_CANDIDATE_REQUIRED")
    need(stat.S_ISDIR(directory.lstat().st_mode) and {p.name for p in directory.iterdir()} == PACKET_FILES, "PACKET_FILES_DENIED")
    raw = regular_bytes(directory / "manifest.json", 128 * 1024)
    digest = hashlib.sha256(raw).hexdigest()
    if expected_manifest is not None:
        need(isinstance(expected_manifest, str) and SHA256.fullmatch(expected_manifest)
             and digest == expected_manifest, "EXPECTED_MANIFEST_DENIED")
    manifest = closed_json(raw)
    need(canonical(manifest) == raw, "NON_CANONICAL_MANIFEST")
    manifest_checks(manifest, expected_candidate)
    payloads = {"manifest.json": raw}
    for name, source in manifest["sources"].items():
        archive = regular_bytes(directory / source["archive"], MAX_ARCHIVE_BYTES)
        inspect_archive(archive, source, REQUIRED[name])
        payloads[source["archive"]] = archive
    need(regular_bytes(directory / "SHA256SUMS", 1024) == checksum_bytes(payloads), "CHECKSUM_FILE_DENIED")
    return {"schema": "N8N_PRIVATE_OPERATOR_PACKET_VERIFIED_V1", "candidate_commit": expected_candidate,
            "manifest_sha256": digest, "source_archives": 3, "git_commit_and_tree_identity_verified": True,
            "expected_candidate_pin_from_caller": True,
            "source_bytes_verified": sum(s["source_bytes"] for s in manifest["sources"].values()),
            "expected_manifest_digest_supplied": expected_manifest is not None,
            "image_identity_verified_offline": False, "source_links_authenticate_approvals": False,
            "ci_observations_authenticated_offline": False, "checksums_are_ci_attestation": False,
            "go_for_installation": False}


def create_packet(candidate_repo, crm_repo, observations_path, output):
    need(os.environ.get("GITHUB_ACTIONS") == "true" and os.environ.get("GITHUB_REPOSITORY") == AGENTS_REPOSITORY
         and os.environ.get("P24_RUNNER_ENVIRONMENT") == "github-hosted", "CREATE_HOSTED_CI_ONLY")
    observations = closed_json(regular_bytes(observations_path, 32768))
    validate_observations(observations)
    for field, env in (("source_sha", "GITHUB_SHA"), ("run_id", "GITHUB_RUN_ID"), ("run_attempt", "GITHUB_RUN_ATTEMPT")):
        need(observations[field] == os.environ.get(env), "CI_OBSERVATIONS_BINDING_DENIED")
    candidate = observations["source_sha"]
    for repo, sha in ((candidate_repo, candidate), (crm_repo, FIXED_PINS["crm"])):
        need(git(repo, "rev-parse", "HEAD").decode().strip() == sha, "CHECKOUT_PIN_DENIED")
        git(repo, "diff", "--quiet")
        git(repo, "diff", "--cached", "--quiet")
    for ancestor in (*LINEAGE.values(), FIXED_PINS["adapter"]):
        git(candidate_repo, "merge-base", "--is-ancestor", ancestor, candidate)
    sources = {"candidate": git_source(candidate_repo, candidate),
               "crm": git_source(crm_repo, FIXED_PINS["crm"]),
               "adapter": git_source(candidate_repo, FIXED_PINS["adapter"])}
    return write_packet(sources, observations, output)


def main(argv=None):
    parser = argparse.ArgumentParser(description=__doc__)
    commands = parser.add_subparsers(dest="command", required=True)
    create = commands.add_parser("create")
    for option in ("candidate-repo", "crm-repo", "observations", "output-dir"):
        create.add_argument("--" + option, type=Path, required=True)
    verify = commands.add_parser("verify")
    verify.add_argument("directory", type=Path)
    verify.add_argument("--expected-candidate-sha", required=True)
    verify.add_argument("--expected-manifest-sha256")
    args = parser.parse_args(argv)
    try:
        result = (create_packet(args.candidate_repo, args.crm_repo, args.observations, args.output_dir)
                  if args.command == "create" else verify_packet(args.directory, args.expected_candidate_sha, args.expected_manifest_sha256))
        print(canonical(result).decode(), end="")
        return 0
    except (Denied, OSError, ValueError, UnicodeError, tarfile.TarError, subprocess.SubprocessError):
        # Paths, arbitrary Git errors and untrusted metadata never enter logs.
        print(json.dumps({"schema": "N8N_PRIVATE_OPERATOR_PACKET_DENIED_V1", "reason": "PACKET_DENIED", "go_for_installation": False}), file=sys.stderr)
        return 2


if __name__ == "__main__":
    raise SystemExit(main())
