# AIA-FT01/F1-03 — Ordem executiva específica e rastreabilidade | 2026-11-08

> Estado: **autorização executiva do responsável recebida nesta conversa apenas para código PHP e testes sintéticos F1-03 em branch isolada.** Não há autorização de merge ou deploy.

## Histórico de autorização

1. O PR documental [#78](https://github.com/rafhael100/imagine-ai-agents/pull/78) submeteu F103-D01–D10. O responsável ratificou por mensagem **“aprovado”** as alternativas A **somente documentalmente**.
2. Foi apresentado ao responsável pedido separado: **“Você autoriza a programação e os testes sintéticos da F1-03 nessas condições, mantendo o merge e o deploy dependentes de novas aprovações?”**. O responsável respondeu **“sim”**.
3. Esta autorização inclui elaborar código e testes sintéticos, mas NÃO autoriza sua integração automática à base, uso de serviços reais ou ampliação de escopo.

## Branch e limites

- Repo: `rafhael100/imagine-ai-agents`.
- Base: `dev/ft01-aia-core-mocks-2026-11-06`. Branch filha: `dev/ft01-f1-03-resilience-mocks-2026-11-08`.
- A base já continha o PR #76 com F1-02. Preservar `main` (SHA observado `ec305688528fe35e9a9dc2d3b1bda1255cbd074a`), outros repositórios e a infraestrutura.
- Alterações limitadas a `src/MockResilience/`, `tests/Unit/` (somente inclusão de suíte no runner), `README.md`, `STATUS-ATUAL.md` e esta documentação.
- Sem alteração de `src/Contracts/`, `src/Domain/`, `src/MockOrchestration/`, `AGENTS.md` ou decisões documentais anteriores. Sem SQL, ALLOW, credenciais, dados pessoais, APIs reais, modelos pagos, Composer, serviços externos, GitHub Actions, VPS, Cloudflare, n8n, despesas ou deploy.

## Contrato funcional do lote

- Injeção determinística de perda de resposta de submit e falha temporária de consulta status, sem retries automáticos.
- Releitura somente de rastros sintéticos, com checagem de sequência/contexto/versão, conflito de provas e estado indeterminado seguro; nunca executar mutação durante replay.
- Sem comprovação de recuperação durável; após reinício sintético sem memória compartilhada, resultado indeterminado.
- 12 novos casos U03-01–U03-12; preservar 10 casos F1-01 e 12 F1-02. Total planejado de 34. Testes reais permitidos apenas localmente, com PHP 8.1+ previamente existente e sem instalação.

## Critérios de revisão

- PR de código **DRAFT** com destino somente à branch base; ninguém faz merge sem nova aprovação expressa.
- Lint e runner completos devem ser executados contra os arquivos reais do PR antes de declarar 34/34 PASS. Se o ambiente Codex estiver sem PHP, declarar **NÃO EXECUTADO** nesse ambiente.
- Validação local auxiliar contra reprodução funcional de classes F1-01/F1-02 não equivale a 34 testes no checkout integral nem a homologação de produção.
- Gates R01-G01–G11 e CORE/SEG/AUT CRM seguem ABERTOS. Nenhum compromisso com F1-04.
