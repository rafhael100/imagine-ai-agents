# Imagine AI Agents — FT01/F1-09 | Avaliação A/B de versões sintéticas

**Data de referência:** 08/11/2026. **Situação:** implementação isolada, sem integração real ou deploy. Diretrizes AIA09-D01..D10 aprovadas funcionalmente; autorização ampla posterior permite somente desenvolvimento sintético/offline na `dev`. Este documento não homologa modelos, dados, segurança operacional, conectores ou provedores.

## Objetivo

Criar a primeira comparação **determinística** de versões candidatas de agente, usando **baseline, dataset, rubrica, contexto e conjunto de casos fixados**, sem acesso a CRM real, LLM, APIs, dados privados, banco ou autorização produtiva.

A avaliação não calcula semântica, custos, latência nem qualidade de modelos reais: cada versão fornece uma tabela de **resultados previamente inventados**, e o comparador verifica esses resultados contra uma matriz imutável. Nenhuma nota ou média compensa falha crítica; nenhuma avaliação habilita publicação automática.

## Arquitetura local em PHP puro

- `src/MockEvaluation/MockEvaluationSuite.php`: seis a doze casos tipados e fechados, severidade `CRITICAL` ou `REVIEW`, contexto multiempresa fictício, dataset e rubrica versionados, digest SHA-256 determinístico.
- `src/MockEvaluation/MockEvaluationCandidate.php`: snapshot de versão vinculada ao digest exato da suíte; resultados permitidos em lista fechada e `NOT_EXECUTED`; ausência de resultado é permitida somente para representar evidência faltante.
- `src/MockEvaluation/MockEvaluationComparator.php`: valida mesma empresa/produto/principal/finalidade/recurso, compara versões diferentes, **rejeita baseline incompleto ou errado**, rejeita mudança de dataset/rubrica e calcula os gates sem produzir efeitos externos.
- `tests/Unit/evaluation_compare.php`: U09-01..U09-12, incorporados ao runner oficial `tests/Unit/run.php`; total de **97** testes sintéticos (85 anteriores + 12 F1-09).
- `examples/f109-evaluation-demo.php`: executável local com três comparações e JSON sintético. Comando: `php examples/f109-evaluation-demo.php`.

### Gates de saída

| Gate | Condição | Efeito |
| --- | --- | --- |
| `BLOCKED_CRITICAL` | Um ou mais casos eliminatórios falharam | Bloqueio, mesmo com demais resultados aprovados |
| `INCONCLUSIVE` | Faltou evidência ou caso não foi executado, sem regressão crítica detectada | Nova avaliação necessária |
| `REVIEW_WITH_FAILURES` | Falha não crítica com evidência completa | Revisão e nova rodada |
| `REVIEW_REQUIRED` | Todos os casos sintéticos passaram | Revisão humana obrigatória, **sem publicação** |

Em **todos** os resultados `promotion_allowed=false`. O avaliador é exclusivamente um mock: não concede permissões, não cria usuários, não chama ferramentas e não integra diretamente ao `MockPromotionGate` de F1-06.

## Laboratório visual independente

- `examples/f109-comparador-versoes-offline.html` apresenta três telas: visão geral, comparação e trilha local.
- Exibe baseline fictício v1.0 versus v1.1 (6/6, revisão humana), v1.2 (4/6, duas falhas críticas, bloqueada) ou v1.3 (5/6, uma evidência não executada, inconclusiva).
- Executa **regras fixas em JavaScript local**, que espelham os conceitos do PHP, mas **não invocam o executor PHP**.
- Arquivo único, CSP `connect-src 'none'`, sem `fetch`/WebSocket, `localStorage`, `sessionStorage`, provedores, iframe, scripts remotos ou formulários; histórico efêmero que desaparece ao atualizar a página.
- `tests/Browser/f109_smoke.cjs`: testes sem dependências externas de navegação, gates dos três candidatos, tabela de seis casos, histórico, limpeza e política offline. Comando: `node tests/Browser/f109_smoke.cjs`.

## Evidência efetivamente produzida

- **PHP 8.4.24**, execução local de `php tests/Unit/run.php`: **97 cases, 0 failures, exit 0**.
- **48/48 arquivos PHP** sintéticos existentes na cópia de verificação: `php -l` sem erros.
- Os **seis arquivos PHP novos/modificados** (três classes, suíte, runner e exemplo) conferidos por hash Git de blob SHA-1 idêntico aos arquivos publicados no GitHub.
- Comparação local executável PHP: `REVIEW_REQUIRED` (6/6), `BLOCKED_CRITICAL` (4/6, duas críticas), `INCONCLUSIVE` (5/6, um caso não executado), sem chamadas externas.
- Verificador `f109_smoke.cjs` executado em V8 isolado usando adaptadores DOM e módulos nativos de Node: **F109_SMOKE_PASS** para três telas, três candidatos e histórico. Esta verificação **ainda não equivale à execução em processo Node real ou à inspeção visual em Chromium/Firefox** do HTML exato.
- A base anterior F1-08 estava em `dev/ft01-aia-core-mocks-2026-11-06`, SHA `0fc74060c5fdfe914fd2a1c89f58d22818f775a2`, com 85 testes sintéticos previamente verificados.

## Falhas eliminatórias simuladas e controles

Os casos U09 cobrem isolamento entre empresas, revogação, idempotência, injeção de instruções, contradição factual e dados dinâmicos, além de baseline incorreto, troca de dataset/rubrica, esquema inválido, contexto indevido, resultados ausentes e replay de versão. `MockError` mantém códigos fechados; os snapshots conservam referência, versão, contexto e digest sem textos de cliente.

## Estado e limites de integração

- **Somente branch de desenvolvimento isolada**, com PR de revisão; não integrar `main` ou publicar automaticamente.
- Antes de merge, exigir SHA HEAD congelado, diff revisado, lint e regressão PHP reexecutados, smoke Node real e **inspeção responsiva em Chromium/Firefox ou justificativa técnica documentada**.
- Instruções de versões reais, permissões, assinatura, acesso API, dados, observabilidade persistente, LGPD operacional, auditoria, custos reais, credenciais e fornecimento de modelos continuam fora do escopo.
- Gates **R01-G01..G11** e **CORE/SEG/AUT CRM** permanecem abertos. Não fechar automaticamente pela aprovação genérica do desenvolvimento.

**Conclusão:** mecanismo de comparação A/B sintético implementado e regressão PHP executada; interface visual aguarda inspeção em navegador real antes de integração da proposta completa.
