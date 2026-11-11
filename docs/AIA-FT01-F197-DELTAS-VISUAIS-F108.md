# FT01/F197 — Diferença de estados no painel navegável F108

**11/11/2026. Somente HTML/JavaScript offline com dados F190. Produção: NO-GO.**

F196 já permite comparar PAUSED/OPEN, mas a mudança nas quantidades exige leitura manual. F197 apresenta três diferenças agregadas entre os estados de forma acessível: **pendentes −1**, **concluídas +1** e **indeterminadas 0**. A última diferença demonstra explicitamente que a incerteza não é apagada após a retomada.

Os valores são calculados no navegador apenas sobre os snapshots F190 submetidos a validação exata antes de qualquer desenho. A nova seção não faz requisições, não grava dados, não altera o JSON F182, nem executa qualquer admissão ou etapa de revisão. As duas seleções mantêm o mesmo delta e o aviso de simulação.

O smoke DOM Node verifica a representação antes e depois da alternância; cinco contratos Python verificam paridade exata ao PHP, invariantes dos números, rótulos acessíveis, ausência de inserção HTML não confiável e chamadas do smoke. Todos incorporados no gate existente, **835 testes PHP e lint 103 arquivos** preservados.

```bash
python3 -m unittest discover -s tests/Contracts -p 'test_f197_visual_delta.py' -v
node tests/Browser/f108_smoke.cjs
EXPECTED_SHA="$(git rev-parse HEAD)" EXPECTED_PHP=103 EXPECTED_CASES=835 bash tools/run_r01_offline_quality_gate.sh
```

**Sem CRM real, IAM, autenticação, deploy, VPS, credenciais, dados pessoais, clientes ou custo novo.** CRM PR #57 continua OPEN/DRAFT, 13 cenários operacionais OP-01 NAO_EXECUTADO, gates R01-G01..G11/CORE/SEG/AUT NO-GO. A branch main não é alterada.
