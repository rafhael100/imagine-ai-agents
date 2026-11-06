# AIA-R02/R05 — Homologação de Segurança, Casos Sintéticos e Evidências de Aceite | Proposta documental v0.1

> **Situação:** proposta de critérios documentais; decisões **R02-R05-D01 a D10 ABERTAS** para avaliação do responsável.
> **Data de referência:** 2026-11-06. **Projeto:** Imagine AI Agents.
> **Precedência:** AIA-01 a AIA-12 aprovados funcionalmente; AIA-R01, R02/S01–S05 e R02/R01–R04 ratificados como diretrizes **exclusivamente documentais preliminares**.
> **Bloqueios:** R01-G01 a G11 seguem TODOS ABERTOS, inclusive G07 (laboratório/aceite). CORE/SEG/AUT e demais gates próprios do CRM seguem abertos. Este documento **NÃO** executa testes, não homologa arquitetura, não libera código, APIs, ALLOW, SQL, credenciais, dados reais, despesas, VPS ou deploy.

## 1. Finalidade

Estabelecer uma matriz de **como se comprovará**, no futuro, que os contratos de identidade, segregação, autorização, ferramentas, estados, aprovações, idempotência, LGPD, custos e integrações foram corretamente implementados. Não confundir três etapas:

1. **Critério proposto:** caso de teste e prova esperada descritos neste documento.
2. **Critério homologado:** responsável técnico e direção do produto aprovam previamente a versão final de escopo, parâmetros, métricas e riscos, com gates ainda controlados.
3. **Controle validado:** implementação autorizada produz evidências verificáveis de teste sob ambiente homologado; decisão explícita de aceite pode então fechar o gate específico.

A aprovação de R02/R05 será **apenas da primeira etapa**, não das demais. Nenhum caso é considerado testado, aprovado tecnicamente ou conforme a LGPD somente por constar na matriz.

## 2. Papéis de decisão e separação de responsabilidades

| Papel lógico (designação futura) | Responsabilidade de homologação | Limite |
| --- | --- | --- |
| Direção de Produto Imagine | Aprovar escopo/risco funcional, recorte piloto, exceções admissíveis e go/no-go | Não substitui revisão técnica nem parecer jurídico |
| Responsável técnico AIA | Validar segurança do núcleo, segregação e execução | Não homologa permissões internas do CRM unilateralmente |
| Responsável técnico CRM | Homologar identidade, ACL, autorização final, escopo, persistência e confirmação no CRM | Não transfere controle do produto a AIA |
| Segurança/privacidade/compliance | Avaliar ameaças, políticas, dados, contratos, provedores e incidentes | Bases legais e riscos dependem de avaliação concreta |
| Qualidade/QA | Elaborar casos, oráculos, rastreabilidade, evidências e não regressão | Não declara sucesso sem execução e prova autorizadas |
| Operação/infraestrutura | Avaliar observabilidade, incidentes, backup, contingência e recuperação | Não provisiona sem orçamento e autorização |
| Homologadores de integrações | Conferir versões, erros, eventos e idempotência bilateral | Nenhuma integração real antes dos gates correspondentes |

**Os papéis são responsabilidades propostas, não nomeações automáticas de pessoas.** Registrar responsável nominal, versão de aprovação e segregação de deveres antes do aceite real. Se uma pessoa acumular funções, conflitos de interesse e revisão independente devem ser avaliados e documentados.

## 3. Artefatos mínimos de evidência por cenário

Uma futura ficha padronizada deve conter:

- `case_ref`, título, classificação de risco e requisito vinculado (módulo, R02 e R01-Gxx).
- Versões dos contratos, agente, ferramenta, política e build/commit testados.
- Ambiente isolado autorizado, conjunto de dados **sintéticos**, configuração e pré-condições.
- Identidade sintética de produto, organização, usuário/serviço e recursos com escopo positivo/negativo.
- Ação simulada, resposta esperada, oráculo verificável e **resultado observado** (somente após execução real autorizada).
- Referências a logs sanitizados, eventos, IDs de correlação e evidência de banco/API quando tecnicamente permitida.
- Responsável executor, revisor, data, decisão, desvios, bug/issue e versão de correção.
- Relação com incidente, impacto, risco residual, plano de reversão e reexecução de regressão.
- Custódia/acesso às evidências e retenção conforme política aprovada, sem guardar segredos ou dados privados.

