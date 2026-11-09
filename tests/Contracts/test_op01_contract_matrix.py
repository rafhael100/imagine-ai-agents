"""Deterministic synthetic-only JSON schema subset vs F1-12 PHP parser matrix.

No network, no CRM, no credentials, no third-party libraries. The oracle
interprets ONLY the closed schema subset already pinned by EVID-07; it is NOT
a general JSON Schema implementation or a real CRM authorization verifier.
"""
from __future__ import annotations
import copy
import json
import re
import subprocess
import sys
import unittest
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
sys.path.insert(0, str(ROOT / "tools"))
import check_op01_contract_parity as pinned

SCHEMA = ROOT / "contracts/crm-aia-readonly.v1.synthetic.schema.json"
PROBE = ROOT / "tools/op01_php_parser_probe.php"
REFS = ("request_ref", "tenant_ref", "principal_ref", "resource_ref", "purpose_ref")
MAX_BATCH = 64


def baseline(schema):
    return {
        "contract": schema["properties"]["contract"]["const"],
        "request_ref": "SYNTHETIC_REQUEST_A",
        "operation": schema["properties"]["operation"]["const"],
        "tenant_ref": "SYNTHETIC_A",
        "principal_ref": "SYNTHETIC_PRINCIPAL",
        "resource_ref": "SYNTHETIC_A_LEAD",
        "purpose_ref": "SYNTHETIC_SUMMARY",
        "fields": ["ref", "stage"],
    }


def restricted_oracle(value, schema):
    """Interpret exactly this pinned closed subset (not all JSON Schema)."""
    if type(value) is not dict or set(value) != set(schema["required"]):
        return False
    props = schema["properties"]
    if value["contract"] != props["contract"]["const"]:
        return False
    if value["operation"] != props["operation"]["const"]:
        return False
    rule = schema["$defs"]["syntheticRef"]
    pattern = re.compile(rule["pattern"])
    for name in REFS:
        field = value[name]
        if type(field) is not str or pattern.fullmatch(field) is None:
            return False
    values = value["fields"]
    projection = props["fields"]
    if type(values) is not list or not (
        projection["minItems"] <= len(values) <= projection["maxItems"]
    ):
        return False
    if not all(type(item) is str and item in projection["items"]["enum"] for item in values):
        return False
    if projection["uniqueItems"] and len(set(values)) != len(values):
        return False
    return True


def fixture_matrix(schema):
    first = baseline(schema)
    cases = [("baseline", first)]
    for fields in (["ref"], ["stage"], ["ref", "stage"], ["stage", "ref"]):
        case = copy.deepcopy(first)
        case["fields"] = fields
        cases.append(("projection-" + "-".join(fields), case))
    refs = (
        "SYNTHETIC_A", "SYNTHETIC_1", "SYNTHETIC_A_B",
        "SYNTHETIC_" + "A" * 80,
        "SYNTHETIC_", "SYNTHETIC_" + "A" * 81,
        "SYNTHETIC_lower", "SYNTHETIC_Á", "SYNTHETIC_é",
        "SYNTHETIC_A B", "SYNTHETIC_A-B", "SYNTHETIC_A.B",
        "SYNTHETIC_A\n", "SYNTHETIC_A\0", "12345",
    )
    for field in REFS:
        for i, value in enumerate(refs):
            case = copy.deepcopy(first)
            case[field] = value
            cases.append((f"{field}-{i}", case))
    for field in schema["required"]:
        case = copy.deepcopy(first)
        del case[field]
        cases.append(("missing-" + field, case))
        for i, wrong in enumerate((None, False, 42, ["ref"], {"ref": True}, 1.5)):
            case = copy.deepcopy(first)
            case[field] = wrong
            cases.append((f"type-{field}-{i}", case))
    for field in ("is_admin", "role", "authorization", "scope", "token", "password", "notes"):
        case = copy.deepcopy(first)
        case[field] = "ALLOW"
        cases.append(("extra-" + field, case))
    for i, values in enumerate((
        [], ["ref", "ref"], ["stage", "stage"], ["email"], ["ref", "email"],
        ["ref", "stage", "email"], "ref", {"0": "ref"}, {"ref": "stage"},
        [5], ["ref", 2], ["REF"], ["ref", None],
    )):
        case = copy.deepcopy(first)
        case["fields"] = values
        cases.append((f"invalid-projection-{i}", case))
    for i, value in enumerate((
        "SYNTHETIC_CRM_AIA_READ_V2", "crm.task.create", "mock.task.create",
        "", "ALLOW",
    )):
        case = copy.deepcopy(first)
        case["contract" if i in (0, 3, 4) else "operation"] = value
        cases.append((f"contract-op-{i}", case))
    cases.extend((
        ("root-array", [first]),
        ("root-string", "SYNTHETIC_CRM"),
        ("root-null", None),
        ("root-number", 42),
        ("root-boolean", True),
    ))
    return cases


class ContractMatrix(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        # A change to the schema cannot silently redefine this restricted oracle.
        pinned.check(pinned.SNAPSHOT, pinned.MANIFEST, pinned.SCHEMA)
        cls.schema = json.loads(SCHEMA.read_text(encoding="utf-8"))

    def test_cross_language_matrix(self):
        cases = fixture_matrix(self.schema)
        self.assertGreaterEqual(len(cases), 120)
        expected = [restricted_oracle(item, self.schema) for _, item in cases]
        self.assertGreaterEqual(sum(expected), 15)
        self.assertGreaterEqual(len(expected) - sum(expected), 100)
        accepted = []
        for start in range(0, len(cases), MAX_BATCH):
            chunk = cases[start:start + MAX_BATCH]
            process = subprocess.run(
                ["php", str(PROBE)],
                input=json.dumps([item for _, item in chunk], ensure_ascii=True),
                text=True, capture_output=True, timeout=15, check=False,
            )
            self.assertEqual(process.returncode, 0, process.stderr)
            answer = json.loads(process.stdout)
            self.assertEqual(answer["contract"], "SYNTHETIC_PARSER_PROBE_V1")
            self.assertEqual(set(answer), {"contract", "accepted"})
            self.assertEqual(len(answer["accepted"]), len(chunk))
            accepted.extend(answer["accepted"])
            # None of the fixture identifiers should be present in the result.
            self.assertNotIn("SYNTHETIC_A_LEAD", process.stdout)
        differences = [
            label for (label, _), actual, declared in zip(cases, accepted, expected)
            if actual is not declared
        ]
        self.assertEqual(differences, [])
        self.assertEqual(len(accepted), len(expected))

    def test_probe_does_not_accept_oversized_fixture_batch(self):
        first = baseline(self.schema)
        process = subprocess.run(
            ["php", str(PROBE)],
            input=json.dumps([first for _ in range(81)]),
            text=True, capture_output=True, timeout=15, check=False,
        )
        self.assertEqual(process.returncode, 2)
        self.assertEqual(process.stdout, "")
        self.assertNotIn("SYNTHETIC_A_LEAD", process.stderr)

    def test_oracle_rejects_type_confusion_and_unknown_keys(self):
        first = baseline(self.schema)
        for mutate in (
            {"tenant_ref": True},
            {"principal_ref": 0},
            {"fields": {"0": "ref"}},
            {"fields": ["ref", "ref"]},
            {"is_admin": True},
        ):
            with self.subTest(case=mutate):
                case = copy.deepcopy(first)
                case.update(mutate)
                self.assertFalse(restricted_oracle(case, self.schema))


if __name__ == "__main__":
    unittest.main()
