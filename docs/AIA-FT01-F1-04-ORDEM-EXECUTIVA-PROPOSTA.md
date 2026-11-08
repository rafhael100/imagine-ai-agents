# AIA-FT01/F1-04 — Autorização executiva restrita ratificada | v0.1

> **ESTADO: F1-04 AUTORIZADO EXPRESSAMENTE EM 2026-11-08 PARA CÓDIGO MOCKADO E TESTES UNITÁRIOS SINTÉTICOS, SOMENTE CONFORME LIMITES DESTA ORDEM.** O responsável respondeu “sim” à pergunta explícita sobre autorizar o desenvolvimento do AIA-FT01-F1-04, sem merge, custos, integrações reais ou mudanças de infraestrutura. **Não há autorização para merge de código, implantação, F1-05 ou fechamento de gates.**
>
> **Data:** 2026-11-08. **Repo:** `rafhael100/imagine-ai-agents`. **Base documental:** `dev/ft01-aia-core-mocks-2026-11-06`. **Branch de minuta:** `docs/ft01-f1-04-autorizacao-executiva-proposta-2026-11-08`.
>
> **Precedência:** F104-D01 a D10, alternativas A, **APROVADAS SOMENTE DOCUMENTALMENTE** e integradas no PR #82 (squash `84f882bb4b9c5ed22514114491e5138cc599bb07`). **Além da ratificação documental, o responsável CONCEDEU AUTORIZAÇÃO EXECUTIVA EXPRESSA SEPARADA em 2026-11-08** para código e testes sintéticos F1-04, após preflight e somente nos limites deste documento. Nenhum código ou teste F1-04 é presumido executado pela autorização.
>
> **Gates:** R01-G01 a G11 **TODOS ABERTOS** e CORE/SEG/AUT CRM **ABERTOS**. `main` intacta; sem SQL/ALLOW, APIs reais, autenticação funcional, credenciais, dados privados, PHP/Composer instalado, GitHub Actions, despesas, VPS/Cloudflare/n8n, alterações nos produtos ou deploy.

## 1. Objetivo restrito do lote autorizado

Implementar, **sob autorização executiva expressa separada concedida em 2026-11-08**, um **catálogo local determinístico de ferramentas e capacidades fictícias** e um **despachante supervisionado puramente mockado**, aproveitando os modelos do F1-01, o coordenador F1-02 e a resiliência F1-03.

O laboratório deverá distinguir:
- **catálogo:** ferramenta fictícia existe, está ativa e tem versão de esquema suportada;
- **capability:** direito simulado associado a produto, tenant, principal, finalidade e recurso, proveniente de fixture independente de conteúdo do LLM;
- **requisição:** operação e parâmetros tipados/conhecidos, sem campos extras nem acesso a recursos fora do escopo;
- **decisão:** negar antes de chamar qualquer gateway quando faltar escopo, versão, aprovação ou informação confiável;
- **efeito:** separar leitura/sugestão sem mutação de uma operação **somente fake**, com objeto Approval apropriado, idempotência e consulta de status sem retry cego;
- **rastro:** motivo e referência mínimos sintéticos, nunca material/prompt/lead integral.

**Não é objetivo** criar clientes HTTP, schemas/API homologados com o CRM, autenticação/ACL real, automações configuráveis de clientes, filas, persistência, motor LLM, n8n ou custos.

## 2. Branch de código autorizada, criação somente após preflight

| Item | Regra de autorização proposta |
| --- | --- |
| Repositório | `rafhael100/imagine-ai-agents` |
| Branch base de desenvolvimento | `dev/ft01-aia-core-mocks-2026-11-06` |
| Branch de implementação autorizada — **ainda não criada no preflight inicial** | `dev/ft01-f1-04-tool-catalog-mocks-2026-11-08` |
| Ponto inicial | SHA corrente da base aferida via GitHub autenticado antes da criação |
| Destino do PR de código | **Draft filha → branch base**, sem merge |
| `main`, outros produtos e VPS | Inalterados |
| Autorização atual | **F1-04 código mockado e testes sintéticos EXPRESSAMENTE AUTORIZADOS**, sem merge de código/integrações reais/deploy |

A autorização executiva foi concedida em 2026-11-08; confirmar a inexistência de branch de código antes de criá-la, sempre após preflight; se já existir, examinar SHA e conteúdo sem sobrescrever ou dar force-push. Divergências exigem intervenção humana.

## 3. Preflight obrigatório para executor autorizado

1. Conferir `gh auth status`, `git remote -v`, `git status`, `git branch --show-current` e `git ls-remote`; verificar SHA da base e da `main` sem forçar atualização.
2. Ler `AGENTS.md`, `README.md`, `STATUS-ATUAL.md`, AIA-04, AIA-07, AIA-08, AIA-09, R04/S01 e S02, R01/R02/R03, documentos e testes F1-01–F1-03, proposta F1-04 e esta minuta.
3. Verificar ausência de mudanças não autorizadas, contratos reais, tokens, dados pessoais e colisão com arquivos/nomes existentes. Se precisar mudar contratos ou classes consolidadas anteriores, **parar**.
4. Verificar se PHP CLI 8.1+ **já existe**. Proibido instalar PHP, Composer, PHPUnit, extensões ou SDKs; na ausência, registrar lint/testes não executados, sem inventar resultados.
5. Anotar SHA base, ambiente, comandos propostos, arquivos-alvo e limites da autorização antes de trabalhar.

