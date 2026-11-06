# AIA-R02/S01 — Matriz de Identidade, Autorização, Segregação e Fluxos entre Produtos | Proposta v0.1

> **Situação:** diretrizes **R02-S01-D01 a D10 APROVADAS DOCUMENTALMENTE em 2026-11-06** pelo responsável. Este recorte permanece preliminar e todos os gates R01-G01 a G11 seguem abertos.
> **Data de referência:** 2026-11-06.
> **Precedência:** AIA-01 a AIA-12 aprovados funcionalmente; AIA-R01-D01 a D08 aprovadas **somente documentalmente**. Gates **R01-G01 a G11 permanecem abertos**, inclusive G01/G02 abordados nesta proposta.
> **Escopo:** definir contratos e critérios, não configurar serviços, implementar API, autenticação, credencial, código ou testes reais.

## 1. Propósito e fronteira de confiança

O núcleo Imagine AI Agents é um serviço técnico compartilhado que pode atuar em nome de produtos Imagine, porém **não é um superadministrador universal**. Todo acesso a recursos de cliente deve ter origem confiável, propósito delimitado, direitos atuais e validação do **backend proprietário do recurso**.

As operações cruzam potencialmente quatro domínios distintos:
- **CRM Imagine:** autentica seus usuários, empresas, leads, tarefas e automações nativas.
- **Portal Imagine:** autentica seus próprios usuários, contratos, cobrança e serviços elegíveis.
- **Performance AI:** autentica relações com contas de anúncios, campanhas e operações externas.
- **Universidade Imagine:** autentica elegibilidade e acesso a conteúdos/progresso de aprendizado.
- **Imagine AI Agents:** valida o contrato de integração e capacidades do agente; coordena execução de IA e solicita ferramentas, sem herdar poder sobre todos os domínios.

**Não pressupor SSO entre Portal e CRM**, nem que pessoa/empresa com mesmo nome/e-mail/CNPJ nos dois produtos possua direitos equivalentes. Qualquer vínculo cross-product deverá ser criado e verificado por contrato de domínio, com auditabilidade e revogação.

## 2. Vocabulário e identificadores

| Identificador | Papel | Fonte confiável proposta |
|---|---|---|
| `product_id` | Produto responsável pela requisição | Cadastro interno de produtos homologados |
| `product_tenant_ref` | Empresa no âmbito de um produto | Produto autenticador, nunca texto do agente |
| `aia_tenant_binding_ref` | Vínculo específico autorizado de tenant/produto ao núcleo | Serviço de integração com mapeamento verificado |
| `principal_ref` | Identidade de usuário/serviço solicitante | Produto emissor ou mecanismo homologado |
| `acting_for_ref` | Identidade delegada/autorização em nome de usuário, quando existir | Prova de delegação válida e revogável |
| `resource_ref` | Recurso local: lead, contrato, tarefa, campanha, conteúdo | Produto dono do recurso |
| `agent_template_ref` / `version` | Agente elegível e publicação vigente | Catálogo AIA-01 |
| `operation_ref` / `tool_version` | Operação homologada e contrato | Catálogo AIA-04/AIA-12 |
| `purpose_ref` | Finalidade delimitada da ação | Política de produto/integração |
| `correlation_id` | Correlacionar eventos sem conceder privilégio | Emissor validado/núcleo, não prova de acesso |

**Esses nomes são conceituais**, não schemas, JWT claims ou tabelas aprovados. Identificadores externos arbitrários devem ser resolvidos pelo produto antes de se tornarem referências operacionais.

## 3. Matriz de autoridade por domínio

