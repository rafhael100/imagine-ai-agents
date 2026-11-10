# FT01/SEC-21 — Ferramentas exigem Run e Step canônicos do coordenador

**Data:** 10/11/2026. **Escopo:** PHP puro com dados sintéticos, execução offline. **Integração operacional: NO-GO.**

## Vulnerabilidade estrutural do laboratório

O `SupervisedCoordinator` preserva deliberadamente o suporte histórico a objetos `Run` criados fora dele quando nenhuma execução foi registrada (`startedRuns` vazio). Embora útil para fixtures antigas, esse comportamento não deve atravessar a interface de ferramentas: um chamador poderia construir um `Run`, `Step`, `Intent` e `Approval` sintéticos e solicitar `MUTATE` ou `SUGGEST` sem um evento `STARTED` ou `STEP_CREATED` produzido pelo coordenador. O `Approval` sintético não é autenticação humana real.

## Correção fail-closed

- `SupervisedCoordinator::assertToolSuggestionReady` passou a exigir que a **mesma instância** de `Run` exista em `startedRuns`, além das verificações anteriores de contexto, estado ativo e espaço para auditoria.
- Novo `SupervisedCoordinator::assertToolIntentReady` exige `Run` e `Step` **canônicos, por identidade de objeto**, antes de qualquer efeito visível pela interface de ferramentas.
- `MockToolDispatcher::dispatch(MUTATE)` invoca o novo controle antes de `execute`; a etapa de sugestão já utiliza `assertToolSuggestionReady`.
- Objetos destacados, mesmo com os mesmos IDs, material e aprovação falsamente `APROVADA`, são recusados com `RUN_NOT_OWNED` ou `STEP_NOT_OWNED`, sem submissão de efeito nem novos eventos no coordenador. A interface registra `SYNTHETIC_DENIED` sem divulgar payloads.
- Não alteramos o suporte legado do coordenador **interno**, para preservar fixtures históricas. A fronteira exposta por ferramentas é a proteção reforçada; não representa autenticação ou autorização real.

## Regressões U42-01..06

1. `SUGGEST` em Run externo é recusado sem invocar provedor de IA fake;
2. `MUTATE` em Run externo com aprovação artificialmente válida não faz `submit`;
3. fluxo legítimo `STARTED → STEP_CREATED → SUGGEST → MUTATE` continua funcionando após aprovação sintética explícita;
4. Run clonado com mesmo ID não assume a identidade do canônico;
5. Step externo com ID igual ao Step registrado não herda sua permissão;
6. uma tentativa externa recusada não impede uma nova execução canônica e auditada.

**Gate de CI esperado:** 409 testes PHP, 82 arquivos PHP com lint, além das verificações Python e pacote bilateral OP-01 offline. Requer aprovação de CI no commit exato antes de merge na `dev`.

## Dependências reais

O [CRM PR #57](https://github.com/rafhael100/imagine-crm/pull/57) permanece OPEN/DRAFT; o [pacote OP-01 EVID-19](AIA-R01-EVID-19-PACOTE-BILATERAL-OP01.md) ainda registra 7 decisões, 14 gates e 13 cenários sem homologação. **R01-G01..G11 e CRM CORE/SEG/AUT permanecem ABERTOS/NO-GO.** Para qualquer operação real, identidade da pessoa e do serviço, ACL backend por tenant/recurso/campo, assinatura da aprovação, persistência/idempotência, LGPD e rollback exigem homologação independente. Nenhuma mudança na `main`, CRM real, VPS, clientes, segredos, fornecedores pagos ou produção.
