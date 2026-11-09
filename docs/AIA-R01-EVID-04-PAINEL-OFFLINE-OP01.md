# AIA-R01/EVID-04 — Painel navegável offline de prontidão OP-01

**Objetivo:** fornecer aos revisores CORE/SEG/AUT e demais responsáveis uma visualização local, responsiva e sem rede dos 14 gates R01/CRM e 13 casos do piloto somente leitura.

**Origem dos dados:** `docs/evidencias/aia-r01-gates-op01.csv` e `docs/evidencias/op01-casos-homologacao.csv`, registrados pelo dossiê EVID-01. O arquivo `examples/r01-op01-readiness-offline.html` incorpora uma cópia estática e revisável dessas linhas, sem JavaScript remoto, fontes, API, servidor ou dependências.

**Uso:** abrir o arquivo HTML localmente em navegador moderno; pesquisar ID, autoridade, evidência ou caso e filtrar entre Gates/Cenários. O painel é de consulta e não possui mecanismo de edição, aprovação, login, gravação, rede ou deploy.

**Guardas:** `tools/check_op01_readiness_dashboard.py` exige que todas as 27 linhas e seus campos correspondam aos dois CSVs, que o status global seja `NO_GO` e que não haja ativos externos ou código de rede. A suíte `tests/Contracts/test_op01_readiness_dashboard.py` exercita adulteração de registros, estados de aprovação e referência, desaparecimento de linhas e inserção de rede.

**Limites:** uma cópia HTML pode ficar desatualizada. A verificação em CI detecta divergências no checkout, mas não consulta o CRM nem garante que o PR #57 permaneça no mesmo estado após a revisão. Nenhum gate foi encerrado, nenhuma identidade validada e nenhum caso integrado executado.

**Governança:** PR CRM #57 permanece em DRAFT e sem merge; G01..G11/CORE/SEG/AUT abertos. Dados fictícios, sem credenciais, SQL, VPS, fornecedores, cobrança, `main` ou produção.

**Evidência pós-integração (09/11/2026):** [PR #125](https://github.com/rafhael100/imagine-ai-agents/pull/125) integrado exclusivamente à dev, squash `53fc200e48b1c05789317e85b9b143efc47b76f0`. [CI #42624860955](https://github.com/rafhael100/imagine-ai-agents/actions/runs/42624860955), SHA `7ca16416914291f1a6fd7d9f47126228ff2f8526`, PASS no verificador, testes negativos EVID-02/03/04, 228 testes PHP e 73 lint oficiais mais o probe. **NÃO** aprova integração real; NO-GO e gates abertos.
