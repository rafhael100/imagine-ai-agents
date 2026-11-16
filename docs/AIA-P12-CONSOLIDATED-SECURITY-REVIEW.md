# P12 — Revisão consolidada de crédito e automações P8–P11

**16/11/2026 — candidata técnica empilhada sobre P7 #352. Risco operacional: NO-GO.**

O produto tem interface navegável com Assistente Comercial P0–P3 e base técnica P4–P7 consolidada na [PR #352](https://github.com/rafhael100/imagine-ai-agents/pull/352), com R01 integral aprovado (879 casos/120 arquivos PHP) e PostgreSQL16 descartável. As PRs P8–P11 agora fazem parte de **uma única candidata de revisão**:

- **P8 [#354](https://github.com/rafhael100/imagine-ai-agents/pull/354):** reserva de créditos fictícios sob lock transacional; cota de dez workers; timeout mantém saldo; reconciliação conservadora.
- **P9 [#361](https://github.com/rafhael100/imagine-ai-agents/pull/361):** corrige ACK atrasado quando o mesmo worker volta a processar evento n8n; exige fencing por número exato da tentativa.
- **P10 [#362](https://github.com/rafhael100/imagine-ai-agents/pull/362):** introduz estado `DISPATCHED`, proíbe debitar antes do despacho e recusa reutilizar pedidos após despacho, cancelamento ou finalização.
- **P11 [#363](https://github.com/rafhael100/imagine-ai-agents/pull/363):** exige comprovante de uso sintético canônico e assinado Ed25519 sob public key de confiança gerenciado pelo harness, armazenando hash do recibo; rejeita assinatura falsa, expirado ou escopo divergente.

O workflow `ci-p12-unified-security.yml` executa P4/P5/P6/P8/P9/P10/P11 e a demo P0–P2 na **mesma HEAD e mesmo runner com PostgreSQL16**, embora em tabelas de laboratório separadas. O workflow existente R01 é acionado na candidata para preservar os 879 testes oficiais e as verificações do código fonte.

## Revisão independente exigida antes de qualquer uso real

Os testes acima **não comprovam** integração operacional ou acesso autorizado. Os adaptadores críticos estão deliberadamente em `tools/labs`, apenas em PG16 efêmero, sob flag CI, com credenciais públicas e dados sintéticos. Não há provider de IA, cobrança monetária, sessão de usuário, tenant real, TLS, autenticação n8n, nonce durável de S2S, segredo, chave de fornecedor nem deploy de VPS.

É necessária uma revisão independente das seguintes decisões **antes** de mover qualquer código para o serviço: confiabilidade de identidade do Portal e API, roles e RLS PostgreSQL por empresa, vínculo de lead/campo/finalidade a contrato CRM autorizado, provedor e chave pública distribuída por canal confiável, consumo observado reconciliado com fatura, suporte a timeouts/valores maiores que estimativa, política de retenção/auditoria e backup/rollback. A central n8n precisará de autenticação própria, rede/egress administrada e idempotência no consumidor.

A PR CRM [OP-01 #57](https://github.com/rafhael100/imagine-crm/pull/57) continua com 14 gates, 7 decisões e 13 cenários operacionais ainda não homologados. **Nenhuma CI verde substitui esses controles ou autoriza merge, deploy, uso de dados ou cobrança.**

Regra de promoção: a PR P12 é revisão cumulativa P8–P11; as PRs históricas individuais não devem ser unidas separadamente à `main`. O monitoramento de conflitos deve continuar nos fluxos GitHub, preservando a VPS sem alterações.
