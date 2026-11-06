# AIA-R03/R02 — Comparativo de Stack, Custos e Critérios para Fast-Track Técnico Isolado | Proposta documental v0.1

> **Situação:** proposta **DOCUMENTAL PRELIMINAR**, decisões **R03-R02-D01 a D10 ABERTAS** para aprovação.  
> **Data de referência:** 2026-11-06. **Repositório:** `rafhael100/imagine-ai-agents`.  
> **Precedência:** AIA-01–AIA-12 aprovados funcionalmente; AIA-R01, AIA-R02, AIA-R03/S01–S05, R03/R01 e AIA-R04/S01–S02 aprovados **somente documentalmente**.  
> **Gates:** R01-G01–G11 **TODOS ABERTOS**; CORE/SEG/AUT e demais gates próprios do CRM permanecem **abertos**.  
> **Escopo negativo obrigatório:** este comparativo **não escolhe stack final, não autoriza código ou testes, não homologa contrato/fornecedor/licença, não cria credenciais/API/SQL/ALLOW e não provisiona servidores ou faz deploy**. Nenhuma alteração em repositórios de CRM, Portal, Sites, Performance AI ou Universidade.

## 1. Objetivo e critério de sucesso documental

Responder **como poderemos iniciar código isolado de baixo risco** quando houver autorização específica, sem pressupor que os gates de segurança do produto ou do CRM tenham sido fechados. O plano deve ser reproduzível, econômico, reversível e alinhado aos contratos CRM→AIA→CRM.

**Definições distintas:**
1. **Aprovação funcional/documental:** define comportamento desejado, não prova existência.
2. **Preferência de stack:** orienta comparação e proposta de tecnologia, não autoriza instalação.
3. **Autorização de fast-track:** documento futuro que delimitará arquivos, branch, escopo, atividades, testes permitidos, responsáveis e bloqueios; **ainda não existe**.
4. **Homologação de gates:** comprovação de controles com evidências e aceite do responsável competente, separada por gate e produto.
5. **Deploy:** decisão formal ainda posterior; **não autorizado**, mesmo após início de código isolado.

A meta do primeiro código futuro é criar uma **estrutura verificável em ambiente isolado com dados sintéticos e contratos mockados**, sem acessar contas, serviços reais ou recursos do CRM.

## 2. Características obrigatórias da solução antes da escolha da tecnologia

| Requisito | Contrato não negociável no desenho | Referência |
| --- | --- | --- |
| Separação de produto | AIA tem deploy, dados técnicos e ciclo de vida separados do CRM/Portal | R03/S01 e R01-G08/G10 |
| Multi-tenant | Isolamento por serviço, produto, tenant, principal, recurso e finalidade, inclusive filas/logs | R02/S01–S04 e G01/G02/G05 |
| Agentes e ferramentas | Catálogo e versões com ferramentas explicitamente permitidas, sem SQL nem superadmin | AIA-01/04/12 |
| Execução recuperável | Run/step/intenção/aprovação/tentativa/efeito/consumo; estados, idempotência e conciliação | R02/R03 e R03/S02 |
| Dados e privacidade | Contexto mínimo, segregação, prazo por classe/finalidade, auditabilidade sem logs privados indiscriminados | R02/R04 e G05 |
| Confiabilidade de efeitos | Somente CRM confirma gravação ou criação de tarefa; sugestão de IA não é mutação | R04/S01–S02 e G03/G08 |
| Cobrança e IA | Uso técnico AIA distinto de crédito, plano e faturamento CRM; provedores sob homologação | R03/S04 e G06/G09 |
| Automação do cliente | Motor nativo no CRM; n8n, se aplicável, só em orquestrações internas homologadas da Imagine | AIA-R02 e R03/S03/S04 |
| Ambientes | Desenvolvimento, homologação e produção com credenciais/dados separados | R03/S01/S05 |
| Entrega controlada | PR/branch isolados e review; sem merge/deploy automático | R03/S05 e G07/G11 |

## 3. Alternativas de stack de aplicação — comparação qualitativa

