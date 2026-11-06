# AIA-R03/S05 — Implantação, Homologação, Versionamento e Rollback | Proposta documental v0.1

> **Estado:** proposta exclusivamente documental; **R03-S05-D01 a D10 ABERTAS** para decisão do responsável.  
> **Data de referência:** 2026-11-06. **Repositório:** `rafhael100/imagine-ai-agents`.  
> **Precedência:** AIA-01 a AIA-12 aprovados funcionalmente; AIA-R01, AIA-R02/S01–S05, AIA-R02/R01–R06 e AIA-R03/S01–S04 ratificados exclusivamente como diretrizes **documentais preliminares**.  
> **Bloqueios:** R01-G01 a G11 **TODOS ABERTOS**; gates CORE/SEG/AUT e demais bloqueios do CRM inalterados. Não estão autorizados código, ALLOW, SQL, testes reais, credenciais, seleção de fornecedor, contratações, VPS, Cloudflare, n8n, alteração de outros repositórios, release real ou deploy.

## 1. Objetivo e alcance

Definir os **critérios de planejamento**, ainda não operacionalizados, para implantação segura do núcleo central de agentes de IA Imagine. Diferenciar claramente:

1. **Planejamento documental**: definição de requisitos, alternativas, responsabilidades e riscos; único escopo desta revisão.
2. **Homologação técnica**: critérios e evidências reais por gate, incluindo contratos bilaterais com o CRM; NÃO iniciada.
3. **Autorização de desenvolvimento**: decisão futura, explícita, com escopo, branch, orçamento, responsáveis, limites e gates; NÃO concedida.
4. **Autorização de testes reais**: decisão futura, específica, dependente de ambiente e dados autorizados; NÃO concedida.
5. **Autorização de produção/deploy**: decisão futura, separada, após evidências, go/no-go, contingência e operação; NÃO concedida.

A aprovação documental deste PR não implica qualquer das autorizações 2–5. O piloto proposto permanece **CRM → AIA → CRM**, com leitura sob ACL, resumos/classificações sugeridos e escrita de tarefa somente se o CRM efetivamente autorizar e confirmar o efeito. Portal, Sites, Performance AI e Universidade continuam fora do recorte inicial e preservam contratos independentes.

## 2. Dependências e relações de responsabilidade

| Dependência | Produto responsável pela autoridade | Critério antes de futuro go/no-go |
| --- | --- | --- |
| Identidade, vínculo de tenant, delegação e revogação | CRM para principal/recurso; AIA para serviço/execução | Contratos R02/S01–S03/R02 e R03/S03 tecnicamente homologados |
| Autorização do lead, atendimento, tarefa e campos | Backend CRM | Matrizes oficiais de papéis, escopos e casos negativos aprovados no CRM |
| Estados, idempotência, aprovação e efeito | AIA + CRM, cada qual em sua fonte de verdade | Evidência de concorrência, timeout, replay, reconciliação e fonte de confirmação |
| Dados/privacidade/derivados | Donos dos dados e custodiantes designados | Fluxo/classificação, base legal, retenção, provedores, expurgo e incidentes validados |
| Cotas, créditos comerciais e efeito de cobrança | CRM | Contrato de conciliação com custos técnicos AIA, sem dupla cobrança |
| Arquitetura/topologia/segredos/rede | Responsáveis AIA/infraestrutura/segurança | Seleção técnica aprovada, isolamento, contingência e operação |
| Modelos/provedores/n8n | Donos técnicos e jurídico/comercial competentes | Licenciamento, condições contratuais, finalidade, custo e região efetivamente verificados |
| Testes e evidências | QA, segurança, produtos e responsáveis a designar | Critérios R02/R05, casos sintéticos executados somente com autorização e resultados auditáveis |
| Escopo/ordem de implantação | Direção de Produto e operações | Aceite de MVP, orçamento e autorização explícita de release e rollback |

