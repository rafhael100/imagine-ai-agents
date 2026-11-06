# AIA-R01 — Consolidação Transversal e Gates de Arquitetura | Proposta v0.1

> **Situação:** diretrizes de consolidação **R01-D01 a D08 APROVADAS DOCUMENTALMENTE em 2026-11-06**. Todos os gates R01-G01 a G11, homologações técnicas e autorização de implementação permanecem abertos.  
> **Data:** 2026-11-06.  
> **Marco verificado:** AIA-01 (D01–D08) e AIA-02 a AIA-12 (D01–D10 cada) aprovados **somente funcionalmente** e incorporados à `main`.  
> **Bloqueio:** esta revisão NÃO autoriza código, APIs reais, SQL, testes funcionais, despesas, mudanças em outros repositórios, provisionamento nem deploy.

## 1. Objetivo e método

A revisão AIA-R01 é um bloco **transversal, não AIA-13**. Seu objetivo é identificar dependências, tensões de responsabilidade e gates que precisam de contratos técnicos verificáveis antes de programar ou integrar o núcleo Imagine AI Agents com CRM, Portal, Performance AI ou Universidade.

**Escopo verificado:** matriz das diretrizes funcionais AIA-01 a AIA-12, `docs/ESCOPO-FUNCIONAL.md`, `docs/ROADMAP.md` e `STATUS-ATUAL.md` no repositório dos agentes. A aprovação dos módulos não equivale à aprovação de arquitetura, segurança implementada, viabilidade de fornecedores, orçamento ou critérios de homologação.

## 2. Inventário funcional consolidado

| Módulo | Estado funcional | Dependência primordial para implementação |
| --- | --- | --- |
| AIA-01 — Catálogo | Diretrizes aprovadas | Identidade de versões e permissões de instâncias por tenant/produto |
| AIA-02 — Instruções | Diretrizes aprovadas | Composição versionada e regras duras no backend |
| AIA-03 — Modelos/provedores | Diretrizes aprovadas | Homologação, privacidade, preço/consumo e roteamento |
| AIA-04 — Ferramentas | Diretrizes aprovadas | Contratos de APIs, autorização final no produto e idempotência |
| AIA-05 — Conhecimento | Diretrizes aprovadas | Classificação/ACL, retenção, indexação, proveniência |
| AIA-06 — Memória | Diretrizes aprovadas | Fonte de verdade no produto, ciclo de vida e exclusão |
| AIA-07 — Execução | Diretrizes aprovadas | Estados, filas, reconciliação e limites |
| AIA-08 — Aprovações | Diretrizes aprovadas | Alçadas vinculadas a ação, contrato de decisão e revogação |
| AIA-09 — Laboratório | Diretrizes aprovadas | Casos sintéticos, critérios eliminatórios e evidências |
| AIA-10 — Observabilidade | Diretrizes aprovadas | Eventos, custos conciliados, alertas e isolamento dos logs |
| AIA-11 — Governança | Diretrizes aprovadas | Matriz de riscos, contratos de dados, segurança e conformidade |
| AIA-12 — SDK/contratos | Diretrizes aprovadas | Autenticação serviço-serviço, schemas, versões, adaptadores |

A aprovação é do comportamento pretendido, não da implementação ou disponibilidade técnica.

## 3. Dependências cruzadas críticas

### 3.1 Identidade e autorização — AIA-01/04/06/08/11/12
Um `tenant_id` ou papel vindo da mensagem de texto não serve como prova. O produto emissor deve autenticar a identidade e a empresa; o núcleo verifica a vinculação e o contrato de confiança; a ferramenta do produto revalida a autorização antes de executar. **Gate antes de código funcional:** especificação técnica testável de contextos, tokens, audience/issuer, escopos, identidade de usuário e serviço, revogação e separação de tenants.

### 3.2 Execução, ferramenta e aprovação — AIA-04/07/08/10/12
A ação pode ser proposta pela IA mas jamais executada sem autorização real. Timeout não garante falha sem efeito. São necessários contratos conjuntos para a chave de idempotência, transições, aprovações vinculadas a parâmetros, cancelamento e confirmação externa. **Gate:** um diagrama de máquina de estados com transições válidas e regras de reconciliação.

### 3.3 Conhecimento, memória e privacidade — AIA-02/05/06/11
Conhecimento RAG, histórico transacional do CRM e memória derivada não devem se duplicar indiscriminadamente. Fontes externas são dados, não ordens; memórias e resumos não substituem decisões comprovadas. **Gate:** matriz de propriedade, classificação, expiração, revogação e eliminação de originais/derivados/caches.

