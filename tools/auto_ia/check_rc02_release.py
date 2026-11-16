#!/usr/bin/env python3
"""AUTO-IA RC02 independent static review. NOT production security certification."""
from __future__ import annotations
import json
from pathlib import Path

ROOT=Path(__file__).resolve().parents[2]
paths=[
    "tools/auto_ia/AutoIaRouter.php",
    "tools/auto_ia/MockS2SEnvelopeVerifier.php",
    "tools/auto_ia/pg/001_auto_ia_pg_lab.sql",
    "tools/auto_ia/pg/002_no_ai_inbox.sql",
    "tools/auto_ia/pg/003_signed_ingress_lab.sql",
    "infra/n8n-private/docker-compose.yml",
    "tools/auto_ia/demo/n8n-noai-demo.workflow.json",
]
assert all((ROOT/path).is_file() for path in paths)
sql=(ROOT/"tools/auto_ia/pg/003_signed_ingress_lab.sql").read_text()
assert "REVOKE EXECUTE ON FUNCTION auto_ia_lab.receive_no_ai" in sql
assert "public.hmac" in sql and "LAB_DENY_MAC" in sql
assert "LAB_DENY_SERVICE_TENANT" in sql
assert "LAB_DENY_AI_OR_UNKNOWN_OPERATION" in (ROOT/"tools/auto_ia/pg/002_no_ai_inbox.sql").read_text()
assert "FOR UPDATE" in (ROOT/"tools/auto_ia/pg/001_auto_ia_pg_lab.sql").read_text()
compose=(ROOT/"infra/n8n-private/docker-compose.yml").read_text()
assert "\n    ports:" not in compose, "n8n must not publish host ports"
assert 'expose:\n      - "5678"' in compose, "internal n8n UI exposure missing"
assert compose.count("internal: true")==2
assert "POSTGRES_PASSWORD" in compose
assert "N8N_ENCRYPTION_KEY" in compose
workflow=json.loads((ROOT/"tools/auto_ia/demo/n8n-noai-demo.workflow.json").read_text())
assert workflow["active"] is False
assert len(workflow["nodes"])==2
assert {n["type"] for n in workflow["nodes"]}=={
    "n8n-nodes-base.manualTrigger","n8n-nodes-base.code"}
docs=(ROOT/"docs/AUTO-IA-RC02-CANDIDATA-INTEGRADA.md").read_text()
assert "NO-GO" in docs and "VPS" in docs and "P7" in docs
report={
 "schema":"AUTO_IA_RC02_REVIEW_V1",
 "catalogued_stages":12,
 "production_decision":"NO_GO",
 "tenant_iam_real":False,
 "n8n_installed":False,
 "vps_changed":False,
 "provider_api_enabled":False,
 "commercial_credit_debit_enabled":False,
 "independent_review_complete":False,
 "parallel_p7_merged":False,
 "static_review":"PASS"
}
print(json.dumps(report,ensure_ascii=False,sort_keys=True))
assert report["production_decision"]=="NO_GO"
