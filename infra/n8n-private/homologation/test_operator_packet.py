#!/usr/bin/env python3
"""Offline serialization/security tests with an isolated, synthetic Git repo.

These fixtures do not run or impersonate GitHub Actions, n8n or an operator.
"""
from __future__ import annotations

import copy
import contextlib
import base64
import gzip
import hashlib
import io
import json
from pathlib import Path
import subprocess
import tempfile
import unittest
from unittest.mock import patch

import operator_packet as packet


class OperatorPacketTests(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.public_required_crm = frozenset(packet.REQUIRED["crm"])
        cls.public_crm_pin = packet.FIXED_PINS["crm"]
        cls.workspace = tempfile.TemporaryDirectory(prefix="operator-packet-unit-")
        cls.root = Path(cls.workspace.name)
        cls.repo = cls.root / "repo"
        cls.repo.mkdir()
        for path, content in {
            ".gitattributes": b"/.github export-ignore\n",
            ".github/workflows/example.yml": b"name: synthetic-test-only\n",
            "Dockerfile": b"FROM scratch\nCOPY src/contract.php /contract.php\n",
            "src/contract.php": b"<?php // synthetic public fixture\n",
            "composer.lock": b'{"packages":[]}\n',
            "docs/a/b/c/ação.md": b"public-source fixture\n",
            "empty": b"",
            "run.sh": b"#!/bin/sh\nexit 0\n",
        }.items():
            target = cls.repo / path
            target.parent.mkdir(parents=True, exist_ok=True)
            target.write_bytes(content)
        (cls.repo / "run.sh").chmod(0o755)
        for args in (("init", "-q"), ("config", "user.name", "Synthetic Unit Fixture"),
                     ("config", "user.email", "unit@example.invalid"), ("add", "."),
                     ("commit", "-q", "-m", "Synthetic public source fixture")):
            subprocess.run(["git", "-C", str(cls.repo), *args], check=True, capture_output=True)
        cls.commit = packet.git(cls.repo, "rev-parse", "HEAD").decode().strip()
        cls.files, cls.identity = packet.git_source(cls.repo, cls.commit)

    @classmethod
    def tearDownClass(cls):
        cls.workspace.cleanup()

    def setUp(self):
        self.temp = tempfile.TemporaryDirectory(dir=self.root)
        self.directory = Path(self.temp.name) / "packet"
        self.required = {"candidate": {"Dockerfile", "src/contract.php"},
                         "crm": {"composer.lock"}, "adapter": {"run.sh"}}
        self.pins_patch = patch.dict(packet.FIXED_PINS, {"crm": self.commit, "adapter": self.commit})
        self.required_patch = patch.object(packet, "REQUIRED", self.required)
        blobs = {path: packet.git_hash("blob", data) for path, mode, data in self.files}
        self.imports = {
            "owner_drafts": {"repository": packet.AGENTS_REPOSITORY, "source_commit": self.commit,
                "relationship": "SELECTED_FILES_NOT_ANCESTRY", "files": {
                    "src/contract.php": {"mode": "100644", "source_blob": blobs["src/contract.php"],
                        "candidate_blob": blobs["src/contract.php"], "adaptation": "NONE"}}},
            "owner_draft_recovery": {"repository": packet.AGENTS_REPOSITORY, "source_commit": self.commit,
                "relationship": "SELECTED_FILES_NOT_ANCESTRY", "files": {
                    "run.sh": {"mode": "100755", "source_blob": blobs["run.sh"],
                        "candidate_blob": blobs["run.sh"], "adaptation": "NONE"}}},
        }
        self.imports_patch = patch.object(packet, "IMPORTED_COMPONENTS", self.imports)
        self.pins_patch.start()
        self.required_patch.start()
        self.imports_patch.start()
        self.addCleanup(self.pins_patch.stop)
        self.addCleanup(self.required_patch.stop)
        self.addCleanup(self.imports_patch.stop)
        self.addCleanup(self.temp.cleanup)
        self.observations = {
            "schema": packet.OBSERVATIONS_SCHEMA, "run_id": "1", "run_attempt": "1", "source_sha": self.commit,
            "remote_images": {"n8n": packet.N8N_IMAGE, "n8n_postgres": "postgres@sha256:" + "a" * 64,
                              "agents_postgres": "postgres@sha256:" + "b" * 64, "nginx": "nginx@sha256:" + "c" * 64},
            "local_build_images": {"agents_php": "sha256:" + "d" * 64, "crm_php": "sha256:" + "e" * 64,
                                   "owner_php": "sha256:" + "f" * 64},
            "checks": dict.fromkeys(packet.CHECKS, "PASS"), "p30": copy.deepcopy(packet.P30),
            "owner_p67": copy.deepcopy(packet.OWNER_P67), "owner_p68": copy.deepcopy(packet.OWNER_P68),
        }

    def source_for(self, raw, files=None):
        selected = self.files if files is None else files
        return {**self.identity, "archive_bytes": len(raw), "sha256": hashlib.sha256(raw).hexdigest(),
                "file_count": len(selected), "source_bytes": sum(len(entry[2]) for entry in selected)}

    def make_packet(self):
        return packet.write_packet({name: (self.files, self.identity) for name in packet.REPOSITORIES},
                                   self.observations, self.directory)

    def test_complete_tree_ignores_export_ignore_and_untracked_worktree(self):
        # An untracked secret and a changed worktree file cannot enter Git blobs.
        (self.repo / ".env").write_text("SYNTHETIC_UNTRACKED_SECRET_DO_NOT_PACKAGE\n")
        (self.repo / "src/contract.php").write_text("worktree-only alteration\n")
        files, identity = packet.git_source(self.repo, self.commit)
        self.assertEqual(files, self.files)
        self.assertIn(".github/workflows/example.yml", {entry[0] for entry in files})
        self.assertNotIn(".env", {entry[0] for entry in files})
        self.assertEqual(identity["tree"], packet.git(self.repo, "rev-parse", self.commit + "^{tree}").decode().strip())

    def test_deterministic_archives_and_verified_full_packet(self):
        raw = packet.archive_bytes(self.files)
        self.assertEqual(raw, packet.archive_bytes(list(reversed(self.files))))
        packet.inspect_archive(raw, self.source_for(raw), self.required["candidate"])
        result = self.make_packet()
        verified = packet.verify_packet(self.directory, self.commit, result["manifest_sha256"])
        self.assertTrue(verified["git_commit_and_tree_identity_verified"])
        self.assertFalse(verified["go_for_installation"])
        self.assertTrue(verified["imported_component_candidate_blobs_verified"])
        self.assertFalse(verified["import_origins_verified_offline"])
        self.assertEqual({p.name for p in self.directory.iterdir()}, packet.PACKET_FILES)
        with self.assertRaises(FileExistsError):
            self.make_packet()

    def test_tar_mutations_and_incomplete_build_sources_are_denied(self):
        altered = [(path, mode, data + b"tampered" if path == "src/contract.php" else data)
                   for path, mode, data in self.files]
        for files in (altered, self.files + [("extra", "100644", b"x")],
                      self.files + [self.files[0]], [f for f in self.files if f[0] != "src/contract.php"]):
            with self.subTest(paths=[f[0] for f in files]):
                raw = packet.archive_bytes(files)
                with self.assertRaises(packet.Denied):
                    packet.inspect_archive(raw, self.source_for(raw, files), self.required["candidate"])
        for path in ("../escape", "/absolute", "C:/absolute", "a//b", "a\\b", "a/./b", ".git/config", ".env", "key.pem"):
            with self.subTest(path=path), self.assertRaises(packet.Denied):
                packet.archive_bytes([(path, "100644", b"x")])

    def test_symlink_duplicate_and_trailer_archives_denied(self):
        import tarfile
        output = io.BytesIO()
        with tarfile.open(fileobj=output, mode="w:gz") as archive:
            item = tarfile.TarInfo("src/contract.php")
            item.type, item.linkname = tarfile.SYMTYPE, "/etc/passwd"
            archive.addfile(item)
        raw = output.getvalue()
        with self.assertRaises(packet.Denied):
            packet.inspect_archive(raw, self.source_for(raw), set())

    def test_gzip_pax_expansion_is_bounded_before_tar_parser(self):
        import tarfile
        header = tarfile.TarInfo("pax-header")
        header.type, header.size = tarfile.XHDTYPE, 65536
        raw = gzip.compress(header.tobuf() + b"x" * 65536, mtime=0)
        with patch.object(packet, "MAX_TAR_BYTES", 2048), patch.object(packet.tarfile, "open") as parser:
            with self.assertRaisesRegex(packet.Denied, "ARCHIVE_DECOMPRESSION_LIMIT"):
                packet.inspect_archive(raw, self.source_for(raw), set())
            parser.assert_not_called()
        raw = b"not a gzip archive"
        with self.assertRaisesRegex(packet.Denied, "ARCHIVE_ENCODING_DENIED"):
            packet.inspect_archive(raw, self.source_for(raw), set())
        raw = bytearray(gzip.compress(b"x" * 1024, mtime=0))
        raw[10] = 7  # Reserved DEFLATE block type, before any tar header.
        raw = bytes(raw)
        with self.assertRaisesRegex(packet.Denied, "ARCHIVE_ENCODING_DENIED"):
            packet.inspect_archive(raw, self.source_for(raw), set())
        raw = packet.archive_bytes(self.files) + b"unreviewed trailer"
        with self.assertRaises(packet.Denied):
            packet.inspect_archive(raw, self.source_for(raw), set())

    def test_packet_file_tampering_extra_files_and_symlinks_denied(self):
        self.make_packet()
        archive = self.directory / "candidate.tar.gz"
        raw = archive.read_bytes()
        archive.write_bytes(raw[:-1] + bytes([raw[-1] ^ 1]))
        with self.assertRaises(packet.Denied):
            packet.verify_packet(self.directory, self.commit)
        archive.write_bytes(raw)
        extra = self.directory / "private.log"
        extra.write_text("must not enter operator artifact")
        with self.assertRaises(packet.Denied):
            packet.verify_packet(self.directory, self.commit)
        extra.unlink()
        archive.unlink()
        archive.symlink_to(self.repo / "composer.lock")
        with self.assertRaises(packet.Denied):
            packet.verify_packet(self.directory, self.commit)

    def test_closed_json_schema_and_operational_claims_denied(self):
        for raw in (b'{"source_sha":"a","source_sha":"b"}', b'{"x":NaN}'):
            with self.assertRaises(packet.Denied):
                packet.closed_json(raw)
        for mutate in (lambda x: x.update(secret="forbidden"),
                       lambda x: x["checks"].pop("p30_native_operator_mfa"),
                       lambda x: x["p30"].update(mfa_enforced_instance_wide=True),
                       lambda x: x["local_build_images"].update(agents_php="docker.io/private:latest"),
                       lambda x: x["remote_images"].update(n8n_postgres="postgres:17")):
            changed = copy.deepcopy(self.observations)
            mutate(changed)
            with self.assertRaises(packet.Denied):
                packet.validate_observations(changed)

    def test_independent_commit_and_manifest_pins_deny_rewritten_metadata(self):
        result = self.make_packet()
        with self.assertRaises(packet.Denied):
            packet.verify_packet(self.directory, "f" * 40)
        path = self.directory / "manifest.json"
        manifest = json.loads(path.read_bytes())
        manifest["observations"]["local_build_images"]["agents_php"] = "sha256:" + "f" * 64
        path.write_bytes(packet.canonical(manifest))
        files = {name: (self.directory / name).read_bytes() for name in packet.PACKET_FILES - {"SHA256SUMS"}}
        (self.directory / "SHA256SUMS").write_bytes(packet.checksum_bytes(files))
        # Hashes inside an untrusted packet cannot authenticate CI observations.
        with self.assertRaises(packet.Denied):
            packet.verify_packet(self.directory, self.commit, result["manifest_sha256"])

    def test_commit_tree_counts_and_approval_mutations_denied(self):
        self.make_packet()
        original = json.loads((self.directory / "manifest.json").read_bytes())
        for mutate in (
            lambda x: x["sources"]["candidate"].update(tree="a" * 40),
            lambda x: x["sources"]["candidate"].update(commit_object_b64=base64.b64encode(b"forged commit").decode()),
            lambda x: x["sources"]["candidate"].update(file_count=packet.MAX_FILES + 1),
            lambda x: x["sources"]["candidate"].update(source_bytes=packet.MAX_SOURCE_BYTES + 1),
            lambda x: x["sources"]["candidate"].update(archive="../candidate.tar.gz"),
            lambda x: x["boundaries"].update(go_for_installation=True),
            lambda x: x["boundaries"].update(ci_observations_authenticated_offline=True),
        ):
            changed = copy.deepcopy(original)
            mutate(changed)
            with self.assertRaises(packet.Denied):
                packet.manifest_checks(changed, self.commit)

    def test_create_cannot_run_outside_hosted_ci(self):
        stderr = io.StringIO()
        with patch.dict("os.environ", {}, clear=True), contextlib.redirect_stderr(stderr):
            result = packet.main(["create", "--candidate-repo", str(self.repo), "--crm-repo", str(self.repo),
                                  "--observations", "/unavailable-observations", "--output-dir", str(self.directory)])
        self.assertEqual(result, 2)
        self.assertFalse(self.directory.exists())
        self.assertNotIn("unavailable-observations", stderr.getvalue())

    def test_older_crm_packet_and_missing_candidate_lineage_are_denied(self):
        self.make_packet()
        original = json.loads((self.directory / "manifest.json").read_bytes())
        for previous_pin in ("e1b3b7f118eff0350a00789aecc7bb2bb2331464",
                             "96dbf1aed2d1cf3237bc3631c4594540a0168d2a"):
            previous_crm = copy.deepcopy(original)
            previous_crm["sources"]["crm"]["commit"] = previous_pin
            with patch.dict(packet.FIXED_PINS, {"crm": self.public_crm_pin}):
                with self.assertRaisesRegex(packet.Denied, "SOURCE_PIN_DENIED"):
                    packet.manifest_checks(previous_crm, self.commit)
        for ancestor in ("candidate_479", "candidate_482"):
            missing_ancestor = copy.deepcopy(original)
            missing_ancestor["lineage"].pop(ancestor)
            with self.assertRaisesRegex(packet.Denied, "MANIFEST_POLICY_DENIED"):
                packet.manifest_checks(missing_ancestor, self.commit)
        unexecuted_bridge = copy.deepcopy(original)
        unexecuted_bridge["observations"]["checks"].pop("crm_backup_bridge_contracts")
        with self.assertRaisesRegex(packet.Denied, "FIELDS_DENIED"):
            packet.manifest_checks(unexecuted_bridge, self.commit)
        wrong_scope = copy.deepcopy(original)
        wrong_scope["boundaries"]["owner_monitor_35_tables_exercised_here"] = True
        with self.assertRaisesRegex(packet.Denied, "MANIFEST_POLICY_DENIED"):
            packet.manifest_checks(wrong_scope, self.commit)

    def test_crm_migration_sources_required_for_creation_and_offline_inspection(self):
        # Public source membership is not a claim that CRM migration CI ran here.
        # Each omitted path has a coherent synthetic Git identity, so missing
        # source admission is the reason for denial, not a stale tree/checksum.
        added_sources = {
            "infra/homologation/schema.sql",
            "infra/homologation/20-runtime-roles.sh",
            "infra/homologation/s2s/install-candidate.sql",
            "infra/homologation/s2s/outbound-candidate.sql",
            "infra/homologation/s2s/owner-drafts.sql",
            "infra/homologation/migrations/001_automation_ledger_candidate.sql",
            "scripts/ops/private_crm_owner_drafts_ci.py",
            "scripts/ops/private_crm_owner_monitor_migration.py",
            "scripts/ops/test-private-crm-owner-monitor-migration.py",
            "scripts/ops/private_crm_base_monitor_migration.py",
            "scripts/ops/test-private-crm-base-monitor-migration.py",
            "tests/owner_draft_ci_admission_test.py",
            "tests/base_monitor_migration_contract_test.py",
            "scripts/ci/test_automation_incremental_drift.py",
            "scripts/ci/check_automation_incremental.sql",
            ".github/workflows/ci-crm-owner-agents-drafts.yml",
            ".github/workflows/central-auto-incremental-migration.yml",
            "docs/automation/CRM-AUTOMATION-MIGRATION-DRIFT.md",
            "docs/desenvolvimento/CRM-OWNER-MONITOR-INCREMENTAL-MIGRATION-2026-11-18.md",
            "docs/desenvolvimento/CRM-BASE-MONITOR-INCREMENTAL-MIGRATION-2026-11-18.md",
        }
        self.assertEqual(len(added_sources), 20)
        self.assertTrue(added_sources <= self.public_required_crm)
        existing_paths = {path for path, mode, data in self.files}
        complete = self.files + [(path, "100644", b"synthetic public source fixture\n")
                                 for path in sorted(self.public_required_crm - existing_paths)]

        def identity_for(files):
            tree = packet.tree_hash(files)
            original_commit = base64.b64decode(self.identity["commit_object_b64"])
            commit = b"tree " + tree.encode() + b"\n" + original_commit.split(b"\n", 1)[1]
            return {**self.identity, "tree": tree, "commit": packet.git_hash("commit", commit),
                    "commit_object_b64": base64.b64encode(commit).decode()}

        required = {**self.required, "crm": set(self.public_required_crm)}
        with patch.object(packet, "REQUIRED", required):
            complete_identity = identity_for(complete)
            sources = {name: (self.files, self.identity) for name in packet.REPOSITORIES}
            sources["crm"] = (complete, complete_identity)
            with patch.dict(packet.FIXED_PINS, {"crm": complete_identity["commit"]}):
                result = packet.write_packet(sources, self.observations, self.directory)
                verified = packet.verify_packet(self.directory, self.commit, result["manifest_sha256"])
                self.assertEqual(verified["source_archives"], 3)
                self.assertFalse(verified["ci_observations_authenticated_offline"])

            for index, missing in enumerate(sorted(added_sources)):
                with self.subTest(missing=missing):
                    files = [entry for entry in complete if entry[0] != missing]
                    identity = identity_for(files)
                    raw = packet.archive_bytes(files)
                    descriptor = {**identity, "archive_bytes": len(raw),
                                  "sha256": hashlib.sha256(raw).hexdigest(),
                                  "file_count": len(files), "source_bytes": sum(len(entry[2]) for entry in files)}
                    with self.assertRaisesRegex(packet.Denied, "BUILD_SOURCE_INCOMPLETE"):
                        packet.inspect_archive(raw, descriptor, required["crm"])
                    sources["crm"] = (files, identity)
                    denied_output = Path(self.temp.name) / ("missing-crm-source-" + str(index))
                    with patch.dict(packet.FIXED_PINS, {"crm": identity["commit"]}):
                        with self.assertRaisesRegex(packet.Denied, "BUILD_SOURCE_INCOMPLETE"):
                            packet.write_packet(sources, self.observations, denied_output)
                    self.assertFalse(denied_output.exists())

    def test_selected_imports_cannot_claim_ancestry_or_unproved_product_scope(self):
        self.make_packet()
        original = json.loads((self.directory / "manifest.json").read_bytes())
        self.assertNotIn("owner_drafts", original["lineage"])
        self.assertEqual(original["imported_components"], self.imports)
        for mutate in (
            lambda x: x["lineage"].update(owner_drafts=self.commit),
            lambda x: x["lineage"].pop("candidate_481"),
            lambda x: x["boundaries"].update(complete_agents_product=True),
            lambda x: x["boundaries"].update(import_origins_verified_offline=True),
            lambda x: x["boundaries"].update(owner_human_crm_session_verified=True),
            lambda x: x["boundaries"].update(combined_distributed_recovery=True),
            lambda x: x["imported_components"]["owner_drafts"].update(relationship="ANCESTOR"),
            lambda x: x["imported_components"]["owner_drafts"].update(source_commit="a" * 40),
            lambda x: x["imported_components"]["owner_drafts"]["files"].pop("src/contract.php"),
            lambda x: x["imported_components"]["owner_drafts"]["files"]["src/contract.php"].update(candidate_blob="a" * 40),
        ):
            changed = copy.deepcopy(original)
            mutate(changed)
            with self.assertRaises(packet.Denied):
                packet.manifest_checks(changed, self.commit)

    def test_changed_component_is_denied_even_with_recomputed_valid_git_identity(self):
        self.make_packet()
        manifest = json.loads((self.directory / "manifest.json").read_bytes())
        changed = [(path, mode, data + b"unreviewed owner mutation" if path == "src/contract.php" else data)
                   for path, mode, data in self.files]
        tree = packet.tree_hash(changed)
        commit = base64.b64decode(self.identity["commit_object_b64"])
        commit = b"tree " + tree.encode() + b"\n" + commit.split(b"\n", 1)[1]
        new_sha = packet.git_hash("commit", commit)
        archive = packet.archive_bytes(changed)
        manifest["sources"]["candidate"].update(
            commit=new_sha, tree=tree, commit_object_b64=base64.b64encode(commit).decode(),
            sha256=hashlib.sha256(archive).hexdigest(), archive_bytes=len(archive),
            source_bytes=sum(len(entry[2]) for entry in changed))
        manifest["observations"]["source_sha"] = new_sha
        (self.directory / "candidate.tar.gz").write_bytes(archive)
        (self.directory / "manifest.json").write_bytes(packet.canonical(manifest))
        files = {name: (self.directory / name).read_bytes() for name in packet.PACKET_FILES - {"SHA256SUMS"}}
        (self.directory / "SHA256SUMS").write_bytes(packet.checksum_bytes(files))
        # The source/commit/tree/checksum checks are all internally valid. The
        # independently closed component binding must still reject the change.
        packet.manifest_checks(manifest, new_sha)
        with self.assertRaisesRegex(packet.Denied, "IMPORTED_COMPONENT_CANDIDATE_BLOB_DENIED"):
            packet.verify_packet(self.directory, new_sha)

    def test_owner_checks_images_and_runtime_limits_are_mandatory(self):
        mutations = [
            lambda x: x.pop("owner_p67"),
            lambda x: x.pop("owner_p68"),
            lambda x: x["local_build_images"].pop("owner_php"),
            lambda x: x["local_build_images"].update(owner_php="owner:latest"),
            lambda x: x["local_build_images"].update(owner_php=x["local_build_images"]["agents_php"]),
            lambda x: x["local_build_images"].update(owner_php=x["local_build_images"]["crm_php"]),
            lambda x: x["owner_p67"].update(executed=True),
            lambda x: x["owner_p67"].update(human_crm_session_verified=True),
            lambda x: x["owner_p67"].update(positive_http=True),
            lambda x: x["owner_p67"].update(secret="not a permitted report field"),
            lambda x: x["owner_p68"].update(zero_rpo_or_distributed_recovery_proven=True),
            lambda x: x["owner_p68"].update(independent_disaster_authority_available=True),
            lambda x: x["owner_p68"].update(current_authority_from_still_live_fixture_source=False),
            lambda x: x["owner_p68"].update(restored_datasets=35),
        ]
        for check in ("owner_provider_admission_contracts", "owner_draft_contracts", "owner_draft_recovery"):
            changed = copy.deepcopy(self.observations)
            changed["checks"].pop(check)
            with self.assertRaisesRegex(packet.Denied, "FIELDS_DENIED"):
                packet.validate_observations(changed)
        for mutate in mutations:
            changed = copy.deepcopy(self.observations)
            mutate(changed)
            with self.assertRaises(packet.Denied):
                packet.validate_observations(changed)

    def test_owner_envelope_binds_result_to_source_run_image_and_component(self):
        identity = {"source_sha": self.commit, "source_tree": self.identity["tree"],
                    "run_id": "1", "run_attempt": "1", "owner_php_image": "sha256:" + "f" * 64}
        for component, report in (("p67", packet.OWNER_P67), ("p68", packet.OWNER_P68)):
            envelope = {"schema": "OWNER_PROVIDER_COMPONENT_RESULT_V1", "component": component,
                        **identity, "import_source_sha": self.commit, "result": copy.deepcopy(report),
                        "go_for_installation": False, "separate_from_native_feedback": True}
            self.assertEqual(packet.owner_component_result(envelope, component, **identity), report)
            for key, value in (("source_sha", "a" * 40), ("source_tree", "b" * 40),
                               ("run_id", "2"), ("run_attempt", "2"),
                               ("owner_php_image", "sha256:" + "a" * 64),
                               ("import_source_sha", "c" * 40), ("component", "p68" if component == "p67" else "p67"),
                               ("go_for_installation", True), ("separate_from_native_feedback", False)):
                changed = copy.deepcopy(envelope)
                changed[key] = value
                with self.assertRaisesRegex(packet.Denied, "OWNER_COMPONENT_CI_BINDING_DENIED"):
                    packet.owner_component_result(changed, component, **identity)
            changed = copy.deepcopy(envelope)
            changed["unreviewed"] = "must not be silently projected away"
            with self.assertRaisesRegex(packet.Denied, "FIELDS_DENIED"):
                packet.owner_component_result(changed, component, **identity)

    def test_original_component_membership_uses_pinned_git_objects(self):
        reference = Path(self.temp.name) / "owner-reference"
        subprocess.run(["git", "clone", "--quiet", "--no-hardlinks", str(self.repo), str(reference)],
                       check=True, capture_output=True)
        packet.verify_import_origins(reference)
        mutated = copy.deepcopy(self.imports)
        mutated["owner_drafts"]["files"]["src/contract.php"]["source_blob"] = "f" * 40
        with patch.object(packet, "IMPORTED_COMPONENTS", mutated):
            with self.assertRaisesRegex(packet.Denied, "OWNER_COMPONENT_SOURCE_BLOB_DENIED"):
                packet.verify_import_origins(reference)
        mutated = copy.deepcopy(self.imports)
        mutated["owner_drafts"]["files"]["src/contract.php"]["mode"] = "100755"
        with patch.object(packet, "IMPORTED_COMPONENTS", mutated):
            with self.assertRaisesRegex(packet.Denied, "OWNER_COMPONENT_SOURCE_BLOB_DENIED"):
                packet.verify_import_origins(reference)


if __name__ == "__main__":
    unittest.main()
