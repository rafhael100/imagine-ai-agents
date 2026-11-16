# F238 — envelope HTTP sintético compatível com o CRM F246

**15/11/2026 — OFFLINE / NO-GO / NO-MERGE / NO-DEPLOY / NO-COST**

## Entrega
A classe `MockSyntheticHttpEnvelope` transforma evidências **fictícias**, previamente verificadas pelo `MockSyntheticEvidenceGate` F235, em estrutura de ensaio com método `POST`, cabeçalhos `content-type: application/json`, `x-aia-claims` e `x-aia-signature`, corpo JSON canônico e marcadores `transmission_authorized=false`, `real_authorization=false` e `operational_decision=NO_GO`.

A prova Ed25519, a correspondência da empresa/recurso/campos e o replay em memória precisam passar antes de montar a estrutura. Rejeita alteração de assinatura, formato, cabeçalhos com controles de quebra de linha, vencimento, tenant e política. O teste usa chave de teste publicamente determinística e **32 cenários negativos**, mais teste de replay e um ensaio sintético válido.

## Contrato do CRM

Os nomes e formatos dos três cabeçalhos seguem o formato esperado pelo `AiaStagingDeniedIngressLab` na PR CRM #602, commit `6e4e99879e456aea19124065b105cd989bbf1274`. O receptor de laboratório responde **sempre HTTP 404** mesmo quando o envelope assinado é bem formado. A F238 **não executa HTTP** nem verifica um servidor CRM de verdade.

A classe não recebe URL, rota, transporte, token, sessão, credencial, banco ou cliente real. Nenhum retorno de F238 é permissão para emitir requisições. O mecanismo anti-replay desta classe depende da F235 em memória; em produção a única autoridade deverá ser o CRM após identidade real, nonce persistente/atômico, IAM/ACL, RLS e revogação.

## Próximos passos
F239 validará o contrato de resposta HTTP negativa. F240 executará uma prova combinada de envelope → negativa, sem rede. Só depois será possível revisar a compatibilidade com o CRM funcional #614. Os 14 gates, 7 decisões e 13 cenários reais da CRM #57 permanecem não homologados.
