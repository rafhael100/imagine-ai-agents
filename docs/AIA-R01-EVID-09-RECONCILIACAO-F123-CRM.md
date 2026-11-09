# AIA-R01 / EVID-09 — Reconciliação de evidências após FT01/F1-23

Data: 2026-11-09. Estado: **evidência documental / NO-GO operacional**. Este arquivo não substitui aceite de segurança, teste de integração ou decisão de direção.

## Proveniência verificável

| Controle | Evidência | Resultado |
| --- | --- | --- |
| Revisão e merge F1-23 | [PR Agents #151](https://github.com/rafhael100/imagine-ai-agents/pull/151) | Integrado somente em `dev/ft01-aia-core-mocks-2026-11-06`; squash `f50e15f906169d0c12a11c69ec79aab3cdc3e901` |
| CI de revisão | [GitHub Actions #42678725023](https://github.com/rafhael100/imagine-ai-agents/actions/runs/42678725023) | `success` no HEAD `b69f76bcc24f21d8255af5c06582c187c9ebb048`; gate exige 320 testes PHP e 79 arquivos PHP |
| Resposta do CRM | [PR CRM #57](https://github.com/rafhael100/imagine-crm/pull/57) | `OPEN/DRAFT`, não integrado; base `a99397630f05c5033207cd48c9d5d5dd4d8c0ea6`, head `a006bd427dbf34afbbbdec3098316c47a59fc608` |
| Gate de homologação | [Dossiê EVID-01](AIA-R01-EVID-01-DOSSIE-ACEITE-PILOTO-CRM-OP01.md) | R01-G01..G11 e CRM CORE/SEG/AUT em aberto |

**Precisão das evidências:** a execução CI citada comprova o HEAD do PR, não deve ser descrita como CI pós-merge no SHA do squash. Não se deduz `GO` de sucesso de testes sintéticos. O PR #151 contém texto histórico da falha inicial: a correção posterior foi aprovada no run #42678725023.

## Estado funcional do laboratório

- `SupervisedCoordinator::reconcile` reserva trilha antes de consultar um gateway **fictício**.
- `STATUS_UNAVAILABLE` encerra exceção lançada por consulta, propagando-a, sem texto arbitrário em auditoria.
- `STATUS_REJECTED` encerra resposta contraditória reprovada por `remember`, sem trocar uma prova terminal previamente conhecida.
- `ReadOnlyTraceReview` exige pares de abertura/fechamento e rejeita eventos injetados fora de sequência.
- Não há autenticação real, persistência durável, assinatura digital, credenciais, conexão CRM, banco ou chamada a LLM.

## Dependências de aceite bilateral (não inferir donos pessoais)

| Decisão | Evidência necessária | Autoridade funcional | Estado |
| --- | --- | --- | --- |
| Identidade serviço-serviço (G01) | emissor/audience, rotação, expiração, revogação, verificação em runtime | SEG/IAM + CRM CORE | ABERTO |
| ACL por empresa/recurso/campo (G02) | testes cruzados no backend CRM com negação por padrão | CRM CORE + SEG | ABERTO |
| Finalidade e minimização (G05) | campos efetivamente aprovados, base legal, retenção, operadores | Privacidade + CRM | ABERTO |
| Compatibilidade OP-01 (G08) | schema/versão bilateral, erros não enumeráveis e rollback | CRM CORE + Agents | ABERTO |
| Infra e operações (G10) | ambiente isolado, segredos, logging, backups, incidentes | Infra + SEG | ABERTO |
| Decisão de piloto (G11) | responsáveis nomeados, limite de dados e custo, plano de parada | Direção e responsáveis | ABERTO |
| CORE/SEG/AUT CRM | revisão independente e evidência de cada subsistema | autoridades do CRM | ABERTO |

Demais gates R01-G03/G04/G06/G07/G09 também permanecem **ABERTOS**. O CRM PR #57 não constitui assinatura ou aceite de quem responde por essas áreas.

## Sequência segura a partir deste checkpoint

1. Revisar o documento documental CRM #57 contra o HEAD atual da dev CRM; resolver divergência de base antes de qualquer possível merge no CRM (não automático).
2. Obter decisões escritas sobre os contratos G01/G02/G05/G08/G10 e CORE/SEG/AUT; registrar versão e evidências independentes.
3. Em ambiente de teste especificamente autorizado, implementar **primeiro somente leitura** `crm.lead.read_minimal` sem LLM externo, sem escrita e com dados artificiais.
4. Executar matriz negativa OP01-N01..N11, casos positivos P01..P02, segregação multiempresa e revogação; versionar artefatos reproduzíveis.
5. Solicitar autorização expressa de piloto (G11) e, separadamente, de produção. Não antecipar publicação, tokens, custos ou acesso a clientes.

**Decisão agora:** `NO-GO` operacional. `main`, VPS, APIs reais, pagamentos e produção intocados por esta reconciliação.
