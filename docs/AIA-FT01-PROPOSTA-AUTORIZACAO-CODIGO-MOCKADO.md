# AIA-FT01 — Proposta de Autorização Controlada do Primeiro Código Mockado | v0.1

> **ESTADO: PROPOSTA DOCUMENTAL NÃO APROVADA.** As decisões **FT01-D01 a D10 estão ABERTAS**. Este arquivo **NÃO AUTORIZA CODIFICAÇÃO, TESTES, DEPENDÊNCIAS, ACESSOS OU DEPLOY**.
> **Data:** 2026-11-06. **Repositório permitido se houver futura aprovação:** `rafhael100/imagine-ai-agents`.
> **Precedência:** AIA-01–AIA-12 funcionais, AIA-R01, AIA-R02/S01–S05/R01–R06, AIA-R03/S01–S05/R01–R02 e AIA-R04/S01–S02 ratificados somente em caráter documental; **nenhum gate técnico homologado**.
> **Preservar:** R01-G01 a G11 **TODOS ABERTOS**, CORE/SEG/AUT CRM e demais gates próprios do CRM **ABERTOS**, `main` de todos os repositórios, infraestrutura, credenciais, contratos e dados privados.

## 1. Decisão que será pedida ao responsável

Depois de avaliar esta proposta, o responsável poderá **autorizar expressamente uma etapa mínima de código somente em branch isolada**, limitada a modelos de domínio, interfaces locais e dados sintéticos, com restrições e revisão. A mera aprovação de R03/R02, ou o merge deste PR **documental**, **não concede essa autorização**.

**Interpretação obrigatória de atos:**
- `aprovar esta proposta documental` → apenas ratifica o plano e mantém código bloqueado, salvo autorização explícita adicional.
- `autorizar expressamente FT01-F1, nos limites do documento` → libera **somente o primeiro lote de código mockado** no repositório e branch aprovados, não libera implementação de API CRM, testes funcionais reais ou produção.
- `aprovar merge de PR de código` → somente mediante instrução específica posterior; não inferir autorização a partir de aceite de FT01.

## 2. Justificativa, riscos e stack candidata

A proposta sugere como **hipótese inicial de implementação isolada** a família **PHP/Laravel**, aproveitando compatibilidade conceitual com o backend planejado do CRM e familiaridade operacional. É uma **escolha proposta para o primeiro lote**, não seleção/homologação da stack de produção. TypeScript/Node e Python seguem alternativas documentadas na R03/R02.

**Antes de qualquer instalação**: verificar versão e suporte efetivo de PHP/Laravel, compatibilidade de dependências, licença e capacidade da estação/ambiente Codex. Não alegar que um framework ou versão está homologado por esta proposta. Se não houver ambiente autorizado ou versão verificável, produzir relatório de bloqueio; não provisionar servidor.

**F1 proposto:** autorizar estrutura PHP compatível com adoção futura de Laravel, sem obrigar instalação inicial de dependências externas; o plano de dependências deve ser apresentado previamente se forem necessárias. Não escolher PostgreSQL/Redis, nem criar migrations/SQL, filas operacionais ou integração com banco neste primeiro lote.

## 3. Branch, pré-verificação e limites de superfície

**Branch futura proposta (não criar antes da autorização):**
`dev/ft01-aia-core-mocks-2026-11-06` baseada em `main`, após checagem da SHA vigente.

**Preflight F1-00 (proposto):**
1. Codex lê `AGENTS.md`, `README.md`, `STATUS-ATUAL.md`, R02/R03, R04/S01–S02, R03/R02 e este AIA-FT01.
2. Confirma repositório exato e branch de base, SHA e acesso GitHub **somente leitura**, sem inventar links, pull requests ou permissões.
3. Verifica que não há arquivos/código pré-existentes a sobrescrever; identifica diretórios, arquivos e dependências.
4. Confirma ausência de segredos reais e ausência de configuração de endpoints externos no escopo permitido.
5. Se autenticador Git recusar acesso ou `main` estiver divergente, **parar e relatar o impedimento**, sem burlar credenciais, criar repositório substituto ou escrever em branch desconhecida.
6. Após preflight, a execução de F1-01 exige autorização FT01-F1 expressa; o preflight não autoriza merge/deploy.

