"""Small offline contract tests. Positive engine execution belongs to P26 CI."""
from __future__ import annotations
import copy
import hashlib
import json
import os
import subprocess
import tempfile
import unittest
from pathlib import Path

from native_contract import (ContractDenied, LEAD_NAMES, LEAD_TEMPLATE_SHA256,
                             LEAD_WORKFLOW_REF, NAMES, TEMPLATE_SHA256, canonical, expected, inputs,
                             materialize, template, verify_export, verify_result)

ROOT = Path(__file__).resolve().parents[3]
SOURCE = ROOT / "infra/n8n-private/workflows/native-run-status-noai.workflow.json"
LEAD_SOURCE = ROOT / "infra/n8n-private/workflows/native-crm-lead-noai.workflow.json"


def lead_fixture():
    event = {"contract": "SYNTHETIC_CRM_LEAD_INGEST_V1", "event_ref": "SYNTHETIC_LEAD_TEST",
             "tenant_ref": "SYNTHETIC_LEAD_TENANT", "company_id": "11111111-1111-4111-8111-111111111111",
             "lead_id": "abcdef12-3456-7123-8abc-0123456789ab", "source_payload_hash": "a" * 64,
             "kind": "crm.lead.ingest", "occurred_at": 1700000000}
    encoded = canonical(event).decode()
    context = {"company_id": event["company_id"], "tenant_ref": event["tenant_ref"],
               "event_ref": event["event_ref"], "worker_ref": "SYNTHETIC_LEAD_WORKER", "attempt": 1,
               "authority_revision": 1, "mapping_revision": 1, "executor_key_id": "SYNTHETIC_LEAD_EXECUTOR",
               "canonical_event": encoded, "payload_hash": hashlib.sha256(encoded.encode()).hexdigest()}
    flow, bound = materialize(template(LEAD_SOURCE), context)
    observation = {"data": {"resultData": {
        "lastNodeExecuted": LEAD_NAMES[2], "runData": {
            name: [{"executionStatus": "success", "data": {"main": [[{"json": data}]]}}]
            for name, data in zip(LEAD_NAMES, ({}, inputs(bound), expected(bound)))
        }}}}
    return event, context, flow, bound, observation


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


class NativeLeadContractTests(unittest.TestCase):
    def setUp(self):
        self.event, self.context, self.flow, self.bound, self.observation = lead_fixture()

    def test_lead_preparation_has_distinct_source_task_and_result(self):
        result = verify_result(self.observation, self.bound)
        self.assertEqual(result["schema"], "AIA_NATIVE_LEAD_INGEST_V1")
        self.assertEqual(result["operation"], "lead.ingest")
        self.assertEqual(result["lead_status"], "PREPARED")
        self.assertIs(result["crm_mutated"], False)
        self.assertIs(result["operational_authorization"], False)
        self.assertEqual(result["ai_api_calls"], 0)
        self.assertEqual(result["lead_id"], self.event["lead_id"])
        self.assertEqual(result["source_payload_hash"], self.event["source_payload_hash"])
        self.assertNotEqual(result["source_payload_hash"], result["payload_hash"])
        self.assertNotIn("run_status", result)
        self.assertNotIn("run_ref", result)
        self.assertEqual(self.flow["id"], "lead" + self.bound["payload_hash"][:20])
        self.assertEqual(self.bound["template_sha256"], LEAD_TEMPLATE_SHA256)
        self.assertEqual(inputs(self.bound)["workflow_ref"], LEAD_WORKFLOW_REF)

    def test_task_is_closed_canonical_and_has_exact_scalar_types(self):
        mutations = [("contract", "SYNTHETIC_AIA_AUTOMATION_V1"), ("kind", "mock.run.completed"),
                     ("operation", "run.status.project"), ("source_payload_hash", "A" * 64),
                     ("source_payload_hash", None), ("lead_id", self.event["lead_id"].upper()),
                     ("lead_id", "not-a-uuid"), ("company_id", "11111111-1111-7111-8111-111111111111"),
                     ("occurred_at", True), ("occurred_at", 1700000000.0), ("occurred_at", -1)]
        for field, value in mutations:
            with self.subTest(field=field, value=value):
                event = {**self.event, field: value}
                raw = canonical(event).decode()
                context = {**self.context, "canonical_event": raw, "payload_hash": hashlib.sha256(raw.encode()).hexdigest()}
                with self.assertRaises(ContractDenied):
                    inputs(context)
        for raw in (canonical(dict(reversed(list(self.event.items())))).decode(),
                    '{"kind":"crm.lead.ingest",' + canonical(self.event).decode()[1:]):
            with self.subTest(raw=raw):
                with self.assertRaises(ContractDenied):
                    inputs({**self.context, "canonical_event": raw, "payload_hash": hashlib.sha256(raw.encode()).hexdigest()})

    def test_template_is_bound_to_task_operation_and_cannot_be_modified(self):
        with self.assertRaises(ContractDenied):
            materialize(template(SOURCE), self.context)
        legacy = NativeContractTests()
        legacy.setUp()
        with self.assertRaises(ContractDenied):
            materialize(template(LEAD_SOURCE), legacy.context)
        changed = template(LEAD_SOURCE)
        changed["nodes"][2]["type"] = "n8n-nodes-base.httpRequest"
        with self.assertRaises(ContractDenied):
            materialize(changed, self.context)
        with tempfile.TemporaryDirectory() as tmp:
            path = Path(tmp) / "lead.json"
            path.write_bytes(LEAD_SOURCE.read_bytes().replace(b'"active": false', b'"active": true'))
            with self.assertRaises(ContractDenied):
                template(path)

    def test_context_binds_company_tenant_source_and_graph(self):
        for field, value in (("company_id", "33333333-3333-4333-8333-333333333333"),
                             ("tenant_ref", "SYNTHETIC_FOREIGN"), ("event_ref", "SYNTHETIC_OTHER"),
                             ("payload_hash", "b" * 64), ("template_sha256", TEMPLATE_SHA256),
                             ("workflow_id", "p26" + self.bound["payload_hash"][:20]),
                             ("workflow_sha256", "c" * 64)):
            with self.subTest(field=field):
                with self.assertRaises(ContractDenied):
                    materialize(template(LEAD_SOURCE), {**self.bound, field: value})
        changed_event = {**self.event, "source_payload_hash": "d" * 64}
        with self.assertRaises(ContractDenied):
            inputs({**self.context, "canonical_event": canonical(changed_event).decode()})

    def test_observation_cannot_change_source_scope_operation_or_claim_crm_mutation(self):
        for field, value in (("schema", "AIA_NATIVE_RUN_STATUS_V1"), ("operation", "run.status.project"),
                             ("company_id", "33333333-3333-4333-8333-333333333333"),
                             ("lead_id", "22222222-2222-4222-8222-222222222222"),
                             ("source_payload_hash", "f" * 64), ("payload_hash", "f" * 64),
                             ("tenant_ref", "SYNTHETIC_FOREIGN"), ("attempt", True),
                             ("template_sha256", TEMPLATE_SHA256), ("lead_status", "CREATED"),
                             ("crm_mutated", True), ("crm_mutated", 0), ("ai_api_calls", False),
                             ("operational_authorization", True), ("unexpected", "EXTRA")):
            with self.subTest(field=field, value=value):
                changed = copy.deepcopy(self.observation)
                changed["data"]["resultData"]["runData"][LEAD_NAMES[2]][0]["data"]["main"][0][0]["json"][field] = value
                with self.assertRaises(ContractDenied):
                    verify_result(changed, self.bound)

    def test_native_graph_export_and_observation_require_the_lead_nodes(self):
        verify_export(self.flow, [self.flow])
        changed = copy.deepcopy(self.flow)
        changed["nodes"][2]["credentials"] = {"httpBasicAuth": {"id": "foreign"}}
        with self.assertRaises(ContractDenied):
            verify_export(self.flow, [changed])
        for case in ("wrong_operation", "failed", "repeated"):
            changed = copy.deepcopy(self.observation)
            result = changed["data"]["resultData"]
            if case == "wrong_operation":
                result["lastNodeExecuted"] = NAMES[2]
            elif case == "failed":
                result["runData"][LEAD_NAMES[1]][0]["executionStatus"] = "error"
            else:
                result["runData"][LEAD_NAMES[2]].append(copy.deepcopy(result["runData"][LEAD_NAMES[2]][0]))
            with self.assertRaises(ContractDenied):
                verify_result(changed, self.bound)


