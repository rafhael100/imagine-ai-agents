# AIA-FT01/F1-03 — Proposta documental de resiliência, replay seguro e auditoria sintética | v0.1

> **ESTADO: PROPOSTA DOCUMENTAL — F103-D01 a D10 ABERTAS. F1-03 NÃO AUTORIZADO PARA CÓDIGO, TESTES OU MERGE.**
>
> Data: 2026-11-08. Repositório: rafhael100/imagine-ai-agents. Branch documental: docs/ft01-f1-03-proposta-resiliencia-auditoria-mock-2026-11-08. Base: dev/ft01-aia-core-mocks-2026-11-06.
>
> **Restrições vinculantes:** main inalterada; gates R01-G01 a G11 e CORE/SEG/AUT CRM ABERTOS; sem banco, SQL, ALLOW, filas, rede, APIs reais, autenticação/ACL em produção, dados pessoais, modelos de IA pagos, instalação de PHP/Composer, GitHub Actions, VPS/Cloudflare/n8n, despesas ou deploy. Esta aprovação para preparar DOCUMENTAÇÃO não é autorização para executar Codex.

## 1. Contexto confirmado e posição na arquitetura

O FT01-F1-01 foi aceito apenas na branch dev pelo PR #71, com modelos de Run, Step, Intent, Approval, Attempt, Effect, Usage, TenantContext e interfaces/mocks PHP puros.

O FT01-F1-02 foi aceito apenas na mesma branch dev pelo PR #76, com SupervisedCoordinator, MockSupervisedGateway e 12 novos testes. A verificação independente registrada no STATUS-ATUAL aponta 19 arquivos PHP sem erros de sintaxe e 22/22 testes unitários sintéticos aprovados em PHP 8.4.24, com hashes dos arquivos conferidos. Não se trata de validação de integração, autenticação ou segurança de produção.

**Problema de F1-03:** simulações simples de sucesso/timeout não comprovam comportamento seguro diante de **falhas compostas** (ex.: consulta indisponível após perda da resposta; rastro corrompido; divergência de versão; instâncias sintéticas sem memória comum). Precisamos exercitar decisões defensivas e evidências mínimas sem criar um motor real.

**Recorte recomendado:** laboratório de falhas, trilha sintética verificável e análise de replay **somente de leitura** que identifica o que é conhecido, desconhecido e não seguro retentar. Nenhum replay pode executar novamente uma intenção ou recriar/aprovar efeitos.

## 2. Escopo candidato FUTURO — sujeito a autorização executiva separada

| Componente candidato | Finalidade local e sintética | Limite obrigatório |
| --- | --- | --- |
| MockFaultPlan / MockFaultInjector | Sequência determinística e explícita de falhas em submit/status/decisão; sem relógio global | Não interferir em serviços reais ou criar retries |
| MockDecisionJournal / TraceVerifier | Eventos em memória com IDs sintéticos e códigos de decisão permitidos; identificar descontinuidade, conflito e ordem inválida | Não usar dados de leads, prompts, segredos, stack traces ou IDs reais |
| ReadOnlyTraceReview / ReplayAnalyzer | Reconstituir somente interpretação de estados a partir de rastros sintéticos, sem chamar gateway de mutação | Não reexecutar submit, criar Approval, compensar ou afirmar efeito confirmado sem prova |
| UnknownEffectPolicy | Classificar efeito como CONFIRMADO/NEGADO/INDETERMINADO/PENDENTE somente segundo evidência fake explícita | Não transformar silêncio, timeout, fim de run ou log em confirmação |
| SimulatedBoundaryRecovery | Representar perda de memória de um coordenador e apontar necessidade de reconciliação externa real FUTURA | Não prometer persistência, consistência entre processos ou recuperação durável |
| Unit tests F1-03 | Validar fail-closed e 12 cenários novos, mais regressão dos 22 do F1-01/02 | Sem testes de integração, Actions, VPS, rede ou dependências |

**Arquivos candidatos após futura autorização:** src/MockResilience/, src/Mocks/, src/Domain/ (apenas tipos pequenos, imutáveis, se indispensáveis), tests/Unit/, README.md e STATUS-ATUAL.md. Não tocar src/Contracts/, AGENTS.md, contratos de CRM, módulos de outros projetos, main ou documentação técnica ratificada.

