# AIA-FT01 / F1-08 — Laboratório visual navegável, somente offline

> **Atualização vigente de 08/11/2026:** PR #98 integrado exclusivamente na dev, squash `d37cd9a603981c3d963823307119c402b67edf89`. Node real e Chromium (5 viewports) PASS, hashes Git conferidos; Firefox não testado. Nenhum deploy; autorização, privacidade e integração real permanecem bloqueadas por gates. Trechos abaixo que citam PR DRAFT são históricos.


**Recorte de prototipação aprovado pela autorização ampla de desenvolvimento do responsável em 08/11/2026.** Este F1-08 é uma **numeração operacional proposta para a demonstração visual**, não uma nova homologação dos módulos funcionais AIA-01 a AIA-12. Seu conteúdo está alinhado ao laboratório AIA-09, à revisão AIA-R01 e aos mocks F1-01..F1-07 já implementados na branch `dev/ft01-aia-core-mocks-2026-11-06`.

**Branch de trabalho:** `dev/ft01-f1-08-visual-lab-2026-11-08`, derivada de `23c076c623a47b9dc6c91122eb72c6c271d27a78` (PRs #96 e #97 já integrados à dev). **Sem alteração da main, VPS ou outros repositórios.**

## Objetivo

Permitir uma primeira navegação demonstrativa dentro do repositório Imagine AI Agents, antes de qualquer credencial, serviço, conexão CRM/Portal/Performance/Universidade ou contratação. Evidenciar visualmente os resultados esperados de **contexto → autorização → recuperação → decisão → trilha**, sem alegar execução de uma IA real.

## Entregas

- `examples/f108-laboratorio-navegavel.html`: arquivo único, responsivo, em português, que abre diretamente em navegador, sem servidor. Quatro telas:
  1. **Visão geral**: números históricos da regressão F1-07 (85/85), mapa conceitual do fluxo e gates ainda abertos.
  2. **Agentes fictícios**: três exemplos ilustrativos para CRM, Performance AI e Universidade, sem existência de instâncias reais.
  3. **Testar cenários**: seis fixtures determinísticas selecionáveis e botão de executar; o resultado é produzido em JS local, não pelo backend PHP.
  4. **Trilha local**: apenas IDs de caso, números de sequência e códigos sintéticos de evento. Reinicialização explícita e descarte de memória ao recarregar.
- `tests/Browser/f108_smoke.cjs`: verificador Node 18+ sem dependências terceirizadas que testa os comandos de navegação, saídas dos casos, trilha, reset e invariantes de ausência de rede/armazenamento.
- Documentação e regras de aceitação para que o protótipo seja explorado sem confusão com autenticação, permissões ou publicação real.

## Casos visuais e retornos determinísticos

| Caso | Entrada simulada | Resposta apresentada |
| --- | --- | --- |
| U07-01 | Contexto e fonte fictícia elegíveis | `READY`, conteúdo `UNTRUSTED_DATA` |
| U07-02 | Outra empresa usa a mesma referência | `KNOWLEDGE_DENIED` |
| U07-05 | Grant revogado após seleção | `KNOWLEDGE_DENIED` |
| U07-07 | Fontes contraditórias | `CONFLICT` |
| U07-08 | Saldo/preço/estado dinâmico | `DYNAMIC_SOURCE_REQUIRED` |
| U07-09 | Documento tenta ordenar execução | `READY · UNTRUSTED_DATA`, sem ferramenta |

**Importante:** a interface usa regras fixas escritas em JavaScript; **não invoca as classes PHP**, não reexecuta os testes U07 oficiais e não substitui o resultado de autorização de nenhum produto.

## Invariantes de segurança

1. Arquivo autossuficiente com Content Security Policy: `default-src 'none'; connect-src 'none'`, sem scripts, imagens ou fontes remotas.
2. Não utiliza `fetch`, `XMLHttpRequest`, `WebSocket`, `localStorage`, `sessionStorage`, formulários, iframes, serviço, cookies, arquivos pessoais ou dados de clientes.
3. O DOM de saídas e eventos é atualizado com `textContent`, e as fixtures ficam congeladas em memória local. Nenhuma política ou grant real é emitido.
4. Nenhum botão se apresenta como publicação, aprovação real, conexão CRM ou execução de ferramenta.
5. A trilha local desaparece após recarregar a página; não existe persistência, auditoria legal, monitoramento ou retenção.
6. É proibido classificar o protótipo como ambiente de produção, homologação integrada, conector ou agente implantado.

## Testes / evidências de hoje

- Arquivo HTML da branch recuperado por conector autorizado e conferido por Git blob SHA-1. Layout responsivo e rótulo permanente de simulação.
- Smoke de DOM com os arquivos exatos obtidos do GitHub, executado em V8 com adaptadores in-memory de `fs`/`path`/`vm` e `assert`: **PASS** (4 páginas, 6 respostas, 14 eventos, contadores de auditoria, reset, atalho de agente, CSP e ausência de rede).
- `node tests/Browser/f108_smoke.cjs` executado em processo Node.js v22.16.0 com código exato e **F108_SMOKE_PASS**. Teste também cobre o atributo `aria-pressed` em todos os cenários.
- O código F1-08 não altera nenhum PHP da base. Regressores PHP da cópia reconstruída do PR #96: **85 cases, 0 failures** reexecutados localmente; não é novo teste de segurança operacional.
- **Chromium real validado:** QA em ambiente isolado: HTML exato do GitHub reconstruído e validado por hash Git blob SHA-1; Node.js 22.16.0 executou o smoke original com PASS; Chromium headless real com Playwright renderizou o HTML via set_content nas resoluções 1440×900, 1024×768, 768×1024, 390×844 e 320×680: 5/5 PASS, sem rolagem horizontal, erros JavaScript, erros de console nem requisições externas. As seis decisões, navegação, contadores, 14 eventos e reset foram reproduzidos em cada viewport. Revisão visual de capturas desktop/mobile feita; corrigidos menu mobile em duas colunas, textos de fixtures e IDs de cenário. Estado selecionado agora usa aria-pressed; teste por teclado com Tab em Chromium PASS. Firefox não foi executado. O sandbox bloqueia navegação file://, portanto o teste visual usou set_content sobre os bytes exatos conferidos por hash.

## Governança e próximos passos

- PR #98 criado em DRAFT. Após a bateria técnica e revisão visual em Chromium, está candidato a integração controlada exclusivamente na `dev`, mediante confirmação de SHA, integridade, diff e ausência de conflitos. Nenhum merge na `main`, deploy ou promoção a produção.
- Para testar offline: abrir `examples/f108-laboratorio-navegavel.html` localmente; para testar interações por código: `node tests/Browser/f108_smoke.cjs`.
- Após validar UI, considerar uma futura camada de demonstração PHP que sirva conteúdo inteiramente sintético na VPS de desenvolvimento **somente mediante decisão de arquitetura/segurança separada**.
- `main`, produção, VPS, n8n, Cloudflare, CRM/Portal/Performance/Universidade, credenciais, APIs/LLM, dados privados, SQL/ALLOW, fornecedores e gastos permanecem intocados.
- Gates R01-G01 a G11 e CORE/SEG/AUT CRM **todos abertos**; a demonstração não os fecha.

**Estado efetivo:** F1-08 **protótipo visual offline testado em Chromium/Node**, ainda sem publicação web, **sem testes em Firefox**, sem integração real e sem homologação de produção.
