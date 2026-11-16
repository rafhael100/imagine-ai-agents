#!/usr/bin/env python3
"""AIA RC63: exact-SHA, read-only CRM / Portal / Central / Agents compatibility.

Never runs a real service, uses secrets, updates a remote branch or creates
a merge commit. Requires independent checkouts pinned by SHA.
"""
from __future__ import annotations

import json
import os
import re
import subprocess
from pathlib import Path

ROOT=Path(__file__).resolve().parents[1]
CENTRAL=ROOT/"_central_p20_pinned"
CRM=ROOT/"_crm_p19_pinned"
PORTAL=ROOT/"_portal_p20_pinned"
PINS={
    "agents_integrated_base":"2ea5df5c7691d8594a129922363454af7072f497",
    "central_p20":"3cf31b78f745d539425b46b754edbe3c48e9d088",
    "crm_p19":"cca5055608acff8c767b888b6322df77fe966558",
    "portal_p20":"12d08d2e2af36b8011bc520d55c5e9cdbcccc108",
}

def git(cwd:Path,*args:str,allow_conflicts:bool=False)->subprocess.CompletedProcess[str]:
    r=subprocess.run(
        ["git",*args],cwd=cwd,capture_output=True,text=True,timeout=35,
        check=False
    )
    if r.returncode!=0 and not (allow_conflicts and r.returncode==1):
        raise RuntimeError("RC63_GIT_UNAVAILABLE:"+str(args[0])+":"+str(r.returncode))
    return r

def source(root:Path,path:str,*needles:str)->str:
    f=root/path
    if not f.is_file() or f.is_symlink():
        raise AssertionError("RC63_REQUIRED_FILE_MISSING:"+path)
    body=f.read_text(encoding="utf-8")
    for token in needles:
        if token not in body:
            raise AssertionError("RC63_CONTRACT_CHANGED:"+path+":"+token)
    return body

def verify_contracts()->dict:
    receiver=source(
        CENTRAL,"runtime/central-auto/index.php",
        "CRM_LEAD_EVENT_V1","lead.created",
        "AUTO_IA_ACK_V1","'DISABLED'","AGENTS_PRIVATE_S2S_PROFILE",
        "IMAGINE_MTLS_VERIFY"
    )
    grant=source(
        CENTRAL,"runtime/central-auto/receiver-grants.sql",
        "operation='lead.ingest'","FORCE ROW LEVEL SECURITY",
        "receive_company_signed_no_ai","LAB_DUPLICATE_NO_DELIVERY",
        "LAB_DENY_COMPANY_LINK"
    )
    sender=source(
        CRM,"app/Homologation/S2S/AgentsEventAdapter.php",
        "lead.ingest","CRM_LEAD_EVENT_V1",
        "https://agents-s2s:8443/private/events","'DISABLED'",
        "ACK_SIGNATURE","verify_peer_name","SENDER_DISABLED"
    )
    source(
        CRM,"infra/homologation/s2s/outbound-candidate.sql",
        "action='lead.created'","operation='lead.ingest'",
        "FORCE ROW LEVEL SECURITY","OUTBOUND_GRANT_DENIED"
    )
    portal=source(
        PORTAL,"apps/portal/docs/administration/CENTRAL-AUTO-P20-AUTHORITY.md",
        "MFA","IMAGINE_AUTOMATION_TRANSPORT_ENABLED","false","NO-GO"
    )
    plan=source(
        CENTRAL,"infra/n8n-private/homologation/PLAN-P20.md",
        "DRAFT / NO-GO","Dispatch n8n permanece desligado",
        PINS["crm_p19"],PINS["portal_p20"]
    )
    owner=source(
        ROOT,"tools/labs/PgOwnerDecisionLab.php",
        "AIA_OWNER_DECISION_V1","run.status.project",
        "ALLOW_NO_AI_LAB","verifyAndConsumeInsideTransaction",
        "SYNTHETIC_CRM_POLICY_OWNER"
    )
    funnel=source(
        ROOT,"src/Automation/DeterministicAutomationService.php",
        "funnel.calculate","NO_AI","operational_authorization",
        "LAB_PREVIEW_ONLY"
    )
    source(
        ROOT,"src/Automation/DeterministicRuleEngine.php",
        "LAB_PROPOSALS_ONLY","NOT_EXECUTED",
        "HUMAN_REVIEW_DRAFT","CRM_TASK_DRAFT"
    )
    if "run.status.project" in receiver or "funnel.calculate" in receiver:
        raise AssertionError("RC63_RECEIVER_SCOPE_EXPANDED")
    if "funnel.calculate" in grant or "run.status.project" in grant:
        raise AssertionError("RC63_LEAD_GRANT_CROSSES_OPERATIONS")
    if "lead.ingest" in owner or "lead.ingest" in funnel:
        raise AssertionError("RC63_RULE_CORE_REUSES_LEAD_GRANT")
    if "AUTO_IA_ACK_V1" not in sender:
        raise AssertionError("RC63_SENDER_ACK_DRIFT")
    if re.search("Dispatch n8n permanece (habilitado|ligado)",plan,re.I):
        raise AssertionError("RC63_P20_PRETENDS_LIVE_DISPATCH")
    return {
        "crm_transport_operation":"lead.ingest",
        "crm_source_event":"lead.created",
        "crm_ack":"AUTO_IA_ACK_V1",
        "agents_owner_operation":"run.status.project",
        "agents_deterministic_operation":"funnel.calculate",
        "portal_transport_enabled":False,
        "n8n_dispatch":"DISABLED",
        "cross_operation_grant":"NOT_PRESENT_NO_GO",
        "real_business_authority":"NOT_VERIFIED_NO_GO",
    }