Arquitetura proposta para cada execução sintética: entrada explícita de contexto + sequência de falhas → coordenador existente F1-02 → status fake conhecido/indeterminado → journaling mínimo → avaliação em modo leitura. O plano NÃO autoriza alterações no coordenador existente para contornar seus controles. Qualquer mudança nele exige justificativa expressa no futuro escopo técnico.

## 3. Invariantes e critérios de segurança

1. **Desconhecido permanece desconhecido:** perda de resposta seguida de falha em status não confirma nem nega efeito; nunca aciona retry de mutação.
2. **Prova de efeito é independente da run:** run concluída não implica confirmação; apenas fonte sintética elegível em Effect pode indicar confirmação fake.
3. **Replay não age:** leitura de rastro não chama submit, não emite autorização, não reexecuta step nem altera saldo/uso.
4. **Falhas compostas não criam permissões:** erro de provedor de sugestão, erro em status e erro de mock de aprovação não liberam efeitos.
5. **Isolamento de contexto:** produto, tenant, principal, finalidade, recurso e versão devem permanecer vinculados; tenant diverso gera negativa, não vazamento.
6. **Aprovação não é inferida:** ausência, revogação, vencimento ou conflito de material/versão mantém o bloqueio de ação.
7. **Idempotência apenas simulada:** colisão de chave com assinatura material distinta falha; sem garantia transacional ou coordenação entre processos.
8. **Rastro mínimo:** somente sequência, códigos fechados, identificadores pseudossintéticos e estado observável; sem conteúdo integral, credenciais, erros brutos ou payloads.
9. **Integridade declarada, não criptografia de produção:** qualquer cadeia de hash/test checksum serve apenas para flagrar corrupção controlada de fixtures, não como prova legal ou inviolabilidade.
10. **Estados terminais não reabrem:** cancelamento e conclusão impedem novos efeitos, mas preservam distinção de efeitos já conhecidos.
11. **Relógio explicitamente injetado:** expirations e ordem temporal são reproduzíveis, sem dormir nem usar horários de servidor.
12. **Falha de interpretação é fail-closed:** versão futura/desconhecida, trace truncado ou evento conflitante devolvem estado de revisão indeterminada, não um allow permissivo.

## 4. Cenários de testes propostos — 12 novos + 22 regressões = 34 (NÃO EXECUTADOS)

| Caso | Simulação sintética | Critério futuro de aceite |
| --- | --- | --- |
| U03-01 | Perda de resposta de submit e falha de consulta status | INDETERMINADO, zero retransmissões |
| U03-02 | Retry de consulta status após falha temporária | Somente consulta, nunca segundo submit |
| U03-03 | Rastro truncado antes do estado de efeito | Leitura não afirma CONFIRMADO |
| U03-04 | Evento duplicado com conteúdo/código conflitante | Marcar conflito, não sobrescrever silenciosamente |
| U03-05 | Eventos fora de ordem ou relógio regressivo | Análise conservadora e erro tipado |
| U03-06 | Tentativa de replay contendo comando de mutação | Rejeitar e manter contador submit intacto |
| U03-07 | Run CANCELADA com efeito mockado previamente confirmado | Efeito preservado, sem novo step |
| U03-08 | Divergência de tenant/produto/finalidade no rastro | Negação segura, sem exposição entre tenants |
| U03-09 | Aprovação expirada/revogada entre decisão e execução fake | Nenhum novo submit |
| U03-10 | Mesma chave, assinatura material conflitante após reconstrução sintética | IDEMPOTENCY_CONFLICT/UNKNOWN, sem admitir reconciliação falsa |
| U03-11 | Simulação de reinício sem memória compartilhada | Detectar falta de prova e não alegar recuperação durável |
| U03-12 | Prompt, dado adversarial ou token sintético dentro de fixture | Trace não copia payload nem promove instrução a regra |

Os casos acima são **critérios propostos, não resultados obtidos**. Os 22 testes existentes permanecem intocados e deverão ser reexecutados com os 12 novos SOMENTE após autorização explícita de testes. Se o Codex continuar sem PHP, registrar NÃO EXECUTADOS; é vedado instalar PHP ou habilitar GitHub Actions por conveniência. Validação eventual em PHP 8.1+ já disponível deve registrar versão, hash dos arquivos testados, lint e saída real. **34/34 não representa homologação do SaaS.**

## 5. Exclusões e riscos que permanecem bloqueados

