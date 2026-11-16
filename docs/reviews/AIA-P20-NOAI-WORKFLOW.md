# AIA-P20 — Prova de segurança do workflow NO_AI sem execução n8n

16/11/2026 — continuidade da P19 (#374). O workflow AUTO-IA-12 de Visitas → Leads → Clientes segue **manual e inativo**. Esta PR verifica os bytes exatos do workflow no checkout de AUTO-IA CONV-02 fixado em SHA e acrescenta um guard independente que **não executa** o JavaScript do n8n.

Mutações negativas: ativação automática, nó HTTP, credencial embutida, fetch/import externo, cobrança de créditos, tokens de IA, indicação de autorização, destino alterado e pinData com dados de cliente. A comparação com hash de blob evita mudança de código sem revisão de pin. Em paralelo, a matriz original `AutoIaRouter` é percorrida: todas as rotas determinísticas retornam NO_AI, todas as rotas semânticas exigem revisão, e nenhuma chama provedor durante classificação.

**Atenção:** testes estáticos e pinning não são sandbox de execução nem certificação de segurança de n8n. Antes de GO, exige instalação autenticada, inspeção de credenciais, licenciamento, redes privadas, monitoramento, autorização de cada produto e aprovação financeira. Nenhuma API de IA, n8n real, VPS, merge ou deploy. **DRAFT / NO-GO / NO-MERGE / NO-DEPLOY**.
