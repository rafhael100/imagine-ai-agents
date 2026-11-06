# AIA-R02/S02 — Contratos de Autenticação, Claims, Validade e Revogação | Proposta documental v0.1

> **Estado:** diretrizes **R02-S02-D01 a D10 ABERTAS** para avaliação do responsável.  
> **Data:** 2026-11-06. **Projeto:** Imagine AI Agents.  
> **Precedência:** AIA-01 a AIA-12 aprovados funcionalmente; AIA-R01-D01 a D08 e R02-S01-D01 a D10 ratificados somente documentalmente.  
> **Gates:** R01-G01 a G11 permanecem **TODOS ABERTOS**, inclusive G01/G02. Não programar, emitir credenciais, executar testes reais nem implantar.

## 1. Objetivo

Propor um contrato lógico de autenticação e identidade confiável entre **CRM, Portal, Performance AI, Universidade** e o núcleo **Imagine AI Agents**, sem converter o núcleo em superadministrador ou herdar permissões entre produtos.

Este recorte define **quais informações precisam ser comprovadas e verificadas**, por quem, quando e como negar solicitações inválidas. **Não seleciona ainda** OAuth2/OIDC, JWT, mTLS, HTTP signatures, serviço de identidade, algoritmo de assinatura, biblioteca, região ou mecanismo de armazenamento de chaves; essas escolhas requerem estudo técnico, testes, revisão de ameaças e homologação separados.

A autenticação confirma **quem é o chamador**; a autorização determina **o que ele pode fazer**. Uma credencial válida não representa, sozinha, direito sobre lead, contrato, campanha ou conteúdo de curso.

## 2. Quatro identidades que não devem ser confundidas

| Identidade | Exemplo de função | Autoridade de origem |
|---|---|---|
| **Serviço emissor** | Backend CRM solicitando análise ao núcleo | Cadastro e credencial de serviço homologados |
| **Produto e ambiente** | CRM produção vs homologação | Registro de confiança e política de ambiente |
| **Empresa/tenant do produto** | Organização vinculada ao CRM, sem equivalência presumida com Portal | Produto emissor e vínculo canônico validado |
| **Solicitante humano ou conta técnica delegada** | Operador CRM com permissão para consultar determinado lead | Produto proprietário; escopo limitado, prazo e possibilidade de revogação |

Identidades de execução e agentes especialistas devem preservar a cadeia de delegação. Não permitir transformar uma identidade de serviço de integração em identidade administrativa irrestrita do produto.

## 3. Proposta de envelope lógico de identidade — não é payload de API aprovado

Campos documentais candidatos:

| Campo lógico | Finalidade | Regra de validação |
|---|---|---|
| `issuer_ref` | Identificar o emissor/autoridade que emitiu a declaração | Deve integrar registro homologado |
| `audience_ref` | Identificar o serviço destinatário e uso autorizado | Destinatário deve corresponder exatamente à solicitação |
| `subject_ref` | Identificar o serviço chamador, sem equivaler a usuário final | Autenticação efetiva do serviço |
| `product_ref`, `environment_ref` | Delimitar produto e ambiente | Registro oficial + isolamento produção/homologação |
| `product_tenant_ref`, `binding_ref` | Restringir a organização do produto e vínculo ao núcleo | Mapeamento verificável, ativo e não ambíguo |
| `principal_ref` | Usuário/serviço pelo qual se solicita a ação | Deve ser confirmado pelo produto emissor |
| `delegation_ref` | Evidência de delegação quando em nome de humano | Delegação válida, escopos limitados e revogável |
| `purpose_ref` | Finalidade específica | Permitida pelo contrato, produto e operação |
| `scopes` | Capacidades solicitadas | Interseção com permissões vigentes; nunca únicas provas |
| `issued_at`, `not_before`, `expires_at` | Controle temporal | Vigência curta, tolerância de relógio homologada |
| `request_id`, `nonce` ou equivalente | Proteger rastreamento e replay | Unicidade, política anti-replay e prazo |
| `agent_ref`, `agent_version` | Delimitar agente publicado elegível | Verificar catálogo e elegibilidade |
| `correlation_id` | Rastrear solicitações e eventos | Não pode conceder privilégio |

