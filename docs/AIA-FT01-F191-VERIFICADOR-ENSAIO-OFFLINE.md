# FT01/F191 — Verificador independente do ensaio F190

**11/11/2026 — laboratório sintético; operação e produção: NO-GO.**

## Entrega

O utilitário `tools/verify_f190_rehearsal.py` executa duas vezes o ensaio PHP F190 e exige igualdade determinística dos resultados, contrato externo fechado, dois snapshots `SYNTHETIC_QUEUE_SUPERVISION_V1`, fila PAUSED → OPEN, conservação permanente de `INDETERMINADO=1`, uma revisão irresolvida e nenhum campo explícito de referência/identidade. Qualquer erro falha fechado: retorna código diferente de zero com mensagem genérica e mantém `operational_decision=NO_GO`.

O verificador não aceita entrada de usuário, arquivo de clientes nem credenciais. Executa o PHP local já existente, sem instalar dependências. Não é prova de isolamento de sistema operacional, autenticação, auditoria durável, fila distribuída ou aprovação para produção.

## Execução

```bash
python3 tools/verify_f190_rehearsal.py
python3 -m unittest discover -s tests/Contracts -p 'test_f191_rehearsal_verifier.py' -v
EXPECTED_SHA="$(git rev-parse HEAD)" EXPECTED_PHP=103 EXPECTED_CASES=835 bash tools/run_r01_offline_quality_gate.sh
```

Nove regressões de contrato (oito negativas/positivas em memória e uma integração real PHP) e execução obrigatória no quality gate. **835 testes PHP/lint 103** permanecem como baseline de PHP; o acréscimo é Python. Sem alteração da `main`, VPS, deploy, usuários, dados reais, segredos ou custos. CRM PR #57 e 13 testes reais OP-01 continuam bloqueantes; R01-G01..G11/CORE/SEG/AUT NO-GO.
