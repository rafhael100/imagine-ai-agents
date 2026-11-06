# AIA-R03/S01 — Topologia, Fronteiras de Serviços e Ambientes | Proposta documental v0.1

> **Situação:** proposta de arquitetura documental; decisões **R03-S01-D01 a D10 ABERTAS** para avaliação do responsável.  
> **Referência:** 2026-11-06. **Repositório:** Imagine AI Agents.  
> **Precedência:** AIA-01 a AIA-12 com diretrizes funcionais aprovadas; AIA-R01 e AIA-R02/S01–S05 e R02/R01–R06 aprovados **somente documentalmente**.  
> **Gates:** todos os **R01-G01 a G11 ABERTOS**, especialmente G01/G02/G05/G08/G09/G10/G11; CORE/SEG/AUT e demais gates próprios do CRM abertos.  
> **Limite:** não selecionar stack definitiva, região, fornecedor, servidor, configuração, contrato, custo ou quantidade de instâncias; não programar, provisionar VPS/Cloudflare/n8n, emitir credenciais, implementar APIs, SQL/ALLOW, executar testes reais ou fazer deploy.

## 1. Objetivo

Definir **alternativas e fronteiras lógicas** para um **núcleo compartilhado de agentes de IA administrado pela Imagine**, consumido por produtos comercialmente e funcionalmente independentes: CRM, Portal, Sites, Performance AI e Universidade. Iniciar o detalhamento técnico sem transformar diretrizes documentais em componentes instalados.

O piloto proposto continua **CRM → núcleo AIA → CRM**, sujeito a contratos de identidade, permissões por recurso, alçadas, privacidade, idempotência e confirmação de efeitos no backend CRM. A arquitetura não concede acesso automático aos demais produtos.

## 2. Visão lógica candidata (não é diagrama de infraestrutura instalado)

```text
   CRM Imagine [owner de usuário, lead, ACL, tarefa e automações nativas]
       │ serviço autenticado, tenant explícito, operação autorizada
       ▼
┌───────────────────────────────────────────────────────────────┐
│ Fronteira de entrada do Núcleo Imagine AI Agents               │
│  Autenticação de serviço + tenant + escopos + versionamento    │
│     ▼                                                         │
│  Orquestrador de runs e políticas      Catálogo de agentes    │
│  Aprovações/revalidação                Ferramentas permitidas  │
│     ▼                                                         │
│  Execução isolada por contexto de tenant e finalidade          │
│  Estados e filas | memória/RAG opt-in | provedor IA homologado │
│     ▼                                                         │
│  Telemetria/custo mínimo | auditoria/retenção | reconciliador  │
└───────────────────────────────────────────────────────────────┘
       │ ferramenta oficial autenticada + autorização final
       ▼
   Backend CRM [revalida lead/campo/tarefa e confirma efeito]

Portas futuras, NÃO incluídas no piloto:
   Portal  ── contratos próprios ──┐
   Sites   ── eventos autorizados ─┤
   Performance AI ── contrato ─────┤→ gateway/entrada homologada AIA
   Universidade ── contrato ───────┘

   n8n interno Imagine ── contrato restrito, serviço homologado ── AIA
       (nunca acesso global a dados ou builder n8n do cliente)
```

As caixas ilustram **responsabilidades lógicas**, não um requisito de seis servidores, microserviços, contêineres, mensagens reais ou esquema de rede específico.

## 3. Componentes lógicos e autoridade

| Componente lógico | Responsabilidade candidata | Fronteira obrigatória |
| --- | --- | --- |
| Entrada/contratos | Identidade de serviço, ambiente/audience, tenant binding, quota, versões e erros seguros | Não confiar em IDs do prompt; negar por padrão |
| Catálogo de agentes AIA-01/02 | Templates, versões, publicação e elegibilidade | Nunca implantar agente não homologado por solicitação livre do LLM |
| Orquestração AIA-07 | Runs, etapas, concorrência, checkpoints, idempotência e recuperação | Não confirmar efeitos do CRM sem prova dele |
| Ferramentas AIA-04/12 | Integrações tipadas/versionadas, escopos e callbacks | Nenhuma API ou SQL irrestritos; CRM revalida recursos |
| Aprovações AIA-08 | Política, ação exata, alçada e revogação | Aprovação não substitui ACL nem confirma efeito |
| Provedores IA AIA-03 | Abstração de modelos, contingência e controle de custo | Provedor não recebe dado real sem homologação e contrato |
| Conhecimento/memória AIA-05/06 | Dados derivados e bases autorizadas por finalidade/tenant | Retenção, segregação e expurgo de índices/caches |
| Custos e auditoria AIA-10/11 | Metadados técnicos, alertas, limites, consumo e evidências | Sem prompts privados indiscriminados ou faturamento CRM direto |
| Infra e operação | Runtime, filas, estado, segredos, rede, observabilidade, backups | Credenciais por serviço/ambiente, privilégios mínimos |
| Produto CRM | Fonte de verdade de ACL, lead, atendimento, tarefa, planos/créditos, automação nativa | Não delegar acesso global ao núcleo AIA |