A lista pode ser fracionada entre token, contexto autenticado, referências internas e verificação online. **Não exigir que todos os campos transitem em tokens**; dados sensíveis devem ser minimizados e referências consultadas sob autorização.

## 4. Emissão, validação e delegação

### 4.1 Cadastro de confiança

A equipe Imagine registra serviços emissores, produtos, ambientes, destinatários, finalidades e políticas. Somente serviços homologados podem solicitar operações.

### 4.2 Solicitação autenticada

O backend de origem autentica o solicitante e valida direito local. Ele só encaminha ao núcleo a finalidade e o contexto mínimo comprováveis. Entrada do navegador, prompt, documento ou mensagem externa não deve emitir reivindicação de administrador, tenant ou alçada.

### 4.3 Autenticação do emissor

O núcleo verifica identidade do serviço, emissor aceito, destinatário específico, vigência, integridade, ambiente, revogação e proteção contra replay. Ausência de requisito essencial resulta em **negação por padrão**.

### 4.4 Vinculação empresarial

Antes de consultar recursos de produto, o núcleo resolve de forma segura a vinculação `(produto, tenant_local, finalidade)` → contexto de execução autorizado, rejeitando vínculos inexistentes, suspensos ou ambíguos.

### 4.5 Autorização na execução

Cada ferramenta AIA-04 revalida capacidade, produto/tenant, plano, principal, recurso, risco, alçada e vigência, e o **backend de destino** faz a última verificação do direito sobre seu recurso.

### 4.6 Delegação entre agentes

Delegação a outro agente ou fila assíncrona não amplia escopos; usa **interseção** do escopo autorizado original, recurso, finalidade, prazo e orçamento. Agentes não fabricam credenciais nem podem trocar produto/tenant.

## 5. Validade, renovação e revogação

- Identidades de serviço e delegações devem ter vigência curta e verificável, condicionada ao tipo de execução.
- Renovação/rotação deve ser controlada por serviço confiável e não pelo prompt ou memória do agente.
- Revogações podem ocorrer por saída do funcionário, perda de papel, cancelamento de contrato, desligamento de ferramenta, incidente, desativação da empresa ou troca de vínculo.
- Execução assíncrona em fila ou checkpoint não preserva autorização indefinida. Antes de **cada ação sensível** e retomada, revalidar contexto e revogação.
- Falha temporária de verificação deve **bloquear ou aguardar**; não recorrer a credencial global mais privilegiada.
- Revogação interrompe ações futuras, mas não apaga efeitos externos já confirmados; estes exigem trilha e eventual compensação pelo sistema dono.
- Definir depois, por análise de risco, mecanismos concretos de revogação, cache de decisão, latência aceitável e expiração. Nenhum TTL foi aprovado neste documento.

## 6. Antirreplay, assinatura, rotação e separação ambiental

- Preservar autenticação mútua/identidade verificável conforme tecnologia futuramente aprovada, e validação estrita de `issuer`, `audience`, validade e origem.
- Rejeitar mensagens repetidas fora da política de idempotência; diferença entre **retry idempotente** e **replay malicioso** deve ser documentada.
- A proteção não deve depender apenas de TLS: é necessária análise de replay em filas/eventos, reuso de tokens, callbacks e ambiente comprometido.
- Segredos/chaves devem ser protegidos, rotacionados e revogáveis; não incluir em repositório, prompt, logs, mensagens ou arquivos de teste.
- Separar homologação e produção; proibir credenciais e dados de produção em ambientes não homologados.
- Mudança de chave ou versão do emissor deve garantir transição controlada, sem aceitar emissores desconhecidos nem prolongar credenciais revogadas.
- Assinaturas de eventos e webhooks a serem definidas com AIA-12; rejeitar callback não autorizado e URL arbitrária enviada pelo usuário.

