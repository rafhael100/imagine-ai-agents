"""Synthetic admission counterproofs; no Docker or hosted-CI claim is made."""
from __future__ import annotations

import ast
import contextlib
import copy
import io
import os
from pathlib import Path
import subprocess
import sys
import tarfile
import tempfile
from types import SimpleNamespace
import unittest
from unittest.mock import patch

ROOT = Path(__file__).resolve().parents[1]
sys.path.insert(0, str(ROOT / "tools"))
import run_owner_component_candidate as driver


class OwnerComponentAdmissionTests(unittest.TestCase):
    def event_fixture(self):
        environment = {
            "GITHUB_ACTIONS": "true", "GITHUB_REPOSITORY": driver.REPOSITORY,
            "GITHUB_EVENT_NAME": "push", "GITHUB_REF": driver.BRANCH, "GITHUB_SHA": "a" * 40,
            "GITHUB_RUN_ID": "123", "GITHUB_RUN_ATTEMPT": "1",
            "N8N_CANDIDATE_REPOSITORY_PRIVATE": "false",
            "P24_RUNNER_ENVIRONMENT": "github-hosted", "P30_RUNNER_ENVIRONMENT": "github-hosted",
        }
        event = {"repository": {"full_name": driver.REPOSITORY, "private": False, "fork": False},
                 "ref": driver.BRANCH, "after": "a" * 40, "deleted": False}
        return environment, event

    def test_event_context_mutations_deny_before_any_command(self):
        environment, event = self.event_fixture()
        with patch.object(driver, "command", side_effect=AssertionError("no resource command permitted")):
            self.assertEqual(driver.validate_event(environment, event), "a" * 40)
            for key, value in (("GITHUB_ACTIONS", "false"), ("GITHUB_REPOSITORY", "fork/agents"),
                               ("GITHUB_EVENT_NAME", "pull_request"), ("GITHUB_REF", "refs/heads/main"),
                               ("GITHUB_SHA", "a" * 39), ("GITHUB_RUN_ID", "0"),
                               ("GITHUB_RUN_ATTEMPT", ""), ("N8N_CANDIDATE_REPOSITORY_PRIVATE", "true"),
                               ("P24_RUNNER_ENVIRONMENT", "self-hosted"), ("P30_RUNNER_ENVIRONMENT", "self-hosted"),
                               ("DOCKER_HOST", "tcp://example.invalid"), ("DOCKER_CONTEXT", "remote")):
                changed = {**environment, key: value}
                with self.subTest(key=key), self.assertRaises(driver.Denied):
                    driver.validate_event(changed, event)
            for mutate in (lambda x: x["repository"].update(full_name="fork/agents"),
                           lambda x: x["repository"].update(private=True),
                           lambda x: x["repository"].update(fork=True),
                           lambda x: x["repository"].update(private="false"),
                           lambda x: x.update(ref="refs/heads/main"),
                           lambda x: x.update(after="b" * 40), lambda x: x.update(deleted=True)):
                changed = copy.deepcopy(event)
                mutate(changed)
                with self.assertRaises(driver.Denied):
                    driver.validate_event(environment, changed)
            previous_branch = "refs/heads/integrate/n8n-owner-provider-components-2026-11-18"
            previous_environment = {**environment, "GITHUB_REF": previous_branch}
            previous_event = {**event, "ref": previous_branch}
            with self.assertRaisesRegex(driver.Denied, "REAL_HOSTED_PUSH_REQUIRED"):
                driver.validate_event(previous_environment, previous_event)

    def test_native_ancestor_without_integrated_provider_ancestor_is_denied(self):
        # Genuine Git ancestry with synthetic commits; no hosted identity or
        # Docker admission is fabricated to exercise this source boundary.
        with tempfile.TemporaryDirectory() as temporary:
            repo = Path(temporary)
            def fixture_git(*arguments):
                return subprocess.run(["git", "-C", str(repo), *arguments], check=True,
                                      capture_output=True).stdout.decode().strip()
            fixture_git("init", "-q")
            fixture_git("config", "user.name", "Synthetic Fixture")
            fixture_git("config", "user.email", "fixture@example.invalid")
            fixture_git("commit", "--allow-empty", "-q", "-m", "Native source fixture")
            native = fixture_git("rev-parse", "HEAD")
            fixture_git("commit", "--allow-empty", "-q", "-m", "Integrated providers fixture")
            integrated = fixture_git("rev-parse", "HEAD")
            fixture_git("commit", "--allow-empty", "-q", "-m", "Candidate descendant fixture")
            candidate = fixture_git("rev-parse", "HEAD")
            fixture_git("checkout", "-q", "--detach", native)
            fixture_git("commit", "--allow-empty", "-q", "-m", "Native-only sibling fixture")
            native_only = fixture_git("rev-parse", "HEAD")
            with patch.object(driver, "PARENT", native), patch.object(driver, "INTEGRATED_PARENT", integrated):
                driver.verify_candidate_lineage(repo, candidate)
                driver.git(repo, "merge-base", "--is-ancestor", native, native_only)
                with self.assertRaisesRegex(driver.Denied, "COMMAND_DENIED"):
                    driver.verify_candidate_lineage(repo, native_only)

    def test_duplicate_event_names_are_denied(self):
        with self.assertRaisesRegex(driver.Denied, "DUPLICATE_JSON_KEY"):
            driver.closed_json(b'{"after":"a","after":"b"}')

    def test_real_git_archive_modes_inventory_bytes_and_executable_boundaries(self):
        with tempfile.TemporaryDirectory() as temporary:
            root = Path(temporary)
            repo, exported = root / "repo", root / "owner"
            repo.mkdir(); exported.mkdir()
            (repo / "src").mkdir()
            (repo / "src/file.txt").write_text("public synthetic source\n")
            (repo / "run.sh").write_text("#!/bin/sh\nexit 0\n")
            (repo / "run.sh").chmod(0o755)
            for arguments in (("init", "-q"), ("config", "user.name", "Synthetic Fixture"),
                              ("config", "user.email", "fixture@example.invalid"), ("add", "."),
                              ("commit", "-q", "-m", "Synthetic archive fixture")):
                subprocess.run(["git", "-C", str(repo), *arguments], check=True, capture_output=True)
            archive = subprocess.run(["git", "-C", str(repo), "-c", "tar.umask=0022", "archive", "HEAD"],
                                     check=True, capture_output=True).stdout
            with tarfile.open(fileobj=io.BytesIO(archive)) as bundle:
                modes = {entry.name: entry.mode for entry in bundle if entry.isreg()}
            self.assertEqual(modes, {"run.sh": 0o755, "src/file.txt": 0o644})
            with tarfile.open(fileobj=io.BytesIO(archive)) as bundle:
                bundle.extractall(exported, filter="data")
            driver.verify_archive(exported, archive)
            path = exported / "src/file.txt"
            original = path.read_bytes()
            path.write_bytes(original + b"changed")
            with self.assertRaisesRegex(driver.Denied, "SOURCE_BLOB_OR_MODE"):
                driver.verify_archive(exported, archive)
            path.write_bytes(original)
            (exported / "extra").mkdir()
            with self.assertRaisesRegex(driver.Denied, "ARCHIVE_EXACT_INVENTORY"):
                driver.verify_archive(exported, archive)
            (exported / "extra").rmdir()
            (exported / "foreign.py").write_text("untracked shadow source")
            with self.assertRaisesRegex(driver.Denied, "ARCHIVE_EXACT_INVENTORY"):
                driver.verify_archive(exported, archive)
            (exported / "foreign.py").unlink()
            (exported / "run.sh").chmod(0o644)
            with self.assertRaisesRegex(driver.Denied, "SOURCE_BLOB_OR_MODE"):
                driver.verify_archive(exported, archive)
            (exported / "run.sh").chmod(0o755)
            path.unlink()
            with self.assertRaisesRegex(driver.Denied, "SOURCE_REGULAR_FILE"):
                driver.verify_archive(exported, archive)
            path.symlink_to(repo / "src/file.txt")
            with self.assertRaisesRegex(driver.Denied, "SOURCE_REGULAR_FILE"):
                driver.verify_archive(exported, archive)

    def test_imported_scenarios_preserve_bodies_and_historical_guards(self):
        explicit = os.environ.get("OWNER_COMPONENT_TEST_REFERENCE")
        reference = (Path(explicit) if explicit else
                     Path(os.environ["AIA_OWNER_COMPONENTS_CHECKOUT"]) / "_owner_components_pinned")
        for component in ("p67", "p68"):
            filename = "check_p67_owner_draft_lab.py" if component == "p67" else "check_p68_owner_draft_recovery.py"
            relative = "tools/" + filename
            original = (reference / relative).read_bytes()
            self.assertEqual(driver.blob(original), driver.IMPORTS[relative][2])
            old = ast.parse(original)
            new = ast.parse((ROOT / relative).read_bytes())
            old_functions = {node.name: node for node in old.body if isinstance(node, (ast.FunctionDef, ast.ClassDef))}
            new_functions = {node.name: node for node in new.body if isinstance(node, (ast.FunctionDef, ast.ClassDef))}
            for name, node in old_functions.items():
                if name != "main":
                    self.assertEqual(ast.dump(node), ast.dump(new_functions[name]), name)
            exercise = new_functions["exercise"]
            self.assertEqual([ast.dump(node) for node in old_functions["main"].body[1:]],
                             [ast.dump(node) for node in exercise.body[:-1]])
            self.assertEqual(ast.dump(exercise.body[-1]), ast.dump(ast.parse("return summary").body[0]))
            self.assertEqual(ast.dump(new_functions["main"].body[0]), ast.dump(old_functions["main"].body[0]))
            self.assertEqual(new_functions["main"].body[1].value.func.id, "exercise")
            self.assertEqual(len(new_functions["main"].body), 2)
        literal = [relative for relative, item in driver.IMPORTS.items() if item[2] == item[3]]
        self.assertEqual(len(literal), 12)
        for relative in literal:
            self.assertEqual(driver.blob((ROOT / relative).read_bytes()), driver.IMPORTS[relative][2])

    def test_module_from_another_archive_is_denied(self):
        expected = ROOT / "tools/check_p67_owner_draft_lab.py"
        driver.verify_module(SimpleNamespace(__file__=str(expected)), expected)
        for module in (SimpleNamespace(), SimpleNamespace(__file__="/foreign/check_p67_owner_draft_lab.py")):
            with self.assertRaisesRegex(driver.Denied, "MODULE_ORIGIN"):
                driver.verify_module(module, expected)

    def test_image_binding_requires_observed_distinct_build_and_current_source(self):
        identity, native, sha = "sha256:" + "a" * 64, "sha256:" + "b" * 64, "c" * 40
        observed = [{"Id": identity, "Config": {"User": "www-data", "Labels": {
            "imagine.owner.source_sha": sha, "imagine.owner.dockerfile_blob": "5aa7d966c429ec62f89922d5713c2d9c712fa338"}}}]
        self.assertEqual(driver.validate_owner_image(identity, observed, native, sha), identity)
        with self.assertRaisesRegex(driver.Denied, "SEPARATE_OWNER_IMAGE"):
            driver.validate_owner_image(identity, observed, identity, sha)
        for mutate in (lambda x: x[0].update(Id=native),
                       lambda x: x[0]["Config"].update(User="root"),
                       lambda x: x[0]["Config"]["Labels"].update({"imagine.owner.source_sha": "d" * 40}),
                       lambda x: x[0]["Config"]["Labels"].update({"imagine.owner.dockerfile_blob": "e" * 40})):
            changed = copy.deepcopy(observed)
            mutate(changed)
            with self.assertRaises(driver.Denied):
                driver.validate_owner_image(identity, changed, native, sha)

    def test_rejected_admission_never_imports_or_runs_the_exercise(self):
        output = io.StringIO()
        with patch.object(driver, "admit_sources", side_effect=driver.Denied("OWNER_COMPONENT_EVENT_PROVENANCE")), \
                patch.object(driver, "verify_owner_image") as image, patch.object(driver, "load_owner_modules") as load, \
                contextlib.redirect_stderr(output):
            self.assertEqual(driver.main(["p67"]), 1)
        image.assert_not_called(); load.assert_not_called()
        self.assertEqual(output.getvalue(), "OWNER_PROVIDER_COMPONENT_DENIED_OWNER_COMPONENT_EVENT_PROVENANCE\n")

    def test_exercise_and_subprocess_failures_are_sanitized_after_cleanup(self):
        context = {"source_sha": "a" * 40, "source_tree": "b" * 40, "run_id": "1", "run_attempt": "1", "temporary": Path("/tmp")}
        errors = (RuntimeError("SQL password=DO_NOT_EXPOSE mac=PRIVATE_VALUE"),
                  ValueError("P67_SECRET_THAT_LOOKS_LIKE_A_MARKER"),
                  subprocess.CalledProcessError(1, ["sql", "PRIVATE_VALUE"], output=b"PRIVATE_VALUE", stderr=b"PRIVATE_VALUE"))
        for error in errors:
            cleaned = []
            def failing_exercise(*_):
                try:
                    raise error
                finally:
                    cleaned.append(True)
            modules = {"check_p67_owner_draft_lab": SimpleNamespace(exercise=failing_exercise)}
            stdout, stderr = io.StringIO(), io.StringIO()
            with patch.object(driver, "admit_sources", return_value=context), \
                    patch.object(driver, "verify_owner_image", return_value="sha256:" + "c" * 64), \
                    patch.object(driver, "load_owner_modules", return_value=modules), \
                    contextlib.redirect_stdout(stdout), contextlib.redirect_stderr(stderr):
                self.assertEqual(driver.main(["p67"]), 1)
            self.assertEqual(cleaned, [True])
            self.assertEqual(stdout.getvalue(), "")
            self.assertEqual(stderr.getvalue(), "OWNER_PROVIDER_COMPONENT_DENIED_OWNER_COMPONENT_UNEXPECTED_FAILURE\n")


if __name__ == "__main__":
    unittest.main()
