# F163 / EVID-25 — Ensaio offline dos 13 cenários OP-01

**Data:** 10/11/2026. **Escopo:** somente PHP sintético do Imagine AI Agents, sem rede, sem CRM real e sem credenciais. **Decisão operacional: NO-GO.**

## Objetivo

O [pacote de aceite bilateral](evidencias/op01-pacote-aceite-bilateral.json) lista **13 cenários de homologação reais ainda não executados**, `OP01-N01..N11` e `OP01-P01..P02`. O contrato proposto pelo [CRM PR #57](https://github.com/rafhael100/imagine-crm/pull/57) segue **OPEN/DRAFT** e não oferece API autenticada para validação. Este trabalho **não executa esses testes reais**. Ele ensaia, no laboratório PHP, um *análogo sintético de cada cenário* para impedir regressões no lado consumidor enquanto CRM CORE/SEG/AUT examinam o contrato.

## Mapeamento um a um

| Cenário do pacote | Ensaio sintético U63 | Semântica ensaiada localmente |
| --- | --- | --- |
| OP01-N01 | U63-OP01-N01 | Ausência/revogação do envelope fictício |
| OP01-N02 | U63-OP01-N02 | Issuer e audience divergentes |
| OP01-N03 | U63-OP01-N03 | Suspensão de empresa e membro no owner mock |
| OP01-N04 | U63-OP01-N04 | Recurso estrangeiro e inexistente, negação uniforme |
| OP01-N05 | U63-OP01-N05 | Campos pessoais ou desconhecidos negados |
| OP01-N06 | U63-OP01-N06 | Operação de escrita ou contrato incompatível negados |
| OP01-N07 | U63-OP01-N07 | Replay entre dois gateways compartilhando o **mesmo objeto em memória** |
| OP01-N08 | U63-OP01-N08 | Revogação antes de invocar leitura fictícia **sem fila real** |
| OP01-N09 | U63-OP01-N09 | Indisponibilidade fictícia de owner e registro de nonce |
| OP01-N10 | U63-OP01-N10 | Campo extra com marcador de segredo não contaminando resposta/telemetria |
| OP01-N11 | U63-OP01-N11 | Saturação de contador antes de parse/consumo de nonce |
| OP01-P01 | U63-OP01-P01 | Leitura mínima própria de uma referência sintética |
| OP01-P02 | U63-OP01-P02 | Duas colunas fictícias permitidas em ordem alternativa |

O registro `docs/evidencias/op01-casos-homologacao.csv` **permanece íntegro**, com os 13 `NAO_EXECUTADO`. O teste Python `test_op01_synthetic_rehearsal_coverage.py` garante a paridade entre os IDs de cenário do CSV e os nomes de regressão do PHP e bloqueia adulteração da situação de homologação.

## Evidência técnica exigida

**Gate esperado após os 13 novos ensaios: 546 testes PHP / 82 arquivos de lint + Python**, revisão no SHA exato e validação pós-merge na branch de desenvolvimento.

## O que o ensaio não comprova

Os gateways compartilham estado **em um único processo**, não há cache distribuído, reinício real, fila de mensagens, prova criptográfica, identidade de serviço ou usuário, ACL/RLS persistente, banco CRM, telemetria durável, SLA, rollback de infraestrutura ou autenticação. Por isso **nenhum gate é marcado como homologado**; R01-G01..G11 e CRM CORE/SEG/AUT permanecem ABERTOS/NO-GO. A operação real só pode progredir com especificação aceita bilateralmente, testes positivos e negativos integrados em ambiente isolado e aprovação humana independente. Sem alteração em `main`, VPS, produção, clientes, segredos ou gastos.