| Alternativa | Pontos favoráveis | Riscos/tradeoffs | Julgamento para avaliação |
| --- | --- | --- | --- |
| **ST-A — Laravel/PHP + PostgreSQL + filas do framework**, serviço AIA isolado | Alinhamento com o backend CRM já planejado em Laravel/PostgreSQL; equipe/processos potencialmente reutilizáveis; suporte a modelos relacionais e workers | Ferramentas de agentes/SDKs variam por linguagem, longas execuções exigem desenho cuidadoso; compartilhar stack não justifica compartilhar banco | **Candidato inicial de simplicidade operacional**, sujeito a comparação de SDK, segurança, concorrência e requisitos |
| **ST-B — TypeScript/Node.js + PostgreSQL + workers/filas isolados** | Amplo ecossistema de integrações e SDKs de IA; tipagem de contratos; serviço técnico independente | Stack adicional ao CRM, operação e versões independentes, disciplina de isolamento e custo de manutenção | **Candidato igualmente válido**, especialmente se capacidades de integração o justificarem |
| **ST-C — Python/FastAPI + PostgreSQL + execução assíncrona** | Ecossistema robusto de modelos, processamento e ferramentas de IA | Operação de stack adicional e filas/cargas I/O/CPU exigem disciplina; licenças de bibliotecas a verificar | **Candidato técnico**, sobretudo se necessidades de ML forem determinantes |
| ST-D — Integrar toda a execução ao processo principal do CRM | Uma única stack e autenticação do produto | Acoplamento de segurança, disponibilidade, versão, tenants e dados; contaminação de responsabilidades | **Não recomendado** para núcleo central multissistema |
| ST-E — Executar toda a lógica no n8n | Construção visual rápida e integrações prontas | Limites de governança, isolamento, estado/efeito, dependência de licença e regras de automações dos clientes | **Não substituir núcleo AIA nem motor nativo CRM** |

**Recomendação nesta fase:** manter **ST-A, ST-B e ST-C em shortlist**, com ST-A como **hipótese operacional inicial** por aderência ao CRM, **não como stack escolhida**. A decisão técnica real requer verificar experiência da equipe, requisitos dos agentes, APIs/SDKs atuais, manutenção, licenças, isolamento, latência, filas, observabilidade e custo.

## 4. Persistência, fila e componentes opcionais: priorização mínima

| Componente | Recorte mínimo candidato | Alternativas futuras | Decisão pendente |
| --- | --- | --- | --- |
| Armazenamento de run/intent | Modelo relacional durável e separado do CRM | Outros bancos/serviços sob garantias equivalentes | Banco, schema, proteção e custo |
| Fila | Execução assíncrona durável; avaliar fila baseada em banco no começo para reduzir dependências | Redis/broker/serviço gerenciado conforme volume | Tecnologia, concorrência, DLQ e semântica de entrega |
| Workers | Processos com escopo de tenant, limites, timeout, retries e reconciliação | Mais serviços/escala quando houver evidência | Capacidade e isolamento |
| Auditoria/telemetria | IDs correlacionados e eventos mínimos sanitizados | Stack central de observabilidade conforme custo | Política de retenção e acesso |
| Memória/RAG | **Fora do primeiro fast-track básico**; somente contrato/abstração | Índices e armazenamento homologados por finalidade | LGPD/segregação/retenção |
| Provedor IA | Interface mock em primeiro estágio | Adaptadores reais após segurança e contratos | Fornecedor, região, privacidade, custo |
| CRM | Adaptador simulado sem rede/conta real | API oficial após homologação bilateral | Papéis, recurso, alçada, efeitos |
| Segredos | Referências não funcionais no primeiro estágio; nenhuma credencial real | Custodiante seguro escolhido em gate técnico próprio | Emissão, IAM, revogação |
| n8n | **Nenhuma dependência operacional no primeiro código** | Orquestração interna Imagine mediante licenciamento e segurança | G09, contrato e identidade limitada |

**Regra:** menor número de serviços não justifica autorização ampla. Uma fila em banco pode ser candidata econômica; uma fila volátil sem recuperação não atende ao contrato de efeitos sensíveis.

## 5. Matriz de custos sem inventar preços

A análise deve produzir cenário para **desenvolvimento isolado**, **piloto homologado** e **produção mínima**, mas nenhuma tarifa corrente foi verificada nem contratada neste documento.