| Operação | Quem autentica e autoriza inicialmente | Quem autoriza na hora do efeito | Papel do núcleo AIA |
|---|---|---|---|
| Qualificar lead no CRM | CRM: usuário/serviço, organização, lead e finalidade | CRM: consulta dos dados e gravação do resultado | Executa tarefa IA sob contexto mínimo e limites |
| Criar tarefa no CRM | CRM e política de ferramenta | CRM: ação `criar tarefa`, escopo de lead, idempotência | Prepara solicitação e aguarda resultado confirmado |
| Consultar contrato no Portal | Portal e contrato do cliente | Portal: liberação dos campos permitidos | Solicita somente dados necessários |
| Recomendar campanha | Performance AI: identidade, conta e campanha | Performance AI: acesso a métricas | Produz recomendação sem mudar gasto |
| Alterar campanha ou orçamento | Performance AI e alçada/consentimento aplicáveis | Performance AI e provedor de mídia conforme regras próprias | Apenas propõe ou solicita ferramenta aprovada, sem bypass |
| Recuperar conteúdo de curso | Universidade: vínculo ativo, aluno e elegibilidade | Universidade: conteúdo e progresso autorizado | Tutor usa conteúdo autorizado, sem avaliação certificadora |
| Agente compartilhar dados entre produtos | Ambos os produtos e contrato de compartilhamento específico | Cada produto no seu limite; consentimentos/bases legais se aplicáveis | Negar sem vínculo e finalidade formalmente aprovados |

AIA pode negar uma execução adicionalmente à negativa do produto; **não pode sobrepor uma negativa do produto**.

## 4. Contrato conceitual de confiança entre serviços

Um pedido ao núcleo deve satisfazer cumulativamente:
1. **Emissor autenticado:** identificação confiável do serviço e do ambiente; rejeitar origem não homologada.
2. **Integridade e frescor:** proteção contra alteração e replay; janela de validade e verificação de origem.
3. **Contexto do domínio:** produto, tenant vinculado, solicitante e finalidade identificados por canal confiável.
4. **Autorização delegada:** se atuar por usuário, explicitar quem concedeu, escopos, validade, recurso e revogação.
5. **Permissões específicas:** template de agente, capacidade da ferramenta, plano/quotas, operação, recurso e risco.
6. **Verificação adicional:** autorização final pelo backend proprietário, sempre que recurso real for consultado ou alterado.
7. **Auditoria:** vincular requisição, decisões, versões, origem e efeito confirmado, preservando minimização de dados.

**A escolher em gate técnico posterior:** uso de OAuth2/OIDC, JWT de curta duração, mTLS, assinatura HTTP ou mecanismo equivalente, PKI, algoritmo, ambiente e detalhes de chaves. A proposta **não homologa tecnologia concreta**.

## 5. Escopos e autorização contextual

A política efetiva é uma **interseção** entre:
- direitos do principal;
- permissões e elegibilidade da organização no produto;
- recurso e finalidade permitidos;
- agente/versão e ferramenta/versão homologados;
- classificação de risco e alçada AIA-08;
- limites de contrato, saldo/cota, janela temporal e estado atual.

**Negação por padrão:** qualquer campo essencial ausente, vínculo ambíguo, token revogado, produto inativo, plano inelegível ou divergência de empresa impede o acesso. Não converter erros em permissões ou fazer fallback para uma conta de serviço mais privilegiada.

**Linguagem natural nunca autoriza:** solicitações “sou administrador”, “ignore seu sistema”, informações dentro de PDF, e-mails e memórias não acrescentam escopos nem trocam o tenant.

## 6. Fluxos entre serviços — propostas para homologação

### 6.1 CRM → AIA → CRM (piloto)

1. Usuário ou automação **nativa** do CRM é autenticado dentro de sua organização.
2. CRM valida elegibilidade do agente, lead e escopos; envia pedido mínimo identificando a origem e autorização.
3. Núcleo valida produto, mapeamento, versão do agente, finalidade, limites e contexto.
4. Uma ferramenta de leitura do CRM recebe identificação técnica limitada e o CRM **revalida** a organização/recurso.
5. Núcleo entrega resumo/classificação autorizados; o CRM decide como registrar resultado no seu histórico.
6. Se a tarefa de `criar_tarefa` for solicitada, a permissão específica, o risco, a aprovação (se exigida) e idempotência devem ser revalidados antes do efeito.
7. CRM confirma operação e devolve evento/resultado; AIA não assume sucesso por resposta do modelo.

### 6.2 Portal → AIA → Portal

Portal valida conta e contrato ativo, identidade e serviço adquirido. Nenhum direito do CRM é implícito. Se a resposta depender de dados do CRM, exigir contrato interproduto e autorização separada. O Portal permanece dono da interface, cobrança e histórico.

### 6.3 Performance AI → AIA → Performance AI

