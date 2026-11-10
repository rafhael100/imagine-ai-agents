# FT01/SEC-19 — Não expor erros arbitrários do gateway na interface de ferramentas

**Data:** 10/11/2026. **Escopo:** PHP offline, dados fictícios. **NO-GO** para CRM real/produção.

## Risco corrigido

O `MockToolDispatcher::dispatch` registrava uma negação, mas **relançava qualquer `MockError` sem verificar o conteúdo**. Durante uma reconciliação de status de operação fictícia, `SupervisedCoordinator::reconcile` pode relançar um erro recebido do gateway. Um adaptador alternativo poderia gerar `MockError` com token, identificador ou texto interno, e o consumidor da ferramenta receberia essa mensagem bruta.

## Regra de fronteira

- A interface de ferramentas agora mantém uma **lista fechada de códigos públicos** de erro de domínio, incluindo `APPROVAL_REQUIRED`, `CAPABILITY_DENIED`, `RUN_NOT_ACTIVE`, `SYNTHETIC_STATUS_UNAVAILABLE` e outros códigos fixos do contrato sintético.
- `MockError` cujo conteúdo não seja **exatamente** um desses códigos é convertido em `TOOL_EXECUTION_DENIED`. Sufixos, quebras de linha, segredos e mensagens novas não passam por comparação por prefixo.
- A trilha registra somente `SYNTHETIC_DENIED` e hashes de referências. Não serializa o erro interno.
- A reconciliação continua **somente status**, sem reenviar `submit` e sem criar confirmação de efeito.
- Erros já fixos e previamente documentados mantêm compatibilidade; exceções gerais já eram neutralizadas pela SEC-17. A SEC-18 continua exigindo fechamento terminal em caso de sugestão rejeitada.

## Regressões U40-01..03

Um gateway fictício específico lança uma mensagem com marcador de segredo, outra mensagem que contém código público seguido de nova linha e segredo, e o código público exato. Os dois primeiros casos devolvem recusa genérica; o último preserva o código fixo. Todos verificam **uma só submissão** e trilha sem conteúdo sensível.

**Gate esperado:** 397 casos PHP, lint em 82 arquivos PHP, mais verificações Python existentes no SHA revisado.

## Limites e próxima fase

Isso protege somente mensagens internas do mock. Não há endpoint real nem integração CRM. A futura implementação de API deverá usar catálogo de erros versionado, resposta pública uniforme, telemetria privada minimizada e decisão de autorização no **backend CRM**. O [CRM PR #57](https://github.com/rafhael100/imagine-crm/pull/57) segue OPEN/DRAFT; R01-G01..G11 e CORE/SEG/AUT **ABERTOS / NO-GO**. Sem alteração em `main`, VPS, dados de clientes, credenciais, despesas ou produção.
