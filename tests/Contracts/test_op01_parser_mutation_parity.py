"""SEC-42: deterministic, offline adversarial matrix against the PHP OP-01 parser.

This is a *consumer-side* syntax oracle for the pinned fictional contract.
No CRM API, auth acceptance, HTTP, network, user records, or schema validator
dependencies. It never changes bilateral case status NAO_EXECUTADO / NO-GO.
"""
from __future__ import annotations

import copy
import json
import re
import subprocess
import unittest
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
SCHEMA = ROOT / "contracts/crm-aia-readonly.v1.synthetic.schema.json"
PROBE = ROOT / "tools/op01_php_parser_probe.php"
FIELDS = (
    "contract", "request_ref", "operation", "tenant_ref",
    "principal_ref", "resource_ref", "purpose_ref", "fields",
)
REFERENCES = (
    "request_ref", "tenant_ref", "principal_ref", "resource_ref", "purpose_ref",
)
REF = re.compile(r"SYNTHETIC_[A-Z0-9_]{1,80}")


def baseline() -> dict:
    return {
        "contract": "SYNTHETIC_CRM_AIA_READ_V1",
        "request_ref": "SYNTHETIC_REQUEST_A",
        "operation": "crm.lead.read_minimal",
        "tenant_ref": "SYNTHETIC_A",
        "principal_ref": "SYNTHETIC_PRINCIPAL",
        "resource_ref": "SYNTHETIC_A_LEAD",
        "purpose_ref": "SYNTHETIC_SUMMARY",
        "fields": ["ref", "stage"],
    }


def expected_consumer_acceptance(payload: object) -> bool:
    """Independent, deliberately strict *PHP consumer* value oracle."""
    if not isinstance(payload, dict) or set(payload) != set(FIELDS):
        return False
    if payload["contract"] != "SYNTHETIC_CRM_AIA_READ_V1":
        return False
    if payload["operation"] != "crm.lead.read_minimal":
        return False
    if not all(isinstance(payload[k], str) and REF.fullmatch(payload[k])
               for k in REFERENCES):
        return False
    fields = payload["fields"]
    return (isinstance(fields, list) and 1 <= len(fields) <= 2
            and all(isinstance(v, str) and v in ("ref", "stage") for v in fields)
            and len(set(fields)) == len(fields))


def matrix() -> list[object]:
    """Fixed Cartesian-style boundary families, no random seed/clock/network."""
    result: list[object] = []
    good = baseline()
    for fields in (["ref"], ["stage"], ["ref", "stage"], ["stage", "ref"]):
        body = copy.deepcopy(good)
        body["fields"] = fields
        result.append(body)
        result.append(dict(reversed(list(body.items()))))
    for name in FIELDS:
        body = copy.deepcopy(good)
        del body[name]
        result.append(body)
    for extra in (
        "authorization", "role", "token", "is_admin", "scope", "granted",
        "allow", "email", "owner_ref", "tenant_override", "metadata",
        "signature", "service_token", "__proto__", "constructor",
        "real_authorization", "external_calls",
    ):
        body = copy.deepcopy(good)
        body[extra] = "SYNTHETIC_FORGED"
        result.append(body)
    references: list[object] = [
        "", "SYNTHETIC_", "SYNTHETIC_A", "SYNTHETIC_" + "A" * 80,
        "SYNTHETIC_" + "A" * 81, "SYNTHETIC_" + "A" * 512,
        "SYNTHETIC_a", "SYNTHETIC_é", "SYNTHETIC_☃",
        "SYNTHETIC_A\n", "SYNTHETIC_A\r", "SYNTHETIC_A\x00",
        "SYNTHETIC_A\t", "SYNTHETIC_A ", "SYNTHETIC_A/B",
        "SYNTHETIC_A.B", "SYNTHETIC_A-B", "SYNTHETIC_A@B",
        "SYNTHETIC_A$B", "SYNTHETIC_A+B", "SYNTHETIC_A:B",
        "SYNTHETIC_A\u200b", "SYNTHETIC_" + "_" * 80,
        "SYNTHETIC_" + "_" * 81, "SYNTHETIC_" + "9" * 80,
        "UNTRUSTED_A", 17, -1, True, False, None, [], {},
    ]
    for key in REFERENCES:
        for value in references:
            body = copy.deepcopy(good)
            body[key] = value
            result.append(body)
    for value in [
        [], ["ref"], ["stage"], ["stage", "ref"], ["ref", "stage"],
        ["ref", "ref"], ["stage", "stage"], ["email"], ["notes"],
        ["ref", "email"], ["stage", "email"], ["ref", "stage", "email"],
        ["ref", "stage", "ref"], ["ref", "stage", "stage"],
        ["ref", 42], ["ref", None], [0], [True],
        "ref", {}, {"0": "ref"}, {"0": "ref", "1": "stage"},
        None, False, 17, [[], "ref"], [["ref"]], ["stage", "REF"],
    ]:
        body = copy.deepcopy(good)
        body["fields"] = value
        result.append(body)
    for key, values in {
        "contract": ["SYNTHETIC_CRM_AIA_READ_V2", "", None, 17,
                     "SYNTHETIC_CRM_AIA_READ_V1\n", "crm.task.create"],
        "operation": ["crm.task.create", "crm.lead.write", "", None,
                      "crm.lead.read_minimal\n", True],
    }.items():
        for value in values:
            body = copy.deepcopy(good)
            body[key] = value
            result.append(body)
    for other in (None, True, 1, "", [], ["not_an_object"],
                  {"unexpected": "SYNTHETIC_A"}):
        result.append(other)
    return result


