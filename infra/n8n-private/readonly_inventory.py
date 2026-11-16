#!/usr/bin/env python3
"""Read-only host metadata for CENTRAL-AUTO HOM-01 (not deployment approval).

Allowlist only: docker ps/network/volume counters, docker server and Compose
versions, and ss port listening checks. Never docker inspect, env, logs,
mounts, secrets, SSH, network calls, or container startup. Machine identity
must be verified independently before a GO recommendation.
"""
import argparse
import hashlib
import json
import os
import re
import shutil
import subprocess

PORTS=(17990,18081,18090)

def run(args):
    p=subprocess.run(args,capture_output=True,text=True,timeout=10,check=False)
    if p.returncode:
        raise RuntimeError("inventory_command_unavailable")
    if len(p.stdout)>128000:
        raise RuntimeError("inventory_command_too_large")
    return p.stdout

def available_memory_bytes():
    try:
        return int(os.sysconf("SC_AVPHYS_PAGES"))*int(os.sysconf("SC_PAGE_SIZE"))
    except (AttributeError,ValueError,OSError):
        return None

def inventory(execute=run):
    report={
        "schema":"N8N_HOST_INVENTORY_V2",
        "status":"blocked","host_identity":"unverified","changes":0,
        "cpu_count":os.cpu_count(),
        "memory_available_bytes":available_memory_bytes(),
        "disk_free_bytes":shutil.disk_usage("/").free,
        "docker":{"available":False,"server_version":None,"compose_version":None},
        "ports":{str(p):"unverified" for p in PORTS},
        "gates":[
            "independent_leaseweb_host_identity","license_review",
            "docker_compatibility","cpu_memory_disk_headroom",
            "preserve_crm_sites_and_containers","private_https_mfa_ingress",
            "service_iam_and_egress","secrets_custody",
            "external_backup_and_verified_restore","appsec_dba_lgpd",
            "human_staging_approval"
        ]
    }
    try:
        names=execute(["docker","ps","-a","--format","{{.Names}}"]).splitlines()
        networks=execute(["docker","network","ls","--format","{{.Name}}"]).splitlines()
        volumes=execute(["docker","volume","ls","--format","{{.Name}}"]).splitlines()
        report["docker"]={
            "available":True,"containers":len(names),
            "networks":len(networks),"volumes":len(volumes),
            "container_refs":[hashlib.sha256(n.encode()).hexdigest()[:16] for n in names[:128]],
            "server_version":None,"compose_version":None
        }
        try:
            v=execute(["docker","version","--format","{{.Server.Version}}"]).strip()
            if re.fullmatch(r"[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,4}",v):
                report["docker"]["server_version"]=v
        except (OSError,RuntimeError,subprocess.TimeoutExpired):
            pass
        try:
            v=execute(["docker","compose","version","--short"]).strip().lstrip("v")
            if re.fullmatch(r"[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,4}",v):
                report["docker"]["compose_version"]=v
        except (OSError,RuntimeError,subprocess.TimeoutExpired):
            pass
    except (OSError,RuntimeError,subprocess.TimeoutExpired):
        pass
    for port in PORTS:
        try:
            listeners=execute(["ss","-H","-ltn","sport = :"+str(port)]).strip()
            report["ports"][str(port)]=(
                "occupied" if listeners else "observed_free_not_reserved"
            )
        except (OSError,RuntimeError,subprocess.TimeoutExpired):
            pass
    # Keep compatibility with earlier safety tooling without representing
    # free/nonfree ports as ownership or operational availability.
    report["port_17990"]=report["ports"]["17990"]
    return report

if __name__=="__main__":
    p=argparse.ArgumentParser()
    p.add_argument("--probe-host",action="store_true",required=True)
    p.parse_args()
    print(json.dumps(inventory(),sort_keys=True))
