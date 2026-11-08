# AIA-FT01/F1-02 — Proposta de Coordenação Supervisionada e Recuperação Simulada | v0.1

> **ESTADO: FT01-F102-D01 a D10 APROVADAS DOCUMENTALMENTE em 2026-11-08**, alternativas A, como diretrizes preliminares. **F1-02 NÃO AUTORIZADO PARA PROGRAMAÇÃO**; F1-01 não autoriza F1-02. R01-G01 a G11 e CORE/SEG/AUT CRM continuam ABERTOS.
>
> **Data:** 2026-11-07. **Repo:** `rafhael100/imagine-ai-agents`. **Base documental:** `dev/ft01-aia-core-mocks-2026-11-06`, após PR #71. **Branch desta proposta:** `docs/ft01-f1-02-proposta-orquestracao-mock-2026-11-07`.
>
> **Guarda permanente:** `main` intacta; R01-G01 a G11 e CORE/SEG/AUT CRM **TODOS ABERTOS**. Sem código, merge de código, testes nesta etapa documental, SQL, ALLOW, banco, fila, API real, modelos pagos, credenciais, dados pessoais, VPS/Cloudflare/n8n, GitHub Actions ou deploy.

## 1. Objetivo, precedência e fatos verificados

O **F1-01 foi executado e incorporado apenas à branch de desenvolvimento** via [PR #71](https://github.com/rafhael100/imagine-ai-agents/pull/71), commit de merge `d419d481af9fbaea0e1baa60094ddcc799fb72b4`, seguido de atualização documental da branch dev. Em validação independente com PHP 8.4.24, **16/16 arquivos PHP passaram no lint e 10/10 cenários sintéticos passaram**. Não há autorização de `main`, nem comprovação de ACL/CRM real. O workflow Actions provisório foi removido sem execução e sem consumo de minutos.

O F1-01 possui `Run`, `Step`, `Intent`, `Approval`, `Attempt`, `Effect`, `Usage`, `TenantContext` e `MockError`, interfaces locais `CrmReader`, `CrmEffectGateway`, `AiSuggestionProvider`, três mocks locais e runner simples em `tests/Unit/run.php`. É um **laboratório sintético**, não o SaaS funcional.

**Propósito proposto de F1-02:** acrescentar uma **camada pequena, pura e determinística de coordenação supervisionada dos mocks existentes**, que transforme estados, aprovações e efeitos em resultados observáveis, sem dar à camada de IA autoridade para mutações não autorizadas. Não adicionar capacidades reais, scheduler, motor de automações do CRM ou dependências externas.

A aprovação deste documento apenas aprova diretrizes para posterior pedido de autorização. **O usuário precisa aprovar separadamente um comando executivo F1-02** citando repo, branch de código, permissões de testes e limites negativos antes de iniciar Codex.

## 2. Escopo positivo CANDIDATO para futuro lote, após autorização própria

| Unidade proposta | Responsabilidade simulada | Proibição específica |
| --- | --- | --- |
| `LocalRunCoordinator` ou nome equivalente, `src/MockOrchestration/` | Encadear, em memória, `Run → Step → Intent → aprovação → solicitação fake → status fake` e produzir resultado tipado | Não ser motor distribuído, worker, job real, scheduler ou automação configurável pelo cliente |
| `RunOutcome` / `Decision` / `TraceRecord`, `src/Domain/` | Representar resultado com `run_ref`, tenant, intenção, decisão, status e motivo seguros | Não afirmar efeito CRM real, autorização real ou auditoria homologada |
| `MockApprovalPolicy`, `src/Mocks/` | Simular alçada, aprovação requerida/negada/revogada e expiração por **relógio de entrada** | Não criar política CRM verdadeira nem contornar `Approval::assertValid` |
| `MockEffectReconciler`, `src/Mocks/` | Consultar o `CrmEffectGateway::status` existente depois de timeout/indeterminação **sem reenviar cegamente** | Não criar API externa, retries automáticos nem mecanismo de persistência |
| `MockTraceCollector`, `src/Mocks/` | Coletar somente IDs e códigos sintéticos de run/step/intenção/estado com ordem determinística | Não salvar conteúdo de leads, prompts completos, segredos, logs de produção |
| `tests/Unit/` | Novos testes unitários sintéticos, sem alterar os 10 testes existentes de F1-01 além de correções justificadas | Não rodar integração, acesso de rede ou GitHub Actions; não afirmar homologação |
| `README.md`, `STATUS-ATUAL.md` | Explicar limites do coordenador e documentar testes **efetivamente** executados | Não dizer que todos os gates estão aprovados |

Os nomes das classes e pastas são **candidatos**. Antes de implementá-los, revisar `AGENTS.md`, contratos aprovados e responsabilidades do CRM; reduzir arquivos se duplicarem comportamento do F1-01.

### Fluxo mínimo e invariantes

1. Receber um `TenantContext` **sintético** e uma intenção material de teste associada ao `Run`, sem interpretar o prefixo SYNTHETIC como autenticação.
2. A operação de leitura gera uma projeção mínima do fake CRM, sob finalidade/campos permitidos; texto de origem pode conter instruções maliciosas, mas continua sendo **dado**.
3. Gerar uma sugestão fake determinística **sem efeito de gravação**. A classe de sugestão não deve possuir referência que lhe permita chamar o gateway de mutação.
4. Se uma ação exigir aprovação, obter o objeto simulado de alçada para **exatamente a mesma intenção/material/contexto e janela temporal**, revalidando antes da mutação fake.
5. Decisão `DENY`, `APPROVAL_REQUIRED`, `REVOKED`, `TIMEOUT` ou `INDETERMINATE` não pode virar `CONFIRMED` por inferência, retorno de modelo ou término do run.
6. Efeitos confirmados no fake precisam da mesma prova **sintética** já exigida em `Effect`, nunca de uma alegação arbitrária do coordenador.
7. Repetição de uma intenção deve consultar o fake para distinguir duplicidade/conflito. **Nunca implementar retentativas não idempotentes** ou prometer atomicidade de banco; idempotência continua em memória.
8. Cancelamento impede passos futuros; não apaga efeitos mockados confirmados. Reabertura de estados terminais deve ser negada.
9. Tentativas, aprovação e efeito mantêm ciclos e estados distintos; não confundir status de transporte com status de negócio.
10. Em caso de exceção, coletar apenas identificador sintético e código seguro; sem registrar material sensível, stack traces ou payload completo.

## 3. Contratos locais e compatibilidade

- Reaproveitar `ImagineAI\Contracts\*` já existentes e os modelos do F1-01; **não alterar contratos CRM reais**.
- Não modificar `CrmEffectGateway::submit` ou `status` para contornar validações. Se faltarem tipos, propor extensão local **retrocompatível** em interfaces próprias sem exigir mudança no F1-01.
- O coordenador não executa SQL, não constrói endpoints, não emite HTTP, não contém cliente de IA real e não escolhe provedor/linguagem de produção.
- Rejeitar explicitamente versões desconhecidas; `mock-v1` permanece rótulo conceitual, **não** um protocolo oficial versionado.
- Priorizar **injeção de dependências manual** e relógio explícito; sem Composer, Laravel instalado, autoload remoto ou pacotes de teste.
- Reutilizar PHP puro com sintaxe compatível com **PHP 8.1+ como alvo**, sem alegar compatibilidade antes da execução. O ambiente Codex anterior não tinha PHP e não deve instalar nada para contornar isso.

## 4. Testes sintéticos propostos — não executados nem autorizados por este documento

Além de preservar os **10 testes de regressão do F1-01**, propõem-se **12 casos novos** para um futuro lote autorizado:

| ID | Cenário de teste | Resultado exigido no mock |
| --- | --- | --- |
| U02-01 | Fluxo autorizado com intenção e aprovação da mesma versão | Efeito fake confirmado, com referência sintética de origem |
| U02-02 | Somente sugestão; texto de fonte contém "ignore rules" | Nenhuma gravação, tarefa ou elevação de privilégios |
| U02-03 | Aprovação ausente/pending/negada | Negação segura sem alteração do fake |
| U02-04 | Aprovação válida, depois revogada antes da tentativa | Bloqueio no instante de executar |
| U02-05 | Aprovação expirada, ou sobre outra intenção/material | Bloqueio por escopo/tempo |
| U02-06 | Run concluída, efeito `INDETERMINADO` | Resultado não confirmado, sem assumir sucesso |
| U02-07 | Timeout simulado seguido de consulta de status | Sem segundo `submit` nem efeito duplicado |
| U02-08 | Mesma chave com parâmetros materiais diferentes | `IDEMPOTENCY_CONFLICT` no fake |
| U02-09 | Tenant A tenta consultar/confirmar intenção B | Negação sem vazamento entre tenants |
| U02-10 | Run cancelada durante sequência | Não iniciar etapa posterior; não remover efeito anterior |
| U02-11 | Versão desconhecida e transição terminal inválida | Erros tipados; sem fallback silencioso |
| U02-12 | Trace do cenário autorizado/negado | Somente referências/códigos sintéticos; sem payload integral |

Nenhum resultado acima foi obtido ou testado: a tabela descreve **critérios futuros**. Se uma autorização separada permitir testes, devem ser executados em ambiente que **já possua PHP 8.1+** sem instalar dependências, registrando versão, saída, falhas e reexecução dos 10 originais. Não habilitar GitHub Actions automaticamente, pois consumo de minutos dependeria da franquia e dos limites da conta não verificados.

## 5. Branches, PR e preflight CANDIDATOS para execução futura

**Branch de trabalho proposta, NÃO CRIADA:** `dev/ft01-f1-02-supervised-mocks-2026-11-07`, criada a partir de `dev/ft01-aia-core-mocks-2026-11-06` **apenas após autorização expressa do F1-02**, com SHA e árvore conferidas.

Se autorizado, Codex deve confirmar Git autenticado, ausência de conflitos, leitura de `AGENTS.md`, `STATUS-ATUAL.md`, R02/R03, R04/S01–S02, AIA-FT01 e este documento. É vedado sobrescrever branch existente. Abrir **PR draft filha → branch de desenvolvimento**, sem merge. `main` permanece inalterada e F1-03 não é liberado automaticamente.

**Superfícies candidatas permitidas só após autorização:** `src/MockOrchestration/`, `src/Domain/` (tipos puros mínimos), `src/Mocks/` (simulações e auditoria sintética), `tests/Unit/`, `README.md` e `STATUS-ATUAL.md`. Não alterar `AGENTS.md` para relaxar gates, `docs/` aprovado, `main`, outros repositórios ou dados reais.

**Condições de interrupção:** necessidade de API real, instalação de PHP/Laravel/Composer, acesso a VPS, banco/fila real, GitHub Actions, token/custo de provedor, credencial, dúvida de ACL, divergência de branch ou ausência de capacidade de Git autenticado. Parar e relatar; nenhuma alternativa permissiva.

## 6. Critérios de revisão e aceitação separados

A execução futura exigirá:
- código estritamente mockado e diff dentro das superfícies;
- revisão dos invariantes contra R02/R03 e R04/S01–S02;
- teste de todos os **22 casos propostos no total** (10 regressões + 12 novos), **somente quando autorizados e realmente executados**, com saída e versão PHP; se PHP indisponível, registrar *não executado*;
- documentação clara de limitações: sem ACL, autenticação, pagamento, IA real, persistência, concorrência, trilha de auditoria de produção ou contrato CRM homologado;
- revisão humana do PR draft e autorização específica para eventual merge **somente na branch de desenvolvimento**;
- `main`, CRM, Portal, Sites, Performance e Universidade inalterados, gastos zero e gates abertos.

**Não equivale a homologação de sistema nem liberação do F1-03.** A governança F1-02 exige autorização para criar código e, posteriormente, autorização independente de merge.

## 7. Decisões FT01-F102-D01 a D10 ratificadas documentalmente

| Decisão | Alternativa A — recomendada | Alternativa B — não recomendada |
| --- | --- | --- |
| **FT01-F102-D01** — escopo | Planejar F1-02 como **coordenação supervisionada mockada**, sem execução real | Implementar agentes com ações externas neste lote |
| **D02** — responsabilidade | Reusar modelos/contratos do F1-01 e manter dono do efeito no CRM real | Duplicar motor de automações e políticas CRM |
| **D03** — aprovação | Simular aprovação vinculada à intenção exata, vencimento e revogação | Tratar silêncio/status de run como aprovação |
| **D04** — efeitos | Distinguir sugestão, transporte, status indeterminado e confirmação sintética | Inferir efeito de HTTP, IA ou timeout |
| **D05** — idempotência | Reconciliar por consulta local e falhar com segurança em conflito, sem retry cego | Reenviar mutação automaticamente |
| **D06** — isolamento | Projeções mínimas, referências/tenants sintéticos e logs sem conteúdo privado | Copiar leads ou prompts integrais para logs |
| **D07** — testes | Propor 12 novos testes locais mais regressão dos 10 originais, a autorizar depois | Pular execução, alegar homologação com mocks |
| **D08** — stack/custos | PHP puro, sem dependências instaladas, infra ou GitHub Actions automático | Introduzir Composer, servidores ou serviços pagos |
| **D09** — Git/revisão | Novo branch filho de dev, PR draft, `main` e gates intactos | Fazer merge automático em `main` ou iniciar F1-03 |
| **D10** — governança | Ratificar este **plano documental somente**; pedir autorização executiva própria antes do Codex | Interpretar aprovação desta proposta como permissão para programar |

**FT01-F102-D01 a D10: APROVADAS DOCUMENTALMENTE em 2026-11-08**, alternativas A, sem autorização executiva F1-02. A aprovação **NÃO AUTORIZA** criar branch de código, codificar, testar, usar Actions ou mesclar PR, e não fecha gates.

## 8. Próximas decisões separadas

- [x] Ratificação **documental** FT01-F102-D01 a D10 (alternativas A) pelo responsável em 2026-11-08.
- [ ] Preparar, submeter e obter **autorização executiva expressa** de F1-02 com branch, arquivos, restrições e testes sintéticos.
- [ ] Somente depois: orientar Codex a executar F1-02 e abrir PR draft, sem merge.
- [ ] Validar o diff e evidências antes de pedir aprovação independente para merge em branch dev.
- [ ] Manter `main`, R01-G01 a G11 e CORE/SEG/AUT **abertos e inalterados**.
- [ ] Não instalar dependências, usar serviços externos/cobráveis, dados pessoais, SQL, ALLOW, APIs reais ou infraestrutura.

**Próxima ação documental:** preparar e submeter proposta separada de **autorização executiva F1-02** para avaliação do responsável. A ratificação documental já ocorreu, mas **não autoriza o Codex a programar**.
