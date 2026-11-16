# AIA-P15 — Boundary de política de ingresso, apenas sintético

16/11/2026. Candidata empilhada sobre AIA-P14 (#366).

O componente `MockPolicyBoundIngress` usa o contrato P12/P13 já existente e o adaptador AUTO-IA CONV-02 **fixado no SHA** 8b04d0e936168c77db028160b5aeb1758db497c4. Não cria um novo roteador paralelo nem incorpora outro ramo por merge.

O ingress exige política **injetada separadamente** do envelope, com empresa ativa, serviço fixo e concessões exatas somente para `run.status.project` e `usage.quota.evaluate`. Vincula os nove campos do envelope ao resultado canônico P7, exige verificação da assinatura **fictícia** do verificador original e rejeita replay no mesmo processo. Recusa tenant distinto, política revogada, operação sem grant, curinga, duplicação de grant, hash trocado, assinatura inválida, chave ausente, expiração e aprovação não mapeada.

**O resultado `LAB_POLICY_MATCH_NO_EFFECT` não significa acesso permitido ou tarefa entregue.** O armazenamento de nonce é apenas local ao processo e não resiste a reinicialização, concorrência, outros processos ou falha de rede. IAM real, autorização de negócio no serviço proprietário, assinatura e revogação reais, banco transacional, autenticação S2S e entrega ao n8n ainda requerem homologação. Não há SQL de produção, chamadas de IA, custos, segredos reais, merge ou deploy. **DRAFT / NO-GO / NO-MERGE / NO-DEPLOY**.
