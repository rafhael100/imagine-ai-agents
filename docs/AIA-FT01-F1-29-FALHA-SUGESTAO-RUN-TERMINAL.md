# FT01/F1-29 — Fechamento de execução após falha de sugestão sintética

**Data:** 09/11/2026. **Escopo:** somente PHP puro e testes offline, sem integração real.

## Problema
O piloto de leitura F1-26 criava uma Run ativa após leitura autorizada. Se o provedor de sugestão falhasse ou devolvesse texto fora do contrato, a resposta pública seria corretamente negada, mas a Run fictícia podia permanecer em estado `EXECUTANDO` sem evento terminal.

## Correção
`MockReadOnlyPilot` acompanha a referência da Run que iniciou **nessa chamada**. Se ocorrer exceção antes do encerramento normal, tenta transicioná-la para `FALHOU` e registrar `RUN_CHANGED` sem expor o erro ou conteúdo do provedor. Não finaliza Runs anteriores, não substitui prova de efeito e não chama `submit/status`. O preflight F1-28 já reserva espaço lógico para quatro eventos, suficiente também para esse caminho de erro (`STARTED`, `STEP_CREATED`, opcional `SUGGESTED`, `RUN_CHANGED`).

A resposta pública negada continua com `summary=null`, `real_authorization=false`, `external_calls=0`, `write_calls=0`. Caso a própria tentativa de fechamento em memória falhe, a saída ainda é negada; isto não é garantia de rastreabilidade durável ou transacional.

## Testes U32-01..04
- Provedor lança exceção após a leitura: Run registra `FALHOU`, sem `SUGGESTED` ou escrita.
- Provedor devolve payload fictício malformado: não atravessa a fronteira pública; trilha registra falha terminal.
- Acesso negado pelo produto dono: provedor nunca é invocado, nenhuma Run iniciada.
- Uma execução posterior com nonce e Run novos pode prosseguir, preservando a falha anterior e sem reaproveitar o nonce anterior.

**Meta CI:** 359 testes PHP, lint em 82 arquivos PHP, testes Python de contrato e demonstração exigidos no SHA do PR.

## Limitações
Tudo acontece em memória e é fictício. Exceções de provedor real, cancelamento distribuído, fila e recuperação durável requerem protocolo operacional separado. [CRM PR #57](https://github.com/rafhael100/imagine-crm/pull/57) continua proposta documental. R01-G01..G11 e CRM CORE/SEG/AUT **ABERTOS / NO-GO**. Sem alteração em `main`, VPS, credenciais, dados reais, custos ou produção.
