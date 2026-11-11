"""EVID-48 protects the actual capacity deliverable and NO-GO history."""
from pathlib import Path
import unittest

ROOT = Path(__file__).resolve().parents[2]
DOC = ROOT / "docs/AIA-R01-EVID-48-F204-CI-POSTMERGE.md"


class Evid48Tests(unittest.TestCase):
    def test_exact_postmerge_sha_and_ci_are_documented(self):
        text = DOC.read_text(encoding="utf-8")
        self.assertIn("pull/285", text)
        self.assertIn("e6eee470f6b49d45488b2f93122cf5c452626695", text)
        self.assertIn("actions/runs/43090143368", text)
        self.assertIn("completed/success", text)

    def test_current_baseline_keeps_previous_evidence(self):
        status = (ROOT / "STATUS-ATUAL.md").read_text(encoding="utf-8")
        readme = (ROOT / "README.md").read_text(encoding="utf-8")
        agents = (ROOT / "AGENTS.md").read_text(encoding="utf-8")
        self.assertLess(len(status), 16000)
        self.assertLess(len(readme), 8000)
        for page in (status, readme, agents):
            for label in ("EVID-44", "EVID-45", "EVID-46", "EVID-47", "EVID-48"):
                self.assertIn(label, page)
            self.assertIn("NO-GO", page)
        self.assertIn("879 testes PHP / lint 109", status)
        self.assertIn("43038243942", status)
        self.assertIn("43088648397", status)

    def test_capacity_report_is_not_an_operational_permission(self):
        text = DOC.read_text(encoding="utf-8")
        for value in ("SYNTHETIC_QUEUE_CAPACITY_V1", "865 testes PHP",
                      "107 arquivos", "NAO_EXECUTADO", "OPEN/DRAFT",
                      "R01-G01..G11", "CORE/SEG/AUT", "NO-GO", "main"):
            self.assertIn(value, text)

    def test_full_quality_gate_includes_latest_evidence(self):
        gate = (ROOT / "tools/run_r01_offline_quality_gate.sh").read_text(encoding="utf-8")
        for name in ("test_evid47_postmerge.py", "test_evid48_postmerge.py",
                     "test_f201_triage_rehearsal.py", "test_f202_triage_tab.py",
                     "test_f203_triage_adversarial.py"):
            self.assertIn(name, gate)
        self.assertIn("tools/verify_f201_triage.py", gate)


if __name__ == "__main__":
    unittest.main()
