"""F214 exposes the validated, still-blocked workstreams in GitHub CI summary."""
from pathlib import Path
import re
import sys
import unittest

ROOT = Path(__file__).resolve().parents[2]
sys.path.insert(0, str(ROOT / "tools"))
import plan_op01_release as release


class F214Summary(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.workflow = (ROOT / ".github/workflows/r01-dev-integrity.yml").read_text()
        cls.gate = (ROOT / "tools/run_r01_offline_quality_gate.sh").read_text()
        cls.doc = (ROOT / "docs/AIA-FT01-F214-RESUMO-CI-OP01.md").read_text()

    def test_summary_is_after_hard_gate_and_before_crm_checkout(self):
        before = self.workflow.index("bash tools/run_r01_offline_quality_gate.sh")
        summary = self.workflow.index("Publish verified OP-01 NO-GO blockers")
        checkout = self.workflow.index("Checkout pinned CRM public mock source")
        self.assertLess(before, summary)
        self.assertLess(summary, checkout)
        self.assertIn("python3 tools/plan_op01_release.py --check", self.workflow)
        self.assertIn("python3 tools/plan_op01_release.py --format markdown >> "
                      '"$GITHUB_STEP_SUMMARY"', self.workflow)
        self.assertIn("set -euo pipefail", self.workflow)

    def test_no_checkout_proliferation_or_new_authorization(self):
        self.assertEqual(
            len(re.findall(r"(?m)^\s*uses:\s*actions/checkout@", self.workflow)), 2)
        self.assertEqual(self.workflow.count("persist-credentials: false"), 2)
        self.assertIn("contents: read", self.workflow)
        self.assertNotIn("contents: write", self.workflow)
        for banned in ("secrets.", "workflow_dispatch:", "pull_request_target:",
                       "curl -", "wget ", "docker build", "npm install"):
            self.assertNotIn(banned, self.workflow)

    def test_summary_content_comes_only_from_closed_review_packet(self):
        rendered = release.render_markdown(release.compile_plan())
        self.assertIn("NO-GO", rendered)
        self.assertEqual(rendered.count("\n## W"), 5)
        for label in ("OP01-N01", "OP01-P02", "CRM-AUT", "CRM-CORE",
                      "Privacidade", "Direção", "14 gates", "13 cenários"):
            self.assertIn(label, rendered)
        self.assertNotIn("GO APROVADO", rendered)

    def test_f214_gate_and_limitations_are_documented(self):
        self.assertIn("test_f214_github_summary.py", self.gate)
        self.assertIn("test_f213_op01_view.py", self.gate)
        for value in ("NO-GO", "NAO_EXECUTADO", "CRM #57",
                      "14 gates", "13 cenários", "879"):
            self.assertIn(value, self.doc)


if __name__ == "__main__":
    unittest.main()
