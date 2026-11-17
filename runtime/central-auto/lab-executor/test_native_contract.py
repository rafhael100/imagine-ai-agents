"""Small offline contract tests. Positive engine execution belongs to P26 CI."""
from __future__ import annotations
import copy
import hashlib
import tempfile
import unittest
from pathlib import Path

from native_contract import (ContractDenied, NAMES, canonical, expected, inputs,
                             materialize, template, verify_export, verify_result)

ROOT = Path(__file__).resolve().parents[3]
SOURCE = ROOT / "infra/n8n-private/workflows/native-run-status-noai.workflow.json"


class NativeContractTests(unittest.TestCase):
    def setUp(self):
        event = {"contract": "SYNTHETIC_AIA_AUTOMATION_V1", "event_ref": "SYNTHETIC_P26_TEST",
                 "tenant_ref": "SYNTHETIC_P26_TENANT", "run_ref": "SYNTHETIC_P26_RUN",
                 "kind": "mock.run.completed", "reference_ref": "SYNTHETIC_P26_RESOURCE",
                 "occurred_at": 1700000000}
        encoded = canonical(event).decode()
        self.context = {"tenant_ref": event["tenant_ref"], "event_ref": event["event_ref"],
                        "worker_ref": "SYNTHETIC_P26_WORKER", "attempt": 1,
                        "canonical_event": encoded, "payload_hash": hashlib.sha256(encoded.encode()).hexdigest()}
        self.flow, self.bound = materialize(template(SOURCE), self.context)
        self.observation = {"data": {"resultData": {
            "lastNodeExecuted": NAMES[2], "runData": {
                name: [{"executionStatus": "success", "data": {"main": [[{"json": data}]]}}]
                for name, data in zip(NAMES, ({}, inputs(self.bound), expected(self.bound)))
            }}}}

    def test_template_modification_cannot_be_materialized(self):
        with tempfile.TemporaryDirectory() as tmp:
            path = Path(tmp) / "flow.json"
            path.write_bytes(SOURCE.read_bytes().replace(b'"active": false', b'"active": true'))
            with self.assertRaises(ContractDenied):
                template(path)

    def test_roundtrip_rejects_an_added_credential_or_swapped_graph(self):
        verify_export(self.flow, [self.flow])
        for field, value in (("credentials", {"httpBasicAuth": {"id": "foreign"}}),
                             ("type", "n8n-nodes-base.httpRequest")):
            with self.subTest(field=field):
                changed = copy.deepcopy(self.flow)
                changed["nodes"][2][field] = value
                with self.assertRaises(ContractDenied):
                    verify_export(self.flow, [changed])

    def test_observation_must_match_tenant_event_hash_attempt_and_closed_output(self):
        self.assertEqual(verify_result(self.observation, self.bound), expected(self.bound))
        for field, value in (("tenant_ref", "SYNTHETIC_FOREIGN"), ("event_ref", "SYNTHETIC_OTHER"),
                             ("payload_hash", "f" * 64), ("attempt", 2), ("attempt", True),
                             ("unexpected", "SYNTHETIC_EXTRA")):
            with self.subTest(field=field, value=value):
                changed = copy.deepcopy(self.observation)
                changed["data"]["resultData"]["runData"][NAMES[2]][0]["data"]["main"][0][0]["json"][field] = value
                with self.assertRaises(ContractDenied):
                    verify_result(changed, self.bound)

    def test_failed_or_repeated_native_nodes_cannot_be_success(self):
        for case in ("failure", "repeated"):
            changed = copy.deepcopy(self.observation)
            runs = changed["data"]["resultData"]["runData"]
            if case == "failure":
                runs[NAMES[1]][0]["executionStatus"] = "error"
            else:
                runs[NAMES[2]].append(copy.deepcopy(runs[NAMES[2]][0]))
            with self.assertRaises(ContractDenied):
                verify_result(changed, self.bound)


if __name__ == "__main__":
    unittest.main()
