# FT01/F201 — Ensaio de triagem da fila com contrato JSON verificável

**11/11/2026 — PHP/Python exclusivamente offline. Operação real: NO-GO.**

## Entrega técnica

`examples/f201-queue-triage-demo.php` usa a classe F200 em uma sequência reproduzível de seis fotografias: `empty` (QUIET), `pending` (PENDING), `leased` (LEASE_IN_PROGRESS), `uncertain` (REVIEW_REQUIRED), `paused` (PAUSED) e `resumed` (REVIEW_REQUIRED).

O cenário cria duas requisições fictícias, permite expirar a primeira reserva, pausa a fila, registra dois pareceres sintéticos concordantes e conclui a segunda requisição depois da retomada. A primeira permanece INDETERMINADA. Não chama CRM, banco, modelo de IA, provedor de anúncios, serviço externo ou mecanismo de autorização.

A saída `SYNTHETIC_QUEUE_TRIAGE_REHEARSAL_V1` reúne somente contagens agregadas, estado de admissão, rótulos de triagem e flags `NO_GO`; nenhuma referência de run, claim, auditor ou usuário é emitida. Todo o script devolve falha genérica sem expor detalhes em caso de exceção.

## Verificação e qualidade

`tools/verify_f201_triage.py` executa o CLI duas vezes, recusa não determinismo, exige todas as etapas, contrato fechado, contagens inteiras exatas, invariantes do resultado indeterminado e ausência de referências privadas. A suíte `tests/Contracts/test_f201_triage_rehearsal.py` contém doze regressões, incluindo adulteração adversarial de JSON e paridade com a saída PHP. Ambos integram o gate R01 offline.

**Baseline PHP oficial: 851 casos, lint 106 arquivos**, além de Python/DOM. A atualização de lint é necessária porque o novo demo PHP entra no inventário oficial de exemplos.

```bash
php examples/f201-queue-triage-demo.php
python3 tools/verify_f201_triage.py
python3 -m unittest discover -s tests/Contracts -p 'test_f201_triage_rehearsal.py' -v
EXPECTED_SHA="$(git rev-parse HEAD)" EXPECTED_PHP=106 EXPECTED_CASES=851 bash tools/run_r01_offline_quality_gate.sh
```

A `main`, VPS, produção, clientes, dados reais, credenciais e custos não foram alterados. O CRM PR #57 permanece OPEN/DRAFT, treze cenários reais OP-01 NAO_EXECUTADO e gates R01-G01..G11/CORE/SEG/AUT NO-GO.
