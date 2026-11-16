# AIA-P40 — Regressão cruzada do AI Agents com o mTLS real de laboratório CRM/Portal

16/11/2026. Continuação da P39 (#408), aproveitando a candidata independente CENTRAL-AUTO-P7 (#401) **sem mesclar branches nem reimplementar o transporte**.

Esta CI reproduz no mesmo runner um banco PostgreSQL16 descartável contendo schemas separados. Primeiramente executa as verificações completas da pilha AI Agents até P39 (recibo V2 assinado, revisões, rotação, evidência transacional, auditoria). Em seguida faz checkout em SHA fixo de três bases: CENTRAL-AUTO-P7 `7bd4b85a9621f518274800d9c0eb248abfbfff3b`, CRM `9afa6683e6b3463f12bd80a25f455f925828c0af` e Portal `9d3877dfaa81524faf2c08f80244b485968316ec`.

A pipeline instala no banco CI a ledger P7 de nonces restrita, executa **os scripts originais** do HTTPS/mTLS de loopback do CRM/Portal com certificado efêmero (somente `127.0.0.1:18787`), reinicia o servidor de teste e comprova replay negado, além de oito admissões concorrentes de nonce com um vencedor. Ao final repete a P39 para verificar que os dois namespaces não interferiram entre si. Não reinventa código de transporte nem usa um proxy/servidor externo.

**Os protocolos continuam distintos:** CENTRAL-AUTO-MTLS-REQUEST-V1 usa company UUID do CRM/Portal, enquanto AIA-N8N-ACK-RECEIPT-V2 é um recibo sintético do agente. A CI evidencia coexistência técnica, **não implementa a tradução entre company_id e tenant_ref, não fornece IAM operacional e não estabelece um canal mTLS produtivo para o Agent**.

O teste P7 de backup/restauração separado permanece documentado em #401; P40 não afirma reproduzir recuperação real em Leaseweb. Também faltam licenciamento, n8n privado, chaves de verdade, onboarding, revogação em todos os serviços, auditoria externa, LGPD e aceite humano. Nenhum merge, deploy, publicação DNS, cliente, fatura ou API paga de IA. **DRAFT / NO-GO / NO-MERGE / NO-DEPLOY / NO-SPEND.**
