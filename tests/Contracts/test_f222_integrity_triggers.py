"""F222: no false-green CI when only governance files change or repo is private."""
from pathlib import Path
import unittest

ROOT=Path(__file__).resolve().parents[2]


class F222Integrity(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.workflow=(ROOT/".github/workflows/r01-dev-integrity.yml").read_text()
        cls.guard=(ROOT/"tests/Contracts/test_ci_guardrails.py").read_text()
        cls.gate=(ROOT/"tools/run_r01_offline_quality_gate.sh").read_text()
        cls.doc=(ROOT/"docs/AIA-FT01-F222-CI-SEM-SKIP.md").read_text()

    def test_no_path_filter_for_governance_changes(self):
        self.assertIn("  push:\n",self.workflow)
        self.assertIn("  pull_request:\n",self.workflow)
        self.assertNotIn("\n    paths:",self.workflow)
        self.assertNotIn("\n    paths-ignore:",self.workflow)
        self.assertNotIn("pull_request_target:",self.workflow)
        self.assertIn(
            "test_every_push_and_pr_runs_without_path_filter_bypass",self.guard)

    def test_private_source_fails_before_checkout(self):
        self.assertNotIn(
            "if: " + "$" + "{{ github.event.repository.private == false }}",
            self.workflow)
        self.assertIn(
            "if: " + "$" + "{{ github.event.repository.private == true }}",
            self.workflow)
        self.assertIn(
            "PRIVATE_CROSS_REPOSITORY_SOURCE_NOT_VERIFIED",self.workflow)
        self.assertLess(
            self.workflow.index("Fail closed when private"),
            self.workflow.index("Read-only checkout of reviewed SHA"))
        guard=self.workflow[self.workflow.index("Fail closed when private"):
                            self.workflow.index("Read-only checkout of reviewed SHA")]
        self.assertIn("exit 1",guard)

    def test_permissions_and_sha_stay_read_only(self):
        self.assertEqual(self.workflow.count(
            "actions/checkout@3d3c42e5aac5ba805825da76410c181273ba90b1"),2)
        self.assertEqual(self.workflow.count("persist-credentials: false"),2)
        self.assertIn("contents: read",self.workflow)
        self.assertIn("7999b1e34a6cbd0ee1c5e2351067ad172e01bbad",self.workflow)
        self.assertIn("EXPECTED_CASES: '879'",self.workflow)
        self.assertIn("EXPECTED_PHP: '110'",self.workflow)
        self.assertIn("test_f222_integrity_triggers.py",self.gate)

    def test_no_new_secrets_network_or_deploy(self):
        for banned in ("secrets.","npm install","docker build",
                       "contents: write","pull_request_target:",
                       "workflow_dispatch:"):
            self.assertNotIn(banned,self.workflow)
        for word in ("NO-GO","14 gates","13 cenários","CRM #57",
                     "private","README","AGENTS.md"):
            self.assertIn(word,self.doc)


if __name__=="__main__":
    unittest.main()
