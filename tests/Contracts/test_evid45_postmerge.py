"""EVID-45: no fabricated post-merge evidence or accidental GO."""
from pathlib import Path
import unittest

ROOT = Path(__file__).resolve().parents[2]
DOC = ROOT / "docs/AIA-R01-EVID-45-F196-F198-CI-POSTMERGE.md"


class Evid45Tests(unittest.TestCase):
    def test_three_verified_postmerge_sha_pairs(self):
        text = DOC.read_text(encoding="utf-8")
        tuples = [
            ("274", "dc9a0b71ff970f7594e210d157f480bdcc84587a", "43075578789"),
            ("275", "808dd778c208a59220dad9c1897148d689ca3132", "43076103326"),
            ("276", "fa5046e0bce0e343e7f645f9b91de6ddb4467470", "43076603247"),
        ]
        for pr, sha, run in tuples:
            with self.subTest(pr=pr):
                self.assertIn("pull/" + pr, text)
                self.assertIn(sha, text)
                self.assertIn("actions/runs/" + run, text)
        self.assertEqual(text.count("**success** |"), 3)

    def test_bounded_status_indices_keep_document_history(self):
        status = (ROOT / "STATUS-ATUAL.md").read_text(encoding="utf-8")
        readme = (ROOT / "README.md").read_text(encoding="utf-8")
        agents = (ROOT / "AGENTS.md").read_text(encoding="utf-8")
        self.assertLess(len(status), 16000)
        self.assertLess(len(readme), 8000)
        for value in (status, readme, agents):
            self.assertIn("EVID-44", value)
            self.assertIn("EVID-45", value)
            self.assertIn("NO-GO", value)
        self.assertIn("EVID-43", status)
        self.assertIn("EVID-43", agents)

    def test_operational_approval_cannot_be_inferred_from_green_ci(self):
        data = DOC.read_text(encoding="utf-8")
        for required in ("835 testes", "lint 103", "16 testes Python",
                         "16 casos adversariais", "NAO_EXECUTADO",
                         "OPEN/DRAFT", "CORE/SEG/AUT", "NO-GO", "main"):
            self.assertIn(required, data)

    def test_quality_gate_keeps_earlier_evidence_and_new_contract(self):
        gate = (ROOT / "tools/run_r01_offline_quality_gate.sh").read_text(encoding="utf-8")
        self.assertIn("test_evid44_postmerge.py", gate)
        self.assertIn("test_evid45_postmerge.py", gate)
        self.assertIn("test_f198_tamper_gate.py", gate)
        self.assertIn("f196_tamper_smoke.cjs", gate)


if __name__ == "__main__":
    unittest.main()
