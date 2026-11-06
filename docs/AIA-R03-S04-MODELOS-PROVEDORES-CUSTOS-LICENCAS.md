# AIA-R03/S04 — Modelos, Provedores, Custos, Licenças e Contingência | Proposta documental v0.1

> **Estado:** diretrizes **R03-S04-D01 a D10 APROVADAS DOCUMENTALMENTE em 2026-11-06** pelo responsável, exclusivamente preliminares; R01-G01 a G11 e CORE/SEG/AUT do CRM permanecem abertos.  
> **Referência:** 2026-11-06. **Repositório:** Imagine AI Agents.  
> **Precedência:** AIA-01 a AIA-12 aprovados funcionalmente; AIA-R01, AIA-R02/S01–S05, AIA-R02/R01–R06 e AIA-R03/S01–S03 aprovados **somente documentalmente**.  
> **Gates:** R01-G01 a G11 TODOS ABERTOS, em particular G05 (dados), G06 (consumo/créditos), G07 (laboratório), G09 (licenças), G10 (arquitetura/operação) e G11 (MVP/orçamento). CORE/SEG/AUT e demais gates do CRM também abertos.  
> **Sem autorização:** este documento não escolhe ou contrata modelo/provedor/serviço/licença, não fixa preço, cota, região ou SLA, não gera custos, credenciais, contas, código, SQL, ALLOW, integração real, testes funcionais ou deploy.

## 1. Finalidade e premissas

Definir uma **metodologia de seleção técnica, jurídica e financeira** para os modelos de IA, provedores, mecanismos de contingência, limites de consumo e infraestrutura do núcleo central administrado pela Imagine. **O cliente do CRM não configura livremente agentes n8n;** automações configuráveis permanecem no **motor nativo do CRM**, enquanto o núcleo de agentes e quaisquer orquestrações internas são controlados pela Imagine, sujeitos a licença aplicável.

O piloto continua restrito a **CRM → AIA → CRM**, com leitura de leads/atendimentos autorizados, resumo e classificação **sugeridos** e eventual tarefa/registro de resumo mediante permissão, confirmação e idempotência próprias do CRM. Não há seleção real de fornecedor por estar descrito como alternativa.

## 2. Responsabilidades e contratos

| Domínio | Responsável pela decisão final | Contrato a detalhar |
| --- | --- | --- |
| Elegibilidade da empresa, plano e créditos | CRM | Limites comerciais, suspensão, reservas, débitos e conciliação |
| Catálogo e versão de agentes | Imagine AI Agents | Capacidade, finalidade, ferramentas aprovadas, testes e publicação |
| Seleção e roteamento do modelo | Núcleo AIA, sob regras homologadas | Critérios de capacidade, custo, latência, privacidade e disponibilidade |
| Uso técnico do provedor | Núcleo AIA e contrato do fornecedor | Métricas por tentativa, unidades e tratamento de erros |
| Conteúdo permitido e base legal | Produto de origem e responsáveis de privacidade | Minimização, classificação, proibições, região, retenção e suboperadores |
| Execução de ferramentas CRM | Backend CRM | Revalidação de acesso, alçada e confirmação de efeito |
| Licença de n8n e dependências | Responsáveis jurídico/comercial/técnico Imagine | Termos efetivos da modalidade de uso, limites e custos |
| Infraestrutura e segurança | Arquitetura/operação Imagine | Capacidade, segregação, segredos, observabilidade, backup, DR |
| Aprovação financeira | Direção de Produto e responsáveis autorizados | Cenário de gastos, margens, limites e orçamento por fase |

AIA não deve recalcular ou alterar arbitrariamente o saldo comercial do CRM em nome da empresa cliente.

## 3. Abstração de modelos e capacidades

Definir futuramente um **catálogo controlado de capacidades**, não uma enumeração livre de marcas:
- tarefas: classificação, sumarização, extração estruturada, embeddings, busca e, se aprovado em outro recorte, multimodal;
- exigências por capacidade: idiomas, qualidade, janela de contexto, limites de entrada/saída, formatos, chamadas de ferramentas, segurança e versionamento;
- compatibilidade: um provedor pode não oferecer as mesmas garantias de JSON, ferramentas, latência, política de dados ou embeddings;
- contrato de resultados: versão de modelo, agente e prompt, origem de conteúdo, limites de confiança e resultado de validação;
- política de publicação: modelos novos e atualizações materiais exigem revisão e critérios sintéticos antes de produção;
- política de roteamento: utilizar somente modelos, regiões e ferramentas **explicitamente homologados** para a classe de dados e finalidade.

**Não aprovar** acesso direto de usuário/LLM a uma lista irrestrita de endpoints ou modelos por conveniência. Conteúdo recuperado de documentos/terceiros é dado não confiável, não instrução operacional.