Nenhuma linha da matriz nomeia uma pessoa automaticamente, aprova sua alçada, modifica PRs do CRM ou prova que os testes foram realizados.

## 3. Sequência proposta de gates (dependências, não fechamento)

| Etapa lógica | Entrada exigida antes de execução futura | Saída/documento esperado | Condição atual |
| --- | --- | --- | --- |
| P0 — Documento | S01–S04 da arquitetura, R02 e AIA-R01 ratificados preliminarmente | Matriz de decisões e gaps | **Apenas documentação**, P0 em avaliação neste recorte |
| P1 — Contratos técnicos | Decisões bilaterais CRM/AIA sobre schemas, permissões, dados, custos e efeitos | Versões e critérios técnicos submetidos para homologação | **PENDENTE** |
| P2 — Fornecedores e arquitetura | Estudo atualizado de stack, licenças, segurança, orçamento e operação | Decisão explícita de tecnologia/custo/risco | **PENDENTE** |
| P3 — Implementação controlada | Escopo aprovado de código, responsável, gates aplicáveis e branch própria | Código e testes autorizados sob política | **BLOQUEADO** |
| P4 — Validação em laboratório | Ambiente isolado autorizado, casos sintéticos aprovados, rastreabilidade | Evidências observadas, desvios, correções e retestes | **BLOQUEADO** |
| P5 — Aceite bilateral | Gates AIA e CRM necessários efetivamente homologados; risco residual tratado | Decisão formal de go/no-go do piloto | **BLOQUEADO** |
| P6 — Implantação limitada | Autorização de deploy, monitoramento, rollback, suporte, custo e incidentes | Rollout controlado com evidência de efeito e reversibilidade | **BLOQUEADO** |
| P7 — Expansão | Aprendizado do piloto, contratos de produto, privacidade e novos gates | Proposta separada para cada outro produto | **FORA DE ESCOPO** |

A sequência é proposta para planejar dependências; os nomes P0–P7 **não são gates homologados** nem substituem R01-G01 a G11.

## 4. Matriz de verificação por gate antes de eventual release

| Gate | Critério mínimo documental para liberação futura | Situação |
| --- | --- | --- |
| **R01-G01** | Identidade entre produtos, vínculo tenant, validade/revogação e negação com evidências | **ABERTO** |
| **R01-G02** | Papéis e ACL reais do CRM por recurso/campo, validação final e testes negativos | **ABERTO** |
| **R01-G03** | Run/step/intenção, idempotência, concorrência, timeout e reconciliação evidenciadas | **ABERTO** |
| **R01-G04** | Alçadas e aprovações por ação material/versão/prazo, revalidação e revogação | **ABERTO** |
| **R01-G05** | Inventário de dados, LGPD, retenção/expurgo, modelos, provedores e backups | **ABERTO** |
| **R01-G06** | Métricas de consumo/uso técnico, quota e créditos comerciais CRM conciliados | **ABERTO** |
| **R01-G07** | Casos sintéticos, critérios eliminatórios, evidências, reprovação e reteste com responsáveis | **ABERTO** |
| **R01-G08** | Contratos CRM↔AIA, schemas, mudanças, callbacks, erros, reversão e aceite bilateral | **ABERTO** |
| **R01-G09** | Revisão jurídica/comercial efetiva do n8n e demais provedores/licenças | **ABERTO** |
| **R01-G10** | Stack, rede, segredos, isolamento, logs, filas, backups, RTO/RPO e resposta a incidentes | **ABERTO** |
| **R01-G11** | MVP, orçamento, plano de release, responsabilidades e autorização formal por etapa | **ABERTO** |

**Proibição de atalho:** a aprovação documental de cada requisito, PR ou issue não é evidência de implementação, conformidade ou aceitação. Cada gate só poderá mudar de estado mediante decisão específica e evidências futuras válidas. Gates CORE/SEG/AUT do CRM **não são fechados pelo repositório AIA**.

