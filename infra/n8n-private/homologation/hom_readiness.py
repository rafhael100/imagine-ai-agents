#!/usr/bin/env python3
"""Offline, fail-closed evidence inventory for private n8n homologation.

This tool NEVER authorizes deployment. It only reports incomplete evidence.
No network, shell, credentials, Docker, or filesystem writes.
"""
import argparse
import json
import re
import stat
import sys
from pathlib import Path

SCHEMA = "IMAGINE_AIA_HOM_READINESS_V1"
GATES = ("HOM-01", "HOM-02", "HOM-03", "HOM-04", "HOM-05", "HOM-06")
SHA40 = re.compile(r"^[a-f0-9]{40}$")
MAX_BYTES = 65536


def analyze(data, expected_sha):
    """Return an informational inventory; never an authorization."""
    errors = []
    if not isinstance(data, dict) or set(data) != {"schema", "candidate_sha", "gates"}:
        return {"status": "NO_GO", "operational_authorization": False,
                "errors": ["INVALID_TOP_LEVEL"], "missing_gates": list(GATES)}
    if data["schema"] != SCHEMA:
        errors.append("INVALID_SCHEMA")
    if not isinstance(data["candidate_sha"], str) or not SHA40.fullmatch(data["candidate_sha"]):
        errors.append("INVALID_CANDIDATE_SHA")
    if data["candidate_sha"] != expected_sha:
        errors.append("SHA_MISMATCH")
    raw_gates = data["gates"]
    if not isinstance(raw_gates, dict) or set(raw_gates) != set(GATES):
        errors.append("INVALID_GATE_SET")
        raw_gates = {}
    missing = []
    for gate in GATES:
        item = raw_gates.get(gate)
        if not isinstance(item, dict) or set(item) != {"state", "evidence"}:
            errors.append(gate + ":INVALID_FIELDS")
            missing.append(gate)
            continue
        state, evidence = item["state"], item["evidence"]
        if state not in ("PENDING", "BLOCKED", "REVIEWED"):
            errors.append(gate + ":INVALID_STATE")
        if not isinstance(evidence, list) or len(evidence) > 10 or any(
            not isinstance(ref, str) or not re.fullmatch(r"[A-Za-z0-9][A-Za-z0-9._:/#-]{0,199}", ref)
            for ref in evidence
        ) or len(evidence) != len(set(evidence)):
            errors.append(gate + ":INVALID_EVIDENCE")
        if state != "REVIEWED" or not evidence:
            missing.append(gate)
    return {
        "status": "NO_GO" if errors or missing else "REVIEW_REQUIRED",
        "operational_authorization": False,
        "errors": sorted(set(errors)),
        "missing_gates": missing,
        "note": "Documentary completeness is not human approval or a deploy permit.",
    }


def reject_duplicate_keys(pairs):
    obj = {}
    for key, value in pairs:
        if key in obj:
            raise ValueError("DUPLICATE_JSON_KEY")
        obj[key] = value
    return obj


def load_evidence(path):
    # Bound the actual bytes read; stat alone can race with file replacement.
    mode = path.lstat().st_mode
    if not stat.S_ISREG(mode):
        raise ValueError("NOT_REGULAR_FILE")
    with path.open("rb") as handle:
        payload = handle.read(MAX_BYTES + 1)
    if len(payload) > MAX_BYTES:
        raise ValueError("EVIDENCE_TOO_LARGE")
    return json.loads(
        payload.decode("utf-8"),
        object_pairs_hook=reject_duplicate_keys,
        parse_constant=lambda _: (_ for _ in ()).throw(ValueError("NON_FINITE")),
    )


def main(argv=None):
    p = argparse.ArgumentParser(description=__doc__)
    p.add_argument("evidence_json", type=Path)
    p.add_argument("--expected-candidate-sha", required=True)
    args = p.parse_args(argv)
    if not SHA40.fullmatch(args.expected_candidate_sha):
        p.error("expected candidate SHA must be 40 lowercase hex characters")
    try:
        data = load_evidence(args.evidence_json)
    except (OSError, UnicodeError, json.JSONDecodeError, ValueError) as exc:
        print(json.dumps({"status": "NO_GO", "operational_authorization": False,
                          "errors": [type(exc).__name__], "missing_gates": list(GATES)}, sort_keys=True))
        return 2
    result = analyze(data, args.expected_candidate_sha)
    print(json.dumps(result, sort_keys=True, ensure_ascii=False))
    return 0 if result["status"] == "REVIEW_REQUIRED" else 2


if __name__ == "__main__":
    sys.exit(main())
