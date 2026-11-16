#!/usr/bin/env python3
"""P60: fail-closed guard against redundant or overprivileged CI runs.

Earlier draft workflows remain evidence and are not silently removed.
"""
from pathlib import Path

ROOT=Path(__file__).resolve().parents[1]
PATH=ROOT/".github/workflows/ci-p60-efficient-gates.yml"
HEAD="review/aia-p59-author-review-separation-2026-11-16"
GROUP="group: aia-p60-"+chr(36)+"{{ github.head_ref || github.ref_name }}"

def check(text:str)->bool:
    before_jobs=text.split("\njobs:\n",1)[0]
    if "\n  push:" in before_jobs:
        return False
    must=[
        "name: Agents P60 - consolidated PR-only CI with cancellation",
        "on:\n  pull_request:\n    branches:\n      - "+HEAD,
        "  workflow_dispatch:",
        "permissions:\n  contents: read",
        "concurrency:",
        GROUP,
        "cancel-in-progress: true",
        "jobs:",
        "p60-efficient-ci-full-regression:",
        "p47-official-n8n-pg17-independent:",
        "p56-local-only-pg16-browser:",
        "AIA_P59_AUTHOR_LAB:",
        "AIA_P58_REJECTION_RACE_LAB:",
        "tools/check_p60_ci_gates.py",
        "tools/check_p59_author_review_separation.php",
        "tools/check_p58_serialized_review_rejection.php",
        "tools/check_p55_scoped_review_queue_pg.php",
        "tools/check_p56_live_pg_readonly_http.py",
        "infra/n8n-private/run_native_noai_pg.sh",
        "persist-credentials: false",
        "actions/checkout@3d3c42e5aac5ba805825da76410c181273ba90b1",
    ]
    if any(fragment not in text for fragment in must):
        return False
    if any(bad in before_jobs for bad in (
        "pull_request_target","contents: write","actions: write",
        "id-token: write","packages: write",
    )):
        return False
    # Every checkout occurrence must be fixed to the reviewed SHA, not
    # merely one matching checkout elsewhere in the same workflow.
    import re
    pins=re.findall(r"uses:\s*actions/checkout@([^\s]+)",text)
    if not pins or any(
        pin!="3d3c42e5aac5ba805825da76410c181273ba90b1"
        for pin in pins
    ):
        return False
    if "persist-credentials: true" in text:
        return False
    if text.count("    runs-on: ubuntu-24.04")!=3:
        return False
    return True

def main()->None:
    original=PATH.read_text("utf-8")
    assert check(original),"P60_UNSAFE_OR_INCOMPLETE_WORKFLOW"
    mutations=[
        original.replace("  workflow_dispatch:", "  push:\n  workflow_dispatch:",1),
        original.replace("contents: read","contents: write",1),
        original.replace("cancel-in-progress: true","cancel-in-progress: false",1),
        original.replace(GROUP,"group: fixed",1),
        original.replace("  pull_request:","  pull_request_target:",1),
        original.replace("persist-credentials: false","persist-credentials: true",1),
        original.replace("actions/checkout@3d3c42e5aac5ba805825da76410c181273ba90b1",
                         "actions/checkout@v4",1),
        # Both lint and execution references must disappear: changing only
        # one occurrence leaves another and does not test gate enforcement.
        original.replace("tools/check_p59_author_review_separation.php","no_p59_tests.php"),
        original.replace("p56-local-only-pg16-browser:","disabled-browser:",1),
        original.replace("p47-official-n8n-pg17-independent:","disabled-n8n:",1),
    ]
    for i,bad in enumerate(mutations):
        assert bad!=original and not check(bad),("P60_MUTATION_ACCEPTED",i)
    print(
        "AIA_P60_PR_CI_GATES_PASS source=PR_ONLY "
        f"mutations_rejected={len(mutations)} "
        "permissions=READ_ONLY three_jobs=1 "
        "superseded_runs_cancelled=1 no_deploy=1 NO_GO"
    )

if __name__=="__main__":
    main()
