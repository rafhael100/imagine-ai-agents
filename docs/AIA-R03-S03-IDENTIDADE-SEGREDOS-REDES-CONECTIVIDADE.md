# AIA-R03/S03 — Identidade, Segredos, Redes e Conectividade Técnica | Proposta documental v0.1

> **Situação:** diretrizes **R03-S03-D01 a D10 APROVADAS DOCUMENTALMENTE em 2026-11-06** pelo responsável, exclusivamente como orientações preliminares; os gates R01-G01 a G11 e CORE/SEG/AUT do CRM permanecem abertos.  
> **Data:** 2026-11-06. **Projeto:** Imagine AI Agents.  
> **Precedência:** AIA-01 a AIA-12 aprovados funcionalmente; AIA-R01, AIA-R02/S01–S05, AIA-R02/R01–R06 e AIA-R03/S01–S02 aprovados exclusivamente como **diretrizes documentais preliminares**.  
> **Gates:** R01-G01 a G11 TODOS ABERTOS, especialmente G01, G02, G05, G08, G09, G10 e G11. CORE/SEG/AUT e demais gates próprios do CRM também abertos.  
> **Proibições:** não emitir chaves ou tokens, não escolher definitivamente padrão/provedor/região, não alterar firewall/rede/DNS, não acessar dados reais, não modificar repositórios de outros produtos, não executar código, ALLOW, SQL, testes funcionais, instalações, contratos, despesas, VPS ou deploy.

## 1. Objetivo e escopo

Comparar formas técnicas **candidatas**, ainda não selecionadas, para proteger o trajeto **CRM → núcleo AIA → ferramentas CRM**, as chamadas a fornecedores de IA homologados e os caminhos futuros de Portal, Sites, Performance AI e Universidade. O foco é impedir falsificação de identidade, troca indevida de tenant, vazamento de credenciais, acesso arbitrário à rede e contorno da autorização do produto.

**Princípio de autoridade:** CRM autentica seus usuários e autoriza seus leads, atendimentos, campos, tarefas, contratos, planos e motor nativo de automações; AIA autentica o serviço chamador, verifica seus próprios limites e utiliza ferramentas oficiais; CRM realiza autorização final e confirma qualquer efeito. Um token válido do serviço não representa permissão irrestrita para manipular o CRM.

## 2. Fronteiras de confiança e atores

| Ator | Pode comprovar | Não pode presumir |
| --- | --- | --- |
| Usuário autenticado no CRM | Identidade e direitos definidos pelo CRM | Acesso a todos os leads, anexos ou outras empresas |
| Backend CRM | Organização, operação e recurso autorizados segundo política vigente | Privilégio dentro do núcleo além de contrato homologado |
| Entrada AIA | Emissor, destinatário, ambiente, vínculo de produto/tenant e capacidade de agente | Que ID de organização enviado em prompt/URL seja confiável |
| Worker AIA | Identidade técnica limitada à tarefa e ao contexto | Permissão global do CRM ou de outros produtos |
| Adaptador/ferramenta CRM | Operação tipada, escopo, autorização final do CRM | Mutação por texto livre do LLM ou credencial administrativa |
| Serviço n8n interno Imagine | Fluxos de serviço Imagine se futuramente homologados/licenciados | Ser motor configurável de automações dos clientes ou root de todos os tenants |
| Provedor IA/terceiro | Requisição mínima para finalidade autorizada | Acesso ao banco do CRM, memória global ou direitos ampliados |
| Portal, Sites, Performance, Universidade | Sua própria autoridade e contrato | Equivalência automática de direitos via mesmo e-mail/CNPJ |

O domínio de confiança deve ser estabelecido **entre serviços** e novamente **no recurso do produto**, não apenas por segmentação de rede.

## 3. Modelo lógico de identidade e claims candidatos

Uma chamada futura precisa vincular, conforme contrato, grupos de atributos verificáveis:

- **Serviço:** referência de emissor/serviço, destinatário/audience, produto de origem, ambiente, versão e política de aceitação.
- **Organização:** vínculo CRM↔AIA validado por fonte de verdade autorizada; negar vínculos desconhecidos, revogados ou ambíguos.
- **Principal:** humano ou serviço com escopo próprio, delegação explícita quando agir em nome de um usuário e limite de permissões.
- **Operação:** finalidade, recurso e operação identificados, agente/ferramenta e respectivas versões, plano/cota e eventual aprovação.
- **Temporal:** emissão, início de validade, vencimento, proteção contra replay e estado de revogação.
- **Auditoria:** correlação, intenção idempotente, run, tentativa, confirmação do CRM e decisão de autorização sem conteúdo pessoal excessivo.

