# AIA-R01/EVID-06 — Integração contínua unificada e econômica da branch dev

**Situação:** proposta de infraestrutura de teste exclusivamente **GitHub Actions de repositório público**, sem API, credenciais ou backend de negócio. Não habilita acesso ao CRM nem homologa gates.

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

## Critérios de aceite desta entrega

Revisar o diff do workflow e executar CI real no commit da branch de revisão. Só integrar à dev se a execução tiver resultado comprovado como `success`, diff limitado ao escopo aprovado e ausência de mudanças em `main`. Depois conferir o primeiro run disparado automaticamente no HEAD integrado da dev. Se alguma validação falhar, registrar o bloqueio e corrigir na branch, sem silenciar testes.

**Status operacional permanece NO-GO.** CRM PR #57 em rascunho; CORE/SEG/AUT e R01-G01..G11 abertos. `main`, VPS, clientes e produção inalterados.
