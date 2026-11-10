# R01/EVID-31 — SEC-46 integrada: recibo de leitura e defesa contra ABA

**Verificado:** 10/11/2026. **Decisão operacional: NO-GO.** Evidência apenas do simulador PHP offline dos Agentes de IA.

## Estado verificável no GitHub

- [PR #222 — SEC-46](https://github.com/rafhael100/imagine-ai-agents/pull/222): integrado por squash exclusivamente à `dev/ft01-aia-core-mocks-2026-11-06`, commit `6d676f5a93ef837c3dc3f852b8a5b78be82c6e7b`.
- [CI pós-merge #42918687251](https://github.com/rafhael100/imagine-ai-agents/actions/runs/42918687251): `completed/success` no mesmo SHA, **597 testes PHP, lint de 83 arquivos PHP** e testes Python de contrato/isolamento, sem rede.
- `MockCrmOwnerPolicy` registra mudanças de permissão numa revisão monotônica local. A borda rejeita uma leitura mesmo quando uma revogação é imediatamente revertida antes da resposta (*ABA*).
- `MockCrmReadPublicFacade::recheck` exige recibo da **última leitura bem-sucedida**: mesmos objetos de prova, mesmo contexto, mesma requisição validada e revisão de política inalterada. A retenção é restrita a um único recibo privado e não introduz novo token.
- **12 testes U68-01..12** verificam recheck sem leitura, falha e replay, troca de prova/campos, grant revogado e reconcedido, suspensão e reativação, outage e recuperação, incluindo leituras e sugestões reentrantes.

## Limites e bloqueios que não mudaram

O comprovante não é um token confiável; a revisão é um contador de memória do processo. Não existe autorização atômica no banco CRM, IAM real, nonce/estado distribuído, API autenticada, concorrência multiprocesso, prova de expiração temporal nem revisão de segurança independente. Mesmo CI verde **não** altera o estado dos 13 casos reais `NAO_EXECUTADO` ou concede acesso a clientes.

[CRM PR #57](https://github.com/rafhael100/imagine-crm/pull/57) permanece OPEN/DRAFT; as 7 decisões OP01-R01..R07 e gates R01-G01..G11/CRM CORE/SEG/AUT seguem ABERTOS/NO-GO. Sem merge em `main`, VPS, produção, credenciais, clientes ou novos custos. A homologação bilateral precisa de evidências reais e aprovação específica dos responsáveis antes de avançar.
