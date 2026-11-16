#!/usr/bin/env python3
"""CENTRAL-AUTO P18B: read-only, ephemeral, reviewed resolution rehearsal.

Resolve ONLY a known, inspected four-file conflict in a disposable Git
object database, never update refs or main, then check combination with P60.
No merge commit is pushed and no production resource is touched.
"""
from __future__ import annotations
import json
import os
import re
import subprocess
import sys
import tarfile
import tempfile
from pathlib import Path

from check_crosslane_merge_tree import BRANCHES,validate_pins,run

CONFLICT_FILES=(
 "infra/n8n-private/check_compose_static.py",
 "infra/n8n-private/docker-compose.yml",
 "infra/n8n-private/readonly_inventory.py",
 "infra/n8n-private/test_readonly_inventory.py",
)
REQUIRED_IDENTITY_FILES=(
 ".github/workflows/central-auto-credential-lock.yml",
 "tools/auto_ia/pg/check_key_revocation_lock.php",
 "tools/auto_ia/pg/check_key_revocation_lock.sh",
)
REQUIRED_CENTRAL_FILES=(
 "infra/n8n-private/check_noai_egress_policy.py",
 "infra/n8n-private/ingress/check_operator_overlay.py",
 "infra/n8n-private/ingress/nginx-operator.conf",
 "infra/n8n-private/ingress/run_operator_mtls_lab.sh",
)
SHA=re.compile(r"^[0-9a-f]{40}$")

def git(*args,allow_conflict=False,env=None):
    p=subprocess.run(["git",*args],capture_output=True,text=True,
                     timeout=80,check=False,env=env)
    if p.returncode and not (allow_conflict and p.returncode==1):
        raise RuntimeError("GIT_OP_FAILED_"+args[0]+"_"+str(p.returncode))
    return p

def object_id(tree_or_commit:str,path:str)->str:
    return git("rev-parse",f"{tree_or_commit}:{path}").stdout.strip()

def rehearse()->dict:
    stale=validate_pins()
    central=BRANCHES["central_p17"][1]
    identity=BRANCHES["identity_p4"][1]
    agents=BRANCHES["agents_p60"][1]
    # Inspect the exact initial conflict paths; deny unexpected conflicts
    # before any strategy resolution is attempted.
    first=git("merge-tree","--write-tree","--name-only",central,identity,
              allow_conflict=True)
    if first.returncode!=1:
        raise RuntimeError("EXPECTED_KNOWN_CONFLICTS_NOT_PRESENT")
    lines=set(first.stdout.splitlines())
    actual=set(CONFLICT_FILES).intersection(lines)
    if actual!=set(CONFLICT_FILES):
        raise RuntimeError("KNOWN_CONFLICT_SET_CHANGED")
    # Merge strategy -X ours chooses P17 in conflicting hunks; verify the
    # *full blobs* match original P17 for the four safe-reviewed files.
    resolved=git("merge-tree","--write-tree","-X","ours",central,identity)
    candidate_tree=resolved.stdout.splitlines()[0].strip()
    if not SHA.fullmatch(candidate_tree):
        raise RuntimeError("RESOLVED_TREE_MISSING")
    for path in CONFLICT_FILES:
        if object_id(candidate_tree,path)!=object_id(central,path):
            raise RuntimeError("RESOLVED_FILE_DIFFERS_FROM_HARDENED_"+path)
    for path in (*REQUIRED_IDENTITY_FILES,*REQUIRED_CENTRAL_FILES):
        oid=object_id(candidate_tree,path)
        if not SHA.fullmatch(oid):
            raise RuntimeError("REQUIRED_FILE_MISSING_"+path)

    env=os.environ.copy()
    env.update(GIT_AUTHOR_NAME="CI Synthetic Review",
               GIT_AUTHOR_EMAIL="ci-review@example.invalid",
               GIT_COMMITTER_NAME="CI Synthetic Review",
               GIT_COMMITTER_EMAIL="ci-review@example.invalid")
    # This synthetic commit exists ONLY inside the runner's local .git objects.
    # HEAD and remote refs remain untouched.
    commit=git("commit-tree",candidate_tree,"-p",central,"-p",identity,
               env=env)
    ephemeral_sha=commit.stdout.strip()
    if not SHA.fullmatch(ephemeral_sha):
        raise RuntimeError("EPHEMERAL_COMMIT_MISSING")

    combined=git("merge-tree","--write-tree","--name-only",
                 ephemeral_sha,agents,allow_conflict=True)
    if combined.returncode!=0:
        return {
          "result":"FURTHER_CONFLICT_NO_GO",
          "first_4_conflicts_resolved_with_reviewed_central_files":True,
          "p60_combination_clean":False,
          "conflict_trace_sample":combined.stdout.splitlines()[1:12],
          "release_authorized":False
        }
    final_tree=combined.stdout.splitlines()[0].strip()
    if not SHA.fullmatch(final_tree):
        raise RuntimeError("THREE_LANE_TREE_MISSING")
    return {
      "result":"THREE_LANE_SIMULATION_CLEAN_NO_GO",
      "first_4_conflicts_resolved_with_reviewed_central_files":True,
      "p60_combination_clean":True,
      "identity_files_preserved":len(REQUIRED_IDENTITY_FILES),
      "central_files_preserved":len(REQUIRED_CENTRAL_FILES),
      "snapshot_branches_moved":stale,
      "ephemeral_git_objects_only":True,
      "commit_created_on_remote":False,
      "vps_modified":False,
      "release_authorized":False,
      "tree_for_runner_only":final_tree,
    }

