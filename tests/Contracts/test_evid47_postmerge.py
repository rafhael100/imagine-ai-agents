"""EVID-47: release notes cannot invent a green CI or production GO."""
from pathlib import Path
import unittest

ROOT = Path(__file__).resolve().parents[2]
EVIDENCE = ROOT / "docs/AIA-R01-EVID-47-F200-F203-CI-POSTMERGE.md"


class Evid47Tests(unittest.TestCase):
    def test_four_verified_postmerge_shas(self):
        note = EVIDENCE.read_text(encoding="utf-8")
        rows = [
            ("280", "99e0793c2b352794772d9f70db553c0728eacde1", "43086311234"),
            ("281", "3168601ddeeb8f98eae7a678c6de3fdda1038851", "43087249037"),
            ("282", "d964fa0a0175ce2b1fd7030af47f2afead2a6df7", "43088119546"),
            ("283", "c0cc7fa1de79c793d8bb48cb745ece50639c25d1", "43088648397"),
        ]
        for pr, sha, ci in rows:
            with self.subTest(pr=pr):
                self.assertIn("pull/" + pr, note)
                self.assertIn(sha, note)
                self.assertIn("actions/runs/" + ci, note)
        self.assertEqual(note.count("**success** |"), 4)

    def test_current_baseline_and_history_are_preserved(self):
        status = (ROOT / "STATUS-ATUAL.md").read_text(encoding="utf-8")
        readme = (ROOT / "README.md").read_text(encoding="utf-8")
        agents = (ROOT / "AGENTS.md").read_text(encoding="utf-8")
        self.assertLess(len(status), 16000)
        self.assertLess(len(readme), 8000)
        for body in (status, readme, agents):
            self.assertIn("EVID-44", body)
            self.assertIn("EVID-45", body)
            self.assertIn("EVID-46", body)
            self.assertIn("EVID-47", body)
            self.assertIn("NO-GO", body)
        self.assertIn("879 testes PHP / lint 110", status)
        self.assertIn("43038243942", status)
        self.assertIn("43088648397", status)
        self.assertIn("EVID-43", agents)

    def test_no_go_and_operational_blockers(self):
        data = EVIDENCE.read_text(encoding="utf-8")
        for marker in ("851 testes PHP", "106 arquivos", "24 cenários",
                       "OPEN/DRAFT", "NAO_EXECUTADO", "R01-G01..G11",
                       "CORE/SEG/AUT", "NO-GO", "main"):
            self.assertIn(marker, data)

    def test_unified_gate_keeps_all_earlier_regressions(self):
        gate = (ROOT / "tools/run_r01_offline_quality_gate.sh").read_text(encoding="utf-8")
        for name in ("test_evid44_postmerge.py", "test_evid45_postmerge.py",
                     "test_evid46_postmerge.py", "test_evid47_postmerge.py",
                     "test_f201_triage_rehearsal.py", "test_f202_triage_tab.py",
                     "test_f203_triage_adversarial.py"):
            self.assertIn(name, gate)
        self.assertIn("node tests/Browser/f202_triage_tamper_smoke.cjs", gate)


if __name__ == "__main__":
    unittest.main()
