# Imagine AI Agents — roadmap operacional

**Estado verificado por SHA/run:** fontes em [STATUS-ATUAL.md](../STATUS-ATUAL.md). A integração owner criar/consultar já foi aprovada e executada na CI de homologação CRM #658 contra o receptor P67 original. P67, P68, CRM #658 e CRM #659 estão verdes nas SHAs registradas e permanecem DRAFT/sem merge. Produção continua NO-GO.

## Próximo marco — fluxo owner em homologação persistente

Disponibilizar o mesmo fluxo autenticado de criação e consulta de rascunhos em homologação persistente, preservando regra comercial, resolução de empresa no CRM, autoria histórica, grants atuais, mTLS e reconciliação. A decisão de permitir esse fluxo ao owner ativo já está tomada; o próximo incremento prepara sua disponibilização e verificação no ambiente alvo.

O resultado revisável deve identificar as fontes exatas e a configuração efetivamente disponível, apresentar a jornada owner → planejamento existente → rascunho → nova consulta e registrar persistência/continuidade no ambiente. As provas de recuperação atuais cobrem seus snapshots e limites declarados; não demonstram recuperação distribuída ou RPO zero. Não presumir ausência de infraestrutura, R2, MFA ou credenciais a partir de uma CI descartável.

Esta sequência registra a continuidade autorizada; o arquivo não instala serviços, concede grants ou autoriza deploy/produção. Os limites do [P67](reviews/AIA-P67-CRM-OWNER-DRAFTS.md) e do [P68](reviews/AIA-P68-OWNER-DRAFT-RECOVERY.md) permanecem.

## Registro histórico — Fase A, base de demonstração offline

Os registros A/B abaixo preservam a posição histórica consolidada em 14/11/2026, com [EVID-49](AIA-R01-EVID-49-CRM-AGENTS-POSTMERGE.md), [EVID-50](AIA-R01-EVID-50-REAL-FACADE-CORE-CRM-POSTMERGE.md), [EVID-51](AIA-R01-EVID-51-OP01-CAMINHO-CRITICO-POSTMERGE.md) e EVID-52. As restrições dos mocks não reclassificam as autorizações e provas posteriores. [Arquivo histórico integral](historico/ROADMAP-ANTES-CONSOLIDACAO-2026-11-10.md).

**Entregue na branch de desenvolvimento para avaliação técnica, não para clientes.** F1-12 a F1-30, SEC-11 a SEC-40 e os demais incrementos documentados criaram contratos sintéticos, borda OP-01, política fake de owner, prevenção de replay local, leitura de ferramenta governada, sugestões fixas e trilha limitada.

