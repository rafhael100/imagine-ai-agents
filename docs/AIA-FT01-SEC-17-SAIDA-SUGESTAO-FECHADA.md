# FT01/SEC-17 — Saída de sugestão verificada antes de devolver dados à ferramenta

**Data:** 10/11/2026. **Escopo:** código PHP de laboratório, sem CRM ou modelo externo. **Decisão: NO-GO operacional**.

## Risco identificado
O `MockToolDispatcher` repassava o resultado de `SupervisedCoordinator::suggest` ao consumidor sem verificar seu schema. Embora o provedor padrão seja fixo, um provedor de teste alternativo podia emitir texto arbitrário, campo extra (`secret`) ou identificador de execução falsificado. Também era possível que uma exceção inesperada escapasse sem evento de ferramenta `DENIED`.

## Proteção implementada
- O despacho da ferramenta `SUGGEST` agora aceita **somente** uma resposta fechada `kind/version/run/source/text` com os identificadores esperados da Run e o texto fixo sintético.
- Retornos com campos adicionais, outra Run, outra origem ou texto diferente são recusados com `TOOL_SUGGESTION_DENIED`; nenhum payload do provedor é devolvido.
- Exceções do provedor, incluindo `MockError` com mensagem não confiável, são normalizadas para a mesma negação e deixam trilha minimizada `SYNTHETIC_DENIED`.
- Qualquer `Throwable` não previsto no restante do dispatcher também é registrado como negação e transformado em `TOOL_EXECUTION_DENIED`, sem mostrar stack trace.
- O teto SEC-16 é consultado antes de toda operação. Não reabilita `dispatch(READ)` antigo, não concede permissões e não realiza operações reais.

## Regressões U38-01..05
Texto forjado, campo com token, exceção bruta de provedor, exceção de domínio com código não confiável e caso positivo permitido. As trilhas nunca incluem o texto malicioso, e o gateway de efeitos não recebe submissão.

**Gate esperado:** 389 testes PHP, lint em 82 arquivos PHP, demais testes Python de contrato e isolamento. O sucesso depende da CI no SHA exato.

## Restrições
O contrato estrito foi deliberadamente limitado a respostas **fictícias** e não define o formato de um modelo de IA real. A futura saída de LLM exige schema/versionamento, moderação, processamento de dados, política de prompts, limites de tamanho, logs minimizados, validação humana e segurança independente. CRM [PR #57](https://github.com/rafhael100/imagine-crm/pull/57) segue DRAFT; R01-G01..G11 e CRM CORE/SEG/AUT **ABERTOS/NO-GO**. Sem `main`, VPS, dados privados, credenciais, fornecedores, custos ou produção.