## 4. Alternativas de execução e roteamento — sem escolha definitiva

| Alternativa | Potenciais vantagens | Riscos/tradeoffs | Condição |
| --- | --- | --- | --- |
| **M-A: abstração AIA com catálogo homologado e roteamento por política** (recomendada como diretriz) | Permite substituir modelos com governança e observabilidade | Exige contratos normalizados e análise das diferenças reais de fornecedores | Seleção de implementação aberta |
| M-B: integrar somente um provedor | Simplicidade operacional inicial | Concentração de indisponibilidade, preço e política de dados | Pode ser fase inicial, mediante análise |
| M-C: vários provedores alternando automaticamente por custo | Possível otimização de preço | Pode transferir dados a jurisdição/terceiro não autorizado | **Não permitir fallback sem homologação** |
| M-D: usar somente modelo local/self-hosted | Potencial controle de dados | Hardware/capacidade, custos e operação maiores; qualidade variável | Avaliar viabilidade futura |

Não presumir que `open source`, serviço local ou API paga sejam automaticamente mais seguros, mais baratos ou licenciados para o caso comercial.

## 5. Privacidade, LGPD e homologação de provedores

Cada combinação **produto + finalidade + classe de dados + provedor + modelo + região** precisa de avaliação específica antes de processar informações reais.

Matriz documental de verificação:
- operador/controlador/suboperador por fluxo, responsabilidades contratuais e base jurídica adequada;
- uso de prompts, arquivos e saídas para treinamento, monitoramento ou melhoria de modelos nos **termos efetivos** da API/plano;
- retenção, logs, subprocessadores, local de processamento, transferência internacional e mecanismos aplicáveis;
- criptografia, segregação, credenciais, segurança, auditoria, incidentes e exclusão;
- política de conteúdo e de dados sensíveis; proibição de envio desnecessário de segredos e históricos integrais;
- versionamento dos termos do fornecedor e reavaliação quando houver mudança material;
- continuidade comercial e técnica: limitação de API, término de contrato, alteração de preços ou indisponibilidade.

**Diretriz candidata:** dados privados de clientes não são enviados para treinamento/reutilização global por padrão. Qualquer exceção depende de finalidade, base/autorização adequada, contrato e avaliação específica. Aprovação documental não é homologação jurídica.

## 6. Custo técnico versus cobrança comercial

**Separar conceitos:**
1. **Uso técnico efetivo:** unidades de entrada e saída, embeddings, requisições, armazenamento e taxas técnicas por tentativa.
2. **Custo do fornecedor:** preço e política da operação técnica no período e região contratada, inclusive tentativas adicionais.
3. **Reserva/limite de execução AIA:** orçamento de segurança e controle de capacidade, revisável sob política.
4. **Crédito comercial CRM:** benefício/quantidade de plano, regras de elegibilidade, uso, suspensão, estorno e reconciliação.
5. **Efeito de negócio CRM:** confirmação de tarefa, resumo gravado ou outra operação, **não equivalente** a uso de tokens.

AIA registra métricas técnicas com correlação e **não lança créditos comerciais diretamente** sem contrato autorizado pelo CRM.

### 6.1 Fórmula ilustrativa de simulação, não preço real

`C_IA = Σ[(tokens_entrada/1e6 × tarifa_entrada) + (tokens_saida/1e6 × tarifa_saida) + taxa_chamada + custo_embedding]` para **tentativas realmente consumidas**, acrescida quando aplicável de armazenagem, transmissão e serviços auxiliares.

`C_total_estimado = C_IA + C_infra + C_logs + C_backup + C_suporte + C_licencas + margem_de_contingencia`.

Variáveis são **placeholders**. Nenhuma tarifa, moeda, quantidade, margem, preço final ou compromisso é aprovado nesta proposta. Quotas, preços e retenção serão documentados e validados posteriormente com dados atuais e contratos reais.

### 6.2 Controles de exposição

- orçamento e limites por tenant, produto, plano, agente, ferramenta, modelo e janela, com responsável pela regra;
- avisos de aproximação e bloqueio/degradação segura diante do limite;
- política de interrupção para loops, prompts excessivos, anexos grandes e retries;
- medição/correlação de `run_ref`, `attempt_ref`, `usage_ref` e `effect_ref`;
- conciliação de faturas técnicas com consumo e créditos CRM, sem dupla contabilização de eventos;
- custos de inferência repetida podem ser reais mesmo se **nenhuma nova tarefa** for criada;
- modelos que alterem custo, risco ou formato exigem revisão e aprovação antes de roteamento.

## 7. Política de contingência e fallback seguro

