"""F216 — full PHP edge must invalidate stale receipts on denied new attempts."""
from pathlib import Path
import unittest

ROOT = Path(__file__).resolve().parents[2]


class F216EdgeReceipt(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.edge = (ROOT / "src/MockTelemetry/MockCrmReadEdgeBoundary.php").read_text()
        cls.facade = (ROOT / "src/MockBridge/MockCrmReadPublicFacade.php").read_text()
        cls.php = (ROOT / "tools/check_f216_edge_receipt.php").read_text()
        cls.gate = (ROOT / "tools/run_r01_offline_quality_gate.sh").read_text()
        cls.doc = (ROOT / "docs/AIA-FT01-F216-RECIBO-BORDA.md").read_text()

    def test_edge_invalidates_before_telemetry_begin(self):
        self.assertLess(
            self.edge.index("$this->facade->discardReadReceipt();"),
            self.edge.index("$ticket = $this->counters->begin();"))
        self.assertEqual(self.edge.split("public function handle(", 1)[1].count("$this->facade->discardReadReceipt();"), 2)

    def test_facade_taints_reentrant_read_without_authentication(self):
        self.assertIn("public function discardReadReceipt(): void", self.facade)
        self.assertIn("$this->lastReadReceipt = null;", self.facade)
        self.assertIn("if ($this->handlingRead)", self.facade)
        self.assertIn("$this->nestedReadObserved = true;", self.facade)
        self.assertIn("Not an operational", self.doc)

    def test_4_executable_scenarios_and_existing_positive_recheck(self):
        for token in (
            "SCHEMA", "SCHEMA_FRESH", "CAPACITY", "TELEMETRY", "REENTRY",
            "F216CounterTamperingReader", "F216NestedReader",
            "new MockCrmReadEdgeCounters(1)", "f216Denied()",
            "->recheck(", "checks=4", "NO_GO",
        ):
            self.assertIn(token, self.php)
        self.assertIn("f216Check($edge->recheck(", self.php)
        self.assertIn("f216Check(!$edge->recheck(", self.php)

    def test_ci_runs_source_code_and_contract(self):
        for token in (
            "test_f216_edge_receipt.py",
            "php -l tools/check_f216_edge_receipt.php",
            "php tools/check_f216_edge_receipt.php",
        ):
            self.assertIn(token, self.gate)
        self.assertLess(
            self.gate.index("php tools/check_f216_edge_receipt.php"),
            self.gate.index("bash tools/validate_ft01_offline.sh"))

    def test_no_real_network_files_secrets_or_customer_access(self):
        for banned in (
            "curl_init(", "proc_open(", "shell_exec(", "PDO(", "mysqli_",
            "Http::", "getenv(", "putenv(", "Authorization:", "Bearer ",
            "api.github.com", "file_get_contents('http",
        ):
            self.assertNotIn(banned, self.php)
        for marker in ("NO-GO", "CRM #57", "13", "879", "110"):
            self.assertIn(marker, self.doc)


if __name__ == "__main__":
    unittest.main()
