#!/usr/bin/env python3
"""AUTO-IA-03 deterministic SQLite persistence and independent-process contention tests."""
from __future__ import annotations

import hashlib
import json
import multiprocessing
import os
import subprocess
import tempfile
from pathlib import Path

from durable_synthetic_ledger import (
    AI_CANDIDATES, DurableSyntheticLedger, LabDenied, fake_proof_digest,
)

CHECKS = 0


def check(condition: bool, title: str) -> None:
    global CHECKS
    CHECKS += 1
    if not condition:
        raise AssertionError("AUTO_IA_03_FAILED " + title)


def denied(code: str, fn) -> None:
    try:
        fn()
    except LabDenied as e:
        check(e.code == code, "expected " + code + " got " + e.code)
        return
    raise AssertionError("expected denial " + code)


def args(event="SYNTHETIC_EVT_001", tenant="SYNTHETIC_TENANT_A", key="SYNTHETIC_KEY_001",
         cost=3, tokens=10):
    return dict(
        tenant=tenant, product="imagine-crm", operation="lead.message.interpret",
        event=event, request_key=key, policy="SYNTHETIC_POLICY_A",
        payload_digest=hashlib.sha256(b"static synthetic mock request").hexdigest(),
        estimated_tokens=tokens, estimated_minor=cost,
    )


def worker(file: str, i: int, queue: multiprocessing.Queue, start: multiprocessing.Event) -> None:
    start.wait(10)
    try:
        ledger = DurableSyntheticLedger(file)
        result = ledger.reserve(**args("SYNTHETIC_EVT_" + str(i),
                                       key="SYNTHETIC_KEY_" + str(i), cost=4, tokens=11))
        ledger.close()
        queue.put(result)
    except LabDenied as exc:
        queue.put(exc.code)
    except Exception as exc:
        queue.put("ERROR_" + type(exc).__name__)


