# AIA-R02/R06 — Síntese de Segurança e Matriz de Evidências para Gates Técnicos | Proposta documental v0.1

> **Estado:** diretrizes **R02-R06-D01 a D08 APROVADAS DOCUMENTALMENTE em 2026-11-06** pelo responsável, exclusivamente preliminares. Os gates R01-G01 a G11 e CORE/SEG/AUT do CRM continuam abertos.
> **Data de referência:** 2026-11-06. **Repositório:** `rafhael100/imagine-ai-agents`.
> **Precedência:** AIA-01 a AIA-12 aprovados **funcionalmente**; AIA-R01, AIA-R02/S01 a S05 e AIA-R02/R01 a R05 aprovados **documentalmente**, com registros na `main`.
> **Condição vinculante:** R01-G01 a G11 TODOS ABERTOS; CORE/SEG/AUT e demais bloqueios próprios do CRM preservados. A aprovação desta proposta não autoriza testes reais, código, SQL, ALLOW, credenciais, contratação, infraestrutura, alterações em outros repositórios ou deploy.

## 1. Objetivo e alcance

Encerrar **somente a revisão de coerência documental** da série R02 e apresentar um inventário verificável do que ainda será necessário para **homologação técnica futura**. Este documento não homologa contratos nem comprova operação de controles, mesmo que um módulo ou diretriz tenha sido aprovado.

Os artefatos de entrada são:
- **S01:** Identidade, Autorização, Segregação e Fluxos entre Produtos.
- **S02:** Autenticação, Claims, Validade e Revogação.
- **S03:** Matriz de Permissões e Escopos do CRM.
- **S04:** Segregação, Retenção, Privacidade e Conformidade.
- **S05:** Estados, Aprovações, Idempotência e Reconciliação.
- **R01:** Consolidação Transversal de Segurança e Contratos.
- **R02:** Contratos Detalhados de Confiança e Escopos CRM.
- **R03:** Máquina de Estados, Alçadas, Efeitos e Reconciliação.
- **R04:** Dados, Auditoria, Retenção e Privacidade nos Fluxos.
- **R05:** Critérios Documentais de Homologação, Casos Sintéticos e Evidências.

## 2. Resultado consolidado

| Eixo | Diretriz documental consolidada | Falta para homologar |
| --- | --- | --- |
| Identidade e confiança | Emissor/ambiente/tenant/principal/delegação verificáveis, sem superadmin universal | Escolher tecnologia, definir schemas e certificados/segredos, mapeamentos e revogação com prova |
| Direitos sobre recurso | CRM decide acesso por pessoa/serviço, empresa, lead, atendimento, campo e ferramenta | Matriz real de papéis/ACL do CRM, contratos de API e cenários negativos |
| Sugestão versus efeito | Resumo/classificação são sugestões; alterações requerem ferramenta e autorização próprias | Contratos de gravação/tarefas no CRM, alçadas e aceite do proprietário |
| Execução segura | Separar run/step/intenção/aprovação/tentativa/efeito/consumo; conciliar timeout | Persistência, concorrência, transições, deduplicação, oráculos e testes |
| Dados privados | Mínimo necessário e segregação de memórias, índices, filas, logs e backups | Inventário real de fluxo, direitos, retenção, expurgo e contratos externos |
| Auditoria e gastos | Rastrear prova de autorização e efeito, sem log indiscriminado; separar custos e créditos | Política de evidências, conciliação contábil, acesso/retenção e testes |
| Aceite de segurança | Suítes sintéticas e critérios eliminatórios descritos | Responsáveis designados, critérios técnicos finalizados, ambiente e testes autorizados |
| Produtos e dependências | CRM piloto primeiro; demais produtos mantêm autoridade e contratos separados | Homologação bilateral CRM/AIA, outros produtos apenas em fases específicas |

**Avaliação documental:** não se identificou contradição de princípio que exija reverter as aprovações anteriores; existem **tensões de implementação em aberto** e lacunas de definição técnica/jurídica. A revisão documental não comprova compatibilidade real dos contratos.

## 3. Matriz de evidências — 11 gates ainda bloqueantes

