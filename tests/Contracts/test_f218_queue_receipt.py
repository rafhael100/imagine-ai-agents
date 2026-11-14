"""F218: rejected queue and batch admission cannot retain a previous receipt."""
from pathlib import Path
import unittest

ROOT = Path(__file__).resolve().parents[2]


class F218QueueReceipt(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.pilot = (ROOT / "src/MockPilot/MockReadOnlyPilot.php").read_text()
        cls.queued = (ROOT / "src/MockPilot/MockQueuedReadOnlyPilot.php").read_text()
        cls.batch = (ROOT / "src/MockPilot/MockQueuedBatchRunner.php").read_text()
        cls.php = (ROOT / "tools/check_f218_queue_receipt.php").read_text()
        cls.gate = (ROOT / "tools/run_r01_offline_quality_gate.sh").read_text()
        cls.doc = (ROOT / "docs/AIA-FT01-F218-RECIBO-FILA-LOTE.md").read_text()

    def test_delegation_only_removes_read_rights(self):
        self.assertIn("public function discardReadReceipt(): void", self.pilot)
        self.assertIn("$this->edge->discardReadReceipt();", self.pilot)
        self.assertIn("public function discardReadReceipt(): void", self.queued)
        self.assertIn("$this->pilot->discardReadReceipt();", self.queued)

    def test_queued_attempt_invalidates_before_preflight(self):
        self.assertLess(
            self.queued.index("$this->discardReadReceipt();"),
            self.queued.index("$this->assertQueueAdmissionReady();"))
        self.assertGreaterEqual(self.queued.count("$this->discardReadReceipt();"), 3)
        self.assertIn("if (!$ok) {", self.queued)

    def test_batch_preflight_also_revokes_old_receipt(self):
        self.assertLess(
            self.batch.index("$this->pilot->discardReadReceipt();"),
            self.batch.index("$this->pilot->assertQueueAdmissionReady();"))

    def test_php_runner_verifies_recovery_and_no_extra_read(self):
        for name in ("SYNTHETIC_F218_REQUEST_A", "SYNTHETIC_F218_REQUEST_B",
                     "SYNTHETIC_F218_REQUEST_C", "'EMPTY'",
                     "['email']", "SYNTHETIC_FORGED",
                     "F218_QUEUE_RECEIPT_FAIL_CLOSED_PASS", "checks=4",
                     "write_calls=0"):
            self.assertIn(name, self.php)
        self.assertIn("$counts->snapshot()['attempts'] === 3", self.php)
        self.assertIn("$queue->counts()['CONCLUIDA'] === 3", self.php)

    def test_ci_includes_real_php_and_python_regressions(self):
        for name in ("test_f218_queue_receipt.py",
                     "php -l tools/check_f218_queue_receipt.php",
                     "php tools/check_f218_queue_receipt.php"):
            self.assertIn(name, self.gate)
        self.assertLess(
            self.gate.index("php tools/check_f217_pilot_receipt.php"),
            self.gate.index("php tools/check_f218_queue_receipt.php"))
        self.assertLess(
            self.gate.index("php tools/check_f218_queue_receipt.php"),
            self.gate.index("bash tools/validate_ft01_offline.sh"))

    def test_still_inert_and_no_go(self):
        for unsafe in ("curl_init(", "PDO(", "mysqli_", "getenv(",
                       "putenv(", "Authorization:", "Bearer ", "api.github.com"):
            self.assertNotIn(unsafe, self.php)
        for word in ("NO-GO", "CRM #57", "879", "110", "13"):
            self.assertIn(word, self.doc)


if __name__ == "__main__":
    unittest.main()