Um fallback somente é elegível se **todos** os requisitos forem verdadeiros:
1. O provedor/modelo alternativo está homologado para aquele produto, tenant, classe e finalidade de dados.
2. Oferece capacidades compatíveis e garantias de segurança/contrato não inferiores às exigidas.
3. O custo e a elegibilidade da operação permanecem sob orçamento e política autorizados.
4. Não modifica automaticamente o tipo de efeito, destinatário, escopo, conteúdo material ou aprovação exigida.
5. O novo destino é autenticado/permitido e a transferência de dados está autorizada.
6. As decisões ficam rastreadas com versões, motivo de troca, falha original e resultado.

Sem candidato compatível, usar **falha segura, espera ou intervenção humana**; não executar com qualquer modelo disponível. Reprocessamento de inferência pode ser autorizado sob limites, mas ações de efeito CRM continuam idempotentes e exigem reconciliação em caso de timeout.

**Cenários candidatos:** rate limit, indisponibilidade, qualidade abaixo da margem homologada, versão incompatível, falha contratual/privacidade, mudança de tarifa. Nenhum tempo máximo ou limiar é fixado aqui.

## 8. n8n: licenciamento e limites do serviço interno Imagine

Diretriz funcional ratificada: n8n, se usado, fica **sob controle operacional da Imagine** em automações internas de seus serviços, com identidades e ferramentas limitadas; clientes não personalizam livremente automações dentro do n8n como produto da Imagine. A configuração de automações pelos clientes do CRM pertence ao seu **motor nativo**.

**Questão ainda ABERTA (R01-G09):** o cenário comercial efetivo, licenças, restrições de uso, direito de execução para clientes e limites do produto precisam ser conferidos nos **termos vigentes** do n8n e, quando apropriado, com fornecedor/assessoria competente. Não concluir automaticamente que self-hosted é gratuito ou permitido em toda hipótese SaaS.

A homologação deverá distinguir:
- automações internas executadas pela Imagine para entregar seus próprios serviços;
- funcionalidades SaaS acessadas por clientes, sem acesso ao n8n;
- embutir/white-label, gestão de workflows por clientes ou revenda da plataforma;
- limites de usuários, execução, ambientes e eventuais exigências comerciais;
- suporte, atualização, backups, proteção de credenciais, licenciamento de nodes e integrações externas.

**Nesta fase:** não contratar, instalar, configurar, alterar ou licenciar o n8n. Nenhuma conclusão comercial definitiva é tomada.

## 9. Infraestrutura, dependências e orçamento

Análise futura deverá separar:
- capacidade e custo do serviço AIA: entrada, workers, bancos/estado, filas, memoria/RAG, logs e backups;
- topologia por ambiente: dev, homologação e produção com proteção de segredos e dados;
- confiabilidade: RTO/RPO, backup, recuperação, escala e limites de filas;
- licenças de modelos, componentes, observabilidade, conectores e n8n;
- custos variáveis de IA e tarifas de provedores;
- atendimento a incidentes, monitoramento e suporte;
- domínios e rede/egress controlados, custos de transferência e regiões;
- riscos de lock-in, descontinuação, alteração de preço e saída/migração.

Leaseweb, VPS Ubuntu/Docker, Cloudflare e outros serviços já cogitados no ecossistema são **insumos candidatos**. AIA-R03/S04 não seleciona VPS, banco, modelo, n8n ou região nem autoriza instalação.

## 10. Casos de avaliação e riscos de fornecedor (não executados)

| Cenário documental | Comportamento esperado futuramente |
| --- | --- |
| Provedor primário fica indisponível, alternativo não homologado | Não transmitir dados ao alternativo; falha segura |
| Modelo novo passa a coletar prompts para treinamento incompatível | Bloquear uso até reavaliação contratual |
| Requisição excede orçamento de tenant/plano | Impedir novas etapas conforme regras homologadas |
| Retry da inferência causa custo técnico, efeito CRM foi confirmado | Medir custo técnico; não duplicar tarefa ou crédito |
| Versão de modelo altera o formato e score de classificação | Revalidar qualidade e compatibilidade antes de publicar |
| Callback de consumo chega duas vezes | Deduplicar evento comercial; preservar consumo técnico verdadeiro |
| Cliente tenta configurar fluxo n8n interno Imagine | Não disponibilizar construtor; CRM controla automações nativas |
| Provedor exige região incompatível com fluxo de dados | Impedir envio e registrar necessidade de decisão |
| Custos sobem após reajuste não comunicado | Limites de orçamento e revisão de margens/fornecedor |
| Agente recebe instrução de usar endpoint externo não homologado | Negar e impedir exfiltração |
| Plano CRM é suspenso entre pedido e chamada paga | Revalidar elegibilidade antes de consumo quando aplicável |
| Modelo alternativo tem menos proteção para dado confidencial | Não usar fallback apesar de disponibilidade |
| Fornecedor interrompe suporte à versão homologada | Inventariar impacto, migração e nova homologação |
| Logs técnicos guardam conteúdo privado para cálculo de custo | Minimizar; usar métricas em lugar de payload completo |

