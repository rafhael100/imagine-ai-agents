"""Regression checks for the read-only Imagine Agents development workflow.

Not a substitute for GitHub permissions, supply-chain review, or CRM IAM gates.
"""
from pathlib import Path
import re
import unittest

ROOT = Path(__file__).resolve().parents[2]
WORKFLOW = ROOT / ".github/workflows/r01-dev-integrity.yml"
CHECKOUT_SHA = "3d3c42e5aac5ba805825da76410c181273ba90b1"


class WorkflowGuardrails(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.workflow = WORKFLOW.read_text(encoding="utf-8")

    def test_checkout_is_exact_reviewed_release(self):
        uses = re.findall(r"(?m)^\s*uses:\s*(\S+)", self.workflow)
        self.assertEqual(uses, [f"actions/checkout@{CHECKOUT_SHA}"])
        self.assertIn("# v7.0.1 Node24 SHA pinned", self.workflow)

    def test_checkout_does_not_persist_credentials(self):
        self.assertRegex(self.workflow, r"persist-credentials:\s*false")

    def test_root_permissions_read_only(self):
        self.assertRegex(self.workflow, r"(?m)^permissions:\s*\n\s+contents:\s+read\b")
        self.assertNotRegex(self.workflow, r"(?m)^\s+contents:\s+write\b")

    def test_no_privileged_trigger(self):
        self.assertIn("  pull_request:\n", self.workflow)
        for trigger in ("pull_request_target:", "workflow_run:", "workflow_dispatch:"):
            self.assertNotIn(trigger, self.workflow)

    def test_demo_artifacts_trigger_both_push_and_pr_checks(self):
        # An examples-only change must not evade the unified offline CI.
        self.assertEqual(self.workflow.count("      - 'examples/**'"), 2)
        self.assertNotIn("examples/r01-op01-readiness-offline*.html", self.workflow)
        self.assertIn("  push:\n", self.workflow)
        self.assertIn("  pull_request:\n", self.workflow)

    def test_public_repository_only(self):
        self.assertIn("github.event.repository.private == false", self.workflow)

    def test_bounded_runner_and_concurrency(self):
        self.assertRegex(self.workflow, r"timeout-minutes:\s*6\b")
        self.assertIn("cancel-in-progress: true", self.workflow)

    def test_full_suite_bound_to_commit_sha(self):
        expected = "EXPECTED_SHA: " + chr(36) + "{{ github.sha }}"
        self.assertIn(expected, self.workflow)
        self.assertIn("bash tools/run_r01_offline_quality_gate.sh", self.workflow)

    def test_no_installation_or_credential_reference(self):
        for command in ("pip install", "npm install", "apt-get install",
                        "docker build", "secrets.", "curl -", "wget "):
            with self.subTest(command=command):
                self.assertNotIn(command, self.workflow)


if __name__ == "__main__":
    unittest.main()
