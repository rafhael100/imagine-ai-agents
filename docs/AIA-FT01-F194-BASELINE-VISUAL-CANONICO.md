# FT01/F194 — Baseline coerente na prévia visual F108

**11/11/2026 — Apenas correção de transparência da interface estática. Operação real: NO-GO.**

## Correção

A visão geral do laboratório F108 ainda destacava 85/85 testes PHP e 43 arquivos com lint, correspondentes a uma fase histórica F1-07. Embora rotulados como históricos, esses números eram apresentados como a última base. O código de desenvolvimento já atingiu 835 testes PHP e lint em 103 arquivos, conforme o STATUS canônico e as CIs pós-merge.

O painel passa a exibir 835 / 103 como baseline do CI offline F192 e afirma explicitamente que não constitui homologação de CRM, piloto ou autorização de produção.

Um contrato Python obrigatório compara os dois números data-baseline-cases/data-baseline-lint ao cabeçalho canônico de STATUS-ATUAL.md, impedindo deriva silenciosa quando a base evoluir. Também detecta os rótulos desatualizados e preserva a indicação permanente de simulação. Quatro verificações novas; mantém 835 testes PHP/lint 103.

## Execução

```bash
python3 -m unittest discover -s tests/Contracts -p 'test_f194_visual_baseline.py' -v
node tests/Browser/f108_smoke.cjs
EXPECTED_SHA="$(git rev-parse HEAD)" EXPECTED_PHP=103 EXPECTED_CASES=835 bash tools/run_r01_offline_quality_gate.sh
```

Sem integração real, armazenamento, segredo, CRM, IAM, VPS, main, deploy, cliente ou novos custos. CRM PR #57 OPEN/DRAFT, treze homologações reais NAO_EXECUTADO e R01-G01..G11/CORE/SEG/AUT NO-GO.
