# F215 — Caderno fechado de 13 cenários de homologação CRM ↔ Agents

**14/11/2026 — Fase B sem aceite / NO-GO.**

O programa `tools/build_op01_testbook.py` transforma o pacote oficial OP-01 e o plano F212 em **13 especificações distintas** de homologação futura, com os objetivos técnicos de N01–N11 e P01–P02, revisores sugeridos, pré-condições, evidências exigidas e respostas esperadas. Não emite chamadas, tokens, endpoint, comandos de produção ou dados de clientes. Resultado esperado não equivale a resultado observado.

Uso local:
```bash
python3 tools/build_op01_testbook.py --check
python3 tools/build_op01_testbook.py --format markdown
python3 tools/build_op01_testbook.py --format json
```

O caderno rejeita mudanças não aprovadas no inventário e no pacote, exige 11 negativos + 2 positivos e **13 NAO_EXECUTADO**. A CI executa o preflight e nove regressões adversariais. Os testes PHP permanecem **879/lint 110**; nenhum teste real foi executado.

**Gates ainda pendentes:** 14 gates, sete decisões, [CRM PR #57](https://github.com/rafhael100/imagine-crm/pull/57) OPEN/DRAFT, R01-G01..G11 e CORE/SEG/AUT/LGPD NO-GO. O uso do caderno somente pode começar em staging após as revisões independentes. Sem VPS, `main`, deploy, credenciais ou custos.
