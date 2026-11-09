# AIA-R01/EVID-06 — Integração contínua unificada e econômica da branch dev

**Situação vigente (09/11/2026):** [PR #128](https://github.com/rafhael100/imagine-ai-agents/pull/128) integrado apenas à dev, squash `e8f8ee2bc2353b2e8c3d7ce5b38bdcb38f9d5c0c`. [CI de revisão #42629105845](https://github.com/rafhael100/imagine-ai-agents/actions/runs/42629105845) PASS no head `902458ee87d0c8711c499ece55d56747272a3901`, e **[CI integrada #42629209058](https://github.com/rafhael100/imagine-ai-agents/actions/runs/42629209058) PASS no HEAD da dev `e8f8ee2bc2353b2e8c3d7ce5b38bdcb38f9d5c0c` após merge**. Exclusivamente GitHub Actions em repositório público; sem API, segredos, custos contratados ou backend real. Não habilita CRM nem homologa gates.

## Motivação e melhoria

As entregas EVID-02 a EVID-05 possuem workflows individuais vinculados às branches de cada etapa. Após o merge, nem sempre ocorre execução automática que confira o estado **integrado** da branch `dev/ft01-aia-core-mocks-2026-11-06`. O novo `.github/workflows/r01-dev-integrity.yml` aplica, numa sequência única e somente offline, as provas já existentes.

## Acionamento restrito e controle de custos

- Em `push` na dev principal e em `pull_request` com base nessa dev, apenas quando arquivos de código, testes, ferramentas, contratos, matrizes CSV, painel HTML ou o próprio workflow forem alterados.
- A branch `dev/r01-evid-06-unified-dev-ci-2026-11-09` serve somente para validar o workflow novo antes do merge.
- Execução apenas quando o repositório for público (`github.event.repository.private == false`), em `ubuntu-24.04` com limite rígido de 6 minutos. Se tornar privado, o job é omitido até avaliação de custos e novo aceite; não pressupor gratuidade futura.
- Usa apenas Python, PHP e Git já presentes no runner. **Sem pip, composer, apt, docker pull, LLM, provedores externos ou upload de artefatos.**
- `permissions: contents: read`, checkout `persist-credentials: false`, nenhum segredo, nenhum token de usuário, nenhuma publicação e nenhum deploy.
- `concurrency.cancel-in-progress: true` evita manter verificações antigas concorrentes da mesma ref.

## Qualidade verificada — sem falsificar autoridade

1. Inventário dos 14 gates (todos abertos) e 13 cenários (todos não executados).
2. Paridade da proposta CRM PR #57 **congelada** com o schema e o parser sintéticos do Agents, sem conferir uma API real.
3. Geração determinística e validação de segurança do HTML offline.
4. Suítes unitárias Python EVID-02/03/04/05, incluindo desvios e tentativas de adulteração.
5. `php -l` do probe isolado e regressão de **228/228 casos PHP**, **73 arquivos sintáticos** (contagens vigentes, sujeitas a atualização deliberada se o código legítimo evoluir).
6. `EXPECTED_SHA=${{ github.sha }}` impede validar um checkout diferente do commit do workflow.

**IMPORTANTE:** `success` significa apenas que **os critérios sintéticos programados passaram**. Não significa G01/G02/G05/G08/G10/G11 fechados, aceitação bilateral, testes integrados, credenciais, prontidão de produção ou `GO`.

## Evidências verificadas e critérios para alterações futuras

**Verificado:** o diff foi restrito a workflow e documentação; o run de revisão e o run disparado pelo merge concluíram com sucesso. Para alterações futuras, repetir preflight de SHA, exigir CI `success`, proteger `main` e manter controles `NO-GO` até homologação. Se alguma validação falhar, registrar o bloqueio e corrigir na branch, sem silenciar testes.

**Status operacional permanece NO-GO.** CRM PR #57 em rascunho; CORE/SEG/AUT e R01-G01..G11 abertos. `main`, VPS, clientes e produção inalterados.

## CI-SEC-01 — checkout com SHA fixado e Node.js 24 (09/11/2026)

O workflow da dev utilizava `actions/checkout@v4`, com aviso de obsolescência de Node.js 20 nos logs. A release oficial `actions/checkout v7.0.1` foi verificada por API e pelo `action.yml` que declara `runs.using: node24`; SHA imutável: `3d3c42e5aac5ba805825da76410c181273ba90b1`. Referências: https://github.com/actions/checkout/releases/tag/v7.0.1 e https://github.com/actions/checkout/commit/3d3c42e5aac5ba805825da76410c181273ba90b1.

O workflow foi atualizado para o SHA exato, mantendo `persist-credentials: false`, `contents: read`, repositório público e limite de 6 minutos, sem dependências adicionais. O arquivo `tests/Contracts/test_ci_guardrails.py` contém oito verificações preventivas contra relaxamento acidental; não substituem inspeção de segurança do GitHub nem homologação IAM/CRM.

**Escopo:** somente o workflow consolidado da dev; não altera workflows históricos por fase, `main`, VPS, dados, credenciais ou produção. A CI real de PR e pós-merge confirmará compatibilidade com o runner. R01-G01..G11 e CRM CORE/SEG/AUT continuam ABERTOS, estado operacional `NO-GO`.

## CI-OPS-01 — mesmo comando na CI e em checkout local

Para evitar deriva entre testes executados no GitHub e em uma futura máquina de desenvolvimento, foi criado o entrypoint `tools/run_r01_offline_quality_gate.sh`. Ele verifica previamente a presença de Python3/PHP e do `EXPECTED_SHA` obrigatório, executa validadores EVID-02/03/04/05/07/08, testes de guardrails de CI, scanner SEC-03, lint do probe e regressão oficial **240 PHP/74 lint** por meio do script existente com guarda de SHA.

A CI unificada passa a executar **o mesmo script**, preservando configuração sem segredos, repositório público, checkout por SHA imutável e timeout de seis minutos. `tests/Contracts/test_quality_gate_coverage.py` adiciona sete verificações estáticas que impedem a retirada acidental de etapas da sequência. O resultado do script explicita `operational_decision=NO_GO` mesmo após sucesso de testes.

Uso em checkout completo com runtimes já instalados, sem necessidade de instalação ou serviços externos:

```sh
EXPECTED_SHA="$(git rev-parse HEAD)" EXPECTED_PHP=74 EXPECTED_CASES=240 bash tools/run_r01_offline_quality_gate.sh
```

A CI e o script não homologam API/CRM real, identidade, ACL, licenciamento ou VPS. `main` e produção inalteradas; gates R01-G01..G11 e CRM CORE/SEG/AUT continuam ABERTOS.