def probe(candidates: list[object]) -> list[bool]:
    """Call the existing zero-network PHP probe with its bounded 80-case API."""
    observed: list[bool] = []
    for offset in range(0, len(candidates), 32):
        subset = candidates[offset:offset + 32]
        wire = json.dumps(subset, ensure_ascii=True, separators=(",", ":"))
        if len(wire.encode("utf-8")) >= 131072:
            raise AssertionError("UNBOUNDED_TEST_BATCH")
        process = subprocess.run(
            ["php", str(PROBE)], input=wire, text=True,
            capture_output=True, check=False, timeout=10,
        )
        if process.returncode != 0:
            raise AssertionError("PHP_PROBE_FAILED:" + process.stderr[:160])
        data = json.loads(process.stdout)
        if (set(data) != {"contract", "accepted"}
            or data["contract"] != "SYNTHETIC_PARSER_PROBE_V1"
            or len(data["accepted"]) != len(subset)
            or not all(type(value) is bool for value in data["accepted"])
        ):
            raise AssertionError("UNSAFE_PROBE_RESPONSE")
        observed.extend(data["accepted"])
    return observed


class Op01DeterministicMutationParity(unittest.TestCase):
    def test_oracle_is_scoped_to_pinned_synthetic_schema_not_crm(self):
        schema = json.loads(SCHEMA.read_text("utf-8"))
        self.assertEqual(schema["$id"], "urn:imagine:synthetic:crm-aia:read-only:v1")
        self.assertFalse(schema["additionalProperties"])
        self.assertEqual(set(schema["required"]), set(FIELDS))
        self.assertEqual(set(schema["properties"]), set(FIELDS))
        self.assertEqual(schema["$defs"]["syntheticRef"]["pattern"],
                         "^SYNTHETIC_[A-Z0-9_]{1,80}$")
        self.assertEqual(schema["properties"]["operation"]["const"],
                         "crm.lead.read_minimal")
        self.assertEqual(schema["properties"]["fields"]["items"]["enum"],
                         ["ref", "stage"])

    def test_every_deterministic_mutation_matches_strict_php_consumer(self):
        cases = matrix()
        self.assertGreaterEqual(len(cases), 200)
        expected = [expected_consumer_acceptance(case) for case in cases]
        self.assertGreaterEqual(sum(expected), 12)
        self.assertGreaterEqual(len(expected) - sum(expected), 180)
        actual = probe(cases)
        self.assertEqual(len(actual), len(expected))
        for offset, (observed, required) in enumerate(zip(actual, expected)):
            with self.subTest(case=offset):
                self.assertEqual(observed, required)

    def test_mutations_are_deterministic_and_do_not_require_a_service(self):
        first, second = matrix(), matrix()
        self.assertEqual(first, second)
        self.assertFalse(expected_consumer_acceptance(
            {**baseline(), "authorization": "SYNTHETIC_FORGED"}))
        self.assertTrue(expected_consumer_acceptance(
            dict(reversed(list(baseline().items())))))
        self.assertEqual(probe([baseline()]), [True])

    def test_probe_rejects_oversized_matrix_and_batch_without_payload_echo(self):
        for payload in [
            json.dumps([baseline()] * 81),
            json.dumps([{"huge": "SYNTHETIC_" + "X" * 131072}]),
            "{}",
        ]:
            with self.subTest(size=len(payload)):
                process = subprocess.run(
                    ["php", str(PROBE)], input=payload, text=True,
                    capture_output=True, check=False, timeout=10,
                )
                self.assertEqual(process.returncode, 2)
                self.assertIn("PROBE_INPUT_DENIED", process.stderr)
                self.assertNotIn("SYNTHETIC_A_LEAD", process.stderr + process.stdout)


if __name__ == "__main__":
    unittest.main()
