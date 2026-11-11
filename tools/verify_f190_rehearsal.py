#!/usr/bin/env python3
"""F191 offline verifier for the synthetic F190 pause/resume rehearsal.

Runs the local PHP fixture twice. Never reads customer input or makes network calls.
This check is evidence of deterministic mock behavior, NOT operational approval.
"""
import json
import pathlib
import subprocess
import sys

ROOT = pathlib.Path(__file__).resolve().parent.parent
DEMO = ROOT / "examples" / "f190-queue-admission-demo.php"
EXPECTED_KEYS = frozenset({
    "contract", "paused", "resumed", "operational_decision",
    "real_authorization", "external_calls", "write_calls",
})
FORBIDDEN = frozenset({
    "run_ref", "claim_ref", "reviewer", "decision_ref", "tenant",
    "lead", "principal", "token", "reason", "request_ref",
})


def verify(payload):
    """Validate closed outer schema and safety-critical invariants."""
    if type(payload) is not dict or frozenset(payload) != EXPECTED_KEYS:
        raise ValueError("INVALID_OUTER_SCHEMA")
    if payload["contract"] != "SYNTHETIC_QUEUE_PAUSE_REHEARSAL_V1":
        raise ValueError("INVALID_CONTRACT")
    if (payload["operational_decision"] != "NO_GO"
            or payload["real_authorization"] is not False
            or type(payload["external_calls"]) is not int
            or payload["external_calls"] != 0
            or type(payload["write_calls"]) is not int
            or payload["write_calls"] != 0):
        raise ValueError("UNSAFE_OPERATIONAL_FLAGS")
    for stage, state, pending, completed in (
        ("paused", "PAUSED", 1, 0),
        ("resumed", "OPEN", 0, 1),
    ):
        entry = payload[stage]
        if type(entry) is not dict:
            raise ValueError("INVALID_SUPERVISION")
        if frozenset(entry) != frozenset({"contract", "queue", "review", "admission", "operational_decision", "real_authorization", "external_calls", "write_calls"}):
            raise ValueError("INVALID_SUPERVISION_SCHEMA")
        if entry.get("contract") != "SYNTHETIC_QUEUE_SUPERVISION_V1":
            raise ValueError("INVALID_SUPERVISION_CONTRACT")
        if entry.get("admission") != {"state": state}:
            raise ValueError("INVALID_ADMISSION")
        queue, review = entry.get("queue"), entry.get("review")
        if type(queue) is not dict or type(review) is not dict:
            raise ValueError("INVALID_COUNTS")
        for key, value in (("pending", pending), ("completed", completed),
                           ("indeterminate", 1)):
            if type(queue.get(key)) is not int or queue[key] != value:
                raise ValueError("INVALID_QUEUE_COUNTS")
        if (type(review.get("reviewed_unresolved")) is not int
                or review["reviewed_unresolved"] != 1):
            raise ValueError("UNCERTAINTY_WAS_LOST")
    def scan(obj):
        if type(obj) is dict:
            for key, value in obj.items():
                if key.lower() in FORBIDDEN:
                    raise ValueError("FORBIDDEN_IDENTIFIER")
                scan(value)
        elif type(obj) is list:
            for value in obj:
                scan(value)
    scan(payload)
    return True


def run_fixture():
    process = subprocess.run(
        ["php", str(DEMO)], cwd=str(ROOT), input=b"",
        stdout=subprocess.PIPE, stderr=subprocess.PIPE,
        timeout=15, check=False,
    )
    if process.returncode or len(process.stdout) > 32768:
        raise ValueError("FIXTURE_FAILED")
    try:
        return json.loads(process.stdout.decode("utf-8"))
    except (UnicodeError, json.JSONDecodeError) as exc:
        raise ValueError("INVALID_JSON") from exc


def main():
    try:
        first, second = run_fixture(), run_fixture()
        verify(first)
        verify(second)
        if first != second:
            raise ValueError("NON_DETERMINISTIC")
    except (ValueError, OSError, subprocess.TimeoutExpired):
        print("F191_OFFLINE_REHEARSAL_FAIL operational_decision=NO_GO", file=sys.stderr)
        return 1
    print("F191_OFFLINE_REHEARSAL_PASS operational_decision=NO_GO")
    return 0


if __name__ == "__main__":
    sys.exit(main())
