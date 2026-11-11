#!/usr/bin/env python3
"""Fail-closed verification of the six-stage F201 synthetic PHP triage."""
import json
import pathlib
import subprocess
import sys

ROOT = pathlib.Path(__file__).resolve().parents[1]
STATES = {
    "empty": ("QUIET", "NO_LOCAL_WORK", "OPEN",
              (0, 0, 0, 0, 0, 0), (0, 0, 0, 0)),
    "pending": ("PENDING", "OBSERVE_PENDING", "OPEN",
                (2, 0, 0, 0, 0, 0), (0, 0, 0, 0)),
    "leased": ("LEASE_IN_PROGRESS", "OBSERVE_LEASE", "OPEN",
               (1, 1, 0, 0, 0, 0), (0, 0, 0, 0)),
    "uncertain": ("REVIEW_REQUIRED", "INSPECT_UNCERTAINTY", "OPEN",
                  (0, 1, 0, 0, 0, 1), (1, 0, 0, 0)),
    "paused": ("PAUSED", "INSPECT_PAUSE", "PAUSED",
               (0, 1, 0, 0, 0, 1), (1, 0, 0, 0)),
    "resumed": ("REVIEW_REQUIRED", "INSPECT_UNCERTAINTY", "OPEN",
                (0, 0, 1, 0, 0, 1), (0, 0, 1, 0)),
}
QUEUE = ("pending", "reserved", "completed", "failed", "cancelled", "indeterminate")
REVIEW = ("awaiting_first", "awaiting_second", "reviewed_unresolved", "conflict")
FLAGS = ("operational_decision", "real_authorization", "external_calls", "write_calls")


def closed(mapping, keys):
    return type(mapping) is dict and set(mapping) == set(keys)


def no_go(mapping):
    return (
        mapping["operational_decision"] == "NO_GO"
        and mapping["real_authorization"] is False
        and type(mapping["external_calls"]) is int
        and mapping["external_calls"] == 0
        and type(mapping["write_calls"]) is int
        and mapping["write_calls"] == 0
    )


def verify(data):
    if not closed(data, ("contract", "stages", *FLAGS)):
        raise ValueError("F201_SCHEMA_DENIED")
    if data["contract"] != "SYNTHETIC_QUEUE_TRIAGE_REHEARSAL_V1" or not no_go(data):
        raise ValueError("F201_NO_GO_DENIED")
    if not closed(data["stages"], STATES):
        raise ValueError("F201_STAGES_DENIED")
    for name, (state, action, admission, queue, review) in STATES.items():
        stage = data["stages"][name]
        if not closed(stage, ("contract", "state", "next_step",
                              "queue", "review", "admission", *FLAGS)):
            raise ValueError("F201_STAGE_SCHEMA_DENIED")
        if (stage["contract"] != "SYNTHETIC_QUEUE_TRIAGE_V1"
                or stage["state"] != state
                or stage["next_step"] != action
                or not no_go(stage)):
            raise ValueError("F201_STAGE_DENIED")
        if not closed(stage["admission"], ("state",)) or stage["admission"]["state"] != admission:
            raise ValueError("F201_ADMISSION_DENIED")
        if not closed(stage["queue"], QUEUE) or not closed(stage["review"], REVIEW):
            raise ValueError("F201_COUNTER_SCHEMA_DENIED")
        for keys, expected in ((QUEUE, queue), (REVIEW, review)):
            for key, value in zip(keys, expected):
                if type(stage["queue" if keys == QUEUE else "review"][key]) is not int:
                    raise ValueError("F201_COUNTER_TYPE_DENIED")
                if stage["queue" if keys == QUEUE else "review"][key] != value:
                    raise ValueError("F201_COUNTER_VALUE_DENIED")
    # Full scan prevents leaking identifiers, even under unrecognized nested values.
    payload = json.dumps(data, separators=(",", ":"), ensure_ascii=False)
    for forbidden in ("run_ref", "claim_ref", "request_ref", "reviewer_ref",
                      "nonce", "SYNTHETIC_F201_RUN_", "SYNTHETIC_F201_REQ_"):
        if forbidden in payload:
            raise ValueError("F201_PRIVATE_REF_DENIED")
    return True


def run_fixture():
    proc = subprocess.run(
        ["php", "examples/f201-queue-triage-demo.php"],
        cwd=ROOT, capture_output=True, text=True, timeout=25, check=False,
    )
    if proc.returncode != 0:
        raise ValueError("F201_PHP_FAILED")
    return json.loads(proc.stdout)


def main():
    try:
        first, second = run_fixture(), run_fixture()
        if first != second:
            raise ValueError("F201_NON_DETERMINISTIC")
        verify(first)
    except (ValueError, OSError, subprocess.TimeoutExpired):
        print("F201_VERIFICATION_FAILED operational_decision=NO_GO", file=sys.stderr)
        return 1
    print("F201_VERIFICATION_PASS operational_decision=NO_GO stages=6")
    return 0


if __name__ == "__main__":
    sys.exit(main())
