# F218 — Recibos descartados também por tentativas em fila e lote

**14/11/2026. Exclusivamente laboratório PHP offline, NO-GO.**

A F216 corrige a borda e a F217, o piloto supervisionado. Contudo, `MockQueuedReadOnlyPilot::runNext` e `MockQueuedBatchRunner::drain` podem negar uma tentativa **antes de invocar o piloto** (por exemplo campos proibidos, lote malformado, fila vazia ou indisponível). Nesses casos, a última leitura fictícia podia continuar com recibo utilizável, apesar de uma nova tentativa rejeitada.

A F218 expõe operações estritamente redutoras `discardReadReceipt` nos adaptadores e invalida o recibo antes da validação/claim. Também elimina qualquer recibo no tratamento de falhas de conclusão da fila, inclusive quando o piloto teve sucesso mas o ack da fila falhou.

A prova PHP `tools/check_f218_queue_receipt.php` utiliza as classes **reais do laboratório** e passa por: (1) um Run de fila concluído com recheck positivo; (2) input proibido negado sem consumir nova leitura; (3) lote inválido negado sem deixar recibo anterior; (4) fila vazia após novo sucesso que também invalida recibo. Uma prova nova e independente permite novos Runs concluídos sem mutações CRM. Testes Python de integração com CI complementam os ensaios.

**Baseline:** 879 testes PHP, 110 lint + testes executáveis F216–F218. CRM #57 OPEN/DRAFT; 13 cenários reais NAO_EXECUTADO; 14 gates NO-GO. Nenhum worker, API, IAM real, conta, credencial, cliente, VPS, deploy, `main`, custo ou cobrança.
