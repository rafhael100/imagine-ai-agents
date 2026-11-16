# AIA-P41 — Vínculo explícito company_id CRM/Portal → tenant_ref AI Agents

16/11/2026. Complemento da P40 (#410), **somente laboratório CI com dados fictícios**.

A prova HTTPS/mTLS P7 utiliza um `company_id` UUID do CRM/Portal; os contratos de Agents usam `tenant_ref` sintético. Não é seguro tratá-los como a mesma identidade ou aceitar tenant indicado no corpo de evento.

A P41 cria tabela de mapeamento aprovada apenas como fixture, com unicidade de UUID e tenant, estado ativo e revisão monotônica. O pré-check `PgSyntheticCompanyTenantBridgeLab::inspect` compara empresa e tenant esperados com a tabela e com a linha efetiva de admissão de nonce gravada pelo código original CENTRAL-AUTO-P7 após a execução HTTPS/mTLS em CI. Exige issuer, audience, UUID, request, nonce, hash e janela curta de tempo; empresa B, que não foi admitida no P7, não ganha acesso apenas por estar mapeada. `SELECT ... FOR SHARE` impede revogação concorrente de completar enquanto o pré-check está aberto.

Testes negativos: tentativa de mapear UUID já vinculado a outro tenant, troca de empresa, nonce/hash adulterados, revisão incorreta, prova velha e revogação. O retorno é `LAB_MATCHED_BOUNDARY_NO_EXECUTION`, sem URL, token, operação de escrita, IA ou n8n.

**Limite essencial:** ao terminar o pré-check a transação é encerrada. Sua aprovação **NÃO autoriza** um `stage` subsequente, pois a revogação pode ocorrer no intervalo. Uma futura etapa deverá levar essa verificação para dentro da mesma transação de admissão do evento, sempre com IAM verdadeiro e autoridade do produto de origem. Não há tradução operacional, cadastro real de empresa, aceite CRM/Portal ou despliegue.

**DRAFT / NO-GO / NO-MERGE / NO-DEPLOY / NO-SPEND.**
