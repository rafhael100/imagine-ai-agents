# AIA-P25 — Auditoria do vínculo request e nonce com o evento original

**16/11/2026 — correção de integridade do auditor P23.**

## Problema identificado

A P23 verificava presença das linhas, hash do evento e bytes canônicos do outbox, mas **não validava que os campos request_ref e nonce_ref gravados no ledger antirrepetição correspondiam aos derivados do evento original**. Uma alteração em tais campos poderia permanecer invisível se todos os hashes do evento fossem preservados.

## Implementação

O auditor reaproveita o adaptador oficial AUTO-IA CONV-02, fixado no commit 8b04d0e936168c77db028160b5aeb1758db497c4, para reconstruir a proposta **sem assinatura, execução ou transporte**, usando o carimbo de data já presente no próprio evento como janela fictícia de validação histórica. Confronta request_ref e nonce_ref gravados em PostgreSQL com as referências derivadas do contrato canônico. Retorna respectivamente REPLAY_REQUEST_BINDING_MISMATCH e REPLAY_NONCE_BINDING_MISMATCH; uma derivação não mais reconhecida resulta em negação de consistência.

Os testes corrompem cada identificador separadamente em PostgreSQL16 descartável e verificam detecção, restauração exclusiva do harness e regressão P23/P24.

## Limites e segurança

A reconstrução histórica **não prova que um evento era válido à época**, muito menos que pode ser executado agora. Continua não havendo IAM operacional, assinatura real, transporte S2S, n8n ligado, pagamento ou autorização do serviço proprietário. A auditoria nunca repara, envia eventos, assina envelope ou grava registros. A revisão independente continua pendente.

**DRAFT / NO-GO / NO-MERGE / NO-DEPLOY.**
