# AIA-R01/EVID-11 — Entrega da fase offline e handoff técnico para piloto CRM OP-01

**Data:** 09/11/2026. **Versão:** v0.1, proposta de revisão. **Estado de decisão:** NO-GO operacional. Não é ata de aceite nem solicitação para credenciais.

## 1. Base técnica verificada, sem extrapolar

| Entrega | Prova reproduzível | Escopo |
| --- | --- | --- |
| [PR Agents #159](https://github.com/rafhael100/imagine-ai-agents/pull/159) | [CI #42737036742](https://github.com/rafhael100/imagine-ai-agents/actions/runs/42737036742) `success` no SHA `ead3d791c7c512bd39a81a14b48afea14fb27c48`; merge dev `4a03adf5f91e760950adaa3239fb2c8dba05da6d` | Schema público de resposta OP-01 e paridade das respostas **reais do PHP sintético**, testando sucesso, replay, negação uniforme, campo proibido, troca de empresa e ausência de grant |
| [PR Agents #160](https://github.com/rafhael100/imagine-ai-agents/pull/160) | [CI #42738080284](https://github.com/rafhael100/imagine-ai-agents/actions/runs/42738080284) `success` no SHA `416e51d820331ab496b71db0d66ea49b62cbee17`; merge dev `f46dd7191b59100f26c12251c05fc2101fb2b47d` | Fluxo em memória: leitura mínima autorizada fictícia → geração de sugestão fixa → encerramento de Run sem escrita; doze regressões |
| Gate offline oficial | `tools/run_r01_offline_quality_gate.sh` com `EXPECTED_SHA` exato do checkout | **351 casos PHP / 81 arquivos PHP com lint** e testes de contrato Python, inclusive EVID-10 |

**Precisão:** as duas CIs acima atestam os SHAs revisados dos PRs. Não as denominar CIs pós-merge sem verificação separada. O sucesso do laboratório **não fecha** um único gate real.

## 2. O que está funcional apenas em memória

- Contratos rígidos de leitura e saída, mínimos `ref`/`stage`, mensagens de negação que não enumeram recursos, verificação de escopo e replay **sintéticos**.
- Orquestração de Runs, passos, sugestões e controle de efeito com trilhas, capacidade, idempotência e falhas fechadas **locais**.
- O fluxo F1-26 **não executa** OP-05/OP-06 (escrita no CRM). A Run concluída apenas indica fim da demonstração, não operação externa efetivada.
- O provedor de sugestões não realiza uma segunda leitura independente: recebe campos filtrados pelo mock do produto dono.
- Não existe token, identidade validada pelo transporte, autorização real de usuário, RLS, SQL, HTTP, LLM externo, observabilidade distribuída ou recuperação pós-processo.

## 3. Critérios de saída para o próximo estágio (todos pendentes)

| Gate | Decisão ou evidência que ainda falta | Revisão/aceite funcional |
| --- | --- | --- |
| R01-G01 + CRM AUT | Serviço autenticado, emissor/audiência, vínculo de tenant/usuário, verificação de assinatura, TTL, rotação, revogação e teste anti-replay distribuído | IAM/Segurança + AUT CRM |
| R01-G02 + CRM CORE/SEG | Autorização no **backend CRM** por empresa, principal, recurso, finalidade e **campo**; testes negativos de isolamento entre empresas e política deny-by-default | CORE + SEG CRM |
| R01-G05 | Base legal/finalidade, dados permitidos, política de prompts, minimização, retenção/expurgo, subprocessadores e resposta a incidentes | Privacidade/Jurídico + CORE |
| R01-G08 | Aprovação bilateral da versão do contrato, nomes reais dos campos, resposta/erros, responsabilidade de recurso e rollback | CORE/Arquitetura CRM + AIA |
| R01-G10 | Ambiente de desenvolvimento isolado, gestão de credenciais, rede, evidência de backup/restauração, observabilidade mínima e plano de parada | Infra/Operações + SEG |
| R01-G11 | Nomeação dos responsáveis, escopo, limite de dados autorizados, orçamento/teto de custo e aprovação expressa do piloto | Direção + lideranças |
| Outros R01-G03/G04/G06/G07/G09 | Idempotência/recuperação real, aprovações, quotas, segurança independente e licença/fornecedor | Donos específicos por assunto |

**Situação externa verificada:** o [CRM PR #57](https://github.com/rafhael100/imagine-crm/pull/57) continua `OPEN/DRAFT`, **sem merge e sem aceite bilateral**. O documento nesse PR é somente proposta, não endpoint, controller ou permissão. A revisão deve reconciliar o HEAD atual da dev CRM antes de qualquer futura integração.

## 4. Próxima execução autorizável somente com gates fechados

1. Concluir revisão CRM CORE/SEG/AUT/Privacidade dos contratos e fixar sua versão. Não marcar `GO` por presença de uma linha CSV ou merge documental.
2. Homologar identidade serviço-serviço e autorização final em API de teste isolada, com segredos e dados exclusivamente de teste autorizados.
3. Implementar primeiro **somente OP-01**, sem acesso SQL direto ao CRM e sem OP-05/06 ou envio a LLM externo.
4. Executar os casos negativos de [EVID-01](AIA-R01-EVID-01-DOSSIE-ACEITE-PILOTO-CRM-OP01.md): emissor inválido, empresa errada, usuário suspenso, campo proibido, replay entre instâncias, revogação, timeout, autorização indisponível, erros não enumeráveis e vazamento de logs; também casos positivos para projeção estrita.
5. Registrar os resultados de serviço real com versão de ambiente, hashes, responsáveis, falhas e retestes; realizar revisão independente e preparar rollback.
6. Requerer decisão expressa sobre o piloto. **Produção exige outro aceite posterior**.

## 5. Limite deste handoff

- `main`, VPS, CRM real, credenciais, dados privados, provedores pagos e produção **não** são alterados pelo handoff.
- R01-G01..G11 e CRM CORE/SEG/AUT seguem **ABERTOS**. Não presumir nomes de aprovadores, pareceres ou orçamento autorizado.
- O período FT01 já fornece uma demonstração offline integrada e regressão reproduzível; novas demonstrações fictícias isoladas **não devem ser usadas para aparentar conclusão da integração**.
- O próximo marco de produto é a **integração segura, aprovada e testada com o CRM**, não apenas mais uma PR de mocks.

**Decisão registrada:** **NO-GO para piloto real e produção** até fechamento documental e técnico dos gates acima.
