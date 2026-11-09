# FT01/SEC-05 — Assinatura de intenção vinculada à execução, etapa e idempotência

**Escopo:** correção de vínculo no modelo **100% sintético** de aprovações e execução PHP. Não habilita CRM real, identidade, ACL, credenciais nem produção.

## Inconsistência encontrada

Antes desta correção, `Intent::signature()` considerava apenas ID da intenção, contexto, operação e conteúdo. Duas intenções com o mesmo ID e conteúdo, mas criadas em execuções (`Run`) ou etapas (`Step`) distintas, podiam ter a mesma assinatura; a chave de idempotência também estava ausente. Como `Approval::assertValid` e as verificações de conflitos dependem dessa assinatura, uma aprovação fictícia emitida para um alvo podia ser confundida com a de outro.

## Ajuste conservador

A assinatura passa a incorporar, além dos elementos anteriores: **idempotencyKey**, **run ID** e **step ID**. A ordem é estável e os objetos de domínio permanecem imutáveis. Chaves de idempotência em si continuam escopadas a produto/empresa; reutilizá-las para uma intenção materialmente distinta resulta em `IDEMPOTENCY_CONFLICT`. Aprovação emitida para outra assinatura resulta em `APPROVAL_REQUIRED`, sem segundo envio.

Os testes `U19-01` a `U19-11` cobrem equivalência estável do mesmo alvo, mudança de run/step, alteração apenas da chave, reuso de aprovação, conflito no gateway, conflito no coordenador e uso legítimo de duas chaves independentes. A suíte oficial sobe de 246 para **257 casos PHP** com **75 arquivos para lint**. O gate offline unificado continua fixado ao SHA do commit validado.

## Limites e compatibilidade

É uma alteração intencional da assinatura **local e fictícia**, que muda hashes derivados e exige não comparar snapshots gerados por versões antigas como se fossem assinaturas estáveis de produção. Não existem dados reais nem migração de estado nesta branch; um protocolo real exigirá versionamento e aprovação bilateral independente.

**NO-GO operacional:** [CRM PR #57](https://github.com/rafhael100/imagine-crm/pull/57) permanece DRAFT; os gates R01-G01..G11 e CRM CORE/SEG/AUT seguem ABERTOS. Nenhum acesso, deploy, migração, gasto ou mudança na `main`.
