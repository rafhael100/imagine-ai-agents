# AIA-R03/R01 — Revisão Consolidada da Arquitetura Técnica, Pendências e Gates | Proposta documental v0.1

> **Estado:** revisão de arquitetura **DOCUMENTAL EM PROPOSTA**; **R03-R01-D01 a D08 ABERTAS** para aprovação preliminar.  
> **Data de referência:** 2026-11-06. **Projeto:** Imagine AI Agents.  
> **Precedência:** AIA-01 a AIA-12 aprovados funcionalmente; AIA-R01; AIA-R02/S01–S05; AIA-R02/R01–R06; e AIA-R03/S01–S05 aprovados **exclusivamente documentalmente**.  
> **Gates:** **R01-G01 a G11 TODOS ABERTOS**, especialmente G01/G02/G03/G05/G06/G08/G09/G10/G11. CORE/SEG/AUT e demais gates próprios do CRM permanecem **abertos**.  
> **Proibições:** esta revisão não define arquitetura física final, não provisiona serviços, não escolhe modelos, tecnologias, servidores, licenças ou preços, não cria código, ALLOW, SQL, APIs, credenciais, testes funcionais, contratos pagos nem deploy.

## 1. Finalidade, fontes e alcance da consolidação

Este documento revisa a consistência e as lacunas dos **cinco recortes arquiteturais R03/S01 a S05**, confrontando-os com as diretrizes de segurança do R02 e os gates do R01. **Conclusão documental não é homologação técnica**, e aprovação de PR não equivale a funcionamento de recurso.

| Fonte | Diretrizes consolidadas | Aspectos ainda não homologados |
| --- | --- | --- |
| **R03/S01 — Topologia e fronteiras** | Núcleo lógico Imagine separado dos produtos; piloto CRM→AIA→CRM; ambientes segregados; n8n interno | Topologia física, dimensionamento, rede, custos e controle verificável de isolamento |
| **R03/S02 — Persistência e filas** | Estado durável, fila recuperável, idempotência de efeito, reconciliação, retenção e backup | Banco/broker, schema, atomicidade, limites, concorrência, RTO/RPO e recuperação testada |
| **R03/S03 — Identidade e conectividade** | Emissores/escopos/delegação, proteção de credenciais, egress permitido e falha segura | Protocolo, claims reais, rotação/revogação, IAM, custódia de segredos e rede |
| **R03/S04 — Provedores e custos** | Catálogo homologável, fallback condicionado, medição técnica independente de crédito CRM, n8n sob revisão | Fornecedores/modelos/regiões, preços atuais, contratos, licenças, quotas numéricas e orçamento |
| **R03/S05 — Implantação e rollback** | Gates separados, versionamento, go/no-go, rollout limitado e rollback sem desfazer efeitos CRM | Responsáveis designados, ambientes, critérios de release, evidências, migrações e implantação |
| **R02/S01–S05 e R02/R01–R06** | Autoridade de cada produto, dados mínimos, alçadas, estados, evidências e controles de segurança | Contratos executáveis bilaterais, validação LGPD, evidências técnicas e decisão de cada gate |

A arquitetura lógica proposta pode ser **coerente como referência documental**, mas sua viabilidade técnica, custos e conformidade dependem de análises e evidências futuras.

## 2. Invariantes transversais consolidadas

1. **Autoridade de produto:** o CRM continua responsável por usuário, organização, lead, atendimento, tarefas, plano, crédito comercial, autorização final e confirmação de efeito. Portal, Sites, Performance AI e Universidade preservam responsabilidades e contratos próprios.
2. **Núcleo sem superadmin:** Imagine AI Agents recebe pedidos restritos, autentica serviços, checa vínculo de tenant/agente/ferramenta e não acessa bancos dos produtos diretamente por privilégio global.
3. **Sem efeito por inferência:** resposta de IA, sugestão, aprovação humana e aceite HTTP não comprovam gravação; somente o backend proprietário do recurso confirma o efeito.
4. **Intenção idempotente:** tentativas de rede, eventos e retries não podem recriar efeito. Resultado de timeout potencialmente efetivado exige consulta/reconciliação antes de repetir.
5. **Proteção de dados desde a fonte:** filtrar campos antes da inferência, segregar memória/RAG/filas/cache/logs/backups por tenant/finalidade e impedir armazenamento global de dados do cliente por padrão.
6. **Segredos e rede restritos:** identidade de serviço, revogação, egress permitido, proteção contra SSRF/replay, sem credenciais em prompts, logs ou repositórios.
7. **Custos e licenças governados:** uso técnico AIA não equivale a crédito comercial do CRM; provedores e n8n exigem análise de contrato e licenciamento efetivo antes da utilização.
8. **Dev, homologação e produção separados:** versões e autorizações independentes; dados reais e credenciais produtivas não devem ser copiados para laboratório.
9. **Rollout condicionado:** aprovação documental não libera código, testes reais, despesas ou deploy; cada gate e produto exige responsável, evidência e decisão formal.
10. **n8n só interno Imagine sob contrato adequado:** automações configuráveis pelo cliente continuam no motor nativo do CRM, e uma eventual orquestração n8n não herda direitos de root.

