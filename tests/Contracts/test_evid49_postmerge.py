"""EVID-49 records post-merge PHP cross-repository evidence without GO."""
from pathlib import Path
import unittest

ROOT = Path(__file__).resolve().parents[2]
DOC = ROOT / "docs/AIA-R01-EVID-49-CRM-AGENTS-POSTMERGE.md"


class Evid49PostMerge(unittest.TestCase):
    def setUp(self):
        self.doc = DOC.read_text(encoding="utf-8")
        self.status = (ROOT / "STATUS-ATUAL.md").read_text(encoding="utf-8")
        self.readme = (ROOT / "README.md").read_text(encoding="utf-8")
        self.roadmap = (ROOT / "docs/ROADMAP.md").read_text(encoding="utf-8")
        self.gate = (ROOT / "tools/run_r01_offline_quality_gate.sh").read_text()

    def test_head_sha_postmerge_sha_and_exact_ci_all_recorded(self):
        for marker in (
            "d2d62cd4a3913e6094dcf3b1dc25ec0aebd627ea",
            "54478ea25193bbe3a61fb87d9056ff4baace9149",
            "0b2df8c70cfbd75b3237e7981813cb954841a809",
            "8461480179461710ca74b3641a4503e2a9cdc182",
            "43605107022", "43605185307", "43605536868", "43605619299",
            "7999b1e34a6cbd0ee1c5e2351067ad172e01bbad",
        ):
            self.assertIn(marker, self.doc)
        self.assertIn("completed/success", self.doc)

    def test_integrated_development_only(self):
        for marker in ("#293", "#294", "EVID-49", "879 testes PHP",
                       "lint 110", "43605185307", "43605619299"):
            self.assertIn(marker, self.status)
        self.assertIn("main", self.status)
        self.assertIn("NO-GO", self.status)
        self.assertIn("EVID-49", self.readme)
        self.assertIn("EVID-49", self.roadmap)
        self.assertLess(len(self.status), 16000)
        self.assertLess(len(self.readme), 8000)

    def test_no_approval_inferred_from_green_ci(self):
        for content in (self.doc, self.status, self.roadmap):
            self.assertIn("NO-GO", content)
            self.assertIn("NAO_EXECUTADO", content)
        self.assertIn("CRM PR #57", self.doc)
        self.assertIn("OPEN/DRAFT", self.doc)
        self.assertIn("13 cenários", self.doc)
        self.assertIn("0 homologados", self.doc)
        self.assertIn("test_evid49_postmerge.py", self.gate)


if __name__ == "__main__":
    unittest.main()
