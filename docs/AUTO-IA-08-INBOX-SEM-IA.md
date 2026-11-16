# AUTO-IA-08 — Inbox PostgreSQL para futuros eventos n8n sem IA

Complementa [AUTO-IA-07](AUTO-IA-07-PG16-RLS-ATOMICIDADE.md) e o contrato sintético [AUTO-IA-05](AUTO-IA-05-CONTRATO-PRIVADO-N8N.md).

## Objetivo
Receber **referências fictícias de eventos** sem promover o n8n a autoridade de negócio, impedir repetição por evento e nonce em PostgreSQL, demonstrar isolamento de empresas por papéis de banco e rejeitar qualquer operação classificada como candidata à IA.

Inclui o catálogo fechado de 16 operações \`NO_AI\` dos oito produtos, com teste automático de igualdade com o catálogo PHP AUTO-IA-01. Oito sessões SQL enviam o mesmo nonce e apenas uma pode gravá-lo. Não há cliente HTTP, fila publicada, worker, callbacks, segredo de API, modelo ou débito comercial.

A função \`receive_no_ai\` utiliza relógio do próprio PostgreSQL para validar janela de validade e não recebe um timestamp de verificação autodeclarado pelo chamador. Aceita apenas referências sintéticas e hash de payload. O registro \`LAB_RECEIVED_NO_DELIVERY\` significa **persistido em banco de CI**, não entregue, processado ou autorizado.

## Bloqueios antes da operação
- O papel de sessão de CI **não prova IAM de empresa real**. A autenticação de serviço precisa derivar tenant de identidade validada pelo CRM/Portal, com revogação, auditoria e credenciais protegidas.
- Apenas um contrato de demonstração com referências e hashes está implementado. Não existe comprovação de origem/prova de payload assinada ou transporte privado S2S, nem entrega para n8n.
- Nenhum workflow poderá acionar diretamente provedores de IA, aplicar mutação no CRM, publicar Sites ou gerar cobrança. Os serviços responsáveis precisam validar permissões antes e depois da execução.
- Operação exige integração real em rede privada, antifraude, idempotência distribuída, alertas e DLQ, backup/restauração e revisão AppSec/DBA/LGPD.
- A VPS e os dados reais permanecem inalterados.

**DRAFT / NO-MERGE / NO-DEPLOY / NO-N8N / NO-AI-API.**
