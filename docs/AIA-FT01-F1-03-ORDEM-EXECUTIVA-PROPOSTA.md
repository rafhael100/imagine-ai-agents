# AIA-FT01/F1-03 — Proposta de autorização executiva restrita | v0.1

> **ESTADO: PROPOSTA EXECUTIVA, NÃO AUTORIZADA.** O responsável aprovou **apenas as diretrizes documentais F103-D01 a D10 (alternativas A)** em 2026-11-08. Aprovação documental, criação/merge de PRs documentais, e esta minuta não autorizam Codex a programar F1-03.
>
> Repositório: `rafhael100/imagine-ai-agents`. Branch documental: `docs/ft01-f1-03-autorizacao-executiva-proposta-2026-11-08`; base: `dev/ft01-aia-core-mocks-2026-11-06`.
>
> **Gates vinculantes:** R01-G01–G11 TODOS ABERTOS e CORE/SEG/AUT CRM ABERTOS. Sem alteração da `main`, outros repositórios, servidores ou sistemas reais.

## 1. Fundamento e limites da futura execução

- **F1-01:** PR #71 integrado somente na branch dev; modelos PHP puros e mocks.
- **F1-02:** PR #76 integrado somente na branch dev; `SupervisedCoordinator`, `MockSupervisedGateway`, 12 casos sintéticos. Resultado histórico de validação independente: 19/19 PHP sem erros de sintaxe e 22/22 testes unitários sintéticos aprovados em PHP 8.4.24.
- **F1-03:** PR #78, merge documental via squash `6ed2e77b9d3366252a5258ffd4f2dcdad4b43de4`, ratificou alternativas A F103-D01–D10 e planejou 12 novos cenários. **Isso não é autorização para escrever código, iniciar testes ou subir infraestrutura.**
- **Base verificada antes desta minuta:** `dev/ft01-aia-core-mocks-2026-11-06` em `6ed2e77b9d3366252a5258ffd4f2dcdad4b43de4`; `main` preservada em `ec305688528fe35e9a9dc2d3b1bda1255cbd074a`.

Se autorizado futuramente, o F1-03 será um **laboratório de resiliência e auditoria sintéticas**: simular falhas compostas, identificar evidências insuficientes, classificar estado como indeterminado quando aplicável, e reproduzir **somente a interpretação de rastros**, sem repetir efeitos ou efetuar chamadas de produção.

## 2. Branch e política de trabalho propostas — NENHUMA BRANCH DE CÓDIGO CRIADA

| Parâmetro | Valor proposto |
| --- | --- |
| Repositório autorizado futuramente | `rafhael100/imagine-ai-agents` |
| Branch-base de desenvolvimento | `dev/ft01-aia-core-mocks-2026-11-06` |
| Branch filha futura — ainda não criada | `dev/ft01-f1-03-resilience-mocks-2026-11-08` |
| Origem | SHA remoto da base aferido por Git autenticado no início da execução |
| PR de implementação | **Draft** da filha → branch dev; **sem merge** |
| `main` | Absolutamente inalterada |
| F1-04 | Não autorizado |

A branch de código **só pode ser criada após autorização executiva expressa separada**; antes, confirmar que não existe. Nunca sobrescrever branch existente, fazer force-push, alterar `main` ou mergear por dedução do aceite documental.

## 3. Preflight obrigatório caso haja autorização futura

1. Conferir autenticação GitHub e remoto: `gh auth status`, `git remote -v`, `git ls-remote`; conferir `git status`, branch ativa, SHA da `main`, base e eventual branch filha. Se houver conflito, parar.
2. Ler `AGENTS.md`, `README.md`, `STATUS-ATUAL.md`, documentação R01/R02/R03/R04, contratos F1-01/F1-02 e os dois documentos F1-03 (proposta e esta ordem).
3. Garantir que o F1-02 já consta da base, os **22 casos anteriores permanecem íntegros** e nenhum compromisso com API real foi implicitamente concedido.
4. Verificar se `php` 8.1+ **já está disponível** no ambiente de execução; **não instalar** PHP, Composer, PHPUnit, containers, extensões ou SDKs. Se PHP não existir, documentar lint/testes **não executados**, sem alegar sucesso.
5. Declarar executor real, ambiente, SHA inicial, arquivos planejados e quaisquer pré-requisitos que impeçam a execução. Nenhum acesso a dados reais.

## 4. Código PHP puro FUTURO — superfícies limitadas