| Gate | Evidência requerida antes de eventual encerramento | Dono da decisão/evidência, a designar | Situação |
| --- | --- | --- | --- |
| **R01-G01** Identidade e tenant | Trust model, vínculo explícito CRM/AIA, emissores, revogação, proteção contra replay e testes negativos | Segurança AIA + responsável CRM | **ABERTO** |
| **R01-G02** Escopos e autorização | ACL CRM oficial, acesso por recurso/campo, dupla verificação e testes de negação | Produto CRM + segurança | **ABERTO** |
| **R01-G03** Execução/idempotência | Estados, efeito confirmado, intenção estável, concorrência, timeout e reconciliador | Engenharia AIA + CRM | **ABERTO** |
| **R01-G04** Aprovação/alçadas | Aprovador elegível, decisão vinculada a parâmetros, expiração, revogação e segregação de funções | Dono do produto + segurança | **ABERTO** |
| **R01-G05** LGPD/retenção | Inventário por fluxo, base legal, terceiros, classes, derivados, descarte e backups | Privacidade/jurídico + donos dos dados | **ABERTO** |
| **R01-G06** Custos/limites | Consumo por tentativa, créditos CRM, cotas, reservas, deduplicação e conciliação | Produto CRM + financeiro + AIA | **ABERTO** |
| **R01-G07** Laboratório | Critérios e oráculos homologados, ambiente isolado, conjunto sintético, evidências reproduzíveis | QA + segurança + responsáveis de produto | **ABERTO** |
| **R01-G08** Contratos CRM | APIs/esquemas/versionamento/erros, confirmações, idempotência e aceite bilateral | CRM + AIA + integrações | **ABERTO** |
| **R01-G09** Licenças/terceiros | Avaliação vigente do n8n e demais serviços/modelos, custos/contratos e limites comerciais | Jurídico/comercial/técnico | **ABERTO** |
| **R01-G10** Arquitetura/operação | Stack, topologia, segregação, filas, segredos, observabilidade, backups e RTO/RPO | Arquitetura + segurança + operação | **ABERTO** |
| **R01-G11** MVP/planejamento | Escopo mínimo, critérios go/no-go, orçamento, responsáveis, rollback e autorização de implementação | Direção de Produto e responsáveis técnicos | **ABERTO** |

Os nomes de papéis indicam **funções a designar** e não comprovam que pessoas específicas ou revisores independentes tenham sido nomeados. Aprovação documental da série R02 não muda nenhuma célula desta tabela para “concluído”.

## 4. Registro de tensões, donos e encaminhamentos

| Tensão | Requisito de conciliação | Gate atingido |
| --- | --- | --- |
| **C01** Identidade do serviço vs direito sobre lead | CRM deve revalidar recurso/ACL ao autorizar leitura ou mutação; AIA nunca amplia direito | G01/G02/G08 |
| **C02** Revogação durante fila/checkpoint | Autorização atual antes de efeito e retomada; falha segura | G01/G03/G04 |
| **C03** Resumo sugerido vs gravado | Gravação é operação distinta e requer confirmação CRM | G02/G03/G08 |
| **C04** Auditoria vs minimização | Prova material referencial, integridade, acesso restrito e retenção legítima | G05/G07/G10 |
| **C05** Retry vs expurgo/retenção | Reconciliação sem reconstruir dados eliminados; custódia mínima do resultado | G03/G05 |
| **C06** Retry e custo comercial | Separar tentativa técnica, uso de modelo, crédito CRM e efeito idempotente | G03/G06 |
| **C07** Aprovação vs direito atual | Aprovação não substitui verificação final de alçada/permissão | G02/G04 |
| **C08** Vínculo CRM/Portal/n8n | Não presumir SSO nem equivalência por e-mail/CNPJ; contrato interproduto específico | G01/G08/G09 |

Nenhuma tensão C01 a C08 é marcada resolvida tecnicamente. A cadeia de decisão permanece: **produto autentica e autoriza → AIA valida capacidades e limites → produto revalida e confirma efeito**.

## 5. Evidências e estados do processo de aceite

Para cada caso futuro do R02/R05, manter:
- ID de caso e vínculo com gate, risco, responsável, condição de entrada e oráculo de aceite.
- Build/commit, contratos, agente, ferramenta e políticas versionados.
- Ambiente e dataset sintético homologados, sem credenciais ou dados pessoais reais.
- Resultado observado apenas quando houver **execução expressamente autorizada**; evidência auditável e revisão.
- Desvios, remediações, reteste e decisão formal individual de gate.
- Proteção/retenção da prova sem copiar prompts completos e informações privadas desnecessárias.

**Estado verificado nesta data:** requisitos/casos documentados; **0 testes executados nesta série de documentação**, **0 gates homologados por esta série**. Não inferir aprovação técnica por ausência de achados ou merge de PR documental.

## 6. Entrada sugerida na fase AIA-R03 — não é autorização de desenvolvimento

A próxima fase pode iniciar **planejamento de arquitetura técnica** e comparação de alternativas, preservando bloqueios. Recortes candidatos:

