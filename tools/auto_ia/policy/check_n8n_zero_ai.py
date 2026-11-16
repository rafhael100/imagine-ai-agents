#!/usr/bin/env python3
"""AUTO-IA-13: reject unexpected or modified NO_AI n8n demo workflows.

Defense in depth only. A static checker cannot prove arbitrary JavaScript
cannot spend AI credits; therefore Code nodes require an exact reviewed Git
blob SHA and must remain manual/inactive. NEVER a production authorization.
"""
from __future__ import annotations

import hashlib
import json
import re
from pathlib import Path

ROOT=Path(__file__).resolve().parents[3]
MANIFEST=ROOT/"tools/auto_ia/policy/approved_no_ai_workflows.json"
WORKFLOW_DIR=ROOT/"tools/auto_ia/demo"
FORBIDDEN_TYPES=(
    "http", "webhook", "langchain", "agent", "openai", "anthropic",
    "google", "groq", "bedrock", "vertex", "llm",
)
FORBIDDEN_CODE=re.compile(
    r"\b(?:fetch|require|import|eval|Function|XMLHttpRequest|WebSocket|"
    r"process|globalThis|axios|http|https|openai|anthropic|gemini|groq)\b",
    re.IGNORECASE,
)
URL_OR_SECRET=re.compile(
    r"https?://|wss?://|\b(?:api[_-]?key|bearer[_-]?token|authorization|"
    r"client[_-]?secret)\b",re.IGNORECASE,
)

class Denied(Exception):
    pass

def strict_json(raw:bytes):
    def closed_pairs(pairs):
        obj={}
        for k,v in pairs:
            if k in obj:
                raise Denied("DUPLICATE_JSON_KEY")
            obj[k]=v
        return obj
    try:
        return json.loads(raw,object_pairs_hook=closed_pairs)
    except (ValueError,UnicodeDecodeError) as ex:
        raise Denied("INVALID_JSON") from ex

def blob_sha(raw:bytes)->str:
    return hashlib.sha1(b"blob "+str(len(raw)).encode()+b"\x00"+raw).hexdigest()

def all_strings(obj):
    if isinstance(obj,str):
        yield obj
    elif isinstance(obj,list):
        for item in obj:
            yield from all_strings(item)
    elif isinstance(obj,dict):
        for k,v in obj.items():
            yield k
            yield from all_strings(v)

def check_workflow(raw:bytes,expected_blob:str,permitted_types:list[str])->str:
    if len(raw)>65536:
        raise Denied("WORKFLOW_SIZE_LIMIT")
    workflow=strict_json(raw)
    if not isinstance(workflow,dict):
        raise Denied("WORKFLOW_MUST_BE_OBJECT")
    allowed={"name","nodes","connections","settings","active","pinData","tags"}
    if set(workflow)!=allowed:
        raise Denied("WORKFLOW_CLOSED_KEYS_REQUIRED")
    if workflow.get("active") is not False:
        raise Denied("WORKFLOW_MUST_BE_INACTIVE")
    if workflow.get("pinData")!={} or workflow.get("tags")!=[]:
        raise Denied("WORKFLOW_PINNED_DATA_OR_TAGS_FORBIDDEN")
    nodes=workflow.get("nodes")
    if not isinstance(nodes,list) or len(nodes)!=2:
        raise Denied("CLOSED_TWO_NODE_MANUAL_DEMO_REQUIRED")
    node_types=[n.get("type") for n in nodes if isinstance(n,dict)]
    if set(node_types)!=set(permitted_types) or len(node_types)!=2:
        raise Denied("NODE_ALLOWLIST_REQUIRED")
    if any(any(token in t.lower() for token in FORBIDDEN_TYPES)
           for t in node_types):
        raise Denied("AI_OR_NETWORK_NODE_FORBIDDEN")
    if not isinstance(workflow.get("connections"),dict) or len(workflow["connections"])!=1:
        raise Denied("SINGLE_MANUAL_TO_CODE_CONNECTION_REQUIRED")
    by_name={n.get("name"):n for n in nodes}
    if len(by_name)!=2 or len({n.get("id") for n in nodes})!=2:
        raise Denied("NODE_IDENTITY_COLLISION")
    manual=next(n for n in nodes if n["type"]=="n8n-nodes-base.manualTrigger")
    code=next(n for n in nodes if n["type"]=="n8n-nodes-base.code")
    expected_conn={manual["name"]:{
        "main":[[{"node":code["name"],"type":"main","index":0}]]
    }}
    if workflow["connections"]!=expected_conn:
        raise Denied("CONNECTION_MUST_BE_MANUAL_TO_CODE")
    if "credentials" in manual or "credentials" in code:
        raise Denied("CREDENTIALS_FORBIDDEN")
    if manual.get("parameters")!={}:
        raise Denied("MANUAL_PARAMETERS_NOT_EMPTY")
    if set(code.get("parameters",{}))!={"mode","jsCode"} or (
        code["parameters"]["mode"]!="runOnceForAllItems"
    ):
        raise Denied("CLOSED_CODE_PARAMETERS_REQUIRED")
    js=code["parameters"]["jsCode"]
    if not isinstance(js,str) or len(js)>10000 or FORBIDDEN_CODE.search(js):
        raise Denied("CODE_REQUIRES_REVIEWED_DETERMINISTIC_SCRIPT")
    for s in all_strings(workflow):
        if URL_OR_SECRET.search(s):
            raise Denied("WORKFLOW_URL_OR_SECRET_PROHIBITED")
    if blob_sha(raw)!=expected_blob:
        raise Denied("WORKFLOW_BLOB_CHANGED_NEEDS_REVIEW")
    return "NO_AI_MANUAL_ONLY_REVIEWED"