| Superfície | Permitido sob autorização específica | Fora de escopo |
| --- | --- | --- |
| `src/MockResilience/` | Motor determinístico **somente local** de injeção de falhas, verificador de sequência de eventos e analisador replay **read-only** | Reexecutar `submit`, executar aprovações, alterar efeito ou disparar job |
| `src/Mocks/` | Mocks de respostas de `status`/timeout e eventos sintéticos, sem acesso à rede ou sistema operacional | Alterar `MockSupervisedGateway` ou outros componentes existentes sem justificativa/documentação |
| `src/Domain/` | Novos objetos de valor pequenos e puros se indispensáveis; preservar interfaces existentes | Autoridade efetiva de ACL, tokens reais, persistência |
| `tests/Unit/` | Até 12 testes novos F1-03 e inclusão mínima no runner, com regressão dos 22 F1-01/02 | Testes de integração, CI, GitHub Actions, serviços externos |
| `README.md` e `STATUS-ATUAL.md` | Registrar limites, commits, estados e **resultados realmente executados** | Afirmar homologação ou fechar gates |

**Não modificar** `AGENTS.md`, `src/Contracts/`, `src/MockOrchestration/SupervisedCoordinator.php`, documentação previamente ratificada, `.github/workflows/`, `main` ou projetos CRM/Portal/Sites/Performance/Universidade sem decisão independente. Se for imprescindível modificar a lógica existente, parar e apresentar justificativa e novo pedido de escopo.

Não criar `composer.json`, lockfiles, migrations, SQL, `.env`, ações de CI, Docker, endpoints HTTP, clientes LLM/CRM reais, instalações, credenciais, arquivos persistentes ou repositórios auxiliares.

## 5. Invariantes exigidos no laboratório

1. **Fail-closed:** erro de transporte ou da consulta mantém o resultado `INDETERMINADO` quando a prova não é suficiente. Nunca inferir sucesso ou fracasso.
2. **Zero replay de mutação:** a análise recebe dados sintéticos e retorna classificação; não injeta um `CrmEffectGateway` capaz de chamar `submit` nem usa objeto `Approval` para disparar ação.
3. **Sem duplicidade:** toda simulação de timeout/estado desconhecido observa contadores para demonstrar que **nenhum novo submit** foi gerado.
4. **Prova vs interpretação:** `Run::CONCLUIDA`, eventos de trace e conclusão de passo são insuficientes para confirmar o efeito fake sem evidência elegível.
5. **Integridade não criptográfica:** hashes e sequência verificam consistência da fixture; não equivalem a autenticidade, prova legal, cadeia de custódia ou anonimização em produção.
6. **Isolamento lógico:** tenant/produto/finalidade/versão de fixtures inconsistentes recusados; contexto sintético não constitui login ou ACL real.
7. **Eventos hostis como dados:** texto adversarial, payload de exemplo e código desconhecido não instruem o sistema nem concedem permissão.
8. **Relógio explícito:** tempos de aprovação e sequência são entradas reproduzíveis, nunca relógio implícito, tarefa agendada ou temporizador real.
9. **Recuperação limitada:** perda de memória entre processos deve revelar insuficiência de evidência, não criar falso estado persistente, exactly-once ou compensação.
10. **Traços mínimos:** sem prompts, leads, dados pessoais, stack traces, tokens ou exceções brutas; no máximo identificadores sintéticos e códigos fechados.
11. **Compatibilidade:** preservar os 22 testes existentes sem alteração oportunista. Se precisar mudar regressões para fazê-las passar, bloquear e pedir revisão.
12. **Auditoria sem fechamento de gates:** nenhum teste mockado comprova ACL, conformidade LGPD, concorrência, idempotência durável, integração CRM real ou segurança de produção.

## 6. Escopo de testes futuros — 12 novos + 22 regressões = 34

Os 12 casos **U03-01 a U03-12**, documentados no PR #78, deverão ser implementados **somente após autorização expressa F1-03**:

| ID | Resultado exigido no ambiente exclusivamente sintético |
| --- | --- |
| U03-01 | Resposta perdida + consulta status falha → estado indeterminado, sem reenviar |
| U03-02 | Consultas status repetidas após falha → zero novo submit |
| U03-03 | Rastro truncado → não declarar confirmação |
| U03-04 | Evento duplicado conflitante → erro ou revisão explícita, sem sobrescrita |
| U03-05 | Ordem temporal inválida → análise conservadora |
| U03-06 | Replay que contenha comando de mutação → rejeitar sem chamar gateway |
| U03-07 | Run cancelada com efeito antes confirmado → preservar prova, impedir novas etapas |
| U03-08 | Contexto incompatível de tenant/produto/finalidade → negar sem vazar |
| U03-09 | Aprovação expirada ou revogada → bloquear nova execução |
| U03-10 | Chave idempotente com material divergente → conflito/UNKNOWN, sem novo envio |
| U03-11 | Reinício simulado sem memória comum → não prometer recuperação durável |
| U03-12 | Instrução hostil ou token sintético na entrada → nenhum vazamento em trace |