**Superfícies a permitir sob autorização específica de F1:**
- `src/Domain/` — value objects/interfaces e modelos puros de execução, identidade de tenant sintético e estados;
- `src/Contracts/` — interfaces mockadas para leitura CRM, confirmação de efeito e provedor IA simulado;
- `src/Mocks/` — doubles locais e fixtures geradas, sem dados privados;
- `tests/Unit/` — somente testes unitários **explicitamente autorizados** com dados sintéticos e sem rede;
- `README.md` — instrução para executar testes/mock localmente e limitações de segurança;
- `STATUS-ATUAL.md` — registrar lote, SHA, bloqueios e evidências reais, quando houver;
- arquivo local de configuração de projeto mínimo **somente se aprovado na autorização** e sem instalação de dependências.

Estas pastas são **propostas**, não presentes ou aprovadas para escrita ainda. `AGENTS.md` deve continuar como guardrail; **não alterá-lo automaticamente** para liberar código. Qualquer arquivo fora do escopo exige pedido e autorização específicos.

## 4. Primeiro lote F1-01: domínios e interfaces mockados

Entregável previsto **apenas depois de autorização futura**:
- `Run`, `Step`, `Intent`, `Approval`, `Attempt`, `Effect` e `Usage` como objetos/modelos de domínio com identidades de teste e enumerações candidatas documentadas.
- `TenantContext` com referências sintéticas que preservem produto, organização, principal, finalidade, recurso e versão, **sem implementar ou alegar ACL real**.
- `MockCrmReader` retornando subconjuntos sintéticos de lead/atendimento por finalidade e tenant, sem conexão ao CRM.
- `MockCrmEffectGateway` simulando resultados `CONFIRMADO`, `NEGADO`, `PENDENTE` e `INDETERMINADO`, com deduplicação em memória somente para estudo; **não alegar idempotência de produção**.
- `MockAiSuggestionProvider` gerando saída **determinística e sintética**, sem chamada a IA real, tokens ou biblioteca externa.
- Validador puramente local de versões/IDs/tenant sintético e estados propostos, sem token JWT, OAuth, mTLS, senha ou segredos reais.
- Erros tipados locais de versão, tenant simulado, intenção conflitante e ausência de aprovação, com documentação sobre a diferença para a autorização do CRM.
- Fixtures claramente marcadas `SYNTHETIC`, sem dados de cliente, pessoa, e-mail/telefone real nem CPF/CNPJ verdadeiro.

**Não entregar:** servidor HTTP público, webhooks, fila real, SQL, migration, conexão DB, OAuth funcional, worker produtivo, agentes autônomos, processamento RAG de documentos de clientes ou painel operacional.

## 5. Testes unitários sintéticos F1-01 — só após autorização expressa

Se o responsável incluir testes unitários na futura autorização, permitir apenas execução local, sem rede, de casos puros como:
1. tentativa de consulta com `tenant_A` e recurso sintético `tenant_B` → negado pelo **mock**;
2. run concluída com efeito ainda `INDETERMINADO` → não informar efeito confirmado;
3. intenção com mesma chave e parâmetros materiais distintos → conflito no **mock**;
4. timeout sintético após efeito simulado → consulta simulada não duplica a tarefa;
5. aprovação expirada/ausente no caso sintético → impedir continuação do **mock**;
6. geração de sugestão → não cria tarefa e não grava histórico;
7. cancelamento → bloqueia passos futuros sem apagar efeito sintético previamente confirmado;
8. versão lógica incompatível → erro seguro;
9. separação entre uso técnico e crédito comercial → nenhuma mutação de crédito;
10. input malicioso simulado em um campo de histórico → dado tratado como dado, não instrução de autoridade.

**Esses testes não provam:** aderência LGPD real, isolamento de produção, autenticação/autorização CRM, deduplicação transacional em banco, segurança de rede ou autorização para deploy. A documentação do PR de código deve listar explicitamente essas limitações.

## 6. Critérios de revisão e encerramento do lote

Na eventual execução autorizada:
- PR de código **não mergeado**, base `dev/ft01-aia-core-mocks-2026-11-06` ou fluxo escolhido pelo responsável; confirmar a origem e a base antes de abrir.
- Inventário preciso de arquivos/SHAs e justificativa das alterações.
- Ausência de acessos à rede, dados privados, secrets, dependências baixadas sem autorização ou comandos de infraestrutura.
- Evidências dos testes **efetivamente executados** somente se incluídos na autorização, com ambiente/versões e resultados; não inventar testes.
- Revisão de vazamento de dados e introdução de privilégios/escopos entre tenants em código mockado.
- Relatório claro de limitações, APIs inexistentes, gates R01-G01 a G11 abertos e CRM CORE/SEG/AUT abertos.
- Nenhum commit ou merge em `main` além de novos documentos especificamente autorizados.
- Próximo lote F1-02 só pode ser proposto **depois da revisão de F1-01** e de nova autorização.

