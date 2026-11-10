# Imagine AI Agents — estado atual

**Consolidado em 10/11/2026.** Fonte prioritária: estado da branch e dos PRs no GitHub; os SHAs abaixo representam apenas verificações realizadas nesta data. **Sem aprovação de produção.**

## Estado executivo

**Fase:** laboratório técnico PHP inteiramente sintético, com execução offline e integrações fictícias. O fluxo de leitura mínima OP-01 e a ferramenta de sugestão são demonstráveis localmente, mas **o produto operacional não está finalizado**. Não existe integração autenticada com o Imagine CRM, execução em VPS ou acesso a clientes. **Decisão: NO-GO** para piloto real e produção.

**Branch de trabalho:** `dev/ft01-aia-core-mocks-2026-11-06`; `main` permanece fora desta entrega. **Baseline verificável do laboratório: 500 testes PHP, lint de 82 arquivos PHP, testes Python de contrato, isolamento e demonstração.** O número de testes não é percentual de conclusão do produto.

## Últimas entregas verificadas

**SEC-35 em revisão:** o coordenador também impede regressão `PENDENTE → INDETERMINADO` em respostas de adaptadores inconsistentes, sem substituir objetos de evidência já conhecidos. Oito regressões U57-01..08; normaliza respostas sem prova e mantém status-only após timeout. [SEC-35](docs/AIA-FT01-SEC-35-COORDENADOR-MONOTONICO.md). Sem CRM real.

**SEC-34 integrada à dev:** a versão sintética selecionada deixa de ser apresentada como ativa quando a revisão associada é revogada; consultas com relógio explícito também verificam validade temporal. Sete regressões U56-01..07. [SEC-34](docs/AIA-FT01-SEC-34-REVALIDACAO-REVISAO-ATIVA.md). Não aprova versões reais.

**SEC-33 integrada à dev:** impede regressão de evidências sintéticas de `PENDENTE` para `INDETERMINADO`, preserva replays não terminais sem alocar novo efeito e mantém resultados terminais imutáveis. Seis regressões U55-01..06. [SEC-33](docs/AIA-FT01-SEC-33-EVIDENCIAS-MONOTONICAS.md). Sem CRM ou IAM real.

**SEC-32 integrada à dev:** limite de 256 efeitos fictícios por instância do gateway, sem expulsão de histórico e com falha fechada para novas submissões; sete regressões U54-01..07. [Documento SEC-32](docs/AIA-FT01-SEC-32-EFEITOS-LIMITADOS.md). Sem vínculo com CRM real ou autorização de piloto.

**SEC-31 integrada à dev:** limites de 256 fontes sintéticas e 1.024 registros de seleção/entrega, sem apagar histórico, com negação quando a trilha não puder registrar o resultado. Sete regressões U53-01..07. [Documento SEC-31](docs/AIA-FT01-SEC-31-LIMITES-CONHECIMENTO.md). Nenhum serviço externo de conhecimento é utilizado.

**SEC-30 integrada em desenvolvimento:** o catálogo de modelos e snapshots sintéticos possui limites definidos, sem evicção de versões e com preservação de conflitos, replays e desativação. [Documento técnico](docs/AIA-FT01-SEC-30-CATALOGO-LIMITADO.md). **Não é uma publicação de agentes para clientes.**

**SEC-29 em revisão:** limite de decisões e eventos do controle de versões simulado, com reserva de espaço para suspensão e seis regressões. [Evidência técnica SEC-29](docs/AIA-FT01-SEC-29-RESERVA-AUDITORIA-VERSOES.md). A execução segue offline e o CRM real continua **NO-GO**.

**SEC-28:** o quadro de revisão local limita registros de votação a 128 pacotes sem descartar decisões anteriores. O segundo parecer de pacotes já iniciados continua possível. [Detalhes e regressões](docs/AIA-FT01-SEC-28-REVISOES-BOUNDED.md). **Isto não é homologação real.**

**SEC-26 / PR #194** vinculou a identidade do consumo ao estado do transporte ([CI pós-merge #42863662530](https://github.com/rafhael100/imagine-ai-agents/actions/runs/42863662530) success). **SEC-27 / PR #195** limitou IDs e unidades no domínio de uso ([CI pós-merge #42864322239](https://github.com/rafhael100/imagine-ai-agents/actions/runs/42864322239) success). [EVID-23](docs/AIA-R01-EVID-23-IDENTIDADE-USO-446.md) registra evidência e limites. Os marcos anteriores abaixo são históricos.

