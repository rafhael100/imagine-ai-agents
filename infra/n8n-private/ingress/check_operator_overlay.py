#!/usr/bin/env python3
"""P15: validate merged Compose for optional loopback operator ingress.

The 2-service base remains unchanged and wholly internal. Only a dedicated
mTLS nginx bridge may publish 127.0.0.1:18744 for disposable CI use.
An allowed lab configuration NEVER implies MFA or production approval.
"""
from __future__ import annotations
import json,os,sys
from pathlib import Path
sys.path.insert(0,str(Path(__file__).resolve().parents[1]))
from check_noai_egress_policy import check_compose,Rejected as BaseRejected

class Denied(ValueError):pass

PROJECT="imagine-n8n-p15-ci"
SERVICES={"n8n","n8n-db","operator-ingress"}
PRIVATE={"n8n_storage","n8n_apps_private"}

def check(data:object)->dict:
    if not isinstance(data,dict) or data.get("name")!=PROJECT:
        raise Denied("WRONG_PROJECT")
    services=data.get("services")
    networks=data.get("networks")
    if not isinstance(services,dict) or set(services)!=SERVICES:
        raise Denied("UNKNOWN_SERVICE")
    if not isinstance(networks,dict) or set(networks)!=PRIVATE|{"operator_loopback_frontend"}:
        raise Denied("NETWORK_SET")
    try:
        check_compose({"services":{k:services[k] for k in ("n8n","n8n-db")},
                       "networks":{k:networks[k] for k in PRIVATE}})
    except BaseRejected as ex:
        raise Denied("BASE_SECURITY_"+str(ex)) from ex
    if any(networks[n].get("internal") is not True for n in PRIVATE):
        raise Denied("INTERNAL_NETWORK_CHANGED")
    # Docker Compose omits an explicit internal:false when rendering defaults.
    # Only the ingress is connected to this isolated project bridge.
    frontend=networks["operator_loopback_frontend"]
    if frontend.get("internal",False) is not False or frontend.get("driver","bridge")!="bridge":
        raise Denied("OPERATOR_FRONTEND_ABSENT")
    app=services["n8n"]
    env=app.get("environment",{})
    if env.get("N8N_PROXY_HOPS")!="1" or (
        env.get("N8N_EDITOR_BASE_URL")!="https://localhost:18744/"
    ):
        raise Denied("REVERSE_PROXY_HEADERS")
    if env.get("N8N_WEBHOOK_URL") or env.get("WEBHOOK_URL"):
        raise Denied("WEBHOOK_MUST_STAY_OFFLINE")
    proxy=services["operator-ingress"]
    if not isinstance(proxy,dict):
        raise Denied("PROXY_NOT_OBJECT")
    if set(proxy.get("networks",[]))!={"n8n_apps_private","operator_loopback_frontend"}:
        raise Denied("PROXY_NETWORK_BOUNDARY")
    ports=proxy.get("ports")
    if not isinstance(ports,list) or len(ports)!=1:
        raise Denied("PROXY_ONE_LOOPBACK_PORT")
    port=ports[0]
    if not isinstance(port,dict) or (
        port.get("host_ip")!="127.0.0.1" or str(port.get("published"))!="18744"
        or int(port.get("target",0))!=8443 or port.get("protocol","tcp")!="tcp"
    ):
        raise Denied("PROXY_PORT_MUST_BE_LOOPBACK_TLS")
    if (proxy.get("privileged") or proxy.get("network_mode") or proxy.get("extra_hosts")
        or proxy.get("dns") or proxy.get("pid") or proxy.get("ipc")
        or proxy.get("devices") or proxy.get("build")):
        raise Denied("UNSAFE_PROXY_OVERRIDE")
    image=proxy.get("image","")
    if not isinstance(image,str) or "@sha256:" not in image or len(image.split("@sha256:")[-1])!=64:
        raise Denied("PROXY_DIGEST_UNPINNED")
    if proxy.get("profiles")!=["operator-lab"]:
        raise Denied("PROXY_NOT_LAB_PROFILE")
    if proxy.get("cap_drop")!=["ALL"] or (
        proxy.get("security_opt")!=["no-new-privileges:true"]
    ):
        raise Denied("PROXY_PRIVILEGES")
    # Root master only retains permission to drop worker UID/GID and
    # chown its isolated temporary directory. No DAC override/NET_ADMIN.
    if set(proxy.get("cap_add",[]))!={"SETUID","SETGID","CHOWN"} or len(proxy.get("cap_add",[]))!=3:
        raise Denied("PROXY_ONLY_SETUID_SETGID")
    if proxy.get("restart")!="no" or proxy.get("environment"):
        raise Denied("PROXY_ENV_OR_RESTART")
    # Compose renders byte/pid limits as either strings or integers.
    try:
        memory=int(proxy.get("mem_limit",0))
        pids=int(proxy.get("pids_limit",0))
    except (TypeError,ValueError):
        raise Denied("PROXY_RESOURCE_LIMITS") from None
    if memory!=134217728 or pids!=64:
        raise Denied("PROXY_RESOURCE_LIMITS")
    if proxy.get("depends_on",{}).get("n8n",{}).get("condition")!="service_healthy":
        raise Denied("PROXY_UPSTREAM_HEALTHCHECK")
    sec=proxy.get("secrets")
    if not isinstance(sec,list) or {v.get("source") for v in sec if isinstance(v,dict)}!={
        "operator_server_cert","operator_server_key","operator_client_ca"
    } or len(sec)!=3:
        raise Denied("PROXY_CERT_SOURCE")
    vols=proxy.get("volumes")
    if (not isinstance(vols,list) or len(vols)!=2
        or any(not isinstance(v,dict) or v.get("type")!="bind"
               or v.get("read_only") is not True for v in vols)):
        raise Denied("PROXY_ONLY_READ_ONLY_BINDS")
    by_target={v.get("target"):v.get("source") for v in vols}
    if len(by_target)!=2 or set(by_target)!={
        "/etc/nginx/conf.d/default.conf",
        "/etc/nginx/operator-fingerprints.conf"
    }:
        raise Denied("PROXY_CERT_PIN_MOUNT_TARGETS")
    if not str(by_target["/etc/nginx/conf.d/default.conf"]).endswith(
        "/infra/n8n-private/ingress/nginx-operator.conf"
    ) or not str(by_target["/etc/nginx/operator-fingerprints.conf"]).endswith(
        "/operator-fingerprints.conf"
    ):
        raise Denied("PROXY_CERT_PIN_UNREVIEWED_SOURCE")
    return {
        "schema":"CENTRAL_AUTO_P15_OPERATOR_MTLS_V1",
        "decision":"LAB_ONLY_NO_GO",
        "host_binding":"127.0.0.1:18744",
        "operator_auth":"CLIENT_CERTIFICATE_AND_SYNTHETIC_CN",
        "mfa_integrated":False,
        "outside_host_access":False,
        "n8n_still_internal":True,
        "public_api_disabled":True,
        "customer_data":False,
        "production_authorization":False,
    }

def main():
    def unique(pairs):
        d={}
        for k,v in pairs:
            if k in d:raise Denied("JSON_DUPLICATE_KEY")
            d[k]=v
        return d
    print(json.dumps(check(json.load(sys.stdin,object_pairs_hook=unique)),sort_keys=True))
if __name__=="__main__":
    try:main()
    except (Denied,TypeError,ValueError,KeyError,AssertionError) as ex:
        print("CENTRAL_AUTO_P15_DENIED_"+str(ex),file=sys.stderr)
        raise SystemExit(1)