Se houver falha de Git autenticado, ferramentas indisponíveis, risco de overwrite, dúvidas de autorização, versões incompatíveis ou execução que necessite API/rede, **parar e reportar**, não substituir escopo por improvisação.

## 7. Condições de encerramento negativo e rollback

**Stop conditions:** dados pessoais/segredos reais encontrados na superfície de teste, capacidade de executar mutação CRM real, fallback para endpoint público, criação de usuário/conta, necessidade de instalar VPS/Cloudflare/n8n ou alteração em outro repo, execução não permitida de testes/SQL, problemas de Git ou conflito com branch-base.

Rollback neste lote = descartar alterações do **ramo isolado** mediante procedimento autorizado e preservar registros auditáveis; **não tocar em tarefas ou dados CRM**, já que nenhuma integração com efeito real é permitida.

## 8. Decisões FT01-D01 a D10 — propostas, NÃO APROVADAS

| ID | Tema | Alternativa A — recomendada | Alternativa B |
| --- | --- | --- | --- |
| **FT01-D01** | Natureza da autorização | Preparar e submeter **autorização específica para código isolado**, distinta do aceite documental deste PR | Tratar aprovação do documento como execução automática |
| **FT01-D02** | Linguagem inicial | Propor PHP com estrutura de domínio compatível com Laravel para o primeiro mock; versões/dependências exigem verificação, sem comprometer stack de produção | Escolher linguagem de produção definitiva sem comparação |
| **FT01-D03** | Branch e repo | Restringir futuro F1 à branch `dev/ft01-aia-core-mocks-2026-11-06` no repo Imagine AI Agents; `main` intocada | Programar diretamente na `main` ou no CRM |
| **FT01-D04** | Pré-verificação Git | Codex deve comprovar acesso autenticado e SHA, ler guardrails e parar se houver falha de acesso | Burlar bloqueio de credenciais ou alegar PR inexistente |
| **FT01-D05** | Primeiro lote | Limitar F1-01 a modelos puros, interfaces e mocks CRM/IA, sem endpoints, banco ou serviços reais | Implementar agentes integrados com sistemas reais desde o início |
| **FT01-D06** | Testes autorizáveis | Admitir **somente testes unitários locais com fixtures sintéticas**, se autorização posterior os incluir expressamente | Rodar testes funcionais reais e tratar mocks como homologação |
| **FT01-D07** | Privacidade e acesso | Proibir dados pessoais reais, segredos, egress, provedores pagos, credenciais e acesso aos outros produtos | Copiar base CRM ou usar APIs de produção |
| **FT01-D08** | Revisão e merge | Revisão por PR de código isolado, sem merge automático nem deploy; lote F1-02 depende de nova autorização | Liberar tudo pela aprovação geral do fast-track |
| **FT01-D09** | Gates | Manter R01-G01 a G11 e CORE/SEG/AUT CRM abertos; mocks não fecham gates | Declarar integração/segurança homologadas pelos testes puros |
| **FT01-D10** | Próximo ato | Após aprovar o **plano documental** FT01, pedir expressamente **“Autorizo FT01-F1-01 nos limites ...”** antes de orientar Codex a programar | Codificar apenas por aceite documental genérico |

**FT01-D01 a D10: TODAS ABERTAS**. Aprovação de alternativas A como diretrizes documentais não vale como autorização da execução F1-01. A eventual autorização de código deverá ser **explícita e inequívoca**, mencionar repositório, branch, escopo e testes permitidos.

## 9. Pendências para liberação específica futura

- [ ] Revisar FT01-D01 a D10 e decidir política do primeiro lote.
- [ ] Escolher formalmente a linguagem inicial para este mock, verificar versão/licença e dependências; **não decidir stack de produção por inferência**.
- [ ] Ratificar escopo de arquivos e limites de ambiente/branch, sob Codex com acesso validado.
- [ ] Decidir explicitamente se testes unitários sintéticos ficam permitidos durante F1-01.
- [ ] Conceder separadamente autorização textual para Codex executar F1-01; caso contrário **não executar**.
- [ ] Preservar `main`, docs/PRs e outros repositórios, e todos os 11 gates.
- [ ] Exigir nova decisão para qualquer dependência de Composer, PHP runtime, infraestrutura, APIs, CRM real ou gastos além dos limites permitidos.

**Próximo passo recomendado:** validar esta proposta e solicitar **aprovação documental FT01-D01 a D10**. Depois, se desejado, emitir um comando preciso de **autorização FT01-F1-01** ao Codex para o primeiro lote mockado. Nenhuma das etapas é automática.

**Registro:** documentação ≠ homologação ≠ autorização de código ≠ teste de integração ≠ produção.