def smoke_merged_tree(tree_sha:str)->dict:
    """Run small, deterministic offline tests against the *combined tree*."""
    import shutil
    if not SHA.fullmatch(tree_sha):
        raise RuntimeError("INVALID_SMOKE_TREE")
    checks=[
      ("inventory_v2",["python3","-m","unittest","discover",
                       "-s","infra/n8n-private","-p","test_readonly_inventory.py","-v"]),
      ("inventory_assessment",["python3","-m","unittest","discover",
                       "-s","infra/n8n-private","-p","test_assess_readonly_host.py","-v"]),
      ("noai_compose_policy",["python3","-m","unittest","discover",
                       "-s","infra/n8n-private","-p","test_noai_egress_policy.py","-v"]),
      ("operator_overlay_policy",["python3","-m","unittest","discover",
                       "-s","infra/n8n-private/ingress","-p","test_operator_overlay.py","-v"]),
      ("agents_p60_policy",["python3","tools/check_p60_ci_gates.py"]),
      ("identity_lock_bash_syntax",["bash","-n","tools/auto_ia/pg/check_key_revocation_lock.sh"]),
      ("operator_mtls_bash_syntax",["bash","-n","infra/n8n-private/ingress/run_operator_mtls_lab.sh"]),
      ("n8n_restore_bash_syntax",["bash","-n","infra/n8n-private/check_full_ephemeral_restore.sh"]),
    ]
    with tempfile.TemporaryDirectory(prefix="central-auto-p18b-") as folder:
        tar=Path(folder)/"snapshot.tar"
        src=Path(folder)/"repo"
        src.mkdir()
        with tar.open("wb") as out:
            p=subprocess.run(["git","archive","--format=tar",tree_sha],
                stdout=out,stderr=subprocess.PIPE,timeout=90)
        if p.returncode:
            raise RuntimeError("GIT_ARCHIVE_FAILED")
        with tarfile.open(tar,mode="r") as archive:
            archive.extractall(path=src,filter="data")
        report={}
        env=os.environ.copy()
        env["PYTHONPYCACHEPREFIX"]=str(Path(folder)/"pycache")
        for name,command in checks:
            p=subprocess.run(command,cwd=src,capture_output=True,text=True,
                             env=env,timeout=75)
            report[name]="PASS" if p.returncode==0 else "FAIL"
            if p.returncode:
                print("CENTRAL_AUTO_P18B_SMOKE_FAIL "+name+
                      " rc="+str(p.returncode),file=sys.stderr)
                raise RuntimeError("MERGED_TREE_SMOKE_FAILED_"+name)
        if shutil.which("php"):
            p=subprocess.run(["php","-l",
                "tools/auto_ia/pg/check_key_revocation_lock.php"],
                cwd=src,capture_output=True,text=True,timeout=20)
            report["identity_lock_php_syntax"]="PASS" if p.returncode==0 else "FAIL"
            if p.returncode:
                raise RuntimeError("MERGED_TREE_PHP_SYNTAX_INVALID")
        else:
            report["identity_lock_php_syntax"]="NOT_AVAILABLE_ON_RUNNER"
    return report

if __name__=="__main__":
    if (os.environ.get("GITHUB_ACTIONS")!="true" or
        os.environ.get("GITHUB_REPOSITORY")!="rafhael100/imagine-ai-agents"):
        print("CENTRAL_AUTO_P18B_DENIED_NOT_GITHUB_CI",file=sys.stderr)
        raise SystemExit(1)
    try:
        result=rehearse()
        print(json.dumps(result,sort_keys=True))
        if result["result"]!="THREE_LANE_SIMULATION_CLEAN_NO_GO":
            raise SystemExit(1)
        smoke=smoke_merged_tree(result["tree_for_runner_only"])
        print(json.dumps({"schema":"CENTRAL_AUTO_P18B_SMOKE_V1","tests":smoke,"release_authorized":False},sort_keys=True))
        print("CENTRAL_AUTO_P18B_THREE_LANE_REHEARSAL_PASS NO_GO review_only=1")
    except (RuntimeError,ValueError,subprocess.TimeoutExpired) as exc:
        print("CENTRAL_AUTO_P18B_DENIED_"+str(exc),file=sys.stderr)
        raise SystemExit(1)
