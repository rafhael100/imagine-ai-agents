#!/usr/bin/env python3
"""AUTO-IA RC03 CI-only release blocker and adversarial source checks.

This review is necessary but not sufficient. Production is hard-coded NO_GO.
No network access, credentials, live IAM, Docker startup or automation calls.
"""
from __future__ import annotations

import json
import re
from pathlib import Path

ROOT=Path(__file__).resolve().parents[2]
P12_SHA="aacbfdaa9fbfd878834d3c7b46eeaf99b4b8fdd9"
FILES=(
    "tools/auto_ia/convergence/MockP7ToAutoIaAdapter.php",
    "tools/auto_ia/convergence/check_convergence_04.php",
    "tools/auto_ia/convergence/check_convergence_05_seed.php",
    "tools/auto_ia/convergence/check_convergence_05_restore.sql",
    "tools/auto_ia/pg/001_auto_ia_pg_lab.sql",
    "tools/auto_ia/pg/002_no_ai_inbox.sql",
    "tools/auto_ia/pg/003_signed_ingress_lab.sql",
    "tools/auto_ia/convergence/004_mock_agents_signer_key.sql",
    "infra/n8n-private/docker-compose.yml",
    "tools/auto_ia/demo/n8n-noai-demo.workflow.json",
    ".github/workflows/auto-ia-rc03-convergence.yml",
    "docs/AIA-RC03-CONVERGENCIA-P12-RC02.md",
)
RESTRICTED_CALLS=re.compile(
    r"\b(?:curl_init|curl_exec|fsockopen|pfsockopen|shell_exec|proc_open)\s*\(",
    re.IGNORECASE,
)
UNSAFE_ACTIONS=(
    "pull_request_target:", "workflow_dispatch:", "docker compose up",
    "docker run ", "docker push ", "ssh ", "scp ", "rsync ",
    "kubectl ", "deploy-prod", "permissions:\n  contents: write",
)

def audit_sources(contents: dict[str,str])->list[str]:
    issues=[]
    for name in FILES:
        if name not in contents:
            issues.append(name+":missing")
            continue
        value=contents[name]
        if len(value)>120_000:
            issues.append(name+":unexpected_size")
        if name.endswith(".php") and RESTRICTED_CALLS.search(value):
            issues.append(name+":forbidden_io")
    workflow=contents.get(".github/workflows/auto-ia-rc03-convergence.yml","")
    if workflow.count("ref: "+P12_SHA)!=2:
        issues.append("workflow:pinned_P12_SHA_not_used_twice")
    if workflow.count("persist-credentials: false")<5:
        issues.append("workflow:credentials_persistence_not_disabled")
    if "contents: read" not in workflow:
        issues.append("workflow:missing_least_privilege")
    for forbidden in UNSAFE_ACTIONS:
        if forbidden in workflow:
            issues.append("workflow:unsafe_action")
    if "pg_dump" not in workflow or "pg_restore" not in workflow:
        issues.append("workflow:recovery_proof_missing")
    if "check_convergence_04.php" not in workflow or "check_convergence_05_seed.php" not in workflow:
        issues.append("workflow:missing_handoff_or_restore")
    if "auto_ia_lab.receive_signed_no_ai" not in contents.get(
        "tools/auto_ia/convergence/check_convergence_04.php",""
    ):
        issues.append("handoff:unsigned_inbox_bypass")
    if "LAB_DUPLICATE_NO_DELIVERY" not in contents.get(
        "tools/auto_ia/convergence/check_convergence_05_restore.sql",""
    ):
        issues.append("restore:replay_not_tested")
    if "LAB_DENY_REPLAY_CONFLICT" not in contents.get(
        "tools/auto_ia/pg/002_no_ai_inbox.sql",""
    ):
        issues.append("inbox:replay_not_denied")
    if "FOR UPDATE" not in contents.get("tools/auto_ia/pg/001_auto_ia_pg_lab.sql",""):
        issues.append("quota:atomicity_missing")
    docs=contents.get("docs/AIA-RC03-CONVERGENCIA-P12-RC02.md","")
    if "NO-GO" not in docs or "P12" not in docs or "VPS" not in docs:
        issues.append("docs:release_constraints_missing")
    return issues

def read_source()->dict[str,str]:
    return {
        name:(ROOT/name).read_text(encoding="utf-8")
        for name in FILES if (ROOT/name).is_file() and not (ROOT/name).is_symlink()
    }

def main()->None:
    contents=read_source()
    problems=audit_sources(contents)
    # Include adversarial negative mutations, not only a happy-path scan.
    mutants=[
        (".github/workflows/auto-ia-rc03-convergence.yml",
         "ref: "+P12_SHA,"ref: main","mutable_P12_checkout"),
        ("tools/auto_ia/convergence/check_convergence_04.php",
         "auto_ia_lab.receive_signed_no_ai","auto_ia_lab.receive_no_ai",
         "unsigned_ingress"),
        ("tools/auto_ia/pg/001_auto_ia_pg_lab.sql",
         "FOR UPDATE","NO_LOCK","missing_budget_lock"),
        ("tools/auto_ia/convergence/check_convergence_05_restore.sql",
         "LAB_DUPLICATE_NO_DELIVERY","LAB_RECEIVED_NO_DELIVERY","duplicate_reprocessing"),
    ]
    for filename,before,after,label in mutants:
        if filename not in contents or before not in contents[filename]:
            problems.append(label+":mutation_anchor_missing")
            continue
        changed=dict(contents)
        changed[filename]=changed[filename].replace(before,after,1)
        if not audit_sources(changed):
            problems.append(label+":negative_mutation_not_detected")
    code=dict(contents)
    code["tools/auto_ia/convergence/check_convergence_04.php"]+="\ncurl_exec('https://bad.example');\n"
    if not audit_sources(code):
        problems.append("network_call_mutation_not_detected")
    report={
        "schema":"AUTO_IA_RC03_RELEASE_REVIEW_V1",
        "source_p12_sha":P12_SHA,
        "release_decision":"NO_GO",
        "security_review_complete":False,
        "iam_operational":False,
        "n8n_installed":False,
        "provider_api_calls":0,
        "credits_debited":0,
        "vps_changed":False,
        "merges_executed":0,
        "static_check":"PASS" if not problems else "FAIL",
        "problems":problems,
    }
    print(json.dumps(report,ensure_ascii=False,sort_keys=True))
    if problems:
        raise SystemExit(1)

if __name__=="__main__":
    main()