## 3. Desenho integrado lógico, não implantado

```text
[CRM Imagine: usuário, ACL, plano, lead, histórico e tarefa]
    │  contrato serviço→serviço autenticado e tenant validado
    ▼
[Entrada do Núcleo Imagine AI Agents]
    ├─ identidade/tenant/política/capacidade, validação e limites
    ├─ catálogo e versões de agentes / ferramentas permitidas
    ├─ run, step, intenção, aprovação, tentativa, efeito e uso
    ├─ estado durável / fila / worker / recuperação
    ├─ controle de contexto e memória autorizada por finalidade
    ├─ provedor IA homologado + custos técnicos + contingência
    └─ trilha de auditoria mínima + alertas/privacidade
    │
    └──> [Ferramenta CRM] ──> [CRM autoriza recurso e confirma efeito]
                                  │
                       [Reconciliação por intenção/efeito]
                                  │
                           [Estado de execução AIA]

Outros produtos (Portal/Sites/Performance/Universidade):
   somente contratos e habilitações futuras específicas.

n8n interno Imagine:
   possibilidade sujeita a homologação de identidade, licença,
   acesso limitado e separação do motor nativo de automações CRM.
```

Esta representação **não especifica microserviços, contêineres, VPS, fornecedor de nuvem, conectores reais, URLs, secrets, tecnologia de filas ou topologia física**.

## 4. Conflitos e pontos de atenção para o próximo detalhamento

| ID | Questão arquitetural pendente | Resolução documental exigida | Gates vinculados |
| --- | --- | --- | --- |
| **ARQ-C01** | Serviço autenticado versus permissão de recurso | Definir integração com ACL oficial CRM e revalidação final por ação, sem bypass | G01/G02/G08 |
| **ARQ-C02** | Fila assíncrona versus expiração/revogação | Revalidar titularidade, plano, alçada, consentimentos e vínculo após espera ou checkpoint | G01/G03/G04 |
| **ARQ-C03** | Banco AIA versus fonte de verdade do CRM | Persistir apenas estados/referências autorizadas; CRM retém efeito e dados comerciais | G03/G05/G08 |
| **ARQ-C04** | Enfileiramento versus commit durável | Evitar aceitar pedido que pode se perder e duplicar efeitos; avaliar outbox/inbox ou equivalente | G03/G10 |
| **ARQ-C05** | Retentativa do worker versus duplicação de tarefa | Chave idempotente fim a fim, concorrência e reconciliador consultando CRM | G03/G08 |
| **ARQ-C06** | Log de aprovação versus privacidade | Evidência de proposta/versão sem copiar prompts, dados pessoais ou segredos em excesso | G04/G05/G07 |
| **ARQ-C07** | Multi-tenant versus fila/memória compartilhada | Impor isolamento em execução, cache, índice, backup e logs, inclusive após restore | G01/G05/G10 |
| **ARQ-C08** | Roteamento/fallback versus termos de dados | Alternativo precisa de finalidade, contrato, região e dados permitidos, sem downgrade de segurança | G05/G09 |
| **ARQ-C09** | Uso técnico versus crédito comercial | CRM governa saldo/cobrança; AIA entrega métricas verificáveis com deduplicação | G06/G08 |
| **ARQ-C10** | n8n central versus automação do cliente | Contrato restrito para serviços Imagine e revisão de licença; construtor do cliente no CRM nativo | G08/G09 |
| **ARQ-C11** | Rollback de release versus efeitos de negócio | Reverter versão não desfaz tarefa ou resumo confirmado no CRM; compensação requer autorização | G03/G04/G10 |
| **ARQ-C12** | Escala e disponibilidade versus custos | Comparar opções físicas e operação futura com limites e RTO/RPO, sem comprometer orçamento | G09/G10/G11 |