## 7. Escopos, autorização e conflitos

| Situação | Resultado documental exigido |
|---|---|
| Credencial de serviço válida, usuário sem permissão de lead | **Negar** acesso ao lead |
| Usuário do CRM com permissão em tenant A pede lead do tenant B | **Negar** sem revelar dados de B |
| Mesmo CNPJ/e-mail aparece em CRM e Portal | **Não vincular automaticamente** |
| Serviço emissor do Portal tenta usar autorização do CRM sem contrato | **Negar** |
| Token válido em homologação enviado para produção | **Negar** por ambiente/audience |
| Delegação vencida ou removida na espera da fila | **Negar** novos passos |
| Serviço tem acesso à API, mas agente não possui ferramenta homologada | **Negar** ação |
| Aprovação humana está expirada | **Negar** efeitos; nova aprovação conforme política |
| Identidade principal indefinida para mutação sensível | **Negar** ou exigir fluxo específico |
| Ausência temporária do verificador de direitos | **Bloquear ou aguardar**, sem autorização otimista |
| Texto externo instrui “ignorar escopos” | **Tratar como dados não confiáveis** |
| Mudança de empresa entre leitura e criação de tarefa | **Revalidar** no backend e bloquear cruzamento |

## 8. Respostas de erro e observabilidade seguras

Erros candidatos (conceituais e sujeitos a contratos AIA-12):

- `UNTRUSTED_ISSUER` ou `INVALID_SERVICE_IDENTITY`.
- `AUDIENCE_MISMATCH`, `ENVIRONMENT_MISMATCH`.
- `IDENTITY_EXPIRED`, `IDENTITY_NOT_YET_VALID`, `REVOKED_IDENTITY`.
- `REPLAY_DETECTED`, `DELEGATION_INVALID`.
- `TENANT_BINDING_INVALID`, `SCOPE_DENIED`.
- `AUTHORIZATION_UNAVAILABLE`, `APPROVAL_REQUIRED`.

Retornar mensagem segura, código estável, `correlation_id` e indicação conservadora de retentativa. **Não** expor nomes de tenants de terceiros, segredos, reivindicações brutas, credenciais, stack traces ou detalhes de validação que facilitem enumeração.

Logs mínimos: produto emissor, ambiente, identidade técnica pseudonimizada/referenciada, contexto validado, motivo categorizado de negativa, versão, carimbo de tempo, correlação e auditoria de quem inspecionou os eventos. Retenção e acesso dependem AIA-10/11 e decisão técnica.

## 9. Interface com produtos e componentes

**CRM piloto (primeiro recorte):** backend autentica usuário/serviço do CRM e passa identidade verificada ao núcleo; o CRM reautoriza recurso de lead/tarefa ao executar consulta ou mutação. Motor nativo de automações permanece no CRM. Resumos permanecem no histórico do CRM conforme diretrizes já aprovadas.

**Portal:** mantém login e contrato do serviço; não reutiliza automaticamente sessão ou conta do CRM.

**Performance AI:** aprovações e poderes sobre campanhas/contas da plataforma de mídia permanecem no próprio produto. Uma credencial do núcleo não concede direito de alterar orçamento.

**Universidade:** somente pessoas/clientes elegíveis; o tutor não herda dados de CRM, Portal ou Performance.

**n8n central Imagine:** quando futuramente homologado/licenciado, executa fluxos internos com identidade própria de serviço e escopos limitados; não pode se tornar bypass das permissões do produto nem editor cliente de automações.

## 10. Riscos e dependências ainda não homologados