A [SEC-20 / PR #182](https://github.com/rafhael100/imagine-ai-agents/pull/182) valida projeções antes da auditoria de sucesso, com [CI pós-merge #42759202848](https://github.com/rafhael100/imagine-ai-agents/actions/runs/42759202848) aprovada; veja [EVID-18](AIA-R01-EVID-18-SEGURANCA-PROJECAO-403.md).

**Evidências mais recentes:** [PR #173](https://github.com/rafhael100/imagine-ai-agents/pull/173), [PR #174](https://github.com/rafhael100/imagine-ai-agents/pull/174), [PR #175](https://github.com/rafhael100/imagine-ai-agents/pull/175), [PR #177](https://github.com/rafhael100/imagine-ai-agents/pull/177), [PR #178](https://github.com/rafhael100/imagine-ai-agents/pull/178) e [EVID-16](AIA-R01-EVID-16-SEGURANCA-TOOL-397.md). SEC-41/42 integradas à dev: **555 testes PHP / 83 lint** e matriz Python adversarial; [CI pós-merge #42899017010](https://github.com/rafhael100/imagine-ai-agents/actions/runs/42899017010) e [EVID-27](AIA-R01-EVID-27-SEC41-42-POSTMERGE.md) confirmadas, mais contratos Python e demonstração offline.

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

A [SEC-35](AIA-FT01-SEC-35-COORDENADOR-MONOTONICO.md) protege também o coordenador contra status não terminais regressivos, sem reenvio e com oito regressões offline. Ainda não é reconciliação operacional do CRM.

A [SEC-36](AIA-FT01-SEC-36-IDS-FERRAMENTA-LIMITADOS.md) limita IDs de ferramentas a 128 bytes antes da análise e usa impressão digital fixa nos erros de entrada grande, com cinco regressões offline, sem acesso real ao CRM.

A [SEC-37](AIA-FT01-SEC-37-PARSER-OP01-LIMITADO.md) limita o processamento da estrutura OP-01 antes de ordenar chaves e examinar referências, com sete regressões para entradas grandes e não consumo de nonce. Permanece offline.

A [SEC-38](AIA-FT01-SEC-38-CONCESSOES-LIMITADAS.md) valida e limita as referências de concessões sintéticas antes de armazená-las, com seis regressões offline. Não cria identidade ou permissão reais.

A [SEC-39](AIA-FT01-SEC-39-LIMITES-SUGESTOES.md) limita a projeção sintética de sugestões antes de chamar qualquer adaptador, com oito regressões offline. A política de IAM/CRM operacional continua pendente.

A [SEC-40](AIA-FT01-SEC-40-MANIFESTO-LIMITADO.md) limita a contagem de chaves do manifesto e referências sintéticas comuns antes de ordenar ou executar regex, em sete regressões offline. Sem publicação real.

A [EVID-25](AIA-R01-EVID-25-ENSAIOS-OP01-13-CENARIOS.md) adiciona ensaios **somente do consumidor fictício** para os 13 cenários do pacote OP-01, sem alterar seus estados `NAO_EXECUTADO`. Não é homologação bilateral nem prova de identidade/ACL operacional.

A [SEC-41](AIA-FT01-SEC-41-PROJECAO-OP01-UNIFICADA.md) unifica a regra de projeção das três camadas do leitor OP-01 fictício. Nove regressões adicionais, sem ACL ou API reais.

A [SEC-42](AIA-FT01-SEC-42-PARIDADE-MUTACOES-OP01.md) ensaia centenas de variações de requests sintéticas no parser PHP, sem prova de aceitação pelo CRM.

A [SEC-43](AIA-FT01-SEC-43-REVALIDACAO-POS-LEITURA.md) foi integrada à dev com dez regressões offline e [CI pós-merge #42902464064](https://github.com/rafhael100/imagine-ai-agents/actions/runs/42902464064) aprovada: **565 testes PHP / lint 83**. [EVID-28](AIA-R01-EVID-28-SEC43-CI-POSTMERGE.md) registra limites; não substitui transação de autorização real no CRM.

A [SEC-44](AIA-FT01-SEC-44-FERRAMENTA-PROJECAO-COMUM.md) foi integrada à dev com dez regressões offline para ferramenta de leitura, revogação, replay e auditoria. **575 testes PHP / 83 lint** aprovados na [CI pós-merge #42906719897](https://github.com/rafhael100/imagine-ai-agents/actions/runs/42906719897); [EVID-29](AIA-R01-EVID-29-SEC44-CI-POSTMERGE.md). Sem CRM real.

A [SEC-45](AIA-FT01-SEC-45-REVALIDACAO-POS-SUGESTAO.md) foi integrada à dev: revalidação após sugestão fictícia, dez U67-01..10, **585 testes PHP / lint 83** na [CI pós-merge #42913437800](https://github.com/rafhael100/imagine-ai-agents/actions/runs/42913437800), [EVID-30](AIA-R01-EVID-30-SEC45-CI-POSTMERGE.md). Não equivale à transação de ACL do CRM real.

A [SEC-46](AIA-FT01-SEC-46-RECIBO-REVISAO-OWNER-ABA.md) foi integrada à dev, bloqueando revogação+reconcessão (ABA) e recheck sem recibo. 12 regressões; **597 testes PHP / 83 lint** aprovados na [CI pós-merge #42918687251](https://github.com/rafhael100/imagine-ai-agents/actions/runs/42918687251), [EVID-31](AIA-R01-EVID-31-SEC46-CI-POSTMERGE.md). É memória local, não ACL/nonce distribuídos.

[SEC-47](AIA-FT01-SEC-47-RECHECK-TEMPO-MONOTONICO.md): 605 PHP, [CI #42923158871](https://github.com/rafhael100/imagine-ai-agents/actions/runs/42923158871), [EVID-32](AIA-R01-EVID-32-SEC47-CI-POSTMERGE.md).

SEC-48: 613 PHP, [EVID-33](AIA-R01-EVID-33-SEC48-CI-POSTMERGE.md) e CI #42924834951.

[SEC-49](AIA-FT01-SEC-49-REVISAO-TRUST-COMPARTILHADO.md): [EVID-34](AIA-R01-EVID-34-SEC49-CI-POSTMERGE.md), CI #42933553917 (621 PHP).

[SEC-50](AIA-FT01-SEC-50-CAPABILITY-RECHECK-POS-FERRAMENTA.md): 629 PHP / 83 lint; revogação pós READ/SUGGEST, U72-01..08. Gate em revisão/NO-GO.

## Registro histórico — Fase B, pacote bilateral OP-01 de 10/11/2026

**Pacote de revisão disponível, aguardando decisões independentes:** [OP-01 JSON](evidencias/op01-pacote-aceite-bilateral.json) e [EVID-19](AIA-R01-EVID-19-PACOTE-BILATERAL-OP01.md) relacionam sete responsáveis sugeridos a 14 gates e 13 cenários ainda não homologados. [CI pós-merge #42770907072](https://github.com/rafhael100/imagine-ai-agents/actions/runs/42770907072) success no arquivo gerado. **Este é um checklist, não um GO.** Registrar pareceres de CRM CORE/SEG/AUT, Privacidade, Infra e Direção com evidências reais; não abrir API antes da liberação.


**Estado: ABERTO / NO-GO.** O [CRM PR #57](https://github.com/rafhael100/imagine-crm/pull/57) é uma proposta documental OPEN/DRAFT, não um endpoint implementado. Antes de código de integração operacional, registrar em decisão rastreável:

1. **G01/G03 + CRM AUT/SEG:** fonte de autenticação, credencial protegida, emissor/audience, identidade delegada, tenant resolvido de maneira confiável, revogação, replay durável e concorrência;
2. **G02 + CRM CORE/SEG:** matriz de autorização no **backend CRM** por operação, tenant, principal, recurso, finalidade, campo, escopo e estado da organização, com negação por padrão;
3. **G05/G08 + Produto/Privacidade:** classificação/nomes dos campos reais, formato de requisição e resposta, versionamento, LGPD, propósito, retenção, falha uniforme e auditoria;
4. **G07/G10 + Infra/Segurança:** revisão independente, segregação e segredos de homologação, telemetria minimizada, backups, rollback e testes negativos entre instâncias;
5. **G11 + Direção:** responsáveis, limites financeiros e autorização expressa do **piloto**, distinta da autorização de produção.

Sem parecer itemizado e evidência verificável, permanecer em NO-GO. Não marcar como aprovados por comentário em PR ou benchmark sintético.

## Fase C — implementação OP-01 real em homologação

**Executada nos recortes expressamente aprovados de homologação fictícia.** A consulta humana mínima OP-01 foi exercitada nas CRM #649/#651 e no consumidor P65. Em seguida, a autorização específica owner permitiu criação/consulta de rascunhos; a [CRM #658](https://github.com/rafhael100/imagine-crm/pull/658) executou sessão/MFA e fonte nativa reais contra o P67 fixado, na [CI 44237380307](https://github.com/rafhael100/imagine-crm/actions/runs/44237380307). O CRM conserva a decisão de acesso e a autoria atestada.

Essa prova externa complementa os ensaios internos P67/P68, que continuam declarando `human_crm_session_verified=false`. Ela não encerra automaticamente os treze cenários históricos de delegação nem abre OP-05/OP-06, execução de ações ou uso de dados reais por analogia. O próximo marco é a disponibilização persistente descrita acima.

## Fase D — piloto restrito e avaliação de produção

**Não iniciada e não autorizada por este arquivo.** Requer autorização distinta de piloto, indicadores operacionais, rollback testado, privacidade e orçamento confirmado. Produção exige novo aceite independente e controles operacionais permanentes.

## Regra vigente de continuidade e limites

Continuar autonomamente o desenvolvimento e as verificações dos recortes já autorizados, em branches isoladas e com evidência da SHA executada, sem exigir nova decisão rotineira sobre owner criar/consultar. Esta documentação não amplia o escopo para novos efeitos, clientes, provedores pagos, merge em `main`/DEV ou implantação. A mudança concreta de disponibilização deve ser revisável antes de qualquer ação no ambiente alvo; produção e ativação comercial permanecem NO-GO. Consulte [AGENTS.md](../AGENTS.md), as autorizações específicas registradas e os contratos do recorte; o [dossiê EVID-16](AIA-R01-EVID-16-SEGURANCA-TOOL-397.md) conserva a evidência histórica.