**Separação essencial:** agentes podem solicitar capacidades oferecidas por ferramentas homologadas, mas **não recebem acesso genérico ao banco do CRM, credenciais globais ou conta superadmin**.

## 4. Alternativas de topologia para análise futura

| Modelo | Descrição | Vantagens | Riscos e tradeoffs |
| --- | --- | --- | --- |
| **T-A: núcleo lógico único, componentes isolados** (recomendado como diretriz) | Um produto AIA central com módulos de entrada, execução, memória e observabilidade separados conceitualmente; fronteiras por produto/tenant e ambientes | Governança comum, menos duplicação, implantação inicial controlável | Exige segregação comprovada e cuidado com falhas comuns; topologia física pendente |
| T-B: motor próprio em cada produto | CRM/Portal/Performance/Universidade repetem orquestração e integrações | Isolamento físico por produto potencialmente simples | Duplicação de regras, custos, agentes, patches e governança |
| T-C: núcleo sem separação entre clientes | Mesma identidade global, compartilhamento de filas/memória sem ACL | Menor complexidade aparente | **Incompatível** com princípios ratificados de segregação e mínimo privilégio |
| T-D: múltiplos serviços independentes desde o primeiro dia | Entrada, workers, auditoria, memória etc. em infraestrutura dedicada por serviço | Escalabilidade e isolamento potencialmente mais granulares | Custo/operacionalidade e superfície de integração maiores, sem evidência de necessidade |

Escolher **T-A como orientação conceitual** não decide monólito modular, microserviços, VPS, contêineres, fornecedores, arquitetura de rede ou replicação.

## 5. Ambientes e caminhos de promoção

**Ambientes lógicos candidatos:**
- **Desenvolvimento:** documentação, código futuro e experimentos autorizados com dados sintéticos. Não usar tokens e dados de produção.
- **Homologação:** versão controlada e infraestrutura isolada autorizada para testes sintéticos, com responsáveis, critérios e evidências; **não está provisionada**.
- **Produção:** somente após contratos e gates homologados, autorização de custos e rollout separado; **não está implantada**.

Separações requeridas em desenho: emissores/audience/segredos específicos, namespaces ou recursos isolados quando cabível, retenção, limites e observabilidade por ambiente; promotion controlada por versão/artefato/aceite, nunca copiar bases privadas para desenvolvimento.

**Preferência conhecida como insumo — não aprovação:** a estratégia geral Imagine considera uma VPS central Leaseweb com Ubuntu e contêineres para desenvolvimento e serviços separados em produção. AIA-R03/S01 **não escolhe a Leaseweb ou uma VPS específica** para o núcleo nem reserva capacidade, configura redes ou instala nada; tais decisões exigem R03/S02–S05 e R01-G09/G10/G11.

## 6. Limites de rede e confiança

Desenho técnico futuro deve especificar:
1. **Entradas** apenas por serviços e emissores autorizados; autenticar origem, emissor, audiência, ambiente, tempo e revogação.
2. **Saídas** por catálogo de ferramentas/destinos permitidos, com validação contra SSRF, callbacks arbitrários e fluxos exfiltrantes.
3. **Segredos** sob custodiante técnico homologado, separados por produto/ambiente e rotacionáveis; não logar ou injetar em prompts.
4. **Segmentação** entre runtime de agentes, banco/estado, filas, memória/RAG, auditoria e provedores externos; desenho físico posterior.
5. **Segregação tenant** em cada mensagem, fila, cache, índice, arquivo, estado, log e backup, com revalidação de vínculo.
6. **Controle de egress de IA** que considere dados/segredos, aprovação de provedores e transferência internacional.
7. **Observabilidade** técnica sem acesso irrestrito a conteúdo de clientes.
8. **Recuperação** que revalide autorizações, prazos, efeitos já confirmados e exclusões anteriores.

Não impor escolha entre API gateway específico, VPN, service mesh, mTLS, OAuth2/OIDC, filas concretas ou provedor de segredos. Detalhar e comparar na fase de arquitetura.

## 7. Fronteira CRM → AIA → CRM

