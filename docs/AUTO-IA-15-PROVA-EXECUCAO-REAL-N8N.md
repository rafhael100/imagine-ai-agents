# AUTO-IA-15 — Prova real do resultado n8n sem consumir IA

**Base validada:** [AUTO-IA-14 #377](https://github.com/rafhael100/imagine-ai-agents/pull/377) usa imagem oficial n8n v2.47.7 pinada por digest, GitHub Actions, contêiner descartável com --network none, nenhuma porta exposta e nenhum acesso à VPS Leaseweb.

## Problema corrigido
A execução da CLI n8n sem erro não é, por si só, prova do valor do cálculo ou do consumo de IA. Agora o teste captura o JSON da execução real n8n execute --id ... --rawOutput e valida os nós e o resultado do n8n.

O verificador verify_n8n_execution.py exige somente os nós Manual Trigger + Code em uma execução bem-sucedida, com JSON fechado, **120 visitas, 12 leads e 2 clientes**, ai_api_calls=0, ai_tokens=0, commercial_credits_debited=0 e operational_authorization=false. Rejeita erros, execução extra, alteração de tenant, campos não previstos e resultados adulterados.

O modo --self-test cobre **16 situações negativas** incluindo créditos, tokens, contagem incorreta, erro no nó, dados indevidos no trigger e saída incompleta. Nenhum provedor ou cliente é usado.

## Evidência e limites
A CI executa uma única vez o n8n em contêiner temporário sem banco de clientes e sem chave de API de modelos, sem alteração na Leaseweb. O resultado ai_api_calls=0 é uma **declaração do fluxo**, não medição oficial de faturamento; a prova de ausência de acesso externo neste ensaio vem do Docker --network none e das verificações do host. Em produção o firewall/egress, orçamento, identidade e auditoria independente ainda são necessários.

Todos os PRs e o fluxo seguem em revisão e inativos. **DRAFT / NO-GO / NO-VPS / NO-AI-API / NO-MERGE.**
