'use strict';
const fs=require('node:fs');
const assert=require('node:assert/strict');
const path=require('node:path');
const workflow=JSON.parse(fs.readFileSync(
  path.join(__dirname,'n8n-noai-demo.workflow.json'),'utf8'));
assert.equal(workflow.active,false);
assert.equal(workflow.nodes.length,2);
assert.deepEqual(workflow.nodes.map(n=>n.type),
 ['n8n-nodes-base.manualTrigger','n8n-nodes-base.code']);
assert.deepEqual(Object.keys(workflow.connections),
 ['Disparo manual — somente laboratório']);
assert.equal(workflow.connections['Disparo manual — somente laboratório'].main[0][0].node,
 'Calcular funil sem IA');
assert(!workflow.nodes.some(n=>Object.prototype.hasOwnProperty.call(n,'credentials')));
const code=workflow.nodes[1].parameters.jsCode;
assert.equal(workflow.nodes[1].parameters.mode,'runOnceForAllItems');
assert(!/\\b(?:fetch|axios|require|import|process|XMLHttpRequest|https?|openai|anthropic|groq|billing)\\b/i.test(code),
 'workflow code unexpectedly references external APIs');
const result=new Function(code)();
assert.equal(result.length,1);
assert.deepEqual(result[0].json,{
 schema:'AUTO_IA_DEMO_RESULT_V1',
 decision:'NO_AI',
 tenant_ref:'SYNTHETIC_TENANT_DEMO',
 event_ref:'SYNTHETIC_EVENT_DEMO',
 visits:120,
 projected_leads:12,
 projected_customers:2,
 ai_api_calls:0,
 ai_tokens:0,
 commercial_credits_debited:0,
 operational_authorization:false,
 mode:'SYNTHETIC_MANUAL_ONLY'
});
console.log('AUTO_IA_12_NO_AI_WORKFLOW_PASS manual_only=1 no_provider=1 no_billing=1');