**Nunca confiar em claims não verificados** nem tomar o texto gerado pelo LLM como prova de delegação ou tenant. O conjunto/nomes dos atributos é conceitual; não define JWT, JSON, API, claims físicos nem algoritmos de assinatura.

## 4. Alternativas de autenticação serviço-serviço (comparação)

| Candidato | Possíveis benefícios | Fragilidades e cuidados | Estado |
| --- | --- | --- | --- |
| **I-A — identidade forte de serviço com credenciais curtas/rotacionáveis** | Permite vincular emissor, audience, ambiente e expiração | Requer cadeia de confiança, custódia e política de revogação | Diretriz de qualidade, tecnologia ainda aberta |
| I-B — credenciais estáticas de longa duração por serviço | Integração simples para MVP | Maior impacto de vazamento; rotação e revogação difíceis | Não preferencial, dependeria de avaliação |
| I-C — autenticação mútua de canal associada a identidade de serviço | Pode autenticar endpoints e limitar personificação | Certificados, distribuição e renovação requerem operação cuidadosa | Comparar conforme topologia |
| I-D — delegação explícita por autorização de usuário/serviço | Permite representar titular e escopo da ação | Não substitui ACL de recurso nem direitos atuais no CRM | Requisito de desenho, mecanismo aberto |
| I-E — confiar só no IP interno/rede privada | Simplicidade aparente | Identidade por rede não é autorização nem proteção suficiente | Incompatível como único controle |

OAuth2/OIDC, credenciais assinadas, certificados e gateway de identidade são **opções a comparar**, não ferramentas escolhidas ou habilitadas. A confiança deve contemplar ida e volta, callbacks e versões.

## 5. Custódia de segredos e ciclo de vida

Os próximos contratos técnicos devem prever:
1. Segredos e chaves fora de código, GitHub, prompts, logs, histórico de agente e conteúdo de tickets.
2. Acesso a credenciais por serviço, função, ambiente e finalidade, com privilégio mínimo e trilha.
3. Segredos separados para desenvolvimento, homologação e produção, sem cópia de credenciais reais para testes.
4. Emissão, ativação, rotação, expiração, revogação emergencial, recuperação e descarte com responsabilidades claras.
5. Proteção da credencial em repouso e em trânsito; política de backup e restauração que não reative segredo revogado.
6. Impossibilidade de o agente solicitar ou exibir arbitrariamente uma credencial utilizada internamente por ferramenta.
7. Proteção contra vazamento em erros, dumps, stack traces, capturas de observabilidade, RAG e outputs de modelo.
8. Auditoria de acesso administrativo e de emergência, sem disponibilizar segredos aos próprios registros.
9. Mitigação de revogação durante run/fila: revalidar antes de cada ferramenta sensível e na retomada.
10. Mecanismo de contingência para comprometimento de um serviço sem liberar identidade superadmin.

Gerenciador de segredos, KMS, vault, identidade de workload e política de criptografia são decisões **posteriores**. Nenhuma credencial real será criada neste recorte.

## 6. Rede, entrada e saída: desenho de confiança

**Entrada (ingress):**
- Somente serviços e rotas explicitamente homologados, com autenticação, autorização, limites de tamanho/taxa e validação de contrato.
- Separar identidade de canal de autorização de recurso: chamada válida pode ser negada para determinado lead.
- Proteção contra replay de chamadas e callbacks, versões desconhecidas, parâmetros adulterados e tenant inválido.
- Evitar interfaces administrativas ou ferramentas de execução abertas à Internet sem mecanismo específico.

**Saída (egress):**
- Catálogo restrito de destinos e capacidades, nunca URL arbitrária fornecida por prompt, documento, usuário ou ferramenta não confiável.
- Controle contra SSRF, redirecionamentos para rede interna, metadados de nuvem/host, DNS rebinding e exfiltração indireta.
- Dados pessoais enviados a modelos apenas após classificação, minimização e avaliação dos fornecedores/transferências.
- Destino dos callbacks/webhooks aprovado e autenticado, vinculado ao produto e tenant, com assinatura/identidade e replay controlados.
- Rotas para CRM somente por ferramentas autorizadas; **nenhuma conexão SQL direta ou API global** como conveniência.

**Isolamento:**
- Dividir conceitualmente caminhos de entrada, orquestrador/workers, estado/filas, memória/RAG, telemetria e provedores.
- Aplicar segregação por tenant em mensagens, objetos, filas, cache, índices, armazenamento e backups; separar também ambientes.
- Rede privada, firewall, proxy, service mesh ou zero-trust gateway serão **comparados** adiante, não ativados agora.

## 7. Comunicação CRM → AIA → CRM, inclusive falhas e revogação

