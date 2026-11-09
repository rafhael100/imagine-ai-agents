# Imagine AI Agents — FT01 F1-06 + F1-07 | Integração sintética controlada

**Estado: branch de preparação; NÃO integrada à dev, NÃO implantada, sem homologação de segurança real.** Data de registro: 2026-11-08.

## Contexto e proveniência

- Branch de preparação: `dev/ft01-f106-f107-integration-sandbox-2026-11-08`, criada a partir do HEAD do PR #95 em `8771c6778fb7afa1ef8293dce64504cb6f735371`.
- PR #95: F1-07 (recuperação de conhecimento e contexto puramente fictícios); nenhuma alteração em classes antigas F1-01 a F1-06.
- Os arquivos `src/MockCatalog/MockPromotionGate.php` e `tests/Unit/catalog_release.php` foram recuperados **sem reescrita** dos blobs do PR #92, SHA-1 Git `7ea94b42b3bf41b1b4bb7575e587e14784a92716` e `f687934b099d8cdba44a946f0ca3aa9ee031f17e`.
- A branch é um *ensaio de composição*; os PRs #92 e #95 continuam independentes, DRAFT e sem merge à dev.

## Escopo e requisitos de verificação

O runner `tests/Unit/run.php` da branch F1-07 carrega as suites anteriores sem mudança de contrato. Com os três casos U06-C01 a U06-C03 trazidos do PR #92, a contagem por suíte é:

| Suíte | Casos declarados |
| --- | ---: |
| `tests/Unit/run.php` (centrais) | 10 |
| `supervised.php` | 12 |
| `resilience.php` | 12 |
| `tool_dispatch.php` | 12 |
| `usage_quota.php` | 12 |
| `catalog_release.php` | 15 |
| `knowledge_context.php` | 12 |
| **Total** | **85** |

**85 = casos identificados no código, NÃO testes executados e aprovados.** Conferência estática realizada no GitHub: 85 nomes distintos, sem duplicação. PHP 8.4.24 estava presente no sandbox, e um teste auxiliar F1-07 (13/13) e três casos F1-06 corretivos isolados (3/3) passaram em reproduções locais; **isso não substitui a regressão integral**.

### Gate obrigatório de aceite — somente em checkout completo

```sh
git rev-parse HEAD
EXPECTED_SHA=<SHA_EXATO_DO_HEAD_DA_BRANCH> \
  EXPECTED_CASES=85 EXPECTED_PHP=43 bash tools/validate_ft01_offline.sh
```

O script nesta branch exige `EXPECTED_SHA` informado explicitamente e igual ao checkout; não faz `fetch`, não instala nada, não acessa rede, não chama Actions, não usa serviços pagos. Se faltar arquivo, PHP 8.1+, SHA, lint ou um dos 85 PASS, deve interromper com falha/bloqueio. Só `VALIDATION_PASS` com código de saída 0 para a regressão real e completa poderá ser usado como evidência.

O total 43 refere-se aos arquivos PHP da árvore de F1-07: classes de src, suites de testes e exemplo offline. O gate calcula com `find src tests/Unit examples`, logo deve ser executado no exato checkout esperado, sem arquivos extras ou versões ausentes.

## Limites e condições de integração

1. **Não mesclar esta branch automaticamente**; primeiro obter `VALIDATION_PASS` em checkout completo com SHA, conferir diff frente à dev e revisar segurança dos dois recortes.
2. Se a dev avançar por qualquer PR concorrente, comparar novamente os commits e conciliar a base preservando decisões documentais. Não usar force-push nem sobrescrever o trabalho de outra branch.
3. Dados totalmente fictícios, nenhum documento de cliente, nenhum efeito real, nenhuma memória persistente, nenhum banco, LLM, API, plugin comercial, credencial ou infraestrutura.
4. `main`, produção, CRM, Portal, Sites, Performance, Universidade e outras branches não são alteradas.
5. R01-G01..G11 e CORE/SEG/AUT CRM permanecem **ABERTOS**. F1-08 não está autorizado por esta preparação; seu eventual recorte requer análise própria.

**Resultado desta rodada:** branch conjunta pronta para validação oficial de 85 cenários, não liberada para merge.
