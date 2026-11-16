# AIA-P49 — Motor determinístico configurável de regras, sem efeitos externos

16/11/2026. Continuação sobre P48 (#424). Somente prévia sintética, não operacional.

## Entrega funcional

A classe src/Automation/DeterministicRuleEngine.php avalia um ruleset configurável contendo 1 a 8 regras em ordem, com versão e escopo de empresa sintéticos, contra o resultado do cálculo P48. Cada regra possui referência única, métrica allowlisted (visitas, leads projetados ou clientes projetados), operador (>=, <= ou igualdade), valor inteiro de comparação e natureza de proposta: HUMAN_REVIEW_DRAFT, CRM_TASK_DRAFT ou OPERATOR_NOTICE_DRAFT.

Somente propostas marcadas NOT_EXECUTED são retornadas. São rascunhos para revisão futura, não tarefas no CRM nem mensagens enviadas. O motor não abre rede, não acessa banco, não invoca n8n/IA, não executa código configurado pelo cliente e não cobra crédito. Fingerprints estáveis se vinculam a empresa, request, ruleset, versão, regra, métrica, entrada observada e tipo de proposta, para possibilitar conciliação futura sem simular persistência.

Um ruleset de três regras sobre o vetor P48 (120 visitas, 12 leads, 2 clientes) produz dois rascunhos elegíveis e rejeita a condição de visitas <=119. A identidade do tenant no ruleset deve coincidir com a identidade sintética do comando, mas esta conferência NÃO autentica o usuário real.

## Testes

A CI roda 2.500 cenários pseudoaleatórios além dos casos negativos: regras duplicadas, mais de 8 regras, array disperso, referências ilegais, métricas e operadores desconhecidos, tentativa de ação HTTP/WhatsApp, valores fora dos limites, campos extras, versão inválida, operação e tenant incoerentes. Propostas repetidas com os mesmos insumos preservam seu fingerprint. Uma nova versão ou request gera fingerprint diferente.

## Gatilhos de produção que ainda não existem

Nenhuma regra sai da prévia para execução sem identidade e permissão do proprietário CRM/Portal, autorização de ação, revogação distribuída, idempotência persistente, trilha de auditoria e, quando necessário, aceite humano. O codepath anterior permanece como desenvolvimento isolado; não há API ou worker liberados.

**DRAFT / NO-GO / NO-MERGE / NO-DEPLOY / NO-SPEND.**