1. **R03/S01 — Topologia e Fronteiras de Serviços:** núcleo central IA, produtos separados, ambiente de desenvolvimento/homologação, tenancy e tráfego; candidatos sem contratar/provisionar.
2. **R03/S02 — Persistência, Filas e Recuperação:** estado durável, idempotência, concorrência, reconciliação, backup e observabilidade.
3. **R03/S03 — Identidade, Segredos e Conectividade Técnica:** estudar mecanismos compatíveis com R02 sem emitir credenciais.
4. **R03/S04 — Custos, Modelos, Provedores e Licenças:** estimativas e conformidade comercial de n8n/modelos, sem comprometer orçamento.
5. **R03/S05 — Matriz de Implantação e Rollback:** plano experimental faseado, dependências dos gates e go/no-go; sem execução.

As opções de Leaseweb/VPS, Docker, Laravel/PostgreSQL, serviços de filas ou ferramentas externas mencionadas no ecossistema são **insumos candidatos**, não stack AIA homologada. O desenvolvimento dos agentes e infraestrutura exigem autorização **separada por etapa**; autorizações existentes em CRM/Portal não se transferem automaticamente.

## 7. Limites do piloto para análise futura

O piloto proposto continua restrito ao fluxo **CRM → AIA → CRM**: leitura de dados de lead/histórico explicitamente autorizados, resumo e classificação **sugeridos**, criação de tarefa somente sob ferramenta, permissão, alçada e idempotência próprias. Status e consumo são consultados sob ACL.

Não autoriza: mudança genérica de registros, importações, exclusões, redistribuição de leads, envio de mensagens, campanhas, alteração de orçamento, contratação/pagamento, integração de todos os produtos, acesso direto SQL ou automações de clientes dentro do n8n central.

## 8. Diretrizes R02-R06-D01 a D08 — aprovadas documentalmente

| ID | Tema | Alternativa A — recomendada | Alternativa B |
| --- | --- | --- | --- |
| R02-R06-D01 | Marco documental | Reconhecer S01–S05/R01–R05 como diretrizes documentais consolidadas, não contratos técnicos homologados | Declarar projeto pronto para produção |
| R02-R06-D02 | Gates | Manter R01-G01 a G11 abertos e exigir evidência e decisão específica para cada fechamento | Fechar todos pelo aceite da síntese |
| R02-R06-D03 | CRM | Conservar autoridade de ACL, contratos, planos, dados e efeitos do CRM; CORE/SEG/AUT intocados | AIA substitui direitos e gates do CRM |
| R02-R06-D04 | Evidências | Matriz por gate, dono a designar, caso sintético, resultado futuro e trilha auditável | Usar aprovação genérica sem prova técnica |
| R02-R06-D05 | Riscos críticos | Falhas de tenant isolation, autorização, revogação e efeitos indevidos como bloqueantes | Aceitar vazamento/bypass em prol da velocidade |
| R02-R06-D06 | Dados e custos | Preservar retenção por finalidade, minimização, separação consumo técnico/crédito e contratos de terceiros pendentes | Centralizar dados/créditos irrestritamente |
| R02-R06-D07 | Próxima fase | Autorizar somente **planejamento documental da arquitetura AIA-R03**, com alternativas avaliadas sem provisionamento | Selecionar infraestrutura e implantar imediatamente |
| R02-R06-D08 | Liberação técnica | Código, testes reais, credenciais, compras e deploy requerem autorizações futuras expressas e específicas | Considerar este documento uma ordem de implementação |

**R02-R06-D01 a D08: APROVADAS DOCUMENTALMENTE em 2026-11-06 pelo responsável.** Aprovação exclusivamente **documental preliminar**, sem fechar R01-G01 a G11 ou CORE/SEG/AUT, sem executar testes, emitir credenciais, escolher tecnologia, programar ou implantar.

## 9. Pendências para avaliação e sequência

- [x] R02-R06-D01 a D08 aprovadas documentalmente pelo responsável em 2026-11-06.
- [ ] Designar formalmente funções técnicas e revisores antes da homologação efetiva.
- [ ] Confirmar matriz de permissões e recursos com documentação oficial CRM e responsáveis, preservando seus gates.
- [ ] Harmonizar contratos de dados, efeitos, alçadas, custos e APIs com os produtos.
- [ ] Aprovar planejamento técnico R03 em nova decisão documental, sem contratar ou instalar.
- [ ] Analisar n8n/licenças/fornecedores/planos e orçamento, mantendo G09 aberto.
- [ ] Homologar em separado critérios, evidências e controles de cada R01-G01 a G11.
- [ ] Requerer autorização expressa por etapa para programação, testes reais, SQL, ALLOW, credenciais, VPS e deploy.

**Imutáveis:** nenhum gate R01-G01 a G11 fechado; nenhuma modificação de `main`, PRs ou bloqueios do CRM/Portal/Performance/Universidade; nenhuma instalação em Leaseweb/Cloudflare/n8n, uso de conta externa, API paga, código ou teste funcional.
