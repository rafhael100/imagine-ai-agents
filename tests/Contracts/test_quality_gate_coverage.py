"""Read-only checks for the single command offline gate, never an API permission."""
from pathlib import Path
import unittest

ROOT = Path(__file__).resolve().parents[2]
SCRIPT = ROOT / "tools/run_r01_offline_quality_gate.sh"
WORKFLOW = ROOT / ".github/workflows/r01-dev-integrity.yml"


class QualityGateCoverage(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.script = SCRIPT.read_text(encoding="utf-8")
        cls.workflow = WORKFLOW.read_text(encoding="utf-8")

    def test_all_offline_contract_regressions_still_run(self):
        for name in (
            "test_r01_readiness.py", "test_op01_contract_parity.py",
            "test_op01_parser_mutation_parity.py",
            "test_op01_bilateral_packet.py", "test_op01_contract_matrix.py", "test_op01_public_response_parity.py", "test_f127_offline_pilot_demo.py", "test_f180_queued_batch_demo.py", "test_f181_queue_observation_demo.py", "test_f182_queue_supervision_demo.py",
            "test_ci_guardrails.py",
            "test_op01_readiness_dashboard.py", "test_op01_dashboard_generator.py",
            "test_synthetic_php_isolation.py", "test_quality_gate_coverage.py",
            "test_canonical_project_status.py",
        ):
            with self.subTest(name=name):
                self.assertIn(name, self.script)

    def test_inventory_parity_and_panel_are_required(self):
        for command in (
            "tools/check_r01_readiness.py",
            "tools/check_op01_contract_parity.py",
            "tools/generate_op01_bilateral_packet.py --check",
            "tools/check_op01_public_response_parity.py",
            "tools/generate_op01_dashboard.py --check",
            "tools/check_op01_readiness_dashboard.py",
        ):
            with self.subTest(command=command):
                self.assertIn(command, self.script)

    def test_php_probe_isolation_and_official_regression_are_required(self):
        self.assertIn("php -l tools/op01_php_parser_probe.php", self.script)
        self.assertIn("php -l tools/op01_php_public_response_probe.php", self.script)
        self.assertIn("php tools/check_synthetic_php_isolation.php", self.script)
        self.assertIn("bash tools/validate_ft01_offline.sh", self.script)

    def test_missing_sha_fails_before_any_validation(self):
        self.assertIn("printenv EXPECTED_SHA", self.script)
        self.assertIn("exit 2", self.script)
        self.assertLess(self.script.index("printenv EXPECTED_SHA"),
                        self.script.index("python3 tools/check_r01_readiness.py"))

    def test_ci_calls_same_local_entrypoint(self):
        self.assertIn("bash tools/run_r01_offline_quality_gate.sh", self.workflow)
        self.assertIn("EXPECTED_PHP: '97'", self.workflow)
        self.assertIn("EXPECTED_CASES: '776'", self.workflow)

    def test_no_installation_or_direct_external_integration(self):
        for command in ("pip install", "npm install", "apt-get install",
                        "docker run", "docker build", "curl ", "wget ",
                        "git clone", "secrets."):
            with self.subTest(command=command):
                self.assertNotIn(command, self.script)

    def test_success_does_not_report_operational_go(self):
        self.assertIn("operational_decision=NO_GO", self.script)
        self.assertNotIn("operational_decision=GO", self.script)


if __name__ == "__main__":
    unittest.main()
