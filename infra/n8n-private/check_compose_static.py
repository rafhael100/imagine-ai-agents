#!/usr/bin/env python3
"""AUTO-IA-11: validate an expanded Docker Compose JSON; no containers started."""
import json
import os
import sys

data=json.load(sys.stdin)
svcs=data["services"]
assert set(svcs)=={"n8n","n8n-db"}
assert data["name"]==os.environ.get("N8N_EXPECTED_PROJECT","imagine-n8n-private-staging")
for name,s in svcs.items():
    image=s["image"]
    assert "@sha256:" in image and len(image.split("@sha256:")[1])==64, (name,image)
    assert s.get("restart")=="no"
    assert not s.get("privileged",False)
    assert s.get("network_mode") is None
assert not svcs["n8n-db"].get("ports"), "DB must not publish ports"
ports=svcs["n8n"]["ports"]
assert len(ports)==1 and ports[0].get("host_ip")=="127.0.0.1"
assert int(ports[0].get("published"))==17990
assert set(svcs["n8n"].get("profiles",[]))=={"homologation"}
assert svcs["n8n"].get("cap_drop")==["ALL"]
assert svcs["n8n"].get("security_opt")==["no-new-privileges:true"]
assert svcs["n8n"].get("depends_on",{}).get("n8n-db",{}).get("condition")=="service_healthy"
networks=data["networks"]
assert len(networks)==2 and all(v.get("internal") is True for v in networks.values())
assert all("openai" not in x.lower() and "anthropic" not in x.lower()
           for x in svcs["n8n"].get("environment",{}))
assert svcs["n8n"]["environment"]["N8N_DIAGNOSTICS_ENABLED"]=="false"
print("AUTO_IA_11_COMPOSE_STATIC_PASS no_run=1 no_public_port=1 no_provider=1")

for service in svcs.values():
    assert not {"POSTGRES_PASSWORD","DB_POSTGRESDB_PASSWORD","N8N_ENCRYPTION_KEY"}.intersection(service.get("environment",{}))
assert svcs["n8n"]["environment"]["DB_POSTGRESDB_USER"]=="n8n_runtime"
assert svcs["n8n"]["environment"]["N8N_BLOCK_ENV_ACCESS_IN_NODE"]=="true"
assert set(svcs["n8n-db"].get("secrets",[])[i]["source"] for i in range(2))=={"n8n_pg_admin_password","n8n_pg_password"}
print("P4_SECRET_FILES_AND_NON_SUPERUSER_CONFIG_PASS runtime_probe_required=1")
