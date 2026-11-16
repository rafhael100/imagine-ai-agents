# AUTO-IA-03 — Ledger durável sintético sem autorização de IA

Continua [AUTO-IA-01 #340](https://github.com/rafhael100/imagine-ai-agents/pull/340), [AUTO-IA-02 #341](https://github.com/rafhael100/imagine-ai-agents/pull/341) e [DEC-AUTO-IA-001 #339](https://github.com/rafhael100/imagine-ai-agents/issues/339).

## Escopo implementado
- Laboratório **offline** Python SQLite com arquivo privado local, transações IMMEDIATE e atomicidade sob concorrência de processos independentes.
- Isolamento por organização, produto, política, evento e chave idempotente; rejeita operação fora do catálogo de oito candidatas e compara catálogo com roteador PHP.
- Orçamentos em inteiros separados: tokens estimados e custo estimado em **unidade monetária menor sintética**, sem conversões ou faturamento.
- Exposição conservadora: somatório das reservas ainda abertas ou das observações conciliadas. Quantidades desconhecidas são retidas e congelam novas reservas da política até revisão.
- Replays idênticos não duplicam exposição; divergência em identidade/payload é erro. Um resultado observado só pode ser substituído por um idêntico (não corrigir silenciosamente provas conflitantes).
- Prova de observação é deliberadamente um hash **sem assinatura**, e **NÃO é comprovante de provedor**.
- O resultado \`LAB_RESERVED_NO_AUTHORIZATION\` **não autoriza**, chama, agenda, debita ou libera uso de API de IA.

## Executar
\`\`\`bash
php tools/auto_ia/check_auto_ia.php
php tools/auto_ia/check_auto_ia_02.php
python3 tools/auto_ia/check_auto_ia_03.py
\`\`\`
Testes usam somente \`tempfile.TemporaryDirectory\` e o SQLite da biblioteca padrão Python. Nenhum banco da VPS ou ambiente real é acessado.

## Limites para produção
SQLite é exclusivamente referência testável para a próxima implementação PostgreSQL transacional com RLS, papel de menor privilégio, identidade assinada e segregação real de tenant. Não há cotas reais, gerenciamento de segredos, autorização financeira, prova de fornecedor, LGPD operacional ou execução n8n. É proibido implementar comando de autorização ou ponte para API paga a partir deste laboratório.

Próximo recorte: AUTO-IA-04 — demonstrar **conciliação sem cobrança** entre estimado, observado, unidades monetárias e créditos comerciais em registros separados; negativos de falha, incerteza e ajuste, preservando impossibilidade de ativar IA.