## 5. Versionamento, compatibilidade e promoção de artefatos

O planejamento de versão deve distinguir:
- **Documentos/requisitos**, com IDs, versões, status de aprovação, precedência e alterações;
- **Contrato bilateral CRM/AIA**, incluindo schemas, API, eventos, autorização, erros e compatibilidade;
- **Agente e prompt**, incluindo versão de catálogo, ferramentas permitidas, riscos e avaliação;
- **Modelo e provedor**, com capacidade, termos de tratamento, versão, preços/limites e fallback homologados;
- **Infraestrutura**, com configuração, secrets refs e mudança aprovada — sem incluir credenciais em Git;
- **Conjunto de evidências**, ligado às exatas versões de código/contrato/modelo/política;
- **Release**, com identificador imutável, responsável, ticket/PR, autorização e reversão compatível.

**Regra candidata:** alterações materiais em ACL, política de aprovação, API/contrato, modelo, ferramenta, fluxo de dados ou retenção invalidam os casos de aceite afetados e exigem revisão de risco e regressão correspondente. Não promover versão por silêncio ou sem evidência.

**Compatibilidade:** consumidores e produtores devem tolerar versões previstas sem aceitar schema desconhecido permissivamente; eventuais eventos atrasados não podem reabrir autorização revogada nem criar efeitos duplicados.

## 6. Estratégias de rollout futuras (comparação)

| Modelo | Uso possível | Risco e condição |
| --- | --- | --- |
| **I-A: habilitação por produto/tenant e capacidade** (diretriz recomendada) | Liberar progressivamente somente recursos e tenants expressamente elegíveis após homologação | Requer feature flags/política real sob contrato, ACL CRM, rastreabilidade e rollback |
| I-B: rollout global para todos os clientes | Velocidade operacional aparente | Não recomendado antes de evidência de isolamento, limites e suporte |
| I-C: aprovação manual por solicitação do cliente | Controle pontual | Pode ser operacionalmente pesado; não dispensa verificação técnica |
| I-D: canary técnico/ambiente de homologação | Reduz impacto de regressão quando configurado corretamente | Dados e ambiente devem ser autorizados; não equivale a liberação comercial |

O piloto **não será habilitado automaticamente** por aprovação deste documento. Qualquer futura liberação exige revisão de escopos e contratos do CRM, consentimentos/autorizações pertinentes e decisão explícita sobre tenants habilitados.

## 7. Rollback, cancelamento e efeitos confirmados

Separar quatro ações:
1. **Rollback de release AIA:** voltar versão/configuração de componentes com contrato de compatibilidade; não é autorização para reverter banco CRM.
2. **Desabilitar uma capacidade/agente:** impedir novas runs ou ferramentas, conservando prova mínima legítima.
3. **Cancelar run em andamento:** bloquear novos passos e revalidar checkpoints; **não apagar efeitos confirmados**.
4. **Compensar efeito de negócio:** operação distinta do produto dono (CRM para tarefa, resumo etc.), com permissão, política, idempotência e auditoria próprias.

Rollback não pode:
- restaurar segredos vencidos, aprovações revogadas ou dados pessoais já excluídos;
- disparar novamente tarefas já criadas por interpretação errada de timeout;
- reabrir escopo de tenant ou privilégio não autorizado;
- reutilizar armazenamento/índices antigos entre empresas;
- fazer downgrade de contrato que aceite permissões mais amplas;
- apagar evidências legítimas de incidente sem base de retenção e política competente.

A escolha técnica de migrações compatíveis, blue/green, snapshots, versionamento de filas e downgrade seguro depende de análise futura; **não executar migração nem rollback real** nesta fase.

## 8. Critérios de go/no-go e incidentes

