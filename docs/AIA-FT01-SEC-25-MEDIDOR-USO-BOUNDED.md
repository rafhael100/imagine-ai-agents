# FT01 / SEC-25 — Limites auditáveis no medidor de uso

**Data:** 10/11/2026 · **Escopo:** PHP e dados sintéticos, sem provedores ou serviços externos · **Operação real: NO-GO**.

## Problema corrigido

O `MockUsageLedger` mantinha mapas de eventos de uso, tentativas, medições e histórico de auditoria sem um teto explícito. Em execução prolongada, registros sintéticos poderiam crescer indefinidamente, enquanto um orçamento aparentemente saudável poderia deixar de refletir a capacidade de registrar mais eventos.

## Nova política fail-closed

- Máximo de **512 eventos materiais** distintos de uso, **256 tentativas** e **1.024 eventos de auditoria** por instância do laboratório.
- Nenhum registro antigo é descartado. Isso preserva verificação de duplicidade e incerteza de consumo em vez de transformar uma chave antiga em uma cobrança nova.
- `record()`, `markUnknown()` e `reconcile()` verificam o espaço necessário **antes** de alterar medições ou registrar inconsistências.
- Ao atingir o limite, uma nova operação que dependa de registro emite `USAGE_CAPACITY_DENIED`. Uma operação idempotente já registrada continua retornando o mesmo resultado, sem novo evento.
- `budget()` deixa de indicar `SYNTHETIC_WITHIN_QUOTA` quando o medidor não consegue acomodar novas medições; devolve `INDETERMINADO / MEASUREMENT_CAPACITY_EXCEEDED`. Essa função já era apenas observação técnica, nunca concessão de autorização.
- A auditoria mantém os dados minimizados e não envia notificações, mensagens, faturas nem chamadas reais.

## Regressões U47-01..06

Os testes abrangem esgotamento de tentativas, repetição idempotente após limite, 512 eventos sem expulsão de histórico, timeout de tentativa nova rejeitado, prova contraditória sem alocação parcial e 1.024 eventos de auditoria com recusa segura de novo material. Usam somente referências sintéticas.

**Gate esperado:** 433 testes PHP, lint de 82 arquivos PHP e verificadores Python offline, com CI aprovada no HEAD do PR e validação pós-merge.

## Decisão operacional

A correção não constitui billing transacional, persistência, autorização de IA, trilha de auditoria durável ou rate limiting distribuído. A integração real exigirá o contrato e os controles aprovados por CRM CORE/SEG/AUT, Privacidade, Segurança e Direção. O [CRM PR #57](https://github.com/rafhael100/imagine-crm/pull/57) segue OPEN/DRAFT; R01-G01..G11 e os 14 gates do [pacote EVID-19](AIA-R01-EVID-19-PACOTE-BILATERAL-OP01.md) permanecem sem aceite. Sem `main`, VPS, produção, dados de clientes, credenciais, gastos ou novos fornecedores.
