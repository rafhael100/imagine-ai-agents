#!/usr/bin/env node
"use strict";
/**
 * P2 cross-language parity: compare six real PHP mock-domain suggestions with
 * the actual pure JS functions shipped in the offline UI. No network/SDK.
 */
const fs=require("node:fs");
const vm=require("node:vm");
const cp=require("node:child_process");
const assert=require("node:assert/strict");

const php=cp.spawnSync("php",["tools/check_p2_sales_domain.php","--fixtures"],
 {encoding:"utf8",timeout:10000});
assert.equal(php.status,0,php.stderr);
const fixture=JSON.parse(php.stdout);
const html=fs.readFileSync("examples/p0-assistente-comercial-demo.html","utf8");
const match=html.match(/<script\b[^>]*>([\s\S]*?)<\/script>/);
assert.ok(match,"One inline script required");
const source=match[1];
const before=source.split("// UI BOOT")[0];
assert.ok(before!==source,"Pure-functions boundary required");
const sandbox=vm.createContext(Object.create(null));
vm.runInContext(before,sandbox,{timeout:1000});
const {advice,createDraft,simulatedRun}=sandbox;
assert.equal(typeof advice,"function");
assert.equal(typeof createDraft,"function");
assert.equal(typeof simulatedRun,"function");

let cases=0;
for(const goal of ["next","summary"]){
 for(const lead of ["A","B","C"]){
  const actual=advice(lead,goal);
  const expected=fixture[goal][lead];
  assert.equal(actual.ref,expected.lead_ref);
  assert.equal(actual.stage,expected.stage);
  assert.equal(actual.text,expected.text);
  const run=simulatedRun([],"PRESET_SALES",lead,goal,0);
  assert.equal(run.requiresReview,expected.requires_review);
  assert.equal(run.suggestion.text,expected.text);
  assert.equal(run.used,1);
  cases++;
 }
}
const added=createDraft([],"Consultor Comercial","sales");
assert.equal(added.length,1);
assert.equal(added[0].id,"SYNTHETIC_AGENT_01");
assert.equal(createDraft(added,"consultor comercial","sales"),null);
assert.equal(createDraft(added,"<script>","sales"),null);
assert.equal(createDraft(added,"__proto__","sales"),null);
const analysis=createDraft(added,"Analista de Leads","analysis");
assert.equal(analysis.length,2);
const summary=simulatedRun(analysis,"SYNTHETIC_AGENT_02","C","next",1);
assert.equal(summary.requiresReview,false);
assert.equal(summary.used,2);
assert.equal(summary.suggestion.text,fixture.summary.C.text);
assert.equal(simulatedRun(analysis,"SYNTHETIC_AGENT_02","C","next",8),null);
assert.equal(simulatedRun(analysis,"SYNTHETIC_AGENT_98","A","next",0),null);
console.log("P2_PHP_JS_PARITY_PASS cases="+cases+" configs=2 max_runs=8 NO_GO");
