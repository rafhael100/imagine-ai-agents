#!/usr/bin/env python3
"""Explicit cross-repository CI entrypoint; never impersonates the Agents repository."""
import hashlib,json,os,shutil,subprocess,sys
from pathlib import Path
if os.getenv('GITHUB_ACTIONS')!='true' or os.getenv('GITHUB_REPOSITORY')!='rafhael100/imagine-crm':
 raise SystemExit('P19_DENIED_NON_CRM_INTEGRATION_CI')
root=Path(__file__).resolve().parents[2]
sys.path.insert(0,str(root/'tools/auto_ia/central_transport'))
from rehearse_identity_merge_tree import rehearse,smoke_merged_tree,export_review_snapshot
result=rehearse()
if result['result']!='THREE_LANE_SIMULATION_CLEAN_NO_GO' or any(result['snapshot_branches_moved'].values()):
 raise SystemExit('P19_DENIED_STALE_OR_CONFLICTING_BASES')
checks=smoke_merged_tree(result['tree_for_runner_only'])
target=Path(os.environ['RUNNER_TEMP'])/'central-auto-p18b-merged'
export_review_snapshot(result['tree_for_runner_only'],str(target))
shutil.copytree(root/'runtime/central-auto',target/'runtime/central-auto')
head=subprocess.run(['git','rev-parse','HEAD'],cwd=root,capture_output=True,text=True,check=True).stdout.strip()
print(json.dumps({'schema':'CENTRAL_AUTO_P19_INTEGRATED_TREE_V1','consolidated_tree':result['tree_for_runner_only'],
 'receiver_source_commit':head,'prior_tree_checks':checks,'overlay_hashes':{p.name:hashlib.sha256(p.read_bytes()).hexdigest() for p in sorted((target/'runtime/central-auto').iterdir()) if p.is_file()},
 'release_authorized':False,'dispatch':'DISABLED'},sort_keys=True))
