# FT01/SEC-30 — Limites e identidade imutável do catálogo de Agentes

**Data:** 10/11/2026. **Escopo:** laboratório offline com versões sintéticas de Agentes, sem implantação e sem CRM real. **Decisão operacional: NO-GO.**

## Problema resolvido

O `MockAgentTemplateCatalog` armazenava modelos, snapshots e índices por versão sem limite de quantidade. Criar novos modelos ou versões repetidamente podia ampliar os mapas PHP indefinidamente. A expulsão de versões antigas não seria uma correção segura: perderia o histórico de conflitos e permitiria reaproveitar identificadores de versões já vistas.

## Comportamento implantado no laboratório

- **Até 64 modelos (`templates`) por instância**, com `CATALOG_CAPACITY_DENIED` ao tentar inserir o 65º.
- **Até 256 snapshots por instância**, com o mesmo erro ao tentar inserir um snapshot novo depois de atingir o teto.
- **Sem expulsão de histórico:** versões anteriormente cadastradas continuam consultáveis e verificáveis.
- **Idempotência preservada:** inserir novamente um snapshot com mesmo ID, versão e digest não gasta capacidade nem altera sua identidade.
- **Colisões preservadas:** mesmo ID com manifesto diferente, ou mesmo modelo/versão associado a outro snapshot, ainda retorna `SNAPSHOT_CONFLICT`.
- **Revogação segura:** desabilitar uma versão conhecida e suspender a seleção local continuam possíveis mesmo com catálogo cheio.
- A capacidade é verificada **antes** de alterar os índices de snapshots e versões, evitando entradas parciais.

## Regressões U52-01..07

64 modelos e recusa do próximo; entrada inválida sem consumir a última vaga; 256 snapshots e recusa do 257º; inserção idempotente em capacidade máxima; conflito por digest; conflito por versão; desabilitação de versão e suspensão de seleção existentes.

**Gate exigido:** 465 casos PHP, sintaxe de 82 arquivos PHP e verificações Python de contrato, isolamento e prontidão. CI no SHA de revisão e, após merge, CI na `dev`.

## Bloqueios operacionais

O catálogo continua local e fictício, não é registry operacional, deployment, IAM ou assinatura de publicações. [CRM PR #57](https://github.com/rafhael100/imagine-crm/pull/57) permanece em rascunho; R01-G01..G11 e CRM CORE/SEG/AUT continuam **ABERTOS/NO-GO**. A aprovação bilateral, a autorização por empresa e campo dentro do CRM e os testes independentes são pré-condições para qualquer integração real. Nenhuma mudança na `main`, VPS, produção, segredos, clientes ou novos custos.