A [SEC-25 / PR #192](https://github.com/rafhael100/imagine-ai-agents/pull/192) limitou o medidor de uso em memória e garantiu falha segura sem perda de idempotência; [CI pós-merge #42859143202](https://github.com/rafhael100/imagine-ai-agents/actions/runs/42859143202) success. Ver [EVID-22](docs/AIA-R01-EVID-22-MEDIDOR-USO-433.md). Entregas anteriores abaixo são históricas.

[SEC-23 / PR #189](https://github.com/rafhael100/imagine-ai-agents/pull/189) aplicou limites a Intent/Approval ([CI pós-merge #42847828963](https://github.com/rafhael100/imagine-ai-agents/actions/runs/42847828963) success). [SEC-24 / PR #190](https://github.com/rafhael100/imagine-ai-agents/pull/190) estendeu os limites aos objetos básicos Run/Step ([CI pós-merge #42848621884](https://github.com/rafhael100/imagine-ai-agents/actions/runs/42848621884) success). Ver [EVID-21](docs/AIA-R01-EVID-21-LIMITES-DOMINIO-427.md). Marcos anteriores abaixo são históricos.

As SEC-21 e SEC-22 foram integradas somente à `dev`: [PR #186](https://github.com/rafhael100/imagine-ai-agents/pull/186) exige Run/Step canônicos na interface de ferramentas ([CI pós-merge #42836105212](https://github.com/rafhael100/imagine-ai-agents/actions/runs/42836105212) success); [PR #187](https://github.com/rafhael100/imagine-ai-agents/pull/187) limita o tamanho dos IDs ([CI pós-merge #42836672922](https://github.com/rafhael100/imagine-ai-agents/actions/runs/42836672922) success). [EVID-20](docs/AIA-R01-EVID-20-RUN-STEP-CANONICO-414.md) apresenta o estado técnico vigente. Os marcos anteriores abaixo são históricos.

A [SEC-20 / PR #182](https://github.com/rafhael100/imagine-ai-agents/pull/182) corrigiu a auditoria de projeções inconsistentes antes de registrar sucesso; [CI pós-merge #42759202848](https://github.com/rafhael100/imagine-ai-agents/actions/runs/42759202848) aprovada. Consulte [EVID-18](docs/AIA-R01-EVID-18-SEGURANCA-PROJECAO-403.md). O [README](README.md) atual foi consolidado e a versão completa anterior permanece arquivada.

| Marco | Mudança | Evidência |
| --- | --- | --- |
| [F1-30 — PR #173](https://github.com/rafhael100/imagine-ai-agents/pull/173) | Leitura de ferramenta somente pela borda OP-01; leitor direto legado bloqueado | [CI pós-merge #42748908649](https://github.com/rafhael100/imagine-ai-agents/actions/runs/42748908649) |
| [SEC-16 — PR #174](https://github.com/rafhael100/imagine-ai-agents/pull/174) | Trilha de ferramentas limitada e validação de capacidade antes dos efeitos | [CI pós-merge #42749287014](https://github.com/rafhael100/imagine-ai-agents/actions/runs/42749287014) |
| [SEC-17 — PR #175](https://github.com/rafhael100/imagine-ai-agents/pull/175) | Validação de saída da sugestão e rejeição de texto/campos não confiáveis | [CI pós-merge #42749779477](https://github.com/rafhael100/imagine-ai-agents/actions/runs/42749779477) |
| [SEC-18 — PR #177](https://github.com/rafhael100/imagine-ai-agents/pull/177) | Run sintética encerrada após falha de sugestão, impedindo efeito posterior | [CI pós-merge #42753075500](https://github.com/rafhael100/imagine-ai-agents/actions/runs/42753075500) |
| [SEC-19 — PR #178](https://github.com/rafhael100/imagine-ai-agents/pull/178) | Códigos públicos fechados; erros arbitrários do gateway neutralizados | [CI pós-merge #42753536675](https://github.com/rafhael100/imagine-ai-agents/actions/runs/42753536675) |
| [EVID-16 — PR #179](https://github.com/rafhael100/imagine-ai-agents/pull/179) | Documenta riscos, CIs verificadas e dependências reais | Integrado em `ec9425fb21c4fee2eecd2f15efb353b3744687fe` |

**Reproduzir em checkout revisado:**

```bash
php examples/f127-read-only-pilot-demo.php
EXPECTED_SHA="$(git rev-parse HEAD)" EXPECTED_PHP=82 EXPECTED_CASES=465 bash tools/run_r01_offline_quality_gate.sh
```

O comando acima exige PHP e Python já instalados, não emite credenciais e não acessa CRM real. O sucesso não abre gates operacionais.

## Pacote formal de revisão entregue, sem aceite (10/11/2026)

O [PR #184](https://github.com/rafhael100/imagine-ai-agents/pull/184) integrou um [pacote JSON de aceite bilateral](docs/evidencias/op01-pacote-aceite-bilateral.json) que correlaciona **7 decisões OP01-R01..R07**, **14 gates** e **13 cenários não executados**, com propostas de responsáveis e evidências pendentes. A [CI pós-merge #42770907072](https://github.com/rafhael100/imagine-ai-agents/actions/runs/42770907072) passou. Consulte [EVID-19](docs/AIA-R01-EVID-19-PACOTE-BILATERAL-OP01.md).

O registro foi comunicado no CRM PR #57 **sem aprovação ou merge**. Os dados deste pacote não provam a execução de testes reais e permanecem **NO-GO**.

## Bloqueadores de integração CRM ↔ Agents

O [CRM PR #57](https://github.com/rafhael100/imagine-crm/pull/57) permanece **OPEN/DRAFT, não integrado e não homologado** em 10/11/2026. A versão documental revisada foi reconciliada com o Agents em [EVID-17](docs/AIA-R01-EVID-17-RECONCILIACAO-CRM-PR57-2026-11-10.md), usando HEAD CRM `97bc1e8d6ce4cf4b52378472c31cb49de6a66d51` e blob `cefb390ec41a76d486b137279d92627f097d1d47`; isso **não** aprova nenhum gate. A proposta é documental; nenhuma rota, API real ou permissão foi autorizada. As decisões precisam de aceite independente por área:

- **R01-G01/G03 e CRM AUT/SEG:** identidade serviço-serviço, issuer/audience, vínculo principal/tenant, expiração, revogação e anti-replay distribuído;
- **R01-G02 e CRM CORE/SEG:** autorização final pelo backend CRM por tenant, membership, recurso, finalidade e campo, incluindo revogação e isolamento multiempresa;
- **R01-G05/G08:** classificação dos campos reais, base legal, minimização, retenção, esquema/versionamento de mensagens, erros não enumeráveis e responsabilidade de cada componente;
- **R01-G07/G10:** ambiente isolado, segredos sob controle, revisão independente de segurança, testes de integração positivos/negativos, recuperação e rollback;
- **R01-G11:** responsáveis, limites financeiros e autorização específica do piloto. Produção exige aceite adicional.

**Todos os gates R01-G01..G11 e CRM CORE/SEG/AUT seguem ABERTOS/NO-GO.** Nenhum teste sintético, CI verde ou comentário em PR substitui a aprovação e a evidência de runtime requeridas.

## Referências atuais e histórico preservado

- [Dossiê técnico EVID-16](docs/AIA-R01-EVID-16-SEGURANCA-TOOL-397.md) — SEC-18/19, evidências e limites.
- [Handoff EVID-15](docs/AIA-R01-EVID-15-HANDOFF-READ-TOOL-389.md) e [EVID-14](docs/AIA-R01-EVID-14-CHECKPOINT-LEITURA-OP01.md) — entregas anteriores.
- [Roadmap atual](docs/ROADMAP.md) — sequência de decisões e critérios de saída.
- [Status histórico integral, preservado sem perda](docs/historico/STATUS-ATUAL-ANTES-CONSOLIDACAO-2026-11-10.md) — decisões, PRs e registros datados; **não** interpretar métricas antigas como status atual.
- [Roadmap histórico integral](docs/historico/ROADMAP-ANTES-CONSOLIDACAO-2026-11-10.md) — evolução e ideias anteriores.

**Restrições mantidas:** nenhuma modificação em `main`, VPS ou produção, sem dados de cliente, segredos, chamadas pagas ou novos contratos. A continuidade permitida é revisão e desenvolvimento controlado em `dev`, não autorização de ativação comercial.
