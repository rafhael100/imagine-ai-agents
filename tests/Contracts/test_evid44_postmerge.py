"""EVID-44 prevents losing the verified five-PR offline evidence chain."""
from pathlib import Path
import unittest

ROOT = Path(__file__).resolve().parents[2]
DOC = ROOT / "docs/AIA-R01-EVID-44-F191-F195-CI-POSTMERGE.md"


class Evid44Tests(unittest.TestCase):
    def test_exact_postmerge_ids_and_shas(self):
        doc = DOC.read_text(encoding="utf-8")
        rows = (
            ("268", "7fd30ac8ec3b5b5dc7885daa0802e583c15964d1", "43047660682"),
            ("269", "6867b8a2ffb534451c2f4dd1b979eb9a9e9d661f", "43048120334"),
            ("270", "2e386ef62078da610e3c146fb54ab64a93dee068", "43049412790"),
            ("271", "f4cbb06013ab03610bdf609ed629923db88a2053", "43049695153"),
            ("272", "e100631b047f5153d5c3a912bf4fd298b619f32a", "43050336317"),
        )
        for pr, sha, ci in rows:
            with self.subTest(pr=pr):
                self.assertIn("pull/" + pr, doc)
                self.assertIn(sha, doc)
                self.assertIn("actions/runs/" + ci, doc)
        self.assertEqual(doc.count(" success |"), 5)

    def test_synthetic_only_and_no_go_are_explicit(self):
        doc = DOC.read_text(encoding="utf-8")
        for key in ("835 testes PHP", "lint 103", "37 testes Python",
                    "NAO_EXECUTADO", "OPEN/DRAFT", "R01-G01..G11",
                    "CORE/SEG/AUT", "NO-GO", "main"):
            self.assertIn(key, doc)

    def test_canonical_indexes_keep_bounded_sizes(self):
        status = (ROOT / "STATUS-ATUAL.md").read_text(encoding="utf-8")
        readme = (ROOT / "README.md").read_text(encoding="utf-8")
        agents = (ROOT / "AGENTS.md").read_text(encoding="utf-8")
        self.assertLess(len(status), 16000)
        self.assertLess(len(readme), 8000)
        for body in (status, readme, agents):
            self.assertIn("EVID-44", body)
            self.assertIn("NO-GO", body)
        self.assertIn("EVID-43", agents)
        self.assertIn("EVID-43", status)

    def test_offline_quality_gate_invokes_evidence_guard(self):
        gate = (ROOT / "tools/run_r01_offline_quality_gate.sh").read_text(encoding="utf-8")
        self.assertIn("test_evid44_postmerge.py", gate)
        self.assertIn("test_f195_transition_delta.py", gate)


if __name__ == "__main__":
    unittest.main()
