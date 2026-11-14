"""EVID-50 postmerge trace without accidental operational approval."""
from pathlib import Path
import unittest

ROOT = Path(__file__).resolve().parents[2]


class EVID50(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.doc = (ROOT / "docs/AIA-R01-EVID-50-REAL-FACADE-CORE-CRM-POSTMERGE.md").read_text()
        cls.status = (ROOT / "STATUS-ATUAL.md").read_text()
        cls.readme = (ROOT / "README.md").read_text()
        cls.roadmap = (ROOT / "docs/ROADMAP.md").read_text()
        cls.gate = (ROOT / "tools/run_r01_offline_quality_gate.sh").read_text()

    def test_exact_head_merge_and_ci_evidence(self):
        for code in (
            "a7372f65a2b38668a41930807b24a34d0ce37f05",
            "60444d42221f6fb2e78476a313a35fa44a25ec89",
            "98bbe4fb12ac3c3565c98b0f13585a30d6d53b49",
            "d69458d6a4488e7e762206d9e159cd9cd4ac44ea",
            "43606859038", "43606939495", "43607201374", "43607272003",
            "7999b1e34a6cbd0ee1c5e2351067ad172e01bbad",
            "completed/success",
        ):
            self.assertIn(code, self.doc)

    def test_document_limits_and_current_status(self):
        self.assertLess(len(self.status), 16000)
        self.assertLess(len(self.readme), 8000)
        self.assertLess(len(self.roadmap), 12000)
        for token in ("EVID-49", "EVID-50"):
            for content in (self.status, self.readme, self.roadmap):
                self.assertIn(token, content)
        for token in ("#296", "#297", "879 testes PHP", "lint 110",
                      "43606939495", "43607272003"):
            self.assertIn(token, self.status)

    def test_no_go_and_prior_ci_still_mandatory(self):
        for content in (self.doc, self.status, self.roadmap):
            self.assertIn("NO-GO", content)
        for marker in ("CRM PR #57", "OPEN/DRAFT", "NAO_EXECUTADO",
                       "14 gates", "R01-G01..G11", "main"):
            self.assertIn(marker, self.doc)
        for test in ("test_evid49_postmerge.py", "test_evid50_postmerge.py",
                     "test_f210_crm_facade_core.py",
                     "test_f211_crm_owner_failclosed.py"):
            self.assertIn(test, self.gate)


if __name__ == "__main__":
    unittest.main()
