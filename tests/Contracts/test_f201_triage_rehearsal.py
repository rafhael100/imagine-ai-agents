"""F201 closed six-stage triage snapshots, offline and without CRM access."""
import copy
import pathlib
import shutil
import subprocess
import sys
import unittest

ROOT = pathlib.Path(__file__).resolve().parents[2]
sys.path.insert(0, str(ROOT / "tools"))
import verify_f201_triage as verifier


def fixture():
    stages = {}
    for name, (state, action, admission, queue, review) in verifier.STATES.items():
        stages[name] = {
            "contract": "SYNTHETIC_QUEUE_TRIAGE_V1",
            "state": state,
            "next_step": action,
            "queue": dict(zip(verifier.QUEUE, queue)),
            "review": dict(zip(verifier.REVIEW, review)),
            "admission": {"state": admission},
            "operational_decision": "NO_GO",
            "real_authorization": False,
            "external_calls": 0,
            "write_calls": 0,
        }
    return {
        "contract": "SYNTHETIC_QUEUE_TRIAGE_REHEARSAL_V1",
        "stages": stages,
        "operational_decision": "NO_GO",
        "real_authorization": False,
        "external_calls": 0,
        "write_calls": 0,
    }


class F201Tests(unittest.TestCase):
    def test_canonical_six_stage_fixture(self):
        self.assertIs(verifier.verify(fixture()), True)

    def test_no_go_envelope_cannot_be_forged(self):
        state = fixture()
        state["operational_decision"] = "GO"
        with self.assertRaises(ValueError):
            verifier.verify(state)

    def test_nested_real_authorization_is_denied(self):
        state = fixture()
        state["stages"]["resumed"]["real_authorization"] = True
        with self.assertRaises(ValueError):
            verifier.verify(state)

    def test_uncertainty_cannot_disappear_after_resume(self):
        state = fixture()
        state["stages"]["resumed"]["queue"]["indeterminate"] = 0
        with self.assertRaises(ValueError):
            verifier.verify(state)

    def test_review_cannot_claim_resolved(self):
        state = fixture()
        state["stages"]["resumed"]["review"]["reviewed_unresolved"] = 0
        with self.assertRaises(ValueError):
            verifier.verify(state)

    def test_extra_run_reference_is_denied(self):
        state = fixture()
        state["stages"]["paused"]["run_ref"] = "SYNTHETIC_INTERNAL"
        with self.assertRaises(ValueError):
            verifier.verify(state)

    def test_boolean_counter_cannot_be_coerced_to_int(self):
        state = fixture()
        state["stages"]["empty"]["queue"]["pending"] = False
        with self.assertRaises(ValueError):
            verifier.verify(state)

    def test_invalid_admission_state_is_denied(self):
        state = fixture()
        state["stages"]["paused"]["admission"]["state"] = "OPEN"
        with self.assertRaises(ValueError):
            verifier.verify(state)

    def test_renamed_stage_is_denied(self):
        state = fixture()
        state["stages"]["unknown"] = state["stages"].pop("resumed")
        with self.assertRaises(ValueError):
            verifier.verify(state)

    def test_external_call_must_be_zero_int(self):
        state = fixture()
        state["stages"]["uncertain"]["external_calls"] = 1
        with self.assertRaises(ValueError):
            verifier.verify(state)

    def test_extra_queue_counter_is_denied(self):
        state = fixture()
        state["stages"]["leased"]["queue"]["secret"] = 23
        with self.assertRaises(ValueError):
            verifier.verify(state)

    @unittest.skipUnless(shutil.which("php"), "PHP missing")
    def test_php_cli_matches_closed_contract_and_determinism(self):
        first = verifier.run_fixture()
        second = verifier.run_fixture()
        self.assertEqual(first, second)
        self.assertEqual(first, fixture())
        self.assertIs(verifier.verify(first), True)
        process = subprocess.run([sys.executable, str(ROOT / "tools/verify_f201_triage.py")],
                                 cwd=ROOT, capture_output=True, text=True, timeout=35)
        self.assertEqual(process.returncode, 0, process.stderr)
        self.assertIn("operational_decision=NO_GO", process.stdout)


if __name__ == "__main__":
    unittest.main()