Condições propostas de **NO-GO**:
- falha conhecida de isolamento entre empresas, exposição de dados ou bypass de autorização/alçada;
- contrato de API/segregação CRM/AIA não homologado bilateralmente;
- idempotência/efeito indeterminado sem prova de reconciliação de ações sensíveis;
- tratamento de dados por modelo/provedor sem termos e finalidade homologados;
- licença/obrigações comerciais n8n/provedor não analisadas quando a dependência for usada;
- ausência de monitoramento, credenciais seguras, backup/recuperação e plano de incidente compatíveis;
- ausência de orçamento, responsável, go/no-go formal, critérios de teste ou evidência;
- CORE/SEG/AUT do CRM ou algum gate AIA indispensável ao escopo ainda aberto.

A falta de prova equivale a **PENDENTE**, não a aprovação tácita. Nenhuma decisão comercial deve transformar violação crítica em risco residual aceitável por conveniência.

**Resposta a incidente futura:** detectar, conter, preservar evidências mínimas, avaliar dados e tenants afetáveis, suspender capacidades, acionar responsáveis, comunicar quando exigido, corrigir, testar novamente e decidir reativação. Os tempos, canais, SLA e políticas concretas exigem documento e homologação próprios.

## 9. Observabilidade mínima de uma liberação futura

Painel/relatório operacional candidato:
- versão do agente, modelo, ferramenta, contrato, release, política e ambiente;
- runs recebidas, ativas, bloqueadas, concluídas, indeterminadas e canceladas por produto/tenant;
- profundidade de filas, latência, falhas, DLQ/quarentena, limites e reconciliação;
- tentativas e efeitos confirmados separadamente;
- consumo técnico de IA versus crédito comercial do CRM;
- alertas de custo, mudança de permissão, expiração de aprovação e isolamento;
- logs/auditoria sanitizados, com acesso restrito e registro de consultas;
- integridade de backups, status de restore e revogações aplicadas;
- trilha do go/no-go, habilitações de tenant, incidentes e reversões.

Números de alerta/limiares, páginas, tecnologias, SLA/RTO/RPO e gatilhos de rollback **não foram escolhidos**. Operação não poderá consultar histórico privado do CRM por simples acesso ao painel AIA.

## 10. Casos documentais de reversão e aceitação futura

| Cenário sintético | Resultado esperado |
| --- | --- |
| Release novo altera a validação de ACL de lead | Bloquear rollout e exigir testes negativos/aceite bilateral CRM |
| Modelo substituto não está homologado para classe de dados | Não enviar dados; usar falha segura |
| Canary de tenant A recebe callback do B | Negar, auditar e impedir contaminação de estados |
| Rollback de worker após tarefa CRM confirmada | Não recriar tarefa, reconciliar referência de efeito |
| Aprovação expira enquanto fila é processada | Bloquear efeito, sem execução por silêncio |
| Restore traz embedding de registro apagado | Reconciliar exclusão antes de disponibilização |
| Nova versão do contrato envia campo não autorizado | Negar, proteger dado e invalidar aceite afetado |
| Plano CRM é suspenso durante rollout | Revalidar e bloquear novas capacidades sujeitas a elegibilidade |
| Métrica de custo duplicada por retry/callback | Conciliar uso técnico sem dupla cobrança comercial |
| Um serviço n8n tenta executar tool CRM global | Bloquear por identidade/escopo; não aceitar bypass |
| Ausência de backup/restauração comprovada | Não conceder go-live para efeito crítico |
| Falha crítica S0 não corrigida | NO-GO, sem exceção informal |
| Rollback requer downgrade de contrato incompatível | Suspender rollout, usar plano específico, não forçar downgrade |
| Desativação de agente com runs pendentes | Cancelar/suspender próximas etapas com auditoria e revalidação |

Estes são **casos de planejamento**; não foram realizados testes, deploys, rollback nem incidentes simulados reais.

## 11. Matriz R03-S05-D01 a D10 — propostas para decisão

