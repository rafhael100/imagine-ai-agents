#!/usr/bin/env python3
"""AUTO-IA-RC04: immutable evidence pins for proven n8n offline runtime.

Read-only review of critical Git blobs. A successful audit is ALWAYS NO_GO
for production; real IAM, provider costs, n8n server installation remain absent.
"""
import hashlib
import json
from pathlib import Path

ROOT=Path(__file__).resolve().parents[2]
PINNED={
 "infra/n8n-private/check_ephemeral_n8n_runtime.sh":"70187888a37e3ef6d787725cb8bc689b9d41f0d1",
 "tools/auto_ia/demo/verify_n8n_execution.py":"f94ee946ff78c09769688b2f2e98250aafee2086",
 "tools/auto_ia/demo/check_n8n_roundtrip.py":"b0ebf3eada62bda44e41659086d1d3e349279ba6",
 "tools/auto_ia/policy/check_n8n_zero_ai.py":"71f693890575625da60c802d54dae2d4dba59a95",
 "tools/auto_ia/policy/approved_no_ai_workflows.json":"95e2a42bbea21101fcd5e7987ef57ea4bc63ecc1",
 "tools/auto_ia/demo/n8n-noai-demo.workflow.json":"513359b15149a74ef534c9ba1939e3e37767c6bf",
 "tools/auto_ia/convergence/check_rc03_release.py":"10697c04cdc42cc46ab5e4f62b8d996f501a96f5"
}

def hash_git_blob(raw:bytes)->str:
    return hashlib.sha1(b"blob "+str(len(raw)).encode()+b"\x00"+raw).hexdigest()

def check(contents):
    problems=[]
    for path,pin in PINNED.items():
        value=contents.get(path)
        if value is None or hash_git_blob(value)!=pin:
            problems.append("PIN_FAILED:"+path)
    return problems

def source():
    contents={}
    for path in PINNED:
        p=ROOT/path
        if p.is_file() and not p.is_symlink():
            contents[path]=p.read_bytes()
    return contents

def self_test(contents):
    for path in (
       "tools/auto_ia/demo/n8n-noai-demo.workflow.json",
       "tools/auto_ia/demo/verify_n8n_execution.py",
       "infra/n8n-private/check_ephemeral_n8n_runtime.sh"
    ):
        changed=dict(contents)
        changed[path]+=b"\n# unsafe unreviewed modification\n"
        if not check(changed):
            raise AssertionError("MUTATION_ACCEPTED:"+path)
    changed=dict(contents)
    changed.pop("tools/auto_ia/policy/check_n8n_zero_ai.py",None)
    if not check(changed):
        raise AssertionError("MISSING_POLICY_ACCEPTED")
    return 4

def main():
    contents=source()
    problems=check(contents)
    if not problems:
        n=self_test(contents)
    else:
        n=0
    doc=(ROOT/"docs/AUTO-IA-RC04-REVIEW-DOSSIER.md")
    if not doc.is_file() or "NO-GO" not in doc.read_text(encoding="utf-8"):
        problems.append("RELEASE_DOSSIER_MISSING")
    result={
      "schema":"AUTO_IA_RC04_REVIEW_V1",
      "source_n8n_v2":"2.47.7",
      "verified_runtime_github_run":"43870748304",
      "approved_files_exact":len(PINNED),
      "adversarial_mutations_denied":n,
      "static_integrity":"PASS" if not problems else "FAIL",
      "n8n_vps_installed":False,
      "external_provider_enabled":False,
      "live_customer_data":False,
      "credits_debited":0,
      "production_decision":"NO_GO",
      "blockers":[
        "REAL_S2S_IAM","REAL_SECRETS","VPS_PREFLIGHT","N8N_LICENSE_CHECK",
        "OPERATIONAL_BACKUP_RESTORE","APPSEC_DBA_LGPD_REVIEW",
        "CROSS_REPO_RELEASE_GATES","HUMAN_GO"
      ],
      "problems":problems,
    }
    print(json.dumps(result,ensure_ascii=False,sort_keys=True))
    if problems:
        raise SystemExit(1)

if __name__=="__main__":
    main()
