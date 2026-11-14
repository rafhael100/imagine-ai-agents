"""F209 adversarial protections of 13-case synthetic source-code matrix."""
from copy import deepcopy
from pathlib import Path
import sys
import unittest

ROOT = Path(__file__).resolve().parents[2]
sys.path.insert(0, str(ROOT / "tools"))
import verify_f209_matrix as f209


def fixture():
    return {
        "contract": "SYNTHETIC_CRM_AIA_OP01_MATRIX_V1",
        "scope": "TWO_REPOSITORY_SOURCE_CODE_ONLY",
        "cases": [
            {"id": name, "synthetic_request_shape_valid": f209.SHAPE_VALID[name],
             "mock_response": "DENIED_OR_NOT_FOUND",
             "operational_status": "NAO_EXECUTADO"}
            for name in f209.IDS
        ],
        "cases_real_executed": 0,
        "operational_decision": "NO_GO",
        "real_authorization": False,
        "external_calls": 0,
    }


class F209MatrixTests(unittest.TestCase):
    def mutate(self, change):
        data = deepcopy(fixture())
        change(data)
        with self.assertRaises(ValueError):
            f209.verify(data)

    def test_valid_closed_matrix(self):
        self.assertTrue(f209.verify(fixture()))
        self.assertEqual(len(f209.IDS), 13)

    def test_go_cannot_be_forged(self):
        self.mutate(lambda d: d.update(operational_decision="GO"))

    def test_real_execution_cannot_be_claimed(self):
        self.mutate(lambda d: d.update(cases_real_executed=13))

    def test_one_case_cannot_claim_operational_execution(self):
        self.mutate(lambda d: d["cases"][1].update(operational_status="PASS"))

    def test_positive_case_cannot_become_grant(self):
        self.mutate(lambda d: d["cases"][-1].update(mock_response="READ_SYNTHETIC_ONLY"))

    def test_boolean_instead_of_integer_cannot_be_coerced(self):
        self.mutate(lambda d: d.update(external_calls=False))

    def test_order_and_duplicates_are_rejected(self):
        self.mutate(lambda d: d["cases"][0].update(id="OP01-N02"))

    def test_tenant_or_secrets_cannot_be_added_to_output(self):
        self.mutate(lambda d: d["cases"][0].update(tenant_ref="SYNTHETIC_A"))

    def test_parser_rejects_duplicate_status_json(self):
        with self.assertRaises(ValueError):
            f209.decode_fixture_json('{"operational_decision":"NO_GO",'
                                    '"operational_decision":"GO"}')

    def test_ci_runs_actual_php_and_verifier(self):
        wf = (ROOT / ".github/workflows/r01-dev-integrity.yml").read_text()
        gate = (ROOT / "tools/run_r01_offline_quality_gate.sh").read_text()
        php = (ROOT / "tools/check_crm_aia_scenario_matrix.php").read_text()
        self.assertIn("php tools/check_crm_aia_scenario_matrix.php", wf)
        self.assertIn("python3 tools/verify_f209_matrix.py", wf)
        self.assertIn("test_f209_op01_source_matrix.py", gate)
        self.assertIn("AiaReadOnlyMockBoundary", php)
        self.assertIn("MockCrmReadRequest", php)
        self.assertIn("op01-pacote-aceite-bilateral.json", php)
        self.assertIn("NAO_EXECUTADO", php)
        for forbidden in ("curl_init(", "PDO(", "mysqli_", "shell_exec(", "api.github.com"):
            self.assertNotIn(forbidden, php)


if __name__ == "__main__":
    unittest.main()