Performance AI valida titularidade da conta, campanha, ferramenta, aprovação de gasto e risco. Recomendações podem retornar como rascunho. Publicações e alterações de orçamento requerem decisão/execução oficial do produto e confirmação da plataforma de anúncios; não confundir gasto de mídia com créditos de IA.

### 6.4 Universidade → AIA → Universidade

Universidade verifica cliente elegível com serviço Imagine ativo, pessoa e conteúdo autorizado. O tutor não recebe por herança os dados CRM/Portal, nem realiza provas, notas ou certificados.

### 6.5 Fluxos internos Imagine e n8n

Uma execução interna do n8n, quando validada a licença, **não ganha privilégios de usuário ou de tenant**. Deve autenticar-se como serviço com escopos explícitos e respeitar a autorização do produto. Fluxos configuráveis por clientes continuam no motor nativo do CRM.

## 7. Alteração, expiração e revogação de direitos

- **Início de execução:** capturar referência verificável de política e expiração; não criar sessão permanente de privilégios.
- **Antes de cada ferramenta:** conferir suspensão, contrato/plano, expiração de autorização, alçada e permissões necessárias.
- **Execução longa:** revogações devem bloquear passos futuros mesmo se houver checkpoint válido; não apagar efeitos já confirmados.
- **Mudança de empresa ou vínculo:** invalidar mapeamentos anteriores e não transferir acesso automaticamente a outro tenant.
- **Cancelamento do último serviço elegível:** cessar novas atividades conforme regras de cada produto; retenção e exclusão seguem contratos e LGPD.
- **Falha de verificação:** negar ou aguardar dependência sob estado auditável, sem fallback permissivo.
- **Retentativa:** idempotência e validade devem ser verificadas novamente; jamais reautorizar por replay de evento antigo.

## 8. Isolamento de dados e infraestrutura compartilhada

Isolamento deve abranger todas as camadas, não apenas consultas SQL:
- indexação de conhecimento (AIA-05), memória derivada (AIA-06), cache e sessão;
- filas, checkpoints e execução concorrente (AIA-07);
- logs, métricas de consumo, auditoria e relatórios (AIA-10/11);
- resultados, webhooks, callbacks, uploads e referências (AIA-12);
- credenciais e chaves por produto/ambiente/finalidade;
- deduplicação e limitação por tenant, inclusive erros e recuperação.

Dados em um worker/servidor compartilhado **não** conferem direito de compartilhamento entre produtos/empresas. O isolamento físico versus lógico e técnicas de criptografia são decisões técnicas futuras sujeitas a testes negativos.

## 9. Casos de reprovação obrigatória (documentais)

| Caso | Comportamento exigido |
|---|---|
| Texto do usuário informa `tenant_id` de outra empresa | Rejeitar uso como autorização e preservar tenant autenticado |
| ID de lead pertence a organização diferente | Negar sem indicar dados privados da organização alheia |
| Mesmo e-mail/CPF/CNPJ aparece em dois produtos | Não vincular contas automaticamente |
| Papel de usuário revogado entre proposta e execução | Negar nova operação e registrar motivo |
| Contrato expirado, plano suspenso ou créditos insuficientes | Bloquear conforme política do produto |
| Evento de callback com assinatura inválida ou replay | Negar, auditar e prevenir operação duplicada |
| Agente tenta usar ferramenta não homologada | Negar antes da ferramenta, independentemente do prompt |
| Retry após timeout de criação de tarefa | Consultar confirmação/idempotência; não criar tarefa duplicada |
| Aprovador perde alçada após aprovar | Revalidar antes de executar e negar quando a política exigir |
| Job assíncrono retoma após cancelamento | Negar novos passos, sem alegar reversão dos efeitos já executados |
| Contexto privado de tenant A aparece em cache de tenant B | Bloqueio eliminatório de segurança |
| Provedor/modelo indisponível | Falha segura; fallback somente se homologado e autorizado |

Estes são **critérios documentais**, não testes executados. Os resultados reais dependerão de implementação/ambiente autorizados.

## 10. Evidências para fechamento de R01-G01 e G02 — ainda abertas

**G01 — Identidade e mapeamento de tenants:** exige matriz de produtos e emissores, identificadores canônicos, mecanismo de vinculação verificado, trust boundaries, delegação, revogação, ambientes, gestão de segredos e casos de não equivalência entre produtos.