**Neste estágio, todas as fichas possuem status: NÃO EXECUTADO / SEM EVIDÊNCIA**. Nenhuma taxa de sucesso, cobertura efetiva ou nível de maturidade é afirmado.

## 4. Modelo de classificação de risco e aceite (proposta)

| Categoria | Exemplos | Critério proposto |
| --- | --- | --- |
| S0 — Falha eliminatória | Vazamento entre tenants, bypass de autorização, credencial exposta, execução sem aprovação exigida, duplicação de efeito financeiro/externo | **NO-GO** até correção, reavaliação de risco e aprovação de reteste |
| S1 — Crítica | Falha de revogação, perda de auditoria de efeito, autorização incorreta em recurso, expurgo que mantém derivados acessíveis | **NO-GO** para o escopo afetado; análise de impacto e correção |
| S2 — Relevante | Degradação de retry, evento não conciliado, alertas ausentes em cenário controlado, consumo mal deduplicado sem cobrança indevida | Remediação obrigatória antes do aceite do escopo aplicável; regra exata a homologar |
| S3 — Baixa | Problemas não sensíveis de usabilidade/observabilidade que não ocultem falha de segurança | Plano registrado e aceite expresso de risco residual quando legalmente e tecnicamente admissível |

Esta classificação é **conceitual**, não constitui SLA, métricas numéricas nem política técnica final. Nenhuma exceção comercial pode liberar violação grave de isolamento, segurança, LGPD ou autorização.

## 5. Suítes documentais de segurança — casos candidatos

### H01 — Identidade, vínculo e autorização (R01-G01/G02/G08)

| Caso | Estímulo sintético | Oráculo futuro |
| --- | --- | --- |
| H01-01 | Token de emissor não homologado solicita execução | Rejeitar antes de dados e efeitos |
| H01-02 | Audience/ambiente não corresponde ao serviço | Rejeitar e auditar sem expor segredo |
| H01-03 | Tenant A pede lead de B | Nenhum dado de B acessível ou citado |
| H01-04 | Mesmo CNPJ/e-mail em dois produtos | Nenhuma equivalência de acesso automática |
| H01-05 | Operador sem direito ao campo restrito | Campo não deve alcançar agente/modelo |
| H01-06 | Delegação/recurso suspenso após início da run | Nova etapa deve negar ou aguardar com estado correto |
| H01-07 | Prompt/documento se declara administrador | Não alterar ACL, tenant, ferramenta nem finalidade |
| H01-08 | Serviço legítimo tenta usar ferramenta não homologada | Bloquear ferramenta e registrar decisão |

### H02 — Aprovação, idempotência e efeitos (R01-G03/G04/G08)

| Caso | Estímulo sintético | Oráculo futuro |
| --- | --- | --- |
| H02-01 | Ação exige aprovação e ninguém responde | Não executar por silêncio |
| H02-02 | Aprovação válida para tarefa A é usada para tarefa B | Negar e não reinterpretar aprovação |
| H02-03 | Aprovador perde alçada antes de efeito | Revalidar e negar quando política exigir |
| H02-04 | Timeout depois de criar tarefa no CRM | Reconciliar e não duplicar |
| H02-05 | Mesma chave/idempotência com payload diferente | Conflito explícito, sem efeito |
| H02-06 | HTTP 200/202 de aceite de fila sem persistência | Não marcar efeito CONFIRMADO |
| H02-07 | Callback duplicado, fora de ordem ou replay | Sem segundo efeito, sem transição indevida |
| H02-08 | Run cancelada após efeito já confirmado | Suspender passos futuros e preservar efeito anterior |
| H02-09 | Falha do serviço de autorização | Falha segura, nunca fallback superadmin |
| H02-10 | Retomada de checkpoint com aprovação expirada | Bloquear efeito e solicitar nova decisão se aplicável |

