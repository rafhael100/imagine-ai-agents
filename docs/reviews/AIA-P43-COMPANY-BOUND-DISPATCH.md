# AIA-P43 — Revogação do vínculo CRM/Portal bloqueia lease e ACK

16/11/2026. Incremento empilhado sobre P42 (#412), sem tocar `main` ou VPS.

**Lacuna:** P42 validava o vínculo entre `company_id` mTLS e `tenant_ref` e registrava o evento de forma atômica. Mas uma revogação posterior à admissão não era checada no momento do *lease* ou ACK. Isso poderia deixar um evento pendente ou já reservado avançar com uma autorização de empresa que não está mais ativa.

**Implementação de laboratório:** `PgCompanyDispatchGuardLab` revalida a linha revogável de P41 usando `SELECT ... FOR SHARE` e exige uma linha única de prova de transporte P42 vinculada ao mesmo `company_id`, `tenant_ref`, `event_ref` e hash do payload. Tanto o novo lease condicionado P28 quanto o ACK condicionado P30 realizam essas verificações **dentro de suas transações**, mantendo os bloqueios até confirmar ou reverter. Isso serializa revogação e confirmação, eliminando a janela TOCTOU desse recorte. O método `PgCompanyBoundSignedDispatchLab` é um caminho explícito para P42 com **recibo Ed25519 V2 e evidência persistida P38**, sem oferecer ACK unsigned como decisão final.

**Testes:** evento pode receber lease e confirmação assinada enquanto ativo; outra revisão da associação é negada; uma prova P42 adulterada não incrementa tentativas; revogação após stage bloqueia novos leases; revogação após lease bloqueia ACK assinado; nenhuma linha falsa de evidência é criada. Os métodos antigos P28/P30 seguem funcionando somente nos outros ensaios; **não são convertidos em endpoints operacionais nem devem ser utilizados para eventos P42 em produção**.

**Limitações críticas:** associação, assinatura e estado IAM são fictícios, e `company_id` aceito como parâmetro não comprova identidade do chamador. Falta validação direta da identidade atual do serviço originador, política da operação, consentimento, ciclo de vida do n8n real, recebimento de executor e política operacional. A CI não instala, publica, cobra, faz merge ou chama APIs pagas.

**DRAFT / NO-GO / NO-MERGE / NO-DEPLOY / NO-SPEND.**