| Custo | Desenvolvimento isolado (futuro) | Piloto autorizado (futuro) | Produção (futuro) |
| --- | --- | --- | --- |
| VPS/CPU/memória | Reutilização de recursos *somente se capacidade e aprovação comprovadas*, ou instância separada | Dimensionamento por concorrência e limites | Alta disponibilidade, isolamento e SLA conforme risco |
| Banco/filas | Projeto simplificado; serviço separado do CRM | Durabilidade, backup e DLQ com evidência | Escala, recuperação, observabilidade e manutenção |
| Serviços IA | **Zero chamadas pagas no fast-track sugerido**, mocks | Preço por unidade/versão e tetos aprovados | Custo variável por workload, fallback e margem |
| Logs/monitoramento | Telemetria local sintética | Custódia e limites aprovados | Retenção por finalidade, auditoria e incidente |
| Backups | Plano documental e testes apenas se autorizados | RTO/RPO e restauração testados | Recuperação periódica, proteção contra expurgo indevido |
| Domínio/TLS/rede | Nenhum domínio novo obrigatório para mock isolado | Identidade/rede/segredos sob gate | Controle egress, certificados e custos |
| n8n | **Sem uso obrigatório** | Só interno, sob análise de licença | Custos/licença conforme termos realmente aplicáveis |
| Desenvolvimento/revisão | Horas de programação futura, QA, segurança e revisão | Correção/testes de integração | Operação, suporte, incidentes e manutenção |

**Fórmula simbólica para orçamento:** `C_total = C_compute + C_storage + C_queue + C_monitoring + C_backup + C_AI_usage + C_network + C_licenses + C_support`. Separar `C_AI_usage` por tentativas/uso efetivo, sem confundir com créditos comerciais CRM.

**Cenários a cotar em etapa própria:** carga pequena, moderada e alta, definida por *runs/dia, picos concorrentes, duração, tokens por run, índices, retenção, tamanho de payload, regras de retry e disponibilidade*. Não atribuir valores numéricos arbitrários nem afirmar que VPS existente tem capacidade ou custo incremental zero.

## 6. Riscos e dependências de fornecedor/licença

- **n8n:** conferir termos vigentes de uso interno de serviços Imagine, limitações em contexto SaaS, direito de hospedagem/embedding, conectores e suporte. Não presumir gratuidade ou dispensa comercial sem revisão de cenário efetivo.
- **PHP/Laravel, Node/TypeScript, Python e bibliotecas:** revisar versões suportadas, manutenção, licenças transitivas, cadeias de SDK e vulnerabilidades antes de escolha real.
- **PostgreSQL, filas, cache e serviços gerenciados:** comparar durabilidade, semântica transacional, custo, concorrência, backup e segregação por tenant.
- **Leaseweb e VPS do ecossistema:** eventual infraestrutura de desenvolvimento central é candidata, **não disponibilidade/capacidade nem permissão de instalar AIA**; isolar recursos e custos.
- **Cloudflare:** eventual borda e proteção dependem de necessidade, contrato e configuração formal, sem presumir DNS/domínios do cliente.
- **Modelos/provedores de IA:** contratos de uso de dados, treinamento, retenção, subprocessamento e transferência internacional precisam ser aprovados para finalidade e classe.
- **Transparência comercial:** a decisão futura deve registrar cotação, período, moeda, tributos incidentes, proprietário da conta, escalabilidade e gatilhos para gastos adicionais.

## 7. Fast-track técnico **candidato**, exclusivamente para aprovação futura separada

**Escopo positivo sugerido para futura autorização formal:**
1. Criar **branch de desenvolvimento isolada** neste repositório AIA, sem alterar `main` diretamente.
2. Criar estrutura mínima de projeto (pastas, configuração de desenvolvimento sem segredos, README de execução, contratos de interfaces tipadas a partir das decisões aprovadas).
3. Criar modelos de domínio **não acoplados ao CRM real**: execução, intenção, aprovação, tentativa, efeito e consumo como estruturas/estados de simulação.
4. Criar adaptadores **mock/sintéticos**, sem conexões externas e sem endpoints reais do CRM, n8n ou provedores pagos.
5. Definir validação de versões/tenant/identidade **falsa de teste**, sem simular que substitui autenticação/ACL oficiais.
6. **Testes unitários de código puro com fixtures sintéticas somente se forem explicitamente autorizados**; proibir testes funcionais de integrações reais.
7. Documentar saída, lacunas, dependências bloqueadas e evidência de review por PR separado.

