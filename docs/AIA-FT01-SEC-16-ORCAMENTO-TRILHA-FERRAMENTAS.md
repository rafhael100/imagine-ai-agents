# FT01/SEC-16 — Capacidade máxima da trilha de ferramentas

**Data:** 10/11/2026. **Escopo:** proteção de memória e auditoria exclusivamente sintéticas, PHP offline. **NO-GO operacional.**

## Lacuna e correção
O `MockToolDispatcher` acumulava eventos de ferramenta em memória sem limite. Uma operação de leitura governada ou um efeito fictício também podia ser executado antes de se detectar indisponibilidade futura de rastreabilidade.

- A trilha agora tem teto **monotônico de 1.024 eventos**, sem descarte dos mais antigos (não liberar replays por eviction).
- `dispatch` verifica espaço **antes** de executar qualquer sugestão, leitura legada ou `MUTATE` fictício. Sem espaço, lança `TOOL_TRACE_CAPACITY_DENIED`, não chama `SupervisedCoordinator::execute` e não registra resultado não auditável.
- `readThroughBoundary` preserva o contrato público: quando a trilha estiver cheia, retorna `DENIED_OR_NOT_FOUND` **antes** de consumir nonce, chamar borda, telemetria ou auditoria do produto dono.
- A função `trace` também impõe o teto como segunda defesa. Não há limpeza silenciosa, rotação automática ou autorização para ultrapassar o limite.

## Testes U37-01..04
- 1.024 recusas são registradas e a 1.025ª não acrescenta evento nem reescreve sequência.
- Esgotamento recusa uma mutação sintética válida **antes** do gateway e não muda a trilha do coordenador.
- Esgotamento recusa uma leitura governada **antes** da borda OP-01, sem consumir provas.
- A última posição livre pode registrar uma leitura governada, e a próxima solicitação é recusada sem nova leitura.

**Gate CI esperado:** 384 casos PHP, 82 arquivos PHP no lint, testes Python de contrato e demonstração.

## Limites
Este teto protege apenas um objeto volátil de laboratório. Não constitui retenção legal, observabilidade, controle de quota comercial ou trilha criptograficamente autenticada. Para ambiente real serão necessários logging durável, isolamento, decisões de retenção e recuperação auditável. [CRM PR #57](https://github.com/rafhael100/imagine-crm/pull/57) ainda está DRAFT; R01-G01..G11 e CORE/SEG/AUT permanecem **ABERTOS / NO-GO**. Sem alterações em `main`, VPS, clientes, credenciais, fornecedores, custos ou produção.