No piloto documental:
- CRM emite solicitação autenticada de **leitura/sugestão** para lead e histórico autorizado.
- Núcleo valida identidade, tenant, agente, ferramenta, plano, quota, finalidade e política; recebe somente dados minimizados.
- Agente produz **resumo e classificação sugeridos** sem modificar diretamente lead, atendimento, escore oficial ou histórico.
- Para **gravar resumo/criar tarefa**, exige intenção específica, direitos, alçada e confirmação do backend CRM; só o CRM comprova efeito.
- Timeout com possível efeito gera estado indeterminado e conciliação; retries não duplicam tarefas, registros ou créditos comerciais.
- Registros de execução, aprovação, status e custo preservam vínculo correto e retenção por finalidade.
- **Automação configurável pelo cliente permanece no motor nativo do CRM**. O n8n central não ganha acesso direto ao construtor nem a SQL CRM.

A futura interface comercial e acesso do Portal/Performance/Universidade requerem contrato bilateral próprio; a conta do Portal não substitui autenticação e ACL do CRM. **Sites:** formulários de captação permanecem condicionados ao destino CRM oficialmente vinculado e autorizado; publicações estáticas não conferem permissão de recepção de leads nem de armazenamento alternativo pelo AIA.

## 8. Topologia física, escalabilidade e custos: perguntas abertas

Avaliar posteriormente:
- Instância única com isolamento lógico versus implantação física separada por criticidade/ambiente; implicações de custo e risco.
- CPU/memória de workers, filas, jobs concorrentes, tempo máximo por execução, quotas, burst e propagação de falhas.
- Estratégia de persistência para runs, intents, idempotency keys, evidências, cache e índices; retenção e backups.
- Isolamento por tenant em operações concorrentes, mensagens e reprocessamento, inclusive em recuperação de incidentes.
- Dependências do modelo/fornecedor, limites de API e token, circuit breaker e contingência sem envio indevido de dados.
- RTO/RPO e disponibilidade esperados por serviço/produto; monitoramento, escalonamento e plano de recuperação.
- Egress, tráfego entre produtos, segurança de callbacks e endpoints sob contrato.
- Custos fixos de infraestrutura, uso variável IA, observabilidade, storage, backup, suporte e licenciamento n8n.
- Requisitos jurídicos e comerciais de provedores e regras de faturamento CRM.
- Segurança da cadeia de software, ambiente de CI e critérios de deploy/rollback.

**Nenhuma métrica, preço, região, VPS, framework ou fornecedor está homologado.** Estimativas futuras devem ser identificadas como simulações, não gasto autorizado.

## 9. Riscos de arquitetura e provas exigíveis futuramente

| Risco | Contenção documental | Evidência futura necessária |
| --- | --- | --- |
| Contaminação cruzada tenant A/B | Identidade/ACL, escopo e segregação em toda camada | Testes negativos isolados por tenant, incluindo filas e memória |
| Identidade técnica global com poderes excessivos | Identidades por serviço/escopo, autorização final do produto | Revisão e testes de bypass do CRM |
| Executor conclui por texto gerado | Fonte do efeito é backend proprietário | Contrato de confirmação e testes de timeout |
| Worker reprocessa ação sensível após revogação | Checkpoint + revalidação + idempotência | Testes de corrida, replay e reconciliação |
| Vazamento via prompt/log/índice | Minimização e classificação antes do uso e log | Provas sintéticas de negação/expurgo |
| Incidente compartilhado afeta todos os produtos | Isolamento de falhas e planos de degradação por dependência | Ensaios autorizados de contingência e operação |
| Custo imprevisível por modelo/retry | Quotas e conciliação de custo técnico/comercial | Cenários de limites e deduplicação |
| n8n comercial fora de licença | Homologação jurídica/comercial antes do uso | Termos/contrato e revisão de cenário operacional |
| Dependência de um só servidor sem recuperação | Planejamento de backup, RTO/RPO e plano de restauração | Testes autorizados de recuperação |
| Escolha de stack prematura | Comparação fundamentada de alternativas e gates | Decisões explícitas de tecnologia, orçamento e responsáveis |

Os cenários são requisitos de avaliação, **não** testes executados. Não declarar a arquitetura segura ou em produção por ter sido descrita.

## 10. Relação com gates R01

| Gate | Impacto desta proposta | Situação |
| --- | --- | --- |
| G01 identidade/tenant | Modelo de fronteira e caminho CRM→AIA→CRM | ABERTO |
| G02 escopos/autorização | CRM como autoridade final de recursos | ABERTO |
| G03 execução/idempotência | Responsabilidade de orquestrador/estado e efeito | ABERTO |
| G04 aprovações | Política e aprovações separadas de efeito | ABERTO |
| G05 LGPD | Minimização e segregação dos dados derivados | ABERTO |
| G06 custos/créditos | Medição técnica AIA separada da cobrança CRM | ABERTO |
| G07 laboratório | Casos negativos e aceite sintético, sem execução | ABERTO |
| G08 contratos CRM | Integrações bilaterais e versões a homologar | ABERTO |
| G09 licenciamento | Dependências de modelos/n8n ainda não verificadas | ABERTO |
| G10 arquitetura/operação | Topologia física, segurança, custo e contingência ainda pendentes | ABERTO |
| G11 MVP/orçamento | Recorte piloto e aprovação futura por etapa | ABERTO |

