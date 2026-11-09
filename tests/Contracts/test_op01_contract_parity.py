"""Negative controls for pinned CRM proposal and synthetic PHP parser; no network."""
import copy
import json
import subprocess
import sys
import tempfile
import unittest
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
SCRIPT = ROOT / "tools/check_op01_contract_parity.py"
PROBE = ROOT / "tools/op01_php_parser_probe.php"
SNAPSHOT = ROOT / "contracts/reviews/crm-aia-ro-01.pr57.snapshot.md"
MANIFEST = ROOT / "contracts/reviews/crm-aia-ro-01.pr57.manifest.json"
SCHEMA = ROOT / "contracts/crm-aia-readonly.v1.synthetic.schema.json"
FIELDS = ["contract", "request_ref", "operation", "tenant_ref", "principal_ref",
          "resource_ref", "purpose_ref", "fields"]


def baseline():
    return {"contract": "SYNTHETIC_CRM_AIA_READ_V1",
            "request_ref": "SYNTHETIC_REQUEST_A", "operation": "crm.lead.read_minimal",
            "tenant_ref": "SYNTHETIC_A", "principal_ref": "SYNTHETIC_PRINCIPAL",
            "resource_ref": "SYNTHETIC_A_LEAD", "purpose_ref": "SYNTHETIC_SUMMARY",
            "fields": ["ref", "stage"]}


