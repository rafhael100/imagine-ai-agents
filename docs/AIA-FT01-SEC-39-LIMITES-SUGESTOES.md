# FT01/SEC-39 — Limites de entrada nas sugestões dos agentes

**Data:** 10/11/2026. **Escopo:** apenas PHP offline, dados e provedores fictícios. **Decisão operacional: NO-GO.**

## Problema

`SupervisedCoordinator::suggest()` aceitava sem limite de contagem/tamanho um vetor de campos antes de chamar a interface `AiSuggestionProvider`. Mesmo o provedor fixo `MockAiSuggestionProvider` percorria todos os elementos e só checava prefixo; integrações futuras ou fixtures de teste poderiam receber arrays enormes e valores de megabytes sem necessidade. A validação restrita do `MockToolDispatcher` não protegia quem usava diretamente o coordenador ou o provedor.

## Correção

- **Validação no coordenador, antes de qualquer provedor ou evento `SUGGESTED`:** no máximo **3 campos**, nomes exatos `ref`, `stage`, `text`, valores tipo string, até **256 bytes** cada e começando com `SYNTHETIC`.
- **Defesa redundante no provedor fictício:** as mesmas verificações para acessos diretos sem coordenador, limitando trabalho desnecessário e impedindo entradas enormes em testes.
- **Falha explícita `VALIDATION_FAILED`** sem alterar o estado do Run ou adicionar eventos quando a entrada é rejeitada. Uma sugestão sintética válida posterior continua possível, sem criar efeitos ou chamar o CRM.
- Preserva intencionalmente projeção **vazia** para fixtures antigas de capacidade/auditoria, bem como as projeções reais dos mocks OP-01 com 1–2 campos. Não afeta o contrato público de sucesso da sugestão.

## Regressões U61-01..08

Cobre 20 mil campos, valor de 1 MiB no coordenador, limites do provedor direto, fronteira 256/257 bytes, projeção válida de 3 campos, chaves e valores inválidos, fixture legada vazia e funcionamento posterior após entrada negada.

**Gate esperado: 526 testes PHP, lint de 82 arquivos PHP e verificadores Python**, CI no SHA revisado e novamente após integração à `dev`.

## Restrições

Não existe modelo externo, token real, tráfego, LLM pago ou autorização operacional. CRM PR #57 permanece OPEN/DRAFT; gates R01-G01..G11 / CRM CORE-SEG-AUT ABERTOS/NO-GO. Nenhuma alteração em `main`, VPS, produção, segredos, dados de clientes ou custos.
