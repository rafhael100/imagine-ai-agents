#!/usr/bin/env python3
"""AUTO-IA-06 independent negative mutations: never convert lab into launch."""
from __future__ import annotations

from pathlib import Path
from release_readiness_lab import (
    IMPLEMENTATIONS, PROJECTS, RELEASE_GATES, problems, readiness, read_sources,
)

base = Path(__file__).resolve().parents[2]
original = read_sources(base)
checks = 0


def check(ok: bool, why: str):
    global checks
    checks += 1
    if not ok:
        raise AssertionError("AUTO_IA_06_FAILED " + why)


clean = readiness(original)
check(clean["static_lab_checks"] == "PASS", "existing source must pass static audit")
check(clean["production_decision"] == "NO_GO", "no promotion")
check(len(PROJECTS) == 8, "all ecosystem projects represented")
check(len(RELEASE_GATES) >= 8 and all(v is False for v in RELEASE_GATES.values()),
      "no hidden gate open")
check(clean["credits_debited"] == 0 and clean["ai_provider_calls"] == 0,
      "zero provider or commercial operation")
check(clean["vps_changed"] is False, "audit does not change VPS")
for missing in IMPLEMENTATIONS:
    reduced = dict(original)
    del reduced[missing]
    check(bool(problems(reduced)), "missing security source rejected " + missing)

for path, addition, expected in [
    ("tools/auto_ia/durable_synthetic_ledger.py", "\nimport requests\n", ":external_client_import"),
    ("tools/auto_ia/synthetic_reconciliation.py", "\nimport socket\n", ":external_client_import"),
    ("tools/auto_ia/MockAiBudgetGate.php", "\n<?php curl_init('http://bad');", ":network_or_process_invocation"),
    ("tools/auto_ia/MockN8nEnvelope.php", "\n<?php fsockopen('127.0.0.1');", ":network_or_process_invocation"),
    ("tools/auto_ia/AutoIaRouter.php", "\nhttps://api.openai.com/v1", ":paid_provider_domain"),
]:
    mutated = dict(original)
    mutated[path] += addition
    check(any(expected in issue for issue in problems(mutated)),
          "forbidden transport mutation rejected " + path)

for path, literal in [
    ("tools/auto_ia/MockAiBudgetGate.php", "'billed_minor' => 0"),
    ("tools/auto_ia/MockN8nEnvelope.php", "'service_effects' => 0"),
    ("tools/auto_ia/synthetic_reconciliation.py", '"commercial_credits_debited": 0'),
]:
    mutated = dict(original)
    mutated[path] = mutated[path].replace(literal, literal.replace("0", "1"))
    check(any("zero_effect_invariant_missing" in issue for issue in problems(mutated)),
          "nonzero effect/cost mutation rejected " + path)

check(readiness(dict())["production_decision"] == "NO_GO",
      "even missing modules cannot enable launch")
print(f"AUTO_IA_06_PASS checks={checks} production=NO_GO provider_calls=0 credits_debited=0")
