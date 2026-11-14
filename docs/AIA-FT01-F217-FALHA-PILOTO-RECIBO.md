# F217 — Encerramento fail-closed do piloto somente leitura

**14/11/2026 — laboratório sintético; NO-GO operacional.**

A F216 invalida o recibo da fachada quando uma nova tentativa é negada no edge. A F217 cobre a janela **depois de uma leitura positiva fictícia e antes da conclusão de uma sugestão supervisionada**: se o provedor lançar exceção, produzir conteúdo incompatível ou o coordenador falhar, a resposta externa é negativa, porém o recibo não deve continuar disponível para `recheck`.

A `MockCrmReadEdgeBoundary::discardReadReceipt` publica uma operação estritamente redutora de capacidade, sem executar leitura ou consumir nonce. `MockReadOnlyPilot::suggest` a chama antes do preflight de **qualquer nova invocação**, também quando o resultado da leitura não atende ao contrato, e em todo `catch` de encerramento. O caminho de sucesso continua podendo revalidar o mesmo recibo sem leitura adicional.

O ensaio PHP `tools/check_f217_pilot_receipt.php` utiliza a fachada, edge, coordinator e piloto **reais do laboratório**. Exercita quatro blocos: sucesso e recheck legítimo; nova tentativa inválida que descarta a prova anterior sem consumir recursos; provedor que lança erro após a leitura; e provedor que forja texto incompatível. Em negativas, recibo é invalidado; prova fresca permite novo sucesso. Um contrato Python adicional vincula as alterações ao quality gate.

**Limites:** baseline 879 PHP / 110 lint preservado; novos testes PHP e Python integrados à CI. Nenhuma chamada ao CRM real, IAM, API, LLM, cliente, token, VPS, gasto ou deploy. CRM #57 continua OPEN/DRAFT, os 13 cenários operacionais `NAO_EXECUTADO` e todos os 14 gates NO-GO.