**Escopo negativo inegociável:**
- Não acessar API, banco, credenciais, dados, regras de permissão ou saldos do CRM real.
- Não criar `ALLOW`, migrations SQL reais, endpoints autenticados externos, contas técnicas ou bypass de gates.
- Não configurar Cloudflare, Leaseweb/VPS, n8n, domínio ou infraestrutura, mesmo se já existentes em outro projeto.
- Não usar token real de IA, fornecedor pago, egress externo, telemetria para terceiros ou dados pessoais.
- Não aprovar merge automático para `main`, release ou deploy.
- Não alegar que fixtures/mocks provam isolamento, segurança ou aderência LGPD dos serviços reais.

**Não é autorização para execução.** A especificação deve ser convertida em pedido formal com branch, arquivos liberados, responsáveis, modelo de teste, custo máximo, critérios de revisão e data/versão, aguardando decisão explícita do usuário.

## 8. Critérios de entrada e de saída do primeiro ciclo controlado

### Entrada — antes da autorização formal

- Selecionar e registrar **stack mínima candidata para código isolado**, com versões e licenças verificadas.
- Escolher branch, revisores, dono técnico, limites de arquivos e parâmetros de segurança.
- Aprovar expressamente **somente** mocks/schemas conceituais e a política de testes unitários sintéticos; sem integrar CRM.
- Registrar no GitHub as lacunas de contratos R04/S01–S02 que não podem ser representadas como APIs existentes.
- Confirmar ausência de segredos, conexões reais, credenciais e alterações em outros repositórios ou servidores.
- Registrar que os 11 gates R01 e gates CORE/SEG/AUT continuam abertos e não serão inferidos como fechados.

### Saída — evidência esperada, somente depois de trabalho autorizado

- Arquivos gerados em branch permitida, sem alterações fora de escopo.
- Contratos de interfaces mockados/versionados, exemplos sintéticos e revisão em PR.
- Eventuais testes unitários **autorizados** com resultados e limitações explícitas, sem equivalência a homologação real.
- Checklist de não integração, dados privados, despesas e deploy comprovado por diff e revisão.
- Próximo lote proposto separadamente; nenhuma liberação automática para sistemas reais.

## 9. Lacunas que continuam **independentes do fast-track**

| Lacuna | Efeito no plano |
| --- | --- |
| Contratos reais de identidade/ACL com CRM | Bloqueia qualquer integração e teste end-to-end, não necessariamente estruturas mockadas |
| Schema/versionamento final CRM | Bloqueia APIs reais, escrita de tarefas e confirmação oficial |
| Escolha/homologação de dados e provedores | Bloqueia processamento de dados reais e chamadas a IA pagas |
| Licenciamento n8n e demais terceiros | Bloqueia uso comercial real de dependências não autorizadas |
| Banco/fila físicos e backups | Bloqueia implantação real e persistência operacional |
| Segurança, LGPD, auditoria e testes negativos | Bloqueia homologação técnica e produção |
| Regras de planos, créditos e faturamento CRM | Bloqueia integração comercial e cobrança |
| Contrato go/no-go e operação/rollback | Bloqueia rollout, liberação por tenant e deploy |

O desenvolvimento mockado, se vier a ser aprovado, **não elimina ou homologa nenhuma lacuna**. Repositório CRM/Portal/Performance/Universidade permanece inalterado.

## 10. Riscos de acelerar sem controle

| Risco | Proteção exigida |
| --- | --- |
| Criar SDK/adaptador antes da API real e tratá-lo como contrato definitivo | Documentar como mock e exigir aceite bilateral posterior |
| Compartilhar banco AIA e CRM por simplicidade | Separar serviço, custódia, tenant e responsabilidades |
| Liberar worker com credencial global para “destravar” | Nenhuma credencial real antes dos gates G01/G02/G08/G10 |
| Contabilizar tokens e créditos comerciais como a mesma operação | Separar métricas AIA e política comercial CRM |
| Aproveitar VPS de outro projeto sem autorização | Solicitar autorização, capacidade/custo, isolamento e segurança separadamente |
| Instalar n8n e supor conformidade comercial | Homologar termos efetivos e fronteira de uso interno |
| Usar dados reais para testes rápidos | Somente fixtures sintéticas; dados reais sob políticas e autorização específicas |
| PR documental interpretado como release | Registro de status por decisão, proteção de main e go/no-go separado |
| Testes unitários de mocks alegados como segurança do CRM | Evidência válida só para domínio testado; gates reais continuam abertos |

