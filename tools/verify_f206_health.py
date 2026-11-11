#!/usr/bin/env python3
"""F206: closed, deterministic PHP queue-health rehearsal verifier (NO_GO)."""
import json
from pathlib import Path
import subprocess
import sys

ROOT = Path(__file__).resolve().parents[1]
FLAGS = {"operational_decision": "NO_GO", "real_authorization": False,
         "external_calls": 0, "write_calls": 0}
QKEYS = ("pending", "reserved", "completed", "failed", "cancelled", "indeterminate")
RKEYS = ("awaiting_first", "awaiting_second", "reviewed_unresolved", "conflict")
# classification, triage state, next label, admission, queue, review, capacity
# audit, decisions, expected capacity pressure, ordered multi-risk signals.
STAGES = {
    "idle": ("QUIET", "QUIET", "NO_LOCAL_WORK", "OPEN",
             (0, 0, 0, 0, 0, 0), (0, 0, 0, 0), 0, 0, "NORMAL",
             ["NO_LOCAL_WORK"]),
    "pending": ("PENDING", "PENDING", "OBSERVE_PENDING", "OPEN",
                (2, 0, 0, 0, 0, 0), (0, 0, 0, 0), 2, 0, "NORMAL",
                ["PENDING_WORK"]),
    "reserved": ("LEASE_IN_PROGRESS", "LEASE_IN_PROGRESS", "OBSERVE_LEASE", "OPEN",
                 (1, 1, 0, 0, 0, 0), (0, 0, 0, 0), 3, 0, "NORMAL",
                 ["LEASE_ACTIVE", "PENDING_WORK"]),
    "uncertain": ("REVIEW_REQUIRED", "REVIEW_REQUIRED", "INSPECT_UNCERTAINTY", "OPEN",
                  (0, 1, 0, 0, 0, 1), (1, 0, 0, 0), 5, 0, "NORMAL",
                  ["UNKNOWN_OUTCOME", "LEASE_ACTIVE"]),
    "paused": ("PAUSED", "PAUSED", "INSPECT_PAUSE", "PAUSED",
               (0, 1, 0, 0, 0, 1), (0, 0, 1, 0), 6, 1, "NORMAL",
               ["ADMISSION_PAUSED", "UNKNOWN_OUTCOME", "LEASE_ACTIVE"]),
    "resumed": ("REVIEW_REQUIRED", "REVIEW_REQUIRED", "INSPECT_UNCERTAINTY", "OPEN",
                (0, 0, 1, 0, 0, 1), (0, 0, 1, 0), 8, 2, "NORMAL",
                ["UNKNOWN_OUTCOME"]),
    "near_capacity": ("CAPACITY_NEAR_LIMIT", "PENDING", "OBSERVE_PENDING", "OPEN",
                      (60, 0, 0, 0, 0, 0), (0, 0, 0, 0), 60, 0, "NEAR_LIMIT",
                      ["CAPACITY_NEAR_LIMIT", "PENDING_WORK"]),
    "full_capacity": ("CAPACITY_EXHAUSTED", "PENDING", "OBSERVE_PENDING", "OPEN",
                      (64, 0, 0, 0, 0, 0), (0, 0, 0, 0), 64, 0, "EXHAUSTED",
                      ["CAPACITY_EXHAUSTED", "PENDING_WORK"]),
}
AUDIT = {"idle": 0, "pending": 2, "reserved": 3, "uncertain": 5,
         "paused": 6, "resumed": 8, "near_capacity": 60, "full_capacity": 64}


def exact(obj, names):
    return type(obj) is dict and set(obj) == set(names)


def no_go(obj):
    return all(key in obj and type(obj[key]) is type(value) and obj[key] == value
               for key, value in FLAGS.items())


def check_count(obj, names, expected):
    if not exact(obj, names):
        raise ValueError("F206_COUNTER_SCHEMA_DENIED")
    for key, val in zip(names, expected):
        if type(obj[key]) is not int or obj[key] != val:
            raise ValueError("F206_COUNTER_VALUE_DENIED")