**Nenhum ARQ-C01 a C12 está tecnicamente resolvido ou homologado por este inventário.** Trata-se de prioridades de especificação e teste, não de declaração de defeitos observados.

## 5. Matriz dos 11 gates e evidências ainda necessárias

| Gate | Dependência prioritária | Evidência ainda pendente | Situação |
| --- | --- | --- | --- |
| **R01-G01** Identidade e tenant | S01/S03; R02/S01/S02; R03/S03 | Trust model efetivo, vinculação de tenant, emissor, audiência, revogação e testes de falsificação | **ABERTO** |
| **R01-G02** Escopos e autorização | R02/S03/R02; R03/S01/S03 | ACL oficial CRM, operação por lead/campo, autorização final e negações comprovadas | **ABERTO** |
| **R01-G03** Execução e idempotência | R02/S05/R03; R03/S02/S05 | Estados, confirmação de efeito, concorrência, timeout, deduplicação e reconciliação comprovados | **ABERTO** |
| **R01-G04** Aprovações e alçadas | AIA-08; R02/S05/R03; R03/S05 | Papéis aprovadores, parâmetros materiais, expiração, revogação e fluxos de negação | **ABERTO** |
| **R01-G05** LGPD e retenção | R02/S04/R04; R03/S02/S03/S04 | Inventário real, bases legais, contratos, dados derivados, expurgo/restauração e incidentes | **ABERTO** |
| **R01-G06** Custos e créditos | AIA-10; R03/S02/S04 | Tarifas atuais, unidades, quotas, política de crédito do CRM e conciliação | **ABERTO** |
| **R01-G07** Laboratório e aceite | AIA-09; R02/R05; R03/S05 | Cenários sintéticos homologados, ambiente autorizado, resultados observados, correção e reteste | **ABERTO** |
| **R01-G08** Integração CRM | R02/R02/R03; R03/S01/S03 | Contratos bilaterais, schemas, erros, versões, callbacks, efeitos e aceite do CRM | **ABERTO** |
| **R01-G09** Licenças e terceiros | R03/S04 | Termos atuais de n8n/modelos/conectores, contratos, direitos comerciais e custos | **ABERTO** |
| **R01-G10** Arquitetura e operação | R03/S01–S05 | Seleção fundamentada de stack/topologia, segregação, segredos, observabilidade, backup/DR | **ABERTO** |
| **R01-G11** MVP, orçamento e autorização | AIA-R01/R02; R03/S04/S05 | Escopo comercial/técnico, orçamento, responsáveis, go/no-go e autorização específica por etapa | **ABERTO** |

**Regra geral:** uma documentação aprovada pode definir requisitos para um gate, mas nunca prova que o controle foi implantado ou testado. Fechamento futuro: contrato técnico + ambiente/execução autorizados + evidência + aceite explícito competente.

## 6. Distinção entre stack candidata e tecnologia homologada

| Tema | Candidatos a comparar futuramente | Fatores de decisão | Homologação |
| --- | --- | --- | --- |
| Execução do núcleo | Aplicação modular central, serviços separados, workers especializados | Isolamento, manutenção, custo e capacidade | **Não realizada** |
| Estado e intenção | Banco relacional, documento/NoSQL ou outras garantias duráveis | Atomicidade, idempotência, recuperação e auditoria | **Não realizada** |
| Filas | Banco/fila durável, broker especializado, serviço gerenciado | Backpressure, repetição, DLQ, operação e custos | **Não realizada** |
| Memória/RAG | Busca autorizada/indexação com isolamento e expurgo | LGPD, custo, segregação de tenant e qualidade | **Não realizada** |
| Identidade/segredos | Tokens curtos, identidade de workload, canal autenticado, vault | Revogação, rota de confiança, auditoria e gestão | **Não realizada** |
| Hospedagem | VPS isolada, contêineres, instância dedicada ou serviço gerenciado | Segurança, regiões, escalabilidade, custo e dependências | **Não realizada** |
| Modelos de IA | Catálogo abstrato de fornecedores e versões | Qualidade, contrato de dados, preço e fallback | **Não realizada** |
| Orquestração interna | Motor próprio AIA e eventual n8n limitado | Licença, governança, isolamento e recuperação | **Não realizada** |

