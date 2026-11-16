# Agents P8 — reserva transacional e reconciliação conservadora de créditos

**16/11/2026 — somente PostgreSQL16 efêmero CI, SEM cobranças ou chamada de API.**

A principal preocupação comercial e técnica da Imagine é consumir créditos de IA **somente se a tarefa realmente necessitar de modelo**. A P5 já propõe tarefas determinísticas gratuitas antes de qualquer chamada. A P8 prepara, em banco de laboratório, o estágio **posterior à elegibilidade**: reservar orçamento antes de uma eventual chamada, impedir duplicidades, contabilizar o consumo efetivamente observado e tratar corretamente a incerteza.

## Transições do laboratório

1. **RESERVED:** deduz da disponibilidade o valor máximo estimado, dentro de uma transação PostgreSQL `SELECT FOR UPDATE`. Nenhuma API é chamada.
2. **CANCELED:** libera a reserva exclusivamente se a tarefa for cancelada **com prova de que não houve despacho**; uma tentativa após timeout é recusada.
3. **UNCERTAIN:** se a execução remota tiver resultado desconhecido, **a reserva permanece integralmente bloqueada**, para não gastar novamente crédito que talvez tenha sido usado.
4. **FINALIZED:** quando houver medição confirmada e compatível, debita **apenas o consumo real** e libera o excedente reservado. Uma prova idêntica repetida não pode debitar outra vez.
5. **DISPUTED / frozen:** se surgir medição contraditória ou superior à reserva, congela a empresa e não executa reembolso ou cobrança automática. É necessária conciliação independente.

Tudo é etiquetado com identificadores `SYNTHETIC_...` e se refere a unidades técnicas inteiras; os números **não correspondem a dinheiro, planos ou tokens reais de fornecedores**. O banco é `imagine_agents_ci` na ação descartável, nunca o CRM ou a VPS. O ensaio cria tabelas `aia_lab_` e usa o superusuário temporário da CI, não um papel operacional.

## Testes e concorrência

Dez processos PHP independentes disputam uma única conta fictícia com 100 unidades de saldo e reserva unitária de 20. Exatamente cinco devem conseguir, cinco devem ser negados; nunca pode existir overbooking. A suíte também cobre idempotência, mesmo request com finalidade alterada, timeout sem liberação, cancelamento pré-despacho, reconciliação tardia com medição observada, conflito de medidas que congela a conta, isolamento de duas empresas e falha de banco sem fallback.

**O que continua faltando para negócio:** autenticador S2S e IAM, reservas com preço/versionamento real do provedor e permissão por empresa, assinatura de medições verificadas, processamento assíncrono e outbox P6 com ACK n8n autenticado, faturamento tributável, chargeback/reembolso auditável, regras de créditos contratados, retenção LGPD e supervisão de segurança. A autorização dos 14 gates/7 decisões/13 cenários do [CRM OP-01 #57](https://github.com/rafhael100/imagine-crm/pull/57) permanece independente.

Não há provider de IA, n8n, escrita de CRM, secrets, deploy, merge ou cobrança por esta PR.
