"""Offline response-contract assurance for actual PHP OP-01 facade values."""
from __future__ import annotations

import copy
import json
import sys
import unittest
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
sys.path.insert(0, str(ROOT / "tools"))
import check_op01_public_response_parity as parity


def sample_success():
    return {
        "contract": "SYNTHETIC_CRM_AIA_PUBLIC_V1",
        "state": "READ_SYNTHETIC_ONLY",
        "projection": {"ref": "SYNTHETIC_A_LEAD"},
        "real_authorization": False,
        "external_calls": 0,
    }


def sample_denial():
    result = sample_success()
    result["state"] = "DENIED_OR_NOT_FOUND"
    result["projection"] = None
    return result


class PublicResponseContractTests(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.schema = json.loads(parity.SCHEMA.read_text("utf-8"))
        parity.check_schema(cls.schema)

    def test_live_php_witness_satisfies_contract(self):
        summary = parity.check()
        self.assertEqual(summary["fixtures_checked"], 6)
        self.assertEqual(summary["success_cases"], 2)
        self.assertEqual(summary["denial_cases"], 4)
        self.assertEqual(summary["operational_decision"], "NO_GO")
        self.assertFalse(summary["real_authorization"])
        self.assertEqual(summary["external_calls"], 0)

    def test_real_response_state_is_not_authorization(self):
        self.assertTrue(parity.is_safe_response(sample_success(), ["ref"]))
        self.assertTrue(parity.is_safe_response(sample_denial(), ["ref"]))
        self.assertFalse(parity.is_safe_response(sample_success(), ["stage"]))
        self.assertFalse(parity.is_safe_response(sample_success(), ["ref", "stage"]))

    def test_forbid_privileged_or_leaking_response_variants(self):
        examples = [
            {"real_authorization": True},
            {"external_calls": 1},
            {"external_calls": False},
            {"state": "ALLOW"},
            {"projection": {"ref": "CUSTOMER_REAL_ID"}},
            {"projection": {"ref": "SYNTHETIC_A_LEAD", "email": "hidden"}},
            {"projection": {}},
            {"projection": {"ref": None}},
            {"projection": {"ref": "SYNTHETIC_A_LEAD\n"}},
        ]
        for changed in examples:
            with self.subTest(changed=changed):
                response = sample_success()
                response.update(changed)
                self.assertFalse(parity.is_safe_response(response, ["ref"]))
        response = sample_success()
        response["token"] = "SYNTHETIC_FAKE"
        self.assertFalse(parity.is_safe_response(response, ["ref"]))

    def test_denied_response_must_be_uniform_and_content_free(self):
        denied = sample_denial()
        for projection in ({}, [], "", {"ref": "SYNTHETIC_A_LEAD"}):
            with self.subTest(projection=projection):
                mutated = copy.deepcopy(denied)
                mutated["projection"] = projection
                self.assertFalse(parity.is_safe_response(mutated, ["ref"]))
        self.assertTrue(parity.is_safe_response(denied, ["email"]))

    def test_strict_field_types_deny_injection(self):
        for fields in ([], ["ref", "ref"], [["ref"]], [42], None,
                       ["ref", "email"], ["stage", "ref", "email"]):
            with self.subTest(fields=fields):
                self.assertFalse(parity.is_safe_response(sample_success(), fields))

    def test_schema_broadening_is_always_rejected(self):
        mutations = [
            lambda schema: schema.update(additionalProperties=True),
            lambda schema: schema["properties"]["real_authorization"].update(const=True),
            lambda schema: schema["properties"]["external_calls"].update(const=1),
            lambda schema: schema["$defs"]["minimalProjection"]["properties"].update(
                email={"type": "string"}),
            lambda schema: schema["properties"]["state"]["enum"].append("ALLOW"),
            lambda schema: schema["allOf"].pop(),
            lambda schema: schema["required"].remove("real_authorization"),
        ]
        for mutate in mutations:
            with self.subTest(mutation=mutate):
                altered = copy.deepcopy(self.schema)
                mutate(altered)
                with self.assertRaises(parity.ResponseParityError):
                    parity.check_schema(altered)


if __name__ == "__main__":
    unittest.main()
