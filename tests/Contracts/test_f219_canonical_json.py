"""F219 canonical JSON framing must fail closed on ambiguous bytes."""
from pathlib import Path
import unittest
ROOT = Path(__file__).resolve().parents[2]

class F219CanonicalJSON(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.request=(ROOT/"src/MockBridge/MockCrmReadRequest.php").read_text()
        cls.edge=(ROOT/"src/MockTelemetry/MockCrmReadEdgeBoundary.php").read_text()
        cls.fixture=(ROOT/"tools/check_f219_canonical_json.php").read_text()
        cls.gate=(ROOT/"tools/run_r01_offline_quality_gate.sh").read_text()
        cls.doc=(ROOT/"docs/AIA-FT01-F219-JSON-CANONICO-OP01.md").read_text()

    def test_raw_bytes_are_bounded_and_compared(self):
        for m in ("decodeCanonicalJson(string $body)", "encodeCanonicalJson(array $wire)",
                  "strlen($body) > 1024", "json_decode($body, true, 4, JSON_THROW_ON_ERROR)",
                  "hash_equals($canonical, $body)", "new self($wire)"):
            self.assertIn(m,self.request)

    def test_entry_produces_uniform_recorded_denials(self):
        i=self.edge.index("public function handleCanonicalJson(")
        j=self.edge.index("public function handle(",i)
        block=self.edge[i:j]
        self.assertLess(block.index("$this->discardReadReceipt();"),
                        block.index("MockCrmReadRequest::decodeCanonicalJson($body)"))
        self.assertIn("return $this->handle([], $envelope, $owner, $trustedContext, $now);",block)
        self.assertIn("return $this->handle($wire, $envelope, $owner, $trustedContext, $now);",block)

    def test_adversarial_php_exercises_success_and_recovery(self):
        for m in ("DUPLICATE","WHITESPACE","ESCAPED","TRAILING","REORDER",
                  "UNKNOWNKEY","DUPFIELD","PERSONAL","BADVERSION",
                  "OVERSIZE","INVALIDUTF8","NULLROOT","ARRAYROOT",
                  "DEEP","EMPTY","ORDERED","_FRESH",
                  "F219_CANONICAL_JSON_PASS negatives=", "operational_decision=NO_GO"):
            self.assertIn(m,self.fixture)
        self.assertIn("$edge->recheck(",self.fixture)

    def test_ci_runs_full_regression(self):
        for m in ("test_f219_canonical_json.py",
                  "php -l tools/check_f219_canonical_json.php",
                  "php tools/check_f219_canonical_json.php"):
            self.assertIn(m,self.gate)
        self.assertLess(
            self.gate.index("php tools/check_f218_queue_receipt.php"),
            self.gate.index("php tools/check_f219_canonical_json.php"))
        self.assertLess(
            self.gate.index("php tools/check_f219_canonical_json.php"),
            self.gate.index("bash tools/validate_ft01_offline.sh"))

    def test_no_operational_permissions(self):
        for m in ("NO-GO","CRM #57","879","110","13","14","No HTTP"):
            self.assertIn(m,self.doc)
        for forbidden in ("curl_init(", "PDO(", "getenv(",
                          "Authorization:", "Bearer ", "api.github.com"):
            self.assertNotIn(forbidden,self.fixture)

if __name__=="__main__":
    unittest.main()
