#!/usr/bin/env python3
"""AIA-P14: fail-closed static guard on P13's cross-line compatibility gate.

No network, secrets, product services or AI providers are accessed.
This test deliberately does not claim operational IAM/S2S validation.
"""
from pathlib import Path
import sys

ROOT = Path(__file__).resolve().parents[1]
WORKFLOW = ROOT / ".github/workflows/ci-p13-reciprocal-autoia.yml"
REPORT = ROOT / "docs/reviews/AIA-P14-CONVERGENCE-GATES.md"
P13_PIN = "8b04d0e936168c77db028160b5aeb1758db497c4"


def validate(workflow: str, report: str) -> list[str]:
    failures = []
    required = {
        "immutable source reference": f"ref: {P13_PIN}",
        "exact runtime pin check": f'= "{P13_PIN}"',
        "read-only CI permissions": "contents: read",
        "checkout without persisted credentials": "persist-credentials: false",
        "original independent adapter regression": "check_convergence_02.php",
        "NO_AI release blocker": "check_rc02_release.py",
        "P5 route regression": "check_p5_ai_routing.php",
    }
    for name, token in required.items():
        if token not in workflow:
            failures.append(f"Missing {name}: {token}")
    if workflow.count("persist-credentials: false") < 2:
        failures.append("Both checkouts must disable persisted credentials")
    forbidden = (
        "pull_request_target:", "workflow_dispatch:", "permissions: write-all",
        "contents: write", "id-token: write", "secrets.", "docker login",
        "docker push", "kubectl ", "terraform apply",
    )
    for token in forbidden:
        if token in workflow:
            failures.append(f"Forbidden privileged CI fragment: {token}")
    for token in (
        "LAB_NO_AI_PROPOSAL_NOT_SIGNED",
        "NO-GO",
        "IAM operacional",
        "n8n",
        "human",
    ):
        if token.lower() not in report.lower():
            failures.append(f"Missing release-gate statement: {token}")
    return failures


def self_test(workflow: str, report: str) -> None:
    assert not validate(workflow, report), "baseline invalid"
    mutations = (
        (workflow.replace(f"ref: {P13_PIN}", "ref: main"), report),
        (workflow.replace("contents: read", "contents: write"), report),
        (workflow.replace("check_convergence_02.php", "skip_adapter.php"), report),
        (workflow + "\n  pull_request_target:\n", report),
        (workflow, report.replace("LAB_NO_AI_PROPOSAL_NOT_SIGNED", "ALLOW")),
    )
    for index, (candidate, doc) in enumerate(mutations, 1):
        assert validate(candidate, doc), f"adversarial mutation {index} escaped"


if __name__ == "__main__":
    workflow = WORKFLOW.read_text(encoding="utf-8")
    report = REPORT.read_text(encoding="utf-8")
    errors = validate(workflow, report)
    if errors:
        print("\n".join(errors), file=sys.stderr)
        raise SystemExit(1)
    self_test(workflow, report)
    print("AIA_P14_STATIC_GATES_PASS mutations=5 ai_calls=0 operational_auth=false NO_GO")
