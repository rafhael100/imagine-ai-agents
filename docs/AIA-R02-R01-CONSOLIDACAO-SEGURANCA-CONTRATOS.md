# AIA-R02/R01 — Consolidação Transversal de Segurança e Contratos (S01 a S05) | Proposta documental v0.1

> **Situação:** proposta de revisão transversal; decisões **R02-R01-D01 a D08 ABERTAS** para avaliação do responsável.  
> **Data de referência:** 2026-11-06.  
> **Precedência verificada:** AIA-01 a AIA-12 aprovados funcionalmente; AIA-R01-D01 a D08 e AIA-R02/S01, S02, S03, S04 e S05 (D01–D10 de cada) ratificados **exclusivamente documentalmente** na `main`.  
> **Impedimentos:** R01-G01 a G11 permanecem TODOS ABERTOS; CORE/SEG/AUT e demais gates do CRM não se alteram. Sem código, SQL, ALLOW, testes reais, autenticação real, APIs, credenciais, contratados pagos, VPS ou deploy.

## 1. Finalidade da revisão

AIA-R02/R01 é uma revisão **transversal e documental**, não um módulo AIA-13. Verifica se as diretrizes dos cinco recortes têm responsabilidade, semântica e sequência de validação coerentes, e identifica evidências necessárias **antes da homologação técnica**. Aprovar este texto não torna o núcleo Imagine AI Agents pronto para programação.

**Fontes internas consolidadas:**
- S01 — Identidade, Autorização, Segregação e Fluxos entre Produtos.
- S02 — Contratos de Autenticação, Claims, Validade e Revogação.
- S03 — Matriz de Permissões e Escopos do CRM.
- S04 — Segregação, Retenção, Privacidade e Conformidade de Dados.
- S05 — Estados, Aprovações, Idempotência e Reconciliação.
- AIA-R01 — Gates G01–G11; AIA-04/05/06/07/08/09/10/11/12.

Todos os cinco recortes são **diretrizes preliminares**, não schemas ou controles existentes.

## 2. Resultado da revisão: coerências já estabelecidas

| Tema transversal | Regra coerente entre os recortes | Situação |
| --- | --- | --- |
| Autoridade | Cada produto autentica e autoriza seus recursos; AIA coordena IA, nunca substitui backend | Diretriz documental consolidável; não homologada tecnicamente |
| Isolamento multiempresa | Verificar produto, tenant, recurso, principal e finalidade; proibir equivalência automática de identidade entre produtos | Sem matriz física nem prova de isolamento |
| Identidade de serviço | Emissores/destinatários homologados, separação de ambientes, vigência, delegação e revogação | Protocolo/claims e mecanismo de revogação não definidos |
| Escopos CRM | Leitura de lead/histórico, sugestão de resumo/classificação e tarefa controlada são operações distintas | Papéis, escopos e APIs reais do CRM pendentes |
| Dados e derivados | Provedor, origem, finalidade, logs, memória, RAG e expurgo precisam de classificação e dono | Bases legais, prazos e contratos concretos pendentes |
| Aprovações | Aprovar proposta não confirma execução; ação exige parâmetros materiais e revalidação de direitos | Alçadas, identidade de aprovador e contratos técnicos pendentes |
| Idempotência | Retry equivale à mesma intenção, sem efeito duplicado; resultado indeterminado exige reconciliação | Armazenamento atômico e prova no produto pendentes |
| Custo e auditoria | Diferenciar tentativa, custo de modelo, crédito comercial e efeito confirmado | Contrato de conciliação e regras de cobrança pendentes |

A ausência de evidência implementada não é descrita como falha real do software: são **gaps de especificação e homologação**.

## 3. Pontos de tensão que precisam de contrato conjunto

### C01 — Token válido não é autorização de recurso

S02 pode comprovar serviço emissor, mas o direito sobre um lead depende do CRM (S01/S03). **Regra de precedência proposta:** autenticação de transporte/serviço → vinculação de tenant → permissão de recurso no CRM → política de agente/ferramenta → aprovação quando aplicável → validação final pelo CRM. Cada etapa pode negar; nenhuma amplia privilégios. Definir diferenciação entre conta de serviço e usuário delegado.