def merge_preview()->dict:
    git(ROOT,"merge-base","--is-ancestor",
        PINS["agents_integrated_base"],"HEAD")
    # Local Git object transfer ONLY. No merge commit/worktree/remote write.
    git(ROOT,"fetch","--quiet","--no-tags",str(CENTRAL),PINS["central_p20"])
    current=git(ROOT,"rev-parse","HEAD").stdout.strip()
    common=git(ROOT,"merge-base",current,PINS["central_p20"]).stdout.strip()
    ours=set(git(ROOT,"diff","--name-only",common,current).stdout.splitlines())
    other=set(git(ROOT,"diff","--name-only",common,PINS["central_p20"]).stdout.splitlines())
    overlap=sorted(ours & other)
    preview=git(ROOT,"merge-tree","--write-tree","--name-only",
                current,PINS["central_p20"],allow_conflicts=True)
    conflicts=[] if preview.returncode==0 else [
        line.strip() for line in preview.stdout.splitlines()[1:]
        if line.strip() and not line.startswith(("Auto-merging","CONFLICT"))
    ][:100]
    return {
        "common_ancestor":common,
        "agents_changed_files":len(ours),
        "central_changed_files":len(other),
        "same_paths_changed_count":len(overlap),
        "overlap_paths":overlap[:100],
        "merge_tree_status":(
            "NO_TEXTUAL_CONFLICT_DETECTED_STILL_NO_GO" if preview.returncode==0
            else "MANUAL_CONFLICT_REVIEW_REQUIRED_NO_GO"
        ),
        "conflict_paths":conflicts,
        "branch_or_merge_commit_created":False,
    }

def main()->None:
    assert os.getenv("GITHUB_ACTIONS")=="true"
    assert os.getenv("GITHUB_REPOSITORY")=="rafhael100/imagine-ai-agents"
    for repo,key in ((CENTRAL,"central_p20"),(CRM,"crm_p19"),(PORTAL,"portal_p20")):
        if git(repo,"rev-parse","HEAD").stdout.strip()!=PINS[key]:
            raise AssertionError("RC63_PIN_INVALID:"+key)
    policy=verify_contracts()
    topology=merge_preview()
    report={
        "schema":"AIA_RC63_CROSSLANE_REVIEW_V1",
        "verified_pins":PINS,
        "separate_operation_contracts":policy,
        "git_dry_run":topology,
        "production_decision":"NO_GO",
        "real_transport_or_ai_calls":0,
        "automatic_merge":False,
    }
    print(json.dumps(report,ensure_ascii=False,sort_keys=True))
    summary=os.getenv("GITHUB_STEP_SUMMARY")
    if summary:
        with open(summary,"a",encoding="utf-8") as f:
            f.write("### AIA-RC63 — Crosslane read-only review\n\n")
            f.write("- CRM P19: lead.created to lead.ingest, n8n disabled\n")
            f.write("- Agents RC62: run.status.project / funnel.calculate, no inherited grant\n")
            f.write("- Portal P20: flag disabled, authority still synthetic\n")
            f.write("- Changed overlapping paths: "+str(len(topology["overlap_paths"]))+"\n")
            f.write("- Git merge-tree: "+topology["merge_tree_status"]+"\n")
            f.write("- Operational release: NO_GO; merge performed: false\n")
if __name__=="__main__":
    main()
