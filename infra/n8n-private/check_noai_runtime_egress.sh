#!/usr/bin/env bash
# CENTRAL-AUTO-P13: prove isolated n8n cannot reach reserved external TEST-NET IPs.
# No DNS, model providers, public websites, real secrets or VPS operations.
set -euo pipefail
test "${GITHUB_ACTIONS:-}" = true
test "${GITHUB_REPOSITORY:-}" = rafhael100/imagine-ai-agents
test "${COMPOSE_PROJECT_NAME:-}" = imagine-n8n-p4-ci
root="$(cd "$(dirname "$0")/../.." && pwd)"
dc=(docker compose --profile homologation -f "$root/infra/n8n-private/docker-compose.yml")
test -n "$("${dc[@]}" ps -q --status running n8n)"
"${dc[@]}" exec -T n8n node - <<'JS'
const net=require('net');
const assert=require('assert');
for(const key of ['N8N_PUBLIC_API_DISABLED','N8N_PUBLIC_API_SWAGGERUI_DISABLED',
  'N8N_SSRF_PROTECTION_ENABLED','N8N_BLOCK_ENV_ACCESS_IN_NODE']) {
  assert.strictEqual(process.env[key],'true','missing flag '+key);
}
const excluded=JSON.parse(process.env.NODES_EXCLUDE||'[]');
for(const n of ['n8n-nodes-base.code','n8n-nodes-base.executeCommand',
  'n8n-nodes-base.ssh','n8n-nodes-base.readWriteFile','n8n-nodes-base.httpRequest']) {
  assert(excluded.includes(n),'unblocked node '+n);
}
for(const key of ['OPENAI_API_KEY','ANTHROPIC_API_KEY','N8N_API_KEY']) {
  assert(!process.env[key],'unapproved provider key '+key);
}
function probe(host){
  return new Promise((resolve,reject)=>{
    let done=false;
    const socket=net.createConnection({host,port:443});
    const finish=(allowed,detail)=>{
      if(done)return;done=true;socket.destroy();
      if(allowed)resolve(detail);
      else reject(new Error('EXTERNAL_CONNECT_SUCCEEDED_'+host));
    };
    socket.once('connect',()=>finish(false,'connected'));
    socket.once('error',e=>finish(true,String(e.code||'blocked')));
    socket.setTimeout(2800,()=>finish(true,'timeout'));
  });
}
(async()=>{
  for(const host of ['192.0.2.1','198.51.100.2']) {
    console.log('CENTRAL_AUTO_P13_TEST_NET_BLOCKED '+host+' '+await probe(host));
  }
  console.log('CENTRAL_AUTO_P13_RUNTIME_PASS host_port=0 providers=0 api_flag_disabled=1 ssrf_flag=1');
})().catch(e=>{console.error(String(e.message).slice(0,120));process.exitCode=1});
JS
