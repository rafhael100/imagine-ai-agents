# AIA-R01/EVID-02 — Verificador offline de prontidão documental do piloto CRM OP-01

**Estado vigente (09/11/2026):** [PR #121](https://github.com/rafhael100/imagine-ai-agents/pull/121) MERGED apenas na dev (squash `9bf82e93466903e42dbbdaf981736b50a6ec8638`). [CI #42621128657](https://github.com/rafhael100/imagine-ai-agents/actions/runs/42621128657) **PASS** no SHA `bf5fabe8d151bf6d9988017b4df0cf74a4ec0e1f`: **15/15 testes Python**, **228/228 testes PHP**, **73/73 lint PHP**. A implementação valida **apenas integridade de inventário** e jamais autoriza o piloto; `pilot_decision=NO_GO`. Todos os gates seguem abertos.

## Finalidade
Conferir mecanicamente se a matriz de 14 gates (R01-G01..G11 e CRM CORE/SEG/AUT) e os 13 cenários OP-01 (11 negativos, 2 positivos) permanecem presentes, bem formados e sem declarações de aprovação ou execução sem prova independente.

O programa `tools/check_r01_readiness.py` usa somente a biblioteca padrão Python, lê exclusivamente os dois arquivos CSV indicados e emite JSON determinístico. Não consulta CRM, GitHub, servidores, serviços, clientes, chaves ou internet; não utiliza dados privados.

## Critérios automáticos
- Todos os 14 identificadores são obrigatórios uma única vez, com tipo correto, autoridade sugerida e descrição de evidência pendente.
- `ABERTO` e `EM_REVISAO` são estados administrativos descritivos permitidos. **Ambos mantêm NO_GO**. Qualquer `APROVADO`, `HOMOLOGADO`, `FECHADO` ou outro estado rejeita o registro até mudança do próprio processo por revisão humana.
- Exatamente 13 casos de homologação permanecem inventariados com expectativa imutável (`NEGAR` nos 11 negativos; `LEITURA_MINIMA` nos 2 positivos) e estado `NAO_EXECUTADO`.
- Rejeitar linhas duplicadas, faltantes, desconhecidas, colunas alteradas, célula vazia, valor de fórmula CSV ou declaração unilateral de caso executado.
- Mesmo com inventário válido, a saída é `pilot_decision=NO_GO`, `authorizes_deploy=false`, `authorizes_credentials=false` e `external_calls=0`. **Exit code 0 significa apenas que o arquivo passou na checagem de integridade, nunca GO.**

## Uso

```bash
python3 tools/check_r01_readiness.py
python3 -m unittest discover -s tests/Contracts -p test_r01_readiness.py -v
# Suíte PHP existente, se checkout Git completo:
EXPECTED_SHA="$(git rev-parse HEAD)" EXPECTED_PHP=73 EXPECTED_CASES=228 bash tools/validate_ft01_offline.sh
```

## Evidência e limite
O job público de CI em branch de desenvolvimento valida o inventário, seus testes negativos e a regressão PHP existente em SHA exato; números são **metas até CI passar**. A checagem não certifica autenticidade de revisores, aceite de terceiros, validade de certificados, controle de acesso ou dados; não resolve condições de R01-G01..G11/CRM CORE/SEG/AUT.

Nenhum merge no CRM, `main`, API real, SQL, concessão de privilégios, deploy, VPS ou gasto está autorizado. O contrato bilateral do CRM segue no [PR #57](https://github.com/rafhael100/imagine-crm/pull/57), DRAFT.