### 3.4 Modelos, consumo e comercial — AIA-03/07/09/10/12
O núcleo registra custos técnicos; cada produto decide créditos e cobrança. Não confundir tokens com créditos de clientes nem gasto de mídia do Performance AI com faturamento da IA. **Gate:** contrato de uso único/deduplicado, reserva/compensação, preço versionado, limite por tenant e conciliação de faturas.

### 3.5 Avaliação, política e publicação — AIA-01/02/03/04/08/09/11
Mudar prompt, modelo ou ferramenta pode alterar o risco. Publicação de versão oficial exige revisão e avaliação; falha de segurança é bloqueio eliminatório, independentemente de nota de qualidade. **Gate:** matriz de casos críticos e responsáveis por aceite técnico e funcional.

### 3.6 n8n, motor nativo do CRM e agentes — AIA-07/12
O CRM mantém o construtor de automações do cliente. Imagine AI Agents coordena tarefas IA. n8n central executa somente fluxos controlados pela Imagine **sujeitos a homologação/licenciamento**. **Gate:** contrato de responsabilidades e validação das licenças efetivas do caso de uso, sem inferir direito comercial por ser self-hosted.

## 4. Lacunas a fechar — não são falhas implementadas

| ID de pendência | Prioridade sugerida | Tema | Evidência necessária para fechamento |
| --- | --- | --- | --- |
| R01-G01 | Bloqueante | Contrato identidade/tenant entre produtos | Identificadores canônicos, trust boundaries, mapeamento e negação por padrão |
| R01-G02 | Bloqueante | Escopos, recurso e autorização final da API | Matriz por operação + testes negativos de acesso |
| R01-G03 | Bloqueante | Execução/idempotência/reconciliação | Estados, retry, chave, confirmação, cancelamento e efeitos duplicados |
| R01-G04 | Bloqueante | Aprovações e alçadas | Ação exata, validade, aprovador, revogação, conflito e revalidação |
| R01-G05 | Bloqueante | LGPD, retenção e provedores | Finalidade, categorias, responsabilidades, contratos e expurgo de derivados |
| R01-G06 | Bloqueante | Limites/custos técnicos e créditos comerciais | Regras contábeis, reservas, deduplicação, cotas e reconciliação |
| R01-G07 | Bloqueante | Aceite do laboratório e gates | Casos sintéticos, não regressão, reprovação crítica e responsáveis |
| R01-G08 | Bloqueante | Compatibilidade e integração CRM | Versionamento de APIs, ownership, rollback e contratos mínimos |
| R01-G09 | Bloqueante | n8n/licenciamento e dependências externas | Avaliação jurídica/comercial da arquitetura e termos efetivos |
| R01-G10 | Bloqueante | Arquitetura técnica e operações | Stack, topologia/isolamento, segredos, logs, filas, backups, RTO/RPO |
| R01-G11 | Bloqueante | MVP, orçamento e plano de implementação | Recorte piloto, aprovação de custos, etapas e autorização expressa |

Os gates acima são **sugestões documentais** e permanecem abertos; nenhuma aprovação anterior implica seu fechamento automático. Critérios concretos e testes dependem de detalhamento posterior.

## 5. Recomposição de fronteiras entre projetos

**Imagine CRM** conserva dados de leads, funis, tarefas e atendimento, motor visual de automações e direitos de usuários. Resumos de IA gravados no histórico do atendimento permanecem governados pelo CRM; o núcleo só retorna resultado/metadata autorizados.

**Portal** conserva contratos, plano, cobrança e identidade de acesso; não presumir SSO entre Portal e CRM.

**Performance AI** é domínio independente de campanhas, contas de anúncio e aprovação de investimento; recomendação da IA não é autorização para efetuar gasto.

**Universidade** preserva acesso apenas a clientes elegíveis com serviço ativo e ensino sem provas/notas/certificados.

**Agentes** conservam catálogo, execução de tarefas IA, governança técnica e adaptação por APIs; não substituir o domínio dos produtos nem ampliar o acesso de clientes.

## 6. Sequência documental recomendada após R01