def verify(data):
    if not exact(data, ("contract", "stages", *FLAGS)):
        raise ValueError("F206_ENVELOPE_SCHEMA_DENIED")
    if data["contract"] != "SYNTHETIC_QUEUE_HEALTH_REHEARSAL_V1" or not no_go(data):
        raise ValueError("F206_ENVELOPE_NO_GO_DENIED")
    if not exact(data["stages"], STAGES):
        raise ValueError("F206_STAGES_DENIED")
    for name, (classification, triage_state, next_step, adm, q, review,
               used, decisions, alert, signals) in STAGES.items():
        health = data["stages"][name]
        if not exact(health, ("contract", "classification", "signals",
                              "triage", "capacity", *FLAGS)):
            raise ValueError("F206_HEALTH_SCHEMA_DENIED")
        if health["contract"] != "SYNTHETIC_QUEUE_HEALTH_V1" or not no_go(health):
            raise ValueError("F206_HEALTH_AUTH_DENIED")
        if (health["classification"] != classification
                or type(health["signals"]) is not list
                or health["signals"] != signals):
            raise ValueError("F206_HEALTH_SIGNALS_DENIED")
        triage, capacity = health["triage"], health["capacity"]
        if not exact(triage, ("contract", "state", "next_step", "queue", "review",
                              "admission", *FLAGS)):
            raise ValueError("F206_TRIAGE_SCHEMA_DENIED")
        if not exact(capacity, ("contract", "alert", "jobs", "audit", "decisions",
                                "resume_budget_available", "admission", *FLAGS)):
            raise ValueError("F206_CAPACITY_SCHEMA_DENIED")
        if (triage["contract"] != "SYNTHETIC_QUEUE_TRIAGE_V1"
                or triage["state"] != triage_state
                or triage["next_step"] != next_step
                or capacity["contract"] != "SYNTHETIC_QUEUE_CAPACITY_V1"
                or capacity["alert"] != alert
                or not no_go(triage) or not no_go(capacity)):
            raise ValueError("F206_NESTED_CONTRACT_DENIED")
        if (not exact(triage["admission"], ("state",))
                or triage["admission"]["state"] != adm
                or capacity["admission"] != triage["admission"]):
            raise ValueError("F206_ADMISSION_DENIED")
        check_count(triage["queue"], QKEYS, q)
        check_count(triage["review"], RKEYS, review)
        for key, used_count, limit in (
            ("jobs", sum(q), 64), ("audit", AUDIT[name], 256),
            ("decisions", decisions, 32),
        ):
            totals = capacity[key]
            if not exact(totals, ("used", "limit", "remaining")):
                raise ValueError("F206_USAGE_SCHEMA_DENIED")
            for field, expected in (("used", used_count), ("limit", limit),
                                    ("remaining", limit - used_count)):
                if type(totals[field]) is not int or totals[field] != expected:
                    raise ValueError("F206_USAGE_DENIED")
        if (type(capacity["resume_budget_available"]) is not bool
                or capacity["resume_budget_available"] != (name == "paused")):
            raise ValueError("F206_RESUME_BUDGET_DENIED")
        if sum(review) != q[-1]:
            raise ValueError("F206_UNCERTAINTY_DENIED")
    encoded = json.dumps(data, ensure_ascii=False)
    for marker in ("run_ref", "claim_ref", "request_ref", "reviewer_ref",
                   "tenant_id", "nonce", "SYNTHETIC_F206_RUN_", "SYNTHETIC_F206_CAP_"):
        if marker in encoded:
            raise ValueError("F206_PRIVATE_REFERENCE_DENIED")
    return True


def fixture():
    out = subprocess.run(["php", "examples/f206-queue-health-demo.php"],
                         cwd=ROOT, capture_output=True, text=True, timeout=25)
    if out.returncode != 0:
        raise ValueError("F206_PHP_FAILED")
    return json.loads(out.stdout)


def main():
    try:
        a, b = fixture(), fixture()
        if a != b:
            raise ValueError("F206_NON_DETERMINISTIC")
        verify(a)
    except (ValueError, OSError, subprocess.TimeoutExpired):
        print("F206_HEALTH_VERIFICATION_FAILED operational_decision=NO_GO",
              file=sys.stderr)
        return 1
    print("F206_HEALTH_VERIFICATION_PASS stages=8 operational_decision=NO_GO")
    return 0


if __name__ == "__main__":
    sys.exit(main())
