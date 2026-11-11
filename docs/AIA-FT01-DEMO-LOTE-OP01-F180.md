# FT01/F180 — demonstração executável de lote OP-01 offline

**Estado:** demonstração local sintética, sem rede e sem CRM real. **Decisão operacional NO-GO.**

## Como executar

Em um checkout completo com PHP 8.1+ e Python 3 já disponíveis:

```bash
php examples/f180-queued-batch-demo.php
python3 -m unittest discover -s tests/Contracts -p 'test_f180_queued_batch_demo.py' -v
EXPECTED_SHA="$(git rev-parse HEAD)" EXPECTED_PHP=89 EXPECTED_CASES=711 bash tools/run_r01_offline_quality_gate.sh
```

O exemplo não aceita argumentos, variáveis de ambiente, credenciais ou entrada padrão e não instala nada. Constrói somente dados **fictícios** de uma empresa e de três pedidos de leitura em memória, passando pela mesma borda OP-01 do laboratório. Dois pedidos têm provas sintéticas distintas e autorização fictícia; o terceiro não apresenta provas. O resultado deverá ser: `processed=3`, `succeeded=2`, `denied=1`, `queue.completed=2`, `queue.failed=1`, `queue.pending=0`. Nenhuma tarefa usa gateway de escrita, serviço externo ou modelo pago.

A saída JSON contém somente contagens, categorias fixas, `mode=OFFLINE_SYNTHETIC_ONLY`, `operational_decision=NO_GO`, `real_authorization=false`, `external_calls=0`, `write_calls=0`. Não publica referências de tenant, recurso, pessoa, request, nonce, owner proof ou claim.

## Verificação independente do exemplo

A suíte Python `test_f180_queued_batch_demo.py` verifica:
1. saída JSON exata e contagens esperadas, sem mutação;
2. determinismo ao repetir a execução com entrada padrão arbitrária;
3. ausência de identificadores e mensagens potencialmente sensíveis;
4. ausência de funções de rede, SQL ou configuração externa no código;
5. flags de NO-GO e autorização falsa em todos os níveis.

O arquivo passa pelo lint PHP e pela verificação de isolamento sintético. A rotina de qualidade executa os cinco testes Python diretamente. **Nenhuma dessas verificações prova um sistema produtivo.**

## Bloqueios reais

CRM PR #57 continua **OPEN/DRAFT**, 13 cenários reais OP-01 **`NAO_EXECUTADO`**, gates R01-G01..G11 e CORE/SEG/AUT **NO-GO**. Não há worker real, fila distribuída, login, IAM, ACL, RLS, dados reais, webhook, rede, integração bilateral nem deploy. `main`, VPS, clientes e credenciais permanecem intocados.