class NativePhpParityTests(unittest.TestCase):
    def setUp(self):
        if not (ROOT / "tools/labs/LabAutomationTaskContract.php").is_file():
            if os.environ.get("GITHUB_ACTIONS") == "true":
                self.fail("The combined domain tree is required for PHP/Python native contract parity")
            self.skipTest("The isolated Central checkout has no combined Agents domain tree")
        self.event, self.context, self.flow, self.bound, self.observation = lead_fixture()

    def php(self, context, observation):
        process = subprocess.run(["php", str(Path(__file__).with_name("test_native_contract_probe.php"))],
                                 input=canonical({"context": context, "observation": observation}),
                                 stdout=subprocess.PIPE, stderr=subprocess.PIPE, check=True)
        return json.loads(process.stdout)

    def test_php_reconstructs_the_same_graph_context_inputs_and_result(self):
        reply = self.php(self.bound, self.observation)
        self.assertIs(reply["context_valid"], True)
        self.assertIs(reply["result_valid"], True)
        self.assertEqual(reply["inputs"], inputs(self.bound))
        self.assertEqual(json.loads(reply["context_json"]), self.bound)
        legacy = NativeContractTests()
        legacy.setUp()
        legacy_bound = {**legacy.bound, "company_id": self.context["company_id"],
                        "authority_revision": 1, "mapping_revision": 1,
                        "executor_key_id": "SYNTHETIC_P26_EXECUTOR"}
        reply = self.php(legacy_bound, legacy.observation)
        self.assertIs(reply["context_valid"], True)
        self.assertIs(reply["result_valid"], True)
        self.assertEqual(reply["inputs"], inputs(legacy_bound))

    def test_php_denies_swapped_context_source_template_and_operation(self):
        for field, value in (("company_id", "33333333-3333-4333-8333-333333333333"),
                             ("tenant_ref", "SYNTHETIC_FOREIGN"), ("template_sha256", TEMPLATE_SHA256),
                             ("workflow_sha256", "c" * 64)):
            with self.subTest(field=field):
                self.assertIs(self.php({**self.bound, field: value}, self.observation)["context_valid"], False)
        changed_event = {**self.event, "source_payload_hash": "e" * 64}
        self.assertIs(self.php({**self.bound, "canonical_event": canonical(changed_event).decode()},
                               self.observation)["context_valid"], False)
        for field, value in (("operation", "run.status.project"), ("source_payload_hash", "d" * 64),
                             ("crm_mutated", True), ("crm_mutated", 0), ("ai_api_calls", False)):
            with self.subTest(field=field, value=value):
                changed = copy.deepcopy(self.observation)
                changed["data"]["resultData"]["runData"][LEAD_NAMES[2]][0]["data"]["main"][0][0]["json"][field] = value
                reply = self.php(self.bound, changed)
                self.assertIs(reply["context_valid"], True)
                self.assertIs(reply["result_valid"], False)


if __name__ == "__main__":
    unittest.main()
