#!/usr/bin/env python3
"""AIA-P50: subprocess contract and fail-closed behavior for offline CLI."""

from __future__ import annotations
import copy
import json
import subprocess
from pathlib import Path

CLI=Path(__file__).resolve().parent/"preview_noai_automation.php"

def canonical(value:object)->str:
    return json.dumps(value,ensure_ascii=False,separators=(",",":"))

def invoke(option:str,payload:str|None=None)->tuple[int,dict]:
    call=subprocess.run(
        ["php",str(CLI),option],
        input=payload,text=True,capture_output=True,timeout=8,check=False
    )
    assert not call.stderr, f"CLI must not leak stderr: {call.stderr[:200]}"
    response=json.loads(call.stdout)
    assert response["schema"]=="AIA_RULE_PREVIEW_CLI_V1"
    assert response["operational_authorization"] is False
    assert response["ai_api_calls"]==0
    assert response["crm_tasks_created"]==0
    assert response["messages_sent"]==0
    return call.returncode,response

def input_fixture()->dict:
    return {
        "command":{
            "schema":"AIA_DETERMINISTIC_COMMAND_V1",
            "operation":"funnel.calculate",
            "tenant_ref":"SYNTHETIC_TENANT_A",
            "request_ref":"SYNTHETIC_P50_TEST",
            "weekly_visits":120,
            "lead_rate_bp":1000,
            "client_rate_bp":2000,
        },
        "ruleset":{
            "schema":"AIA_RULESET_V1",
            "tenant_ref":"SYNTHETIC_TENANT_A",
            "ruleset_ref":"SYNTHETIC_P50_TEST_RULESET",
            "revision":1,
            "rules":[
                {
                    "rule_ref":"SYNTHETIC_P50_TEST_A",
                    "metric":"projected_leads",
                    "operator":"gte",
                    "threshold":12,
                    "draft_kind":"HUMAN_REVIEW_DRAFT",
                },
                {
                    "rule_ref":"SYNTHETIC_P50_TEST_B",
                    "metric":"projected_customers",
                    "operator":"gte",
                    "threshold":2,
                    "draft_kind":"CRM_TASK_DRAFT",
                },
            ],
        },
    }

def main()->None:
    checks=0
    code,result=invoke("--fixture")
    assert code==0 and result["decision"]=="NO_GO"
    assert result["source"]=="SYNTHETIC_OFFLINE_ONLY"
    assert result["preview"]["matched_rules"]==2
    assert result["preview"]["proposals"][0]["execution"]=="NOT_EXECUTED"
    checks+=1

    original=input_fixture()
    code,base=invoke("--stdin",canonical(original))
    assert code==0 and base["preview"]["matched_rules"]==2
    assert base["preview"]["tenant_ref"]=="SYNTHETIC_TENANT_A"
    assert base["preview"]["decision"]=="NO_AI"
    assert base["preview"]["status"]=="LAB_PROPOSALS_ONLY"
    assert base["preview"]["commercial_credits_debited"]==0
    checks+=1
    assert invoke("--stdin",canonical(original))==(code,base)
    checks+=1
    assert invoke("--stdin",canonical(original)+"\n")==(code,base)
    checks+=1

    negative=[
        "{}",
        "[]",
        "not json",
        '{"command":{}}',
        canonical(original).replace(',"ruleset":',',"extra":true,"ruleset":'),
        canonical(original).replace('"command":{','"command":{},"command":{',1),
        " "+canonical(original),
        canonical(original).replace('"weekly_visits":120','"weekly_visits":"120"'),
        canonical(original).replace('"operation":"funnel.calculate"',
                                   '"operation":"crm.lead.write"'),
        canonical(original).replace('"draft_kind":"CRM_TASK_DRAFT"',
                                   '"draft_kind":"EXECUTE_REAL_CRM_WRITE"'),
        canonical(original).replace('"tenant_ref":"SYNTHETIC_TENANT_A"',
                                   '"tenant_ref":"SYNTHETIC_TENANT_B"',1),
        "A"*8193,
    ]
    for index,bad in enumerate(negative):
        exit_code,out=invoke("--stdin",bad)
        assert exit_code==2 and out["decision"]=="DENY",index
        assert "preview" not in out,index
        checks+=1

    secret=copy.deepcopy(original)
    secret["secrets"]="SYNTHETIC_PRIVATE_CREDENTIAL_SHOULD_NEVER_ECHO"
    exit_code,out=invoke("--stdin",canonical(secret))
    assert exit_code==2 and "SYNTHETIC_PRIVATE_CREDENTIAL_SHOULD_NEVER_ECHO" not in canonical(out)
    checks+=1

    exit_code,out=invoke("--unexpected")
    assert exit_code==2 and out["decision"]=="DENY"
    checks+=1

    # A changed rule below threshold must result in zero proposals, never
    # an implicit task, notification or external action.
    zero=copy.deepcopy(original)
    zero["ruleset"]["rules"]=[
        {
            "rule_ref":"SYNTHETIC_P50_TEST_ZERO",
            "metric":"projected_customers",
            "operator":"gte",
            "threshold":3,
            "draft_kind":"HUMAN_REVIEW_DRAFT",
        }
    ]
    code,out=invoke("--stdin",canonical(zero))
    assert code==0 and out["preview"]["matched_rules"]==0
    assert out["preview"]["proposals"]==[]
    checks+=1

    print(
        "AIA_P50_OFFLINE_CLI_PASS "
        f"checks={checks} fixture=1 stdin_canonical=1 "
        "duplicates_denied=1 redacted_errors=1 no_network=1 NO_GO"
    )

if __name__=="__main__":
    main()