Preferências e serviços já existentes no ecossistema Imagine, como **Leaseweb, Ubuntu/Docker, PostgreSQL, n8n e Cloudflare**, são apenas **insumos de comparação**. Aprovar esta revisão **não aprova sua instalação, uso, contratação ou extensão ao núcleo**.

## 7. Sequência recomendada para detalhamento técnico futuro

**Fase A — contratos e critérios bilaterais, ainda documentais:**
- A1. Consolidar matriz oficial de permissões/recursos CRM e identidade de serviço AIA, com principal e delegação.
- A2. Detalhar schemas lógicos de `run`, `intent`, `approval`, `attempt`, `effect` e `usage`, erros/versionamento, endpoints candidatos e ownership.
- A3. Especificar confirmação/idempotência no CRM, concorrência, resultados indeterminados, consulta e reconciliação.
- A4. Mapear fluxos LGPD/retenção/provedores, responsabilidades, respostas a incidentes e evidências de aprovação.
- A5. Definir critérios eliminatórios sintéticos antes de planejar laboratório.

**Fase B — seleção arquitetural e econômico-jurídica, ainda documental:**
- B1. Comparar stack de execução/persistência/fila/rede/segredos com matriz de custos, risco e reversibilidade.
- B2. Validar termos atuais n8n e fornecedores, modelos, regiões, políticas de dados e plano de contingência.
- B3. Simular capacidade e custos do MVP, fluxo de cobrança/créditos do CRM e limites por tenant.
- B4. Definir RTO/RPO, observabilidade, backup, restore, rollout e rollback futuros.

**Fase C — autorizações separadas, NÃO concedidas agora:**
- C1. Avaliar fechamento **individual** de cada R01-Gxx quando houver evidências técnicas.
- C2. Obter decisão explícita sobre escopo, orçamento, branch e permissão de implementar — sem supor que aprovação documental bastou.
- C3. Somente com autorizações próprias, executar testes funcionais sintéticos, integração controlada e go/no-go.
- C4. Deploy de produção somente após decisões distintas, incluindo gates e autorização CRM aplicáveis.

A sequência exata depende do estágio dos projetos e de validações de responsáveis; não criar dependência circular em que só se pode testar após todos os gates fechados. O futuro plano de homologação deverá distinguir **gates para iniciar implementação, gates para iniciar testes e gates para produção**, com aprovação específica de cada fase.

## 8. Mapa de riscos e não objetivos

| Risco | Contenção documental já recomendada | Evidência exigível |
| --- | --- | --- |
| Vazamento de dados entre empresas | Isolamento em todas as camadas, ACL e minimização | Testes negativos de tenant/índice/fila/log/backup |
| Ação indevida por agente | Ferramentas restritas, alçadas, CRM valida e confirma | Contrato oficial e testes de autorização |
| Efeito duplicado após timeout | Idempotência no executor e consulta de resultado | Testes de concorrência, falhas e replay |
| Perda de estado após queda | Estado durável, filas recuperáveis, checkpoints | Testes de crash/restore sob autorização |
| Provedor com contrato incompatível | Homologação jurídica/técnica por finalidade e classe | Termos e revisões atualizados |
| Escalada via n8n/credencial global | Identidade restrita de serviço e motor nativo CRM separado | Verificação de licença e permissão |
| Cobrança/consumo incorreto | Métricas técnicas separadas de créditos comerciais | Reconciliação auditada CRM/AIA |
| Rollback afeta dados CRM | Isolar release AIA de operações do produto | Plano reversível e testes específicos |
| Custo de infraestrutura não previsto | Capacidade, quotas, observabilidade e orçamento | Simulações e custos atuais verificados |
| Publicação antes de gates | Go/no-go formal, incidentes e evidências | Registro de decisão de cada gate |