## 4. Superfícies autorizadas sob esta decisão executiva

| Superfície | Permitido após preflight, por autorização expressa | Proibido |
| --- | --- | --- |
| `src/MockTooling/` | Catálogo puro, validador de requisição/schema, política de capability fixture e despacho simulado | HTTP, auto-discovery de APIs, bindings de serviços reais, eval/código dinâmico |
| `src/Mocks/` | Adaptadores mínimos de fake leitura/sugestão/mutação compatíveis com gateways existentes | Substituir autoridade de `CrmEffectGateway` ou permitir bypass de aprovação |
| `src/Domain/` | Novos value objects pequenos se indispensáveis e sem incompatibilizar F1-01/02/03 | Alterar assinaturas públicas ou estados estáveis para acomodar testes |
| `tests/Unit/` | 12 novos casos U04-01 a U04-12, inclusão mínima no runner, **preservando 34 regressões** | Teste em CRM/Portal real, GitHub Actions, consumo externo |
| `README.md`, `STATUS-ATUAL.md` | Documentar diff, limitações, execuções reais e gates mantidos | Afirmar homologação, autorização real ou resultados não executados |

**Sem autorização para modificar:** `AGENTS.md`, `src/Contracts/`, `src/MockOrchestration/SupervisedCoordinator.php`, `src/MockResilience/ReadOnlyTraceReview.php`, documentos ratificados, `.github/workflows/`, `main`, CRM, Portal, Sites, Performance AI, Universidade, outras aplicações ou infraestrutura.

Não criar `composer.json`, lockfiles, dependências, migrations, bancos, filas, código HTTP, clientes de IA real, `.env`, Dockerfile, ações de CI, SDKs ou armazenamento de produção. Se algo assim for imprescindível, **não implementar**, submeter nova decisão.

## 5. Invariantes de segurança do laboratório

1. **Capability não é identidade real:** nenhuma string fornecida por usuário, IA ou documentação confere direito de executar. Fixture de grant deve vir de injeção separada, com contexto conferido.
2. **Propriedade do CRM:** produção exigirá autenticação e autorização final no próprio CRM; um resultado fake não é prova de aceite desse backend.
3. **Fail-closed:** tool desativada, desconhecida ou versão/schema não suportados bloqueiam o despacho sem fallback permissivo.
4. **Campos mínimos:** leituras retornam somente whitelist permitida no mock; erros não enumeram leads de outro tenant.
5. **Sugestão sem efeito:** texto de LLM fictício não concede Approval, não emite task e não chama submit.
6. **Mutação fake supervisionada:** Approval válida para intenção/material, contexto e janela temporal exatos; revalidar antes do único submit fake.
7. **Sem duplicação:** timeout/resultado indeterminado exige consulta fake de status, nunca reenvio cego de submit. Chave com material divergente → conflito explícito.
8. **Isolamento:** tenant, produto, finalidade, recurso, principal, capability e versão são vinculados; divergência bloqueia sem vazamento.
9. **Trace mínimo:** IDs e códigos sintéticos fechados; nenhum payload pessoal, prompt bruto, exceção crua, token ou stack trace.
10. **Relógio explícito:** tempos simulados reproduzíveis, sem sleeping, agendamento ou relógio de produção.
11. **Quota sintética:** limitar/contar somente em memória no teste; não alterar créditos ou billing real.
12. **Não homologar:** os testes mockados não comprovam segurança real, ACL, LGPD, persistência, concorrência, transações, integração CRM nem compatibilidade em todas as versões PHP.

## 6. Matriz de testes futuros — 12 novos + 34 regressões = 46

| Caso futuro | Resultado exigido |
| --- | --- |
| U04-01 | Leitura fake com capability/whitelist válida retorna somente campos permitidos |
| U04-02 | Sugestão com prompt adversarial não grava ou eleva privilégio |
| U04-03 | Tool inexistente/desativada ou versão desconhecida é recusada |
| U04-04 | Parâmetro extra/tipo inválido/campo proibido/payload grande é recusado |
| U04-05 | Concessão ausente/revogada/expirada/de outra capability bloqueia |
| U04-06 | Produto/tenant/principal/finalidade/recurso incompatíveis não vazam |
| U04-07 | Efeito fake exige Approval válida e gera exatamente uma solicitação |
| U04-08 | Approval expirada/revogada/material diferente bloqueia novo envio |
| U04-09 | Repetir intenção/chave compatíveis consulta status sem segundo submit |
| U04-10 | Chave idempotente com material conflitante gera erro seguro |
| U04-11 | Timeout + status mockado indisponível mantém indeterminação sem retry |
| U04-12 | Trace e retorno não revelam token/prompt/lead sintético introduzido na fixture |

