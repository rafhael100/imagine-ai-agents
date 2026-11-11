# FT01/F181 — demonstração independente de estados da fila OP-01

**11/11/2026.** Exemplo PHP executável inteiramente sintético e offline. **NO-GO para CRM real, piloto e produção.**

## Como executar

Em checkout completo com PHP 8.1+ e Python 3 instalados anteriormente, sem baixar dependências:

```bash
php examples/f181-queue-observation-demo.php
python3 -m unittest discover -s tests/Contracts -p 'test_f181_queue_observation_demo.py' -v
EXPECTED_SHA="$(git rev-parse HEAD)" EXPECTED_PHP=92 EXPECTED_CASES=741 bash tools/run_r01_offline_quality_gate.sh
```

O CLI tem dados de uma única empresa inteiramente fictícia, ignora stdin, não aceita argv, não lê variáveis de ambiente e não executa rede, banco, CRM real, IA externa ou gateway de escrita.

## Cenários demonstrados

1. **Vazio:** antes do enqueue, `EMPTY` sem auditoria de leitura.
2. **Reserva ativa:** um job pendente é reclamado com lease local; polling individual e em lote retornam `IN_PROGRESS` com zero leituras adicionais.
3. **Prazo expirado:** polling converte a reserva para `INDETERMINADO` e expõe apenas `REVIEW_REQUIRED`, nunca `EMPTY` ou requeue.
4. **Progresso parcial:** um novo job com prova fictícia independente é concluído, enquanto o primeiro continua indeterminado. O lote sinaliza `REVIEW_REQUIRED`, mas contabiliza somente **1 processado / 1 sucesso / 0 negativas**, sem contar um segundo resultado inexistente.
5. **Privacidade:** saída JSON limitada a seis categorias de estado e contagens agregadas; nenhuma referência de empresa, lead, pessoa, Run, request, nonce, owner proof ou claim é exportada.

A demonstração exige internamente um único registro auditado de leitura, um nonce consumido, quatro eventos do coordenador e **zero submissões de efeitos**. A saída pública permanece `real_authorization=false`, `external_calls=0`, `write_calls=0`, `operational_decision=NO_GO`.

## Prova automatizada

`tests/Contracts/test_f181_queue_observation_demo.py` contém seis testes: JSON exato; determinismo sob entrada arbitrária; ausência de identificadores/segredos; ausência de transportes SQL/rede no código; contagens de progresso sem operações fantasmas; e retenção de marcadores NO-GO. O gate offline executa esse arquivo diretamente junto às verificações PHP e Python existentes. **Gate esperado: 741 testes PHP / lint 92 + Python.**

## Limites / próximos passos reais

Este CLI não tem worker, fila durável, prova autenticada, RLS/ACL, IAM ou homologação do CRM. Não prova consistência entre processos ou recuperabilidade após crash. CRM PR #57 continua **OPEN/DRAFT**, com 13 cenários reais `NAO_EXECUTADO` e R01-G01..G11/CORE/SEG/AUT **NO-GO**. Sem `main`, VPS, segredo, cliente, produção ou despesas.
