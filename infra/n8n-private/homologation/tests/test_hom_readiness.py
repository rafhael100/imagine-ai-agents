"""Offline regression tests: negative cases must never emit operational approval."""
import importlib.util
import unittest
from pathlib import Path

MODULE = Path(__file__).resolve().parents[1] / "hom_readiness.py"
spec = importlib.util.spec_from_file_location("hom_readiness", MODULE)
module = importlib.util.module_from_spec(spec)
spec.loader.exec_module(module)
SHA = "a" * 40


def fixture():
    return {"schema": module.SCHEMA, "candidate_sha": SHA,
            "gates": {g: {"state": "REVIEWED", "evidence": ["github.com/example/123"]}
                      for g in module.GATES}}


class ReadinessTests(unittest.TestCase):
    def test_complete_still_requires_review(self):
        result = module.analyze(fixture(), SHA)
        self.assertEqual(result["status"], "REVIEW_REQUIRED")
        self.assertFalse(result["operational_authorization"])
        self.assertEqual(result["missing_gates"], [])

    def test_missing_gate(self):
        obj = fixture()
        del obj["gates"]["HOM-04"]
        result = module.analyze(obj, SHA)
        self.assertEqual(result["status"], "NO_GO")
        self.assertIn("INVALID_GATE_SET", result["errors"])

    def test_pending_not_approved(self):
        obj = fixture()
        obj["gates"]["HOM-05"]["state"] = "PENDING"
        self.assertIn("HOM-05", module.analyze(obj, SHA)["missing_gates"])

    def test_blocked_not_approved(self):
        obj = fixture()
        obj["gates"]["HOM-01"]["state"] = "BLOCKED"
        self.assertEqual(module.analyze(obj, SHA)["status"], "NO_GO")

    def test_wrong_sha(self):
        self.assertIn("SHA_MISMATCH", module.analyze(fixture(), "b"*40)["errors"])

    def test_missing_evidence(self):
        obj = fixture()
        obj["gates"]["HOM-06"]["evidence"] = []
        self.assertIn("HOM-06", module.analyze(obj, SHA)["missing_gates"])

    def test_extra_fields_are_rejected(self):
        obj = fixture()
        obj["deploy_approved"] = True
        self.assertIn("INVALID_TOP_LEVEL", module.analyze(obj, SHA)["errors"])

    def test_unknown_state(self):
        obj = fixture()
        obj["gates"]["HOM-01"]["state"] = "GO"
        self.assertIn("HOM-01:INVALID_STATE", module.analyze(obj, SHA)["errors"])

    def test_duplicate_evidence(self):
        obj = fixture()
        obj["gates"]["HOM-01"]["evidence"] *= 2
        self.assertIn("HOM-01:INVALID_EVIDENCE", module.analyze(obj, SHA)["errors"])

    def test_evidence_does_not_accept_secrets_or_spaces(self):
        obj = fixture()
        obj["gates"]["HOM-01"]["evidence"] = ["token = PRIVATE KEY"]
        self.assertIn("HOM-01:INVALID_EVIDENCE", module.analyze(obj, SHA)["errors"])

    def test_non_dict(self):
        self.assertEqual(module.analyze([], SHA)["status"], "NO_GO")

    def test_no_gate_set_list(self):
        obj = fixture()
        obj["gates"] = []
        self.assertEqual(module.analyze(obj, SHA)["status"], "NO_GO")


if __name__ == "__main__":
    unittest.main()