## 11. Matriz R03-S01-D01 a D10 — propostas para decisão

| ID | Tema | Alternativa A — recomendada | Alternativa B |
| --- | --- | --- | --- |
| R03-S01-D01 | Estrutura do produto | Núcleo lógico central AIA administrado pela Imagine e separado dos produtos | Replicar motores de agentes completos em cada produto |
| R03-S01-D02 | Fronteiras | Serviços, dados, permissões e efeitos pertencem ao respectivo produto; AIA não é superadmin | Compartilhamento de banco e acesso irrestrito |
| R03-S01-D03 | Ambientes | Separação documental de desenvolvimento, homologação e produção, com identidade/credencial próprias no futuro | Misturar dados e credenciais reais no ambiente de testes |
| R03-S01-D04 | Integração inicial | Piloto CRM→AIA→CRM com contratos específicos, sugestões sem escrita automática e tarefas controladas | Integração simultânea com todos os produtos |
| R03-S01-D05 | Identidade e rede | Fronteiras zero-trust, emissor/audience, escopos, destinos permitidos e proteção anti-exfiltração | Abertura geral de endpoints, callbacks e conexões |
| R03-S01-D06 | Estado e isolamento | Segregar filas, execuções, índices, memória, logs e backups por contexto/tenant/finalidade | Isolamento somente na interface |
| R03-S01-D07 | n8n central | Usar, se futuramente homologado/licenciado, apenas em serviços internos Imagine com identidade limitada; motor cliente nativo no CRM | Expor construtor n8n ao cliente como núcleo de automações |
| R03-S01-D08 | Topologia física | Comparar monólito modular, serviços, VPS/contêineres e alternativas; **nenhuma escolha/provisionamento nesta fase** | Fixar imediatamente um fornecedor/servidor |
| R03-S01-D09 | Continuidade/observabilidade | Planejar degradação por produto, monitoramento mínimo, backups, incidentes e custos antes da seleção técnica | Compartilhar falhas e dados sem limites |
| R03-S01-D10 | Próximos detalhamentos | Prosseguir em R03/S02–S05 documentalmente, manter todos os gates e implantação bloqueados | Autorizar código e deploy por aprovação deste recorte |

**R03-S01-D01 a D10: ABERTAS** para aprovação **documental preliminar** do responsável. Não há escolha de stack, fornecedor, região, protocolo, contrato, data de implantação ou orçamento. Esta proposta não fecha R01-G01 a G11 nem CORE/SEG/AUT do CRM.

## 12. Próximos recortes propostos

- **R03/S02 — Persistência, Filas, Idempotência e Recuperação:** modelos técnicos candidatos de estado/transação, idempotência, filas, cache, backups, observabilidade; sem programação.
- **R03/S03 — Identidade, Segredos, Redes e Conectividade Técnica:** comparação de padrões de autenticação, isolamento, egress, callbacks e revogação; sem emitir credenciais.
- **R03/S04 — Modelos, Provedores, Custo, Licenças e Contingência:** alternativas de infraestrutura/IA e condições comerciais; sem contratação.
- **R03/S05 — Implantação, Homologação e Rollback:** dependências dos gates, versões, roteiros e reversão; sem deploy.
- **R03/R01 — Revisão de arquitetura:** harmonizar critérios e evidências, sem homologação implícita.

Os nomes e etapas acima são **propostas**, não autorizações. A conclusão futura do planejamento não substitui homologação de R01-G01 a G11, testes negativos, aprovação de despesas e decisão separada de início de desenvolvimento.

## 13. Restrições e checklist

- [ ] Avaliar R03-S01-D01 a D10 e corrigir alternativas.
- [ ] Validar limites de responsabilidade e permissão com a documentação oficial dos produtos.
- [ ] Estabelecer critérios de comparação entre topologias, custo, risco, escalabilidade e isolamento sem escolher fornecedor.
- [ ] Preparar mapa de dados, identidade, segredos, filas, backups e custos, com responsáveis e evidências.
- [ ] Manter R01-G01 a G11 **TODOS ABERTOS**, inclusive G10.
- [ ] Manter CORE/SEG/AUT e todos os gates próprios do CRM abertos e inalterados.
- [ ] Preservar `main`/PRs de CRM, Portal, Performance AI e Universidade; infraestrutura e contratos não autorizados.
- [ ] Não criar código, testes reais, ALLOW, SQL, credenciais, contas, VPS/Cloudflare/n8n, contratos pagos ou deploy.

**Próximo passo recomendado:** aprovação documental preliminar do R03/S01 e, se ratificada, abertura separada do detalhamento R03/S02. 