### C02 — Revogação no meio da execução

S02 prevê expiração e revogação; S05 prevê filas, checkpoints e reexecuções. Um estado inicial autorizado pode se tornar inválido antes do efeito. **Regra proposta:** revalidar direitos na fronteira de cada ferramenta sensível, inclusive após aprovação e retomada. Política de cache, prazos, falha do verificador e tratamento de efeitos anteriores exigem especificação técnica.

### C03 — Proposta IA vs persistência do CRM

S03 permite resumo/classificação como **sugestões**, enquanto S05 trata mutações separadas. **Regra proposta:** geração de texto não altera lead, histórico, tarefa ou score oficialmente. Gravação autorizada tem ferramenta/escopo próprio, validação do CRM e confirmação de efeito. Determinar no CRM se registrar resumo exige aprovação humana e sob quais alçadas; não inventar permissão funcional do CRM aqui.

### C04 — Registro de auditoria vs minimização LGPD

S05 requer evidência de aprovação, payload material e confirmação; S04 rejeita armazenamento indiscriminado de mensagens e dados pessoais. **Regra proposta:** evidências por referências e digest/versionamento, com acesso controlado a conteúdo quando legalmente necessário, retenção por classe e auditoria da auditoria. Verificar risco de dados sensíveis em hashes vulneráveis a inferência; não equiparar hash à anonimização. Políticas concretas aguardam aprovação técnica/jurídica.

### C05 — Fila/retry vs expiração de dados

S05 permite reconciliação e retentativa, mas S04 exige expurgo de derivados e prazo de retenção por finalidade. **Regra proposta:** tentativas após revogação/expiração não podem reconstruir dados apagados ou contornar retenções; estados indeterminados precisam de metadados mínimos para conciliação enquanto houver base legítima. Definir política de encerramento e tratamento de backups.

### C06 — Idempotência de efeito vs custo técnico e crédito comercial

Uma inferência repetida pode consumir tokens novamente; uma chamada duplicada de criação de tarefa não deve gerar segunda tarefa; callback duplicado não deve consumir crédito comercial duas vezes. **Regra proposta:** separar `attempt_ref`, `intent_ref`, `effect_ref` e `usage_ref` conceituais, com dono e deduplicação por tipo. Não escolher identificadores ou cálculos definitivos neste documento.

### C07 — Aprovação humana vs autorização final

Uma aprovação AIA-08 pode ser autêntica, mas não autorizar acesso quando o usuário perdeu papel/contrato, quando a ação mudou ou quando o backend recusou. **Regra proposta:** aprovação vinculada a parâmetros, tenant, alçada e validade; não substitui direito do recurso no momento da execução. Em ação financeira ou externa, o produto aplicará regras próprias mais restritivas.

### C08 — Identidade entre produtos e serviços compartilhados

Portal, CRM, Performance e Universidade mantêm autoridades independentes; mesmo e-mail/CNPJ não comprova equivalência. **Regra proposta:** contrato explícito de troca de dados e de mapeamento de identidades, com finalidade, autorização, auditoria e revogação. n8n Imagine não pode ser ponte privilegiada para contornar direitos e só pode ser utilizado conforme licença adequada.

## 4. Cadeia documental de autorização e efeito

```text
Produto autentica solicitante e seu tenant/recurso
       ↓
Contrato de confiança serviço→AIA é verificado (S01/S02)
       ↓
AIA verifica agente, plano/cota, ferramenta e finalidade
       ↓
Produto reautoriza leitura ou campos autorizados (S03)
       ↓
IA produz resultado, proposta ou intenção (sem efeito implícito)
       ↓
[Risco exige aprovação?] → humano autorizado aprova ação exata
       ↓
Revalidar direitos, alçada, prazo, plano e contexto (S02/S03/S05)
       ↓
Ferramenta homologada envia mesma intenção/idempotency key
       ↓
Backend dono verifica, executa ou nega, confirma ou informa indeterminação
       ↓
AIA registra tentativa, aprovação, efeito, consumo e correlação minimizados
       ↓
[Indeterminado] → conciliar no backend antes de qualquer nova tentativa
```

