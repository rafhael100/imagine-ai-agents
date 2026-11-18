# AIA-P33 — Convergência somente de contratos com AUTO-IA RC04

16/11/2026. Etapa empilhada sobre P32 (#395), sem merges entre linhas.

O AUTO-IA RC04 (#387) já possui prova independente de importação/exportação e execução de workflow manual no **n8n oficial em contêiner GitHub Actions isolado**, com valores Visitas 120 → Leads 12 → Clientes 2, sem chamadas/consumo de IA segundo seus comprovantes. Esta P33 **não reexecuta o n8n**: lê fonte RC04 em SHA fixo `d3860e1e3d95b79ca3a2c6376651a62028820141`, reexecuta as verificações estáticas e mutações negativas do verificador do resultado, e compara o código do roteador AUTO-IA ao pin CONV-02 já utilizado no P32. Os hashes do roteador são idênticos.

Em paralelo, a mesma CI roda o teste P32 de 24 eventos determinísticos passando pelo fluxo protegido P31 e ledger P8, incluindo empresa sem saldo, e a regressão técnica P13–P31. O objetivo é verificar **coerência entre decisões NO_AI, contrato revisado de workflow e o ledger que não deve registrar reservas**.

A integração real dos serviços não é simulada como concluída: workflow de funil é produto `imagine-comercial`, eventos P31 são `imagine-ai-agents`. Ainda não existe ponte S2S, IAM e autorização de empresa real entre eles. Não conceder GO ou faturamento a partir deste ensaio.

Gates pendentes: autenticação S2S segura e revogável, conexão CRM/Portal, aceite do workflow no produto proprietário, n8n privado na VPS, backup/rollback, licenciamento e revisão independente. Nenhuma alteração de servidor, merge ou API paga. **DRAFT / NO-GO / NO-MERGE / NO-DEPLOY / NO-SPEND.**
