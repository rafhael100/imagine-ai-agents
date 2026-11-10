#!/usr/bin/env python3
"""Static, offline parity of a pinned CRM draft and synthetic Agents JSON schema."""
from __future__ import annotations
import argparse
import hashlib
import json
import re
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
SNAPSHOT = ROOT / "contracts/reviews/crm-aia-ro-01.pr57.snapshot.md"
MANIFEST = ROOT / "contracts/reviews/crm-aia-ro-01.pr57.manifest.json"
SCHEMA = ROOT / "contracts/crm-aia-readonly.v1.synthetic.schema.json"
FIELDS = ("contract", "request_ref", "operation", "tenant_ref", "principal_ref",
          "resource_ref", "purpose_ref", "fields")
REFS = ("request_ref", "tenant_ref", "principal_ref", "resource_ref", "purpose_ref")
PINNED_CRM_PR_HEAD = "97bc1e8d6ce4cf4b52378472c31cb49de6a66d51"
PINNED_CRM_PROPOSAL_BLOB = "cefb390ec41a76d486b137279d92627f097d1d47"
ROOT_SCHEMA_KEYS = {
    "$schema", "$id", "title", "description", "type",
    "additionalProperties", "required", "properties", "$defs",
}
SYNTHETIC_TITLE = "SYNTHETIC ONLY — not a production CRM API contract"
SYNTHETIC_DESCRIPTION = (
    "A fixture description for an offline test. "
    "No authentication or CRM authorization is performed by this schema."
)

class ParityError(Exception):
    pass

def check(snapshot: Path, manifest_path: Path, schema_path: Path) -> None:
    try:
        raw = snapshot.read_bytes()
        content = raw.decode("utf-8")
        manifest = json.loads(manifest_path.read_text(encoding="utf-8"))
        schema = json.loads(schema_path.read_text(encoding="utf-8"))
    except (OSError, UnicodeError, ValueError) as error:
        raise ParityError("ARTIFACT_UNREADABLE") from error

    if not isinstance(manifest, dict) or not isinstance(schema, dict):
        raise ParityError("ARTIFACT_SCHEMA_INVALID")
    required = {"source_repo", "source_pr", "source_pr_head", "source_path",
                "source_blob_sha", "proposal_status", "operation", "contract",
                "required_fields", "allowed_projection", "integration_authorized",
                "crm_api_verified", "real_authorization"}
    if (set(manifest) != required
        or manifest["source_repo"] != "rafhael100/imagine-crm"
        or type(manifest["source_pr"]) is not int or manifest["source_pr"] != 57
        or manifest["proposal_status"] != "DRAFT_NOT_APPROVED"
        or not isinstance(manifest["source_pr_head"], str)
        or re.fullmatch(r"[0-9a-f]{40}", manifest["source_pr_head"]) is None
        or manifest["source_pr_head"] != PINNED_CRM_PR_HEAD
        or manifest["source_blob_sha"] != PINNED_CRM_PROPOSAL_BLOB
        or manifest["source_path"] !=
        "docs/desenvolvimento/CRM-AIA-RO-01-proposta-contrato-bilateral-offline-2026-11-09.md"):
        raise ParityError("SOURCE_MANIFEST_INVALID")
    if any(manifest[name] is not False for name in
           ("integration_authorized", "crm_api_verified", "real_authorization")):
        raise ParityError("FALSE_AUTHORITY_CLAIM")
    digest = hashlib.sha1(b"blob " + str(len(raw)).encode("ascii") + b"\0" + raw).hexdigest()
    if digest != manifest["source_blob_sha"]:
        raise ParityError("SOURCE_SNAPSHOT_DRIFT")
    if (manifest["contract"] != "SYNTHETIC_CRM_AIA_READ_V1"
        or manifest["operation"] != "crm.lead.read_minimal"
        or manifest["required_fields"] != list(FIELDS)
        or manifest["allowed_projection"] != ["ref", "stage"]):
        raise ParityError("CONTRACT_MANIFEST_DRIFT")

    proposal_fields = re.findall(r"^\|\s*\x60([^\x60]+)\x60\s*\|", content, flags=re.MULTILINE)
    if proposal_fields != list(FIELDS):
        raise ParityError("CRM_PROPOSAL_FIELDS_DRIFT")
    markers = ["SYNTHETIC_CRM_AIA_READ_V1", "crm.lead.read_minimal",
               "não homologada", "DRAFT / NO MERGE / NO DEPLOY",
               "G01", "ref", "stage",
               "OP01-R01", "OP01-R07", "SYNTHETIC_CRM_AIA_PUBLIC_V1",
               "não aprovados", "397 casos PHP"]
    if any(marker not in content for marker in markers):
        raise ParityError("CRM_PROPOSAL_SCOPE_DRIFT")
    props = schema.get("properties", {})
    if (set(schema) != ROOT_SCHEMA_KEYS
        or schema.get("$schema") != "https://json-schema.org/draft/2020-12/schema"
        or schema.get("$id") != "urn:imagine:synthetic:crm-aia:read-only:v1"
        or schema.get("title") != SYNTHETIC_TITLE
        or schema.get("description") != SYNTHETIC_DESCRIPTION
        or schema.get("type") != "object"
        or schema.get("additionalProperties") is not False
        or schema.get("required") != list(FIELDS)
        or not isinstance(props, dict) or set(props) != set(FIELDS)
        or props.get("contract") != {"const": "SYNTHETIC_CRM_AIA_READ_V1"}
        or props.get("operation") != {"const": "crm.lead.read_minimal"}
        or schema.get("$defs") != {
            "syntheticRef": {"type": "string", "pattern": r"^SYNTHETIC_[A-Z0-9_]{1,80}$"}
        }):
        raise ParityError("SCHEMA_CONTRACT_DRIFT")
    if any(props.get(name) != {"$ref": "#/$defs/syntheticRef"} for name in REFS):
        raise ParityError("SCHEMA_REF_DRIFT")
    if props.get("fields") != {"type": "array", "minItems": 1, "maxItems": 2,
                                "uniqueItems": True, "items": {"enum": ["ref", "stage"]}}:
        raise ParityError("SCHEMA_PROJECTION_DRIFT")

def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--snapshot", type=Path, default=SNAPSHOT)
    parser.add_argument("--manifest", type=Path, default=MANIFEST)
    parser.add_argument("--schema", type=Path, default=SCHEMA)
    args = parser.parse_args()
    try:
        check(args.snapshot, args.manifest, args.schema)
        outcome = {"contract_parity": True, "snapshot_consistent": True}
        status = 0
    except ParityError as error:
        outcome = {"contract_parity": False, "snapshot_consistent": False,
                   "error_code": str(error)}
        status = 1
    outcome.update({"report": "AIA_OP01_CROSS_REPO_DRAFT_PARITY_V1",
                    "pilot_decision": "NO_GO", "crm_proposal_approved": False,
                    "crm_api_verified": False, "authorizes_credentials": False,
                    "authorizes_deploy": False, "external_calls": 0})
    print(json.dumps(outcome, sort_keys=True))
    return status

if __name__ == "__main__":
    sys.exit(main())
