#!/usr/bin/env python3
"""CENTRAL-AUTO-P13: closed NO_AI policy for the n8n private staging Compose.

Explicitly does NOT certify real firewalls, egress in VPS or monetary billing.
This policy rejects network and paid-provider escape hatches in configuration.
"""
from __future__ import annotations
import json
import sys

BLOCKED_NODES={
  "n8n-nodes-base.code",
  "n8n-nodes-base.executeCommand",
  "n8n-nodes-base.ssh",
  "n8n-nodes-base.readWriteFile",
  "n8n-nodes-base.httpRequest",
}
REQUIRED_TRUE={
  "N8N_PUBLIC_API_DISABLED",
  "N8N_PUBLIC_API_SWAGGERUI_DISABLED",
  "N8N_SSRF_PROTECTION_ENABLED",
  "N8N_BLOCK_ENV_ACCESS_IN_NODE",
}
REQUIRED_FALSE={"N8N_DIAGNOSTICS_ENABLED","N8N_COMMUNITY_PACKAGES_ENABLED"}
FORBIDDEN_VARS={
  "N8N_SSRF_ALLOWED_HOSTNAMES",
  "N8N_SSRF_ALLOWED_IP_RANGES",
  "N8N_PUBLIC_API_ENDPOINT",
  "N8N_API_KEY",
  "OPENAI_API_KEY",
  "ANTHROPIC_API_KEY",
  "GOOGLE_API_KEY",
  "DB_POSTGRESDB_PASSWORD",
  "N8N_ENCRYPTION_KEY",
}
class Rejected(Exception): pass

def check_compose(data:object)->dict:
    if not isinstance(data,dict):
        raise Rejected("COMPOSE_OBJECT_REQUIRED")
    svcs=data.get("services")
    if not isinstance(svcs,dict) or set(svcs)!={"n8n","n8n-db"}:
        raise Rejected("SERVICE_ALLOWLIST")
    networks=data.get("networks")
    if not isinstance(networks,dict) or set(networks)!={"n8n_storage","n8n_apps_private"}:
        raise Rejected("NETWORK_ALLOWLIST")
    if any(not isinstance(n,dict) or n.get("internal") is not True
           for n in networks.values()):
        raise Rejected("NETWORK_MUST_BE_INTERNAL")
    for name,s in svcs.items():
        if not isinstance(s,dict):
            raise Rejected("SERVICE_NOT_OBJECT")
        if (s.get("ports") or s.get("network_mode") or s.get("extra_hosts") or
            s.get("links") or s.get("dns") or s.get("dns_search") or
            s.get("privileged") or s.get("pid")=="host"):
            raise Rejected("SERVICE_NETWORK_ESCAPE_"+name)
        assigned=s.get("networks",[])
        if isinstance(assigned,dict):
            assigned=list(assigned)
        if (not isinstance(assigned,list) or
            not set(assigned).issubset(networks) or not assigned):
            raise Rejected("SERVICE_NETWORKS_"+name)
        if "host" in assigned:
            raise Rejected("HOST_NETWORK")
    n8n=svcs["n8n"]
    if set(n8n.get("networks",[]))!={"n8n_storage","n8n_apps_private"}:
        raise Rejected("N8N_MUST_USE_ONLY_INTERNAL_NETWORKS")
    env=n8n.get("environment")
    if not isinstance(env,dict):
        raise Rejected("ENV_OBJECT_REQUIRED")
    for k in REQUIRED_TRUE:
        if env.get(k)!="true":
            raise Rejected("REQUIRED_TRUE_"+k)
    for k in REQUIRED_FALSE:
        if env.get(k)!="false":
            raise Rejected("REQUIRED_FALSE_"+k)
    if FORBIDDEN_VARS.intersection(env):
        raise Rejected("FORBIDDEN_ENV_KEY")
    if any("openai" in k.lower() or "anthropic" in k.lower()
           for k in env):
        raise Rejected("PROVIDER_ENV_KEY")
    raw=env.get("NODES_EXCLUDE")
    try:
        names=json.loads(raw)
    except (TypeError,ValueError):
        raise Rejected("NODES_EXCLUDE_INVALID") from None
    if (not isinstance(names,list) or len(names)!=len(BLOCKED_NODES)
        or set(names)!=BLOCKED_NODES or any(not isinstance(n,str) for n in names)):
        raise Rejected("EXCLUDED_NODE_ALLOWLIST")
    if n8n.get("ports"):
        raise Rejected("N8N_PORTS_NOT_ALLOWED")
    if n8n.get("expose")!=["5678"]:
        raise Rejected("ONLY_INTERNAL_EXPOSE")
    if n8n.get("cap_drop")!=["ALL"] or (
        n8n.get("security_opt")!=["no-new-privileges:true"]
    ):
        raise Rejected("CAPABILITIES_NOT_DROPPED")
    if n8n.get("environment",{}).get("DB_POSTGRESDB_USER")!="n8n_runtime":
        raise Rejected("DB_ACCOUNT_NOT_RESTRICTED")
    if n8n.get("profiles")!=["homologation"]:
        raise Rejected("NOT_LAB_ONLY")
    return {
        "schema":"CENTRAL_AUTO_P13_NO_AI_EGRESS_POLICY_V1",
        "decision":"APPROVED_FOR_CI_OFFLINE_TEST_ONLY",
        "excluded_nodes":len(BLOCKED_NODES),
        "public_api_disabled":True,
        "ssrf_defense_enabled":True,
        "networks_internal":2,
        "host_published_ports":0,
        "real_provider_usage_verified":False,
        "production_authorization":False,
    }

def main():
    def unique(pairs):
        d={}
        for k,v in pairs:
            if k in d:
                raise Rejected("DUPLICATE_JSON_KEY")
            d[k]=v
        return d
    data=json.load(sys.stdin,object_pairs_hook=unique)
    print(json.dumps(check_compose(data),sort_keys=True))
if __name__=="__main__":
    try:main()
    except (Rejected,ValueError,TypeError,KeyError) as exc:
        print("CENTRAL_AUTO_P13_NO_AI_POLICY_DENIED_"+str(exc),file=sys.stderr)
        raise SystemExit(1)
