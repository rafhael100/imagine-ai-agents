#!/usr/bin/env python3
"""P33: compare two reviewed source candidates without merging or running n8n.

The n8n runtime proof cited in RC04 remains a separate GitHub Actions run.
This test consumes only the exact pinned source and offline synthetic verifier.
"""
from __future__ import annotations

import copy
import hashlib
import json
import runpy
from pathlib import Path

ROOT=Path(__file__).resolve().parents[1]
CONV=ROOT/"_auto_ia_pinned_source"
RC04=ROOT/"_auto_ia_rc04_pinned"
ROUTER="tools/auto_ia/AutoIaRouter.php"
POLICY="tools/auto_ia/policy/approved_no_ai_workflows.json"
WORKFLOW="tools/auto_ia/demo/n8n-noai-demo.workflow.json"
VERIFIER="tools/auto_ia/demo/verify_n8n_execution.py"

def git_blob(raw:bytes)->str:
    return hashlib.sha1(b"blob "+str(len(raw)).encode()+b"\x00"+raw).hexdigest()

def assert_static() -> tuple[dict,dict]:
    original=(CONV/ROUTER).read_bytes()
    reviewed=(RC04/ROUTER).read_bytes()
    assert original==reviewed, "ROUTING_POLICY_DIVERGED_BETWEEN_CANDIDATES"
    assert git_blob(original)=="d5af1775431df0430a44df5b2c453b1dfa329442"
    policy=json.loads((RC04/POLICY).read_text(encoding="utf-8"))
    workflow=json.loads((RC04/WORKFLOW).read_text(encoding="utf-8"))
    assert policy["schema"]=="AUTO_IA_N8N_NO_AI_POLICY_V1"
    assert policy["mode"]=="DENY_UNKNOWN_WORKFLOWS"
    assert policy["operational_authorization"] is False
    assert type(policy["api_provider_calls_allowed"]) is int
    assert policy["api_provider_calls_allowed"]==0
    assert policy["commercial_credit_debit_allowed"] is False
    allowed=policy["workflows"]
    assert set(allowed)=={WORKFLOW}, "UNREVIEWED_WORKFLOW_ADDED"
    entry=allowed[WORKFLOW]
    assert entry["classification"]=="NO_AI_MANUAL_ONLY"
    assert entry["active"] is False
    assert entry["git_blob_sha1"]==git_blob((RC04/WORKFLOW).read_bytes())
    assert workflow["active"] is False
    assert len(workflow["nodes"])==2
    assert [x["type"] for x in workflow["nodes"]]==[
        "n8n-nodes-base.manualTrigger","n8n-nodes-base.code"
    ]
    assert all("credentials" not in n for n in workflow["nodes"])
    code=workflow["nodes"][1]["parameters"]["jsCode"]
    assert "operation: 'funnel.calculate'" in code
    assert "commercial_credits_debited: 0" in code
    assert "ai_api_calls: 0" in code
    return policy,workflow

def verify_offline() -> tuple[dict,int]:
    # runpy only reads the verifier functions; does NOT start n8n, a network,
    # docker, browser, database, paid provider or production process.
    namespace=runpy.run_path(str(RC04/VERIFIER))
    verified=namespace["verify"]
    raw=namespace["synthetic_record"]()
    report=verified(raw)
    assert report=={
        "schema":"AUTO_IA_N8N_RUNTIME_PROOF_V1",
        "source":"OFFICIAL_N8N_RUNTIME",
        "visits":120,"leads":12,"customers":2,
        "ai_provider_calls":0,"ai_tokens":0,
        "commercial_credits_debited":0,
        "production_decision":"NO_GO",
    }, "EXTERNAL_PROOF_CONTRACT_DIVERGED"
    errors=0
    for field,bad in [
        ("ai_api_calls",1),("ai_tokens",1),("commercial_credits_debited",1),
        ("operational_authorization",True),
    ]:
        tampered=copy.deepcopy(raw)
        code=namespace["CALCULATE"]
        tampered["data"]["resultData"]["runData"][code][0]["data"]["main"][0][0]["json"][field]=bad
        try:
            verified(tampered)
        except namespace["Denied"]:
            errors+=1
        else:
            raise AssertionError("RC04_FAILED_TO_REJECT_"+field)
    return report,errors

def main() -> None:
    policy,workflow=assert_static()
    proof,denied=verify_offline()
    assert denied==4
    assert policy["commercial_credit_debit_allowed"] is False
    assert workflow["active"] is False
    assert proof["ai_provider_calls"]==proof["ai_tokens"]==proof["commercial_credits_debited"]==0
    print(json.dumps({
        "schema":"AIA_P33_TWO_PIN_CONTRACT_V1",
        "source":"two independent immutable candidate checkouts",
        "candidate_router_blob":"d5af1775431df0430a44df5b2c453b1dfa329442",
        "n8n_review_branch":"AUTO_IA_RC04",
        "n8n_runtime_previously_verified":True,
        "n8n_rerun_by_this_test":False,
        "workflow":"NO_AI_MANUAL_ONLY",
        "expected_visits":proof["visits"],
        "expected_leads":proof["leads"],
        "expected_customers":proof["customers"],
        "negative_receipts_denied":denied,
        "ai_provider_calls":0,
        "commercial_credits_debited":0,
        "real_iam_enabled":False,
        "production_decision":"NO_GO",
    },ensure_ascii=False,sort_keys=True))
if __name__=="__main__":
    main()
