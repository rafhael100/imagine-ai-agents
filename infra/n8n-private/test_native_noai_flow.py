#!/usr/bin/env python3
"""CENTRAL-AUTO-P9 regression against native workflow policy tampering."""
import copy
import importlib.util
import json
from pathlib import Path

ROOT=Path(__file__).resolve().parent
spec=importlib.util.spec_from_file_location("native_guard",ROOT/"check_native_noai_flow.py")
guard=importlib.util.module_from_spec(spec)
spec.loader.exec_module(guard)
source=json.loads((ROOT/"workflows/native-funnel-noai.workflow.json").read_text())
assert guard.source_ok(source)
mutants=[
 ("code_node",lambda o:o["nodes"][2].update(type="n8n-nodes-base.code")),
 ("ai_node",lambda o:o["nodes"][2].update(type="@n8n/n8n-nodes-langchain.agent")),
 ("webhook_node",lambda o:o["nodes"][0].update(type="n8n-nodes-base.webhook")),
 ("active",lambda o:o.update(active=True)),
 ("credential",lambda o:o["nodes"][1].update(credentials={"openAiApi":{"id":"synthetic"}})),
 ("dynamic_http",lambda o:o["nodes"][2]["parameters"]["assignments"]["assignments"][3].update(value="={{ fetch('https://provider.example') }}")),
 ("price_credit",lambda o:o["nodes"][2]["parameters"]["assignments"]["assignments"][8].update(value=10)),
 ("math_changed",lambda o:o["nodes"][2]["parameters"]["assignments"]["assignments"][3].update(value="={{ 9999 }}")),
 ("unexpected_link",lambda o:o["connections"].clear()),
 ("code_node_version",lambda o:o["nodes"][2].update(typeVersion=4)),
 ("secret_field",lambda o:o["nodes"][2]["parameters"]["assignments"]["assignments"].append({
   "id":"SYNTHETIC","name":"api_key","value":"SYNTHETIC_SECRET","type":"string"
 })),
 ("private_data",lambda o:o.update(pinData={"secret":"SYNTHETIC"})),
]
for name,mutate in mutants:
    data=copy.deepcopy(source)
    mutate(data)
    try:
        guard.source_ok(data)
    except guard.Denied:
        continue
    raise AssertionError("P9_UNSAFE_MUTATION_ACCEPTED:"+name)
print("CENTRAL_AUTO_P9_NEGATIVE_PASS mutations="+str(len(mutants))+" no_code=1 no_billing=1")