def audit_files(directory:Path,manifest:dict)->dict:
    if manifest.get("schema")!="AUTO_IA_N8N_NO_AI_POLICY_V1" or (
        manifest.get("mode")!="DENY_UNKNOWN_WORKFLOWS"
        or manifest.get("operational_authorization") is not False
        or manifest.get("api_provider_calls_allowed")!=0
        or manifest.get("commercial_credit_debit_allowed") is not False
    ):
        raise Denied("POLICY_CLOSED_SCHEMA_REQUIRED")
    catalog=manifest.get("workflows")
    if not isinstance(catalog,dict) or not catalog:
        raise Denied("WORKFLOW_CATALOG_REQUIRED")
    found={str(p.relative_to(ROOT)) for p in directory.glob("*.workflow.json")}
    if set(catalog)!=found:
        raise Denied("UNREVIEWED_WORKFLOW_ADDED_OR_APPROVED_REMOVED")
    for name,policy in catalog.items():
        if not name.startswith("tools/auto_ia/demo/") or "/../" in name:
            raise Denied("WORKFLOW_PATH_INVALID")
        path=ROOT/name
        if path.is_symlink():
            raise Denied("WORKFLOW_SYMLINK_FORBIDDEN")
        if policy.get("classification")!="NO_AI_MANUAL_ONLY":
            raise Denied("WORKFLOW_UNAPPROVED_CLASS")
        if policy.get("active") is not False:
            raise Denied("WORKFLOW_ACTIVE_FORBIDDEN")
        check_workflow(path.read_bytes(),
            policy.get("git_blob_sha1",""),
            policy.get("permitted_node_types",[]))
    return {
        "schema":"AUTO_IA_N8N_NO_AI_RESULT_V1",
        "reviewed_workflows":len(found),
        "static_policy":"PASS",
        "production_decision":"NO_GO",
        "n8n_container_started":False,
        "provider_calls":0,
        "credits_debited":0
    }

def negative_tests(manifest:dict)->int:
    name=next(iter(manifest["workflows"]))
    raw=(ROOT/name).read_bytes()
    policy=manifest["workflows"][name]
    expected=policy["git_blob_sha1"]
    types=policy["permitted_node_types"]
    passed=0
    def must_deny(payload:bytes,source:str,override:str|None=None):
        nonlocal passed
        try:
            check_workflow(payload,override or expected,types)
        except Denied:
            passed+=1
            return
        raise AssertionError("AUTO_IA_13_MUTATION_NOT_DENIED_"+source)
    w=strict_json(raw)
    for source,mutate in [
        ("active",lambda o:o.__setitem__("active",True)),
        ("hidden_credentials",lambda o:o["nodes"][1].__setitem__("credentials",{"openAiApi":{"id":"x"}})),
        ("http_node",lambda o:o["nodes"][1].__setitem__("type","n8n-nodes-base.httpRequest")),
        ("ai_node",lambda o:o["nodes"][1].__setitem__("type","@n8n/n8n-nodes-langchain.agent")),
        ("external_url",lambda o:o["nodes"][1]["parameters"].__setitem__("jsCode","fetch('https://api.openai.com/v1')")),
        ("obfuscated_static_change",lambda o:o["nodes"][1]["parameters"].__setitem__(
            "jsCode",o["nodes"][1]["parameters"]["jsCode"]+"\n//SYNTHETIC_NEW_UNREVIEWED_VERSION")),
        ("pin_data",lambda o:o.__setitem__("pinData",{"manual":{"secret":"SYNTHETIC"}})),
    ]:
        import copy
        changed=copy.deepcopy(w)
        mutate(changed)
        data=json.dumps(changed,ensure_ascii=False,separators=(",",":")).encode()
        if source=="obfuscated_static_change":
            must_deny(data,source)
        else:
            must_deny(data,source,override=blob_sha(data))
    must_deny(raw.replace(b'"name":',b'"name":"DUPLICATE", "name":',1),
              "duplicate_json_key",override=blob_sha(raw))
    if passed!=8:
        raise AssertionError("AUTO_IA_13_NEGATIVE_COVERAGE_MISSING")
    return passed

def main()->None:
    manifest=strict_json(MANIFEST.read_bytes())
    report=audit_files(WORKFLOW_DIR,manifest)
    report["adversarial_mutations_denied"]=negative_tests(manifest)
    print(json.dumps(report,ensure_ascii=False,sort_keys=True))

if __name__=="__main__":
    main()
