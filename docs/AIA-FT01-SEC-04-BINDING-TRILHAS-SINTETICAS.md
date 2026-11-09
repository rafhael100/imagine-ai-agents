# FT01/SEC-04 — vínculo de intenção e sequência em trilhas sintéticas

**Escopo:** revisão de consistência de eventos PHP em memória, sem CRM real, IAM, credenciais, dados de clientes, serviços externos ou deploy.

## Lacuna e correção

O revisor `ReadOnlyTraceReview::inspect` validava identificadores de intenção **quando presentes**, mas não exigia que o evento `SUBMITTED` carregasse `intent` e `step`. Com eventos subsequentes e uma prova fictícia compatível, uma trilha incompleta podia ser tratada como confirmação de laboratório.

O revisor passa a exigir referências explícitas de etapa/intenção nos eventos `APPROVAL_DENIED`, `SUBMITTED`, `STATUS_REQUESTED`, `TIMEOUT` e estados de efeito. Quando uma intenção é conhecida, sua etapa deve constar anteriormente em um evento `STEP_CREATED`, antes de `SUBMITTED`. Falta de vínculo retorna `INDETERMINADO / INTENT_CONFLICT`. O caminho normal de trilhas íntegras permanece igual.

## Evidência e limites

Seis testes PHP `U03-13` a `U03-18` cobrem envio sem vínculo, etapa ausente, etapa de outro identificador, etapa tardia, consulta de status desacoplada e timeout sem referência. A suíte oficial sobe de 240 para **246 casos**, mantendo **74 arquivos PHP de lint**, e a CI da dev exige SHA exato.

**Importante:** estes são registros fictícios, não assinados nem persistidos. O revisor não prova origem ou autenticidade de logs; a mudança não concede permissões nem habilita operações de CRM. O [PR #57 do CRM](https://github.com/rafhael100/imagine-crm/pull/57) segue DRAFT e os gates R01-G01..G11/CORE/SEG/AUT permanecem abertos. Estado operacional: **NO-GO**.