O fluxo é **conceitual**. Não autoriza endpoints, contratos de mensagem reais, fila ou comportamento de produção.

## 5. Matriz de gates e evidências pendentes

| Gate | Recortes que contribuem | Faltam para homologar |
| --- | --- | --- |
| **R01-G01** Identidade/tenant | S01, S02, S04 | Protocolo, mapeamentos reais, revogação, trust boundaries e provas |
| **R01-G02** Escopo/autorização | S01, S02, S03, S05 | Matriz oficial do CRM, validação final por recurso/campo e testes negativos |
| **R01-G03** Execução/idempotência | S05, S03 | Estados finais, persistência durável, concorrência, reconciliação e testes |
| **R01-G04** Aprovação/alçadas | S02, S03, S05 | Papéis aprovadores, conflito de interesses, parâmetros e vencimento verificáveis |
| **R01-G05** LGPD/retenção | S04, S01, S03 | Inventário real de fluxo, base legal, prazos, contratos e expurgo |
| **R01-G06** Consumo/custos | S03, S05, AIA-10 | Unidades, política de créditos, deduplicação, preços e conciliação |
| **R01-G07** Laboratório/aceite | S01–S05, AIA-09 | Suites sintéticas, gates eliminatórios, critérios e responsáveis |
| **R01-G08** Contratos CRM | S01–S03, S05, AIA-12 | Schemas, versionamento, callbacks, idempotência e aprovação no CRM |
| **R01-G09** Licenciamento | AIA-R01, AIA-07/12 | Avaliação jurídica/comercial atual de n8n e terceiros, sem suposição de direito |
| **R01-G10** Arquitetura | Todos | Tecnologia, isolamento, criptografia, filas, bancos, segredos, telemetria e operação |
| **R01-G11** MVP/orçamento | S03, S05, AIA-R01 | Recorte aceito, custos de terceiros, critérios de go/no-go e autorização específica |

**Nenhum gate recebe status concluído por esta revisão**, mesmo quando já existe descrição funcional detalhada. Todas as provas e homologações serão posteriores.

## 6. Matriz de critérios negativos propostos

| Cenário sintético | Comportamento obrigatório antes do futuro piloto |
| --- | --- |
| Token CRM válido solicita lead de outro tenant | Bloquear no CRM e AIA; não revelar dados |
| Serviço AIA tem credencial válida, mas ferramenta proibida | Negar ferramenta, não usar fallback privilegiado |
| Aprovador perdeu alçada após decisão | Revalidar e bloquear quando exigido |
| Pedido de tarefa repete após timeout com efeito anterior confirmado | Reconciliar e devolver referência existente |
| Mesma chave para parâmetros diferentes | Conflito de intenção, sem efeito automático |
| Falha na verificação de revogação | Bloquear ou aguardar, nunca autorização por silêncio |
| Callback chega duplicado ou fora de ordem | Deduplicar, preservar monotonicidade de estado e conciliar |
| Dados apagados são recuperados em cache ou backup | Impedir uso indevido e reconciliar derivados |
| Execução do tenant A aparece em log/painel do B | Bloquear e tratar como falha grave |
| Prompts privados aparecem em dataset compartilhado | Reprovar sem contrato/base legal adequada |
| Custo técnico de retry gera cobrança comercial duplicada | Distinguir gasto real, crédito comercial e conciliação |
| Dados Portal reutilizam direito do CRM por mesmo CNPJ | Negar vínculo automático |

Casos são **propostos**; não há execução de testes reais nem afirmação de conformidade atingida.

## 7. Sequência recomendada de fechamento documental (não de gates)

1. **R02/R02 — Contratos de confiança, escopos e fluxos CRM:** detalhar emissores, delegação, recurso, erros e matriz de responsabilidades com critérios de negação, preservando gates.
2. **R02/R03 — Máquina de estados, alçadas e efeitos:** detalhar esquema lógico, concorrência, idempotência, confirmação e reconciliação.
3. **R02/R04 — Dados, auditoria e privacidade:** identificar fluxos, derivados, bases legais, prazos/terceiros e política de logs.
4. **R02/R05 — Homologação de segurança e evidências:** suíte de cenários negativos, responsáveis, critérios de fechamento e limites da fase.
5. **R03 — Arquitetura técnica:** somente depois de delimitar contratos; selecionar tecnologias, integrações e infraestrutura em análise própria.
6. **R04/R05 — Licenciamento/custos/MVP:** manter decisão expressa de orçamento, fornecedor e autorização de implementação.

