#!/usr/bin/env python3
"""P50 offline CLI/JSON schema smoke test; no network or customer data."""
from __future__ import annotations

import json
import subprocess
from pathlib import Path

ROOT=Path(__file__).resolve().parents[1]
CLI=ROOT/"tools/preview_growth_projection.php"
SCHEMA=ROOT/"contracts/aia-growth-projection-v1.schema.json"

def run(*args:str)->subprocess.CompletedProcess[str]:
    return subprocess.run(
        ["php",str(CLI),*args],
        text=True,capture_output=True,timeout=10,check=False
    )

def main()->None:
    schema=json.loads(SCHEMA.read_text(encoding="utf8"))
    assert schema["$schema"]=="https://json-schema.org/draft/2020-12/schema"
    branches=schema["oneOf"]
    assert len(branches)==2
    assert {b["properties"]["mode"]["const"] for b in branches}=={
        "n8n_weekly","comercial_monthly"
    }
    for b in branches:
        assert b["additionalProperties"] is False
        assert set(b["required"])==set(b["properties"])
        assert b["properties"]["schema"]["const"]=="AIA_GROWTH_PROJECTION_COMMAND_V1"
        assert b["properties"]["tenant_ref"]["pattern"].startswith("^SYNTHETIC_")
        assert b["properties"]["request_ref"]["pattern"].startswith("^SYNTHETIC_")

    cases=[
        (("n8n_weekly","120","1000","2000"),"week",120,12,2),
        (("comercial_monthly","120","conservative"),"illustrative_4_week_month",480,10,1),
        (("comercial_monthly","120","reference"),"illustrative_4_week_month",480,19,4),
        (("comercial_monthly","120","optimistic"),"illustrative_4_week_month",480,29,9),
    ]
    checks=0
    for args,period,visits,leads,customers in cases:
        p=run(*args)
        assert p.returncode==0, (args,p.stderr)
        data=json.loads(p.stdout)
        assert data["period"]==period
        assert (data["visits"],data["leads"],data["customers"])==(
            visits,leads,customers
        )
        assert data["decision"]=="NO_AI"
        assert data["status"]=="LAB_PREVIEW_ONLY"
        assert data["operational_authorization"] is False
        assert data["real_results_guaranteed"] is False
        assert data["actual_performance_data"] is False
        assert data["ai_api_calls"]==data["ai_tokens"]==data["commercial_credits_debited"]==0
        assert data["tenant_ref"]=="SYNTHETIC_TENANT_DEMO"
        checks+=1

    invalid=[
        ("n8n_weekly","120","1000"),
        ("n8n_weekly","-1","1000","2000"),
        ("n8n_weekly","120","10001","2000"),
        ("n8n_weekly","120","1000","20000"),
        ("n8n_weekly","1.5","1000","2000"),
        ("n8n_weekly","999999999","1000","2000"),
        ("comercial_monthly","100001","reference"),
        ("comercial_monthly","120","__proto__"),
        ("comercial_monthly","120","reference","1000"),
        ("not_reviewed","120","reference"),
    ]
    for args in invalid:
        p=run(*args)
        assert p.returncode==2, (args,p.returncode,p.stdout,p.stderr)
        assert p.stdout=="", (args,"fail-closed should not emit JSON")
        checks+=1

    print(
        f"AIA_P50_CLI_CONTRACT_PASS checks={checks} "
        "distinct_periods=1 closed_schema=1 provider_calls=0 credits=0 NO_GO"
    )

if __name__=="__main__":
    main()