**Não objetivos:** executar uma prova de conceito, instalar bibliotecas, escolher a região de produção, criar banco, desenhar schema SQL definitivo, criar permissões, configurar Cloudflare/n8n, instalar agentes, conceder acesso a dados privados ou automatizar atividades do CRM/Portal neste turno.

## 9. Matriz R03-R01-D01 a D08 — decisões propostas

| ID | Tema | Alternativa A — recomendada | Alternativa B |
| --- | --- | --- | --- |
| **R03-R01-D01** | Status da arquitetura | Reconhecer R03/S01–S05 consolidados como **diretrizes documentais**, sem stack homologada | Declarar infraestrutura e arquitetura prontas para produção |
| **R03-R01-D02** | Autoridade e isolamento | Manter produtos como fontes de verdade, AIA central de IA com segregação por produto/tenant e sem superadmin | Banco/permissões compartilhados sem fronteiras |
| **R03-R01-D03** | Execução/confiabilidade | Exigir estado durável, filas controladas, idempotência no destino e reconciliação antes de escolher tecnologias | Confiar em memória volátil, retries cegos e resposta do modelo |
| **R03-R01-D04** | Identidade/segredos/rede | Preservar autenticação de serviços, delegação, revogação, egress/SSRF e custódia restrita | Credenciais globais e redes abertas para facilitar implantação |
| **R03-R01-D05** | Dados, fornecedores e custos | Exigir minimização, LGPD, termos/licenças atuais, modelo homologado por finalidade e custo técnico/crédito separados | Enviar dados a qualquer fornecedor e presumir licença gratuita |
| **R03-R01-D06** | Gates e evidências | R01-G01 a G11 e CORE/SEG/AUT permanecem abertos; cada fase exige critério/evidência e aceite próprio | Encerrar gates automaticamente com a síntese |
| **R03-R01-D07** | Próxima especificação | Priorizar **contratos técnicos bilaterais do MVP CRM + matriz de tecnologia/custo** somente documental, sem selecionar stack no ato | Pular contratos e iniciar instalação/integração |
| **R03-R01-D08** | Liberação operacional | Código, SQL/ALLOW, testes reais, credenciais, compras, VPS/n8n/Cloudflare e deploy exigem autorizações explícitas futuras | Tratar aprovação desta revisão como ordem de implementação |

**R03-R01-D01 a D08: TODAS ABERTAS** para ratificação **documental preliminar** do responsável. Nenhuma delas autoriza iniciar desenvolvimento ou fechar gates técnicos.

## 10. Checklist e próximos marcos

- [ ] Revisar e ratificar/ajustar R03-R01-D01 a D08.
- [ ] Conferir matrizes de contratos/ACL reais do CRM por responsáveis, **sem modificar o repositório CRM**.
- [ ] Definir contratos e capacidades técnicas específicas de AIA para o piloto, sem criar APIs.
- [ ] Comparar tecnologias de persistência, filas, IAM, segredos e provedores após requisitos e custos.
- [ ] Validar licenciamento comercial do n8n no cenário Imagine, sem pressupor concessão de direitos.
- [ ] Detalhar LGPD, tratamento/retenção de dados e provedores, com revisores autorizados.
- [ ] Planejar testes sintéticos, evidências, critérios eliminatórios e responsáveis sem executá-los.
- [ ] Manter todos **R01-G01 a G11 ABERTOS**, em particular G01/G02/G03/G05/G08/G09/G10/G11.
- [ ] Manter todos os gates **CORE/SEG/AUT** e demais decisões próprias do CRM abertos.
- [ ] Não implementar código, SQL, ALLOW, APIs, credenciais, contratações, infraestrutura, testes reais, mudanças em outros repositórios ou deploy.

**Próximo bloco documental candidato:** `AIA-R04/S01 — Contratos Técnicos Mínimos do MVP CRM: Operações, Campos, Erros e Confirmações` **OU** `AIA-R03/R02 — Comparativo Técnico e Matriz de Custo de Stack`. A seleção desta sequência precisa ser registrada sem confundir planejamento com homologação. 

**Preservação:** todos os repositórios e PRs de CRM, Portal, Sites, Performance AI e Universidade permanecem intocados, assim como VPS, Cloudflare, n8n, contas, credenciais e servidores.
