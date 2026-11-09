# AIA-R01/EVID-01 — Dossiê de evidências e aceites do piloto CRM OP-01

**Preparado em:** 09/11/2026. **Classificação:** pacote de revisão documental; **NÃO HOMOLOGADO**. **Decisão de integração: NO-GO** até aceite formal dos donos e fechamento dos gates aplicáveis.

## 1. Fontes cruzadas e situação verificada

- Imagine AI Agents: FT01/F1-01 a F1-18 integradas **somente na dev**; [PR #118](https://github.com/rafhael100/imagine-ai-agents/pull/118) e [CI #42610138453](https://github.com/rafhael100/imagine-ai-agents/actions/runs/42610138453) comprovam **228/228 casos PHP e 73/73 sintaxes**, com dados fictícios e sem redes. PR #119 documenta o resultado.
- CRM: [PR #57](https://github.com/rafhael100/imagine-crm/pull/57) está **OPEN/DRAFT/sem merge** e possui somente `docs/desenvolvimento/CRM-AIA-RO-01-proposta-contrato-bilateral-offline-2026-11-09.md`. Não equivale a aceite da equipe CRM, SEG ou AUT.
- Normas internas: `docs/AIA-R01-CONSOLIDACAO-TRANSVERSAL-GATES.md`, `docs/AIA-R01-G01-G02-CONTRATO-CONFIANCA-PROPOSTA.md`, `docs/AIA-R04-S01-CONTRATOS-TECNICOS-MINIMOS-MVP-CRM.md`, `docs/AIA-R04-S02-SCHEMAS-VERSIONAMENTO-OPERACOES-CRM-AIA.md` e `docs/AIA-R01-TRANSICAO-FT01-PILOTO-CONTROLADO.md`.
- Os 228 casos são testes **de laboratório**, e **não** constituem autenticação de serviço, validação de RLS, LGPD operacional, autorização de uso de dados, testes de API CRM, aceitação de fornecedores ou homologação de produção.

## 2. Sequência executiva e travas

1. **Antes de qualquer integração:** CRM CORE e SEG validam titularidade dos dados e o contrato conceitual OP-01; AUT define identidade e vínculo de usuário/empresa; Privacidade determina finalidade e minimização; Arquitetura e Operação escolhem trust boundaries e meios de revogação. Cada parecer deve identificar responsável, versão, data e evidência.
2. **Antes de piloto de desenvolvimento real:** fechar formalmente G01, G02, G05, G08, G10 e G11 e os gates CORE/SEG/AUT pertinentes. Os demais G03/G04/G06/G07/G09 mantêm obrigação de avaliação conforme o recorte; **não declarar exceção automaticamente**. Exigir autorização específica de uso de credenciais, ambiente e dados mesmo que sejam de teste.
3. **Se qualquer evidência crítica faltar:** manter `NO-GO`, sem criar endpoint, emissão de token, grant `ALLOW`, SQL, banco real, integração, consumo externo ou despesa.
4. **O primeiro piloto, quando autorizado, é somente leitura OP-01:** sem OP-05 ou OP-06; sem escrever histórico, criar tarefas, exportar dados ao LLM, usar CRM de clientes reais ou migrar permissões de produto.
5. **Após piloto autorizado:** registrar matriz de execução de casos negativos em serviços reais sob ambiente isolado, trilha minimizada e rollback testado; obtenção de prova não substitui novo aceite de produção.

## 3. Matriz de evidência exigida — nenhuma linha fecha gate por simulação

| Gate | Autoridade sugerida / quem deve aceitar | Prova objetiva mínima para revisão | Lacuna atual | Estado |
| --- | --- | --- | --- | --- |
| R01-G01 identidade | SEG/IAM + CORE CRM + AIA | emissor/audience verificáveis, TTL, rotação/revogação, binding de empresa/usuário; rejeição de token falso/expirado | somente envelopes sintéticos F1-11/F1-14 | ABERTO |
| R01-G02 recurso/campo | CORE/SEG CRM | ACL por tenant, lead, principal, recurso, finalidade e campo no backend; negação cruzada e ausência uniforme | política F1-16 é mock | ABERTO |
| R01-G03 estados/replay | CORE + AIA | matriz de retry e replay distribuído, timeout, reconciliação, idempotência e prova de efeito | F1-14 volátil, sem armazenamento compartilhado real | ABERTO |
| R01-G04 aprovações | AUT/Produto CRM + AIA | aprovador autenticado, alçada e material exato, vencimento/revogação; testes negativos | simulações de alçada, sem identidade real | ABERTO |
| R01-G05 privacidade | Privacidade/Jurídico + CORE | finalidade/base legal, classificação de campos, retenção/expurgo, subprocessadores, logs e incidente | sem inventário operacional ou fluxos reais homologados | ABERTO |
| R01-G06 cotas/custos | Financeiro/Produto + AIA | fronteira do custo técnico x créditos comerciais, reserva e conciliação | consumo somente fictício | ABERTO |
| R01-G07 segurança/aceite | QA + SEG + CORE + AIA | execução reproduzível de contrato e testes críticos cruzados, com aprovadores independentes | CI comprova mocks, não integração | ABERTO |
| R01-G08 compatibilidade CRM | CORE + Arquitetura CRM | operação OP-01, nomes de campos reais, respostas, versão/depreciação, owner e rollback aceitos bilateralmente | CRM PR #57 draft | ABERTO |
| R01-G09 fornecedores/licenças | Jurídico/Operações | licenças atuais verificadas do uso comercial, subprocessadores e termos | não avaliado neste pacote | ABERTO |
| R01-G10 ambiente/operação | Infra/SEG + CORE + AIA | topologia isolada, segredos, rede, telemetria, backup, RTO/RPO, incidentes e rollback | só objetos PHP em memória | ABERTO |
| R01-G11 decisão executiva | Responsável/direção + líderes | escopo piloto, dados autorizados, responsáveis, custo/teto, plano de parada e aprovação expressa | decisão específica pendente | ABERTO |
| CRM CORE | Liderança CORE CRM | confirmação de titularidade/versão/recurso/campo e recursos disponíveis no backend | não demonstrado por PR #57 | ABERTO |
| CRM SEG | Liderança SEG CRM | threat model, negação por padrão e casos reais de ACL/RLS, sem vazamento | não demonstrado por PR #57 | ABERTO |
| CRM AUT | Liderança AUT CRM | identidade/ligação do operador, revogação e escopo por empresa | não demonstrado por PR #57 | ABERTO |

**Observação:** donos acima são **papéis sugeridos para aceite**, não pessoas nomeadas, nem aprovações atribuídas a terceiros. Donos definitivos devem ser designados pela governança.

## 4. Contrato lógico OP-01 para revisão bilateral — sem endpoint funcional

| Elemento | Fonte de autoridade | Regra de aceite |
| --- | --- | --- |
| `issuer`/`audience`/ambiente | Identidade técnica homologada | autenticado fora do payload e vinculável ao serviço correto; negar divergências |
| `tenant`/`principal`/vínculo | CRM | resolver no backend, não aceitar IDs do corpo como prova de direito |
| `resource` lead | CRM | verificar existência e ACL dentro do tenant, sem enumerar recursos por erro |
| `purpose` e `fields` | CRM/Privacidade | finalidade explícita e mínimo necessário; rejeitar campos desconhecidos/pessoais não autorizados |
| `contract_version` | CORE CRM + AIA | versão reconhecida em ambos os lados; rejeitar extração permissiva de versão desconhecida |
| `nonce`/revogação | SEG + Arquitetura | memória compartilhada segura e negação após rotação/revogação |
| `correlation`/auditoria | SEG/Operação | métricas minimizadas, proteção da identidade, retenção decidida, sem logs de conteúdo |
| resposta | CRM | projeção estritamente permitida, sem campos extras; falhas não enumeráveis |
| disponibilidade | CRM/AIA/Operação | indisponibilidade ou timeout de política significa `DENY` ou pendência, **nunca `ALLOW`** |

## 5. Casos de homologação futura — rastreáveis ao PR CRM #57

| Caso | Cenário | Resultado obrigatório | Tipo de prova ainda faltante |
| --- | --- | --- | --- |
| OP01-N01 | serviço não autenticado/chave revogada | negar antes de buscar lead | teste de autenticação real |
| OP01-N02 | issuer, audience ou ambiente incorretos | negar | verificação criptográfica do serviço |
| OP01-N03 | empresa/operador suspenso ou vínculo revogado | negar mesmo com grant anterior | teste CRM AUT + CORE |
| OP01-N04 | recurso inexistente versus outro tenant | negação externa indistinguível por conteúdo; política de tempo/status ainda a definir | teste ACL/RLS e transporte |
| OP01-N05 | campo pessoal ou propriedade desconhecida | negar antes de retornar ou enviar dados à IA | contrato de campos e teste CRM |
| OP01-N06 | tentativa de mutation ou versão desconhecida | negar | contrato/compatibilidade CORE |
| OP01-N07 | replay entre nós após reinício | negar | teste armazenamento distribuído |
| OP01-N08 | revogação no intervalo de fila | revalidar no CRM antes da consulta | teste de race e política |
| OP01-N09 | CRM/autorizador indisponível | negar sem fallback permissivo | caos/timeout controlado |
| OP01-N10 | registro de log com segredo, token ou conteúdo de lead | não registrar conteúdo; alerta sem vazamento | teste de privacidade observabilidade |
| OP01-N11 | falha de telemetria antes de parse | negar de forma uniforme sem processamento não auditado | testes de borda em serviço real |
| OP01-P01 | lead próprio, usuário autorizado e escopo mínimo | retornar só campos permitidos | teste integrado bilateral |
| OP01-P02 | campos em ordem arbitrária dentro do permitido | projeção exata, sem ampliação | teste contrato bilateral |

Os casos são **especificações de teste futuro**, não testes executados no CRM.

## 6. Registro de decisões necessárias — proprietário de cada resposta

| ID | Questão em aberto | Quem precisa decidir |
| --- | --- | --- |
| DEC-01 | prova canônica de serviço (mTLS, token assinado, combinação), TTL e rotação | SEG + Infra + CORE |
| DEC-02 | identificadores reais e autoridade CRM de vínculo usuário/empresa | CORE + AUT |
| DEC-03 | campos permitidos e finalidade juridicamente adequada da OP-01 | CORE + Privacidade |
| DEC-04 | resposta uniforme: HTTP/status, latência, erros, cache e não enumeração | SEG + CORE |
| DEC-05 | nonce e revogação compartilhados, consistência e recuperação | SEG + Infra |
| DEC-06 | retenção/expurgo e segregação de telemetria/derivados | Privacidade + Operação |
| DEC-07 | versão do contrato e esquema de compatibilidade/rollback | CORE + AIA |
| DEC-08 | ambiente piloto, credenciais sintéticas ou teste segregado, limite de custo e responsáveis | Direção + Infra |

**Todos:** PENDENTES. A IA pode propor alternativas com riscos, mas não aprovar em nome de CORE, SEG, AUT, Jurídico ou direção.

## 7. Checkpoints de aceite

- **CP-01 Documental:** revisão escrita por CRM CORE, SEG, AUT e Privacidade para o contrato e matriz OP-01, com divergências explícitas. Não libera infraestrutura.
- **CP-02 Técnica:** evidências executadas das proteções reais no ambiente autorizado com hash de artefatos, data, responsáveis e falhas retestadas.
- **CP-03 Executiva:** aprovação nominal do piloto, custos e limite de dados. Nenhuma credencial antes desse checkpoint.
- **CP-04 Produção:** homologação separada com resiliência, backup, privacidade, rollback, revisão de segurança e autorização expressa. Não derivar CP-04 de CP-01 a CP-03.

**Resultado do dossiê:** material pronto para revisão; todos os gates em aberto e **NO-GO para integração real**.