class ContractParityTests(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory()
        self.addCleanup(self.temp.cleanup)
        root = Path(self.temp.name)
        self.snapshot, self.manifest, self.schema = (
            root / "snapshot.md", root / "manifest.json", root / "schema.json")
        self.snapshot.write_bytes(SNAPSHOT.read_bytes())
        self.manifest.write_bytes(MANIFEST.read_bytes())
        self.schema.write_bytes(SCHEMA.read_bytes())

    def run_linter(self):
        p = subprocess.run([sys.executable, str(SCRIPT), "--snapshot",
                            str(self.snapshot), "--manifest", str(self.manifest),
                            "--schema", str(self.schema)],
                           capture_output=True, text=True, timeout=10)
        r = json.loads(p.stdout)
        self.assertEqual(r["pilot_decision"], "NO_GO")
        self.assertFalse(r["authorizes_deploy"])
        self.assertFalse(r["authorizes_credentials"])
        self.assertFalse(r["crm_proposal_approved"])
        self.assertFalse(r["crm_api_verified"])
        return p.returncode, r

    def expect_rejection(self, code):
        rc, data = self.run_linter()
        self.assertEqual(rc, 1)
        self.assertEqual(data["error_code"], code)

    def edit_manifest(self, key, value):
        m = json.loads(self.manifest.read_text())
        m[key] = value
        self.manifest.write_text(json.dumps(m))

    def edit_schema(self, change):
        s = json.loads(self.schema.read_text())
        change(s)
        self.schema.write_text(json.dumps(s))

    def test_baseline_is_documentary_no_go(self):
        rc, data = self.run_linter()
        self.assertEqual(rc, 0)
        self.assertTrue(data["contract_parity"])
        self.assertTrue(data["snapshot_consistent"])

    def test_snapshot_tampering(self):
        self.snapshot.write_text(self.snapshot.read_text() + "\nadded ALLOW\n")
        self.expect_rejection("SOURCE_SNAPSHOT_DRIFT")

    def test_false_authority(self):
        self.edit_manifest("integration_authorized", True)
        self.expect_rejection("FALSE_AUTHORITY_CLAIM")

    def test_false_api_verified(self):
        self.edit_manifest("crm_api_verified", True)
        self.expect_rejection("FALSE_AUTHORITY_CLAIM")

    def test_wrong_crm_pr(self):
        self.edit_manifest("source_pr", 58)
        self.expect_rejection("SOURCE_MANIFEST_INVALID")

    def test_widened_manifest_fields(self):
        self.edit_manifest("required_fields", FIELDS + ["role"])
        self.expect_rejection("CONTRACT_MANIFEST_DRIFT")

    def test_mutation_operation(self):
        self.edit_manifest("operation", "crm.task.create")
        self.expect_rejection("CONTRACT_MANIFEST_DRIFT")

    def test_schema_mutation_operation(self):
        self.edit_schema(lambda s: s["properties"]["operation"].update(const="crm.task.create"))
        self.expect_rejection("SCHEMA_CONTRACT_DRIFT")

    def test_schema_privilege_expansion(self):
        self.edit_schema(lambda s: s["properties"].update(role={"type": "string"}))
        self.expect_rejection("SCHEMA_CONTRACT_DRIFT")

    def test_schema_missing_tenant(self):
        self.edit_schema(lambda s: s["required"].remove("tenant_ref"))
        self.expect_rejection("SCHEMA_CONTRACT_DRIFT")

    def test_schema_allows_extra(self):
        self.edit_schema(lambda s: s.update(additionalProperties=True))
        self.expect_rejection("SCHEMA_CONTRACT_DRIFT")

    def test_schema_personal_field(self):
        self.edit_schema(lambda s: s["properties"]["fields"]["items"]["enum"].append("email"))
        self.expect_rejection("SCHEMA_PROJECTION_DRIFT")

    def test_schema_large_projection(self):
        self.edit_schema(lambda s: s["properties"]["fields"].update(maxItems=99))
        self.expect_rejection("SCHEMA_PROJECTION_DRIFT")

    def test_schema_weak_reference(self):
        self.edit_schema(lambda s: s["$defs"]["syntheticRef"].update(pattern=".*"))
        self.expect_rejection("SCHEMA_CONTRACT_DRIFT")

    def test_php_parser_synthetic_matrix(self):
        valid = baseline()
        cases = [(valid, True)]
        for fields in (["ref"], ["stage"], ["stage", "ref"]):
            c = copy.deepcopy(valid); c["fields"] = fields
            cases.append((c, True))
        for name in FIELDS:
            c = copy.deepcopy(valid); del c[name]
            cases.append((c, False))
        for field, value in [
            ("contract", "SYNTHETIC_CRM_AIA_READ_V2"),
            ("operation", "crm.task.create"),
            ("tenant_ref", "123"),
            ("tenant_ref", "SYNTHETIC_é"),
            ("principal_ref", 7),
            ("request_ref", "SYNTHETIC_"),
            ("resource_ref", "SYNTHETIC_" + "A" * 81),
            ("purpose_ref", None),
            ("fields", []),
            ("fields", ["ref", "ref"]),
            ("fields", ["ref", "stage", "email"]),
            ("fields", ["email"]),
            ("fields", ["ref", 10]),
            ("fields", "ref"),
            ("fields", {"0": "ref"}),
            ("fields", {"ref": "stage"}),
        ]:
            c = copy.deepcopy(valid); c[field] = value
            cases.append((c, False))
        for name in ("authorization", "is_admin", "role"):
            c = copy.deepcopy(valid); c[name] = "ALLOW"
            cases.append((c, False))
        cases.extend([(["not_an_object"], False), ({"contract": "SYNTHETIC_CRM_AIA_READ_V1"}, False)])
        p = subprocess.run(["php", str(PROBE)], input=json.dumps([x for x, _ in cases]),
                           capture_output=True, text=True, timeout=20)
        self.assertEqual(p.returncode, 0, p.stderr)
        obj = json.loads(p.stdout)
        self.assertEqual(obj["contract"], "SYNTHETIC_PARSER_PROBE_V1")
        self.assertEqual(obj["accepted"], [expected for _, expected in cases])
        self.assertGreaterEqual(len(cases), 30)

    def test_php_probe_invalid_protocol(self):
        for invalid in ("{}", "null", "[]", "not-json"):
            with self.subTest(invalid=invalid):
                p = subprocess.run(["php", str(PROBE)], input=invalid,
                                   capture_output=True, text=True, timeout=10)
                self.assertEqual(p.returncode, 2)
                self.assertNotIn("SYNTHETIC_A_LEAD", p.stdout + p.stderr)


if __name__ == "__main__":
    unittest.main()
