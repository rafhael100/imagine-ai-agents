"""Synthetic-only schema inspection; optional jsonschema 4.x already installed locally.

No network, no credentials, no CRM API, no file writes.
Usage: python tests/Contracts/test_crm_aia_readonly_schema.py
"""
from pathlib import Path
import json
import sys

try:
    from jsonschema import Draft202012Validator
except ImportError:
    print("BLOCKED: jsonschema unavailable (no installation attempted)", file=sys.stderr)
    raise SystemExit(2)

root = Path(__file__).resolve().parents[2]
schema = json.loads((root / "contracts/crm-aia-readonly.v1.synthetic.schema.json").read_text("utf-8"))
Draft202012Validator.check_schema(schema)
validator = Draft202012Validator(schema)
valid = {
    "contract": "SYNTHETIC_CRM_AIA_READ_V1",
    "request_ref": "SYNTHETIC_REQUEST_A",
    "operation": "crm.lead.read_minimal",
    "tenant_ref": "SYNTHETIC_A",
    "principal_ref": "SYNTHETIC_PRINCIPAL",
    "resource_ref": "SYNTHETIC_A_LEAD",
    "purpose_ref": "SYNTHETIC_SUMMARY",
    "fields": ["ref", "stage"],
}
validator.validate(valid)
variants = []
for key in valid:
    v = dict(valid)
    del v[key]
    variants.append((f"missing_{key}", v))
for key, value in [
    ("contract", "SYNTHETIC_CRM_AIA_READ_V2"),
    ("operation", "crm.task.create"),
    ("tenant_ref", "123"),
    ("principal_ref", "SYNTHETIC_é"),
    ("fields", []),
    ("fields", ["ref", "ref"]),
    ("fields", ["private"]),
    ("fields", ["ref", "stage", "notes"]),
    ("fields", "ref"),
]:
    v = dict(valid)
    v[key] = value
    variants.append((f"invalid_{key}_{len(variants)}", v))
extra = dict(valid)
extra["is_admin"] = True
variants.append(("unknown_privilege_key", extra))
for label, instance in variants:
    if validator.is_valid(instance):
        raise AssertionError(f"Unexpectedly accepted: {label}")
validator.validate(dict(reversed(list(valid.items()))))
print(f"F112_SCHEMA_PASS valid=2 rejected={len(variants)} draft=2020-12 synthetic_only=true")
