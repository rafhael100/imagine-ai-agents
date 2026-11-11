# R01/EVID-38 — SEC-53 integrada: proteção de Run durante callback

**Consolidado em 10/11/2026** (horário do projeto, Brasil). **NO-GO operacional.** Ambiente inteiramente fictício e offline.

## Evidências verificadas

- [PR #237 — SEC-53](https://github.com/rafhael100/imagine-ai-agents/pull/237) integrada exclusivamente à `dev/ft01-aia-core-mocks-2026-11-06` no SHA `ad7fc7b718235e0b5e6befaaaf7976adca15bc2a`.
- [CI do PR #42945761135](https://github.com/rafhael100/imagine-ai-agents/actions/runs/42945761135) **success** no HEAD revisado `42c34c492796bbac7d79fc1488817bc4c124d601`.
- [CI pós-merge #42945854088](https://github.com/rafhael100/imagine-ai-agents/actions/runs/42945854088) **success** no SHA exato `ad7fc7b718235e0b5e6befaaaf7976adca15bc2a`: **648 testes PHP**, lint de **83 arquivos PHP** e verificadores Python offline, incluindo verificação de isolamento sem execução de funções dinâmicas.
- `Run::withReadOnlySuggestion(AiSuggestionProvider, array)` usa chamada tipada `suggest()`; bloqueia `Run::transition`, `Run::step` e tentativa de escopo aninhado enquanto o provedor tem referência ao Run. A negativa persiste mesmo se o provedor a capturar. A limpeza `finally` permite que o despachante registre a falha e finalize Run `FALHOU` posteriormente.
- Oito regressões **U75-01..08** verificam alterações diretas com/sem captura, tentativas ilegais, chamada direta ao coordenador, recuperação e leitura legítima.
- Documento técnico [SEC-53](AIA-FT01-SEC-53-RUN-LIFECYCLE-READONLY-CALLBACK.md). Histórico [EVID-37](AIA-R01-EVID-37-SEC52-CI-POSTMERGE.md), [EVID-36](AIA-R01-EVID-36-SEC51-CI-POSTMERGE.md), [EVID-35](AIA-R01-EVID-35-SEC50-CI-POSTMERGE.md) preservado.

## Limitações e próximos gates

**Não** é sandbox para provedor malicioso, nem impede conexões ou efeitos feitos por outros objetos/processos. Não existem IAM/ACL/RLS de produção, tokens, autorização real, integração autenticada com CRM ou auditoria distribuída. O [CRM PR #57](https://github.com/rafhael100/imagine-crm/pull/57) permanece **OPEN/DRAFT**, os 13 cenários operacionais OP-01 seguem `NAO_EXECUTADO` e R01-G01..G11 / CORE/SEG/AUT permanecem **NO-GO**. Sem alteração de `main`, sem VPS, deploy, clientes, dados reais, credenciais ou despesas.

Qualquer avanço para um piloto real requer contrato bilateral aceito, revisão independente, autenticação/permissão real por empresa e homologação operacional segregada.
