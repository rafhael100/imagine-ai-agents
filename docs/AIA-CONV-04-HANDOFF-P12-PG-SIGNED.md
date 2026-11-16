# AIA-CONV-04 — Ensaio de ponta a ponta P12 outbox → AUTO-IA inbox → ACK, sem n8n

[Issue #359](https://github.com/rafhael100/imagine-ai-agents/issues/359). Construído sobre [CONV-02 #360](https://github.com/rafhael100/imagine-ai-agents/pull/360) e [RC02 #358](https://github.com/rafhael100/imagine-ai-agents/pull/358).

**Fonte P12 fixa:** commit `aacbfdaa9fbfd878834d3c7b46eeaf99b4b8fdd9` da [PR #364](https://github.com/rafhael100/imagine-ai-agents/pull/364), obtido somente por checkout de revisão pinada. Não usa `main` nem branch mutável.

## Testes reais em PostgreSQL descartável

1. A classe original P12 `PgN8nOutboxLab` grava um evento canônico sem dados pessoais em `PENDING`; duplo enqueue idêntico não duplica.
2. Um trabalhador sintético faz `claim()` e recebe o número monotônico da tentativa, que funciona como *fencing token*.
3. O adaptador **CONV-02** valida o `kind`, rejeita eventos de aprovação não mapeados, verifica empresa e gera a proposta NO_AI (não assinada).
4. O teste gera HMAC com chave **estritamente sintética**, e a função `receive_signed_no_ai` do PostgreSQL valida a assinatura, a role fictícia, empresa, catálogo e nonce durável.
5. Simula **ACK perdido após gravação bem-sucedida**: o segundo claim após lease expirado gera tentativa 2; o reenvio ao inbox retorna duplicidade sem reprocessar; um ACK atrasado da tentativa 1 é negado; somente o ACK da tentativa 2 é aceito.
6. Uma empresa fictícia B não pode gravar seu evento pela role A; a role B grava de forma independente e a RLS limita as leituras a cada empresa.
7. Evento `mock.approval.requested` **não tem equivalência de autorização**: não é convertido, não recebe ACK; após três tentativas permanece em dead-letter.

O cenário testa um *handoff* lógico através das camadas de laboratório usando o mesmo PostgreSQL efêmero, sem transporte de rede entre serviços. Um nome de worker ou role fictício **não é identidade operacional**. O roteiro não executa workflow n8n, nenhum efeito no CRM, débito ou API paga.

## Bloqueios preservados
- Os modelos originais P12 e RC02 permanecem em linhas independentes; esta candidata não mistura código de produção nem faz merge.
- Key-id e HMAC são apenas fixtures. Exige-se IAM verdadeiro, gestão de chaves, escopo de empresas pelo servidor, TLS/serviço privado e revogação auditada.
- Precisamos de prova integrada de rollback, backup/restauração, infraestrutura n8n na VPS, controles de egress e revisão externa AppSec/DBA/LGPD antes de homologação operacional.
- Ainda não há n8n instalado nem CRM/Portal conectados; não existe autorização comercial de dados reais, deploy, merge ou chamadas IA.

**PR DRAFT / NO-GO / NO-DEPLOY / NO-SPEND.**