## 11. Matriz R03-R02-D01 a D10 — propostas para decisão

| ID | Tema | Alternativa A — recomendada | Alternativa B |
| --- | --- | --- | --- |
| **R03-R02-D01** | Prontidão | Admitir que documentação conceitual permite **planejar** fast-track isolado, sem fechar gates | Declarar sistema tecnicamente homologado |
| **R03-R02-D02** | Serviço | Preservar núcleo AIA separado do CRM, com implantação e dados próprios | Inserir agentes com permissões globais no CRM |
| **R03-R02-D03** | Stack | Shortlist ST-A Laravel, ST-B TypeScript, ST-C Python, com comparação e decisão posterior de versões/licenças | Escolher tecnologia por preferência sem análise |
| **R03-R02-D04** | Persistência e filas | Usar critérios de estado durável, fila recuperável e idempotência para escolha futura, sem instalar componentes | Usar somente workers voláteis e retry cego |
| **R03-R02-D05** | Custos | Orçamento por cenário e componente, cotação/verificação posterior de preços e licenças | Inventar custo zero ou contratar antes de avaliar |
| **R03-R02-D06** | Fast-track | Planejar primeira etapa restrita a estrutura, contratos mockados e fixtures sintéticas; sem integração real | Codificar ferramentas com acesso CRM/n8n/IA em produção imediatamente |
| **R03-R02-D07** | Testes e evidências | Testes unitários sintéticos **somente com autorização futura explícita**, separados de homologação funcional/segurança | Tratar teste mockado como gate técnico encerrado |
| **R03-R02-D08** | Governança | Branch exclusiva, revisão por PR, arquivos permitidos, restrição de main e autorização de cada lote | Mesclar e implantar automaticamente por aprovação documental |
| **R03-R02-D09** | Licença/segurança | n8n e provedores sujeitos a termos reais; evitar segredos, dados reais e APIs pagas no primeiro lote | Assumir n8n livre para qualquer SaaS e usar chaves reais |
| **R03-R02-D10** | Próxima decisão | Criar **proposta formal separada de autorização do fast-track**, sem programar até aceite expresso; manter G01–G11 e CORE/SEG/AUT abertos | Interpretar este PR como autorização de código/SQL/deploy |

**R03-R02-D01 a D10: ABERTAS** para aprovação **documental preliminar** do responsável. Esta decisão não escolhe stack final, não autoriza fast-track, não fecha gates, não homologa licença nem compromete despesas.

## 12. Dependências e passos seguintes

- [ ] Revisar/aprovar R03-R02-D01 a D10 como diretrizes documentais preliminares.
- [ ] Validar comparativo real de SDKs/versões de Laravel, Node/TypeScript, Python, PostgreSQL, filas e respectivos termos antes de escolha.
- [ ] Definir escopo, branch, dono, arquivos e permissões para uma **proposta futura formal de fast-track isolado**.
- [ ] Resolver questões de API/ACL final do CRM e contrato de efeitos apenas quando os responsáveis homologarem bilateralmente.
- [ ] Verificar termos atuais n8n/IA, fornecedor, retenção, transferência, preços e infraestrutura antes de custos e integrações.
- [ ] Confirmar cronograma/documentos de privacidade, dados, observabilidade, quota/planos e testes negativos.
- [ ] Preservar R01-G01 a G11 **TODOS ABERTOS**, sem homologação ou execução de testes de segurança.
- [ ] Preservar CORE/SEG/AUT e demais gates próprios do CRM **ABERTOS**; não alterar CRM/Portal/Performance/Universidade.
- [ ] Não criar código, SQL, ALLOW, APIs, credenciais, contas, dados reais, consumo pago, VPS/Cloudflare/n8n ou deploy por esta aprovação.

**Próximo documento sugerido para aprovação posterior:** `AIA-FT01 — Autorização Controlada de Desenvolvimento Mockado em Branch Isolada`, a apresentar como **proposta** e submeter expressamente ao responsável. Não emitir ordem ao Codex sem essa autorização.

**Status vinculante:** documentação ≠ homologação ≠ autorização de programação ≠ implantação.
