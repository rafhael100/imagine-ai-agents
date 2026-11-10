# Imagine AI Agents

**Estado em 10/11/2026:** laboratório técnico PHP com dados sintéticos e execução offline, desenvolvido somente na branch `dev/ft01-aia-core-mocks-2026-11-06`. **Não é um serviço disponível a clientes, não integra o CRM real, não autentica usuários e não está liberado para produção.** Decisão operacional: **NO-GO**.

## O que está implementado em laboratório

- Coordenação supervisionada de execuções, etapas, aprovações e efeitos **fictícios**, sem SQL, clientes, CRM real ou IA externa.
- Leitura mínima `crm.lead.read_minimal` (OP-01) por uma borda sintética com política fictícia do dono do recurso, isolamento de contexto, controle de replay e auditoria local.
- Ferramenta `READ` governada pela borda; acesso direto legado bloqueado. Sugestões retornam conteúdo fixo e validado; falhas encerram Runs simuladas e não geram mutações automáticas.
- **SEC-20:** validação integral da projeção fictícia **antes** de registrar `READ_SYNTHETIC_ONLY`, com regressões de campos extras, dados incorretos e referência de outro recurso ([PR #182](https://github.com/rafhael100/imagine-ai-agents/pull/182)).

**SEC-21/22:** a ferramenta aceita somente Run/Step canônicos do coordenador, e IDs excessivos são recusados antes do registro de estado. [PR #186](https://github.com/rafhael100/imagine-ai-agents/pull/186) e [PR #187](https://github.com/rafhael100/imagine-ai-agents/pull/187) passaram também nas CIs pós-merge; consulte o [dossiê EVID-20](docs/AIA-R01-EVID-20-RUN-STEP-CANONICO-414.md). O dossiê EVID-18 é histórico.

## Revisão de referências e aprovações — SEC-23/SEC-24

As [SEC-23 / PR #189](https://github.com/rafhael100/imagine-ai-agents/pull/189) e [SEC-24 / PR #190](https://github.com/rafhael100/imagine-ai-agents/pull/190) limitam entradas de Intents, aprovações e identificadores Run/Step também no domínio direto. [EVID-21](docs/AIA-R01-EVID-21-LIMITES-DOMINIO-427.md) reúne as provas e os bloqueios reais. São verificações de laboratório, não autenticação.

## Controle de uso e auditoria — SEC-25

O [PR #192](https://github.com/rafhael100/imagine-ai-agents/pull/192) limitou registros de uso, tentativas e auditoria em memória, com falha segura e sem excluir histórico de idempotência. [CI pós-merge #42859143202](https://github.com/rafhael100/imagine-ai-agents/actions/runs/42859143202) aprovada; detalhes em [EVID-22](docs/AIA-R01-EVID-22-MEDIDOR-USO-433.md). Isso **não** representa medição de cobrança real nem autorização de operação.

## Executar e verificar, sem rede

Requisitos: checkout Git completo com PHP 8.1+ e Python 3 já presentes, sem instalar serviços ou baixar dependências.

```bash
php examples/f127-read-only-pilot-demo.php
php tests/Unit/run.php
EXPECTED_SHA="$(git rev-parse HEAD)" EXPECTED_PHP=82 EXPECTED_CASES=433 bash tools/run_r01_offline_quality_gate.sh
```

**Baseline verificado na dev:** **433 testes PHP / lint de 82 arquivos PHP**, mais os verificadores Python de contrato e isolamento, [CI pós-merge #42759202848](https://github.com/rafhael100/imagine-ai-agents/actions/runs/42759202848) `success`. Essas provas **não** equivalem a IAM, ACL multiempresa, privacidade ou homologação operacional.

## Pacote de aceite bilateral — revisão pendente

O [pacote OP-01 gerado e versionado](docs/evidencias/op01-pacote-aceite-bilateral.json) reúne as **7 decisões**, os **14 gates** e os **13 cenários** ainda não executados, com [instruções de revisão EVID-19](docs/AIA-R01-EVID-19-PACOTE-BILATERAL-OP01.md). O verificador é executado no gate offline:

```bash
python3 tools/generate_op01_bilateral_packet.py --check
```

A [CI pós-merge #42770907072](https://github.com/rafhael100/imagine-ai-agents/actions/runs/42770907072) foi aprovada. A saída permanece `NO_GO`, não fornece credenciais e não aprova contratos.

## Próximo marco de produto

O [CRM PR #57](https://github.com/rafhael100/imagine-crm/pull/57) é uma proposta **OPEN/DRAFT não homologada**, sincronizada documentalmente em [EVID-17](docs/AIA-R01-EVID-17-RECONCILIACAO-CRM-PR57-2026-11-10.md). Faltam decisões e evidências independentes de autenticação serviço-serviço, autorização por empresa/recurso/campo **no backend CRM**, privacidade, retenção, replay distribuído, ambiente isolado, rollback e autorização formal de piloto. **R01-G01..G11 e CRM CORE/SEG/AUT permanecem ABERTOS/NO-GO.**

O fluxo de decisões e critérios de saída está no [roadmap operacional](docs/ROADMAP.md), e a posição consolidada está em [STATUS-ATUAL.md](STATUS-ATUAL.md). Consulte [AGENTS.md](AGENTS.md) para regras de contribuição e proibições de `main`, VPS, segredos, dados de clientes e custos.

## Histórico integral preservado

A versão antiga deste README, incluindo instruções e métricas **históricas** (que não devem ser interpretadas como estado vigente), foi mantida **integralmente**, sem perda, em [README-ANTES-CONSOLIDACAO-2026-11-10.md](docs/historico/README-ANTES-CONSOLIDACAO-2026-11-10.md). Também estão preservados os [status](docs/historico/STATUS-ATUAL-ANTES-CONSOLIDACAO-2026-11-10.md) e [roadmap](docs/historico/ROADMAP-ANTES-CONSOLIDACAO-2026-11-10.md) anteriores.
