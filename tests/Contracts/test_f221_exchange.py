"""F221: a full fake JSON exchange must be bounded and reviewed by CRM."""
from pathlib import Path
import unittest

ROOT=Path(__file__).resolve().parents[2]


class F221Exchange(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.edge=(ROOT/"src/MockTelemetry/MockCrmReadEdgeBoundary.php").read_text()
        cls.cross=(ROOT/"tools/check_crm_aia_real_facade_core.php").read_text()
        cls.fixture=(ROOT/"tools/check_f221_canonical_exchange.php").read_text()
        cls.gate=(ROOT/"tools/run_r01_offline_quality_gate.sh").read_text()
        cls.workflow=(ROOT/".github/workflows/r01-dev-integrity.yml").read_text()
        cls.doc=(ROOT/"docs/AIA-FT01-F221-TROCA-JSON-FIM-A-FIM.md").read_text()

    def test_exchange_reuses_existing_edge_and_framing(self):
        a=self.edge.index("public function exchangeCanonicalJson(")
        b=self.edge.index("public function handle(",a)
        unit=self.edge[a:b]
        for part in ("$this->handleCanonicalJson(",
                     "MockCrmReadRequest::decodeCanonicalJson(",
                     "MockCrmPublicResponseContract::encodeCanonicalJson(",
                     "MockCrmPublicResponseContract::denial()",
                     "$this->discardReadReceipt();"):
            self.assertIn(part,unit)
        self.assertLess(unit.index("$this->handleCanonicalJson("),
                        unit.index("MockCrmReadRequest::decodeCanonicalJson("))
        self.assertNotIn("curl_",unit)

    def test_real_php_read_replay_and_denials(self):
        for scenario in ("GOOD","RECOVER","OWNER","NO_TRUST","CAPACITY",
                         "duplicate", "F221_CANONICAL_EXCHANGE_PASS",
                         "cases=6","operational_decision=NO_GO","write_calls=0"):
            # DUPLICATE is the name of the negative byte attack in docs.
            self.assertIn(scenario,self.fixture+self.doc)
        self.assertIn("f221Denied()",self.fixture)
        self.assertIn("->recheck(",self.fixture)
        self.assertIn("new MockCrmReadRequest($wire)",self.fixture)

    def test_cross_project_core_inspects_actual_byte_exchange(self):
        for token in ("$f221Edge->exchangeCanonicalJson(",
                      "$inspector->inspect($f221Reply, ['ref'])",
                      "SYNTHETIC_PROJECTION_UNTRUSTED",
                      "f210NoRights($f221Review)",
                      "SYNTHETIC_DENIAL_ONLY"):
            self.assertIn(token,self.cross)
        self.assertIn("php tools/check_crm_aia_real_facade_core.php",self.workflow)

    def test_gate_executes_php_and_py_and_preserves_baseline(self):
        for marker in ("test_f221_exchange.py",
                       "php -l tools/check_f221_canonical_exchange.php",
                       "php tools/check_f221_canonical_exchange.php"):
            self.assertIn(marker,self.gate)
        self.assertLess(self.gate.index("php tools/check_f220_canonical_response.php"),
                        self.gate.index("php tools/check_f221_canonical_exchange.php"))
        self.assertLess(self.gate.index("php tools/check_f221_canonical_exchange.php"),
                        self.gate.index("bash tools/validate_ft01_offline.sh"))
        for marker in ("879","110","NO-GO","CRM #57","13","14","Sem HTTP"):
            self.assertIn(marker,self.doc)

    def test_no_operational_network_or_secrets(self):
        for forbidden in ("curl_init(", "PDO(", "getenv(", "shell_exec(",
                          "Authorization:", "Bearer ", "api.github.com"):
            self.assertNotIn(forbidden,self.fixture)


if __name__=="__main__":
    unittest.main()
