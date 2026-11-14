# F214 — Resumo automático no GitHub Actions: caminho crítico OP-01

**14/11/2026 — Somente acompanhamento de homologação, sempre NO-GO.**

O workflow `.github/workflows/r01-dev-integrity.yml` passa a publicar, após o quality gate obrigatório, o relatório Markdown gerado por `tools/plan_op01_release.py` no `GITHUB_STEP_SUMMARY` da própria execução de CI. O objetivo é mostrar diretamente a quem revisa o projeto o mapa de cinco frentes F212: **14 gates** abertos, **13 cenários** reais `NAO_EXECUTADO` e sete decisões independentes ainda não concedidas.

O resumo é derivado dos inventários CSV e do pacote bilateral versionado. O script falha antes de renderizar se o pacote estiver incorreto ou divergente; não escreve em GitHub, não modifica decisões, não instala dependências e não precisa de token. A autorização real não é inferida do sucesso de CI.

**Limites:** o produto continua laboratório sintético (**879 testes PHP / lint 110**). CRM #57 OPEN/DRAFT, R01-G01..G11 e CORE/SEG/AUT/LGPD NO-GO, sem backend CRM operacional, VPS, dados de clientes, segredos, cobrança, custos, alteração de `main` ou deploy. A futura homologação depende de pareceres independentes e ensaios reais.