| ID | Tema | Alternativa A — recomendada | Alternativa B |
| --- | --- | --- | --- |
| R03-S05-D01 | Marcos e autorização | Separar planejamento documental, homologação técnica, código, testes reais e deploy em decisões específicas | Aprovação do plano libera desenvolvimento e produção |
| R03-S05-D02 | Gates interproduto | Exigir R01-G01 a G11 e CORE/SEG/AUT aplicáveis homologados com evidências e donos de produto | AIA auto-homologa permissão e gates do CRM |
| R03-S05-D03 | Versionamento | Vincular documentos, contratos, agentes, prompts, modelos, políticas, ferramentas, testes e releases a versões auditáveis | Atualizar componentes livremente sem avaliação de impacto |
| R03-S05-D04 | Promoção de ambientes | Planejar dev/homologação/produção separados, sem copiar dados/segredos reais e sem promover por silêncio | Um ambiente misto com deploy automático |
| R03-S05-D05 | Rollout | Futura habilitação progressiva por produto, tenant e capacidade elegíveis, sob contrato e autorização formal | Liberar todos os tenants de uma vez sem prova de segurança |
| R03-S05-D06 | Rollback e efeitos | Reverter release e suspender passos sem recriar/apagar efeitos CRM; compensar somente por operação autorizada | Reverter versão equivale a desfazer ações externas |
| R03-S05-D07 | Go/no-go | Riscos críticos, falhas de segregação, ACL, licenças, privacidade ou evidências faltantes são bloqueantes | Lançar com falhas críticas conhecidas |
| R03-S05-D08 | Monitoramento/incidentes | Planejar observabilidade, quotas, alertas, backups, recuperação, responsáveis e contenção antes do go-live | Implantar sem capacidade de detectar e recuperar falhas |
| R03-S05-D09 | Custos e responsáveis | Orçamento, licenças, fornecedores, suporte, alçadas e controle de mudanças aprovados antes de gastos | Contratar/consumir APIs sem aprovação |
| R03-S05-D10 | Próxima fase | Consolidar a arquitetura em R03/R01 documentalmente, sem código, testes funcionais, credenciais ou deploy | Considerar R03/S05 uma ordem para implementar |

**R03-S05-D01 a D10: ABERTAS para avaliação documental preliminar.** A escolha de critérios não fecha gates, homologa stack, define preço ou autoriza operação real.

## 12. Pendências, governança e próximo recorte

- [ ] Ratificar ou corrigir R03-S05-D01 a D10.
- [ ] Definir responsáveis por aprovação, QA, segurança, operação, privacidade, custos, integração CRM e go/no-go sob decisão separada.
- [ ] Confrontar contratos AIA/CRM, matriz oficial de ACL, alçadas e critérios de efeitos, mantendo gates CORE/SEG/AUT abertos.
- [ ] Definir versionamento real de APIs/eventos/agentes/modelos e procedimentos de compatibilidade quando a tecnologia for selecionada.
- [ ] Avaliar opções de rollout, migração, restauração e rollback sem ações reais.
- [ ] Homologar riscos, critérios e evidências por R01-G01 a G11 individualmente, sem fechamento automático.
- [ ] Avaliar fornecedores, licenças n8n/IA, custos, regiões, RTO/RPO, escalabilidade e responsáveis pelo orçamento.
- [ ] Preservar **R01-G01 a G11 TODOS ABERTOS** e **CORE/SEG/AUT do CRM ABERTOS**.
- [ ] Manter código, SQL, ALLOW, credenciais, dados reais, testes funcionais, compras, alterações de VPS/Cloudflare/n8n e deploy bloqueados.

**Próximo bloco documental recomendado:** `AIA-R03/R01 — Revisão Consolidada da Arquitetura Técnica, Decisões Pendentes e Gates`, reunindo R03/S01 a S05 para avaliar consistência e preparar uma futura matriz de homologação, **sem autorização automática de implementação**.

**Imutáveis:** não alterar `main`, PRs ou gates de CRM, Portal, Performance AI e Universidade; não executar software, scripts, infraestrutura, integrações reais ou consumo pago.
