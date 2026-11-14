# F222 — CI sem falso verde por caminho ou repositório privado

**14/11/2026 | NO-GO operacional.**

O fluxo `.github/workflows/r01-dev-integrity.yml` tinha filtros `paths` em push/PR, permitindo que mudanças apenas em `README`, `AGENTS.md`, `STATUS-ATUAL.md` ou outra documentação não acionassem a suíte. O `if: github.event.repository.private == false` no job inteiro também podia produzir resultado pulado depois de tornar o repositório **private**, sem demonstrar a checagem cruzada do CRM.

A F222 remove os dois filtros de caminho, mantendo o escopo de **branches DEV** do trigger. Troca o salto silencioso por etapa inicial com `exit 1` explícito quando o repositório estiver privado e a autenticação cruzada de leitura **não** tiver sido homologada. O resultado privado então é falha verificável, não uma CI verde enganosa. A solução futura para repositórios privados deverá definir acesso de leitura mínimo e revisão de credenciais separada — não criar token improvisado.

As regressões `test_ci_guardrails.py` e `test_f222_integrity_triggers.py` verificam triggers obrigatórios, proteção contra falsos positivos, checkout SHA fixo, leitura apenas, ausência de segredos e integração ao quality gate.

Sem nova permissão, custo, deploy, alteração de `main` ou integração real. **879 testes PHP / lint 110**, e os **14 gates** e **13 cenários** operacionais permanecem **NO-GO**. **CRM #57 OPEN/DRAFT**.