def main() -> None:
    matrix = subprocess.run(
        ["php", "-r",
         "require 'tools/auto_ia/AutoIaRouter.php'; "
         "echo json_encode(\\ImagineAI\\AutomationFirst\\AutoIaRouter::matrix());"],
        capture_output=True, text=True, check=True,
    )
    parsed = json.loads(matrix.stdout)
    pairs = {(product, operation) for product, operations in parsed.items()
             for operation, kind in operations.items() if kind != "NO_AI"}
    check(pairs == AI_CANDIDATES, "exact PHP catalog parity")
    with tempfile.TemporaryDirectory(prefix="auto-ia-03-") as directory:
        filename = str(Path(directory) / "ledger.sqlite")
        l = DurableSyntheticLedger(filename)
        check((os.stat(filename).st_mode & 0o777) == 0o600, "db file private")
        l.add_policy("SYNTHETIC_TENANT_A", "imagine-crm", "SYNTHETIC_POLICY_A", 12, 40)
        l.add_policy("SYNTHETIC_TENANT_B", "imagine-crm", "SYNTHETIC_POLICY_A", 12, 40)
        check(l.reserve(**args()) == "LAB_RESERVED_NO_AUTHORIZATION", "reserve lab")
        check(l.reserve(**args()) == "LAB_IDEMPOTENT_REPLAY_NO_AUTHORIZATION", "idempotent replay")
        check(l.exposure("SYNTHETIC_TENANT_A", "imagine-crm", "SYNTHETIC_POLICY_A") ==
              {"minor": 3, "tokens": 10, "unknown": 0, "events": 1}, "one immutable exposure")
        denied("REQUEST_IDENTITY_CONFLICT", lambda: l.reserve(**args(cost=4)))
        denied("REQUEST_IDENTITY_CONFLICT",
               lambda: l.reserve(**args(event="SYNTHETIC_EVT_002")))
        denied("NO_AI_OR_UNRECOGNIZED_OPERATION",
               lambda: l.reserve(**dict(args(), operation="lead.ingest")))
        denied("REQUEST_NOT_FOUND_OR_SCOPE_DENIED", lambda: l.mark_unknown(
            "SYNTHETIC_TENANT_B", "imagine-crm", "SYNTHETIC_EVT_001", "SYNTHETIC_KEY_001"
        ))
        check(l.mark_unknown("SYNTHETIC_TENANT_A", "imagine-crm",
                             "SYNTHETIC_EVT_001", "SYNTHETIC_KEY_001") ==
              "LAB_UNKNOWN_HELD_FOR_REVIEW", "uncertainty recorded")
        denied("UNKNOWN_USAGE_FREEZES_POLICY",
               lambda: l.reserve(**args(event="SYNTHETIC_EVT_003", key="SYNTHETIC_KEY_003")))
        l.close()
        l = DurableSyntheticLedger(filename)
        check(l.status("SYNTHETIC_TENANT_A", "imagine-crm",
                       "SYNTHETIC_EVT_001", "SYNTHETIC_KEY_001") == "UNKNOWN",
              "uncertainty survives restart")
        proof = dict(
            tenant="SYNTHETIC_TENANT_A", product="imagine-crm",
            event="SYNTHETIC_EVT_001", request_key="SYNTHETIC_KEY_001",
            proof_ref="SYNTHETIC_PROOF_001", observed_tokens=13, observed_minor=5,
        )
        proof["proof_digest"] = fake_proof_digest(
            proof["tenant"], proof["product"], proof["event"], proof["request_key"],
            proof["observed_tokens"], proof["observed_minor"], proof["proof_ref"]
        )
        denied("SYNTHETIC_PROOF_MISMATCH",
               lambda: l.observe(**dict(proof, proof_digest="0" * 64)))
        check(l.observe(**proof) == "LAB_OBSERVED_UNSIGNED_FIXTURE_ONLY", "reconcile synthetic")
        check(l.observe(**proof) == "LAB_OBSERVATION_IDEMPOTENT", "observation idempotent")
        denied("CONFLICTING_OBSERVATION",
               lambda: l.observe(**dict(
                   proof, observed_minor=6,
                   proof_digest=fake_proof_digest(proof["tenant"], proof["product"],
                       proof["event"], proof["request_key"], proof["observed_tokens"],
                       6, proof["proof_ref"])
               )))
        check(l.exposure("SYNTHETIC_TENANT_A", "imagine-crm", "SYNTHETIC_POLICY_A") ==
              {"minor": 5, "tokens": 13, "unknown": 0, "events": 1}, "settlement exposure")
        check(l.reserve(**args(event="SYNTHETIC_EVT_004", key="SYNTHETIC_KEY_004",
                               cost=7, tokens=27)) == "LAB_RESERVED_NO_AUTHORIZATION",
              "exact cap accepted")
        denied("BUDGET_EXCEEDED",
               lambda: l.reserve(**args(event="SYNTHETIC_EVT_005", key="SYNTHETIC_KEY_005",
                                        cost=1, tokens=1)))
        check(l.reserve(**args(event="SYNTHETIC_EVT_001", key="SYNTHETIC_KEY_001",
                               tenant="SYNTHETIC_TENANT_B")) ==
              "LAB_RESERVED_NO_AUTHORIZATION", "tenant isolation")
        l.disable_policy("SYNTHETIC_TENANT_B", "imagine-crm", "SYNTHETIC_POLICY_A")
        denied("POLICY_NOT_ACTIVE",
               lambda: l.reserve(**args(event="SYNTHETIC_EVT_006",
                                        key="SYNTHETIC_KEY_006",
                                        tenant="SYNTHETIC_TENANT_B")))
        check(l.exposure("SYNTHETIC_TENANT_B", "imagine-crm", "SYNTHETIC_POLICY_A")["events"] == 1,
              "revocation preserves history")
        l.close()

        # Parallel independent SQLite connections must not oversubscribe quota.
        contended = str(Path(directory) / "concurrency.sqlite")
        c = DurableSyntheticLedger(contended)
        c.add_policy("SYNTHETIC_TENANT_A", "imagine-crm", "SYNTHETIC_POLICY_A", 12, 33)
        c.close()
        ctx = multiprocessing.get_context("spawn")
        event = ctx.Event()
        queue = ctx.Queue()
        processes = [ctx.Process(target=worker, args=(contended, i + 10, queue, event))
                     for i in range(8)]
        for p in processes:
            p.start()
        event.set()
        results = [queue.get(timeout=30) for _ in processes]
        for p in processes:
            p.join(timeout=30)
            check(p.exitcode == 0, "worker exited cleanly")
        check(results.count("LAB_RESERVED_NO_AUTHORIZATION") == 3,
              "exactly three of eight budget reservations accepted")
        check(results.count("BUDGET_EXCEEDED") == 5,
              "all other reservations blocked atomically")
        c = DurableSyntheticLedger(contended)
        check(c.exposure("SYNTHETIC_TENANT_A", "imagine-crm", "SYNTHETIC_POLICY_A") ==
              {"minor": 12, "tokens": 33, "unknown": 0, "events": 3},
              "durable concurrence never overspends")
        c.close()
    print(f"AUTO_IA_03_PASS checks={CHECKS} synthetic=1 persistent=1 atomic=1 ai_calls=0")


if __name__ == "__main__":
    main()