Fluxo futuro de referência:
1. CRM valida o usuário ou serviço autorizado, recurso e finalidade antes de pedir uma run.
2. AIA verifica identidade técnica CRM, destinatário, ambiente, tempo, integridade e vínculo de tenant.
3. AIA confirma agente/versão, ferramenta permitida, política, quota e necessidade de dados.
4. Para leitura, a ferramenta CRM consulta apenas campos autorizados mediante nova validação CRM.
5. O modelo devolve resumo/classificação **sugeridos**; não altera lead/atendimento.
6. Para gravação ou tarefa, AIA produz intenção delimitada, obtém aprovação quando exigida e revalida direitos imediatamente antes da ferramenta.
7. Backend CRM verifica operação/recurso/idempotência sob sua autoridade e devolve efeito confirmado ou resultado indeterminado.
8. Estado incerto é conciliado com fonte oficial CRM antes de qualquer retry; revogações interrompem ações futuras.
9. AIA e CRM auditam eventos mínimos com escopos de consulta; não expõem conteúdo de outro tenant.

**Resiliência:** se a verificação de identidade, vínculo ou autorização estiver indisponível, bloquear ou aguardar; não passar a confiar apenas em rede/IP, cache vencido ou credencial superadmin.

## 8. n8n e dependências externas

n8n self-hosted é uma alternativa para **orquestrações internas controladas pela Imagine**, sujeita à avaliação de licenciamento, segurança, custos e operação (R01-G09). O cliente do CRM não terá acesso a um construtor n8n para automações personalizadas como parte do núcleo: seu motor configurável permanece **nativo no CRM**.

Um fluxo n8n futuro deverá usar identidade própria de serviço e permissões restritas; não herdar root dos produtos, não armazenar tokens em nós públicos, não enviar dados privados para webhooks arbitrários nem efetuar callbacks sem contrato. A existência de instância n8n em outro projeto **não** equivale a aprovação para conectá-la aqui.

Modelos e provedores externos igualmente exigem revisão de retenção, treinamento, subprocessamento, transferência internacional, acesso técnico, incidentes e custo. Nenhum provedor, plano ou região está homologado.

## 9. Ambientes e caminhos de implantação (sem provisionar)

| Ambiente lógico | Isolamento necessário | Condição nesta proposta |
| --- | --- | --- |
| Desenvolvimento | Dados sintéticos, identidade de teste, credenciais não produtivas e quotas segregadas | Apenas desenho |
| Homologação | Versões imutáveis para critérios de aceite, casos negativos, evidências e acesso autorizado | Não provisionado; testes não executados |
| Produção | Emissores, segredos e recursos exclusivos, operação monitorada, rollback e resposta a incidentes | Bloqueada por gates, orçamento e autorização |

A seleção de região, VPS, endereço de rede, DNS, fornecedor, topologia física, certificado ou protocolo será discutida **após** definir contratos e evidências. Não atribuir à VPS central Leaseweb do ecossistema uma autorização automática de hospedar o núcleo AIA.

## 10. Matriz de riscos, cenários negativos e evidências futuras

| Cenário sintético | Conduta esperada | Gates |
| --- | --- | --- |
| Token/credencial de homologação usado como produção | Rejeitar por ambiente/audience | G01/G10 |
| Identidade de serviço legítima tenta acessar tenant alheio | Negar no AIA e no CRM, sem revelar existência do recurso | G01/G02 |
| Operador perde permissão depois de run enfileirada | Revalidar e impedir ferramenta proibida | G01/G02/G03 |
| Claims antigos usados após revogação | Bloquear novas ações segundo política técnica futura | G01/G03 |
| Worker recebe prompt para revelar variável de ambiente | Tratar como dado não confiável; não divulgar segredo | G01/G05/G10 |
| Callback duplicado/forjado altera status de tarefa | Autenticar, deduplicar e consultar fonte de verdade | G03/G08 |
| Agente tenta chamada a IP interno/metadata endpoint | Restringir egress/destinos; evitar SSRF | G08/G10 |
| RAG contém URL externa para exfiltrar contexto CRM | Não permitir saída não homologada | G05/G10 |
| Serviço n8n tenta usar conta global CRM | Exigir ferramenta/identidade específica e permissão CRM | G02/G09 |
| Log de autorização registra token ou corpo privado | Minimizar, limitar acesso, detectar incidente | G05/G10 |
| Credencial vazada é revogada durante execução | Não aceitar novas ações sem revalidação | G01/G03 |
| Mesmo CNPJ/e-mail em CRM e Portal | Não implicar mapeamento automático de autorização | G01/G02 |
| Recuperar backup reintroduz credencial revogada | Impedir reativação e reconciliar segredo | G05/G10 |
| Provedor IA não homologado recebe dados reais | Bloquear envio; exigir contrato e finalidade | G05/G09 |