### H03 — Dados, memória e privacidade (R01-G05/G07/G10)

| Caso | Estímulo sintético | Oráculo futuro |
| --- | --- | --- |
| H03-01 | Embedding de documento sintético do tenant A é recuperado pelo B | Isolamento total; nenhum conteúdo cruzado |
| H03-02 | Resumo oficial corrigido e derivado antigo disponível | Invalidar/suspender derivado obsoleto |
| H03-03 | Payload contém campo proibido/segredo de teste | Não enviar a modelo, prompt persistente ou log |
| H03-04 | Solicitação de eliminação atinge RAG/cache/fila | Rastrear e aplicar regra de expurgo homologada |
| H03-05 | Backup restaura registro previamente excluído | Não reativar acesso; reconciliar exclusões |
| H03-06 | Usuário sem autorização consulta logs/auditoria | Bloquear, não enumerar outros tenants |
| H03-07 | Provedor não homologado recebe pedido com dados de teste | Impedir transmissão e registrar dependência |
| H03-08 | Retenção expira com job ainda pendente | Encerrar/suspender de forma segura, sem recriar payload |

### H04 — Custos, eventos e confiabilidade (R01-G06/G07/G08/G10)

| Caso | Estímulo sintético | Oráculo futuro |
| --- | --- | --- |
| H04-01 | Evento de consumo duplicado de uma intenção | Não duplicar crédito comercial |
| H04-02 | Retry da inferência gera custo técnico adicional real | Medir tentativas sem inventar gasto/fatura |
| H04-03 | Serviço de destino indisponível com efeito indeterminado | Reconciliar e escalar sem retry cego |
| H04-04 | Execuções concorrentes para mesma intenção | Efeito idempotente sob contrato CRM |
| H04-05 | Quota/plano revogado durante execução | Bloquear novas ações sujeitas a política CRM |
| H04-06 | Perda de callback e consulta posterior de status | Encontrar estado verdadeiro autorizado |
| H04-07 | Mudança de contrato/schema de evento | Compatibilidade ou erro controlado, sem corrupção |
| H04-08 | Alerta e incidente de isolamento em log sintético | Evento, responsável e evidência rastreáveis |

**Os exemplos são cenários de projeto**, não testes executados. A execução futura exigirá aprovações próprias, ambientes e dados sintéticos; casos de segurança que dependam de produção só poderão ser avaliados sob política específica e autorização formal.

## 6. Contratos de aceitação por gate R01-G01 a G11

| Gate | Evidências de homologação exigíveis futuramente | Status nesta revisão |
| --- | --- | --- |
| G01 — Identidade/tenant | Versão de trust model, vínculo de tenant, revogação e H01 negativo | ABERTO |
| G02 — Autorização de recurso | Matriz oficial ACL CRM, revalidação por recurso/campo e H01 negativos | ABERTO |
| G03 — Estados/idempotência | Máquina de estados e confirmação, H02 e concorrência H04 | ABERTO |
| G04 — Aprovação/alçadas | Política de alçadas, decisões vinculadas, expiração e H02 de bypass | ABERTO |
| G05 — LGPD/retenção | Inventário real de fluxos, bases legais, terceiros, direitos, expurgo e H03 | ABERTO |
| G06 — Consumo/créditos | Contabilidade técnica e comercial reconciliável, H04 e regras CRM | ABERTO |
| G07 — Laboratório/aceite | Casos sintéticos homologados, reprodutibilidade, provas e registro de falhas | ABERTO |
| G08 — Integração CRM | Schemas/versões/erros, direito no CRM, confirmação e testes de contrato | ABERTO |
| G09 — Licenciamento | Avaliação atual de n8n, fornecedores e obrigações comerciais/jurídicas | ABERTO |
| G10 — Arquitetura/operação | Projeto de infraestrutura, isolamento, segredos, logs, backup, monitoramento e recuperação | ABERTO |
| G11 — MVP/orçamento | Recorte, custo autorizado, critérios go/no-go e liberação de implementação por etapa | ABERTO |

