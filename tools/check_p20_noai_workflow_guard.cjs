'use strict';
// P20: verify the exact pinned AUTO-IA n8n workflow without executing it.
// No browser, network, provider or n8n runtime is started by this script.
const fs = require('node:fs');
const crypto = require('node:crypto');
const assert = require('node:assert/strict');
const path = require('node:path');

const FILE = path.join(__dirname,'../_auto_ia_pinned_source/tools/auto_ia/demo/n8n-noai-demo.workflow.json');
const EXPECTED_BLOB_SHA = '513359b15149a74ef534c9ba1939e3e37767c6bf';
const source = fs.readFileSync(FILE);
function gitBlobSha(bytes){
  return crypto.createHash('sha1')
    .update('blob '+bytes.length+'\0').update(bytes).digest('hex');
}
function inspect(workflow) {
  if (!workflow || typeof workflow !== 'object') return false;
  if (workflow.active !== false || !Array.isArray(workflow.nodes) || workflow.nodes.length !== 2) return false;
  const [trigger, calculation] = workflow.nodes;
  if (trigger.type !== 'n8n-nodes-base.manualTrigger'
      || calculation.type !== 'n8n-nodes-base.code') return false;
  if (workflow.nodes.some(n => Object.hasOwn(n,'credentials') || Object.hasOwn(n,'webhookId'))) return false;
  if (Object.keys(workflow.connections || {}).length !== 1) return false;
  if (!Object.hasOwn(workflow.connections,'Disparo manual — somente laboratório')) return false;
  if (workflow.connections['Disparo manual — somente laboratório']?.main?.[0]?.[0]?.node !== 'Calcular funil sem IA') return false;
  if (calculation.parameters?.mode !== 'runOnceForAllItems') return false;
  const code = calculation.parameters?.jsCode;
  if (typeof code !== 'string' || code.length > 1600) return false;
  // The original AUTO-IA-12 checker used an escaped word boundary.
  // This additional independent guard uses a real JS RegExp word boundary.
  if (/\b(?:fetch|axios|require|import|eval|Function|XMLHttpRequest|WebSocket|process|globalThis|openai|anthropic|groq|billing|https?)\b/i.test(code)) return false;
  for (const text of [
    "operation: 'funnel.calculate'",
    "decision: 'NO_AI'",
    'ai_api_calls: 0',
    'ai_tokens: 0',
    'commercial_credits_debited: 0',
    'operational_authorization: false',
    "mode: 'SYNTHETIC_MANUAL_ONLY'",
    'weekly_visits: 120',
    'lead_rate_percent: 10',
    'customer_rate_percent: 20',
  ]) if (!code.includes(text)) return false;
  if (!workflow.settings || workflow.settings.executionOrder !== 'v1') return false;
  if (workflow.pinData && Object.keys(workflow.pinData).length > 0) return false;
  return true;
}
assert.equal(gitBlobSha(source),EXPECTED_BLOB_SHA,'AUTO-IA pinned workflow changed');
const baseline = JSON.parse(source.toString('utf8'));
assert(inspect(baseline),'canonical workflow fails safe parser');
let checks=1;
function mutated(description,change){
  const m=structuredClone(baseline);
  change(m);
  assert(!inspect(m),'mutation escaped guard: '+description);
  checks++;
}
mutated('autostart',x=>{x.active=true});
mutated('network node',x=>{x.nodes.push({type:'n8n-nodes-base.httpRequest'})});
mutated('injected credentials',x=>{x.nodes[1].credentials={openAiApi:{id:'fake'}}});
mutated('network request',x=>{x.nodes[1].parameters.jsCode += "\nfetch('https://invalid.example')"});
mutated('dynamic import',x=>{x.nodes[1].parameters.jsCode += "\nimport('node:https')"});
mutated('tokens debit',x=>{x.nodes[1].parameters.jsCode=x.nodes[1].parameters.jsCode.replace('ai_tokens: 0','ai_tokens: 100')});
mutated('commercial debit',x=>{x.nodes[1].parameters.jsCode=x.nodes[1].parameters.jsCode.replace('commercial_credits_debited: 0','commercial_credits_debited: 1')});
mutated('grant authority',x=>{x.nodes[1].parameters.jsCode=x.nodes[1].parameters.jsCode.replace('operational_authorization: false','operational_authorization: true')});
mutated('connection swapped',x=>{x.connections['Disparo manual — somente laboratório'].main[0][0].node='Webhook'});
mutated('sample customer data',x=>{x.pinData={lead:{email:'person@example.invalid'}}});
console.log('AIA_P20_NOAI_WORKFLOW_GUARD_PASS checks='+checks+' pin='+EXPECTED_BLOB_SHA+' ai_tokens=0 no_execution=true NO_GO');
