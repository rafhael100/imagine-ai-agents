"""EVID-46 canonical status must match verified F199 evidence."""
from pathlib import Path
import unittest

ROOT = Path(__file__).resolve().parents[2]
DOC = ROOT / "docs/AIA-R01-EVID-46-F199-CI-POSTMERGE.md"


class Evid46Tests(unittest.TestCase):
    def test_exact_postmerge_evidence(self):
        doc = DOC.read_text(encoding="utf-8")
        for pr, sha, ci in (
            ("277", "c6fd19ee9b4fa9b847cd8b0801959624534aeeac", "43077639745"),
            ("278", "c355bd5ae7044ee17db90ffffdcb5006f64cb2a9", "43078227186"),
        ):
            self.assertIn("pull/" + pr, doc)
            self.assertIn(sha, doc)
            self.assertIn("actions/runs/" + ci, doc)
        self.assertEqual(doc.count("**success** |"), 2)

    def test_index_sizes_and_history(self):
        status = (ROOT / "STATUS-ATUAL.md").read_text(encoding="utf-8")
        readme = (ROOT / "README.md").read_text(encoding="utf-8")
        agents = (ROOT / "AGENTS.md").read_text(encoding="utf-8")
        self.assertLess(len(status), 16000)
        self.assertLess(len(readme), 8000)
        for value in (status, readme, agents):
            for marker in ("EVID-44", "EVID-45", "EVID-46", "NO-GO"):
                self.assertIn(marker, value)
        self.assertIn("43038243942", status)
        self.assertIn("EVID-43", agents)

    def test_no_production_release_or_fake_homologation(self):
        doc = DOC.read_text(encoding="utf-8")
        for marker in ("835 casos", "lint 103", "OPEN/DRAFT",
                       "NAO_EXECUTADO", "R01-G01..G11", "CORE/SEG/AUT",
                       "NO-GO", "main"):
            self.assertIn(marker, doc)

    def test_single_quality_gate_references_its_contract(self):
        script = (ROOT / "tools/run_r01_offline_quality_gate.sh").read_text(encoding="utf-8")
        self.assertIn("test_evid46_postmerge.py", script)
        self.assertIn("test_f199_agent_scope_preview.py", script)
        self.assertIn("test_evid45_postmerge.py", script)


if __name__ == "__main__":
    unittest.main()
