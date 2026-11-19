"""Offline regression tests: negative cases must never emit operational approval."""
import importlib.util
import json
import subprocess
import sys
import tempfile
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


    def test_duplicate_json_keys_fail_closed(self):
        with tempfile.TemporaryDirectory() as tmp:
            path = Path(tmp) / "evidence.json"
            path.write_text('{"schema":"one","schema":"two"}', encoding="utf-8")
            with self.assertRaisesRegex(ValueError, "DUPLICATE_JSON_KEY"):
                module.load_evidence(path)

    def test_nested_duplicate_json_keys_fail_closed(self):
        with tempfile.TemporaryDirectory() as tmp:
            path = Path(tmp) / "evidence.json"
            path.write_text('{"gates":{"HOM-01":{"state":"PENDING","state":"REVIEWED"}}}', encoding="utf-8")
            with self.assertRaisesRegex(ValueError, "DUPLICATE_JSON_KEY"):
                module.load_evidence(path)

    def test_large_input_refused_before_parse(self):
        with tempfile.TemporaryDirectory() as tmp:
            path = Path(tmp) / "large.json"
            path.write_bytes(b" " * (module.MAX_BYTES + 1))
            with self.assertRaisesRegex(ValueError, "EVIDENCE_TOO_LARGE"):
                module.load_evidence(path)

    def test_symlink_refused(self):
        with tempfile.TemporaryDirectory() as tmp:
            base = Path(tmp) / "evidence.json"
            base.write_text(json.dumps(fixture()), encoding="utf-8")
            link = Path(tmp) / "link.json"
            link.symlink_to(base)
            with self.assertRaisesRegex(ValueError, "NOT_REGULAR_FILE"):
                module.load_evidence(link)

    def test_nonfinite_refused(self):
        with tempfile.TemporaryDirectory() as tmp:
            path = Path(tmp) / "nan.json"
            path.write_text('{"x":NaN}', encoding="utf-8")
            with self.assertRaisesRegex(ValueError, "NON_FINITE"):
                module.load_evidence(path)

    def test_cli_complete_still_not_go(self):
        with tempfile.TemporaryDirectory() as tmp:
            path = Path(tmp) / "evidence.json"
            path.write_text(json.dumps(fixture()), encoding="utf-8")
            p = subprocess.run([sys.executable, str(MODULE), str(path),
                                "--expected-candidate-sha", SHA],
                               capture_output=True, text=True, timeout=5, check=False)
            self.assertEqual(p.returncode, 0, p.stderr)
            response = json.loads(p.stdout)
            self.assertEqual(response["status"], "REVIEW_REQUIRED")
            self.assertIs(response["operational_authorization"], False)

    def test_cli_duplicate_key_returns_failure(self):
        with tempfile.TemporaryDirectory() as tmp:
            path = Path(tmp) / "evidence.json"
            path.write_text('{"schema":"one","schema":"two"}', encoding="utf-8")
            p = subprocess.run([sys.executable, str(MODULE), str(path),
                                "--expected-candidate-sha", SHA],
                               capture_output=True, text=True, timeout=5, check=False)
            self.assertEqual(p.returncode, 2)
            self.assertIs(json.loads(p.stdout)["operational_authorization"], False)


if __name__ == "__main__":
    unittest.main()