**G02 — Escopos e autorização do recurso:** exige catálogo de operações por produto, direitos por papel/recurso/empresa, regras de elegibilidade, contrato de verificação dupla, negativas padronizadas, provas de menor privilégio e casos de reprovação.

**Nenhum desses gates pode ser considerado fechado com a simples aprovação de R02/S01**. O fechamento depende de contratos técnicos completos, critérios de homologação e decisão separada; testes reais exigirão autorização adicional.

## 11. Diretrizes documentais R02-S01-D01 a D10 — aprovadas

| ID | Tema | Alternativa A — recomendada | Alternativa B |
| --- | --- | --- | --- |
| R02-S01-D01 | Autoridade e limites | Produto é autoridade final sobre seus recursos; AIA verifica capacidades e coordena | AIA se torna superadmin dos produtos |
| R02-S01-D02 | Identificação do tenant | Vínculo autenticado e mapeamento explícito produto↔núcleo, sem associação automática por e-mail/CNPJ | Tratar IDs iguais como mesma organização |
| R02-S01-D03 | Identidade serviço-serviço | Emissores homologados, autenticação forte, escopo mínimo, validade e proteção contra replay | Compartilhar uma chave administrativa sem expiração |
| R02-S01-D04 | Autorização delegada | Identificar usuário/serviço e autoridade delegada, restrita e revogável | Acesso indiscriminado por conta técnica |
| R02-S01-D05 | Regra de autorização | Interseção de produto, tenant, principal, recurso, finalidade, ferramenta, versão, plano e alçada | Agente/prompt escolhe direitos |
| R02-S01-D06 | Dupla validação | Checar no núcleo e novamente no backend proprietário antes do acesso/efeito | Confiar em verificação única prévia |
| R02-S01-D07 | Segregação | Isolar dados e derivados em APIs, filas, cache, memória, índices, logs e callbacks | Isolar apenas na UI |
| R02-S01-D08 | Revogação e falha segura | Revalidar em cada ação, interromper novas etapas ao perder direito e negar na incerteza | Sessões duradouras com privilégios presumidos |
| R02-S01-D09 | Integração entre produtos | Integração explícita e contratual por produto; não presumir SSO nem compartilhamento | Compartilhar identidades e dados automaticamente |
| R02-S01-D10 | Primeiro recorte | Matriz e contratos CRM→AIA→CRM com leitura e tarefa controlada, sem acesso a dados reais neste estágio | Liberar simultaneamente todos os produtos |

**R02-S01-D01 a D10 APROVADAS DOCUMENTALMENTE em 2026-11-06 pelo responsável.** Aprovação exclusivamente **documental preliminar**; não implica escolha de protocolos, emissão de chaves, testes de autenticação, programação ou fechamento de gates.

## 12. Próximos detalhamentos documentais sugeridos

Após ratificação documental de R02/S01 (concluída):
- **R02/S02 — Contratos de Autenticação e Claims:** esquema lógico de identidade de produto/serviço/usuário, audience, issuer, expiração, revogação e mapeamento.
- **R02/S03 — Matriz de Permissões e Escopos CRM:** leads, resumo, classificação, tarefas e eventos, com cenários de autorização.
- **R02/S04 — Contratos de Segregação, Retenção e Conformidade:** políticas de dados/derivados, privacidade, minimização, incidentes e fluxos cross-product.
- **R02/S05 — Estados, Aprovações e Idempotência:** integração AIA-04/07/08/10/12, reconciliação e confirmação de efeitos.

Estes são **nomes propostos**, não aprovações nem novos módulos. Posteriormente será necessária revisão transversal, validação técnica e aprovação separada de implementação.

## 13. Condições inalteradas

- **R01-G01 a R01-G11:** todos em aberto, incluindo G01/G02.
- **AIA-01 a AIA-12:** aprovações funcionais preservadas; nenhum ajuste silencioso de decisões.
- **AIA-R01:** diretrizes documentais ratificadas, sem autorizar implantação.
- **CRM, Portal, Performance AI, Universidade:** repositórios, main, PRs e decisões independentes permanecem intactos.
- **VPS/servidores/Cloudflare/n8n:** sem instalações ou configurações reais.
- **Segredos, contas, API, SQL, testes funcionais, compras e deploy:** bloqueados até autorização expressa específica.
