# Imagine AI Agents — roadmap operacional

**Versão consolidada em 10/11/2026.** O histórico integral continua disponível em [ROADMAP-ANTES-CONSOLIDACAO-2026-11-10](historico/ROADMAP-ANTES-CONSOLIDACAO-2026-11-10.md); ele descreve decisões e fases anteriores, não a liberação atual. Fonte de estado: [STATUS-ATUAL.md](../STATUS-ATUAL.md).

## Fase A — base de demonstração offline

**Entregue na branch de desenvolvimento para avaliação técnica, não para clientes.** F1-12 a F1-30, SEC-11 a SEC-34 e os demais incrementos documentados criaram contratos sintéticos, borda OP-01, política fake de owner, prevenção de replay local, leitura de ferramenta governada, sugestões fixas e trilha limitada.

A [SEC-20 / PR #182](https://github.com/rafhael100/imagine-ai-agents/pull/182) valida projeções antes da auditoria de sucesso, com [CI pós-merge #42759202848](https://github.com/rafhael100/imagine-ai-agents/actions/runs/42759202848) aprovada; veja [EVID-18](AIA-R01-EVID-18-SEGURANCA-PROJECAO-403.md).

**Evidências mais recentes:** [PR #173](https://github.com/rafhael100/imagine-ai-agents/pull/173), [PR #174](https://github.com/rafhael100/imagine-ai-agents/pull/174), [PR #175](https://github.com/rafhael100/imagine-ai-agents/pull/175), [PR #177](https://github.com/rafhael100/imagine-ai-agents/pull/177), [PR #178](https://github.com/rafhael100/imagine-ai-agents/pull/178) e [EVID-16](AIA-R01-EVID-16-SEGURANCA-TOOL-397.md). Último gate validado no código: **492 testes PHP / 82 lint**, mais contratos Python e demonstração offline.

**Conclusão permitida:** laboratório executável para revisão. **Não** concluir que o agente SaaS, o IAM ou a integração CRM estejam operacionais.

A [SEC-21 / PR #186](https://github.com/rafhael100/imagine-ai-agents/pull/186) e [SEC-22 / PR #187](https://github.com/rafhael100/imagine-ai-agents/pull/187) fortaleceram a identidade canônica de Run/Step e os limites dos IDs na camada de ferramentas. CIs pós-merge #42836105212 e #42836672922 aprovadas, [dossiê EVID-20](AIA-R01-EVID-20-RUN-STEP-CANONICO-414.md). A entrega continua apenas offline.

As [SEC-23 / PR #189](https://github.com/rafhael100/imagine-ai-agents/pull/189) e [SEC-24 / PR #190](https://github.com/rafhael100/imagine-ai-agents/pull/190) agora limitam referências e material também antes das assinaturas e da criação de objetos do domínio. [EVID-21](AIA-R01-EVID-21-LIMITES-DOMINIO-427.md) documenta CIs pós-merge aprovadas. Continua sendo laboratório offline.

[SEC-25 / PR #192](https://github.com/rafhael100/imagine-ai-agents/pull/192): medição de uso em memória com limite e falha fechada, [CI pós-merge #42859143202](https://github.com/rafhael100/imagine-ai-agents/actions/runs/42859143202) success. [EVID-22](AIA-R01-EVID-22-MEDIDOR-USO-433.md) documenta os controles e as restrições. Os gates de integração real permanecem abertos.

[SEC-26 / PR #194](https://github.com/rafhael100/imagine-ai-agents/pull/194) e [SEC-27 / PR #195](https://github.com/rafhael100/imagine-ai-agents/pull/195) fecharam colisões de estado de transporte e referências de uso excessivas no laboratório, [EVID-23](AIA-R01-EVID-23-IDENTIDADE-USO-446.md). As duas CIs pós-merge estão aprovadas. Autenticação e ACL reais seguem dependentes do contrato CRM.

A [SEC-28](AIA-FT01-SEC-28-REVISOES-BOUNDED.md) restringe a 128 pacotes o quadro de revisão em memória e conserva votos idempotentes e rejeições. É um limite local de laboratório, não substitui a autenticação e o registro durável de revisores.

A [SEC-29](AIA-FT01-SEC-29-RESERVA-AUDITORIA-VERSOES.md) limita o histórico local de decisões e reserva espaço de auditoria para suspensões, sem conceder poderes reais de deploy ou publicação.

A [SEC-30](AIA-FT01-SEC-30-CATALOGO-LIMITADO.md) delimita a capacidade de modelos/snapshots fictícios sem apagar o histórico, sem criar um registry ou publicação operacional.

A [SEC-31](AIA-FT01-SEC-31-LIMITES-CONHECIMENTO.md) limita a 256 fontes e 1.024 eventos o conhecimento sintético, com falha fechada quando não pode auditar a entrega. Sete regressões adicionais, sem qualquer consulta real ao CRM ou produção.

A [SEC-32](AIA-FT01-SEC-32-EFEITOS-LIMITADOS.md) limita a 256 efeitos sintéticos por gateway, preservando replays e consultas e recusando novas gravações sem capacidade. Sete regressões adicionais, exclusivamente offline.

A [SEC-33](AIA-FT01-SEC-33-EVIDENCIAS-MONOTONICAS.md) reforça o estado monotônico e a idempotência de evidências supervisionadas, sem CRM real. Seis testes extras offline.

A [SEC-34](AIA-FT01-SEC-34-REVALIDACAO-REVISAO-ATIVA.md) revalida a revisão selecionada, bloqueando visualização de versões com revisão revogada e permitindo checagem temporal sintética explícita. Sete regressões offline, sem liberar produção.

## Fase B — contrato bilateral e autorização (caminho crítico)

**Pacote de revisão disponível, aguardando decisões independentes:** [OP-01 JSON](evidencias/op01-pacote-aceite-bilateral.json) e [EVID-19](AIA-R01-EVID-19-PACOTE-BILATERAL-OP01.md) relacionam sete responsáveis sugeridos a 14 gates e 13 cenários ainda não homologados. [CI pós-merge #42770907072](https://github.com/rafhael100/imagine-ai-agents/actions/runs/42770907072) success no arquivo gerado. **Este é um checklist, não um GO.** Registrar pareceres de CRM CORE/SEG/AUT, Privacidade, Infra e Direção com evidências reais; não abrir API antes da liberação.


**Estado: ABERTO / NO-GO.** O [CRM PR #57](https://github.com/rafhael100/imagine-crm/pull/57) é uma proposta documental OPEN/DRAFT, não um endpoint implementado. Antes de código de integração operacional, registrar em decisão rastreável:

1. **G01/G03 + CRM AUT/SEG:** fonte de autenticação, credencial protegida, emissor/audience, identidade delegada, tenant resolvido de maneira confiável, revogação, replay durável e concorrência;
2. **G02 + CRM CORE/SEG:** matriz de autorização no **backend CRM** por operação, tenant, principal, recurso, finalidade, campo, escopo e estado da organização, com negação por padrão;
3. **G05/G08 + Produto/Privacidade:** classificação/nomes dos campos reais, formato de requisição e resposta, versionamento, LGPD, propósito, retenção, falha uniforme e auditoria;
4. **G07/G10 + Infra/Segurança:** revisão independente, segregação e segredos de homologação, telemetria minimizada, backups, rollback e testes negativos entre instâncias;
5. **G11 + Direção:** responsáveis, limites financeiros e autorização expressa do **piloto**, distinta da autorização de produção.

Sem parecer itemizado e evidência verificável, permanecer em NO-GO. Não marcar como aprovados por comentário em PR ou benchmark sintético.

## Fase C — implementação OP-01 real em homologação

**Condicionada à Fase B, não iniciada.** Sequência planejada após liberação: criar cliente/contrato versionado de somente leitura, integrar ao autorizador real CRM e ao serviço autenticado, aplicar timeouts e falha fechada, executar OP01-N01..N11 / OP01-P01-P02 com dados de teste e revisão independente. O CRM retém a decisão de acesso ao lead. **Não abrir OP-05/OP-06, escrita ou uso de dados reais por analogia** com a OP-01.

## Fase D — piloto restrito e avaliação de produção

**Não iniciada e não autorizada por este arquivo.** Requer autorização distinta de piloto, indicadores operacionais, rollback testado, privacidade e orçamento confirmado. Produção exige novo aceite independente e controles operacionais permanentes.

## Regra permanente de execução

Somente trabalho inerte e verificável em `dev/ft01-aia-core-mocks-2026-11-06` enquanto os gates reais estiverem abertos. Não alterar `main`, implantar VPS, usar credenciais, clientes ou provedores pagos, nem afirmar conclusão do produto por contagem de testes. Consulte [AGENTS.md](../AGENTS.md) e o [dossiê EVID-16](AIA-R01-EVID-16-SEGURANCA-TOOL-397.md) antes de alterar arquitetura.
