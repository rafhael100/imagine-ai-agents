#!/usr/bin/env python3
"""F209 closed output verifier: 13 synthetic code cases never become real approval."""
import json
import sys
from verify_f206_health import decode_fixture_json

IDS = tuple(f"OP01-N{i:02d}" for i in range(1, 12)) + ("OP01-P01", "OP01-P02")
SHAPE_VALID = {
    "OP01-N01": False, "OP01-N02": False,
    "OP01-N03": True, "OP01-N04": True,
    "OP01-N05": False, "OP01-N06": False,
    "OP01-N07": True, "OP01-N08": True, "OP01-N09": True,
    "OP01-N10": False, "OP01-N11": True,
    "OP01-P01": True, "OP01-P02": True,
}


def exact(obj, keys):
    return type(obj) is dict and set(obj) == set(keys)


def verify(data):
    if not exact(data, ("contract", "scope", "cases",
                        "cases_real_executed", "operational_decision",
                        "real_authorization", "external_calls")):
        raise ValueError("F209_ENVELOPE_DENIED")
    if (data["contract"] != "SYNTHETIC_CRM_AIA_OP01_MATRIX_V1"
            or data["scope"] != "TWO_REPOSITORY_SOURCE_CODE_ONLY"
            or data["operational_decision"] != "NO_GO"
            or data["real_authorization"] is not False
            or type(data["external_calls"]) is not int
            or data["external_calls"] != 0
            or type(data["cases_real_executed"]) is not int
            or data["cases_real_executed"] != 0):
        raise ValueError("F209_AUTHORITY_DENIED")
    rows = data["cases"]
    if type(rows) is not list or len(rows) != 13:
        raise ValueError("F209_ROW_COUNT_DENIED")
    for wanted, row in zip(IDS, rows):
        if not exact(row, ("id", "synthetic_request_shape_valid",
                           "mock_response", "operational_status")):
            raise ValueError("F209_ROW_SCHEMA_DENIED")
        if (row["id"] != wanted
                or type(row["synthetic_request_shape_valid"]) is not bool
                or row["synthetic_request_shape_valid"] != SHAPE_VALID[wanted]
                or row["mock_response"] != "DENIED_OR_NOT_FOUND"
                or row["operational_status"] != "NAO_EXECUTADO"):
            raise ValueError("F209_CASE_DENIED")
    return True


def main():
    try:
        raw = sys.stdin.buffer.read(65537)
        if len(raw) > 65536:
            raise ValueError("F209_OVERSIZE_DENIED")
        data = decode_fixture_json(raw.decode("utf-8"))
        verify(data)
    except (ValueError, UnicodeError):
        print("F209_VERIFIER_DENIED operational_decision=NO_GO", file=sys.stderr)
        return 1
    print("F209_MATRIX_PASS cases=13 real_executed=0 operational_decision=NO_GO")
    return 0


if __name__ == "__main__":
    sys.exit(main())
