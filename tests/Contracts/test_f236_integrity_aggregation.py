"""F236: unified offline integrity gate must cover all stacked security labs."""
from pathlib import Path
import unittest

ROOT = Path(__file__).resolve().parents[2]

class F236UnifiedGate(unittest.TestCase):
    def test_file_effects_are_not_in_protected_application_src(self):
        self.assertFalse((ROOT/"src/MockBridge/MockFileAtomicNonceLab.php").exists())
        self.assertTrue((ROOT/"tools/labs/MockFileAtomicNonceLab.php").exists())
        worker=(ROOT/"tools/f234_nonce_worker.php").read_text()
        self.assertIn("tools/labs/MockFileAtomicNonceLab.php", worker)
        scanner=(ROOT/"tools/check_synthetic_php_isolation.php").read_text()
        self.assertIn("dirname(__DIR__) . '/src'", scanner)
        self.assertIn("'fopen'", scanner)

    def test_full_r01_gate_runs_all_proofs_and_no_go_is_preserved(self):
        gate=(ROOT/"tools/run_r01_offline_quality_gate.sh").read_text()
        for s in ("php tools/check_f231_ed25519_preflight.php",
                  "php tools/check_f232_scope_preflight.php",
                  "php tools/check_f233_shared_nonce.php",
                  "python3 tools/check_f234_multiprocess.py",
                  "php tools/check_f235_composed_gate.php",
                  "php tools/check_synthetic_php_isolation.php"):
            self.assertIn(s,gate)
        self.assertIn("R01_OFFLINE_GATE_PASS operational_decision=NO_GO", gate)

    def test_expected_lint_inventory_matches_new_pure_sources(self):
        workflow=(ROOT/".github/workflows/r01-dev-integrity.yml").read_text()
        validator=(ROOT/"tools/validate_ft01_offline.sh").read_text()
        self.assertIn("EXPECTED_PHP: '120'",workflow)
        self.assertIn('expected_php="${EXPECTED_PHP:-120}"',validator)
        self.assertIn("PRIVATE_CROSS_REPOSITORY_SOURCE_NOT_VERIFIED",workflow)
        self.assertIn("OP01_MAIN_PROMOTION_NO_GO",workflow)
        for path in ("MockSyntheticScopePreflight.php",
                     "MockSharedNonceLedger.php",
                     "MockSyntheticEvidenceGate.php"):
            self.assertTrue((ROOT/"src/MockBridge"/path).is_file())

if __name__ == "__main__":
    unittest.main()
