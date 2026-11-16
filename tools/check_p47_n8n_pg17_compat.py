#!/usr/bin/env python3
"""P47: immutable RC07 source + Agents owner-grant lineage compatibility gate.

An official n8n/PG17 runtime test runs separately in CI. This verifier does
not connect the services, authorize a tenant, transfer credentials or issue a
billable request. It is NOT a release approval or operational S2S test.
"""
from __future__ import annotations

import copy
import json
import runpy
from pathlib import Path

ROOT=Path(__file__).resolve().parents[1]
RC07=ROOT/"_central_auto_rc07_pinned"
FLOW=RC07/"infra/n8n-private/workflows/native-funnel-noai.workflow.json"
CHECKER=RC07/"infra/n8n-private/check_native_noai_flow.py"
OWNER=ROOT/"tools/labs/PgOwnerDecisionLab.php"
BOUND=ROOT/"tools/labs/PgOwnerBoundSignedDispatchLab.php"
AUDIT=ROOT/"tools/labs/PgTransportOwnerLineageAuditLab.php"

def must_include(path:Path,*requirements:str)->None:
    if not path.is_file() or path.is_symlink():
        raise AssertionError("P47_UNTRUSTED_MISSING_FILE:"+str(path))
    body=path.read_text(encoding="utf-8")
    for requirement in requirements:
        if requirement not in body:
            raise AssertionError("P47_CONTRACT_DIVERGENCE:"+requirement)

def main()->None:
    must_include(
        OWNER,"ALLOW_NO_AI_LAB","SYNTHETIC_CRM_POLICY_OWNER",
        "verifyAndConsumeInsideTransaction","consumed_owner_grants"
    )
    must_include(
        BOUND,"PgBoundOutboxLeaseLab","PgAtomicSignedEvidenceAckLab",
        "true","NO_GO"
    )
    must_include(
        AUDIT,"REPEATABLE READ, READ ONLY",
        "P42_EVENT_WITHOUT_OWNER_GRANT","MISSING_ORIGINAL_MTLS_ADMISSION",
        "OWNER_HISTORICAL_SIGNATURE_INVALID"
    )
    must_include(
        RC07/"infra/n8n-private/docker-compose.yml",
        "internal: true","N8N_BLOCK_ENV_ACCESS_IN_NODE",
        "NODES_EXCLUDE","expose:","no-new-privileges:true"
    )
    must_include(
        RC07/"infra/n8n-private/run_native_noai_pg.sh",
        "n8n import:workflow","n8n export:workflow",
        "n8n execute","CENTRAL_AUTO_P9_NATIVE_N8N_PASS"
    )

    module=runpy.run_path(str(CHECKER))
    source=module["parse_json"](FLOW)
    module["source_ok"](source)
    assert source["active"] is False
    assert [n["type"] for n in source["nodes"]]==[
        "n8n-nodes-base.manualTrigger",
        "n8n-nodes-base.set",
        "n8n-nodes-base.set"
    ]
    exp=module["EXPECTED"]
    assert exp["schema"]=="AUTO_IA_NATIVE_NO_AI_V1"
    assert exp["tenant_ref"]=="SYNTHETIC_TENANT_DEMO"
    assert (exp["visits"],exp["projected_leads"],exp["projected_customers"])==(120,12,2)
    assert exp["ai_api_calls"]==exp["ai_tokens"]==exp["commercial_credits_debited"]==0
    assert exp["operational_authorization"] is False

    failed=0
    for mutation in ["activate","inject_api"]:
        bad=copy.deepcopy(source)
        if mutation=="activate":
            bad["active"]=True
        else:
            bad["nodes"][1]["type"]="n8n-nodes-base.httpRequest"
        try:
            module["source_ok"](bad)
        except module["Denied"]:
            failed+=1
        else:
            raise AssertionError("P47_MUTATION_WAS_NOT_REJECTED:"+mutation)
    assert failed==2
    print(json.dumps({
        "schema":"AIA_P47_OFFICIAL_N8N_PINNED_COMPAT_V1",
        "rc07_reviewed_sha":"cc55e568bc2994574b85b8b48969ac85fe2ec543",
        "n8n_workflow":"NATIVE_MANUAL_ONLY",
        "postgres_n8n_target_version":17,
        "postgres_agents_test_version":16,
        "expected_visits":120,"expected_leads":12,"expected_customers":2,
        "source_mutations_rejected":failed,
        "ai_calls":0,"credits_debited":0,
        "crm_owner_identity_verified_operationally":False,
        "actual_agents_n8n_service_exchange":False,
        "production_decision":"NO_GO",
    },ensure_ascii=False,sort_keys=True))
if __name__=="__main__":
    main()
