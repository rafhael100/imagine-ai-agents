#!/usr/bin/env python3
"""AUTO-IA-06: static deny-by-default release audit of AUTO-IA labs.

No API, cloud, Docker, n8n, provider, tenant data, credentials or paid integration.
This audit is necessary but NEVER sufficient for production clearance.
"""
from __future__ import annotations

import ast
import json
import re
from pathlib import Path

IMPLEMENTATIONS = (
    "tools/auto_ia/AutoIaRouter.php",
    "tools/auto_ia/MockAiBudgetGate.php",
    "tools/auto_ia/MockN8nEnvelope.php",
    "tools/auto_ia/durable_synthetic_ledger.py",
    "tools/auto_ia/synthetic_reconciliation.py",
)
PROHIBITED_DOMAINS = (
    "api.openai.com", "api.anthropic.com",
    "generativelanguage.googleapis.com", "api.groq.com",
    "api.mistral.ai", "api.deepseek.com",
)
PROHIBITED_PHP = re.compile(
    r"\b(?:curl_init|curl_exec|fsockopen|pfsockopen|"
    r"stream_socket_client|shell_exec|proc_open)\s*\(", re.I
)
PROHIBITED_PYTHON = {"requests", "urllib", "http", "socket", "httpx", "aiohttp", "openai",
                     "anthropic", "google.generativeai", "boto3"}

# A human signature or technical governance must be provided OUTSIDE this
# laboratory. No environment variable or passing CI should change these gates.
RELEASE_GATES = {
    "production_iam_and_tenant_scope": False,
    "signed_provider_receipts_and_cost_reconciliation": False,
    "atomic_postgresql_budget_and_replay_rls": False,
    "human_approval_and_independent_security_review": False,
    "production_secret_custody_and_network_identity": False,
    "n8n_license_and_private_vps_homologation": False,
    "backup_restore_rollout_and_rollback": False,
    "billing_policy_and_credit_conversion_approved": False,
}

PROJECTS = [
    "imagine-portal", "imagine-crm", "imagine-sites", "imagine-comercial",
    "imagine-performance-ai", "imagine-universidade", "imagine-seo-engine",
    "imagine-ai-agents",
]
REQUIRED_INVARIANTS = {
    "tools/auto_ia/AutoIaRouter.php": ("'provider_calls' => 0", "'model_tokens' => 0"),
    "tools/auto_ia/MockAiBudgetGate.php": ("'provider_calls' => 0", "'billed_minor' => 0"),
    "tools/auto_ia/MockN8nEnvelope.php": ("'service_effects' => 0", "'external_calls' => 0", "'ai_tokens' => 0"),
    "tools/auto_ia/synthetic_reconciliation.py": (
        '"commercial_credits_debited": 0', '"actual_provider_bill_minor": None',
        '"live_billing_authorized": False',
    ),
    "tools/auto_ia/durable_synthetic_ledger.py": (
        '"LAB_RESERVED_NO_AUTHORIZATION"', '"LAB_OBSERVED_UNSIGNED_FIXTURE_ONLY"',
    ),
}


def problems(contents: dict[str, str]) -> list[str]:
    issues: list[str] = []
    for path in IMPLEMENTATIONS:
        if path not in contents:
            issues.append(path + ":missing")
            continue
        source = contents[path]
        if len(source) > 120_000:
            issues.append(path + ":unexpected_size")
        for domain in PROHIBITED_DOMAINS:
            if domain in source.lower():
                issues.append(path + ":paid_provider_domain")
        if path.endswith(".php"):
            if PROHIBITED_PHP.search(source):
                issues.append(path + ":network_or_process_invocation")
        else:
            try:
                tree = ast.parse(source, filename=path)
                for node in ast.walk(tree):
                    if isinstance(node, ast.Import):
                        mods = (item.name.split(".")[0] for item in node.names)
                    elif isinstance(node, ast.ImportFrom):
                        mods = ((node.module or "").split(".")[0],)
                    else:
                        continue
                    if any(mod in PROHIBITED_PYTHON for mod in mods):
                        issues.append(path + ":external_client_import")
            except SyntaxError:
                issues.append(path + ":python_syntax_invalid")
        for invariant in REQUIRED_INVARIANTS[path]:
            if invariant not in source:
                issues.append(path + ":zero_effect_invariant_missing")
    return issues


def readiness(contents: dict[str, str]) -> dict:
    blockers = [name for name, satisfied in RELEASE_GATES.items() if not satisfied]
    violations = problems(contents)
    # This is *always* NO_GO for production, even after all static checks pass.
    return {
        "schema": "AUTO_IA_RELEASE_REVIEW_V1",
        "project_count": len(PROJECTS),
        "products": PROJECTS,
        "static_lab_checks": "PASS" if not violations else "FAIL",
        "production_decision": "NO_GO",
        "production_blockers": blockers,
        "violations": violations,
        "ai_provider_calls": 0,
        "credits_debited": 0,
        "vps_changed": False,
    }


def read_sources(base: Path) -> dict[str, str]:
    found = {}
    for path in IMPLEMENTATIONS:
        target = base / path
        if target.is_symlink():
            continue
        if target.is_file():
            found[path] = target.read_text(encoding="utf-8")
    return found


def main() -> None:
    base = Path(__file__).resolve().parents[2]
    report = readiness(read_sources(base))
    print(json.dumps(report, sort_keys=True, separators=(",", ":")))
    if report["static_lab_checks"] != "PASS" or report["production_decision"] != "NO_GO":
        raise SystemExit(1)


if __name__ == "__main__":
    main()
