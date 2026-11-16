#!/usr/bin/env python3
"""AUTO-IA-15: verify import/export in official n8n preserves reviewed NO_AI nodes.

No network, no credentials, no execution and no deployment. Compare the
exported workflow to the approved JSON that passed the hash-pinned policy.
"""
import copy
import json
import sys
from pathlib import Path

class Denied(Exception): pass

FIELDS={"id","name","type","typeVersion","position","parameters"}
APPROVED_TYPES={"n8n-nodes-base.manualTrigger","n8n-nodes-base.code"}

def compare(source,exported):
    if not isinstance(source,dict) or not isinstance(exported,dict):
        raise Denied("WORKFLOWS_MUST_BE_OBJECTS")
    if source.get("active") is not False or exported.get("active") is not False:
        raise Denied("WORKFLOW_ACTIVE")
    if source.get("name")!=exported.get("name"):
        raise Denied("WORKFLOW_IDENTITY_CHANGED")
    for k in ("connections","pinData"):
        if source.get(k,{})!=exported.get(k,{}):
            raise Denied("WORKFLOW_CHANGED_"+k.upper())
    for k,value in source.get("settings",{}).items():
        if exported.get("settings",{}).get(k)!=value:
            raise Denied("WORKFLOW_SETTINGS_CHANGED")
    raw=source.get("nodes")
    returned=exported.get("nodes")
    if not isinstance(raw,list) or not isinstance(returned,list) or len(raw)!=2 or len(returned)!=2:
        raise Denied("WORKFLOW_NODE_COUNT_CHANGED")
    def index(nodes):
        result={}
        for n in nodes:
            if not isinstance(n,dict) or not isinstance(n.get("id"),str):
                raise Denied("WORKFLOW_NODE_FORMAT_INVALID")
            if not FIELDS.issubset(n):
                raise Denied("WORKFLOW_NODE_INCOMPLETE")
            if set(n)-FIELDS:
                raise Denied("WORKFLOW_NODE_EXTRA_FIELDS")
            if n["type"] not in APPROVED_TYPES or "credentials" in n:
                raise Denied("WORKFLOW_NODE_FORBIDDEN")
            if n["id"] in result:
                raise Denied("WORKFLOW_DUPLICATE_NODE_ID")
            result[n["id"]]=n
        return result
    original=index(raw)
    actual=index(returned)
    if original!=actual:
        raise Denied("IMPORTED_CODE_OR_NODE_CHANGED")
    if exported.get("tags",[]) not in ([],None):
        raise Denied("UNREVIEWED_TAGS")
    return {
        "schema":"AUTO_IA_N8N_ROUNDTRIP_PROOF_V1",
        "nodes_verified":2,
        "code_exact":True,
        "active":False,
        "credentials":0,
        "production_decision":"NO_GO"
    }

def negative_tests(source):
    mutations=[
        ("code_modified",lambda e:e["nodes"][1]["parameters"].update(jsCode="return []")),
        ("type_modified",lambda e:e["nodes"][1].update(type="n8n-nodes-base.httpRequest")),
        ("credentials",lambda e:e["nodes"][1].update(credentials={"secret":{"id":"X"}})),
        ("active",lambda e:e.update(active=True)),
        ("connection",lambda e:e.update(connections={})),
        ("extra_node",lambda e:e["nodes"].append(copy.deepcopy(e["nodes"][0]))),
        ("rogue_setting",lambda e:e.setdefault("settings",{}).update(timezone="UTC")),
        ("hidden_fields",lambda e:e["nodes"][0].update(webhookId="SYNTHETIC_HIDDEN")),
        ("altered_name",lambda e:e.update(name="New compromised flow")),
        ("credentials_in_code",lambda e:e["nodes"][1]["parameters"].update(
            jsCode=e["nodes"][1]["parameters"]["jsCode"]+"\n// changed")),
    ]
    count=0
    for label,mutation in mutations:
        value=copy.deepcopy(source)
        mutation(value)
        try: compare(source,value)
        except Denied: count+=1; continue
        raise AssertionError("ROUNDTRIP_MUTATION_NOT_DENIED_"+label)
    if count!=len(mutations): raise AssertionError("MISSING_NEGATIVE_CASES")
    return count

def main():
    if len(sys.argv)!=3: raise Denied("USAGE_SOURCE_EXPORTED")
    source_path=Path(sys.argv[1]); exported_path=Path(sys.argv[2])
    if not source_path.is_file() or not exported_path.is_file() or (
        source_path.is_symlink() or exported_path.is_symlink()
    ): raise Denied("SOURCE_FILE_MISSING")
    source=json.loads(source_path.read_text(encoding="utf-8"))
    if sys.argv[2]=="--self-test": raise Denied("BAD_SELF_TEST_PATH")
    exported=json.loads(exported_path.read_text(encoding="utf-8"))
    if isinstance(exported,list) and len(exported)==1:
        exported=exported[0]
    check=compare(source,exported)
    check["negative_mutations_denied"]=negative_tests(source)
    print(json.dumps(check,sort_keys=True))

if __name__=="__main__":
    try: main()
    except (Denied,ValueError,TypeError) as ex:
        print("AUTO_IA_15_ROUNDTRIP_DENY_"+str(ex),file=sys.stderr)
        raise SystemExit(1)
