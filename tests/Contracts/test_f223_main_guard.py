"""F223: main-targeted PRs must not receive a false-green DEV-only result."""
from pathlib import Path
import re
import unittest

ROOT=Path(__file__).resolve().parents[2]


class F223MainGuard(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.workflow=(ROOT/".github/workflows/r01-dev-integrity.yml").read_text()
        cls.gate=(ROOT/"tools/run_r01_offline_quality_gate.sh").read_text()
        cls.doc=(ROOT/"docs/AIA-FT01-F223-BLOQUEIO-PR-MAIN.md").read_text()

    def test_pr_main_trigger_is_present_without_push_main(self):
        self.assertIn("  pull_request:\n    branches:\n"
                      "      - dev/ft01-aia-core-mocks-2026-11-06\n"
                      "      - main\n",self.workflow)
        push_block=self.workflow[self.workflow.index("  push:"):
                                 self.workflow.index("  pull_request:")]
        self.assertNotIn("      - main",push_block)
        self.assertNotIn("    paths:",self.workflow)

    def test_main_guard_is_before_checkout_and_fails_explicitly(self):
        guard=self.workflow[self.workflow.index("Block main promotion"):
                            self.workflow.index("Fail closed when private")]
        expression="if: "+"$"+"{{ github.event_name == 'pull_request' && github.base_ref == 'main' }}"
        self.assertIn(expression,guard)
        self.assertIn("OP01_MAIN_PROMOTION_NO_GO",guard)
        self.assertIn("exit 1",guard)
        self.assertNotIn("continue-on-error: true",self.workflow)
        self.assertLess(self.workflow.index("Block main promotion"),
                        self.workflow.index("Read-only checkout of reviewed SHA"))

    def test_dev_gate_and_private_fail_closed_still_run(self):
        for item in ("if: "+"$"+"{{ github.event.repository.private == true }}",
                     "PRIVATE_CROSS_REPOSITORY_SOURCE_NOT_VERIFIED",
                     "EXPECTED_CASES: '879'",
                     "EXPECTED_PHP: '110'",
                     "contents: read",
                     "repository: rafhael100/imagine-crm",
                     "ref: d7c7aacc766c7ade4c65dcd211ed4569442144a9"):
            self.assertIn(item,self.workflow)
        self.assertIn("test_f223_main_guard.py",self.gate)
        self.assertEqual(len(re.findall(r"persist-credentials:\s*false",
                                        self.workflow)),2)

    def test_not_a_branch_protection_or_operational_go(self):
        for item in ("NO-GO","main","branch protection",
                     "CRM #57","14 gates","13 cenários",
                     "879","110"):
            self.assertIn(item,self.doc)
        for prohibited in ("secrets.","contents: write",
                           "pull_request_target:","workflow_dispatch:",
                           "curl -","docker build","npm install"):
            self.assertNotIn(prohibited,self.workflow)


if __name__=="__main__":
    unittest.main()
