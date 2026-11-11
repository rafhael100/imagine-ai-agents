# FT01/F192 — Contrato estrito de supervisão do ensaio F190

**11/11/2026 — Somente laboratório PHP/Python offline. Operação: NO-GO.**

## Defeito confirmado

O verificador F191 validava apenas três contagens da fila e uma do quadro de revisão, aceitando campos extras e alterações em outros contadores. Também não validava os indicadores de NO-GO em cada snapshot, apenas no envelope principal. Assim, uma amostra sintética adulterada poderia passar, mesmo reportando falsos resultados.

## Correção

- Ambos os snapshots exigem seis contagens da fila e quatro de revisão, com chaves fechadas, valores exatos e tipo int, recusando booleanos.
- Cada snapshot exige operational_decision=NO_GO, real_authorization=false, external_calls=0 e write_calls=0.
- Nove regressões adversariais reforçam o teste F191 que já integra o quality gate.
- Saída de falha continua genérica, sem revelar referências individuais.

## Execução

```bash
python3 -m unittest discover -s tests/Contracts -p 'test_f191_rehearsal_verifier.py' -v
python3 tools/verify_f190_rehearsal.py
EXPECTED_SHA="$(git rev-parse HEAD)" EXPECTED_PHP=103 EXPECTED_CASES=835 bash tools/run_r01_offline_quality_gate.sh
```

Baseline PHP mantido em 835 testes / lint 103. Sem CRM operacional, IAM, API, worker, persistência, deploy, dados reais, credenciais, novos custos ou mudança na main. CRM PR #57 OPEN/DRAFT, treze cenários OP-01 reais NAO_EXECUTADO e gates R01-G01..G11/CORE/SEG/AUT NO-GO.