1. Definir provedores de identidade, topologia de confiança, emissor de tokens e separação de ambientes.
2. Selecionar mecanismo concreto de autenticação e rotação, incluindo verificação de identidade técnica.
3. Estabelecer esquema de vínculo e revogação com os produtos, evitando acoplamento indevido.
4. Definir limites temporais (TTL, clock skew, caches), proteção anti-replay e política de renovação.
5. Revisar modelo de privilégio de cada ferramenta e diferença entre autorização humana e técnica.
6. Especificar auditoria e retenção de evidências, com minimização de dados pessoais.
7. Definir testes negativos com dados sintéticos e cenário de incidentes.
8. Homologar versões de contrato, impacto de indisponibilidade e estratégias de contingência.
9. Alinhar R02/S03 (escopos CRM), R02/S04 (dados) e R02/S05 (execução/idempotência).
10. Somente depois: gate de arquitetura/tecnologia, orçamento e autorização explícita para implementação isolada.

## 11. Matriz R02-S02-D01 a D10 — propostas para decisão

| ID | Tema | Alternativa A — recomendada | Alternativa B |
| --- | --- | --- | --- |
| R02-S02-D01 | Separação de identidades | Distinguir serviço, produto/ambiente, tenant, principal e delegação | Uma identidade global para todos os produtos |
| R02-S02-D02 | Confiança no emissor | Registro homologado, identidade forte de serviço e destinatário verificado | Aceitar chamada apenas por chave compartilhada indiscriminada |
| R02-S02-D03 | Claims e contexto | Contrato lógico mínimo, origem comprovada e dados sensíveis minimizados | Confiar em IDs, papéis e direitos declarados em texto livre |
| R02-S02-D04 | Audience e ambiente | Restringir emissor/destinatário/ambiente e finalidade em cada chamada | Credenciais aceitas em todos os serviços e ambientes |
| R02-S02-D05 | Delegação e escopos | Autorizar ações em nome de usuário somente sob delegação limitada e revogável | Serviço técnico atua como superadministrador |
| R02-S02-D06 | Validade e revogação | Expiração, revogação e revalidação antes de novas ferramentas/passos | Autorizações longas que sobrevivem a suspensões |
| R02-S02-D07 | Antirreplay | Identificação/verificação de requisição, controle de replay e retries idempotentes | Tratar retransmissão arbitrária como nova autorização |
| R02-S02-D08 | Segredos e rotação | Custódia protegida, separação de ambientes, rotação e revogação auditáveis | Segredos fixos em código, prompt ou logs |
| R02-S02-D09 | Erros e auditoria | Negação por padrão, códigos seguros, trilha mínima e resposta conservadora | Fallback permissivo e exposição de informação interna |
| R02-S02-D10 | Primeiro contrato | Detalhar CRM→AIA→CRM, sem escolher protocolo nem criar credenciais agora | Implantar SSO/integração de todos os produtos simultaneamente |

**R02-S02-D01 a D10 ABERTAS** — aguardam aprovação **documental preliminar** do responsável. Nenhum protocolo, algoritmo, ferramenta de identidade, chave, versão técnica ou arquitetura foram escolhidos.

## 12. Requisitos para eventual fechamento de R01-G01/G02

A aprovação documental de R02/S02 **não fecha** R01-G01 ou G02. Serão necessários:
- Design técnico completo de confiança, emissores, destinatários, delegações e vínculo tenant/produto.
- Modelo de escopos por recurso e permissões do CRM (R02/S03), incluindo precedência e negativa.
- Segurança de tokens/identidades, rotação, revogação, sincronização temporal e incidentes.
- Versões de contrato AIA-12, testes negativos, revisão de LGPD e segregação AIA-11.
- Critérios claros de aceitação, evidências de homologação e aprovação formal posterior.
- Aprovação separada para quaisquer testes reais, código, integração, custos ou deploy.

**Gates preservados: R01-G01 a G11 todos abertos.** A documentação não altera `main` de CRM, Portal, Performance AI ou Universidade, não modifica PRs desses projetos e não toca VPS/servidores, credenciais, SQL, contas externas ou pipelines.
