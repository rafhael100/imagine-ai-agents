#!/usr/bin/env node
"use strict";
/** P1: execute the actual standalone HTML script through an in-memory DOM.
 * No jsdom, browser, network, filesystem writes or provider SDK required.
 */
const fs=require("node:fs");
const vm=require("node:vm");
const assert=require("node:assert/strict");
const html=fs.readFileSync("examples/p0-assistente-comercial-demo.html","utf8");
const scripts=[...html.matchAll(/<script\b[^>]*>([\s\S]*?)<\/script>/g)];
assert.equal(scripts.length,1,"Exactly one self-contained script is required");
const source=scripts[0][1];
assert.doesNotThrow(()=>new vm.Script(source,{filename:"p1-workbench.js"}));
for(const forbidden of ["fetch(","XMLHttpRequest","WebSocket(","sendBeacon(","localStorage",
 "sessionStorage","indexedDB","document.cookie","innerHTML","eval(","new Function("]){
 assert.ok(!source.includes(forbidden),"Forbidden browser escape: "+forbidden);
}
assert.match(html,/connect-src 'none'/);
assert.match(html,/form-action 'none'/);
assert.match(html,/id="draft-name"/);
assert.match(html,/id="draft-template"/);
assert.match(html,/id="agent-select"/);

class Element {
 constructor(tag="div",id=""){
   this.tag=tag;this.id=id;this.value="";this.textContent="";
   this.children=[];this.hidden=false;this.disabled=false;
   this.dataset={};this.attrs={};this.listeners={};this.className="";
 }
 get options(){return this.children.filter(x=>x.tag==="option");}
 append(...nodes){this.children.push(...nodes);return undefined;}
 replaceChildren(...nodes){this.children=[...nodes];}
 addEventListener(event,cb){(this.listeners[event]??=[]).push(cb);}
 setAttribute(k,v){this.attrs[k]=String(v);}
 removeAttribute(k){delete this.attrs[k];}
 click(){for(const cb of (this.listeners.click||[])){cb();}}
 input(){for(const cb of (this.listeners.input||[])){cb();}}
}
const idCache=new Map();
const get=id=>{
 if(!idCache.has(id)){idCache.set(id,new Element("div",id));}
 return idCache.get(id);
};
for(const [id,value] of Object.entries({
 visits:"1000","lead-rate":"5","close-rate":"10",
 "draft-name":"Consultor Comercial","draft-template":"sales",
 "lead-select":"A",goal:"next","agent-select":"PRESET_SALES",
})){get(id).value=value;}
const navs=["overview","agents","simulate","approvals","integrations"].map(value=>{
 const element=new Element("button");element.dataset.nav=value;return element;
});
const switches=[new Element("button"),new Element("button")];
switches[0].dataset.switch="simulate";switches[1].dataset.switch="simulate";
const document={
 getElementById:get,
 createElement:tag=>new Element(tag),
 createTextNode:value=>({tag:"#text",textContent:value}),
 querySelectorAll:selector=>{
  if(selector==="[data-nav]")return navs;
  if(selector==="[data-switch]")return switches;
  throw new Error("Unexpected DOM selector");
 }
};
const sandbox={document,Intl,Number,String,Math,Array,Date,Object};
vm.runInNewContext(source,sandbox,{timeout:1500});
assert.equal(get("funnel-clients").textContent,"5");
assert.equal(get("run-quota").textContent,"0 de 8 execuções simuladas nesta sessão");
assert.equal(get("agent-select").options.length,1);
assert.equal(get("approval-items").children.length,2);

// Form validation and memory-only draft persistence.
get("draft-name").value="<img onerror=x>";
get("save-draft").click();
assert.equal(get("agent-select").options.length,1);
assert.match(get("draft-feedback").textContent,/recusado/);
get("draft-name").value="Consultor Comercial";
get("save-draft").click();
assert.equal(get("agent-select").options.length,2);
assert.equal(get("draft-list").children.length,1);
assert.equal(get("agent-select").value,"SYNTHETIC_AGENT_01");
get("save-draft").click(); // duplicate name must be rejected
assert.equal(get("draft-list").children.length,1);
for(let index=2;index<=5;index++){
 get("draft-name").value="Consultor "+index;
 get("draft-template").value=index===2?"analysis":"sales";
 get("save-draft").click();
}
assert.equal(get("draft-list").children.length,5);
assert.equal(get("agent-select").options.length,6);
assert.equal(get("save-draft").disabled,true);
get("draft-name").value="Consultor 6";
get("save-draft").click();
assert.equal(get("draft-list").children.length,5);

// A read-only analysis template must never queue external/approval actions.
get("agent-select").value="SYNTHETIC_AGENT_02";
get("goal").value="next";
get("run-demo").click();
assert.equal(get("approval-items").children.length,2);
assert.match(get("run-text").textContent,/Estágio atual:/);
assert.equal(get("run-quota").textContent,"1 de 8 execuções simuladas nesta sessão");

// Suggestion template requires a human decision; still does NOTHING externally.
get("agent-select").value="SYNTHETIC_AGENT_01";
get("goal").value="next";
get("run-demo").click();
assert.equal(get("approval-items").children.length,3);
const row=get("approval-items").children[0];
const buttons=row.children.filter(x=>x.className==="controls")[0].children;
assert.equal(buttons.length,2);
buttons[0].click();
const approved=get("approval-items").children[0];
assert.match(approved.children[0].children[1].textContent,/APROVADO_EM_ENSAIO/);
assert.equal(approved.children.some(x=>x.className==="controls"),false);

// Budget exhaustion is enforced in the UI session.
for(let i=0;i<6;i++)get("run-demo").click();
assert.equal(get("run-quota").textContent,"8 de 8 execuções simuladas nesta sessão");
assert.equal(get("run-demo").disabled,true);
const count=get("approval-items").children.length;
get("run-demo").click();
assert.equal(get("approval-items").children.length,count);
assert.match(get("run-text").textContent,/recusada/);
assert.ok(get("audit-log").children.length>0);
assert.ok(get("audit-log").children.length<=10);
console.log("P1_AGENTS_WORKBENCH_DOM_PASS drafts=5 runs=8 approvals=local_only unsafe_effects=0");