Casos são **propostas de teste sintético** para AIA-09 e R02/R05. Não há execução, evidência de sucesso, homologação ou compra.

## 11. Diretrizes R03-S04-D01 a D10 — aprovadas documentalmente

| ID | Tema | Alternativa A — recomendada | Alternativa B |
| --- | --- | --- | --- |
| R03-S04-D01 | Catálogo de modelos | Catálogo por capacidades, versões, privacidade e limites, homologado pela Imagine | Acesso livre a qualquer modelo por prompt |
| R03-S04-D02 | Arquitetura de provedores | Abstração e seleção governada por política, sem fixar fornecedor nesta fase | Acoplamento permanente sem critérios de troca |
| R03-S04-D03 | Dados e termos | Homologar retenção, treinamento, suboperadores e transferências antes de dados reais | Enviar dados privados para qualquer API disponível |
| R03-S04-D04 | Fallback | Substituição apenas entre modelos/rotas previamente homologados para finalidade, dados, capacidade e custo | Trocar automaticamente por qualquer provedor |
| R03-S04-D05 | Medição técnica | Medir tentativas e unidades técnicas por execução, modelo e tenant, com auditoria minimizada | Custo presumido ou calculado a partir da resposta do LLM |
| R03-S04-D06 | Crédito comercial | CRM mantém saldo/plano/cobrança; AIA fornece uso técnico deduplicável e reconciliável | Núcleo modifica diretamente créditos por callbacks |
| R03-S04-D07 | Limites e incidentes | Quotas, tetos, alertas, loops e degradação segura por empresa/operação; valores posteriores | Execuções sem orçamento e sem limitação |
| R03-S04-D08 | n8n/licenças | n8n apenas interno e sujeito à verificação jurídica/comercial dos termos, motor nativo para clientes CRM | Declarar licença comercial aprovada sem análise |
| R03-S04-D09 | Orçamento/continuidade | Simular cenários e comparar custo, segurança, qualidade, RTO/RPO e alternativas antes de contratar | Provisionar e gastar sem estimativas/aceite |
| R03-S04-D10 | Fase | Ratificar somente critérios documentais e avançar R03/S05, deixando gates e implementação bloqueados | Aprovar provedores, compras, credenciais e deploy agora |

**R03-S04-D01 a D10: APROVADAS DOCUMENTALMENTE em 2026-11-06 pelo responsável.** Ratificação exclusivamente documental preliminar: não homologa modelo, região, licença, preço, contrato, cota numérica, infraestrutura ou fornecedores; não autoriza contas, APIs pagas, programação, SQL, ALLOW, testes funcionais nem deploy.

## 12. Dependências, lacunas e próximos passos

- [x] R03-S04-D01 a D10 aprovadas documentalmente pelo responsável em 2026-11-06.
- [ ] Confirmar requisitos de saída/qualidade de resumos e classificação com CRM, sem programar ou alterar gates.
- [ ] Inventariar alternativas de provedor, modelo, preços efetivos e termos contratuais atuais sob avaliação separada.
- [ ] Definir categorias de dados, retenção, bases jurídicas, localização, subprocessadores e planos de incidente.
- [ ] Formalizar métricas de consumo técnico, quotas/créditos comerciais e conciliação, sem acesso ao saldo real do CRM.
- [ ] Definir orçamento de MVP, previsão de escala, responsabilidade por contas e limites por tenant.
- [ ] Revisar condições de licença n8n aplicáveis ao modelo Imagine e fornecedores específicos, sem contratação.
- [ ] Preparar critérios de homologação de qualidade, privacidade, custo e fallback com dados sintéticos; sem testes reais.
- [ ] Preservar **R01-G01 a G11 todos ABERTOS**, sobretudo G05/G06/G07/G09/G10/G11.
- [ ] Preservar **CORE/SEG/AUT e demais gates próprios do CRM ABERTOS**, sem SQL, ALLOW ou programação.
- [ ] Exigir aprovação específica futura para tecnologia, contas, credenciais, testes, infraestrutura, compras e deploy.

**Próximo recorte documental sugerido:** `AIA-R03/S05 — Implantação, Homologação, Versionamento e Rollback`, com critérios de sequência e riscos, **sem deploy**.

**Imutáveis:** não alterar outros repositórios, `main`/PRs de CRM/Portal/Performance/Universidade, VPS/Cloudflare/n8n, dados reais, chaves, provedores externos, pagamentos, SQL/ALLOW, código ou testes funcionais.