Os **34 testes anteriores** do F1-01–03 devem continuar intactos e, se houver autorização de implementação e execução, ser reexecutados no **mesmo checkout do PR de código**. Meta futura: **46 PASS/0 FAIL**. **Nenhum teste F1-04 foi executado nem autorizado por esta minuta.**

Comandos futuros, em ambiente **já provido de PHP 8.1+**:
```sh
find src tests/Unit -type f -name '*.php' -print0 | xargs -0 -n1 php -l
php tests/Unit/run.php
```

Registrar `php -v`, SHA, hashes e saídas reais; se PHP indisponível, declarar **NÃO EXECUTADO** sem instalar PHP/Composer e sem usar GitHub Actions. A execução local só será considerada validada se os arquivos PHP corresponderem ao commit de código examinado; commits posteriores exigem reavaliação.

## 7. Condições de parada e reporte de bloqueios

Interromper se houver necessidade de modificar módulos/contratos anteriores, falha Git autenticado, branch divergente, instalação de pacotes, APIs/LLM reais, despesas, segredos, dados pessoais, esquema de banco, SQL, ALLOW, VPS/Cloudflare/n8n, workflows GitHub Actions, motores de automações CRM ou acesso aos repositórios de outros produtos. Não deduzir permissão de commit anterior, aprovação funcional, comentário ou conclusão do Codex.

## 8. Entregas e política de revisão futuras

- Código limitado às superfícies acima, sob **autorização executiva expressa concedida em 2026-11-08** e somente após preflight Git.
- 12 testes unitários novos e preservação rigorosa dos 34 anteriores; evidências reais/limitações em `STATUS-ATUAL.md`.
- PR de código **draft da branch filha → branch dev**, sem merge.
- Relato da SHA da base, commits, diff, testes/lint e confirmações de ausência de CI/custos/infra.
- Nova autorização **independente** para eventual merge SOMENTE na branch dev; nunca `main`.
- F1-05 bloqueado até autorização própria.

## 9. Autorização executiva concedida em decisão separada

> **Autorizo expressamente executar AIA-FT01-F1-04**, exclusivamente no repositório `rafhael100/imagine-ai-agents`, em branch filha `dev/ft01-f1-04-tool-catalog-mocks-2026-11-08` criada após preflight autenticado a partir de `dev/ft01-aia-core-mocks-2026-11-06`. Autorizo somente PHP puro sintético de catálogo/capabilities e despacho supervisionado de ferramentas fake, validações fail-closed de escopo, versão e parâmetros, auditoria mínima, até 12 casos U04-01–U04-12 e regressão dos 34 existentes, nos diretórios `src/MockTooling/`, `src/Mocks/`, `src/Domain/` se necessário, `tests/Unit/`, `README.md` e `STATUS-ATUAL.md`. Rodar lint e até 46 testes locais somente se PHP 8.1+ já estiver disponível, sem instalações; registrar não executados se indisponível. Criar PR draft filha → branch dev, **sem merge**. Não autorizo alterar `main`, `AGENTS.md`, `src/Contracts/`, `SupervisedCoordinator`, `ReadOnlyTraceReview`, outros repositórios, banco/SQL/ALLOW, APIs reais, dados privados, credenciais, PHP/Composer instalados, GitHub Actions, infraestrutura, custos, deploy, F1-05 nem gates R01-G01 a G11/CORE/SEG/AUT CRM. Diante de divergência, parar e reportar.

A autorização executiva **FOI CONCEDIDA em 2026-11-08**, quando o responsável respondeu **“sim”** à pergunta explícita: **“Você autoriza expressamente o desenvolvimento do AIA-FT01-F1-04, limitado ao código PHP simulado e aos testes unitários sintéticos, sem merge, custos, integrações reais ou alterações na infraestrutura?”**. O texto acima é a **ordem operacional restrita**; não autoriza merge do PR de código, F1-05, dados reais, gastos, deploy ou alteração de gates.

## 10. Próximas decisões

- [x] F104-D01 a D10, alternativas A, **ratificadas SOMENTE documentalmente** em 2026-11-08, PR #82.
- [x] O responsável **autorizou expressamente a execução F1-04** com PHP mockado e testes sintéticos em 2026-11-08, sem merge/infra/gastos.
- [ ] Verificar preflight Git, SHA/branch e limites de arquivos antes de iniciar código.
- [ ] Criar/verificar branch filha, Codex implementar em PR draft **sem merge**, e validar até 46 cenários onde PHP já disponível.
- [ ] Autorização de merge específica após revisão; manter `main`/F1-05/gates bloqueados.

**Estado vinculante:** F1-04 DOCUMENTAL aprovado e **EXECUÇÃO DE CÓDIGO PHP MOCKADO + TESTES SINTÉTICOS EXPRESSAMENTE AUTORIZADA**, sob preflight e escopo restrito. **MERGE DE CÓDIGO, F1-05, PRODUÇÃO, INTEGRAÇÕES REAIS E DEPLOY NÃO AUTORIZADOS.** Gates R01-G01 a G11 e CORE/SEG/AUT CRM continuam ABERTOS.