Títulos futuros são **sugestões de trabalho**, não decisões aprovadas; a sequência exata ainda é objeto de avaliação.

## 8. Matriz R02-R01-D01 a D08 — decisões propostas

| ID | Tema | Alternativa A — recomendada | Alternativa B |
| --- | --- | --- | --- |
| R02-R01-D01 | Estado consolidado | Reconhecer S01–S05 como diretrizes documentais ratificadas, não homologação técnica | Declarar contratos prontos e seguros para produção |
| R02-R01-D02 | Autoridade e identidade | Produto autentica/autoriza seus recursos e AIA verifica contexto; nenhuma confiança por nome/ID livre | Núcleo assume papel superadmin dos produtos |
| R02-R01-D03 | Ciclo da ação | Separar pedido, proposta, aprovação, tentativa, efeito confirmado e estado indeterminado | Considerar resposta da IA/aprovação como efeito confirmado |
| R02-R01-D04 | Segurança temporal | Revalidar identidade, tenant, escopo e alçada antes do efeito e após retomada; falha segura | Validade inicial autoriza ações futuras indefinidamente |
| R02-R01-D05 | Dados e evidências | Minimizar conteúdo e preservar rastreabilidade de autorização, efeito, memória, retenção e expurgo | Armazenar indiscriminadamente prompts, leads e logs |
| R02-R01-D06 | Custos e duplicidade | Diferenciar tentativas, consumo técnico, créditos e efeitos; conciliar antes de repetir ação | Retry e callback duplicados geram cobrança/efeitos adicionais |
| R02-R01-D07 | Gates e homologação | Manter R01-G01 a G11 e CORE/SEG/AUT abertos até contratos técnicos, provas e aprovação específica | Fechar gates automaticamente por revisão documental |
| R02-R01-D08 | Sequência controlada | Detalhar contratos críticos, dados e homologação antes de arquitetura/MVP e implementação autorizada | Iniciar programação ou deploy por aprovação desta revisão |

**R02-R01-D01 a D08: ABERTAS**, aguardando aprovação **documental preliminar** do responsável. Nenhum gate foi encerrado; sem homologação técnica, legal ou comercial e sem autorização de desenvolvimento.

## 9. Critérios de aceite e restrições desta proposta

- [ ] Avaliar/ratificar ou corrigir R02-R01-D01 a D08.
- [ ] Revisar conflitos C01–C08 com responsáveis técnicos e donos dos produtos antes de fechar contratos.
- [ ] Harmonizar a matriz de escopos CRM com a documentação oficial de papéis e autorização do próprio CRM, sem tocar seus repositórios.
- [ ] Definir responsabilidades e evidências de autenticação, revalidação, autorização e aprovação com exemplos sintéticos.
- [ ] Detalhar dados, memórias, índices, logs, backups, retenção e LGPD com responsáveis por cada fluxo.
- [ ] Definir critério técnico de idempotência, confirmação, deduplicação e reconciliação antes de ações externas.
- [ ] Conciliar custos IA/créditos com os contratos próprios de cada produto.
- [ ] Elaborar critérios de laboratório e governança de homologação, mantendo todos os gates abertos.
- [ ] Obter autorizações separadas para código, testes reais, despesas, infraestrutura e deploy.

**Proteções imutáveis:** não alterar `main`, PRs, CORE/SEG/AUT ou implementação de CRM/Portal/Performance AI/Universidade; não criar ALLOW, SQL, APIs, credenciais ou contas, não instalar n8n/servidores/Cloudflare, não contratar modelos ou APIs e não executar testes funcionais reais.

**Próxima recomendação:** após aprovação desta revisão, especificar `R02/R02` — contratos detalhados de confiança e escopos para o piloto CRM — sem supor fechamento dos gates.
