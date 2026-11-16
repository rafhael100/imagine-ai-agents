# AIA-P14 — Gates de segurança da convergência P12 ↔ AUTO-IA

Data: 16/11/2026. Base: PR #365 (AIA-P13), cuja CI de compatibilidade passou em `658a26b798c57ab4446eb146df064dbdc3111293`.

## Escopo e decisão

Este recorte reforça a **prova estática** de que a integração continua apenas em laboratório: o resultado `LAB_NO_AI_PROPOSAL_NOT_SIGNED` **não autoriza** chamada de IA, cobrança, entrega de evento ou execução de workflow. Permanece **NO-GO** para produção, merge ou deploy.

O teste P14 verifica que a CI P13:
- aponta a implementação AUTO-IA revisada por SHA imutável e confirma o SHA no runtime;
- mantém dois checkouts sem credenciais persistidas, token de leitura e sem gatilhos privilegiados;
- executa o ensaio original do adaptador, os bloqueios RC02 e o regressivo do roteador NO_AI;
- detecta mutações adversariais dos próprios gates (pin removido, permissão de escrita, regressão suprimida, gatilho privilegiado e declaração de autorização falsa).

## Condições obrigatórias para futuro GO

1. **IAM operacional**: autenticação real da origem, tenant e serviço, com autorização por operação e negação explícita em ausência de permissão. Não confiar em IDs fornecidos no payload.
2. **S2S**: assinatura/verificação com segredos operacionais rotacionáveis, validade curta, proteção contra replay, revogação e trilha de auditoria, testados em ambiente isolado.
3. **Ingestão transacional**: vincular inbox/outbox a empresa, evento, versão, idempotência, tentativa e ACK com fencing; falhar fechado em conflito ou timeout.
4. **IA sob aprovação humana**: operações NO_AI não podem chamar provedor. Operações AI exigem política e autorização do backend, aprovação **human** quando necessária, reserva de crédito e recibo válido antes da conciliação.
5. **n8n operacional**: homologar HTTPS, MFA no acesso administrativo, redes privadas, limites de recursos, backup/restauração, monitoramento e licença; workflow importável não é execução comprovada.
6. Revisão independente dos diffs P12 + AUTO-IA + adaptador, teste de concorrência e de isolamento entre empresas, avaliação de custos e rollback comprovado.

## Limites da evidência

A P14 não instala n8n, não conecta CRM/Portal, não exerce IAM real, não executa provedor de IA nem envia dados de clientes. Testes estáticos podem ser burlados por alterações não cobertas; não substituem revisão independente nem homologação de segurança. Não houve merge na main, custos ou deploy. **DRAFT / NO-GO / NO-MERGE / NO-DEPLOY.**
