#!/usr/bin/env python3
"""P15 static security mutation tests for operator mTLS loopback-only overlay."""
from __future__ import annotations
import copy,json,sys,unittest
from pathlib import Path
sys.path.insert(0,str(Path(__file__).resolve().parent))
sys.path.insert(0,str(Path(__file__).resolve().parents[1]))
from check_operator_overlay import check,Denied
from test_noai_egress_policy import candidate as core_candidate

def candidate():
    data=core_candidate()
    data["name"]="imagine-n8n-p15-ci"
    data["services"]["n8n"]["environment"].update(
        N8N_PROXY_HOPS="1",N8N_EDITOR_BASE_URL="https://localhost:18744/"
    )
    data["networks"]["operator_loopback_frontend"]={"internal":False,"name":"ci_frontend"}
    data["services"]["operator-ingress"]={
      "image":"nginx@sha256:"+"a"*64,
      "networks":["n8n_apps_private","operator_loopback_frontend"],
      "ports":[{"host_ip":"127.0.0.1","published":"18744","target":8443,"protocol":"tcp"}],
      "profiles":["operator-lab"],
      "cap_drop":["ALL"],"cap_add":["SETUID","SETGID"],
      "security_opt":["no-new-privileges:true"],
      "restart":"no","mem_limit":134217728,"pids_limit":64,
      "depends_on":{"n8n":{"condition":"service_healthy"}},
      "secrets":[{"source":x} for x in (
          "operator_server_cert","operator_server_key","operator_client_ca")],
      "volumes":[{
        "type":"bind","source":"/home/runner/work/ci/infra/n8n-private/ingress/nginx-operator.conf",
        "target":"/etc/nginx/conf.d/default.conf","read_only":True,
      }],
    }
    return data

class P15SecurityTests(unittest.TestCase):
    def test_valid_lab_no_production(self):
        result=check(candidate())
        self.assertEqual("LAB_ONLY_NO_GO",result["decision"])
        self.assertFalse(result["mfa_integrated"])
        self.assertFalse(result["production_authorization"])
        self.assertTrue(result["n8n_still_internal"])
    def test_mutations(self):
        cases=[
          ("wildcard_ip",lambda d:d["services"]["operator-ingress"]["ports"][0].update(host_ip="0.0.0.0")),
          ("ipv6_ip",lambda d:d["services"]["operator-ingress"]["ports"][0].update(host_ip="::")),
          ("port_changed",lambda d:d["services"]["operator-ingress"]["ports"][0].update(published="443")),
          ("port_extra",lambda d:d["services"]["operator-ingress"]["ports"].append({"host_ip":"127.0.0.1","target":443,"published":"8000"})),
          ("raw_tcp_n8n",lambda d:d["services"]["n8n"].update(ports=["127.0.0.1:5678:5678"])),
          ("raw_db",lambda d:d["services"]["n8n-db"].update(ports=["127.0.0.1:5432:5432"])),
          ("secret_env",lambda d:d["services"]["operator-ingress"].update(environment={"TLS_KEY":"synthetic"})),
          ("bad_digest",lambda d:d["services"]["operator-ingress"].update(image="nginx:latest")),
          ("extra_host",lambda d:d["services"]["operator-ingress"].update(extra_hosts=["metadata:169.254.169.254"])),
          ("dns",lambda d:d["services"]["operator-ingress"].update(dns=["8.8.8.8"])),
          ("privileged",lambda d:d["services"]["operator-ingress"].update(privileged=True)),
          ("add_cap",lambda d:d["services"]["operator-ingress"].update(cap_add=["SETUID","SETGID","NET_ADMIN"])),
          ("drop_uid_cap",lambda d:d["services"]["operator-ingress"].update(cap_add=["SETGID"])),
          ("db_connected",lambda d:d["services"]["operator-ingress"]["networks"].append("n8n_storage")),
          ("new_frontend_for_n8n",lambda d:d["services"]["n8n"]["networks"].append("operator_loopback_frontend")),
          ("frontend_internal",lambda d:d["networks"]["operator_loopback_frontend"].update(internal=True)),
          ("base_internet",lambda d:d["networks"]["n8n_apps_private"].update(internal=False)),
          ("proxy_hops",lambda d:d["services"]["n8n"]["environment"].update(N8N_PROXY_HOPS="2")),
          ("editor_base",lambda d:d["services"]["n8n"]["environment"].update(N8N_EDITOR_BASE_URL="https://example.org/")),
          ("api_on",lambda d:d["services"]["n8n"]["environment"].update(N8N_PUBLIC_API_DISABLED="false")),
          ("webhooks",lambda d:d["services"]["n8n"]["environment"].update(N8N_WEBHOOK_URL="https://example.org/")),
          ("secrets_missing",lambda d:d["services"]["operator-ingress"]["secrets"].pop()),
          ("config_rw",lambda d:d["services"]["operator-ingress"]["volumes"][0].update(read_only=False)),
          ("config_swap",lambda d:d["services"]["operator-ingress"]["volumes"][0].update(source="/tmp/config.conf")),
          ("dangerous_service",lambda d:d["services"].update({"custom-agent":{}})),
          ("wrong_project",lambda d:d.update(name="imagine-other")),
          ("no_mtls_proxy_profile",lambda d:d["services"]["operator-ingress"].update(profiles=[])),
          ("proxy_restart",lambda d:d["services"]["operator-ingress"].update(restart="always")),
        ]
        for label,modify in cases:
            with self.subTest(label=label):
                value=copy.deepcopy(candidate());modify(value)
                with self.assertRaises((Denied,ValueError,TypeError)):
                    check(value)

if __name__=="__main__":
    unittest.main()
