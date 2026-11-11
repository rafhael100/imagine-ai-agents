# Imagine AI Agents

**10/11/2026:** PHP sintético offline na branch `dev/ft01-aia-core-mocks-2026-11-06`. **Sem serviço a clientes, CRM real, autenticação ou produção. NO-GO.**

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

## Controles de identidade de uso — SEC-26/27

Os [PRs #194](https://github.com/rafhael100/imagine-ai-agents/pull/194) e [#195](https://github.com/rafhael100/imagine-ai-agents/pull/195) vinculam o estado de transporte à identidade de medição e impõem limites nos objetos de tentativa, uso e prova. [CIs pós-merge #42863662530](https://github.com/rafhael100/imagine-ai-agents/actions/runs/42863662530) e [#42864322239](https://github.com/rafhael100/imagine-ai-agents/actions/runs/42864322239) aprovadas; ver [EVID-23](docs/AIA-R01-EVID-23-IDENTIDADE-USO-446.md). Esses controles são sintéticos.

## Revisão independente com limite de memória — SEC-28

O quadro de revisão sintético agora guarda até **128 pacotes com votos**, sem apagar decisões ou rejeições. Pacotes já iniciados ainda podem receber a segunda revisão independente. Veja [SEC-28](docs/AIA-FT01-SEC-28-REVISOES-BOUNDED.md). Isso não autentica revisores reais nem libera versões para clientes.

## Publicação e rollback sintéticos — SEC-29

O controle local de versões limita as decisões e os registros de auditoria e reserva espaço para suspensão segura. [SEC-29](docs/AIA-FT01-SEC-29-RESERVA-AUDITORIA-VERSOES.md) acrescenta seis regressões. **Nenhuma versão é publicada em produção**, e esses votos/decisões não substituem autorização operacional.

## Catálogo de versões com limites — SEC-30

O catálogo técnico local limita **64 modelos e 256 snapshots**, sem eliminar registros anteriores; replays idempotentes e suspensão continuam possíveis. [SEC-30](docs/AIA-FT01-SEC-30-CATALOGO-LIMITADO.md) traz os testes. Não há registry nem publicação real.

## SEC-31 — fontes de conhecimento e trilha com limites

O catálogo de fontes sintéticas agora aceita até **256 fontes** e a trilha de seleção/entrega até **1.024 eventos**, negando novos registros quando cheia, sem apagar decisões antigas e sem expor conteúdo fictício. A entrega de passagens falha de forma segura sem espaço de auditoria. Veja [SEC-31](docs/AIA-FT01-SEC-31-LIMITES-CONHECIMENTO.md). **Não é RAG real nem autorização de acesso a clientes.**

## Evolução recente e ensaios OP-01

As correções [SEC-32](docs/AIA-FT01-SEC-32-EFEITOS-LIMITADOS.md) a [SEC-40](docs/AIA-FT01-SEC-40-MANIFESTO-LIMITADO.md) limitam memória, entradas e estados de mocks. Consulte [STATUS-ATUAL](STATUS-ATUAL.md) para suas evidências individuais.

O [ensaio F163 / EVID-25](docs/AIA-R01-EVID-25-ENSAIOS-OP01-13-CENARIOS.md) cobre no laboratório os **13 cenários OP-01** sem alterar o aceite real: 13 `NAO_EXECUTADO` no CRM, gates em **NO-GO**. Sem API, token ou integração bilateral.

## Executar e verificar, sem rede

Requisitos: checkout Git completo com PHP 8.1+ e Python 3 já presentes, sem instalar serviços ou baixar dependências.

```bash
php examples/f127-read-only-pilot-demo.php
php tests/Unit/run.php
EXPECTED_SHA="$(git rev-parse HEAD)" EXPECTED_PHP=99 EXPECTED_CASES=800 bash tools/run_r01_offline_quality_gate.sh
```

**OP-01:** histórico **699 testes PHP**, agora **800 PHP/lint 99**; [CI #43013609438](https://github.com/rafhael100/imagine-ai-agents/actions/runs/43013609438). PRs #255–259 integrados: [EVID-41](docs/AIA-R01-EVID-41-F184-F186-SEC58-59-CI-POSTMERGE.md); [EVID-40](docs/AIA-R01-EVID-40-SEC57-REV02-F183-POSTMERGE.md). **NO-GO**.

**SEC-53 integrada (PR #237): 648 testes PHP**. [CI #42945854088](https://github.com/rafhael100/imagine-ai-agents/actions/runs/42945854088), [EVID-38](docs/AIA-R01-EVID-38-SEC53-CI-POSTMERGE.md). **NO-GO**.

**SEC-52 integrada (PR #235): 640 testes PHP**. [EVID-37](docs/AIA-R01-EVID-37-SEC52-CI-POSTMERGE.md).

**SEC-51 integrada (PR #233): 633 testes PHP**. [EVID-36](docs/AIA-R01-EVID-36-SEC51-CI-POSTMERGE.md).

**SEC-50 integrada (PR #230): 629 testes PHP**. [EVID-35](docs/AIA-R01-EVID-35-SEC50-CI-POSTMERGE.md). **Sem CRM real.**

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

O [README histórico](docs/historico/README-ANTES-CONSOLIDACAO-2026-11-10.md), o [status histórico](docs/historico/STATUS-ATUAL-ANTES-CONSOLIDACAO-2026-11-10.md) e o [roadmap histórico](docs/historico/ROADMAP-ANTES-CONSOLIDACAO-2026-11-10.md) foram preservados; suas métricas não são o baseline atual.
