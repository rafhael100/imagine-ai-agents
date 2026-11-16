"""CENTRAL-AUTO-P13: mutation tests of private NO_AI Compose security gates."""
import copy
import json
import unittest
from check_noai_egress_policy import check_compose,Rejected,BLOCKED_NODES

def candidate():
    env={
      "N8N_PUBLIC_API_DISABLED":"true",
      "N8N_PUBLIC_API_SWAGGERUI_DISABLED":"true",
      "N8N_SSRF_PROTECTION_ENABLED":"true",
      "N8N_BLOCK_ENV_ACCESS_IN_NODE":"true",
      "N8N_DIAGNOSTICS_ENABLED":"false",
      "N8N_COMMUNITY_PACKAGES_ENABLED":"false",
      "NODES_EXCLUDE":json.dumps(sorted(BLOCKED_NODES)),
      "DB_POSTGRESDB_USER":"n8n_runtime"
    }
    return {
      "name":"imagine-n8n-private-staging",
      "services":{
        "n8n":{
          "environment":env,
          "networks":["n8n_storage","n8n_apps_private"],
          "expose":["5678"],"profiles":["homologation"],
          "cap_drop":["ALL"],
          "security_opt":["no-new-privileges:true"]
        },
        "n8n-db":{"networks":["n8n_storage"]}
      },
      "networks":{"n8n_storage":{"internal":True},
                  "n8n_apps_private":{"internal":True}}
    }

class NOAIEgressTests(unittest.TestCase):
    def test_reviewed_fixture_accepts_only_ci(self):
        r=check_compose(candidate())
        self.assertEqual("APPROVED_FOR_CI_OFFLINE_TEST_ONLY",r["decision"])
        self.assertFalse(r["production_authorization"])
        self.assertFalse(r["real_provider_usage_verified"])
        self.assertEqual(5,r["excluded_nodes"])
        self.assertEqual(0,r["host_published_ports"])
    def test_adversarial_mutations_fail_closed(self):
        mutants=[
          ("enable_api",lambda d:d["services"]["n8n"]["environment"].update(N8N_PUBLIC_API_DISABLED="false")),
          ("swagger",lambda d:d["services"]["n8n"]["environment"].update(N8N_PUBLIC_API_SWAGGERUI_DISABLED="false")),
          ("ssrf_off",lambda d:d["services"]["n8n"]["environment"].update(N8N_SSRF_PROTECTION_ENABLED="false")),
          ("env_access",lambda d:d["services"]["n8n"]["environment"].update(N8N_BLOCK_ENV_ACCESS_IN_NODE="false")),
          ("diagnostics",lambda d:d["services"]["n8n"]["environment"].update(N8N_DIAGNOSTICS_ENABLED="true")),
          ("packages",lambda d:d["services"]["n8n"]["environment"].update(N8N_COMMUNITY_PACKAGES_ENABLED="true")),
          ("open_http_node",lambda d:d["services"]["n8n"]["environment"].update(NODES_EXCLUDE=json.dumps(sorted(BLOCKED_NODES-{"n8n-nodes-base.httpRequest"})))),
          ("open_code_node",lambda d:d["services"]["n8n"]["environment"].update(NODES_EXCLUDE=json.dumps(sorted(BLOCKED_NODES-{"n8n-nodes-base.code"})))),
          ("open_file_node",lambda d:d["services"]["n8n"]["environment"].update(NODES_EXCLUDE=json.dumps(sorted(BLOCKED_NODES-{"n8n-nodes-base.readWriteFile"})))),
          ("duplicate_node",lambda d:d["services"]["n8n"]["environment"].update(NODES_EXCLUDE=json.dumps(sorted(BLOCKED_NODES)+["n8n-nodes-base.code"]))),
          ("allowed_hostnames",lambda d:d["services"]["n8n"]["environment"].update(N8N_SSRF_ALLOWED_HOSTNAMES="*")),
          ("allowed_ip",lambda d:d["services"]["n8n"]["environment"].update(N8N_SSRF_ALLOWED_IP_RANGES="0.0.0.0/0")),
          ("public_api_key",lambda d:d["services"]["n8n"]["environment"].update(N8N_API_KEY="synthetic")),
          ("model_credential",lambda d:d["services"]["n8n"]["environment"].update(OPENAI_API_KEY="synthetic")),
          ("secret_in_env",lambda d:d["services"]["n8n"]["environment"].update(N8N_ENCRYPTION_KEY="synthetic")),
          ("host_port",lambda d:d["services"]["n8n"].update(ports=["0.0.0.0:5678:5678"])),
          ("db_host_port",lambda d:d["services"]["n8n-db"].update(ports=["0.0.0.0:5432:5432"])),
          ("external_network",lambda d:d["networks"]["n8n_apps_private"].update(internal=False)),
          ("new_external_network",lambda d:d["networks"].update({"operator_bridge":{"internal":False}})),
          ("network_mode_host",lambda d:d["services"]["n8n"].update(network_mode="host")),
          ("add_extrahost",lambda d:d["services"]["n8n"].update(extra_hosts=["metadata:169.254.169.254"])),
          ("add_dns",lambda d:d["services"]["n8n"].update(dns=["8.8.8.8"])),
          ("break_drop",lambda d:d["services"]["n8n"].update(cap_drop=[])),
          ("leave_profile",lambda d:d["services"]["n8n"].update(profiles=[])),
          ("change_db_user",lambda d:d["services"]["n8n"]["environment"].update(DB_POSTGRESDB_USER="postgres")),
          ("expand_exposed_ports",lambda d:d["services"]["n8n"].update(expose=["5678","5679"])),
        ]
        for name,change in mutants:
            with self.subTest(name=name),self.assertRaises(Rejected):
                d=copy.deepcopy(candidate())
                change(d)
                check_compose(d)

if __name__=="__main__":
    unittest.main()
