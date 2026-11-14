"""EVID-51: F212-214 tested and merged to dev, never authorized for real CRM."""
from pathlib import Path
import unittest

ROOT = Path(__file__).resolve().parents[2]


class EVID51(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.doc = (ROOT / "docs/AIA-R01-EVID-51-OP01-CAMINHO-CRITICO-POSTMERGE.md").read_text()
        cls.status = (ROOT / "STATUS-ATUAL.md").read_text()
        cls.readme = (ROOT / "README.md").read_text()
        cls.roadmap = (ROOT / "docs/ROADMAP.md").read_text()
        cls.gate = (ROOT / "tools/run_r01_offline_quality_gate.sh").read_text()

    def test_exact_six_ci_and_commit_evidence(self):
        for token in (
            "7b0ff512d76f9a2e48bd9c9f2b290228e527552a",
            "21a95ce83e4ff28d827febdab77acd01e3dfb077",
            "a100fec7cdec089651d6c8915cf6d399eb035e7b",
            "0a4c393f249abcd8c6b3ec72cdebf286cd7d1458",
            "a1b0ea5a9690229e7dd369cb7afc89dc2f487762",
            "986df65517a1ada86acc3626606195e653881289",
            "43609422027", "43609511492", "43609926838",
            "43610007078", "43610163653", "43610242488",
            "completed/success",
        ):
            self.assertIn(token, self.doc)

    def test_canonical_docs_preserve_history_and_bounds(self):
        self.assertLess(len(self.status), 16000)
        self.assertLess(len(self.readme), 8000)
        self.assertLess(len(self.roadmap), 12000)
        for token in ("EVID-49", "EVID-50", "EVID-51"):
            for content in (self.status, self.readme, self.roadmap):
                self.assertIn(token, content)
        for marker in ("#299", "#300", "#301", "879 testes PHP",
                       "lint 110", "43609511492", "43610007078",
                       "43610242488"):
            self.assertIn(marker, self.status)

    def test_no_go_cannot_be_conflated_with_synthetic_success(self):
        for document in (self.doc, self.status, self.roadmap):
            self.assertIn("NO-GO", document)
        for marker in ("CRM #57", "OPEN/DRAFT", "NAO_EXECUTADO",
                       "14 gates", "13 cenários", "R01-G01..G11",
                       "main", "NÃO está finalizado"):
            self.assertIn(marker, self.doc)
        for marker in ("test_f212_op01_critical_path.py",
                       "test_f213_op01_view.py",
                       "test_f214_github_summary.py",
                       "test_evid51_postmerge.py"):
            self.assertIn(marker, self.gate)


if __name__ == "__main__":
    unittest.main()
