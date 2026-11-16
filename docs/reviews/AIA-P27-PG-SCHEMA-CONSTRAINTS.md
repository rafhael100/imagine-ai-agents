# AIA-P27 — CHECK constraints nativas em PostgreSQL16

16/11/2026. Continuidade da P26 (#385).

O ledger P16 e o outbox P6 possuíam validação em PHP, mas seu schema PostgreSQL de laboratório ainda admitia algumas entradas malformadas em escritas diretas. Este recorte adiciona CHECK constraints nativas para referências sintéticas, hash hexadecimal e limite em bytes de conteúdo canônico do outbox. Também impede identificadores de worker não sintéticos.

O teste adversarial executa inserções diretamente no banco CI descartável, contornando de propósito a validação PHP. Comprova rejeições por SQLSTATE 23514 e ausência de linhas parciais. A CI repete a regressão integrada P13–P26 para verificar que envelopes e filas legítimos continuam funcionando.

**Não é validação criptográfica do SQL:** uma string hexadecimal sintaticamente válida não comprova sua correspondência ao envelope. Isso permanece coberto pela auditoria P23/P25 e pelo backend. Também não introduz IAM verdadeiro, autorização de serviço, assinatura de provedor, integração n8n real ou credenciais operacionais.

Sem acesso à VPS, custos, merge, deploy ou dados reais. **DRAFT / NO-GO / NO-MERGE / NO-DEPLOY.**
