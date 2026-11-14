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
        # Exactly two pinned sources: Agents HEAD + an immutable public CRM SHA.
        self.assertEqual(uses, [f"actions/checkout@{CHECKOUT_SHA}"] * 2)
        self.assertIn("# v7.0.1 Node24 SHA pinned", self.workflow)
        self.assertEqual(self.workflow.count("repository: rafhael100/imagine-crm"), 1)
        self.assertEqual(self.workflow.count(
            "ref: d7c7aacc766c7ade4c65dcd211ed4569442144a9"), 1)
        self.assertEqual(self.workflow.count("path: _synthetic_crm_fixture"), 1)
        self.assertIn(
            'test "$(git -C _synthetic_crm_fixture rev-parse HEAD)" = '
            '"d7c7aacc766c7ade4c65dcd211ed4569442144a9"',
            self.workflow,
        )

    def test_checkout_does_not_persist_credentials(self):
        self.assertEqual(
            len(re.findall(r"persist-credentials:\s*false", self.workflow)), 2)
        self.assertNotIn("persist-credentials: true", self.workflow)

    def test_root_permissions_read_only(self):
        self.assertRegex(self.workflow, r"(?m)^permissions:\s*\n\s+contents:\s+read\b")
        self.assertNotRegex(self.workflow, r"(?m)^\s+contents:\s+write\b")

    def test_no_privileged_trigger(self):
        self.assertIn("  pull_request:\n", self.workflow)
        for trigger in ("pull_request_target:", "workflow_run:", "workflow_dispatch:"):
            self.assertNotIn(trigger, self.workflow)

    def test_every_push_and_pr_runs_without_path_filter_bypass(self):
        # README, AGENTS, status and docs-only changes must trigger offline CI.
        self.assertIn("  push:\n", self.workflow)
        self.assertIn("  pull_request:\n", self.workflow)
        self.assertNotIn("\n    paths:", self.workflow)
        self.assertNotIn("\n    paths-ignore:", self.workflow)
        self.assertNotIn("examples/r01-op01-readiness-offline*.html", self.workflow)

    def test_legacy_read_tool_cannot_bypass_owner_policy(self):
        dispatcher = (ROOT / "src/MockTooling/MockToolDispatcher.php").read_text(
            encoding="utf-8"
        )
        self.assertIn("MockError::fail('READ_BOUNDARY_REQUIRED')", dispatcher)
        self.assertNotIn("MockCrmReader", dispatcher)
        self.assertNotIn("->lead(", dispatcher)
        # The separate, explicitly owned OP-01 path remains available.
        pilot = (ROOT / "src/MockPilot/MockReadOnlyPilot.php").read_text(
            encoding="utf-8"
        )
        self.assertIn("$this->edge->handle(", pilot)

    def test_private_repository_fails_explicitly_not_skipped_green(self):
        self.assertNotIn("if: " + "$" + "{{ github.event.repository.private == false }}",
                         self.workflow)
        self.assertIn("if: " + "$" + "{{ github.event.repository.private == true }}",
                      self.workflow)
        self.assertIn("PRIVATE_CROSS_REPOSITORY_SOURCE_NOT_VERIFIED", self.workflow)
        self.assertLess(
            self.workflow.index("Fail closed when private"),
            self.workflow.index("Read-only checkout of reviewed SHA"))

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