**Validação futura, condicional:** em ambiente já provido de PHP 8.1+, sem instalar nada:
```sh
find src tests/Unit -type f -name '*.php' -print0 | xargs -0 -n1 php -l
php tests/Unit/run.php
```
Meta: **34 casos PASS e zero erros de sintaxe**, mas **não presumir** sucesso; informar versão, SHA, saída e falhas. Caso PHP indisponível, relatar **34 casos não executados** (sem instalar, sem Actions) e manter aceite bloqueado até validação posterior expressamente autorizada.

## 7. Condições de interrupção

Parar e relatar, sem prosseguir por conta própria, diante de qualquer necessidade de:
- alterar contratos CRM ou infraestrutura real, executar efeitos reais ou gravação;
- instalar PHP, Composer, dependências, executar GitHub Actions ou consumir minutos/despesas;
- alterar `src/Contracts/`, `SupervisedCoordinator`, `AGENTS.md`, `main` ou outro repositório;
- usar credenciais, segredos, dados pessoais, APIs LLM/CRM, serviços pagos, cloud, banco, filas ou SQL;
- usar branch não autenticada, detectar divergência no remoto, testes sem runtime ou conflito de estado;
- iniciar F1-04, fechar gates R01/CORE/SEG/AUT ou efetuar merge/deploy.

## 8. Entregáveis para revisão de PR de código FUTURO

- Um PR **draft**, filho `dev/ft01-f1-03-resilience-mocks-2026-11-08` → base dev, **sem merge**.
- Diff apenas das superfícies autorizadas, com alteração mínima das regressões.
- Matriz de 12 novos cenários, inventário dos 22 anteriores, comandos/saídas realmente executados, versão PHP e SHA verificável.
- `README.md` e `STATUS-ATUAL.md` atualizados com bloqueios, não homologações, riscos e ausência de custos.
- Evidência de preservação da `main`, de todos os gates R01-G01–G11/CORE/SEG/AUT CRM e das demais aplicações.
- **Novo aceite específico** para qualquer merge futuro na branch dev. Nunca fazer merge sem essa decisão.

## 9. Modelo para decisão executiva separada — AINDA NÃO APROVADO

O responsável poderá emitir uma mensagem específica como:

> **Autorizo expressamente o AIA-FT01-F1-03 para desenvolvimento puramente mockado**, exclusivamente em `rafhael100/imagine-ai-agents`, criando `dev/ft01-f1-03-resilience-mocks-2026-11-08` a partir da branch `dev/ft01-aia-core-mocks-2026-11-06` após preflight Git autenticado. Autorizo código PHP puro determinístico de falhas compostas, análise read-only de rastros e auditoria sintética nas superfícies `src/MockResilience/`, `src/Mocks/`, `src/Domain/`, `tests/Unit/`, `README.md` e `STATUS-ATUAL.md`. Permito até 12 testes sintéticos novos U03-01–U03-12 e regressão dos 22 existentes onde PHP 8.1+ já estiver disponível; se faltar, não instalar, registrar testes não executados. Quero PR draft filha → dev, sem merge. Não autorizo mudanças em `main`, `src/Contracts/`, `SupervisedCoordinator`, `AGENTS.md`, outras aplicações, SQL/ALLOW, APIs reais, dados privados, credenciais, Composer/PHP instalado, GitHub Actions, despesas, servidores, deploy, F1-04 ou fechamento dos gates R01-G01–G11 e CORE/SEG/AUT CRM. Interromper em caso de conflito e apresentar resultados reais.

O bloco acima é um **modelo**, não aprovação executiva atual. O responsável deverá aprovar **expressamente a execução do F1-03 em decisão posterior**; mesmo que este PR documental seja merged, isso não libera Codex nem criação de branch de código.

## 10. Próximo ato solicitado

- [x] F103-D01 a D10 alternativas A ratificadas **somente documentalmente**, PR #78.
- [ ] Revisar e, se apropriado, aprovar **apenas como minuta técnica** esta autorização executiva proposta.
- [ ] Emitir decisão executiva expressa separada para autorizar F1-03 e testes.
- [ ] Somente depois, criar branch de código, executar Codex, revisar PR e validar 34 testes.
- [ ] Obter autorização específica para eventual merge em dev. `main` e F1-04 bloqueados.

**Estado vinculante:** planejamento F1-03 aprovado documentalmente; **nenhuma programação F1-03 autorizada**. R01-G01–G11 e CORE/SEG/AUT permanecem ABERTOS.