- **Persistência real:** nenhum PostgreSQL, Redis, arquivo persistente, fila, restore ou protocolo de transação. O laboratório não deve afirmar exatamente-once ou recuperação após crash de produção.
- **Segurança real:** ausência de OAuth/JWT funcional, vínculo real de usuários, autorização por ACL do CRM, integração com Portal e gestão de chaves.
- **Integração e operação:** nenhuma API real CRM/IA, HTTP, LLM, n8n, ambiente cloud, plugin comercial ou deploy.
- **Dados e privacidade:** fixtures somente sintéticas, sem PII; hash de identificador arbitrário não equivale à anonimização de produção.
- **Governança e gastos:** sem fechamento de R01-G01–G11, CORE/SEG/AUT CRM, contratação ou GitHub Actions. Sem merge na main ou alteração de outros repositórios.
- **Escopo futuro:** não transformar resiliência mockada em licença para execução dos fluxos configuráveis do cliente; o motor de automações permanece pertencendo ao CRM Imagine.

## 6. Branch e processo CANDIDATOS para eventual execução posterior

- **Branch-base:** dev/ft01-aia-core-mocks-2026-11-06.
- **Branch FILHA PROPOSTA, AINDA NÃO CRIADA:** dev/ft01-f1-03-resilience-mocks-2026-11-08.
- A futura criação da branch de código, qualquer commit PHP e execução de testes dependem de **autorização executiva separada** do responsável, com escopo e permissão de testes expressos.
- Após autorização, executor deve verificar Git autenticado e os SHAs, ler AGENTS.md, STATUS-ATUAL.md e as especificações R01/R02/R03/R04 e F1-01/F1-02, confirmar limpeza de árvore e inexistência de branch conflitante; parar diante de divergências.
- PR futuro de código deve ser **draft**, com destino SOMENTE branch-base, sem merge automático. Merge exige nova aprovação independente após revisão e validação.
- Nenhuma alteração permitida em main, src/Contracts/, CRM/Portal ou infraestrutura e nenhuma programação de F1-04.

## 7. Decisões FT01-F103-D01 a D10 — ABERTAS para ratificação documental

| Decisão | Alternativa A recomendada | Alternativa B não recomendada |
| --- | --- | --- |
| **F103-D01 — propósito** | Simular falhas compostas e classificação conservadora de efeitos | Liberar recuperação operacional real |
| **F103-D02 — replay** | Reprocessar somente interpretação de eventos em leitura | Reexecutar ações ou approvals no replay |
| **F103-D03 — evidências** | Rastro mínimo e detector de conflitos, sem payload integral | Copiar prompts, leads ou stack traces |
| **F103-D04 — falhas** | Injectores determinísticos e relógio explícito | Temporizadores, rede ou caos em serviços reais |
| **F103-D05 — estados** | Indeterminação e reconciliação fake sem retry de submit | Presumir sucesso/fracasso no timeout |
| **F103-D06 — isolamento** | Tenant/produto/versão vinculados a fixtures sintéticas | Aceitar cross-tenant por conveniência |
| **F103-D07 — regressão** | Propor 12 novos casos + preservar 22 existentes; validar só se autorizado | Declarar aceite sem executar testes |
| **F103-D08 — custos/stack** | PHP puro, sem instalações, Actions, despesas ou VPS | Introduzir infraestrutura/pacotes externos |
| **F103-D09 — Git/gates** | PR draft filha → dev, main e gates preservados | Merge em main, F1-04 ou fechamento tácito |
| **F103-D10 — governança** | Aprovar somente esta documentação e pedir autorização executiva própria | Interpretar aprovação documental como ordem ao Codex |

**ESTADO: F103-D01 a D10 TODAS ABERTAS.** Ratificar as alternativas A não autoriza código, teste, instalação, APIs, gastos, merge nem F1-04.

## 8. Critérios de aceite DOCUMENTAL para este PR

- [ ] Responsável revisar e ratificar F103-D01 a D10 como diretrizes documentais.
- [ ] Confirmar que F1-03 permanece limitado a simulações sintéticas sem efeitos reais.
- [ ] Aprovar separadamente, se desejado, uma ordem executiva futura que cite branch filha, arquivos permitidos e execução de 34 testes locais sintéticos.
- [ ] Somente após autorização executiva: Codex trabalha em branch isolada, PR draft e testes sob limites.
- [ ] Revisar resultados reais antes de considerar merge em dev; não estender ao F1-04.
- [ ] Preservar main, R01-G01 a G11 e CORE/SEG/AUT CRM ABERTOS, zero alterações de infra e zero despesas.

**Próximo passo:** apresentar esta proposta para revisão do responsável. Este arquivo NÃO é uma autorização executiva.
