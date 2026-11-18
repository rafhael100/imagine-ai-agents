# AIA-P29 — Corrida de dez workers no lease protegido P28

16/11/2026. Complemento do novo caminho de lease da P28 (#388), apenas em PostgreSQL16 descartável.

A P29 testa dez processos PHP concorrentes reservando o **mesmo evento sintético**. Deve existir exatamente um lease vencedor. Os demais observam a fila ocupada, sem duplicar processamento. Depois, o laboratório testa expiração em 60 segundos, rejeição de ACK antigo, segunda e terceira tentativas e quarentena DEAD ao atingir o limite de três. A empresa B permanece isolada, mesmo com o mesmo event_ref.

O ensaio reutiliza o contrato P6, o ledger P16, o stager P17 e a barreira P28. Não existe executor n8n real, credencial produtiva, webhook, cobrança ou chamada a IA. A validação do lease em CI **não** é autorização IAM; cada produto ainda precisa definir a fonte real de permissões e revogação.

**DRAFT / NO-GO / NO-MERGE / NO-DEPLOY.**
