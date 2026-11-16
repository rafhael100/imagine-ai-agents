# AIA-P31 — Ciclo funcional protegido de automações, somente laboratório

16/11/2026. Candidata empilhada sobre P30 (#393).

A classe `PgGuardedAutomationCycleLab` combina a reserva transacional P17, a barreira de leitura/lease P28, a confirmação restrita P30 e a auditoria P23/P25 **sem expor operações de enqueue P6, claim legado, requisição paga ou transporte ao n8n**.

O teste em PostgreSQL16 descartável demonstra o fluxo:
1. política sintética ativa + envelope firmado apenas no teste → stage atômico;
2. validação de ledger, payload, request e nonce → lease restrito de evento;
3. fencing de worker e tentativa + revalidação → ACK local não operacional;
4. leitura de consistência em duas empresas; bloqueios de repetição, revogação de fixture e órfão legado.

Todas as saídas declaram `NO_GO`, zero chamadas de IA e zero efeitos externos. A CI integrada executa novamente a pilha técnica P13–P30 no mesmo candidato.

**Limitações:** o encapsulamento melhora a ergonomia e reduz o risco de uso indevido dos métodos antigos, mas não remove APIs legadas nem aplica IAM real. Não entrega um webhook, não instala n8n, não prova consentimento nem substitui autenticação, auditoria de segurança independente ou homologação de produção.

**DRAFT / NO-GO / NO-MERGE / NO-DEPLOY.**
