#!/usr/bin/env python3
"""Closed offline response parity for the synthetic OP-01 PHP facade.

This is NOT a general JSON Schema validator, a credential checker or
authorization for an API. It recognizes a pinned subset and checks actual
PHP fixture outputs, refusing all schema drift.
"""
from __future__ import annotations

import json
import re
import subprocess
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
SCHEMA = ROOT / "contracts/crm-aia-public-response.v1.synthetic.schema.json"
PROBE = ROOT / "tools/op01_php_public_response_probe.php"
KEYS = ("contract", "state", "projection", "real_authorization", "external_calls")
REGEX = r"^SYNTHETIC_[A-Z0-9_]{1,80}$"
SUCCESS = "READ_SYNTHETIC_ONLY"
DENIAL = "DENIED_OR_NOT_FOUND"


class ResponseParityError(Exception):
    pass


def check_schema(schema: object) -> dict:
    if type(schema) is not dict or set(schema) != {
        "$schema", "$id", "title", "description", "type", "additionalProperties",
        "required", "properties", "allOf", "$defs",
    }:
        raise ResponseParityError("SCHEMA_ROOT_DRIFT")
    if (schema["$schema"] != "https://json-schema.org/draft/2020-12/schema"
        or schema["$id"] != "urn:imagine:synthetic:crm-aia:public-response:v1"
        or schema["title"] != "SYNTHETIC ONLY — offline response contract, not a CRM API"
        or schema["description"] != "An illustrative closed response envelope for the PHP offline facade. No service authentication or real data access is authorized."
        or schema["type"] != "object"
        or schema["additionalProperties"] is not False
        or schema["required"] != list(KEYS)):
        raise ResponseParityError("SCHEMA_ROOT_DRIFT")
    expected_properties = {
        "contract": {"const": "SYNTHETIC_CRM_AIA_PUBLIC_V1"},
        "state": {"enum": [SUCCESS, DENIAL]},
        "projection": {"oneOf": [{"type": "null"}, {"$ref": "#/$defs/minimalProjection"}]},
        "real_authorization": {"const": False},
        "external_calls": {"const": 0},
    }
    expected_clauses = [
        {"if": {"properties": {"state": {"const": DENIAL}}, "required": ["state"]},
         "then": {"properties": {"projection": {"type": "null"}}}},
        {"if": {"properties": {"state": {"const": SUCCESS}}, "required": ["state"]},
         "then": {"properties": {"projection": {"$ref": "#/$defs/minimalProjection"}}}},
    ]
    projection = {
        "type": "object", "minProperties": 1, "maxProperties": 2,
        "additionalProperties": False,
        "properties": {
            "ref": {"type": "string", "pattern": REGEX},
            "stage": {"type": "string", "pattern": REGEX},
        },
    }
    if (schema["properties"] != expected_properties
        or schema["allOf"] != expected_clauses
        or schema["$defs"] != {"minimalProjection": projection}):
        raise ResponseParityError("SCHEMA_POLICY_DRIFT")
    return schema


def is_safe_response(response: object, fields: object) -> bool:
    """Interpret exactly this locked subset; response alone grants nothing."""
    if (type(response) is not dict or list(response) != list(KEYS)
        or response["contract"] != "SYNTHETIC_CRM_AIA_PUBLIC_V1"
        or response["real_authorization"] is not False
        or type(response["external_calls"]) is not int
        or response["external_calls"] != 0):
        return False
    if response["state"] == DENIAL:
        return response["projection"] is None
    if response["state"] != SUCCESS:
        return False
    projection = response["projection"]
    if (type(fields) is not list or len(fields) not in (1, 2)
        or any(type(field) is not str or field not in ("ref", "stage") for field in fields)
        or len(fields) != len(set(fields))):
        return False
    if type(projection) is not dict or list(projection) != fields:
        return False
    return all(
        type(value) is str and re.fullmatch(REGEX, value) is not None
        for value in projection.values()
    )


def check(schema_path: Path = SCHEMA, probe: Path = PROBE) -> dict:
    try:
        schema = json.loads(schema_path.read_text(encoding="utf-8"))
    except (OSError, UnicodeError, ValueError) as error:
        raise ResponseParityError("SCHEMA_UNREADABLE") from error
    check_schema(schema)
    try:
        run = subprocess.run(
            ["php", str(probe)], capture_output=True, text=True,
            timeout=15, check=False,
        )
    except (OSError, subprocess.TimeoutExpired) as error:
        raise ResponseParityError("PROBE_UNAVAILABLE") from error
    if run.returncode != 0 or len(run.stdout) > 16384:
        raise ResponseParityError("PROBE_FAILED")
    try:
        result = json.loads(run.stdout)
    except ValueError as error:
        raise ResponseParityError("PROBE_INVALID_JSON") from error
    if (type(result) is not dict
        or set(result) != {"probe", "real_authorization", "external_calls", "cases"}
        or result["probe"] != "SYNTHETIC_OP01_PUBLIC_RESPONSE_PROBE_V1"
        or result["real_authorization"] is not False
        or type(result["external_calls"]) is not int
        or result["external_calls"] != 0
        or type(result["cases"]) is not list):
        raise ResponseParityError("PROBE_SCHEMA_DRIFT")
    expected = {
        "single_ref": (["ref"], SUCCESS),
        "replay": (["ref"], DENIAL),
        "ordered_two_fields": (["stage", "ref"], SUCCESS),
        "ungranted": (["stage"], DENIAL),
        "cross_tenant": (["ref"], DENIAL),
        "unknown_field": (["email"], DENIAL),
    }
    if len(result["cases"]) != len(expected):
        raise ResponseParityError("PROBE_COVERAGE_DRIFT")
    for candidate, (name, (fields, expected_state)) in zip(
        result["cases"], expected.items()
    ):
        if (type(candidate) is not dict
            or set(candidate) != {"case", "fields", "response"}
            or candidate["case"] != name
            or candidate["fields"] != fields
            or not is_safe_response(candidate["response"], fields)
            or candidate["response"]["state"] != expected_state):
            raise ResponseParityError("RESPONSE_CONTRACT_DRIFT")
    # Response values may be fictional but no request-ref, nonce or hidden field
    # can be emitted by the public facade or the probe report.
    for sensitive in (
        "SYNTHETIC_EVID10_REQUEST", "SYNTHETIC_EVID10_NONCE",
        "SYNTHETIC_EVID10_OWNER", "SYNTHETIC_PRINCIPAL", "authorization",
        "error_code", "stack_trace", "SYNTHETIC_B_LEAD",
    ):
        if sensitive in run.stdout:
            raise ResponseParityError("PUBLIC_DATA_LEAK")
    return {
        "report": "SYNTHETIC_OP01_PUBLIC_RESPONSE_PARITY_V1",
        "schema_valid": True,
        "fixtures_checked": len(expected),
        "success_cases": 2,
        "denial_cases": 4,
        "operational_decision": "NO_GO",
        "authorizes_deploy": False,
        "real_authorization": False,
        "external_calls": 0,
    }


def main() -> int:
    try:
        report = check()
        status = 0
    except ResponseParityError as error:
        report = {
            "report": "SYNTHETIC_OP01_PUBLIC_RESPONSE_PARITY_V1",
            "schema_valid": False,
            "error_code": str(error),
            "operational_decision": "NO_GO",
            "authorizes_deploy": False,
            "real_authorization": False,
            "external_calls": 0,
        }
        status = 1
    print(json.dumps(report, sort_keys=True, ensure_ascii=False))
    return status


if __name__ == "__main__":
    sys.exit(main())