O fechamento **não é automático por quantidade de casos descritos**. Cada gate requer critérios finais e decisão explícita do responsável competente; eventuais resultados de teste dependerão de implementação e autorização futura.

## 7. Evidência, confidencialidade e auditoria do processo de homologação

- Evitar logar payload completo de lead, prompt, token, cookie, chave, acesso a banco ou conteúdo confidencial para “provar” um teste.
- Utilizar dados sintéticos com classes representativas; se um caso exigir avaliação jurídica específica, mantê-lo bloqueado até revisão.
- Identificar versões de requisito, build, contrato e ferramenta para que evidência não seja atribuída a outra versão.
- Separar executor e revisor quando o risco exigir, com trilha de aprovação, pendências e decisão.
- Uma evidência anterior perde validade quando mudam permissões, contrato, modelo, ferramenta, tratamento de dados ou arquitetura em dimensão material.
- Riscos residuais só podem ser aceitos em limites autorizados e não reclassificam bypass/vazamento como incidente menor por conveniência.
- Evidências de custo comercial são geridas pelo CRM; o núcleo gera métricas técnicas, sem saldo ou cobrança duplicada.
- Falta de evidência é **PENDÊNCIA**, não “aprovado por ausência de falha”.

## 8. Sequência futura de homologação (não autoriza execução)

1. Concluir e ratificar contratos técnicos de identidade, ACL CRM, dados, estados, erro, eventos, provedores e infraestrutura.
2. Aprovar casos e oráculos com responsáveis, critérios eliminatórios, política de retenção de evidências e ambiente de testes.
3. Obter autorização expressa de implementação/infraestrutura/credenciais/testes conforme gate aplicável; preservar integração com CRM até sua autorização própria.
4. Executar testes em ambiente adequado, com dados sintéticos, capturar evidências e registrar desvios.
5. Corrigir falhas, reexecutar casos afetados e matriz de regressão, avaliar impacto de versão e segurança.
6. Submeter cada gate a revisão e decisão específica; somente então preparar go/no-go e rollout controlado, condicionados também a custos/licenças/MVP.
7. Proteger produção com plano de reversão, monitoramento e responsáveis, se e quando autorizado. **Nenhum dos passos 3–7 está liberado agora.**

## 9. Dependências com projetos e licenciamento

- CRM mantém matriz de papéis, organização, lead, histórico, tarefas, contrato/plano, saldos e motor nativo de automações; seus gates CORE/SEG/AUT devem ser homologados **no CRM** e não por PR do repositório AIA.
- Portal, Performance AI e Universidade não passam automaticamente a integrar o MVP: qualquer acesso exige contratos e homologações próprios.
- n8n central Imagine pode ser candidato a fluxos internos, desde que condições de licenciamento e uso comercial sejam verificadas antes de adoção; não é motor configurável de automação de clientes.
- Os prazos da fila de leads Imagine Sites e retenções já estabelecidas no CRM não são substituídos por critérios genéricos de laboratório.
- Nenhuma API de provedor, modelo, região, preço, quantidade de créditos ou SLA está homologada por esta proposta.

## 10. Registro de desvios e remediações (modelo futuro)

Cada falha futura deve apontar: ID do caso, contrato/versão, impacto/risco, evidências sintéticas, abrangência de tenants afetáveis, responsável, data de descoberta, bloqueio, correção proposta, revisão independente quando exigida, reteste, resultado, risco residual, decisão e vínculos com incidentes.

**Estados de registro:** `PROPOSTO`, `EM_REVISAO`, `HOMOLOGADO_COMO_CRITERIO`, `AGUARDANDO_EXECUCAO_AUTORIZADA`, `EXECUTADO_COM_EVIDENCIA`, `REPROVADO`, `CORRIGIDO_AGUARDANDO_RETESTE`, `ACEITO_FORMALMENTE`. Esses valores são conceituais, não tabela ou automação implementada.

Neste documento, **nenhum caso ultrapassou a condição de proposto**.