Os casos são **exemplos documentais não executados**; não há evidência operacional nem gate fechado.

## 11. Diretrizes R03-S03-D01 a D10 — aprovadas documentalmente

| ID | Tema | Alternativa A — recomendada | Alternativa B |
| --- | --- | --- | --- |
| R03-S03-D01 | Autoridade de identidade | CRM é autoridade do usuário/recurso; AIA verifica identidade limitada de serviço e tenant | Agente utiliza uma conta técnica superadmin para tudo |
| R03-S03-D02 | Confiança entre serviços | Exigir emissor/audience/ambiente verificáveis, integridade, validade, antirreplay e revogação; comparar protocolo depois | Confiar apenas em IP ou segredo global permanente |
| R03-S03-D03 | Delegação e acesso | Delegações explícitas e revalidação por recurso/ferramenta/efeito, com negação por padrão | Herdar permissões genéricas para todo tenant |
| R03-S03-D04 | Ciclo de segredos | Custódia externa a prompts/repositório, privilégio mínimo, rotação, revogação e auditoria | Copiar tokens para código, logs ou fluxos públicos |
| R03-S03-D05 | Ambientes | Separar identidades, credenciais, endpoints e dados entre desenvolvimento, homologação e produção | Reutilizar credenciais de produção nos testes |
| R03-S03-D06 | Redes e egress | Entradas homologadas e destinos de saída permitidos, proteção SSRF/callbacks e exposição mínima | Permitir URL arbitrária e acesso irrestrito à rede |
| R03-S03-D07 | Isolamento | Segregar execução, memória, fila, logs e estado por produto/tenant/ambiente em todas as camadas | Limitar segregação apenas à interface |
| R03-S03-D08 | n8n e conectores | n8n apenas em serviços internos Imagine sujeitos à licença e identidade restrita; CRM mantém motor nativo | Abrir n8n e supercredenciais ao construtor do cliente |
| R03-S03-D09 | Segurança/continuidade | Falha segura de validação, observabilidade minimizada e plano de revogação/incidente antes de produção | Fallback permissivo em falhas de rede/identidade |
| R03-S03-D10 | Fase e escolhas | Avaliar mecanismos, ferramentas, fornecedores e custos em etapa própria, sem chaves ou configurações agora | Emitir credenciais, abrir redes e implantar com esta aprovação |

**R03-S03-D01 a D10: APROVADAS DOCUMENTALMENTE em 2026-11-06 pelo responsável.** Ratificação exclusivamente documental preliminar, sem escolher OAuth2, OIDC, mTLS, PKI, Vault, provedor, IAM, firewall, VPN ou topologia física; sem emitir credenciais, programar, configurar rede, autorizar SQL/ALLOW, testar sistemas reais, fechar gates ou implantar.

## 12. Pendências e próximos detalhamentos

- [x] R03-S03-D01 a D10 aprovadas documentalmente pelo responsável em 2026-11-06.
- [ ] Conciliar identidade, trust boundaries e delegação com papéis/ACL oficiais do CRM, sem mudar CORE/SEG/AUT.
- [ ] Preparar matriz técnica futura de emissores, audiência, ambiente, revogação, TTL e replay sob critérios homologados.
- [ ] Comparar tecnologias de IAM, canal e segredos com custo, operação, auditoria e recuperação, sem emitir chaves.
- [ ] Mapear componentes expostos, egress de modelos, callbacks, logs e controles contra SSRF/exfiltração.
- [ ] Delimitar fronteiras de n8n Imagine vs motor nativo de automações CRM, sujeito a avaliação comercial/jurídica.
- [ ] Preparar cenários sintéticos de autorização, revogação, rede e incidentes para avaliação de AIA-09.
- [ ] Manter **R01-G01 a G11 TODOS ABERTOS**, com ênfase em G01/G02/G05/G08/G09/G10/G11.
- [ ] Manter **CORE/SEG/AUT e outros gates do CRM abertos**, sem ALLOW, SQL ou código.
- [ ] Autorizações expressas separadas para testes reais, credenciais, topologia, compras, servidores e deploy.

**Próximo recorte sugerido:** `AIA-R03/S04 — Modelos, Provedores, Custos, Licenças e Contingência`, mediante proposta independente; nenhuma seleção comercial é ratificada por esta versão.

**Preservações:** repositórios, branches `main`, PRs e gates de CRM, Portal, Performance AI e Universidade inalterados; VPS/Cloudflare/n8n, DNS, redes e segredos não modificados.
