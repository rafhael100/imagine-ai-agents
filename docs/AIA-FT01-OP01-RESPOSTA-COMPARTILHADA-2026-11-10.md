# OP-01 — contrato compartilhado de resposta pública sintética

**Escopo:** laboratório PHP offline com dados fictícios. **CRM real, IAM e pilotos: NO-GO.**

## Avanço de arquitetura

A proposta documental [CRM PR #57](https://github.com/rafhael100/imagine-crm/pull/57) descreve uma resposta ilustrativa OP-01 com cinco campos `contract/state/projection/real_authorization/external_calls` e dois estados. Enquanto CRM CORE/SEG/AUT, Privacidade, Arquitetura e Direção não aprovam uma implementação real, o consumidor fictício pode consolidar a verificação da resposta.

`MockCrmPublicResponseContract` implementa um único contrato **em memória** usado por:
- `MockCrmReadEdgeBoundary`: checa sucesso ou negação uniforme e só registra READ após telemetria.
- `MockToolDispatcher::readThroughBoundary`: aceita apenas `READ_SYNTHETIC_ONLY` após grant e política do dono; negação não vira `data` nem permissão.
- `MockReadOnlyPilot`: rejeita respostas falsas, não cria Run nem chama IA para leitura negada.

## Invariantes de entrada fechada

Cinco chaves obrigatórias em ordem canônica, contrato `SYNTHETIC_CRM_AIA_PUBLIC_V1`, `real_authorization=false` e `external_calls=0` em tipos exatos. `DENIED_OR_NOT_FOUND` aceita exclusivamente `projection=null` sem revelar recurso ou motivo. `READ_SYNTHETIC_ONLY` exige `MockCrmReadRequest` vinculado ao tenant/contexto do executor, projeção limitada a `ref/stage` solicitados, valores sintéticos de tamanho limitado e ordem exata; outros campos, tipo errado, versão desconhecida, stage de outro estado ou recurso estrangeiro são negados.

O verificador **não** confere autorização real, nem aceita bytes arbitrários de HTTP. Sua entrada é um array PHP fictício de código do próprio repositório e não define os futuros campos físicos do CRM. Isso evita que quatro consumidores evoluam com semânticas diferentes, mas **não prova paridade com backend Laravel ou RLS real**.

## Testes U76-01..12

Doze verificações adversariais: leitura de 1/2 campos; ordenação; negação uniforme; projeção revelada numa negação; chaves extras/faltantes; tipos e ordem de envelope; deriva de versão/estado; troca de empresa; recursos ou stages falsos; valor grande (1 MiB); campo privado injetado; resposta real do mock público após nonce usado.

Gate solicitado: **660 testes PHP, lint de 84 arquivos PHP, validadores Python offline e CI no SHA revisado**. Confirmação pós-merge requer CI independente.

## Bloqueios

**OP01-R01..R07, R01-G01..G11, CORE/SEG/AUT: NO-GO**. CRM PR #57 permanece OPEN/DRAFT. 13 cenários operacionais continuam `NAO_EXECUTADO`; nenhuma API, controller, rota, SQL, credencial, cliente, dado pessoal, autenticação real, VPS, implantação ou gasto foi introduzido. `main` não deve ser alterada.
