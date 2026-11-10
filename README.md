# Imagine AI Agents

**Estado em 10/11/2026:** laboratório técnico PHP com dados sintéticos e execução offline, desenvolvido somente na branch `dev/ft01-aia-core-mocks-2026-11-06`. **Não é um serviço disponível a clientes, não integra o CRM real, não autentica usuários e não está liberado para produção.** Decisão operacional: **NO-GO**.

## O que está implementado em laboratório

- Coordenação supervisionada de execuções, etapas, aprovações e efeitos **fictícios**, sem SQL, clientes, CRM real ou IA externa.
- Leitura mínima `crm.lead.read_minimal` (OP-01) por uma borda sintética com política fictícia do dono do recurso, isolamento de contexto, controle de replay e auditoria local.
- Ferramenta `READ` governada pela borda; acesso direto legado bloqueado. Sugestões retornam conteúdo fixo e validado; falhas encerram Runs simuladas e não geram mutações automáticas.
- **SEC-20:** validação integral da projeção fictícia **antes** de registrar `READ_SYNTHETIC_ONLY`, com regressões de campos extras, dados incorretos e referência de outro recurso ([PR #182](https://github.com/rafhael100/imagine-ai-agents/pull/182)).

O [dossiê EVID-18](docs/AIA-R01-EVID-18-SEGURANCA-PROJECAO-403.md) descreve a validação mais recente, com CIs e limites.

## Executar e verificar, sem rede

Requisitos: checkout Git completo com PHP 8.1+ e Python 3 já presentes, sem instalar serviços ou baixar dependências.

```bash
php examples/f127-read-only-pilot-demo.php
php tests/Unit/run.php
EXPECTED_SHA="$(git rev-parse HEAD)" EXPECTED_PHP=82 EXPECTED_CASES=403 bash tools/run_r01_offline_quality_gate.sh
```

**Baseline verificado na dev:** **403 testes PHP / lint de 82 arquivos PHP**, mais os verificadores Python de contrato e isolamento, [CI pós-merge #42759202848](https://github.com/rafhael100/imagine-ai-agents/actions/runs/42759202848) `success`. Essas provas **não** equivalem a IAM, ACL multiempresa, privacidade ou homologação operacional.

## Próximo marco de produto

O [CRM PR #57](https://github.com/rafhael100/imagine-crm/pull/57) é uma proposta **OPEN/DRAFT não homologada**, sincronizada documentalmente em [EVID-17](docs/AIA-R01-EVID-17-RECONCILIACAO-CRM-PR57-2026-11-10.md). Faltam decisões e evidências independentes de autenticação serviço-serviço, autorização por empresa/recurso/campo **no backend CRM**, privacidade, retenção, replay distribuído, ambiente isolado, rollback e autorização formal de piloto. **R01-G01..G11 e CRM CORE/SEG/AUT permanecem ABERTOS/NO-GO.**

O fluxo de decisões e critérios de saída está no [roadmap operacional](docs/ROADMAP.md), e a posição consolidada está em [STATUS-ATUAL.md](STATUS-ATUAL.md). Consulte [AGENTS.md](AGENTS.md) para regras de contribuição e proibições de `main`, VPS, segredos, dados de clientes e custos.

## Histórico integral preservado

A versão antiga deste README, incluindo instruções e métricas **históricas** (que não devem ser interpretadas como estado vigente), foi mantida **integralmente**, sem perda, em [README-ANTES-CONSOLIDACAO-2026-11-10.md](docs/historico/README-ANTES-CONSOLIDACAO-2026-11-10.md). Também estão preservados os [status](docs/historico/STATUS-ATUAL-ANTES-CONSOLIDACAO-2026-11-10.md) e [roadmap](docs/historico/ROADMAP-ANTES-CONSOLIDACAO-2026-11-10.md) anteriores.
