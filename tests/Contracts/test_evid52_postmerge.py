"""EVID-52: six validated DEV rounds are not operational CRM approval."""
from pathlib import Path
import unittest

ROOT=Path(__file__).resolve().parents[2]


class EVID52(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.doc=(ROOT/"docs/AIA-R01-EVID-52-F215-F220-POSTMERGE.md").read_text()
        cls.status=(ROOT/"STATUS-ATUAL.md").read_text()
        cls.readme=(ROOT/"README.md").read_text()
        cls.roadmap=(ROOT/"docs/ROADMAP.md").read_text()
        cls.gate=(ROOT/"tools/run_r01_offline_quality_gate.sh").read_text()

    def test_evidence_has_all_exact_pr_and_postmerge_ci(self):
        for token in ("#303","#304","#305","#306","#307","#308",
                      "43611906504","43614546287","43615129946",
                      "43615686895","43618848393","43619321460",
                      "380aae8075a5e1a2e8377567d0e08c3fe401ae09",
                      "8cc0f590632b3b308d34bb9c415750210cf54f72"):
            self.assertIn(token,self.doc)

    def test_three_canonical_docs_preserve_previous_evidence(self):
        self.assertLess(len(self.status),16000)
        self.assertLess(len(self.readme),8000)
        self.assertLess(len(self.roadmap),12000)
        for old in ("EVID-49","EVID-50","EVID-51","EVID-52"):
            for text in (self.status,self.readme,self.roadmap):
                self.assertIn(old,text)
        for prior in ("#299","#300","#301","43609511492","43610007078",
                      "43610242488","879 testes PHP","lint 110"):
            self.assertIn(prior,self.status)

    def test_security_and_gate_never_claim_real_pilot(self):
        for marker in ("NO-GO","OPEN/DRAFT","CRM PR #57",
                       "NAO_EXECUTADO","14 gates","13 cenários",
                       "R01-G01..G11","CORE/SEG/AUT","main"):
            self.assertIn(marker,self.doc)
        for mandatory in ("test_f215_op01_testbook.py",
                          "test_f216_edge_receipt.py",
                          "test_f217_pilot_receipt.py",
                          "test_f218_queue_receipt.py",
                          "test_f219_canonical_json.py",
                          "test_f220_canonical_response.py",
                          "test_evid52_postmerge.py"):
            self.assertIn(mandatory,self.gate)

if __name__=="__main__":
    unittest.main()
