"""F191 contract regressions. No CRM, credentials, network or real data."""
import copy
import importlib.util
import pathlib
import shutil
import subprocess
import sys
import unittest

ROOT = pathlib.Path(__file__).resolve().parents[2]
SPEC = importlib.util.spec_from_file_location(
    "f191_verifier", ROOT / "tools" / "verify_f190_rehearsal.py"
)
module = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(module)


def fixture():
    def stage(state, pending, completed):
        return {
            "contract": "SYNTHETIC_QUEUE_SUPERVISION_V1",
            "admission": {"state": state},
            "queue": {"pending": pending, "reserved": 0, "completed": completed,
                      "failed": 0, "cancelled": 0, "indeterminate": 1},
            "review": {"awaiting_first": 0, "awaiting_second": 0,
                       "reviewed_unresolved": 1, "conflict": 0},
            "operational_decision": "NO_GO", "real_authorization": False,
            "external_calls": 0, "write_calls": 0,
        }
    return {
        "contract": "SYNTHETIC_QUEUE_PAUSE_REHEARSAL_V1",
        "paused": stage("PAUSED", 1, 0),
        "resumed": stage("OPEN", 0, 1),
        "operational_decision": "NO_GO",
        "real_authorization": False,
        "external_calls": 0,
        "write_calls": 0,
    }


class F191Tests(unittest.TestCase):
    def test_valid_synthetic_fixture(self):
        self.assertTrue(module.verify(fixture()))

    def test_open_during_pause_is_denied(self):
        obj = fixture()
        obj["paused"]["admission"]["state"] = "OPEN"
        with self.assertRaises(ValueError):
            module.verify(obj)

    def test_uncertainty_cannot_disappear(self):
        obj = fixture()
        obj["resumed"]["queue"]["indeterminate"] = 0
        with self.assertRaises(ValueError):
            module.verify(obj)

    def test_review_cannot_resolve_uncertainty(self):
        obj = fixture()
        obj["resumed"]["review"]["reviewed_unresolved"] = 0
        with self.assertRaises(ValueError):
            module.verify(obj)

    def test_operational_go_denied(self):
        obj = fixture()
        obj["operational_decision"] = "GO"
        with self.assertRaises(ValueError):
            module.verify(obj)

    def test_extra_fields_denied(self):
        obj = fixture()
        obj["customer_id"] = "private"
        with self.assertRaises(ValueError):
            module.verify(obj)

    def test_hidden_identifiers_denied(self):
        obj = fixture()
        obj["paused"]["audit"] = {"claim_ref": "secret"}
        with self.assertRaises(ValueError):
            module.verify(obj)

    def test_boolean_as_integer_denied(self):
        obj = fixture()
        obj["external_calls"] = False
        with self.assertRaises(ValueError):
            module.verify(obj)

    @unittest.skipUnless(shutil.which("php"), "PHP missing")
    def test_real_offline_rehearsal(self):
        run = subprocess.run(
            [sys.executable, str(ROOT / "tools" / "verify_f190_rehearsal.py")],
            cwd=ROOT, capture_output=True, text=True, timeout=40,
        )
        self.assertEqual(run.returncode, 0, run.stderr)
        self.assertIn("operational_decision=NO_GO", run.stdout)


if __name__ == "__main__":
    unittest.main()