1. **R02 — Segurança e contratos transversais:** autorização e isolamento; autenticação entre serviços; dados/retentivas/retention; estados de aprovação, confirmação e idempotência.
2. **R03 — Arquitetura técnica e integração:** tecnologias, limites de implantação, serviços, filas, banco, segredos, observabilidade, SLA e confiabilidade. Avaliar VPS central e isolamento de contêineres sem provisionar nada.
3. **R04 — Fornecedores, licenciamento e orçamento:** opções de modelos, APIs, termos atuais do n8n, política de provedor, preço versionado e estimativa de custos por cenário.
4. **R05 — MVP e homologação:** recorte piloto do CRM, contratos mínimos, matriz de testes sintéticos, métricas, aceite, rollout/rollback e checklist de go/no-go.
5. **Gate de implementação:** só após aprovar expressamente documentação, segurança, custos e recorte de código, com branch/PR isolados e `main`/VPS preservadas até autorizações específicas.

Os identificadores R02–R05 são **nomes propostos de trabalhos transversais**, não módulos AIA-13 etc. Ainda precisam de avaliação e detalhamento.

## 7. Cenário de MVP sugerido para análise futura

**Agente de apoio à qualificação no CRM**, com escopo inicial de consulta autorizada, resumo e sugestão de classificação; criação de tarefa somente com ferramenta específica, idempotência e política aprovada. Sem alterar preço, fatura, campanha, permissão, contrato, automações personalizadas de clientes ou dados de outras empresas.

Critérios de entrega não negociáveis propostos: tenant isolation, escopos mínimos, observabilidade básica, custo/limite por empresa, erro tipado, testes negativos, rollback e evidência de autorização. Não implica autorização de implementação nem cronograma de deploy.

## 8. Diretrizes documentais R01-D01 a D08 — aprovadas

| ID | Decisão | Alternativa A — recomendada | Alternativa B |
| --- | --- | --- | --- |
| R01-D01 | Marco funcional | Reconhecer AIA-01–12 como diretrizes funcionais aprovadas, mas não como fechamento técnico | Considerar prontas para código todas as interfaces |
| R01-D02 | Hierarquia de autoridade | Produto mantém dados, direitos e regras; núcleo coordena IA sob contratos | Núcleo central substitui autorização dos produtos |
| R01-D03 | Gates obrigatórios | Manter R01-G01–G11 abertos até evidências e decisão explícita | Fechar gates por aprovação genérica dos módulos |
| R01-D04 | Ordem de detalhamento | Priorizar segurança/contratos, depois arquitetura, fornecedores/orçamento e MVP | Escolher ferramentas/servidores antes de validar segurança |
| R01-D05 | CRM piloto | Planejar primeiro qualificação/sumarização e tarefa controlada, sem duplicar automações | Integração simultânea de todos os produtos |
| R01-D06 | Integração e estados | Definir contrato conjunto AIA-04/07/08/10/12 antes de efeitos externos | Cada agente implementar próprios estados/callbacks |
| R01-D07 | Dados e memória | Definir ACL, origem, segregação e retenção antes de ingestão ou persistência | Centralizar cópias integrais de dados sem escopo |
| R01-D08 | Liberação de desenvolvimento | Exigir autorização futura específica por etapa/branch/PR e manter `main`, outros repos e VPS inalterados | Tratar esta revisão como ordem de implementação |

**R01-D01 a D08: APROVADAS DOCUMENTALMENTE em 2026-11-06 pelo responsável.** Esta ratificação **não fecha nenhum dos 11 gates R01-G01 a G11**, não escolhe tecnologia, não homologa contratos, não autoriza despesas nem libera programação.

## 9. Riscos de decisões prematuras

- **Instalar ou contratar infraestrutura antes dos contratos:** risco de custo e acoplamento indevido.
- **Usar o n8n como editor de cliente:** potencial divergência com estratégia do motor nativo do CRM e termos comerciais; avaliar antes de usar.
- **Criar banco central irrestrito:** compromete segregação, base legal e autonomia dos produtos.
- **Lançar agente “autônomo” sem alçadas e idempotência:** ações sensíveis sem confirmação podem produzir perdas ou duplicidades.
- **Escolher modelo somente pelo preço:** pode piorar qualidade, segurança, privacidade e custo de operação.
- **Declarar projeto 100% pronto:** o percentual atual se refere exclusivamente aos 12 módulos com diretrizes funcionais aprovadas.

## 10. Próxima decisão a solicitar

**R01-D01 a D08 foram ratificadas documentalmente.** Todos os gates R01-G01 a G11 e as homologações técnicas continuam abertos. Próximo detalhamento: **R02/S01 — Matriz de Identidade, Autorização, Segregação e Fluxos entre Produtos**, para avaliação por etapas, sem programação.

**Imutáveis até nova autorização:** `main` dos demais repositórios, alterações de CRM/Portal/Performance/Universidade, VPS/servidores, credenciais, SQL, integrações reais, testes funcionais, contratos pagos e deploy.
