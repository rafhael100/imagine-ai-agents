# FT01/F202 — Jornada visual dos seis estágios da triagem F201

**11/11/2026. Exclusivamente laboratório estático e offline; produção NO-GO.**

## Entrega

A página navegável `examples/f108-laboratorio-navegavel.html` incorpora um novo painel de **Jornada de triagem dos agentes**. Os seis botões alternam estados fictícios da fila: Vazia, Pendentes, Em andamento, Incerteza, Pausada e Retomada.

Cada estágio informa um estado de triagem, uma observação informativa, contagens agregadas de pendentes, reservados, concluídos, indeterminados, revisões sem conclusão e estado de admissão. O retorno após a pausa **não** resolve a execução indeterminada. Os indicadores `NO_GO` permanecem explícitos. Os botões jamais executam um comando de fila, leem CRM, usam rede ou registram votos.

## Segurança e paridade

O payload JSON incorporado é o contrato `SYNTHETIC_QUEUE_TRIAGE_REHEARSAL_V1` de F201. O código JavaScript valida, antes de desenhar, chaves fechadas do envelope e de cada um dos seis snapshots, as flags `NO_GO`, os dez contadores com números inteiros esperados, a ordem dos estados, observações e admissão. O conteúdo é colocado apenas em `textContent`.

Sete testes Python obrigatórios executam o PHP F201 e comparam **todo o JSON**, verificam IDs ausentes, botões acessíveis, isolamento de F182/F196 e a sinalização de não autorização. O smoke DOM Node percorre os seis botões e confirma que não muda o painel existente.

**Baseline preservado: 851 regressões PHP, lint 106 arquivos + Python/DOM.**

```bash
python3 -m unittest discover -s tests/Contracts -p 'test_f202_triage_tab.py' -v
node tests/Browser/f108_smoke.cjs
EXPECTED_SHA="$(git rev-parse HEAD)" EXPECTED_PHP=106 EXPECTED_CASES=851 bash tools/run_r01_offline_quality_gate.sh
```

Não representa agente, executor, usuário, autenticação ou CRM em operação. CRM PR #57 OPEN/DRAFT, 13 testes reais OP-01 NAO_EXECUTADO, gates R01-G01..G11/CORE/SEG/AUT NO-GO. `main`, VPS, produção, segredos, dados reais, clientes e custos intactos.