## 11. Matriz de decisões R02-R05-D01 a D10 — propostas

| ID | Tema | Alternativa A — recomendada | Alternativa B |
| --- | --- | --- | --- |
| R02-R05-D01 | Natureza da aprovação | Ratificar critérios como planejamento documental, sem dizer que testes foram executados ou gates fechados | Considerar casos escritos equivalentes a homologação |
| R02-R05-D02 | Cobertura | Matriz com identidade/ACL, alçadas, idempotência, dados, custos, integrações e recuperação | Testar somente resposta textual de IA |
| R02-R05-D03 | Dados/ambiente | Priorizar casos com dados sintéticos e ambiente isolado após autorização própria | Usar dados pessoais reais/produção para primeira validação |
| R02-R05-D04 | Critérios eliminatórios | Vazamento cross-tenant, bypass, efeitos sensíveis sem autorização e falhas graves bloqueiam aceite | Permitir go-live com falhas críticas conhecidas |
| R02-R05-D05 | Evidências | Exigir rastreabilidade por caso, versões, resultado observado, revisor, falha e reteste | Registrar apenas impressão subjetiva de sucesso |
| R02-R05-D06 | Propriedade do aceite | Produto valida seus recursos/permissões, AIA valida núcleo; funções técnicas e de segurança participam | Núcleo auto-homologa todos os produtos |
| R02-R05-D07 | Mudanças/regressão | Alterações materiais em modelo, contrato, ferramenta ou segurança exigem reavaliar casos afetados | Aceite vitalício sem revalidação |
| R02-R05-D08 | Gates | Preservar R01-G01 a G11 e CORE/SEG/AUT até evidência e decisão expressa individual | Fechar gates por aprovação genérica do R02/R05 |
| R02-R05-D09 | Risco residual/incidentes | Registrar, corrigir e retestar desvios; não dispensar violações críticas | Aceitar falhas silenciosamente para acelerar o MVP |
| R02-R05-D10 | Sequência CRM | Homologar primeiro critérios CRM→AIA→CRM e só depois integrar/implantar mediante autorizações separadas | Abrir todos os produtos e executar testes reais imediatamente |

**R02-R05-D01 a D10: ABERTAS**, aguardando decisão **documental preliminar** do responsável. Nada neste texto executa ou autoriza testes, fecha gate, escolhe tecnologia ou permite programação.

## 12. Pendências e próximos passos

- [ ] R02-R05-D01 a D10 aprovadas ou alteradas documentalmente.
- [ ] Consolidar matriz de critérios e evidências por gate/produto, sob revisão técnica.
- [ ] Confirmar os papéis e ACL reais do CRM, critérios de alçada e operações permitidas, sem modificar seus gates.
- [ ] Detalhar política jurídica/privacidade/terceiros e retenção de evidências por fluxo.
- [ ] Escolher posteriormente arquitetura técnica, tecnologias e credenciais de laboratório, sob autorização específica.
- [ ] Validar licenciamento comercial n8n e contratos de provedores; orçamento separado.
- [ ] Definir condições de entrada/saída para MVP CRM e controle de versão.
- [ ] Executar cenários **somente** quando testes forem autorizados e infraestrutura homologada.
- [ ] Manter **R01-G01 a G11 TODOS ABERTOS**, em especial G07.
- [ ] Manter **CORE/SEG/AUT e demais gates próprios do CRM TODOS ABERTOS**, sem código, ALLOW, SQL ou permissões cruzadas.

**Próximo bloco documental sugerido:** `AIA-R02/R06 — Síntese de Segurança e Matriz de Evidências para Futuros Gates Técnicos`, seguido de `AIA-R03 — Arquitetura Técnica e Topologia` após avaliação. Essas propostas não são aprovações nem autorização de programação.

**Preservação integral:** não alterar main/PRs/repositórios de CRM, Portal, Performance AI e Universidade; não provisionar VPS/Cloudflare/n8n; não integrar APIs, emitir credenciais, gastar, testar sistemas reais nem fazer deploy.
