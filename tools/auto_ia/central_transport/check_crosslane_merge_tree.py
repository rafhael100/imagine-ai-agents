#!/usr/bin/env python3
"""CENTRAL-AUTO-P18: read-only merge compatibility of *pinned* review snapshots.

No merge commit, git checkout, deploy, push, secrets, paid services or host
operations. The computed git merge-tree object exists only in the disposable
GitHub Actions runner, not on GitHub. A clean tree is NOT release approval.
"""
from __future__ import annotations
import json
import os
import re
import subprocess
import sys

REPO="rafhael100/imagine-ai-agents"
BRANCHES={
    "central_p17": (
      "feat/central-auto-p17-operator-cert-rotation-2026-11-16",
      "f558e94abd11c802ab7d59321b9aae9f8bd74b2d",
    ),
    "agents_p60": (
      "review/aia-p60-efficient-ci-gates-2026-11-16",
      "1cb27f71478d395402272d10cd9bdf39d44e771f",
    ),
    "identity_p4": (
      "integration/central-auto-identity-private-runtime-2026-11-16",
      "5f96750619e26bb860486a9f43bc83805a2e0ef4",
    ),
    "main": (
      "main",
      "ec305688528fe35e9a9dc2d3b1bda1255cbd074a",
    ),
}
HEX=re.compile(r"^[0-9a-f]{40}$")

def run(*args:str,allow_conflict:bool=False)->subprocess.CompletedProcess:
    p=subprocess.run(["git",*args],capture_output=True,text=True,
                     timeout=90,check=False)
    if p.returncode and not (allow_conflict and p.returncode==1):
        raise RuntimeError("GIT_QUERY_FAILED_"+args[0]+"_rc_"+str(p.returncode))
    return p

def validate_pins()->dict[str,bool]:
    stale={}
    for name,(branch,sha) in BRANCHES.items():
        if not HEX.fullmatch(sha):raise ValueError("UNEXPECTED_PIN")
        # Checkout fetch-depth:0 fetches branch refs. A parallel stream
        # can move during this job: preserve the immutable pinned proof,
        # but mark it stale instead of discarding the merge-tree analysis.
        actual=run("rev-parse",f"refs/remotes/origin/{branch}").stdout.strip()
        stale[name]=(actual!=sha)
        resolved=run("rev-parse",f"{sha}^{{commit}}").stdout.strip()
        if resolved!=sha:
            raise ValueError("NOT_COMMIT_"+name)
    return stale

def commit_count(base:str,head:str)->int:
    return int(run("rev-list","--count",f"{base}..{head}").stdout.strip())

def touched_files(merge_base:str,head:str)->set[str]:
    out=run("diff","--name-only",merge_base,head).stdout
    return set(out.splitlines())

def merge_status(base:str,head:str)->dict:
    merge_base=run("merge-base",base,head).stdout.strip()
    ahead=commit_count(merge_base,head)
    behind=commit_count(merge_base,base)
    touched_a=touched_files(merge_base,base)
    touched_b=touched_files(merge_base,head)
    overlap=sorted(touched_a&touched_b)
    # --write-tree computes the merge in Git's object database only;
    # it does not update HEAD, the worktree, any branch, or the remote.
    p=run("merge-tree","--write-tree","--name-only",base,head,
          allow_conflict=True)
    return {
        "outcome":"CLEAN_SYNTHETIC_MERGE_TREE" if p.returncode==0 else "CONFLICT_NO_GO",
        "base_unique_commits":behind,
        "peer_unique_commits":ahead,
        "base_touched_files":len(touched_a),
        "peer_touched_files":len(touched_b),
        "both_touched_count":len(overlap),
        "overlapping_paths_sample":overlap[:15],
        "merged_tree_object_created_only_in_runner":p.returncode==0,
        "conflict_trace_sample":(
            [line[:160] for line in p.stdout.splitlines()[1:16] if line.strip()]
            if p.returncode else []
        ),
    }

def evaluate()->dict:
    stale=validate_pins()
    central=BRANCHES["central_p17"][1]
    main=BRANCHES["main"][1]
    result={
      "schema":"CENTRAL_AUTO_P18_CROSSLANE_V1",
      "status":"NO_GO_OPERATIONAL",
      "scope":"GIT_HISTORY_ONLY",
      "main_baseline":main,
      "central_p17_sha":central,
      "central_commits_not_in_main":commit_count(main,central),
      "central_main_diff_too_large_for_unreviewed_merge":commit_count(main,central)>100,
      "branch_snapshots_immutable":True,
      "branch_moved_since_snapshot":stale,
      "current_branch_integration_attested":not any(stale.values()),
      "review_merges":{},
      "merge_commit_created":False,
      "github_branch_updated":False,
      "vps_modified":False,
      "paid_ai_used":False,
      "approved_for_release":False,
      "pending":["INDEPENDENT_DIFF_REVIEW","FULL_GATES_RECONCILIATION",
                 "HUMAN_STAGING_AUTHORIZATION","REAL_VPS_INVENTORY",
                 "REAL_MFA_IDP","REAL_S2S_IAM","RESTORE_BACKUP_EXTERNAL"]
    }
    for target in ("agents_p60","identity_p4"):
        result["review_merges"][target]=merge_status(central,BRANCHES[target][1])
    return result

if __name__=="__main__":
    if (os.environ.get("GITHUB_ACTIONS")!="true" or
        os.environ.get("GITHUB_REPOSITORY")!=REPO):
        print("CENTRAL_AUTO_P18_DENIED_NON_CI",file=sys.stderr)
        raise SystemExit(1)
    try:
        report=evaluate()
        print(json.dumps(report,sort_keys=True))
        if any(x["outcome"]!="CLEAN_SYNTHETIC_MERGE_TREE"
               for x in report["review_merges"].values()):
            print("CENTRAL_AUTO_P18_MERGE_BLOCKED NO_GO",file=sys.stderr)
            raise SystemExit(1)
        print("CENTRAL_AUTO_P18_MERGE_TREE_PASS NO_GO no_push=1 no_deploy=1")
    except (ValueError,RuntimeError,subprocess.TimeoutExpired) as exc:
        print("CENTRAL_AUTO_P18_DENIED_"+str(exc),file=sys.stderr)
        raise SystemExit(1)
