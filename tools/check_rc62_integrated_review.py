#!/usr/bin/env python3
"""AIA-RC62: verify a consolidated review train, NOT a release.

Requires full Git ancestry, immutable reviewed anchors, required subsystem
sources and explicit NO-GO operational governance. Does not use GitHub API,
read credentials, deploy, run a worker, connect to n8n or spend credits.
"""
from __future__ import annotations
import json
import os
from pathlib import Path
import subprocess

ROOT=Path(__file__).resolve().parents[1]
BASE="658a26b798c57ab4446eb146df064dbdc3111293"
P61="ab040500f62b041ab9e4a591bf16412bd5d0cdd4"
MANIFEST=ROOT/"docs/reviews/AIA-RC62-REVIEW-TRAIN.json"
REQUIRED=[
    ".github/workflows/ci-p14-convergence-gates.yml",
    "tools/labs/PgOwnerDecisionLab.php",
    "tools/labs/PgOwnerExecutionGuardLab.php",
    "tools/labs/PgTransportOwnerLineageAuditLab.php",
    "tools/check_p47_n8n_pg17_compat.py",
    "src/Automation/DeterministicAutomationService.php",
    "src/Automation/DeterministicRuleEngine.php",
    "tools/preview_noai_automation.php",
    "tools/labs/PgDeterministicDraftRegistryLab.php",
    "tools/labs/PgReadOnlyDraftAuditLab.php",
    "src/Automation/ReviewQueueReadModel.php",
    "examples/p54-review-queue-local.php",
    "tools/labs/PgScopedReviewQueueBridgeLab.php",
    "examples/p56-live-pg-review-local.php",
    "tools/labs/PgSignedDraftReviewLab.php",
    "tools/check_p58_serialized_review_rejection.php",
    "tools/labs/PgDraftAuthorProvenanceLab.php",
    "src/Automation/SignedReviewQueueReadModel.php",
    "tools/labs/PgHistoricalSignedReviewAuditLab.php"
]
GATES=[
    "crm_owner_real_identity",
    "portal_s2s_operation_scope",
    "business_tenant_access_control",
    "n8n_real_private_delivery_and_ack",
    "crm_api_live_approval",
    "consent_and_lgpd_retention",
    "vault_kms_key_custody",
    "leaseweb_backup_restore_rollback",
    "independent_security_review",
    "human_release_acceptance"
]

def git(*args:str)->str:
    p=subprocess.run(
        ["git",*args],cwd=ROOT,text=True,capture_output=True,check=False,
        timeout=15
    )
    if p.returncode:
        raise AssertionError("P62_GIT_ANCESTRY_MISSING:"+args[0])
    return p.stdout.strip()

def main()->None:
    assert os.getenv("GITHUB_ACTIONS")=="true"
    assert os.getenv("GITHUB_REPOSITORY")=="rafhael100/imagine-ai-agents"
    data=json.loads(MANIFEST.read_text(encoding="utf-8"))
    assert list(data)==[
        "schema","base_commit","reviewed_head_commit","increment_start",
        "increment_end","release_decision","runtime_effects",
        "release_gates","review_instructions"
    ],"P62_MANIFEST_CLOSED_FIELDS"
    assert data["schema"]=="AIA_RC62_REVIEW_TRAIN_V1"
    assert data["base_commit"]==BASE
    assert data["reviewed_head_commit"]==P61
    assert data["increment_start"]==14
    assert data["increment_end"]==61
    assert data["release_decision"]=="NO_GO"
    assert data["runtime_effects"]=={
        "production_deploy":False,
        "merge_to_main":False,
        "n8n_operational_delivery":False,
        "paid_ai_calls":0,
        "crm_customer_data_access":False,
        "commercial_credits_debited":0
    }, "P62_NO_OPERATIONAL_EFFECTS"
    gates=data["release_gates"]
    assert list(gates)==GATES
    assert all(v=="NOT_VERIFIED_NO_GO" for v in gates.values())
    assert data["review_instructions"]==(
        "DRAFT_ONLY_REVIEW_SECURITY_AND_INTEGRATION_NO_AUTO_MERGE"
    )
    git("merge-base","--is-ancestor",BASE,"HEAD")
    git("merge-base","--is-ancestor",P61,"HEAD")
    shas=git("rev-list",f"{BASE}..HEAD").splitlines()
    assert len(shas)>=229,"P62_REVIEW_TRAIN_UNEXPECTEDLY_SHORT"
    changed=set(git("diff","--name-only",BASE,"HEAD").splitlines())
    for path in REQUIRED:
        f=ROOT/path
        assert f.is_file() and not f.is_symlink(),("P62_MISSING_SOURCE",path)
        assert path in changed,("P62_SUBSYSTEM_NOT_IN_REVIEW_DIFF",path)
    for item in ["main",".env","vendor",".github/workflows/deploy.yml"]:
        assert item not in changed,("P62_UNEXPECTED_RELEASE_SIDE_EFFECT",item)
    assert "NO_GO" in (ROOT/"docs/reviews/AIA-P61-HISTORICAL-SIGNED-REVIEWS.md").read_text()
    assert "NO_GO" in (ROOT/"docs/reviews/AIA-P57-SIGNED-REVIEW-OBSERVATIONS.md").read_text()
    assert "NO_GO" in (ROOT/"docs/reviews/AIA-P47-OFFICIAL-N8N-PG17.md").read_text()
    print(json.dumps({
        "schema":"AIA_RC62_REVIEW_CHECK_RESULT_V1",
        "base":BASE,"includes_p61":True,
        "commits_after_base":len(shas),
        "files_since_base":len(changed),
        "verified_subsystem_files":len(REQUIRED),
        "operational_gates_in_no_go":len(GATES),
        "release_decision":"NO_GO",
        "merge_main":False,
        "production_deploy":False,
    },sort_keys=True))
if __name__=="__main__":
    main()
