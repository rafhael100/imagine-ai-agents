"""F217: failures beyond the edge must not preserve an old synthetic receipt."""
from pathlib import Path
import unittest

ROOT = Path(__file__).resolve().parents[2]


class F217PilotReceipt(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.edge = (ROOT / "src/MockTelemetry/MockCrmReadEdgeBoundary.php").read_text()
        cls.pilot = (ROOT / "src/MockPilot/MockReadOnlyPilot.php").read_text()
        cls.php = (ROOT / "tools/check_f217_pilot_receipt.php").read_text()
        cls.gate = (ROOT / "tools/run_r01_offline_quality_gate.sh").read_text()
        cls.doc = (ROOT / "docs/AIA-FT01-F217-FALHA-PILOTO-RECIBO.md").read_text()

    def test_edge_exposes_only_reducing_local_receipt_operation(self):
        self.assertIn("public function discardReadReceipt(): void", self.edge)
        self.assertIn("$this->facade->discardReadReceipt();", self.edge)
        self.assertIn("No new read, audit, nonce", self.edge)

    def test_pilot_invalidates_before_preflight_and_after_failure(self):
        self.assertIn("$this->edge->discardReadReceipt();", self.pilot)
        self.assertEqual(self.pilot.count("$this->edge->discardReadReceipt();"), 3)
        self.assertLess(
            self.pilot.index("$this->edge->discardReadReceipt();"),
            self.pilot.index("$this->boundContext->assertSame($trustedContext);"))
        self.assertLess(
            self.pilot.index("} catch (\\Throwable) {\n"
                             "            // A former successful fake read"),
            self.pilot.index("$this->coordinator->transition($run, 'FALHOU');"))

    def test_real_php_runner_includes_normal_preflight_throw_and_forge(self):
        for token in ("'NORMAL'", "'RECOVERY'", "'PROVIDER_THROW'",
                      "'PROVIDER_FORGE'", "F217UnsafeSuggestionProvider",
                      "RUN_INVALID", "SYNTHETIC_FORGED_UNTRUSTED",
                      "checks=4", "write_calls=0", "operational_decision=NO_GO"):
            self.assertIn(token, self.php)
        self.assertIn("f217Check($edge->recheck(", self.php)
        self.assertIn("f217Check(!$edge->recheck(", self.php)

    def test_full_ci_verifies_php_and_static_regressions(self):
        for token in (
            "test_f216_edge_receipt.py", "test_f217_pilot_receipt.py",
            "php -l tools/check_f217_pilot_receipt.php",
            "php tools/check_f217_pilot_receipt.php",
        ):
            self.assertIn(token, self.gate)
        self.assertLess(
            self.gate.index("php tools/check_f216_edge_receipt.php"),
            self.gate.index("php tools/check_f217_pilot_receipt.php"))
        self.assertLess(
            self.gate.index("php tools/check_f217_pilot_receipt.php"),
            self.gate.index("bash tools/validate_ft01_offline.sh"))

    def test_synthetic_only_governance(self):
        for forbidden in ("curl_init(", "shell_exec(", "proc_open(", "PDO(",
                          "mysqli_", "getenv(", "putenv(", "Authorization:",
                          "Bearer ", "api.github.com", "file_get_contents('http"):
            self.assertNotIn(forbidden, self.php)
        for marker in ("879", "110", "NO-GO", "CRM #57", "13"):
            self.assertIn(marker, self.doc)


if __name__ == "__main__":
    unittest.main()
