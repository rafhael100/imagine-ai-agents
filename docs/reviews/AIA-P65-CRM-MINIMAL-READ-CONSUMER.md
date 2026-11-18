# P65 — projeção mínima da consulta privada do CRM

## Escopo aprovado em 17/11/2026

Após concluir a P64, a Direção aprovou a continuidade do recorte funcional: consultar um lead fictício em homologação isolada, com solicitação iniciada por usuário autenticado no CRM, autoridade do próprio CRM e somente referência/etapa na projeção.

A P65 acrescenta o consumidor puro dessa resposta. Não autentica serviço, transporta cookies ou concede delegação. A obtenção da resposta continua no fluxo humano do CRM. A referência esperada vem do recurso solicitado, independentemente do corpo recebido.

Base Agents: P64 `e19fdab549b036f9dc1785f20adc96f52b256410`, [PR #470](https://github.com/rafhael100/imagine-ai-agents/pull/470), [CI 44208580955](https://github.com/rafhael100/imagine-ai-agents/actions/runs/44208580955). A implementação correspondente do CRM parte da candidata #647 `a2c7218f28aac58796387c939832ff1bfae3cba0`; sua prova HTTP fixa a SHA publicada deste consumidor.

## Contrato de sucesso

HTTP 200, JSON compacto e canônico, com esta ordem exata de propriedades:

```json
{"contract":"CRM_AIA_HOMOLOGATION_LEAD_READ_V1","operation":"crm.lead.read_minimal","status":"READ","scope":"SYNTHETIC_PRIVATE_HOMOLOGATION","projection":{"ref":"10000000-0000-4000-8000-000000000001","stage":"new"},"operational":false}
```

O exemplo usa uma referência fictícia. A referência retornada deve ser um UUID canônico em minúsculas, igual ao recurso esperado. As etapas aceitas são `new`, `qualified`, `proposal`, `won` e `lost`. O CRM seleciona somente `id` e `stage` de sua tabela privada, vinculada à empresa resolvida no backend; `id` é serializado como `ref`.

Todos os códigos diferentes de 200 são recusados, inclusive redirecionamento para login. O consumidor não segue redirecionamentos nem interpreta o corpo de erro como projeção. O contrato não exige JSON nas falhas: preserva os erros e a navegação de autenticação existentes do CRM. Recursos ausentes e de outra empresa compartilham a mesma resposta 404 do CRM.

O leitor limita o corpo a 1.024 bytes e recusa propriedades extras, tipos divergentes, referência diferente, estados desconhecidos e formatos antigos de mocks. A comparação com a recodificação canônica rejeita nomes JSON repetidos ou escapados e variações de bytes não admitidas pelo contrato. O valor aceito contém apenas duas propriedades imutáveis: `ref` e `stage`.

## Implementação e prova

- `src/Integration/Crm/MinimalLeadProjection.php`: validador sem banco ou cliente HTTP.
- `tools/check_p65_crm_minimal_read.php`: cinco etapas válidas, imutabilidade e entradas adversariais.
- `tools/p65-crm-minimal-read-probe.php`: corpo HTTP real via stdin, status e referência esperada via argumentos; retorna somente um marcador fixo, sem imprimir conteúdo ou credenciais.
- `.github/workflows/aia-p65-crm-minimal-read.yml`: lint e testes na SHA exata.

Uso da prova de interoperabilidade:

```bash
php tools/p65-crm-minimal-read-probe.php --status 200 --expected-ref 10000000-0000-4000-8000-000000000001
```

O corpo deve chegar por stdin sem uma quebra de linha adicional. Exit 0 significa formato e vínculo de recurso aceitos; exit 2 significa recusa. Esse resultado não comprova a autenticidade do transporte. A prova do CRM executa a autenticação real, captura a resposta original e passa apenas corpo/status/recurso ao consumidor em outro contêiner, sem rede ou credenciais.

## Limites preservados

Os contratos `SYNTHETIC_CRM_AIA_*`, `MockBridge`, a matriz histórica OP-01 e o manifesto CRM #57 permanecem intactos. A nova consulta humana não executa os casos históricos de emissor/audiência/nonce de uma delegação entre serviços. A [proposta CRM #57](https://github.com/rafhael100/imagine-crm/pull/57) continua independente desta evidência.

A consulta cria auditoria `lead.minimal_read`; não altera leads, tarefas ou automações. A política global de produção continua negando. Publicação desta branch e testes em CI não representam merge, implantação, homologação operacional ou acesso a cliente.

Revisão técnica por agentes concluída; resultados efetivos de execução são associados à SHA e à PR, sem antecipar sucesso na documentação.
